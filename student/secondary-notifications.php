<?php

require_once dirname(__DIR__) . '/config/main.php';
require_once BASE_PATH . '/includes/functions/enrollments.php';
require_once BASE_PATH . '/includes/functions/activity.php';

secure_page(ROLE_STUDENT);

$user   = current_user();
$userId = (int)$user['id'];
$pdo    = getDBConnection();

// Mark notifications as read
try {
    $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?")->execute([$userId]);
} catch (Exception $e) {}

// Fetch user notifications
$stmtNotif = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 30");
$stmtNotif->execute([$userId]);
$notifications = $stmtNotif->fetchAll(PDO::FETCH_ASSOC);

// Fetch secondary announcements
$stmtAnn = $pdo->prepare("
    SELECT a.*, CONCAT(u.first_name, ' ', u.last_name) AS author_name
    FROM announcements a
    LEFT JOIN users u ON a.created_by = u.id
    WHERE a.status = 'published' AND a.target_type IN ('all', 'students', 'secondary')
    ORDER BY a.priority = 'urgent' DESC, a.created_at DESC
    LIMIT 20
");
$stmtAnn->execute();
$announcements = $stmtAnn->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Notifications & Exam Alerts | StudyMe';
include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <a href="<?= url('student/secondary-dashboard.php') ?>" class="text-decoration-none text-muted small">
                <i class="bi bi-arrow-left me-1"></i> Dashboard
            </a>
            <span class="text-muted small">&bull;</span>
            <span class="text-muted small">Notifications</span>
        </div>
        <h2 class="fw-bold mb-0 text-dark">
            <i class="bi bi-bell-fill text-info me-2"></i> Secondary Notifications &amp; Announcements
        </h2>
    </div>
</div>

<div class="row g-4 mb-5">
    <!-- ANNOUNCEMENTS COLUMN -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm rounded-4 p-4 h-100 bg-white border">
            <h5 class="fw-bold text-dark mb-3">
                <i class="bi bi-megaphone-fill text-warning me-2"></i> Official Exam Announcements
            </h5>

            <?php if (!empty($announcements)): ?>
                <div class="d-flex flex-column gap-3">
                    <?php foreach ($announcements as $ann): ?>
                    <div class="p-3 bg-light rounded-3 border">
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <span class="badge <?= $ann['priority'] === 'urgent' ? 'bg-danger' : 'bg-primary' ?> rounded-pill px-2 py-1 font-monospace" style="font-size: 0.7rem;">
                                <?= strtoupper(e($ann['priority'])) ?>
                            </span>
                            <small class="text-muted"><i class="bi bi-clock me-1"></i> <?= date('M d, Y', strtotime($ann['created_at'])) ?></small>
                        </div>
                        <h6 class="fw-bold text-dark mb-1"><?= e($ann['title']) ?></h6>
                        <p class="text-muted small mb-0"><?= nl2br(e($ann['content'])) ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-megaphone fs-1 d-block mb-2 text-muted"></i>
                    No official announcements at this time.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- PERSONAL INBOX COLUMN -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm rounded-4 p-4 h-100 bg-white border">
            <h5 class="fw-bold text-dark mb-3">
                <i class="bi bi-inbox-fill text-primary me-2"></i> Personal Alerts &amp; Milestones
            </h5>

            <?php if (!empty($notifications)): ?>
                <div class="d-flex flex-column gap-3">
                    <?php foreach ($notifications as $notif): ?>
                    <div class="p-3 bg-light rounded-3 border">
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <span class="badge bg-info text-dark rounded-pill px-2 py-1 font-monospace" style="font-size: 0.7rem;">
                                <?= strtoupper(e($notif['type'])) ?>
                            </span>
                            <small class="text-muted"><?= date('M d, h:i A', strtotime($notif['created_at'])) ?></small>
                        </div>
                        <h6 class="fw-bold text-dark mb-1"><?= e($notif['title']) ?></h6>
                        <p class="text-muted small mb-0"><?= e($notif['message']) ?></p>
                        <?php if (!empty($notif['link'])): ?>
                            <a href="<?= url($notif['link']) ?>" class="btn btn-sm btn-link p-0 text-primary small fw-bold mt-1">
                                View Details &rarr;
                            </a>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-bell-slash fs-1 d-block mb-2 text-muted"></i>
                    Your notification inbox is clear.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
