<?php
/**
 * StudyMe AI Platform — Teacher Course Editor
 * Features strict backend ownership validation.
 */
require_once dirname(__DIR__) . '/config/main.php';

secure_page(ROLE_TEACHER);
if (current_user_role() !== ROLE_ADMIN) {
    require_active_subscription();
}

$user = current_user();
$pdo  = getDBConnection();
$uid  = $user['id'];
$cid  = (int)($_GET['id'] ?? 0);

// Resolve teacher record
$stmt = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ? LIMIT 1");
$stmt->execute([$uid]);
$teacher = $stmt->fetch(PDO::FETCH_ASSOC);
$tid = $teacher ? (int)$teacher['id'] : 0;

// Fetch course record
$stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ? LIMIT 1");
$stmt->execute([$cid]);
$course = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$course) {
    set_flash('error', 'Course not found.');
    redirect('teacher/courses.php');
}

// STRICT SECURITY CHECK: Course Ownership Authorization
if ($course['teacher_id'] != $tid && current_user_role() !== ROLE_ADMIN) {
    set_flash('error', 'ACCESS DENIED: You are not authorized to edit another teacher\'s course.');
    redirect('teacher/courses.php');
}

$categories = $pdo->query("SELECT id, name FROM categories WHERE status='active' ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

if (is_post()) {
    $title            = trim($_POST['title'] ?? '');
    $categoryId       = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    $shortDescription = trim($_POST['short_description'] ?? '');
    $description      = trim($_POST['description'] ?? '');
    $level            = $_POST['level'] ?? 'beginner';
    $price            = (float)($_POST['price'] ?? 0.00);
    $status           = $_POST['status'] ?? 'draft';
    $thumbnail        = $course['thumbnail'];

    if (empty($title)) {
        set_flash('error', 'Course title cannot be empty.');
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
                SET category_id = ?, title = ?, short_description = ?, description = ?, 
                    thumbnail = ?, level = ?, price = ?, status = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$categoryId, $title, $shortDescription, $description, $thumbnail, $level, $price, $status, $cid]);

            set_flash('success', 'Course updated successfully!');
            redirect('teacher/edit-course.php?id=' . $cid);
        } catch (Exception $e) {
            set_flash('error', 'Update failed: ' . $e->getMessage());
        }
    }
}

include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <p class="text-muted mb-1 fw-semibold text-uppercase small"><i class="bi bi-pencil-square text-primary me-1"></i> Instructor Suite</p>
        <h2 class="fw-bold mb-0">Edit Course: <?= e($course['title']) ?></h2>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url('teacher/courses.php') ?>" class="btn btn-outline-secondary rounded-pill px-4">
            <i class="bi bi-arrow-left me-1"></i> Back to Courses
        </a>
        <a href="<?= url('courses/details.php?slug=' . urlencode($course['slug'])) ?>" class="btn btn-outline-primary rounded-pill px-3" target="_blank">
            <i class="bi bi-eye me-1"></i> Preview
        </a>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm rounded-4 p-4">
            <form method="POST" enctype="multipart/form-data">
                <div class="mb-3">
                    <label class="form-label fw-bold">Course Title <span class="text-danger">*</span></label>
                    <input type="text" name="title" class="form-control rounded-3" value="<?= e($course['title']) ?>" required>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Category</label>
                        <select name="category_id" class="form-select rounded-3">
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= $course['category_id'] == $cat['id'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Skill Level</label>
                        <select name="level" class="form-select rounded-3">
                            <option value="beginner" <?= $course['level'] === 'beginner' ? 'selected' : '' ?>>Beginner</option>
                            <option value="intermediate" <?= $course['level'] === 'intermediate' ? 'selected' : '' ?>>Intermediate</option>
                            <option value="advanced" <?= $course['level'] === 'advanced' ? 'selected' : '' ?>>Advanced</option>
                            <option value="all_levels" <?= $course['level'] === 'all_levels' ? 'selected' : '' ?>>All Levels</option>
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Short Summary</label>
                    <textarea name="short_description" class="form-control rounded-3" rows="2"><?= e($course['short_description']) ?></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Full Course Description</label>
                    <textarea name="description" class="form-control rounded-3" rows="5"><?= e($course['description']) ?></textarea>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Update Thumbnail</label>
                        <input type="file" name="thumbnail" class="form-control rounded-3" accept="image/*">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Publication Status</label>
                        <select name="status" class="form-select rounded-3">
                            <option value="draft" <?= $course['status'] === 'draft' ? 'selected' : '' ?>>Draft</option>
                            <option value="published" <?= $course['status'] === 'published' ? 'selected' : '' ?>>Published</option>
                        </select>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary rounded-pill px-5 fw-bold py-2">
                    <i class="bi bi-save me-1"></i> Update Changes
                </button>
            </form>
        </div>
    </div>

    <!-- Quick Navigation Links to Course Sub-Items -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm rounded-4 p-4">
            <h5 class="fw-bold mb-3">Course Management</h5>
            <div class="list-group list-group-flush">
                <a href="<?= url('teacher/lessons.php?course_id=' . $cid) ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-3 border-0 border-bottom">
                    <div><i class="bi bi-play-btn text-primary me-2"></i> Manage Lessons</div>
                    <i class="bi bi-chevron-right text-muted small"></i>
                </a>
                <a href="<?= url('teacher/tasks.php?course_id=' . $cid) ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-3 border-0 border-bottom">
                    <div><i class="bi bi-list-task text-warning me-2"></i> Manage Tasks</div>
                    <i class="bi bi-chevron-right text-muted small"></i>
                </a>
                <a href="<?= url('teacher/quizzes.php?course_id=' . $cid) ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-3 border-0 border-bottom">
                    <div><i class="bi bi-patch-question text-success me-2"></i> Manage Quizzes</div>
                    <i class="bi bi-chevron-right text-muted small"></i>
                </a>
                <a href="<?= url('teacher/resources.php?course_id=' . $cid) ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-3 border-0 border-bottom">
                    <div><i class="bi bi-file-earmark-pdf text-danger me-2"></i> PDF &amp; Resources</div>
                    <i class="bi bi-chevron-right text-muted small"></i>
                </a>
                <a href="<?= url('teacher/students.php?course_id=' . $cid) ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-3 border-0">
                    <div><i class="bi bi-people text-info me-2"></i> Enrolled Students</div>
                    <i class="bi bi-chevron-right text-muted small"></i>
                </a>
            </div>
        </div>
    </div>
</div>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
