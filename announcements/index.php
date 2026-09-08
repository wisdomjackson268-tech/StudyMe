<?php
/**
 * StudyMe AI Platform — Universal Announcement Feed
 * Displays announcements delivered to the current logged-in user.
 */
require_once dirname(__DIR__) . '/config/main.php';
require_once BASE_PATH . '/includes/functions/announcements.php';

require_login();
$user     = current_user();
$userId   = (int)$user['id'];
$userRole = current_user_role();

// Handle Mark All Read action
if (is_post() && isset($_POST['mark_all_read'])) {
    mark_all_announcements_as_read($userId, $userRole);
    set_flash('success', 'All announcements marked as read.');
    redirect('announcements/index.php');
}

$announcements = get_user_announcements($userId, $userRole, 50);
$unreadCount   = count_unread_announcements($userId, $userRole);

$seo_options = [
    'title'      => 'Announcements & Updates | StudyMe AI Platform',
    'is_private' => true,
    'noindex'    => true
];
include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
        <div>
            <h1 class="h3 fw-bold mb-1"><i class="bi bi-megaphone-fill text-primary me-2"></i> Announcements &amp; Updates</h1>
            <p class="text-muted small mb-0">Official notices, course announcements, maintenance alerts, and academic updates.</p>
        </div>

        <div class="d-flex align-items-center gap-2">
            <?php if ($userRole === 'teacher'): ?>
                <a href="<?= url('teacher/create-announcement.php') ?>" class="btn btn-warning rounded-pill px-4 fw-bold text-dark btn-sm" data-feedback="click">
                    <i class="bi bi-plus-circle me-1"></i> Post Announcement
                </a>
            <?php elseif ($userRole === 'admin'): ?>
                <a href="<?= url('admin/announcements.php') ?>" class="btn btn-primary rounded-pill px-4 fw-bold btn-sm" data-feedback="click">
                    <i class="bi bi-sliders me-1"></i> Manage Announcements
                </a>
            <?php endif; ?>

            <?php if ($unreadCount > 0): ?>
                <form action="<?= url('announcements/index.php') ?>" method="POST">
                    <button type="submit" name="mark_all_read" value="1" class="btn btn-outline-secondary btn-sm rounded-pill fw-semibold">
                        <i class="bi bi-check2-all me-1"></i> Mark All as Read (<?= $unreadCount ?>)
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Announcement Feed -->
    <div class="row justify-content-center">
        <div class="col-lg-9">
            <?php if (!empty($announcements)): ?>
                <div class="d-flex flex-column gap-3">
                    <?php foreach ($announcements as $ann): ?>
                        <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white <?= empty($ann['is_read']) ? 'border-start border-4 border-primary' : '' ?>">
                            <div class="card-body p-4">
                                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                                    <div class="d-flex align-items-center gap-2">
                                        <?php if ($ann['priority'] === 'urgent'): ?>
                                            <span class="badge bg-danger rounded-pill px-3 py-1">URGENT</span>
                                        <?php elseif ($ann['priority'] === 'important'): ?>
                                            <span class="badge bg-warning text-dark rounded-pill px-3 py-1">IMPORTANT</span>
                                        <?php endif; ?>

                                        <?php if (!empty($ann['course_title'])): ?>
                                            <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 py-1">
                                                <i class="bi bi-mortarboard me-1"></i> <?= e($ann['course_title']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary bg-opacity-10 text-secondary rounded-pill px-3 py-1 text-capitalize">
                                                Audience: <?= e($ann['target_type']) ?>
                                            </span>
                                        <?php endif; ?>

                                        <?php if (empty($ann['is_read'])): ?>
                                            <span class="badge bg-success rounded-pill px-2 py-1 small">NEW</span>
                                        <?php endif; ?>
                                    </div>

                                    <small class="text-muted">
                                        <i class="bi bi-clock me-1"></i> <?= date('M d, Y · h:i A', strtotime($ann['created_at'])) ?>
                                    </small>
                                </div>

                                <h4 class="fw-bold mb-2">
                                    <a href="<?= url('announcements/view.php?id=' . $ann['id']) ?>" class="text-decoration-none text-dark hover-primary">
                                        <?= e($ann['title']) ?>
                                    </a>
                                </h4>

                                <div class="text-muted mb-3">
                                    <?= nl2br(e(mb_strimwidth(strip_tags($ann['content']), 0, 250, '...'))) ?>
                                </div>

                                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 pt-2 border-top">
                                    <div class="d-flex align-items-center gap-2 small text-muted">
                                        <div class="p-1 bg-light rounded-circle"><i class="bi bi-person-circle"></i></div>
                                        <span>Posted by <strong><?= e($ann['author_name'] ?: 'StudyMe Academic Team') ?></strong> (<?= ucfirst($ann['creator_role']) ?>)</span>
                                    </div>

                                    <div class="d-flex align-items-center gap-2">
                                        <?php if (!empty($ann['attachment'])): ?>
                                            <a href="<?= url($ann['attachment']) ?>" target="_blank" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                                                <i class="bi bi-paperclip me-1"></i> Attachment
                                            </a>
                                        <?php endif; ?>
                                        <a href="<?= url('announcements/view.php?id=' . $ann['id']) ?>" class="btn btn-sm btn-primary rounded-pill px-3 fw-bold">
                                            Read Full &rarr;
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="card border-0 shadow-sm rounded-4 p-5 text-center bg-white">
                    <div class="p-4 bg-primary bg-opacity-10 text-primary rounded-circle d-inline-flex mx-auto mb-3 fs-1">
                        <i class="bi bi-bell-slash"></i>
                    </div>
                    <h4 class="fw-bold mb-2">No Announcements Yet</h4>
                    <p class="text-muted small mb-0">When your instructors or administrators publish updates, you will see them here.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
