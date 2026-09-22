<?php

require_once dirname(__DIR__) . '/config/main.php';
require_once BASE_PATH . '/includes/functions/quizzes.php';
require_once BASE_PATH . '/includes/functions/certificates.php';
require_once BASE_PATH . '/includes/functions/activity.php';

secure_page(ROLE_STUDENT);

$quizId = (int)($_GET['id'] ?? 0);
$quiz = get_quiz_by_id($quizId);
if (!$quiz) {
    redirect('student/quizzes.php');
}

$questions = get_quiz_questions($quizId);
$user = current_user();
$pdo = getDBConnection();
$userId = $user['id'];

$stmt = $pdo->prepare("SELECT id FROM students WHERE user_id = ? LIMIT 1");
$stmt->execute([$userId]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);
$studentId = $student ? (int)$student['id'] : 0;

log_user_activity($userId, 'quiz_start', 'Started quiz: ' . ($quiz['title'] ?? 'Unknown'), $quiz['course_id'] ?? null, null, $quizId);

if (current_user_role() === ROLE_STUDENT) {
    $stmtEn = $pdo->prepare("SELECT id FROM enrollments WHERE student_id = ? AND course_id = ? AND status = 'active' LIMIT 1");
    $stmtEn->execute([$studentId, $quiz['course_id']]);
    if (!$stmtEn->fetch()) {
        set_flash('error', 'Quiz Locked: You can only take assessments for your active enrolled course.');
        redirect('student/dashboard.php');
    }
}

if (is_post() && isset($_POST['submit_quiz'])) {
    $userAnswers = $_POST['answers'] ?? [];
    $totalPoints = 0.0;
    $earnedPoints = 0.0;

    $stmt = $pdo->prepare("
        INSERT INTO quiz_attempts (quiz_id, student_id, started_at, submitted_at)
        VALUES (?, ?, NOW(), NOW())
    ");
    $stmt->execute([$quizId, $studentId]);
    $attemptId = (int)$pdo->lastInsertId();

    foreach ($questions as $q) {
        $qId = (int)$q['id'];
        $points = (float)($q['points'] ?? 1.0);
        $totalPoints += $points;

        $selectedOptId = isset($userAnswers[$qId]) ? (int)$userAnswers[$qId] : null;
        $isCorrect = false;

        if ($selectedOptId) {
            foreach ($q['options'] as $opt) {
                if ((int)$opt['id'] === $selectedOptId && (bool)$opt['is_correct']) {
                    $isCorrect = true;
                    $earnedPoints += $points;
                    break;
                }
            }
        }

        $stmt = $pdo->prepare("
            INSERT INTO quiz_answers (attempt_id, question_id, option_id, is_correct, points_earned)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$attemptId, $qId, $selectedOptId, $isCorrect ? 1 : 0, $isCorrect ? $points : 0.0]);
    }

    $percentage = $totalPoints > 0 ? round(($earnedPoints / $totalPoints) * 100, 2) : 0.0;
    $passingScore = (float)($quiz['passing_score'] ?? 50.0);
    $passed = $percentage >= $passingScore;

    $stmt = $pdo->prepare("
        UPDATE quiz_attempts
        SET score = ?, percentage = ?, passed = ?
        WHERE id = ?
    ");
    $stmt->execute([$earnedPoints, $percentage, $passed ? 1 : 0, $attemptId]);

    if ($passed) {
        $stmt = $pdo->prepare("SELECT id FROM enrollments WHERE student_id = ? AND course_id = ? LIMIT 1");
        $stmt->execute([$studentId, $quiz['course_id']]);
        $enr = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($enr) {
            maybe_issue_certificate($studentId, $quiz['course_id'], $enr['id']);
        }
    }

    $_SESSION['auth_success_vibrate'] = true;
    redirect('student/results.php?attempt_id=' . $attemptId);
}

include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<div class="mb-4">
    <a href="<?= url('student/quizzes.php') ?>" class="btn btn-outline-secondary btn-sm rounded-pill mb-3">
        <i class="bi bi-arrow-left me-1"></i> Exit Quiz
    </a>
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
        <div>
            <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 py-1 mb-2">
                <?= e($quiz['course_title']) ?>
            </span>
            <h1 class="h2 fw-bold mb-1"><?= e($quiz['title']) ?></h1>
            <p class="text-muted mb-0"><?= e($quiz['description'] ?: 'Answer all questions carefully. You must score at least ' . (float)$quiz['passing_score'] . '% to pass.') ?></p>
        </div>

        <div class="alert alert-warning border-warning rounded-pill px-4 py-2 mb-0 d-flex align-items-center gap-2 fw-bold">
            <i class="bi bi-clock-history fs-5"></i>
            <span>Time Limit: <?= (int)($quiz['time_limit_minutes'] ?? 15) ?> mins</span>
        </div>
    </div>
</div>

<form action="<?= url('student/quiz.php?id=' . $quizId) ?>" method="POST">
    <input type="hidden" name="submit_quiz" value="1">

    <?php if (!empty($questions)): ?>
        <div class="row justify-content-center">
            <div class="col-lg-9">
                <?php foreach ($questions as $index => $q): ?>
                    <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 mb-4">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <span class="badge bg-secondary rounded-pill px-3 py-1">Question <?= $index + 1 ?> of <?= count($questions) ?></span>
                            <span class="text-muted small fw-bold"><?= (float)($q['points'] ?? 1) ?> Point<?= (float)($q['points'] ?? 1) > 1 ? 's' : '' ?></span>
                        </div>
                        <h4 class="fw-bold mb-4 text-dark"><?= e($q['question']) ?></h4>

                        <div class="d-flex flex-column gap-3">
                            <?php foreach ($q['options'] as $opt): ?>
                                <label class="p-3 rounded-3 border bg-light d-flex align-items-center gap-3 cursor-pointer hover-border-primary">
                                    <input type="radio" name="answers[<?= (int)$q['id'] ?>]" value="<?= (int)$opt['id'] ?>" class="form-check-input flex-shrink-0" required>
                                    <span class="text-main fw-semibold"><?= e($opt['option_text']) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <div class="card border-0 shadow-sm rounded-4 p-4 text-end mb-5">
                    <button type="submit" class="btn btn-success btn-lg rounded-pill px-5 fw-bold shadow-sm" data-feedback="success">
                        <i class="bi bi-send-fill me-2"></i> Submit Quiz Answers
                    </button>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="card border-0 shadow-sm rounded-4 p-5 text-center my-4">
            <i class="bi bi-question-diamond text-muted fs-1 mb-2"></i>
            <h4 class="fw-bold">No questions authored for this quiz yet</h4>
            <p class="text-muted mb-4">The instructor has not added questions to this quiz module.</p>
            <a href="<?= url('student/quizzes.php') ?>" class="btn btn-primary rounded-pill px-4">Back to Quizzes</a>
        </div>
    <?php endif; ?>
</form>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
