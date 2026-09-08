<?php
/**
 * StudyMe AI Platform — Student Notifications Inbox
 */
require_once dirname(__DIR__) . '/config/main.php';
require_once BASE_PATH . '/includes/functions/notifications.php';

secure_page(ROLE_STUDENT);

$user = current_user();
$userId = $user['id'];

// Actions
if (is_post()) {
    if (isset($_POST['mark_all_read'])) {
        mark_all_notifications_read($userId);
        set_flash('success', 'All notifications marked as read.');
        redirect('student/notifications.php');
    }
    if (isset($_POST['delete_id'])) {
        delete_notification((int)$_POST['delete_id'], $userId);
        set_flash('success', 'Notification deleted.');
        redirect('student/notifications.php');
    }
}

$notifications = get_user_notifications($userId);
$unreadCount = count_unread_notifications($userId);

include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
    <div>
        <h1 class="h3 fw-bold mb-1"><i class="bi bi-bell-fill text-primary me-2"></i> Notifications Inbox</h1>
        <p class="text-muted mb-0">System updates, AI study insights, course announcements, and quiz feedback.</p>
    </div>

    <?php if ($unreadCount > 0): ?>
        <form method="POST" action="<?= url('student/notifications.php') ?>">
            <button type="submit" name="mark_all_read" value="1" class="btn btn-outline-primary btn-sm rounded-pill fw-bold" data-feedback="click">
                <i class="bi bi-check-all me-1"></i> Mark All as Read
            </button>
        </form>
    <?php endif; ?>
</div>

<div class="row justify-content-center">
    <div class="col-lg-9">
        <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5">
            <?php if (!empty($notifications)): ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($notifications as $n): ?>
                        <div class="list-group-item px-0 py-3 border-bottom d-flex align-items-start justify-content-between gap-3 <?= $n['is_read'] ? '' : 'bg-primary bg-opacity-10 rounded-3 p-3' ?>">
                            <div class="d-flex align-items-start gap-3">
                                <div class="p-2 bg-primary bg-opacity-10 text-primary rounded-circle mt-1">
                                    <i class="bi bi-stars"></i>
                                </div>
                                <div>
                                    <h6 class="fw-bold mb-1 text-main"><?= e($n['title']) ?></h6>
                                    <p class="text-secondary small mb-1"><?= e($n['message']) ?></p>
                                    <span class="text-muted" style="font-size:0.75rem;"><?= date('M j, Y &bull; g:i a', strtotime($n['created_at'])) ?></span>
                                </div>
                            </div>
                            <form method="POST" action="<?= url('student/notifications.php') ?>">
                                <input type="hidden" name="delete_id" value="<?= (int)$n['id'] ?>">
                                <button type="submit" class="btn btn-link text-muted p-0 border-0" title="Delete notification">
                                    <i class="bi bi-x-lg"></i>
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-bell-slash fs-1"></i>
                    <h5 class="fw-bold mt-3">No notifications right now</h5>
                    <p class="small">We'll alert you here when new course materials or quiz evaluations are ready.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
