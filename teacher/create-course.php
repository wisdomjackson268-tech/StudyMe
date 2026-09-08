<?php
/**
 * StudyMe AI Platform — Teacher Course Creation
 */
require_once dirname(__DIR__) . '/config/main.php';

secure_page(ROLE_TEACHER);
if (current_user_role() !== ROLE_ADMIN) {
    require_active_subscription();
}

$user = current_user();
$pdo  = getDBConnection();
$uid  = $user['id'];

// Resolve teacher record
$stmt = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ? LIMIT 1");
$stmt->execute([$uid]);
$teacher = $stmt->fetch(PDO::FETCH_ASSOC);
$tid = $teacher ? (int)$teacher['id'] : 0;

if (!$tid) {
    set_flash('error', 'Teacher account profile required to create courses.');
    redirect('teacher/dashboard.php');
}

// Fetch categories
$categories = $pdo->query("SELECT id, name FROM categories WHERE status='active' ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

if (is_post()) {
    $title            = trim($_POST['title'] ?? '');
    $categoryId       = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    $shortDescription = trim($_POST['short_description'] ?? '');
    $description      = trim($_POST['description'] ?? '');
    $level            = $_POST['level'] ?? 'beginner';
    $price            = (float)($_POST['price'] ?? 0.00);
    $status           = $_POST['status'] ?? 'draft';

    if (empty($title)) {
        set_flash('error', 'Course title is required.');
    } else {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title), '-')) . '-' . time();
        $thumbnail = 'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?w=500&q=80';

        // File Upload Processing for Thumbnail if provided
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
                INSERT INTO courses (teacher_id, category_id, title, slug, short_description, description, thumbnail, level, price, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$tid, $categoryId, $title, $slug, $shortDescription, $description, $thumbnail, $level, $price, $status]);
            $courseId = $pdo->lastInsertId();

            set_flash('success', 'Course created successfully! Add lessons to your new course.');
            redirect('teacher/edit-course.php?id=' . $courseId);
        } catch (Exception $e) {
            set_flash('error', 'Database error: ' . $e->getMessage());
        }
    }
}

include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <p class="text-muted mb-1 fw-semibold text-uppercase small"><i class="bi bi-plus-circle-fill text-primary me-1"></i> Instructor Suite</p>
        <h2 class="fw-bold mb-0">Create New Course</h2>
    </div>
    <a href="<?= url('teacher/courses.php') ?>" class="btn btn-outline-secondary rounded-pill px-4">
        <i class="bi bi-arrow-left me-1"></i> Cancel
    </a>
</div>

<div class="card border-0 shadow-sm rounded-4 p-4 max-w-2xl">
    <form method="POST" enctype="multipart/form-data">
        <div class="mb-3">
            <label class="form-label fw-bold">Course Title <span class="text-danger">*</span></label>
            <input type="text" name="title" class="form-control rounded-3" placeholder="e.g. Masterclass in Quantum Computing" required>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <label class="form-label fw-bold">Category</label>
                <select name="category_id" class="form-select rounded-3">
                    <option value="">Select Category</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>"><?= e($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-bold">Skill Level</label>
                <select name="level" class="form-select rounded-3">
                    <option value="beginner">Beginner</option>
                    <option value="intermediate">Intermediate</option>
                    <option value="advanced">Advanced</option>
                    <option value="all_levels">All Levels</option>
                </select>
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label fw-bold">Short Summary</label>
            <textarea name="short_description" class="form-control rounded-3" rows="2" placeholder="Brief 1-2 sentence introduction to the course..."></textarea>
        </div>

        <div class="mb-3">
            <label class="form-label fw-bold">Full Course Description</label>
            <textarea name="description" class="form-control rounded-3" rows="5" placeholder="Detailed syllabus breakdown and prerequisites..."></textarea>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <label class="form-label fw-bold">Thumbnail Image</label>
                <input type="file" name="thumbnail" class="form-control rounded-3" accept="image/*">
            </div>
            <div class="col-md-6">
                <label class="form-label fw-bold">Status</label>
                <select name="status" class="form-select rounded-3">
                    <option value="draft">Draft</option>
                    <option value="published">Published</option>
                </select>
            </div>
        </div>

        <button type="submit" class="btn btn-primary rounded-pill px-5 fw-bold py-2">
            <i class="bi bi-check-circle me-1"></i> Save Course &amp; Continue
        </button>
    </form>
</div>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
