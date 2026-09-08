<?php
/**
 * StudyMe AI Platform — 500 Internal Server Error Page
 */
require_once __DIR__ . '/config/main.php';

http_response_code(500);
$seo_options = [
    'title'       => 'Server Error (500) | StudyMe',
    'description' => 'A temporary server error occurred. Our engineers have been alerted.',
    'noindex'     => true,
    'is_private'  => true
];

include BASE_PATH . '/includes/layouts/header.php';
?>

<div class="py-5 bg-light-subtle d-flex align-items-center" style="min-height: calc(100vh - 120px);">
    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-lg-6 text-center">
                <div class="card border-0 shadow-lg rounded-4 p-4 p-md-5">
                    <div class="display-1 fw-bold text-warning mb-2">500</div>
                    <div class="p-3 bg-warning bg-opacity-10 text-warning rounded-circle d-inline-flex mx-auto mb-3 fs-1">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                    </div>
                    <h2 class="fw-bold mb-2">System Error Occurred</h2>
                    <p class="text-muted mb-4">
                        We encountered an unexpected technical issue while processing your request. Please try refreshing or return to the platform homepage.
                    </p>
                    <div class="d-flex flex-wrap justify-content-center gap-3">
                        <a href="<?= url('index.php') ?>" class="btn btn-primary rounded-pill px-4 fw-bold" data-feedback="click">
                            <i class="bi bi-house-door-fill me-2"></i> Return Home
                        </a>
                        <a href="<?= url('courses/index.php') ?>" class="btn btn-outline-secondary rounded-pill px-4 fw-bold" data-feedback="click">
                            <i class="bi bi-compass me-2"></i> Explore Courses
                        </a>
                        <a href="<?= url('contact.php') ?>" class="btn btn-outline-secondary rounded-pill px-4 fw-bold" data-feedback="click">
                            <i class="bi bi-headset me-2"></i> Report Issue
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include BASE_PATH . '/includes/layouts/footer.php'; ?>
