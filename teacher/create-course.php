<?php

require_once dirname(__DIR__) . '/config/main.php';
require_once BASE_PATH . '/includes/functions/activity.php';

secure_page(ROLE_TEACHER);
if (current_user_role() !== ROLE_ADMIN) {
    require_active_subscription();
}

$user = current_user();
$pdo  = getDBConnection();
$uid  = (int)$user['id'];

$stmt = $pdo->prepare("SELECT * FROM teachers WHERE user_id = ? LIMIT 1");
$stmt->execute([$uid]);
$teacher = $stmt->fetch(PDO::FETCH_ASSOC);
$tid = $teacher ? (int)$teacher['id'] : 0;

if (!$tid) {
    try {
        $tNum = 'TCH-' . date('Y') . '-' . str_pad($uid, 4, '0', STR_PAD_LEFT);
        $pdo->prepare("INSERT INTO teachers (user_id, teacher_number, status, created_at) VALUES (?, ?, 'active', NOW())")
            ->execute([$uid, $tNum]);
        $tid = (int)$pdo->lastInsertId();
        $stmt->execute([$uid]);
        $teacher = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $stmt->execute([$uid]);
        $teacher = $stmt->fetch(PDO::FETCH_ASSOC);
        $tid = $teacher ? (int)$teacher['id'] : 0;
    }
}

$categories = $pdo->query("SELECT id, name, slug FROM categories WHERE status='active' ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$departments = $pdo->query("SELECT id, name, slug, description FROM departments ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

if (is_post()) {
    $title            = trim($_POST['title'] ?? '');
    $categoryId       = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    $departmentId     = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
    $academicLevel    = trim($_POST['academic_level'] ?? '100 Level');
    $academicYear     = trim($_POST['academic_year'] ?? 'Year 1');
    $shortDescription = trim($_POST['short_description'] ?? '');
    $description      = trim($_POST['description'] ?? '');
    $level            = $_POST['level'] ?? 'beginner';
    $status           = $_POST['status'] ?? 'published';

    $price = 10000.00;
    if ($categoryId) {
        $stmtCat = $pdo->prepare("SELECT slug FROM categories WHERE id = ? LIMIT 1");
        $stmtCat->execute([$categoryId]);
        $cSlug = $stmtCat->fetchColumn();
        if ($cSlug === 'university') {
            $price = 5000.00;
        } elseif ($cSlug === 'secondary-waec-neco') {
            $price = 3000.00;
        } elseif ($cSlug === 'teacher-development') {
            $price = 4000.00;
        }
    }

    if (empty($title)) {
        set_flash('error', 'Course title is required.');
    } else {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title), '-')) . '-' . time();

        $thumbnail = 'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?w=500&q=80';
        if (isset($cSlug) && $cSlug === 'university') {
            $thumbnail = 'https://images.unsplash.com/photo-1523240795612-9a054b0db644?w=500&q=80';
        }

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
                INSERT INTO courses (
                    teacher_id, category_id, department_id, title, slug,
                    short_description, description, thumbnail, level,
                    academic_level, academic_year, price, status, created_at
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $tid, $categoryId, $departmentId, $title, $slug,
                $shortDescription, $description, $thumbnail, $level,
                $academicLevel, $academicYear, $price, $status
            ]);
            $courseId = (int)$pdo->lastInsertId();

            $pdo->prepare("UPDATE teachers SET assigned_course_id = ?, assigned_category_id = ? WHERE id = ?")
                ->execute([$courseId, $categoryId, $tid]);

            log_user_activity($uid, 'course_created', "Created course: $title ($academicLevel / $academicYear)");

            set_flash('success', "Course '$title' created successfully! You can now add lesson modules and multimedia content.");
            redirect('teacher/edit-course.php?id=' . $courseId);
        } catch (Exception $e) {
            set_flash('error', 'Database error: ' . $e->getMessage());
        }
    }
}

