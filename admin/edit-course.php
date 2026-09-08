<?php
/**
 * StudyMe AI Platform — Admin Course Editor
 */
require_once dirname(__DIR__) . '/config/main.php';

secure_page(ROLE_ADMIN);

$pdo = getDBConnection();
$cid = (int)($_GET['id'] ?? 0);

// Fetch course
$stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ? LIMIT 1");
$stmt->execute([$cid]);
$course = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$course) {
    set_flash('error', 'Course not found.');
    redirect('admin/courses.php');
}

$categories = $pdo->query("SELECT id, name, slug FROM categories WHERE status='active' ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$teachers   = $pdo->query("
    SELECT t.id, CONCAT(u.first_name, ' ', u.last_name) AS teacher_name, u.email 
    FROM teachers t 
    JOIN users u ON t.user_id = u.id 
    WHERE t.status = 'active'
    ORDER BY u.first_name ASC
")->fetchAll(PDO::FETCH_ASSOC);

if (is_post()) {
    $title            = trim($_POST['title'] ?? '');
    $categoryId       = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    $teacherId        = !empty($_POST['teacher_id']) ? (int)$_POST['teacher_id'] : $course['teacher_id'];
    $price            = (float)($_POST['price'] ?? $course['price']);
    $durationMinutes  = (int)($_POST['duration_minutes'] ?? $course['duration_minutes']);
    $shortDescription = trim($_POST['short_description'] ?? '');
    $description      = trim($_POST['description'] ?? '');
    $level            = $_POST['level'] ?? 'beginner';
    $status           = $_POST['status'] ?? 'draft';
    $thumbnail        = $course['thumbnail'];

    if (empty($title)) {
        set_flash('error', 'Course title is required.');
    } else {
        if (!empty($_FILES['thumbnail']['name']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['thumbnail']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                $destDir = BASE_PATH . '/uploads/thumbnails';
                if (!is_dir($destDir)) mkdir($destDir, 0755, true);
                $fileName = 'thumb_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
                if (move_uploaded_file($_FILES['thumbnail']['tmp_name'], $destDir . '/' . $fileName)) {
                    $thumbnail = 'uploads/thumbnails/' . $fileName;
                }
            }
        }

        try {
            $stmt = $pdo->prepare("
                UPDATE courses 
                SET category_id = ?, teacher_id = ?, title = ?, price = ?, duration_minutes = ?,
                    short_description = ?, description = ?, thumbnail = ?, level = ?, status = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$categoryId, $teacherId, $title, $price, $durationMinutes, $shortDescription, $description, $thumbnail, $level, $status, $cid]);

            set_flash('success', 'Course updated successfully! Changes are live across the platform.');
            redirect('admin/edit-course.php?id=' . $cid);
        } catch (Exception $e) {
            set_flash('error', 'Update error: ' . $e->getMessage());
        }
    }
}

