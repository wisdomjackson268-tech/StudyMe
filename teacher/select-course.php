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

if (is_post() && isset($_POST['action']) && $_POST['action'] === 'select_course') {
    $selectedCourseId = (int)($_POST['course_id'] ?? 0);

    if ($selectedCourseId <= 0) {
        set_flash('error', 'Please select a valid course to teach.');
    } else {

        $stmtC = $pdo->prepare("SELECT * FROM courses WHERE id = ? LIMIT 1");
        $stmtC->execute([$selectedCourseId]);
        $course = $stmtC->fetch(PDO::FETCH_ASSOC);

        if (!$course) {
            set_flash('error', 'The selected course does not exist.');
        } else {

            $pdo->prepare("UPDATE courses SET teacher_id = ? WHERE id = ?")->execute([$tid, $selectedCourseId]);

            $pdo->prepare("UPDATE teachers SET assigned_course_id = ?, assigned_category_id = ? WHERE id = ?")
                ->execute([$selectedCourseId, $course['category_id'], $tid]);

            $pdo->prepare("UPDATE enrollments SET teacher_id = ? WHERE course_id = ?")->execute([$tid, $selectedCourseId]);

            log_user_activity($uid, 'course_selected', "Selected and assigned to teach course: {$course['title']}");

            set_flash('success', "Congratulations! You are now assigned as the instructor for '{$course['title']}'. You can manage lessons, quizzes, and tasks for your students.");
            redirect('teacher/dashboard.php');
        }
    }
}

$searchTrack    = trim($_GET['track'] ?? 'all');
$searchFaculty  = trim($_GET['faculty'] ?? '');
$searchLevel    = trim($_GET['level'] ?? '');
$searchKeyword  = trim($_GET['q'] ?? '');

