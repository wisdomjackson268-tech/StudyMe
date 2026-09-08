<?php
/**
 * StudyMe AI Platform — Admin Announcements Management & Audience Broadcasting
 */
require_once dirname(__DIR__) . '/config/main.php';
require_once BASE_PATH . '/includes/functions/announcements.php';

secure_page(ROLE_ADMIN);

$pdo    = getDBConnection();
$userId = (int)(current_user()['id'] ?? 0);
$errors = [];
$success = '';

// ── POST Actions ─────────────────────────────────────────────
if (is_post()) {
    $action = trim($_POST['action'] ?? '');

    if ($action === 'create' || $action === 'edit') {
        $id          = (int)($_POST['id'] ?? 0);
        $title       = trim($_POST['title'] ?? '');
        $message     = trim($_POST['message'] ?? '');
        $targetType  = in_array($_POST['target_type'] ?? '', ['all','students','teachers','technology','university','secondary','course','user']) ? $_POST['target_type'] : 'all';
        $courseId    = (int)($_POST['course_id'] ?? 0);
        $targetUserId= (int)($_POST['target_user_id'] ?? 0);
        $priority    = in_array($_POST['priority'] ?? '', ['normal','important','urgent']) ? $_POST['priority'] : 'normal';
        $status      = in_array($_POST['status'] ?? '', ['published','draft','archived']) ? $_POST['status'] : 'published';
        $attachment  = $_FILES['attachment'] ?? null;

        if (empty($title)) { $errors[] = 'Title is required.'; }
        if (empty($message)) { $errors[] = 'Message content is required.'; }

        if (empty($errors)) {
            if ($action === 'create') {
                $res = create_announcement_entry(
                    $title,
                    $message,
                    $userId,
                    'admin',
                    $targetType,
                    $courseId,
                    $priority,
                    $status,
                    $attachment,
                    $targetUserId
                );

                if ($res['success']) {
                    set_flash('success', 'Announcement published successfully to target audience!');
                    redirect('admin/announcements.php');
                } else {
                    $errors[] = $res['error'];
                }
            } else {
                // Edit existing announcement
                $attachmentPath = null;
                if (!empty($attachment) && isset($attachment['tmp_name']) && is_uploaded_file($attachment['tmp_name'])) {
                    $ext = strtolower(pathinfo($attachment['name'], PATHINFO_EXTENSION));
                    $uploadDir = BASE_PATH . '/uploads/announcements';
                    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                    $fileName = 'ann_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
                    if (move_uploaded_file($attachment['tmp_name'], $uploadDir . '/' . $fileName)) {
                        $attachmentPath = 'uploads/announcements/' . $fileName;
                    }
                }

                $stmtPrev = $pdo->prepare("SELECT status, attachment FROM announcements WHERE id = ? LIMIT 1");
                $stmtPrev->execute([$id]);
                $prev = $stmtPrev->fetch(PDO::FETCH_ASSOC);

                $finalAttachment = $attachmentPath ?: ($prev['attachment'] ?? null);
                $wasDraft = ($prev['status'] !== 'published');
                $publishedAt = ($status === 'published' && $wasDraft) ? date('Y-m-d H:i:s') : ($prev['published_at'] ?? null);

                $stmtUpd = $pdo->prepare("
                    UPDATE announcements 
                    SET title=?, content=?, target_type=?, course_id=?, target_user_id=?, priority=?, status=?, attachment=?, published_at=?, updated_at=NOW()
                    WHERE id=?
                ");
                $stmtUpd->execute([
                    $title,
                    $message,
                    $targetType,
                    $courseId ?: null,
                    $targetUserId ?: null,
                    $priority,
                    $status,
                    $finalAttachment,
                    $publishedAt,
                    $id
                ]);

                if ($status === 'published' && $wasDraft) {
                    publish_announcement_notifications($id);
                }

                set_flash('success', 'Announcement updated successfully.');
                redirect('admin/announcements.php');
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("DELETE FROM announcements WHERE id = ?")->execute([$id]);
        set_flash('success', 'Announcement deleted.');
        redirect('admin/announcements.php');
    } elseif ($action === 'toggle_status') {
        $id = (int)($_POST['id'] ?? 0);
        $newStatus = in_array($_POST['new_status'] ?? '', ['published','draft','archived']) ? $_POST['new_status'] : 'published';
        
        $publishedAt = ($newStatus === 'published') ? date('Y-m-d H:i:s') : null;
        $pdo->prepare("UPDATE announcements SET status=?, published_at=?, updated_at=NOW() WHERE id=?")
            ->execute([$newStatus, $publishedAt, $id]);

        if ($newStatus === 'published') {
            publish_announcement_notifications($id);
        }

        set_flash('success', 'Announcement marked as ' . strtoupper($newStatus));
        redirect('admin/announcements.php');
    }
}

// Fetch all courses and users for targeting selects
$allCourses = $pdo->query("SELECT id, title FROM courses ORDER BY title ASC")->fetchAll(PDO::FETCH_ASSOC);
$allUsers   = $pdo->query("SELECT id, first_name, last_name, email, role FROM users WHERE status = 'active' ORDER BY first_name ASC LIMIT 200")->fetchAll(PDO::FETCH_ASSOC);

// Fetch all announcements
$announcements = $pdo->query("
    SELECT a.*, 
           CONCAT(u.first_name,' ',u.last_name) AS author_name,
           c.title AS course_title
    FROM announcements a
    LEFT JOIN users u ON a.created_by = u.id
    LEFT JOIN courses c ON a.course_id = c.id
    ORDER BY a.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

$priorityColors = ['normal'=>'secondary','important'=>'warning text-dark','urgent'=>'danger'];

include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<div class="container-fluid py-4">

    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
        <div>
            <p class="text-muted mb-1 fw-semibold text-uppercase small"><i class="bi bi-megaphone-fill text-warning me-1"></i> Admin Command Center</p>
            <h2 class="fw-bold mb-0">Platform Announcement Broadcasts</h2>
        </div>
        <div class="d-flex align-items-center gap-2">
            <button class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#annModal">
                <i class="bi bi-plus-circle me-1"></i> Create Announcement
            </button>
            <a href="<?= url('announcements/index.php') ?>" class="btn btn-outline-secondary rounded-pill px-3 btn-sm">
                <i class="bi bi-inbox me-1"></i> Public Feed
            </a>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger rounded-4 mb-4">
            <ul class="mb-0 small ps-3">
                <?php foreach($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- Announcements Table Card -->
    <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden mb-4">
        <div class="card-header bg-white py-3 px-4 border-0 d-flex align-items-center justify-content-between">
            <h5 class="fw-bold mb-0">Broadcast Telemetry &amp; Announcement Records</h5>
            <span class="badge bg-secondary rounded-pill px-3"><?= count($announcements) ?> Total</span>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small text-uppercase text-muted">
                    <tr>
                        <th class="ps-4">Title &amp; Creator</th>
                        <th>Target Audience</th>
                        <th>Recipients</th>
                        <th>Read Telemetry</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Created Date</th>
                        <th class="pe-4 text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($announcements)): ?>
                        <?php foreach ($announcements as $ann): ?>
                            <?php $stats = get_announcement_telemetry($ann['id']); ?>
                            <tr>
                                <td class="ps-4" style="max-width: 280px;">
                                    <div class="fw-bold text-dark mb-0">
                                        <a href="<?= url('announcements/view.php?id=' . $ann['id']) ?>" class="text-decoration-none text-dark hover-primary">
                                            <?= e($ann['title']) ?>
                                        </a>
                                    </div>
                                    <small class="text-muted">By <?= e($ann['author_name'] ?: 'System') ?> (<?= ucfirst($ann['creator_role']) ?>)</small>
                                </td>
                                <td>
                                    <?php if ($ann['target_type'] === 'course' && !empty($ann['course_title'])): ?>
                                        <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 py-1">
                                            <i class="bi bi-mortarboard me-1"></i> Course: <?= e($ann['course_title']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary bg-opacity-10 text-secondary rounded-pill px-3 py-1 text-capitalize">
                                            <?= e($ann['target_type']) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="fw-bold text-dark">
                                    <i class="bi bi-people me-1 text-muted"></i> <?= $stats['recipients_count'] ?>
                                </td>
                                <td>
                                    <div class="small">
                                        <span class="text-success fw-bold"><?= $stats['read_count'] ?> Read</span> / 
                                        <span class="text-warning fw-bold"><?= $stats['unread_count'] ?> Unread</span>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-<?= $priorityColors[$ann['priority']] ?? 'secondary' ?> rounded-pill px-2">
                                        <?= ucfirst($ann['priority']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($ann['status'] === 'published'): ?>
                                        <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 py-1">Published</span>
                                    <?php elseif ($ann['status'] === 'draft'): ?>
                                        <span class="badge bg-warning bg-opacity-10 text-warning rounded-pill px-3 py-1">Draft</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary bg-opacity-10 text-secondary rounded-pill px-3 py-1">Archived</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small text-muted"><?= date('M d, Y · h:i A', strtotime($ann['created_at'])) ?></td>
                                <td class="pe-4 text-end">
                                    <a href="<?= url('announcements/view.php?id=' . $ann['id']) ?>" class="btn btn-sm btn-outline-secondary rounded-circle me-1" title="View">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <form action="<?= url('admin/announcements.php') ?>" method="POST" class="d-inline" onsubmit="return confirm('Delete this announcement permanently?');">
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
                            <td colspan="8" class="text-center py-5 text-muted">No announcements recorded. Click "Create Announcement" above to broadcast.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- Modal: Create / Broadcast Announcement -->
<div class="modal fade" id="annModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow-lg">
            <div class="modal-header bg-dark text-white border-0">
                <h5 class="modal-title fw-bold text-white"><i class="bi bi-megaphone-fill me-2"></i> Create Announcement</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="<?= url('admin/announcements.php') ?>" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="create">
                
                <div class="modal-body p-4">
                    
                    <div class="mb-3">
                        <label for="title" class="form-label fw-bold small">Announcement Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" id="title" class="form-control form-control-lg rounded-3" placeholder="e.g. StudyMe Scheduled Maintenance & Updates" required>
                    </div>

                    <!-- Target Audience Selector -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="target_type" class="form-label fw-bold small">Target Audience <span class="text-danger">*</span></label>
                            <select name="target_type" id="target_type" class="form-select rounded-3" onchange="toggleAudienceOptions(this.value)" required>
                                <option value="all">All Users (Students, Teachers, Admins)</option>
                                <option value="students">All Students</option>
                                <option value="teachers">All Teachers</option>
                                <option value="technology">Technology Students Only</option>
                                <option value="university">University Students Only</option>
                                <option value="secondary">Secondary School Students Only</option>
                                <option value="course">Specific Course (Enrolled Students)</option>
                                <option value="user">Specific User</option>
                            </select>
                        </div>

                        <!-- Specific Course Select (Hidden by default unless 'course' chosen) -->
                        <div class="col-md-6 d-none" id="courseSelectCol">
                            <label for="course_id" class="form-label fw-bold small">Select Course</label>
                            <select name="course_id" id="course_id" class="form-select rounded-3">
                                <option value="">-- Choose Course --</option>
                                <?php foreach ($allCourses as $crs): ?>
                                    <option value="<?= $crs['id'] ?>"><?= e($crs['title']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Specific User Select (Hidden by default unless 'user' chosen) -->
                        <div class="col-md-6 d-none" id="userSelectCol">
                            <label for="target_user_id" class="form-label fw-bold small">Select User</label>
                            <select name="target_user_id" id="target_user_id" class="form-select rounded-3">
                                <option value="">-- Choose User --</option>
                                <?php foreach ($allUsers as $u): ?>
                                    <option value="<?= $u['id'] ?>"><?= e($u['first_name'] . ' ' . $u['last_name']) ?> (<?= e($u['email']) ?> - <?= ucfirst($u['role']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6" id="priorityCol">
                            <label for="priority" class="form-label fw-bold small">Priority Level</label>
                            <select name="priority" id="priority" class="form-select rounded-3">
                                <option value="normal">Normal Update</option>
                                <option value="important">Important Notice</option>
                                <option value="urgent">Urgent / Immediate Action</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="message" class="form-label fw-bold small">Message Content <span class="text-danger">*</span></label>
                        <textarea name="message" id="message" rows="5" class="form-control rounded-3" placeholder="Enter full announcement details..." required></textarea>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="status" class="form-label fw-bold small">Publication Status</label>
                            <select name="status" id="status" class="form-select rounded-3">
                                <option value="published">Publish &amp; Notify Immediately</option>
                                <option value="draft">Save as Draft</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="attachment" class="form-label fw-bold small">Attach Resource (Optional)</label>
                            <input type="file" name="attachment" id="attachment" class="form-control rounded-3" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.webp,.txt,.zip">
                        </div>
                    </div>

                </div>

                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-5 fw-bold shadow-sm">
                        <i class="bi bi-send-fill me-1"></i> Broadcast Announcement
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function toggleAudienceOptions(val) {
    const courseCol = document.getElementById('courseSelectCol');
    const userCol = document.getElementById('userSelectCol');
    const priorityCol = document.getElementById('priorityCol');

    if (val === 'course') {
        courseCol.classList.remove('d-none');
        userCol.classList.add('d-none');
    } else if (val === 'user') {
        userCol.classList.remove('d-none');
        courseCol.classList.add('d-none');
    } else {
        courseCol.classList.add('d-none');
        userCol.classList.add('d-none');
    }
}
</script>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