include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div>
        <p class="text-muted mb-1 fw-semibold text-uppercase small"><i class="bi bi-pencil-square text-primary me-1"></i> Admin Command Center</p>
        <h2 class="fw-bold mb-0">Edit Course: <?= e($course['title']) ?></h2>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url('courses/details.php?slug=' . urlencode($course['slug'])) ?>" class="btn btn-outline-primary rounded-pill px-3" target="_blank">
            <i class="bi bi-box-arrow-up-right me-1"></i> Public Page
        </a>
        <a href="<?= url('admin/courses.php') ?>" class="btn btn-outline-secondary rounded-pill px-4">
            <i class="bi bi-arrow-left me-1"></i> Back to Courses
        </a>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4 p-4 p-md-5">
    <form method="POST" enctype="multipart/form-data">
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="mb-3">
                    <label class="form-label fw-bold small">Course Title <span class="text-danger">*</span></label>
                    <input type="text" name="title" class="form-control rounded-3" value="<?= e($course['title']) ?>" required>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold small">Short Summary (Headline)</label>
                    <textarea name="short_description" class="form-control rounded-3" rows="2"><?= e($course['short_description']) ?></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold small">Full Course Description</label>
                    <textarea name="description" class="form-control rounded-3" rows="6"><?= e($course['description']) ?></textarea>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="mb-3">
                    <label class="form-label fw-bold small">Category <span class="text-danger">*</span></label>
                    <select name="category_id" class="form-select rounded-3" required id="catSelect" onchange="autoFillPrice(this)">
                        <option value="">Select Category</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" data-slug="<?= $cat['slug'] ?>" <?= $course['category_id'] == $cat['id'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold small">Assigned Instructor</label>
                    <select name="teacher_id" class="form-select rounded-3">
                        <?php foreach ($teachers as $tch): ?>
                        <option value="<?= $tch['id'] ?>" <?= $course['teacher_id'] == $tch['id'] ? 'selected' : '' ?>><?= e($tch['teacher_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold small">Official Course Price (₦)</label>
                    <input type="number" name="price" id="coursePrice" class="form-control rounded-3 fw-bold text-success" value="<?= (float)$course['price'] ?>" min="0" step="500" required>
                    <small class="text-muted" style="font-size:0.75rem;">Technology: ₦10,000 | Secondary: ₦3,000 | University: ₦4,000</small>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label fw-bold small">Skill Level</label>
                        <select name="level" class="form-select rounded-3">
                            <option value="beginner" <?= $course['level'] === 'beginner' ? 'selected' : '' ?>>Beginner</option>
                            <option value="intermediate" <?= $course['level'] === 'intermediate' ? 'selected' : '' ?>>Intermediate</option>
                            <option value="advanced" <?= $course['level'] === 'advanced' ? 'selected' : '' ?>>Advanced</option>
                            <option value="all_levels" <?= $course['level'] === 'all_levels' ? 'selected' : '' ?>>All Levels</option>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-bold small">Duration (Mins)</label>
                        <input type="number" name="duration_minutes" class="form-control rounded-3" value="<?= (int)$course['duration_minutes'] ?>" min="60" step="60">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold small">Publication Status</label>
                    <select name="status" class="form-select rounded-3">
                        <option value="published" <?= $course['status'] === 'published' ? 'selected' : '' ?>>Published (Live)</option>
                        <option value="draft" <?= $course['status'] === 'draft' ? 'selected' : '' ?>>Draft</option>
                        <option value="archived" <?= $course['status'] === 'archived' ? 'selected' : '' ?>>Archived</option>
                    </select>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-bold small">Update Thumbnail</label>
                    <input type="file" name="thumbnail" class="form-control rounded-3" accept="image/*">
                    <?php if (!empty($course['thumbnail'])): ?>
                    <div class="mt-2">
                        <img src="<?= e($course['thumbnail']) ?>" class="rounded-3 border" style="max-height:80px; object-fit:cover;" alt="Current Thumbnail">
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="d-flex gap-3 pt-3 border-top">
            <button type="submit" class="btn btn-primary rounded-pill px-5 fw-bold py-2 shadow">
                <i class="bi bi-save me-1"></i> Save Changes
            </button>
            <a href="<?= url('admin/courses.php') ?>" class="btn btn-outline-secondary rounded-pill px-4">Cancel</a>
        </div>
    </form>
</div>

<script>
function autoFillPrice(selectEl) {
    const selectedOption = selectEl.options[selectEl.selectedIndex];
    const slug = selectedOption.getAttribute('data-slug');
    const priceInput = document.getElementById('coursePrice');
    if (slug === 'secondary-waec-neco') {
        priceInput.value = 3000;
    } else if (slug === 'university') {
        priceInput.value = 4000;
    } else if (slug === 'teacher-professional') {
        priceInput.value = 5000;
    } else if (slug === 'technology') {
        priceInput.value = 10000;
    }
}
</script>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
