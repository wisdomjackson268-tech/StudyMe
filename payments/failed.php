<?php
/**
 * StudyMe AI Platform — Payment Failure / Unsuccessful Page
 */
require_once dirname(__DIR__) . '/config/main.php';

require_login();
$courseId = (int)($_GET['course_id'] ?? 0);
$reason   = trim($_GET['reason'] ?? 'Verification could not be completed or payment was cancelled.');
$pdo      = getDBConnection();
$course   = null;

if ($courseId > 0) {
    $stmt = $pdo->prepare("SELECT title, slug FROM courses WHERE id = ? LIMIT 1");
    $stmt->execute([$courseId]);
    $course = $stmt->fetch(PDO::FETCH_ASSOC);
}

include BASE_PATH . '/includes/layouts/header.php';
?>

<div class="py-5 bg-light-subtle d-flex align-items-center" style="min-height: calc(100vh - 120px);">
    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-lg-6 text-center">
                <div class="card border-0 shadow-lg rounded-4 p-4 p-md-5">
                    <div class="p-3 bg-danger bg-opacity-10 text-danger rounded-circle d-inline-flex mx-auto mb-3 fs-1 shadow-sm">
                        <i class="bi bi-x-circle-fill"></i>
                    </div>
                    <h2 class="fw-bold mb-2 text-danger">Payment Unsuccessful</h2>
                    <p class="text-danger fw-semibold mb-3">Your course has NOT been activated.</p>
                    <p class="text-muted small mb-4"><?= e($reason) ?></p>

                    <div class="d-flex flex-column flex-sm-row justify-content-center gap-3">
                        <?php if ($courseId > 0): ?>
                        <a href="<?= url('payments/checkout.php?course_id=' . $courseId) ?>" class="btn btn-primary btn-lg rounded-pill px-5 py-3 fw-bold shadow">
                            <i class="bi bi-arrow-repeat me-2"></i> Try Again
                        </a>
                        <a href="<?= url('courses/details.php?slug=' . e($course['slug'] ?? '')) ?>" class="btn btn-outline-secondary btn-lg rounded-pill px-4 py-3 fw-bold">
                            <i class="bi bi-arrow-left me-2"></i> Back to Course
                        </a>
                        <?php else: ?>
                        <a href="<?= url('courses/index.php') ?>" class="btn btn-primary btn-lg rounded-pill px-5 py-3 fw-bold shadow">
                            <i class="bi bi-compass me-2"></i> Browse Courses
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include BASE_PATH . '/includes/layouts/footer.php'; ?>