$categories = $pdo->query("SELECT * FROM categories WHERE status = 'active' ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

$departments = $pdo->query("SELECT * FROM departments ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

$whereConditions = ["c.status = 'published'"];
$queryParams = [];

if ($searchTrack === 'technology') {
    $whereConditions[] = "cat.slug = 'technology'";
} elseif ($searchTrack === 'university') {
    $whereConditions[] = "cat.slug = 'university'";
}

if (!empty($searchFaculty)) {
    $whereConditions[] = "d.slug = ?";
    $queryParams[] = $searchFaculty;
}

if (!empty($searchLevel)) {
    $whereConditions[] = "(c.academic_level = ? OR c.level = ?)";
    $queryParams[] = $searchLevel;
    $queryParams[] = $searchLevel;
}

if (!empty($searchKeyword)) {
    $whereConditions[] = "(c.title LIKE ? OR c.short_description LIKE ? OR c.description LIKE ?)";
    $term = '%' . $searchKeyword . '%';
    $queryParams[] = $term;
    $queryParams[] = $term;
    $queryParams[] = $term;
}

$whereSql = implode(' AND ', $whereConditions);

$query = "
    SELECT c.*, cat.name AS category_name, cat.slug AS category_slug,
           d.name AS department_name, d.description AS department_description,
           t.id AS current_teacher_id,
           CONCAT(u.first_name, ' ', u.last_name) AS current_teacher_name
    FROM courses c
    LEFT JOIN categories cat ON c.category_id = cat.id
    LEFT JOIN departments d ON c.department_id = d.id
    LEFT JOIN teachers t ON c.teacher_id = t.id
    LEFT JOIN users u ON t.user_id = u.id
    WHERE $whereSql
    ORDER BY c.title ASC
";

$stmtCourses = $pdo->prepare($query);
$stmtCourses->execute($queryParams);
$courses = $stmtCourses->fetchAll(PDO::FETCH_ASSOC);

$currentAssignedId = (int)($teacher['assigned_course_id'] ?? 0);

include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
    <div>
        <p class="text-muted mb-1 fw-semibold text-uppercase small"><i class="bi bi-collection-play-fill text-primary me-1"></i> Instructor Onboarding &amp; Course Selection</p>
        <h2 class="fw-bold mb-0">Select an Existing Course to Teach</h2>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url('teacher/create-course.php') ?>" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm">
            <i class="bi bi-plus-circle-fill me-1"></i> Create New Course Instead
        </a>
    </div>
</div>

<div class="card border-0 rounded-4 p-4 mb-4 text-white shadow-sm" style="background: linear-gradient(135deg, #1e1b4b 0%, #312e81 100%);">
    <div class="row align-items-center g-3">
        <div class="col-lg-8">
            <h4 class="fw-bold text-white mb-2"><i class="bi bi-mortarboard-fill text-warning me-2"></i> Choose Your Teaching Track</h4>
            <p class="text-white-50 mb-0">
                You can select from our extensive catalog of accredited courses across <strong>Technology Bootcamps</strong> and <strong>University Faculties &amp; Academic Levels (100L - 600L)</strong>.
                Once selected, you can publish lessons, upload videos, assign tasks, and instruct students.
            </p>
        </div>
        <div class="col-lg-4 text-lg-end">
            <a href="<?= url('teacher/create-course.php') ?>" class="btn btn-warning rounded-pill px-4 py-2 fw-bold text-dark">
                <i class="bi bi-magic me-1"></i> Build Custom Course
            </a>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4 p-4 mb-4 bg-white">
    <form method="GET" action="<?= url('teacher/select-course.php') ?>" class="row g-3">
        <div class="col-md-3">
            <label class="form-label small fw-bold text-muted">Teaching Track</label>
            <select name="track" class="form-select rounded-3">
                <option value="all" <?= $searchTrack === 'all' ? 'selected' : '' ?>>All Tracks</option>
                <option value="technology" <?= $searchTrack === 'technology' ? 'selected' : '' ?>>💻 Technology &amp; Coding</option>
                <option value="university" <?= $searchTrack === 'university' ? 'selected' : '' ?>>🎓 University Undergraduate</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label small fw-bold text-muted">University Level / Year</label>
            <select name="level" class="form-select rounded-3">
                <option value="">All Academic Levels</option>
                <option value="100 Level" <?= $searchLevel === '100 Level' ? 'selected' : '' ?>>100 Level (Year 1 - Foundational)</option>
                <option value="200 Level" <?= $searchLevel === '200 Level' ? 'selected' : '' ?>>200 Level (Year 2 - Intermediate)</option>
                <option value="300 Level" <?= $searchLevel === '300 Level' ? 'selected' : '' ?>>300 Level (Year 3 - Core Discipline)</option>
                <option value="400 Level" <?= $searchLevel === '400 Level' ? 'selected' : '' ?>>400 Level (Year 4 - Advanced Capstone)</option>
                <option value="500 Level" <?= $searchLevel === '500 Level' ? 'selected' : '' ?>>500 Level (Year 5 - Engineering/Law)</option>
                <option value="600 Level" <?= $searchLevel === '600 Level' ? 'selected' : '' ?>>600 Level (Year 6 - Medicine/Surgery)</option>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label small fw-bold text-muted">Search Keyword / Topic</label>
            <div class="input-group">
                <span class="input-group-text bg-transparent border-end-0 text-muted"><i class="bi bi-search"></i></span>
                <input type="text" name="q" class="form-control border-start-0 rounded-end-3" placeholder="e.g. Python, Anatomy, Contract Law..." value="<?= e($searchKeyword) ?>">
            </div>
        </div>
        <div class="col-md-2 d-flex align-items-end gap-2">
            <button type="submit" class="btn btn-primary rounded-pill w-100 fw-bold py-2">Filter</button>
            <?php if ($searchTrack !== 'all' || !empty($searchLevel) || !empty($searchKeyword)): ?>
                <a href="<?= url('teacher/select-course.php') ?>" class="btn btn-outline-secondary rounded-pill py-2" title="Reset Filters"><i class="bi bi-x-lg"></i></a>
            <?php endif; ?>
        </div>
    </form>
</div>

<?php if (!empty($courses)): ?>
    <div class="row g-4 mb-5">
        <?php foreach ($courses as $c): ?>
            <?php
            $isAssignedToMe = ($c['teacher_id'] == $tid || $c['id'] == $currentAssignedId);
            $hasOtherTeacher = (!empty($c['teacher_id']) && $c['teacher_id'] != $tid);
            $thumbUrl = function_exists('get_course_thumbnail_url') ? get_course_thumbnail_url($c['thumbnail'] ?? '', $c['category_slug'] ?? 'technology') : ($c['thumbnail'] ?? 'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?w=500&q=80');
            ?>
            <div class="col-md-6 col-lg-4">
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden h-100 d-flex flex-column <?= $isAssignedToMe ? 'border-primary border-2 shadow' : '' ?>">
                    <div class="position-relative">
                        <img src="<?= e($thumbUrl) ?>" class="card-img-top" style="height: 160px; object-fit: cover;" alt="<?= e($c['title']) ?>" onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1516321318423-f06f85e504b3?w=500&q=80';">
                        <div class="position-absolute top-0 end-0 m-3 d-flex gap-1">
                            <span class="badge bg-dark bg-opacity-75 rounded-pill px-3 py-1 small">
                                <?= e($c['academic_level'] ?: ($c['level'] ? ucfirst($c['level']) : '100 Level')) ?>
                            </span>
                        </div>
                    </div>

                    <div class="card-body p-4 d-flex flex-column flex-grow-1">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 py-1 small fw-bold">
                                <?= e($c['category_name'] ?: 'Course') ?>
                            </span>
                            <?php if (!empty($c['academic_year'])): ?>
                                <span class="badge bg-secondary bg-opacity-10 text-secondary rounded-pill px-2 py-1 small">
                                    <i class="bi bi-calendar3 me-1"></i><?= e($c['academic_year']) ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <h5 class="fw-bold mb-2 text-dark"><?= e($c['title']) ?></h5>
                        <p class="text-muted small mb-3 flex-grow-1"><?= e(substr($c['short_description'] ?: $c['description'] ?: 'Complete comprehensive curriculum and practical modules.', 0, 110)) ?>...</p>

                        <div class="p-3 bg-light rounded-3 mb-3 border border-subtle small">
                            <div class="d-flex align-items-center justify-content-between">
                                <span class="text-muted">Current Instructor:</span>
                                <?php if ($isAssignedToMe): ?>
                                    <span class="badge bg-success rounded-pill px-2 py-1"><i class="bi bi-check-circle-fill me-1"></i> You are teaching</span>
                                <?php elseif ($hasOtherTeacher): ?>
                                    <span class="text-secondary fw-semibold"><?= e($c['current_teacher_name'] ?: 'Assigned') ?></span>
                                <?php else: ?>
                                    <span class="badge bg-warning bg-opacity-10 text-warning rounded-pill px-2 py-1"><i class="bi bi-door-open-fill me-1"></i> Available</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <form method="POST" action="<?= url('teacher/select-course.php') ?>" class="mt-auto">
                            <input type="hidden" name="action" value="select_course">
                            <input type="hidden" name="course_id" value="<?= $c['id'] ?>">

                            <?php if ($isAssignedToMe): ?>
                                <div class="d-flex gap-2">
                                    <a href="<?= url('teacher/dashboard.php') ?>" class="btn btn-outline-primary rounded-pill flex-grow-1 fw-bold">
                                        <i class="bi bi-speedometer2 me-1"></i> View in Dashboard
                                    </a>
                                    <a href="<?= url('teacher/edit-course.php?id=' . $c['id']) ?>" class="btn btn-primary rounded-pill px-3">
                                        <i class="bi bi-pencil-square"></i>
                                    </a>
                                </div>
                            <?php else: ?>
                                <button type="submit" class="btn btn-primary rounded-pill w-100 fw-bold py-2 shadow-sm" data-feedback="click" onclick="return confirm('Assign yourself to teach <?= e(addslashes($c['title'])) ?>?');">
                                    <i class="bi bi-check2-circle me-1"></i> Select &amp; Teach This Course
                                </button>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="card border-0 shadow-sm rounded-4 p-5 text-center my-4 bg-white">
        <div class="display-6 text-muted mb-3"><i class="bi bi-search"></i></div>
        <h4 class="fw-bold mb-2">No courses match your filter</h4>
        <p class="text-muted mb-4">Try clearing your search query or selecting a different academic level or faculty.</p>
        <div class="d-flex justify-content-center gap-3">
            <a href="<?= url('teacher/select-course.php') ?>" class="btn btn-outline-secondary rounded-pill px-4">View All Courses</a>
            <a href="<?= url('teacher/create-course.php') ?>" class="btn btn-primary rounded-pill px-4 fw-bold">Create New Course</a>
        </div>
    </div>
<?php endif; ?>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
