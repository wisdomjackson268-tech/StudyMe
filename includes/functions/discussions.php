<?php
/**
 * StudyMe AI Platform — Lesson Questions & Q&A Discussion Helpers
 */
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once __DIR__ . '/notifications.php';

/**
 * Get all questions asked on a specific lesson, with their replies and author details.
 */
function get_lesson_questions($lessonId) {
    $pdo = getDBConnection();
    try {
        $stmt = $pdo->prepare("
            SELECT q.*, 
                   u.first_name, u.last_name, u.role, u.avatar
            FROM lesson_questions q
            JOIN users u ON q.user_id = u.id
            WHERE q.lesson_id = ?
            ORDER BY q.created_at DESC
        ");
        $stmt->execute([(int)$lessonId]);
        $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch replies for each question
        if (!empty($questions)) {
            $stmtReplies = $pdo->prepare("
                SELECT r.*, 
                       u.first_name, u.last_name, u.role, u.avatar
                FROM lesson_question_replies r
                JOIN users u ON r.user_id = u.id
                WHERE r.question_id = ?
                ORDER BY r.created_at ASC
            ");

            foreach ($questions as &$q) {
                $stmtReplies->execute([(int)$q['id']]);
                $q['replies'] = $stmtReplies->fetchAll(PDO::FETCH_ASSOC);
            }
            unset($q);
        }

        return $questions;
    } catch (Exception $e) {
        error_log("Error in get_lesson_questions: " . $e->getMessage());
        return [];
    }
}

/**
 * Post a new student question on a lesson.
 */
function post_lesson_question($lessonId, $userId, $questionText, $title = null) {
    $pdo = getDBConnection();
    try {
        $lessonId = (int)$lessonId;
        $userId   = (int)$userId;
        $questionText = trim($questionText);
        $title    = $title ? trim($title) : null;

        if (empty($questionText)) {
            return ['success' => false, 'message' => 'Question content cannot be empty.'];
        }

        $stmt = $pdo->prepare("
            INSERT INTO lesson_questions (lesson_id, user_id, title, question, status, created_at)
            VALUES (?, ?, ?, ?, 'open', NOW())
        ");
        $stmt->execute([$lessonId, $userId, $title, $questionText]);
        $questionId = (int)$pdo->lastInsertId();

        // Notify the course instructor if one is assigned
        $stmtTch = $pdo->prepare("
            SELECT c.teacher_id, t.user_id AS teacher_user_id, l.title AS lesson_title, c.title AS course_title
            FROM lessons l
            JOIN course_sections cs ON l.section_id = cs.id
            JOIN courses c ON cs.course_id = c.id
            LEFT JOIN teachers t ON (c.teacher_id = t.id OR t.assigned_course_id = c.id OR (t.assigned_category_id = c.category_id AND t.assigned_category_id IS NOT NULL))
            WHERE l.id = ? LIMIT 1
        ");
        $stmtTch->execute([$lessonId]);
        $tchData = $stmtTch->fetch(PDO::FETCH_ASSOC);

        if ($tchData && !empty($tchData['teacher_user_id']) && (int)$tchData['teacher_user_id'] !== $userId) {
            $studentStmt = $pdo->prepare("SELECT first_name, last_name FROM users WHERE id = ? LIMIT 1");
            $studentStmt->execute([$userId]);
            $student = $studentStmt->fetch(PDO::FETCH_ASSOC);
            $studentName = trim(($student['first_name'] ?? 'A student') . ' ' . ($student['last_name'] ?? ''));

            create_notification(
                (int)$tchData['teacher_user_id'],
                'New Student Question: ' . ($tchData['lesson_title'] ?? 'Lesson'),
                "{$studentName} asked a question on '{$tchData['lesson_title']}': " . mb_strimwidth($questionText, 0, 120, '...'),
                'academic',
                'teacher/discussions.php?question_id=' . $questionId
            );
        }

        return ['success' => true, 'message' => 'Your question has been posted! The instructor has been notified.', 'question_id' => $questionId];
    } catch (Exception $e) {
        error_log("Error in post_lesson_question: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to post question: ' . $e->getMessage()];
    }
}

/**
 * Post a reply to a question (by instructor or student).
 */
function post_lesson_question_reply($questionId, $userId, $replyText, $isInstructor = false) {
    $pdo = getDBConnection();
    try {
        $questionId = (int)$questionId;
        $userId     = (int)$userId;
        $replyText  = trim($replyText);

        if (empty($replyText)) {
            return ['success' => false, 'message' => 'Reply content cannot be empty.'];
        }

        $stmt = $pdo->prepare("
            INSERT INTO lesson_question_replies (question_id, user_id, reply, is_instructor_reply, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$questionId, $userId, $replyText, $isInstructor ? 1 : 0]);
        $replyId = (int)$pdo->lastInsertId();

        // Update question status to answered if instructor replied
        if ($isInstructor) {
            $pdo->prepare("UPDATE lesson_questions SET status = 'answered', updated_at = NOW() WHERE id = ?")->execute([$questionId]);
        }

        // Notify question author if someone else replied
        $stmtQ = $pdo->prepare("
            SELECT q.user_id, q.lesson_id, l.title AS lesson_title
            FROM lesson_questions q
            JOIN lessons l ON q.lesson_id = l.id
            WHERE q.id = ? LIMIT 1
        ");
        $stmtQ->execute([$questionId]);
        $qRow = $stmtQ->fetch(PDO::FETCH_ASSOC);

        if ($qRow && (int)$qRow['user_id'] !== $userId) {
            $authorStmt = $pdo->prepare("SELECT first_name, last_name FROM users WHERE id = ? LIMIT 1");
            $authorStmt->execute([$userId]);
            $replier = $authorStmt->fetch(PDO::FETCH_ASSOC);
            $replierName = trim(($replier['first_name'] ?? 'Someone') . ' ' . ($replier['last_name'] ?? ''));

            $badgeTitle = $isInstructor ? 'Instructor Answered Your Question' : 'New Reply on Your Question';
            create_notification(
                (int)$qRow['user_id'],
                $badgeTitle,
                "{$replierName} posted an answer on '{$qRow['lesson_title']}': " . mb_strimwidth($replyText, 0, 120, '...'),
                'academic',
                'student/lesson.php?id=' . (int)$qRow['lesson_id'] . '#question-' . $questionId
            );
        }

        return ['success' => true, 'message' => 'Reply posted successfully!', 'reply_id' => $replyId];
    } catch (Exception $e) {
        error_log("Error in post_lesson_question_reply: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to post reply: ' . $e->getMessage()];
    }
}

/**
 * Fetch questions for a teacher's courses, assigned categories, or platform with flexible matching.
 */
function get_teacher_course_questions($teacherIdOrUserId, $statusFilter = null, $scope = 'my_courses') {
    $pdo = getDBConnection();
    try {
        $teacherId = (int)$teacherIdOrUserId;

        // Resolve teacher record
        $stmtT = $pdo->prepare("SELECT id, user_id, assigned_course_id, assigned_category_id FROM teachers WHERE id = ? OR user_id = ? LIMIT 1");
        $stmtT->execute([$teacherId, $teacherId]);
        $teacher = $stmtT->fetch(PDO::FETCH_ASSOC);

        $tid = $teacher ? (int)$teacher['id'] : 0;
        $tUid = $teacher ? (int)$teacher['user_id'] : $teacherId;
        $assignedCourseId = (int)($teacher['assigned_course_id'] ?? 0);
        $assignedCatId = (int)($teacher['assigned_category_id'] ?? 0);

        $params = [];
        $whereClauses = [];

        if ($scope === 'all') {
            // No instructor filter - show all questions
        } else {
            // Instructor courses matching
            $courseConditions = [];
            if ($tid > 0) {
                $courseConditions[] = "c.teacher_id = ?";
                $params[] = $tid;
            }
            if ($tUid > 0 && $tUid !== $tid) {
                $courseConditions[] = "c.teacher_id = ?";
                $params[] = $tUid;
            }
            if ($assignedCourseId > 0) {
                $courseConditions[] = "c.id = ?";
                $params[] = $assignedCourseId;
            }
            if ($assignedCatId > 0) {
                $courseConditions[] = "(c.category_id = ? AND c.category_id IS NOT NULL)";
                $params[] = $assignedCatId;
            }

            if (!empty($courseConditions)) {
                $whereClauses[] = "(" . implode(" OR ", $courseConditions) . ")";
            }
        }

        if ($statusFilter && in_array($statusFilter, ['open', 'answered', 'closed'])) {
            $whereClauses[] = "q.status = ?";
            $params[] = $statusFilter;
        }

        $whereSql = !empty($whereClauses) ? ("WHERE " . implode(" AND ", $whereClauses)) : "";

        $sql = "
            SELECT q.*, 
                   l.title AS lesson_title, l.id AS lesson_id,
                   c.title AS course_title, c.id AS course_id,
                   u.first_name, u.last_name, u.email, u.avatar,
                   (SELECT COUNT(*) FROM lesson_question_replies r WHERE r.question_id = q.id) AS reply_count,
                   (SELECT COUNT(*) FROM lesson_question_replies r WHERE r.question_id = q.id AND r.is_instructor_reply = 1) AS instructor_reply_count
            FROM lesson_questions q
            JOIN lessons l ON q.lesson_id = l.id
            JOIN course_sections cs ON l.section_id = cs.id
            JOIN courses c ON cs.course_id = c.id
            JOIN users u ON q.user_id = u.id
            {$whereSql}
            ORDER BY q.created_at DESC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // If no questions found under specific teacher filter and not all scope, check if there are platform questions to display
        if (empty($questions) && $scope !== 'all') {
            // Check if user has no assigned courses yet, fallback to all questions
            $totalPlatformQuestions = (int)$pdo->query("SELECT COUNT(*) FROM lesson_questions")->fetchColumn();
            if ($totalPlatformQuestions > 0 && count($courseConditions ?? []) === 0) {
                return get_teacher_course_questions($teacherIdOrUserId, $statusFilter, 'all');
            }
        }

        // Fetch replies for each
        if (!empty($questions)) {
            $stmtR = $pdo->prepare("
                SELECT r.*, u.first_name, u.last_name, u.role, u.avatar
                FROM lesson_question_replies r
                JOIN users u ON r.user_id = u.id
                WHERE r.question_id = ?
                ORDER BY r.created_at ASC
            ");
            foreach ($questions as &$q) {
                $stmtR->execute([(int)$q['id']]);
                $q['replies'] = $stmtR->fetchAll(PDO::FETCH_ASSOC);
            }
            unset($q);
        }

        return $questions;
    } catch (Exception $e) {
        error_log("Error in get_teacher_course_questions: " . $e->getMessage());
        return [];
    }
}
