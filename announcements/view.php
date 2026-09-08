<?php
/**
 * StudyMe AI Platform — Full Announcement Detail Viewer
 */
require_once dirname(__DIR__) . '/config/main.php';
require_once BASE_PATH . '/includes/functions/announcements.php';

require_login();
$user     = current_user();
$userId   = (int)$user['id'];
$userRole = current_user_role();
$annId    = (int)($_GET['id'] ?? 0);

$pdo = getDBConnection();

// Fetch announcement details
$stmt = $pdo->prepare("
    SELECT a.*,
           CONCAT(u.first_name, ' ', u.last_name) AS author_name,
           u.role AS author_role,
           c.title AS course_title
    FROM announcements a
    LEFT JOIN users u ON a.created_by = u.id
    LEFT JOIN courses c ON a.course_id = c.id
    WHERE a.id = ? LIMIT 1
");
$stmt->execute([$annId]);
$ann = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ann) {
    set_flash('error', 'Announcement not found.');
    redirect('announcements/index.php');
}

// Check access permission
$accessibleUserIds = get_target_recipient_user_ids(
    $ann['target_type'],
    $ann['course_id'],
    $ann['target_user_id'],
    $ann['created_by']
);

$hasAccess = in_array($userId, $accessibleUserIds) || $userRole === 'admin' || (int)$ann['created_by'] === $userId;

if (!$hasAccess) {
    set_flash('error', 'ACCESS DENIED: You do not have permission to view this announcement.');
    redirect('announcements/index.php');
}

// Mark as read automatically for this user
mark_announcement_as_read($annId, $userId);

$priorityColors = ['normal'=>'secondary','important'=>'warning text-dark','urgent'=>'danger'];

$seo_options = [
    'title'      => e($ann['title']) . ' | Announcements | StudyMe',
    'is_private' => true,
    'noindex'    => true
];
include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            
            <a href="<?= url('announcements/index.php') ?>" class="btn btn-outline-secondary btn-sm rounded-pill mb-4 px-3">
                &larr; Back to All Announcements
            </a>

            <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white p-4 p-md-5">
                
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-<?= $priorityColors[$ann['priority']] ?? 'secondary' ?> rounded-pill px-3 py-1 text-uppercase">
                            <?= e($ann['priority']) ?>
                        </span>

                        <?php if (!empty($ann['course_title'])): ?>
                            <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 py-1">
                                <i class="bi bi-mortarboard me-1"></i> Course: <?= e($ann['course_title']) ?>
                            </span>
                        <?php else: ?>
                            <span class="badge bg-secondary bg-opacity-10 text-secondary rounded-pill px-3 py-1 text-capitalize">
                                Target: <?= e($ann['target_type']) ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <small class="text-muted">
                        <i class="bi bi-calendar3 me-1"></i> <?= date('F d, Y · h:i A', strtotime($ann['created_at'])) ?>
                    </small>
                </div>

                <h2 class="display-6 fw-bold mb-4 text-dark"><?= e($ann['title']) ?></h2>

                <!-- Author details bar -->
                <div class="p-3 bg-light rounded-4 d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="p-2 bg-primary text-white rounded-circle fs-4">
                            <i class="bi bi-person-fill"></i>
                        </div>
                        <div>
                            <div class="fw-bold mb-0"><?= e($ann['author_name'] ?: 'StudyMe Administration') ?></div>
                            <small class="text-muted text-capitalize"><?= e($ann['author_role'] ?: $ann['creator_role']) ?> · StudyMe AI Platform</small>
                        </div>
                    </div>

                    <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 py-1">
                        <i class="bi bi-check2 me-1"></i> Marked as Read
                    </span>
                </div>

                <!-- Announcement Body -->
                <div class="announcement-content lead fs-6 text-dark mb-4" style="line-height: 1.8;">
                    <?= nl2br(e($ann['content'])) ?>
                </div>

                <!-- Attachment Box if present -->
                <?php if (!empty($ann['attachment'])): ?>
                    <?php 
                    $ext = strtolower(pathinfo($ann['attachment'], PATHINFO_EXTENSION));
                    $fileName = basename($ann['attachment']);
                    ?>
                    <div class="card border border-primary border-opacity-25 bg-primary bg-opacity-10 rounded-4 p-3 mt-4">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                            <div class="d-flex align-items-center gap-3">
                                <div class="p-2 bg-primary text-white rounded-3 fs-3">
                                    <i class="bi bi-file-earmark-arrow-down-fill"></i>
                                </div>
                                <div>
                                    <div class="fw-bold text-primary mb-0">Attached Resource</div>
                                    <small class="text-muted font-monospace"><?= e($fileName) ?></small>
                                </div>
                            </div>
                            <a href="<?= url($ann['attachment']) ?>" download target="_blank" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm">
                                <i class="bi bi-download me-1"></i> Download File
                            </a>
                        </div>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </div>
</div>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
