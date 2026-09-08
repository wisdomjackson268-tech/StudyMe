<?php
/**
 * StudyMe AI Platform — Quiz Attempt Results View
 */
require_once dirname(__DIR__) . '/config/main.php';

require_login();
$user      = current_user();
$userId    = $user['id'];
$pdo       = getDBConnection();
$attemptId = (int)($_GET['attempt_id'] ?? 0);

// Fetch attempt details
$stmt = $pdo->prepare("
    SELECT qa.*, q.title AS quiz_title, q.passing_score, q.course_id,
           c.title AS course_title, c.slug AS course_slug
    FROM quiz_attempts qa
    JOIN quizzes q ON qa.quiz_id = q.id
    JOIN courses c ON q.course_id = c.id
    JOIN students s ON qa.student_id = s.id
    WHERE qa.id = ? AND (s.user_id = ? OR ? = 'admin')
    LIMIT 1
");
$stmt->execute([$attemptId, $userId, current_user_role()]);
$attempt = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$attempt) {
    set_flash('error', 'Quiz result not found.');
    redirect('student/quizzes.php');
}

// Fetch answers with questions
$stmtAns = $pdo->prepare("
    SELECT ans.*, q.question, q.points,
           qo.option_text AS selected_option,
           (SELECT qo2.option_text FROM question_options qo2 WHERE qo2.question_id = q.id AND qo2.is_correct = 1 LIMIT 1) AS correct_option
    FROM quiz_answers ans
    JOIN questions q ON ans.question_id = q.id
    LEFT JOIN question_options qo ON ans.option_id = qo.id
    WHERE ans.attempt_id = ?
    ORDER BY q.sort_order ASC, q.id ASC
");
$stmtAns->execute([$attemptId]);
$answers = $stmtAns->fetchAll(PDO::FETCH_ASSOC);

include BASE_PATH . '/includes/layouts/header.php';
?>

<div class="py-5 bg-light-subtle" style="min-height: calc(100vh - 120px);">
    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <!-- Result Hero Card -->
                <div class="card border-0 shadow-lg rounded-4 p-4 p-md-5 text-center mb-4">
                    <div class="mb-3">
                        <?php if ($attempt['passed']): ?>
                            <div class="p-3 bg-success bg-opacity-10 text-success rounded-circle d-inline-flex fs-1 mb-2">
                                <i class="bi bi-award-fill"></i>
                            </div>
                            <h2 class="fw-bold text-success mb-1">Congratulations! You Passed! 🎉</h2>
                            <p class="text-muted small">You met the passing threshold for this quiz.</p>
                        <?php else: ?>
                            <div class="p-3 bg-danger bg-opacity-10 text-danger rounded-circle d-inline-flex fs-1 mb-2">
                                <i class="bi bi-x-circle-fill"></i>
                            </div>
                            <h2 class="fw-bold text-danger mb-1">Quiz Not Passed</h2>
                            <p class="text-muted small">You did not achieve the required <?= (int)$attempt['passing_score'] ?>% passing score.</p>
                        <?php endif; ?>
                    </div>

                    <!-- Score Pill -->
                    <div class="d-inline-flex align-items-center justify-content-center gap-3 p-3 bg-light rounded-4 border mx-auto mb-4" style="min-width:280px;">
                        <div>
                            <div class="fs-2 fw-bold <?= $attempt['passed'] ? 'text-success' : 'text-danger' ?>">
                                <?= number_format($attempt['percentage'], 1) ?>%
                            </div>
                            <div class="text-muted small">Final Score</div>
                        </div>
                        <div class="vr"></div>
                        <div>
                            <div class="fs-2 fw-bold text-main">
                                <?= number_format($attempt['score'], 1) ?> pts
                            </div>
                            <div class="text-muted small">Points Earned</div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-center gap-3 flex-wrap">
                        <a href="<?= url('quizzes/attempt.php?id=' . $attempt['quiz_id']) ?>" class="btn btn-primary rounded-pill px-4 fw-bold">
                            <i class="bi bi-arrow-repeat me-1"></i> Retake Quiz
                        </a>
                        <a href="<?= url('student/course.php?id=' . $attempt['course_id']) ?>" class="btn btn-outline-secondary rounded-pill px-4">
                            <i class="bi bi-play-circle me-1"></i> Return to Course
                        </a>
                    </div>
                </div>

                <!-- Answer Review Breakdown -->
                <h4 class="fw-bold mb-3"><i class="bi bi-list-check text-primary me-2"></i>Detailed Question Review</h4>
                <div class="d-flex flex-column gap-3 mb-4">
                    <?php foreach ($answers as $idx => $ans): ?>
                    <div class="card border-0 shadow-sm rounded-4 p-4 <?= $ans['is_correct'] ? 'border-start border-success border-4' : 'border-start border-danger border-4' ?>">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="badge bg-secondary bg-opacity-10 text-secondary rounded-pill px-2">Question <?= $idx + 1 ?></span>
                            <span class="badge <?= $ans['is_correct'] ? 'bg-success' : 'bg-danger' ?> rounded-pill px-2">
                                <?= $ans['is_correct'] ? 'Correct (+'.$ans['points_earned'].' pts)' : 'Incorrect (0 pts)' ?>
                            </span>
                        </div>
                        <h6 class="fw-bold mb-3"><?= e($ans['question']) ?></h6>
                        
                        <div class="p-3 rounded-3 bg-light small mb-2">
                            <div class="text-muted mb-1">Your Answer:</div>
                            <div class="fw-bold <?= $ans['is_correct'] ? 'text-success' : 'text-danger' ?>">
                                <i class="bi <?= $ans['is_correct'] ? 'bi-check-circle-fill' : 'bi-x-circle-fill' ?> me-1"></i>
                                <?= e($ans['selected_option'] ?: 'No answer submitted') ?>
                            </div>
                        </div>

                        <?php if (!$ans['is_correct']): ?>
                        <div class="p-3 rounded-3 bg-success bg-opacity-10 text-success small">
                            <div class="fw-semibold mb-1">Correct Answer:</div>
                            <div class="fw-bold"><i class="bi bi-check-circle-fill me-1"></i> <?= e($ans['correct_option']) ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include BASE_PATH . '/includes/layouts/footer.php'; ?>
