<?php
/**
 * StudyMe AI Platform — Teacher Edit Announcement Page
 */
require_once dirname(__DIR__) . '/config/main.php';
require_once BASE_PATH . '/includes/functions/announcements.php';

secure_page(ROLE_TEACHER);
$user   = current_user();
$userId = (int)$user['id'];
$annId  = (int)($_GET['id'] ?? 0);
$pdo    = getDBConnection();

// Fetch announcement with ownership check
$stmt = $pdo->prepare("SELECT * FROM announcements WHERE id = ? AND created_by = ? LIMIT 1");
$stmt->execute([$annId, $userId]);
$ann = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ann) {
    set_flash('error', 'Announcement not found or access denied.');
    redirect('teacher/announcements.php');
}

// Fetch teacher's assigned courses
$stmtTeacher = $pdo->prepare("SELECT id, assigned_course_id FROM teachers WHERE user_id = ? LIMIT 1");
$stmtTeacher->execute([$userId]);
$teacher = $stmtTeacher->fetch(PDO::FETCH_ASSOC);
$teacherId = $teacher ? (int)$teacher['id'] : 0;
$assignedCourseId = (int)($teacher['assigned_course_id'] ?? 0);

$stmtCourses = $pdo->prepare("SELECT id, title FROM courses WHERE teacher_id = ? OR id = ? ORDER BY title ASC");
$stmtCourses->execute([$teacherId, $assignedCourseId]);
$courses = $stmtCourses->fetchAll(PDO::FETCH_ASSOC);

$errors = [];
$title = $ann['title'];
$message = $ann['content'];
$selectedCourseId = (int)$ann['course_id'];
$priority = $ann['priority'];
$status = $ann['status'];

if (is_post()) {
    $title            = trim($_POST['title'] ?? '');
    $message          = trim($_POST['message'] ?? '');
    $selectedCourseId = (int)($_POST['course_id'] ?? 0);
    $priority         = in_array($_POST['priority'] ?? '', ['normal', 'important', 'urgent']) ? $_POST['priority'] : 'normal';
    $status           = in_array($_POST['status'] ?? '', ['published', 'draft', 'archived']) ? $_POST['status'] : 'published';
    $attachment       = $_FILES['attachment'] ?? null;

    if (empty($title)) {
        $errors[] = 'Announcement title is required.';
    }
    if (empty($message)) {
        $errors[] = 'Announcement message is required.';
    }

    if (empty($errors)) {
        $attachmentPath = $ann['attachment'];
        if (!empty($attachment) && isset($attachment['tmp_name']) && is_uploaded_file($attachment['tmp_name'])) {
            $allowedExts = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx', 'txt', 'zip'];
            $fileExt = strtolower(pathinfo($attachment['name'], PATHINFO_EXTENSION));

            if (in_array($fileExt, $allowedExts) && $attachment['size'] <= 15 * 1024 * 1024) {
                $uploadDir = BASE_PATH . '/uploads/announcements';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                $fileName = 'ann_' . time() . '_' . rand(1000, 9999) . '.' . $fileExt;
                if (move_uploaded_file($attachment['tmp_name'], $uploadDir . '/' . $fileName)) {
                    $attachmentPath = 'uploads/announcements/' . $fileName;
                }
            }
        }

        $wasDraft = ($ann['status'] !== 'published');
        $publishedAt = ($status === 'published' && $wasDraft) ? date('Y-m-d H:i:s') : $ann['published_at'];

        $stmtUpd = $pdo->prepare("
            UPDATE announcements 
            SET title = ?, content = ?, course_id = ?, priority = ?, status = ?, attachment = ?, published_at = ?, updated_at = NOW()
            WHERE id = ? AND created_by = ?
        ");
        $stmtUpd->execute([
            $title,
            $message,
            $selectedCourseId,
            $priority,
            $status,
            $attachmentPath,
            $publishedAt,
            $annId,
            $userId
        ]);

        if ($status === 'published' && $wasDraft) {
            publish_announcement_notifications($annId);
        }

        set_flash('success', 'Announcement updated successfully!');
        redirect('teacher/announcements.php');
    }
}

include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">

            <div class="d-flex align-items-center justify-content-between mb-4">
                <div>
                    <a href="<?= url('teacher/announcements.php') ?>" class="text-decoration-none small text-muted">
                        &larr; Back to My Announcements
                    </a>
                    <h2 class="fw-bold mb-0 mt-1"><i class="bi bi-pencil-square text-primary me-2"></i> Edit Announcement</h2>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 bg-white p-4 p-md-5">
                
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger rounded-3 mb-4">
                        <ul class="mb-0 small ps-3">
                            <?php foreach ($errors as $err): ?>
                                <li><?= e($err) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form action="<?= url('teacher/edit-announcement.php?id=' . $annId) ?>" method="POST" enctype="multipart/form-data">
                    
                    <div class="mb-3">
                        <label for="title" class="form-label fw-bold small">Announcement Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" id="title" class="form-control form-control-lg rounded-3" value="<?= e($title) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="course_id" class="form-label fw-bold small">Target Course</label>
                        <select name="course_id" id="course_id" class="form-select py-2 rounded-3" required>
                            <?php foreach ($courses as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= (int)$c['id'] === $selectedCourseId ? 'selected' : '' ?>>
                                    <?= e($c['title']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="message" class="form-label fw-bold small">Message Content <span class="text-danger">*</span></label>
                        <textarea name="message" id="message" rows="6" class="form-control rounded-3" required><?= e($message) ?></textarea>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label for="priority" class="form-label fw-bold small">Priority Level</label>
                            <select name="priority" id="priority" class="form-select py-2 rounded-3">
                                <option value="normal" <?= $priority === 'normal' ? 'selected' : '' ?>>Normal</option>
                                <option value="important" <?= $priority === 'important' ? 'selected' : '' ?>>Important</option>
                                <option value="urgent" <?= $priority === 'urgent' ? 'selected' : '' ?>>Urgent</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label for="status" class="form-label fw-bold small">Status</label>
                            <select name="status" id="status" class="form-select py-2 rounded-3">
                                <option value="published" <?= $status === 'published' ? 'selected' : '' ?>>Published</option>
                                <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>Draft</option>
                                <option value="archived" <?= $status === 'archived' ? 'selected' : '' ?>>Archived</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="attachment" class="form-label fw-bold small">Replace Attachment (Optional)</label>
                        <input type="file" name="attachment" id="attachment" class="form-control rounded-3" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.webp,.txt,.zip">
                        <?php if (!empty($ann['attachment'])): ?>
                            <div class="small text-muted mt-2">Current file: <code><?= e(basename($ann['attachment'])) ?></code></div>
                        <?php endif; ?>
                    </div>

                    <div class="d-flex align-items-center justify-content-between pt-3 border-top">
                        <a href="<?= url('teacher/announcements.php') ?>" class="btn btn-light rounded-pill px-4">Cancel</a>
                        <button type="submit" class="btn btn-primary rounded-pill px-5 fw-bold shadow-sm" data-feedback="click">
                            Save Changes
                        </button>
                    </div>

                </form>
            </div>
        </div>
    </div>
</div>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