include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
    <div>
        <p class="text-muted mb-1 fw-semibold text-uppercase small"><i class="bi bi-plus-circle-fill text-primary me-1"></i> Instructor Suite</p>
        <h2 class="fw-bold mb-0">Create New Course</h2>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url('teacher/select-course.php') ?>" class="btn btn-outline-primary rounded-pill px-4">
            <i class="bi bi-collection-play me-1"></i> Select Existing Course
        </a>
        <a href="<?= url('teacher/courses.php') ?>" class="btn btn-outline-secondary rounded-pill px-4">
            <i class="bi bi-arrow-left me-1"></i> Cancel
        </a>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 max-w-2xl bg-white mb-5">
    <form method="POST" enctype="multipart/form-data">

        <div class="mb-4">
            <label class="form-label fw-bold text-dark fs-6">Course Title <span class="text-danger">*</span></label>
            <input type="text" name="title" class="form-control form-control-lg rounded-3 py-3" placeholder="e.g. CSC 301: Advanced Data Structures &amp; Algorithms" required>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <label class="form-label fw-bold">Track / Category <span class="text-danger">*</span></label>
                <select name="category_id" id="category_id" class="form-select form-select-lg rounded-3 py-3" required>
                    <option value="">Select Category</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>"><?= e($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-bold">Faculty / Department (Optional for Uni)</label>
                <select name="department_id" class="form-select form-select-lg rounded-3 py-3">
                    <option value="">-- None / General --</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?= $dept['id'] ?>"><?= e($dept['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="row g-3 mb-4 p-3 bg-light rounded-3 border border-subtle">
            <div class="col-12">
                <h6 class="fw-bold text-primary mb-1"><i class="bi bi-mortarboard me-1"></i> University Level &amp; Academic Year</h6>
                <p class="text-muted small mb-0">Specify the undergraduate level/year so students in that academic cohort can easily discover this curriculum.</p>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold small">University Level</label>
                <select name="academic_level" class="form-select rounded-3 py-2">
                    <option value="100 Level">100 Level (Year 1 - Foundational)</option>
                    <option value="200 Level">200 Level (Year 2 - Intermediate)</option>
                    <option value="300 Level">300 Level (Year 3 - Core Discipline)</option>
                    <option value="400 Level">400 Level (Year 4 - Advanced Capstone)</option>
                    <option value="500 Level">500 Level (Year 5 - Engineering/Law)</option>
                    <option value="600 Level">600 Level (Year 6 - Medicine/Surgery)</option>
                    <option value="Postgraduate">Postgraduate (Masters/Ph.D)</option>
                    <option value="All Levels">All Levels</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold small">Academic Year</label>
                <select name="academic_year" class="form-select rounded-3 py-2">
                    <option value="Year 1">Year 1</option>
                    <option value="Year 2">Year 2</option>
                    <option value="Year 3">Year 3</option>
                    <option value="Year 4">Year 4</option>
                    <option value="Year 5">Year 5</option>
                    <option value="Year 6">Year 6</option>
                    <option value="N/A">Not Applicable</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold small">Skill Complexity</label>
                <select name="level" class="form-select rounded-3 py-2">
                    <option value="beginner">Beginner</option>
                    <option value="intermediate">Intermediate</option>
                    <option value="advanced">Advanced</option>
                    <option value="all_levels">All Levels</option>
                </select>
            </div>
        </div>

        <div class="mb-4">
            <label class="form-label fw-bold">Short Summary</label>
            <textarea name="short_description" class="form-control rounded-3" rows="2" placeholder="Brief 1-2 sentence overview of what students will master..."></textarea>
        </div>

        <div class="mb-4">
            <label class="form-label fw-bold">Full Curriculum &amp; Syllabus Breakdown</label>
            <textarea name="description" class="form-control rounded-3" rows="5" placeholder="Detailed syllabus breakdown, prerequisites, textbook references, and learning objectives..."></textarea>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <label class="form-label fw-bold">Course Thumbnail (Optional)</label>
                <input type="file" name="thumbnail" class="form-control rounded-3" accept="image/*">
                <small class="text-muted">Recommended: 1280x720 JPG, PNG, or WebP.</small>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-bold">Publishing Status</label>
                <select name="status" class="form-select rounded-3">
                    <option value="published">Published (Live for Student Enrollment)</option>
                    <option value="draft">Draft (Work in Progress)</option>
                </select>
            </div>
        </div>

        <div class="d-flex gap-3 pt-3 border-top">
            <button type="submit" class="btn btn-primary rounded-pill px-5 fw-bold py-3 shadow-sm">
                <i class="bi bi-check-circle-fill me-2"></i> Save Course &amp; Add Lessons
            </button>
            <a href="<?= url('teacher/courses.php') ?>" class="btn btn-outline-secondary rounded-pill px-4 py-3">Cancel</a>
        </div>
    </form>
</div>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
