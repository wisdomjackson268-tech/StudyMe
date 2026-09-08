<?php
/**
 * StudyMe AI Platform — Teacher Verification Notice
 */
require_once dirname(__DIR__) . '/config/main.php';

require_login();
$user = current_user();

include BASE_PATH . '/includes/layouts/header.php';
?>

<div class="py-5 bg-light-subtle d-flex align-items-center" style="min-height: calc(100vh - 120px);">
    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-md-7 col-lg-5 text-center">
                <div class="card border-0 shadow-lg rounded-4 p-4 p-md-5">
                    <div class="p-3 bg-warning bg-opacity-10 text-warning rounded-circle d-inline-flex mx-auto mb-3 fs-1">
                        <i class="bi bi-clock-history"></i>
                    </div>
                    <h3 class="fw-bold mb-2">Teacher Application Under Review</h3>
                    <p class="text-muted mb-4">
                        Thank you for applying to teach on StudyMe. Our academic committee reviews credentials within 24-48 hours. You will receive an email once approved.
                    </p>
                    <a href="<?= url('index.php') ?>" class="btn btn-primary rounded-pill px-4 fw-bold">Return to Home</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include BASE_PATH . '/includes/layouts/footer.php'; ?>
