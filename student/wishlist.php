<?php

require_once dirname(__DIR__) . '/config/main.php';
secure_page(ROLE_STUDENT);

include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
    <div>
        <h1 class="h3 fw-bold mb-1"><i class="bi bi-bookmark-heart-fill text-danger me-2"></i> Saved Courses &amp; Wishlist</h1>
        <p class="text-muted mb-0">Courses you have bookmarked for future study.</p>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4 p-5 text-center my-4">
    <div class="p-3 bg-danger bg-opacity-10 text-danger rounded-circle d-inline-flex mb-3 fs-2 mx-auto">
        <i class="bi bi-bookmark-star"></i>
    </div>
    <h4 class="fw-bold">Your wishlist is currently empty</h4>
    <p class="text-muted max-w-500 mx-auto mb-4">Bookmark courses from our course directory to save them here for quick access later.</p>
    <div>
        <a href="<?= url('courses/index.php') ?>" class="btn btn-primary rounded-pill px-4 py-2 fw-bold" data-feedback="click">
            <i class="bi bi-compass me-1"></i> Explore Courses Catalog
        </a>
    </div>
</div>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
