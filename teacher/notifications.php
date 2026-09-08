<?php
/**
 * StudyMe AI Platform — Teacher Notifications & Activity Stream
 */
require_once dirname(__DIR__) . '/config/main.php';

secure_page(ROLE_TEACHER);
$user = current_user();

include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <p class="text-muted mb-1 fw-semibold text-uppercase small"><i class="bi bi-bell-fill text-primary me-1"></i> Instructor Suite</p>
        <h2 class="fw-bold mb-0">Notifications</h2>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4 p-5 text-center">
    <i class="bi bi-bell-slash text-muted display-4 mb-3"></i>
    <h5 class="fw-bold">No New Notifications</h5>
    <p class="text-muted small mb-0">You're all caught up! Course updates and student messages will appear here.</p>
</div>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
