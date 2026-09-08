<?php
/**
 * StudyMe AI Platform — 404 Not Found Page
 */
require_once __DIR__ . '/config/main.php';

http_response_code(404);
$seo_options = [
    'title'       => 'Page Not Found (404) | StudyMe',
    'description' => 'The page you requested could not be found.',
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
                    <div class="display-1 fw-bold text-primary mb-2">404</div>
                    <div class="p-3 bg-warning bg-opacity-10 text-warning rounded-circle d-inline-flex mx-auto mb-3 fs-1">
                        <i class="bi bi-compass"></i>
                    </div>
                    <h2 class="fw-bold mb-2">Page Not Found</h2>
                    <p class="text-muted mb-4">
                        The page or learning resource you are looking for might have been moved, renamed, or is temporarily unavailable.
                    </p>
                    <div class="d-flex flex-wrap justify-content-center gap-3">
                        <a href="<?= url('index.php') ?>" class="btn btn-primary rounded-pill px-4 fw-bold" data-feedback="click">
                            <i class="bi bi-house-door- me-2"></i> Return Home
                        </a>fill
                        <a href="<?= url('courses/index.php') ?>" class="btn btn-outline-secondary rounded-pill px-4 fw-bold" data-feedback="click">
                            <i class="bi bi-compass me-2"></i> Browse Courses
                        </a>
                        <a href="<?= url('help.php') ?>" class="btn btn-outline-secondary rounded-pill px-4 fw-bold" data-feedback="click">
                            <i class="bi bi-life-preserver me-2"></i> Help Center
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include BASE_PATH . '/includes/layouts/footer.php'; ?>
