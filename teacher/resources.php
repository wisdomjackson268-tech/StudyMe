<?php

require_once dirname(__DIR__) . '/config/main.php';

secure_page(ROLE_TEACHER);
if (current_user_role() !== ROLE_ADMIN) {
    require_active_subscription();
}

$user = current_user();
$pdo  = getDBConnection();
$uid  = $user['id'];
$courseFilter = (int)($_GET['course_id'] ?? 0);

$stmt = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ? LIMIT 1");
$stmt->execute([$uid]);
$teacher = $stmt->fetch(PDO::FETCH_ASSOC);
$tid = $teacher ? (int)$teacher['id'] : 0;

if (!$tid) {
    set_flash('error', 'Teacher account required.');
    redirect('teacher/dashboard.php');
}

$stmt = $pdo->prepare("SELECT id, title FROM courses WHERE teacher_id = ? ORDER BY title ASC");
$stmt->execute([$tid]);
$myCourses = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (isset($_GET['action']) && $_GET['action'] === 'delete' && !empty($_GET['resource_id'])) {
    $resourceId = (int)$_GET['resource_id'];
    $stmtRes = $pdo->prepare("SELECT * FROM resources WHERE id = ? AND teacher_id = ? LIMIT 1");
    $stmtRes->execute([$resourceId, $tid]);
    $res = $stmtRes->fetch(PDO::FETCH_ASSOC);

    if ($res) {

        $filePath = BASE_PATH . '/' . $res['file_path'];
        if (file_exists($filePath)) {
            @unlink($filePath);
        }
        $stmtDel = $pdo->prepare("DELETE FROM resources WHERE id = ?");
        $stmtDel->execute([$resourceId]);
        set_flash('success', 'Resource deleted successfully.');
    } else {
        set_flash('error', 'Resource not found or unauthorized.');
    }
    redirect('teacher/resources.php' . ($courseFilter ? '?course_id=' . $courseFilter : ''));
}

if (is_post() && isset($_POST['upload_resource'])) {
    $title       = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $courseId    = (int)($_POST['course_id'] ?? 0);

    $stmtCheck = $pdo->prepare("SELECT id FROM courses WHERE id = ? AND teacher_id = ? LIMIT 1");
    $stmtCheck->execute([$courseId, $tid]);

    if (!$stmtCheck->fetch()) {
        set_flash('error', 'ACCESS DENIED: Selected course does not belong to you.');
    } elseif (empty($title) || empty($_FILES['resource_file']['name'])) {
        set_flash('error', 'Title and PDF file are required.');
    } else {
        $file = $_FILES['resource_file'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            set_flash('error', 'File upload error code: ' . $file['error']);
        } else {
            $origName = basename($file['name']);
            $ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
            $finfo    = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if ($ext !== 'pdf' || strpos($mimeType, 'pdf') === false) {
                set_flash('error', 'SECURITY ERROR: Only authentic PDF documents (.pdf) can be uploaded.');
            } elseif ($file['size'] > 25 * 1024 * 1024) {
                set_flash('error', 'File size exceeds maximum limit of 25MB.');
            } else {
                $uploadDir = BASE_PATH . '/uploads/documents';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                    file_put_contents($uploadDir . '/.htaccess', "Options -Indexes\n<FilesMatch \"\\.php$\">\n  Deny from all\n</FilesMatch>\n");
                }

                $storedName = 'pdf_' . time() . '_' . rand(1000, 9999) . '.pdf';
                $targetPath = $uploadDir . '/' . $storedName;
                $relativePath = 'uploads/documents/' . $storedName;

                if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                    try {
                        $stmt = $pdo->prepare("
                            INSERT INTO resources (teacher_id, course_id, title, description, resource_type, original_filename, stored_filename, mime_type, file_size, file_path, created_at)
                            VALUES (?, ?, ?, ?, 'pdf', ?, ?, ?, ?, ?, NOW())
                        ");
                        $stmt->execute([$tid, $courseId, $title, $description, $origName, $storedName, $mimeType, $file['size'], $relativePath]);

                        set_flash('success', 'PDF Resource uploaded successfully!');
                        redirect('teacher/resources.php?course_id=' . $courseId);
                    } catch (Exception $e) {
                        @unlink($targetPath);
                        set_flash('error', 'Database record error: ' . $e->getMessage());
                    }
                } else {
                    set_flash('error', 'Failed to move uploaded PDF to storage directory.');
                }
            }
        }
    }
}

