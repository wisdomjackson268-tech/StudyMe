<?php
/**
 * StudyMe AI Platform — Quiz Helper Functions
 */

/**
 * Get all published quizzes for a course.
 */
function get_course_quizzes($courseId) {
    $pdo = getDBConnection();
    try {
        $stmt = $pdo->prepare("SELECT * FROM quizzes WHERE course_id = ? AND status = 'published' ORDER BY created_at DESC");
        $stmt->execute([$courseId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Get quizzes for a teacher's courses.
 */
function get_teacher_quizzes($teacherId) {
    $pdo = getDBConnection();
    try {
        $stmt = $pdo->prepare("
            SELECT q.*, c.title AS course_title,
                   (SELECT COUNT(*) FROM questions WHERE quiz_id = q.id) AS question_count,
                   (SELECT COUNT(*) FROM quiz_attempts WHERE quiz_id = q.id) AS attempt_count
            FROM quizzes q
            JOIN courses c ON q.course_id = c.id
            WHERE c.teacher_id = ?
            ORDER BY q.created_at DESC
        ");
        $stmt->execute([$teacherId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Get a quiz by ID.
 */
function get_quiz_by_id($quizId) {
    $pdo = getDBConnection();
    try {
        $stmt = $pdo->prepare("SELECT q.*, c.title AS course_title FROM quizzes q JOIN courses c ON q.course_id = c.id WHERE q.id = ? LIMIT 1");
        $stmt->execute([$quizId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Get questions for a quiz with their options.
 */
function get_quiz_questions($quizId) {
    $pdo = getDBConnection();
    try {
        $stmt = $pdo->prepare("SELECT * FROM questions WHERE quiz_id = ? ORDER BY sort_order ASC, id ASC");
        $stmt->execute([$quizId]);
        $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($questions as &$q) {
            $stmt2 = $pdo->prepare("SELECT * FROM question_options WHERE question_id = ? ORDER BY sort_order ASC");
            $stmt2->execute([$q['id']]);
            $q['options'] = $stmt2->fetchAll(PDO::FETCH_ASSOC);
        }
        return $questions;
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Start a quiz attempt.
 */
function start_quiz_attempt($quizId, $studentId) {
    $pdo = getDBConnection();
    try {
        $stmt = $pdo->prepare("INSERT INTO quiz_attempts (quiz_id, student_id, started_at) VALUES (?, ?, NOW())");
        $stmt->execute([$quizId, $studentId]);
        return (int)$pdo->lastInsertId();
    } catch (Exception $e) {
        error_log("start_quiz_attempt error: " . $e->getMessage());
        return false;
    }
}

/**
 * Submit a quiz attempt with answers.
 */
function submit_quiz_attempt($attemptId, $quizId, $studentId, array $answers) {
    $pdo = getDBConnection();
    try {
        $pdo->beginTransaction();

        $questions = get_quiz_questions($quizId);
        $totalPoints = 0;
        $earnedPoints = 0;

        foreach ($questions as $question) {
            $totalPoints += (float)$question['points'];
            $selectedOptionId = $answers[$question['id']] ?? null;

            $isCorrect = false;
            $pointsEarned = 0;
            if ($selectedOptionId) {
                $stmt = $pdo->prepare("SELECT is_correct FROM question_options WHERE id = ? AND question_id = ?");
                $stmt->execute([$selectedOptionId, $question['id']]);
                $opt = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($opt && $opt['is_correct']) {
                    $isCorrect = true;
                    $pointsEarned = (float)$question['points'];
                    $earnedPoints += $pointsEarned;
                }
            }

            $stmt = $pdo->prepare("INSERT INTO quiz_answers (attempt_id, question_id, option_id, is_correct, points_earned) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$attemptId, $question['id'], $selectedOptionId, $isCorrect ? 1 : 0, $pointsEarned]);
        }

        $percentage = $totalPoints > 0 ? round(($earnedPoints / $totalPoints) * 100, 2) : 0;

        // Check passing score
        $quizData = get_quiz_by_id($quizId);
        $passed = $percentage >= (float)($quizData['passing_score'] ?? 50);

        $stmt = $pdo->prepare("UPDATE quiz_attempts SET score = ?, percentage = ?, passed = ?, submitted_at = NOW() WHERE id = ?");
        $stmt->execute([$earnedPoints, $percentage, $passed ? 1 : 0, $attemptId]);

        $pdo->commit();
        return ['percentage' => $percentage, 'passed' => $passed, 'earned' => $earnedPoints, 'total' => $totalPoints];
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log("submit_quiz_attempt error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get a quiz attempt result.
 */
function get_quiz_attempt($attemptId) {
    $pdo = getDBConnection();
    try {
        $stmt = $pdo->prepare("SELECT qa.*, q.title AS quiz_title, q.passing_score FROM quiz_attempts qa JOIN quizzes q ON qa.quiz_id = q.id WHERE qa.id = ? LIMIT 1");
        $stmt->execute([$attemptId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Get all quizzes available to a student (from their enrolled courses).
 */
function get_student_available_quizzes($studentId) {
    $pdo = getDBConnection();
    try {
        $stmt = $pdo->prepare("
            SELECT q.*, c.title AS course_title,
                   (SELECT COUNT(*) FROM questions WHERE quiz_id = q.id) AS question_count,
                   (SELECT qa.percentage FROM quiz_attempts qa WHERE qa.quiz_id = q.id AND qa.student_id = ? ORDER BY qa.submitted_at DESC LIMIT 1) AS last_score,
                   (SELECT COUNT(*) FROM quiz_attempts qa WHERE qa.quiz_id = q.id AND qa.student_id = ?) AS attempt_count
            FROM quizzes q
            JOIN courses c ON q.course_id = c.id
            JOIN enrollments e ON e.course_id = c.id
            WHERE e.student_id = ? AND q.status = 'published'
            ORDER BY q.created_at DESC
        ");
        $stmt->execute([$studentId, $studentId, $studentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}
