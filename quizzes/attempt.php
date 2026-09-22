<?php

require_once dirname(__DIR__) . '/config/main.php';

require_login();
$user     = current_user();
$userId   = $user['id'];
$pdo      = getDBConnection();
$quizId   = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT id FROM students WHERE user_id = ? LIMIT 1");
$stmt->execute([$userId]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);
$studentId = $student ? (int)$student['id'] : 0;

if (!$studentId && current_user_role() === ROLE_STUDENT) {
    set_flash('error', 'Student profile required.');
    redirect('student/dashboard.php');
}

$stmt = $pdo->prepare("
    SELECT q.*, c.title AS course_title, c.id AS course_id
    FROM quizzes q
    JOIN courses c ON q.course_id = c.id
    WHERE q.id = ? AND q.status = 'published' LIMIT 1
");
$stmt->execute([$quizId]);
$quiz = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$quiz) {
    set_flash('error', 'Quiz not found or not published.');
    redirect('student/quizzes.php');
}

if (current_user_role() === ROLE_STUDENT) {
    $stmtEn = $pdo->prepare("SELECT id FROM enrollments WHERE student_id = ? AND course_id = ? AND status = 'active' LIMIT 1");
    $stmtEn->execute([$studentId, $quiz['course_id']]);
    if (!$stmtEn->fetch()) {
        set_flash('error', 'Quiz Locked: You can only take assessments for your active enrolled course.');
        redirect('student/dashboard.php');
    }
}

$stmt = $pdo->prepare("
    SELECT q.*, qo.id AS option_id, qo.option_text, qo.sort_order AS opt_sort
    FROM questions q
    LEFT JOIN question_options qo ON q.id = qo.question_id
    WHERE q.quiz_id = ?
    ORDER BY q.sort_order ASC, q.id ASC, qo.sort_order ASC
");
$stmt->execute([$quizId]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$questions = [];
foreach ($rows as $r) {
    $qId = $r['id'];
    if (!isset($questions[$qId])) {
        $questions[$qId] = [
            'id'            => $r['id'],
            'question'      => $r['question'],
            'question_type' => $r['question_type'],
            'points'        => (float)$r['points'],
            'options'       => []
        ];
    }
    if ($r['option_id']) {
        $questions[$qId]['options'][] = [
            'id'   => $r['option_id'],
            'text' => $r['option_text']
        ];
    }
}

if (is_post() && isset($_POST['submit_quiz'])) {
    $answers = $_POST['answers'] ?? [];
    $totalScore   = 0.0;
    $maxScore     = 0.0;

    try {
        $pdo->beginTransaction();

        $stmtAtt = $pdo->prepare("
            INSERT INTO quiz_attempts (quiz_id, student_id, score, percentage, passed, started_at, submitted_at)
            VALUES (?, ?, 0, 0, 0, NOW(), NOW())
        ");
        $stmtAtt->execute([$quizId, $studentId]);
        $attemptId = $pdo->lastInsertId();

        foreach ($questions as $qId => $qData) {
            $maxScore += $qData['points'];
            $selectedOptId = (int)($answers[$qId] ?? 0);
            $isCorrect = false;
            $earnedPts = 0.0;

            if ($selectedOptId > 0) {

                $stmtCheck = $pdo->prepare("SELECT is_correct FROM question_options WHERE id = ? AND question_id = ? LIMIT 1");
                $stmtCheck->execute([$selectedOptId, $qId]);
                $isCorrect = (bool)$stmtCheck->fetchColumn();

                if ($isCorrect) {
                    $earnedPts   = $qData['points'];
                    $totalScore += $earnedPts;
                }
            }

            $stmtAns = $pdo->prepare("
                INSERT INTO quiz_answers (attempt_id, question_id, option_id, is_correct, points_earned)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmtAns->execute([$attemptId, $qId, $selectedOptId ?: null, $isCorrect ? 1 : 0, $earnedPts]);
        }

        $percentage = $maxScore > 0 ? round(($totalScore / $maxScore) * 100, 2) : 0.0;
        $passed = ($percentage >= (float)$quiz['passing_score']) ? 1 : 0;

        $pdo->prepare("
            UPDATE quiz_attempts
            SET score = ?, percentage = ?, passed = ?
            WHERE id = ?
        ")->execute([$totalScore, $percentage, $passed, $attemptId]);

        $pdo->commit();

        redirect('quizzes/results.php?attempt_id=' . $attemptId);
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = 'Error submitting quiz: ' . $e->getMessage();
    }
}

include BASE_PATH . '/includes/layouts/header.php';
?>

<div class="py-5 bg-light-subtle" style="min-height: calc(100vh - 120px);">
    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-lg-8">

                <div class="card border-0 shadow-lg rounded-4 p-4 p-md-5 mb-4">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                        <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 py-1">
                            <?= e($quiz['course_title']) ?>
                        </span>
                        <div class="text-muted small">
                            <i class="bi bi-clock-fill text-warning me-1"></i> Passing Score: <strong><?= number_format($quiz['passing_score']) ?>%</strong>
                        </div>
                    </div>
                    <h2 class="fw-bold mb-2"><?= e($quiz['title']) ?></h2>
                    <p class="text-muted small mb-0"><?= e($quiz['description'] ?: 'Answer all questions to the best of your ability and click Submit.') ?></p>
                </div>

                <form action="<?= url('quizzes/attempt.php?id=' . $quizId) ?>" method="POST">
                    <input type="hidden" name="submit_quiz" value="1">

                    <div class="d-flex flex-column gap-4 mb-4">
                        <?php $num = 1; foreach ($questions as $qId => $q): ?>
                        <div class="card border-0 shadow-sm rounded-4 p-4">
                            <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                                <span class="badge bg-secondary bg-opacity-15 text-secondary rounded-pill px-2">Question <?= $num ?></span>
                                <span class="text-muted small"><?= $q['points'] ?> pt<?= $q['points'] > 1 ? 's' : '' ?></span>
                            </div>
                            <h5 class="fw-bold mb-3"><?= e($q['question']) ?></h5>

                            <div class="d-flex flex-column gap-2">
                                <?php foreach ($q['options'] as $opt): ?>
                                <label class="p-3 rounded-3 border d-flex align-items-center gap-3 cursor-pointer bg-light hover-shadow transition">
                                    <input class="form-check-input mt-0 flex-shrink-0" type="radio" name="answers[<?= $qId ?>]" value="<?= $opt['id'] ?>" required>
                                    <span class="small fw-semibold text-main"><?= e($opt['text']) ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php $num++; endforeach; ?>
                    </div>

                    <div class="card border-0 shadow-sm rounded-4 p-4 text-center">
                        <p class="text-muted small mb-3">Make sure you have selected an answer for all questions before submitting.</p>
                        <button type="submit" class="btn btn-primary btn-lg rounded-pill px-5 py-3 fw-bold shadow mx-auto">
                            <i class="bi bi-send-check-fill me-2"></i> Submit Quiz Answers
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include BASE_PATH . '/includes/layouts/footer.php'; ?>
