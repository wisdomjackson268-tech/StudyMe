<?php

require_once dirname(__DIR__) . '/config/main.php';
require_once BASE_PATH . '/includes/functions/pricing.php';

require_login();
$user     = current_user();
$userId   = (int)$user['id'];
$courseId = (int)($_GET['course_id'] ?? 0);
$txRef    = trim($_GET['tx_ref'] ?? '');
$pdo      = getDBConnection();
$course   = null;
$amount   = 0.00;

if ($courseId > 0) {
    $stmt = $pdo->prepare("
        SELECT c.id, c.title, c.slug, c.price, cat.name AS category_name,
               CONCAT(u.first_name, ' ', u.last_name) AS teacher_name
        FROM courses c
        LEFT JOIN categories cat ON c.category_id = cat.id
        JOIN teachers t ON c.teacher_id = t.id
        JOIN users u ON t.user_id = u.id
        WHERE c.id = ? LIMIT 1
    ");
    $stmt->execute([$courseId]);
    $course = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!empty($txRef)) {
        $stmtP = $pdo->prepare("SELECT amount FROM payments WHERE transaction_reference = ? AND user_id = ? LIMIT 1");
        $stmtP->execute([$txRef, $userId]);
        $amount = (float)$stmtP->fetchColumn();
    }
    if ($amount <= 0) {
        $amount = get_course_official_price($courseId);
    }
}

$pageTitle = 'Payment Successful — StudyMe AI Platform';
include BASE_PATH . '/includes/layouts/header.php';
?>

<div class="py-5 bg-light-subtle d-flex align-items-center" style="min-height: calc(100vh - 120px);">
    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-lg-6 text-center">
                <div class="card border-0 shadow-lg rounded-4 p-4 p-md-5">
                    <div class="p-3 bg-success bg-opacity-10 text-success rounded-circle d-inline-flex mx-auto mb-3 fs-1 shadow-sm" style="width: 80px; height: 80px; align-items: center; justify-content: center;">
                        <i class="bi bi-check-circle-fill"></i>
                    </div>

                    <h2 class="fw-bold mb-2 text-success">🎉 Payment Successful!</h2>
                    <p class="text-muted mb-4 lead fs-6">Your StudyMe account/course has been activated.</p>

                    <?php if ($course): ?>
                    <div class="p-4 bg-light rounded-4 mb-4 border text-start">
                        <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                            <span class="text-muted small">Course:</span>
                            <span class="fw-bold text-main"><?= e($course['title']) ?></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                            <span class="text-muted small">Category:</span>
                            <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-2"><?= e($course['category_name'] ?: 'Technology') ?></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                            <span class="text-muted small">Amount:</span>
                            <span class="fs-5 fw-bold text-success">₦<?= number_format($amount, 0) ?></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-muted small">Status:</span>
                            <span class="badge bg-success rounded-pill px-3 py-1"><i class="bi bi-shield-check me-1"></i>Active</span>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="p-4 bg-light rounded-4 mb-4 border text-start">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-muted small">Account Type:</span>
                            <span class="fw-bold text-main"><?= ucfirst(current_user_role()) ?></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-muted small">Status:</span>
                            <span class="badge bg-success rounded-pill px-3 py-1"><i class="bi bi-shield-check me-1"></i>Active</span>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="d-flex flex-column flex-sm-row justify-content-center gap-3">
                        <?php if ($course): ?>
                        <a href="<?= url('student/course.php?id=' . $courseId) ?>" class="btn btn-primary btn-lg rounded-pill px-5 py-3 fw-bold shadow" data-feedback="success">
                            <i class="bi bi-play-circle-fill me-2"></i> Start Learning
                        </a>
                        <?php endif; ?>
                        <a href="<?= url('student/dashboard.php') ?>" class="btn btn-outline-secondary btn-lg rounded-pill px-4 py-3 fw-bold">
                            <i class="bi bi-speedometer2 me-2"></i> Go to Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include BASE_PATH . '/includes/layouts/footer.php'; ?>
