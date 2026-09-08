<?php
/**
 * StudyMe AI Platform — Admin Create Course
 */
require_once dirname(__DIR__) . '/config/main.php';

secure_page(ROLE_ADMIN);

$pdo = getDBConnection();
$errors = [];

// Fetch categories & teachers
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
    $categoryId       = (int)($_POST['category_id'] ?? 0);
    $teacherId        = (int)($_POST['teacher_id'] ?? 0);
    $price            = (float)($_POST['price'] ?? 0.0);
    $durationMinutes  = (int)($_POST['duration_minutes'] ?? 1800);
    $level            = in_array($_POST['level'] ?? '', ['beginner', 'intermediate', 'advanced', 'all_levels']) ? $_POST['level'] : 'beginner';
    $shortDescription = trim($_POST['short_description'] ?? '');
    $description      = trim($_POST['description'] ?? '');
    $status           = in_array($_POST['status'] ?? '', ['published', 'draft', 'archived']) ? $_POST['status'] : 'published';
    $thumbnail        = 'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?w=600&q=80';

    if (empty($title)) {
        $errors[] = 'Course title is required.';
    }
    if ($categoryId <= 0) {
        $errors[] = 'Please select a course category.';
    }
    if ($teacherId <= 0 && !empty($teachers)) {
        $teacherId = (int)$teachers[0]['id'];
    }

    // Default price from category if 0
    if ($price <= 0) {
        $stmtCat = $pdo->prepare("SELECT slug FROM categories WHERE id = ? LIMIT 1");
        $stmtCat->execute([$categoryId]);
        $catSlug = $stmtCat->fetchColumn();
        if ($catSlug === 'secondary-waec-neco') {
            $price = 3000.00;
        } elseif ($catSlug === 'university') {
            $price = 4000.00;
        } else {
            $price = 10000.00;
        }
    }

    // Upload thumbnail
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

    if (empty($errors)) {
        $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $title)) . '-' . rand(100, 999);

        try {
            $stmt = $pdo->prepare("
                INSERT INTO courses (teacher_id, category_id, title, slug, short_description, description, level, price, duration_minutes, thumbnail, status, featured, certificate_enabled, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 1, NOW(), NOW())
            ");
            $stmt->execute([$teacherId, $categoryId, $title, $slug, $shortDescription, $description, $level, $price, $durationMinutes, $thumbnail, $status]);
            $newCourseId = $pdo->lastInsertId();

            // Create default module
            $pdo->prepare("INSERT INTO course_sections (course_id, title, sort_order) VALUES (?, 'Module 1: Foundations', 1)")->execute([$newCourseId]);

            set_flash('success', 'Course "' . htmlspecialchars($title) . '" created successfully and added to the catalog!');
            redirect('admin/courses.php');
        } catch (Exception $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}

include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<div class="mb-4">
    <a href="<?= url('admin/courses.php') ?>" class="btn btn-outline-secondary btn-sm rounded-pill mb-3">
        <i class="bi bi-arrow-left me-1"></i> Back to Courses
    </a>
    <h2 class="fw-bold mb-1"><i class="bi bi-plus-circle-fill text-primary me-2"></i> Create New Course</h2>
    <p class="text-muted small">New courses automatically appear in the landing page, categories, and course catalog.</p>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger rounded-3 mb-4">
    <ul class="mb-0 ps-3 small">
        <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 mb-5">
    <form method="POST" enctype="multipart/form-data">
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="mb-3">
                    <label class="form-label small fw-bold">Course Title <span class="text-danger">*</span></label>
                    <input type="text" name="title" class="form-control rounded-3" required placeholder="e.g. Full Stack Web Development">
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-bold">Short Summary (Headline) <span class="text-danger">*</span></label>
                    <input type="text" name="short_description" class="form-control rounded-3" placeholder="Brief 1-2 sentence overview for course cards" required>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-bold">Comprehensive Description</label>
                    <textarea name="description" class="form-control rounded-3" rows="6" placeholder="Full course syllabus, learning outcomes, and prerequisites..."></textarea>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="mb-3">
                    <label class="form-label small fw-bold">Category <span class="text-danger">*</span></label>
                    <select name="category_id" class="form-select rounded-3" required id="catSelect" onchange="autoFillCategoryPrice(this)">
                        <option value="">Select Category</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" data-slug="<?= $cat['slug'] ?>"><?= e($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-bold">Assigned Instructor <span class="text-danger">*</span></label>
                    <select name="teacher_id" class="form-select rounded-3" required>
                        <?php foreach ($teachers as $tch): ?>
                        <option value="<?= $tch['id'] ?>"><?= e($tch['teacher_name']) ?> (<?= e($tch['email']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-bold">Official Price (₦) <span class="text-danger">*</span></label>
                    <input type="number" name="price" id="coursePrice" class="form-control rounded-3 fw-bold text-success" value="10000" min="0" step="500" required>
                    <small class="text-muted" style="font-size:0.75rem;">Technology: ₦10,000 | Secondary: ₦3,000 | Uni: ₦4,000</small>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label small fw-bold">Difficulty Level</label>
                        <select name="level" class="form-select rounded-3">
                            <option value="beginner">Beginner</option>
                            <option value="intermediate">Intermediate</option>
                            <option value="advanced">Advanced</option>
                            <option value="all_levels">All Levels</option>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-bold">Duration (Mins)</label>
                        <input type="number" name="duration_minutes" class="form-control rounded-3" value="1800" min="60" step="60">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-bold">Publication Status</label>
                    <select name="status" class="form-select rounded-3">
                        <option value="published">Published (Active in Catalog)</option>
                        <option value="draft">Draft</option>
                        <option value="archived">Archived</option>
                    </select>
                </div>

                <div class="mb-4">
                    <label class="form-label small fw-bold">Course Thumbnail</label>
                    <input type="file" name="thumbnail" class="form-control rounded-3" accept="image/*">
                </div>
            </div>
        </div>

        <div class="d-flex gap-3 pt-3 border-top">
            <button type="submit" class="btn btn-primary rounded-pill px-5 py-3 fw-bold shadow">
                <i class="bi bi-check-lg me-1"></i> Publish &amp; Add to Catalog
            </button>
            <a href="<?= url('admin/courses.php') ?>" class="btn btn-outline-secondary rounded-pill px-4 py-3">Cancel</a>
        </div>
    </form>
</div>

<script>
function autoFillCategoryPrice(selectEl) {
    const selectedOption = selectEl.options[selectEl.selectedIndex];
    const slug = selectedOption.getAttribute('data-slug');
    const priceInput = document.getElementById('coursePrice');
    if (slug === 'secondary-waec-neco') {
        priceInput.value = 3000;
    } else if (slug === 'university') {
        priceInput.value = 4000;
    } else if (slug === 'teacher-professional') {
        priceInput.value = 5000;
    } else {
        priceInput.value = 10000;
    }
}
</script>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
