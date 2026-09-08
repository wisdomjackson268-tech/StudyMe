<?php
/**
 * StudyMe AI Platform — Student Quiz Results Review
 */
require_once dirname(__DIR__) . '/config/main.php';
secure_page(ROLE_STUDENT);

$attemptId = (int)($_GET['attempt_id'] ?? 0);
$pdo = getDBConnection();
$user = current_user();
$userId = $user['id'];

// Resolve student ID
$stmt = $pdo->prepare("SELECT id FROM students WHERE user_id = ? LIMIT 1");
$stmt->execute([$userId]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);
$studentId = $student ? (int)$student['id'] : 0;

$attempt = null;
$answers = [];

if ($attemptId && $studentId) {
    $stmt = $pdo->prepare("
        SELECT qa.*, q.title AS quiz_title, q.passing_score, q.course_id, c.title AS course_title
        FROM quiz_attempts qa
        JOIN quizzes q ON qa.quiz_id = q.id
        JOIN courses c ON q.course_id = c.id
        WHERE qa.id = ? AND qa.student_id = ?
        LIMIT 1
    ");
    $stmt->execute([$attemptId, $studentId]);
    $attempt = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($attempt) {
        $stmt = $pdo->prepare("
            SELECT qa.*, q.question, q.points, opt.option_text AS selected_option,
                   (SELECT option_text FROM question_options WHERE question_id = q.id AND is_correct = 1 LIMIT 1) AS correct_option
            FROM quiz_answers qa
            JOIN questions q ON qa.question_id = q.id
            LEFT JOIN question_options opt ON qa.option_id = opt.id
            WHERE qa.attempt_id = ?
        ");
        $stmt->execute([$attemptId]);
        $answers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!$attempt) {
    redirect('student/quizzes.php');
}

include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<div class="mb-4">
    <a href="<?= url('student/quizzes.php') ?>" class="btn btn-outline-secondary btn-sm rounded-pill mb-3">
        <i class="bi bi-arrow-left me-1"></i> Back to Quizzes
    </a>
</div>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <!-- Results Scorecard Card -->
        <div class="card border-0 shadow-lg rounded-4 overflow-hidden mb-5 text-center">
            <div class="p-4 p-md-5 bg-<?= $attempt['passed'] ? 'success' : 'danger' ?> text-white">
                <div class="p-3 bg-white bg-opacity-20 rounded-circle d-inline-flex mb-3 fs-1">
                    <i class="bi bi-<?= $attempt['passed'] ? 'trophy-fill text-warning' : 'x-circle-fill' ?>"></i>
                </div>
                <h2 class="fw-bold mb-1"><?= $attempt['passed'] ? 'Quiz Passed!' : 'Quiz Attempt Failed' ?></h2>
                <p class="mb-3 text-white-50"><?= e($attempt['quiz_title']) ?> &bull; <?= e($attempt['course_title']) ?></p>
                <div class="display-3 fw-bold mb-2"><?= number_format($attempt['percentage'], 0) ?>%</div>
                <div class="badge bg-white text-dark px-3 py-2 rounded-pill fw-bold">
                    Pass Threshold: <?= (float)$attempt['passing_score'] ?>%
                </div>
            </div>

            <div class="card-body p-4 d-flex justify-content-center gap-3">
                <a href="<?= url('student/quiz.php?id=' . (int)$attempt['quiz_id']) ?>" class="btn btn-outline-primary rounded-pill px-4 fw-bold">
                    <i class="bi bi-arrow-counterclockwise me-1"></i> Retake Quiz
                </a>
                <?php if ($attempt['passed']): ?>
                    <a href="<?= url('student/certificates.php') ?>" class="btn btn-warning rounded-pill px-4 fw-bold">
                        <i class="bi bi-award-fill me-1"></i> View Certificates
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Answers Review -->
        <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 mb-5">
            <h4 class="fw-bold mb-4"><i class="bi bi-search text-primary me-2"></i> Detailed Answers Breakdown</h4>

            <?php foreach ($answers as $index => $ans): ?>
                <div class="p-4 rounded-4 border mb-3 bg-<?= $ans['is_correct'] ? 'success' : 'danger' ?> bg-opacity-10">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="fw-bold text-main">Question <?= $index + 1 ?></span>
                        <span class="badge bg-<?= $ans['is_correct'] ? 'success' : 'danger' ?> rounded-pill">
                            <?= $ans['is_correct'] ? '+ ' . number_format($ans['points_earned'], 1) . ' Points' : '0 Points' ?>
                        </span>
                    </div>
                    <h5 class="fw-bold text-dark mb-3"><?= e($ans['question']) ?></h5>

                    <div class="small">
                        <div class="mb-1">
                            <strong class="text-secondary">Your Answer:</strong>
                            <span class="<?= $ans['is_correct'] ? 'text-success fw-bold' : 'text-danger fw-bold' ?>">
                                <?= e($ans['selected_option'] ?: 'No answer selected') ?>
                            </span>
                        </div>
                        <?php if (!$ans['is_correct']): ?>
                            <div>
                                <strong class="text-secondary">Correct Answer:</strong>
                                <span class="text-success fw-bold"><?= e($ans['correct_option'] ?: 'N/A') ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