$params = [$tid];
$sql = "
    SELECT r.*, c.title AS course_title
    FROM resources r
    JOIN courses c ON r.course_id = c.id
    WHERE r.teacher_id = ?
";
if ($courseFilter > 0) {
    $sql .= " AND r.course_id = ?";
    $params[] = $courseFilter;
}
$sql .= " ORDER BY r.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$resources = $stmt->fetchAll(PDO::FETCH_ASSOC);

include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
    <div>
        <p class="text-muted mb-1 fw-semibold text-uppercase small"><i class="bi bi-file-earmark-pdf-fill text-danger me-1"></i> Instructor Suite</p>
        <h2 class="fw-bold mb-0">PDF &amp; Learning Resources</h2>
    </div>
</div>

<div class="row g-4">

    <div class="col-lg-5">
        <div class="card border-0 shadow-sm rounded-4 p-4 sticky-top" style="top: 90px;">
            <h5 class="fw-bold mb-3"><i class="bi bi-cloud-arrow-up-fill text-primary me-2"></i>Upload PDF Resource</h5>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="upload_resource" value="1">

                <div class="mb-3">
                    <label class="form-label fw-bold">Select Course <span class="text-danger">*</span></label>
                    <select name="course_id" class="form-select rounded-3" required>
                        <option value="">-- Select Owned Course --</option>
                        <?php foreach ($myCourses as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= $courseFilter === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Resource Title <span class="text-danger">*</span></label>
                    <input type="text" name="title" class="form-control rounded-3" placeholder="e.g. Chapter 1 Study Guide (PDF)" required>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Description (Optional)</label>
                    <textarea name="description" class="form-control rounded-3" rows="2" placeholder="Brief notes for students..."></textarea>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-bold">Select PDF File (.pdf) <span class="text-danger">*</span></label>
                    <input type="file" name="resource_file" class="form-control rounded-3" accept=".pdf,application/pdf" required>
                    <div class="form-text">Maximum size: 25MB. PDF documents only.</div>
                </div>

                <button type="submit" class="btn btn-primary rounded-pill w-100 fw-bold py-2">
                    <i class="bi bi-upload me-1"></i> Upload PDF Resource
                </button>
            </form>
        </div>
    </div>

    <div class="col-lg-7">
        <h5 class="fw-bold mb-3">Uploaded PDF Resources (<?= count($resources) ?>)</h5>

        <?php if (!empty($resources)): ?>
            <div class="d-flex flex-column gap-3">
                <?php foreach ($resources as $r): ?>
                    <div class="card border-0 shadow-sm rounded-4 p-4">
                        <div class="d-flex align-items-start justify-content-between gap-3">
                            <div class="d-flex align-items-start gap-3">
                                <div class="p-3 bg-danger bg-opacity-10 text-danger rounded-3 fs-3">
                                    <i class="bi bi-file-earmark-pdf-fill"></i>
                                </div>
                                <div>
                                    <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 py-1 small mb-1"><?= e($r['course_title']) ?></span>
                                    <h5 class="fw-bold text-main mb-1"><?= e($r['title']) ?></h5>
                                    <p class="text-muted small mb-2"><?= e($r['original_filename']) ?> &bull; <?= number_format($r['file_size'] / 1024, 1) ?> KB</p>
                                    <small class="text-muted"><i class="bi bi-clock me-1"></i>Uploaded <?= date('M d, Y', strtotime($r['created_at'])) ?></small>
                                </div>
                            </div>
                            <div class="d-flex gap-2">
                                <a href="<?= url($r['file_path']) ?>" class="btn btn-sm btn-outline-primary rounded-pill" target="_blank">
                                    <i class="bi bi-eye me-1"></i> View
                                </a>
                                <a href="<?= url('teacher/resources.php?action=delete&resource_id=' . $r['id'] . ($courseFilter ? '&course_id=' . $courseFilter : '')) ?>"
                                   class="btn btn-sm btn-outline-danger rounded-circle p-2"
                                   onclick="return confirm('Delete this PDF resource?');">
                                    <i class="bi bi-trash"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="card border-0 shadow-sm rounded-4 p-5 text-center">
                <i class="bi bi-file-earmark-pdf text-muted display-4 mb-3"></i>
                <h5 class="fw-bold mb-1">No PDF Resources Uploaded</h5>
                <p class="text-muted small mb-0">Use the form on the left to upload course materials for your students.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
