<?php

require_once __DIR__ . '/config/main.php';

http_response_code(403);
$seo_options = [
    'title'       => 'Access Restricted (403) | StudyMe',
    'description' => 'You do not have authorization to access this learning resource.',
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
                    <div class="display-1 fw-bold text-danger mb-2">403</div>
                    <div class="p-3 bg-danger bg-opacity-10 text-danger rounded-circle d-inline-flex mx-auto mb-3 fs-1">
                        <i class="bi bi-shield-lock-fill"></i>
                    </div>
                    <h2 class="fw-bold mb-2">Access Restricted</h2>
                    <p class="text-muted mb-4">
                        You do not have authorization to access this learning resource or portal area. Please ensure you are logged into an authorized account.
                    </p>
                    <div class="d-flex flex-wrap justify-content-center gap-3">
                        <a href="<?= url('auth/login.php') ?>" class="btn btn-primary rounded-pill px-4 fw-bold" data-feedback="click">
                            <i class="bi bi-box-arrow-in-right me-2"></i> Sign In
                        </a>
                        <a href="<?= url('index.php') ?>" class="btn btn-outline-secondary rounded-pill px-4 fw-bold" data-feedback="click">
                            <i class="bi bi-house-door-fill me-2"></i> Return Home
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
