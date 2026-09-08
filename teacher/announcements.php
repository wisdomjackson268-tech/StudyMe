<?php
/**
 * StudyMe AI Platform — Teacher Announcements Management
 */
require_once dirname(__DIR__) . '/config/main.php';
require_once BASE_PATH . '/includes/functions/announcements.php';

secure_page(ROLE_TEACHER);
$user   = current_user();
$userId = (int)$user['id'];
$pdo    = getDBConnection();

// Handle Delete Announcement
if (is_post() && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $delId = (int)($_POST['id'] ?? 0);
    // Strict ownership verification: teacher can only delete their own announcements
    $stmtDel = $pdo->prepare("DELETE FROM announcements WHERE id = ? AND created_by = ?");
    $stmtDel->execute([$delId, $userId]);
    
    if ($stmtDel->rowCount() > 0) {
        set_flash('success', 'Announcement deleted successfully.');
    } else {
        set_flash('error', 'ACCESS DENIED: You cannot delete this announcement.');
    }
    redirect('teacher/announcements.php');
}

// Fetch teacher's assigned course & announcements
$stmtTeacher = $pdo->prepare("SELECT id, assigned_course_id FROM teachers WHERE user_id = ? LIMIT 1");
$stmtTeacher->execute([$userId]);
$teacher = $stmtTeacher->fetch(PDO::FETCH_ASSOC);
$teacherId = $teacher ? (int)$teacher['id'] : 0;

$stmtAnn = $pdo->prepare("
    SELECT a.*, c.title AS course_title
    FROM announcements a
    LEFT JOIN courses c ON a.course_id = c.id
    WHERE a.created_by = ?
    ORDER BY a.created_at DESC
");
$stmtAnn->execute([$userId]);
$myAnnouncements = $stmtAnn->fetchAll(PDO::FETCH_ASSOC);

include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
        <div>
            <h1 class="h3 fw-bold mb-1"><i class="bi bi-megaphone-fill text-warning me-2"></i> My Announcements</h1>
            <p class="text-muted small mb-0">Publish official updates, lesson alerts, and study resources to students enrolled in your courses.</p>
        </div>

        <div class="d-flex align-items-center gap-2">
            <a href="<?= url('teacher/create-announcement.php') ?>" class="btn btn-warning rounded-pill px-4 fw-bold text-dark shadow-sm" data-feedback="click">
                <i class="bi bi-plus-circle-fill me-1"></i> Create Announcement
            </a>
            <a href="<?= url('announcements/index.php') ?>" class="btn btn-outline-secondary rounded-pill px-3 btn-sm">
                <i class="bi bi-inbox me-1"></i> Public Feed
            </a>
        </div>
    </div>

    <!-- Announcement Table Card -->
    <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden">
        <div class="card-header bg-white py-3 px-4 border-0 d-flex align-items-center justify-content-between">
            <h5 class="fw-bold mb-0">Published &amp; Draft Announcements</h5>
            <span class="badge bg-secondary rounded-pill px-3"><?= count($myAnnouncements) ?> Announcements</span>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small text-uppercase text-muted">
                    <tr>
                        <th class="ps-4">Title &amp; Message</th>
                        <th>Target Course</th>
                        <th>Recipients</th>
                        <th>Read Status</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th class="pe-4 text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($myAnnouncements)): ?>
                        <?php foreach ($myAnnouncements as $ann): ?>
                            <?php $stats = get_announcement_telemetry($ann['id']); ?>
                            <tr>
                                <td class="ps-4" style="max-width: 320px;">
                                    <div class="fw-bold text-dark mb-1">
                                        <a href="<?= url('announcements/view.php?id=' . $ann['id']) ?>" class="text-decoration-none text-dark hover-primary">
                                            <?= e($ann['title']) ?>
                                        </a>
                                    </div>
                                    <div class="small text-muted text-truncate"><?= e(strip_tags($ann['content'])) ?></div>
                                    <?php if (!empty($ann['attachment'])): ?>
                                        <span class="badge bg-light text-primary border rounded-pill mt-1">
                                            <i class="bi bi-paperclip"></i> Attachment
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 py-1">
                                        <i class="bi bi-mortarboard me-1"></i> <?= e($ann['course_title'] ?: 'Assigned Course') ?>
                                    </span>
                                </td>
                                <td class="fw-bold text-dark">
                                    <i class="bi bi-people me-1 text-muted"></i> <?= $stats['recipients_count'] ?> Students
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="text-success fw-bold"><?= $stats['read_count'] ?> Read</span>
                                        <span class="text-muted">/</span>
                                        <span class="text-warning fw-bold"><?= $stats['unread_count'] ?> Unread</span>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($ann['priority'] === 'urgent'): ?>
                                        <span class="badge bg-danger rounded-pill px-2">Urgent</span>
                                    <?php elseif ($ann['priority'] === 'important'): ?>
                                        <span class="badge bg-warning text-dark rounded-pill px-2">Important</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary bg-opacity-10 text-secondary rounded-pill px-2">Normal</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($ann['status'] === 'published'): ?>
                                        <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 py-1">Published</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary bg-opacity-10 text-secondary rounded-pill px-3 py-1">Draft</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small text-muted"><?= date('M d, Y · h:i A', strtotime($ann['created_at'])) ?></td>
                                <td class="pe-4 text-end">
                                    <a href="<?= url('announcements/view.php?id=' . $ann['id']) ?>" class="btn btn-sm btn-outline-secondary rounded-circle me-1" title="View">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="<?= url('teacher/edit-announcement.php?id=' . $ann['id']) ?>" class="btn btn-sm btn-outline-primary rounded-circle me-1" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <form action="<?= url('teacher/announcements.php') ?>" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this announcement?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $ann['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger rounded-circle" title="Delete">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="bi bi-megaphone fs-1 d-block mb-2 text-muted"></i>
                                You haven't posted any announcements yet.
                                <div class="mt-3">
                                    <a href="<?= url('teacher/create-announcement.php') ?>" class="btn btn-warning btn-sm rounded-pill px-4 fw-bold text-dark">
                                        Create First Announcement
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
