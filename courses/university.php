<?php

require_once dirname(__DIR__) . '/config/main.php';
require_once BASE_PATH . '/includes/functions/courses.php';
require_once BASE_PATH . '/includes/functions/pricing.php';

$pdo = getDBConnection();
$search  = trim($_GET['search'] ?? '');
$faculty = trim($_GET['faculty'] ?? '');
$level   = trim($_GET['level'] ?? '');

$sql = "
    SELECT c.*, cat.name AS category_name, cat.slug AS category_slug,
           CONCAT(u.first_name, ' ', u.last_name) AS teacher_name,
           t.status AS teacher_status, u.status AS user_status,
           (CASE WHEN c.teacher_id IS NOT NULL AND t.id IS NOT NULL AND t.status = 'active' AND u.status = 'active' THEN 1 ELSE 0 END) AS has_active_teacher,
           (SELECT COUNT(*) FROM lessons l JOIN course_sections cs ON l.section_id = cs.id WHERE cs.course_id = c.id) AS lesson_count
    FROM courses c
    JOIN categories cat ON c.category_id = cat.id
    LEFT JOIN teachers t ON c.teacher_id = t.id
    LEFT JOIN users u ON t.user_id = u.id
    WHERE cat.slug = 'university' AND c.status = 'published'
";
$params = [];

if ($search !== '') {
    $sql .= " AND (c.title LIKE ? OR c.short_description LIKE ? OR c.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($faculty !== '') {

    $sql .= " AND (c.title LIKE ? OR c.slug LIKE ? OR c.short_description LIKE ?)";
    $params[] = "%$faculty%";
    $params[] = "%$faculty%";
    $params[] = "%$faculty%";
}

if ($level !== '') {
    $sql .= " AND c.level = ?";
    $params[] = $level;
}

$sql .= " ORDER BY has_active_teacher DESC, c.featured DESC, c.title ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

$faculties = [
    '' => 'All University Faculties',
    'Engineering' => 'Engineering',
    'Computing' => 'Computing & IT',
    'Medical' => 'Medical & Health',
    'Business' => 'Business & Management',
    'Social' => 'Social Sciences',
    'Law' => 'Law',
    'Sciences' => 'Sciences & Mathematics',
    'Humanities' => 'Arts & Humanities',
    'Education' => 'Education',
    'Agriculture' => 'Agriculture',
    'Architecture' => 'Architecture & Built Env'
];

$pageTitle = 'University Courses & Faculties — StudyMe';
include BASE_PATH . '/includes/layouts/header.php';
?>

<div class="py-5 bg-light-subtle" style="min-height: calc(100vh - 120px);">
    <div class="container py-4">

        <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb small">
                <li class="breadcrumb-item"><a href="<?= url('index.php') ?>" class="text-decoration-none">Home</a></li>
                <li class="breadcrumb-item"><a href="<?= url('courses/index.php') ?>" class="text-decoration-none">Courses</a></li>
                <li class="breadcrumb-item active" aria-current="page">University</li>
            </ol>
        </nav>

        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
            <div>
                <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 py-1 fw-bold text-uppercase small mb-2">
                    <i class="bi bi-mortarboard-fill me-1"></i> Undergraduate Curriculum (11 Faculties)
                </span>
                <h1 class="display-6 fw-bold mb-1">University Course Catalog</h1>
                <p class="text-muted mb-0">Browse university courses across Engineering, Computing, Medical, Business, Social Sciences, and more. All enrolled students are strictly tracked under active verified instructors.</p>
            </div>
            <div class="p-3 bg-white rounded-4 border shadow-sm d-flex align-items-center gap-3">
                <div>
                    <span class="text-muted small d-block" style="font-size:0.75rem;">Official Rate:</span>
                    <span class="fw-bold text-success fs-5">₦5,000 / Course</span>
                </div>
                <?php if (defined('FREE_TESTING_MODE') && FREE_TESTING_MODE): ?>
                    <span class="badge bg-success rounded-pill px-3 py-2 fw-bold">
                        <i class="bi bi-check-circle-fill me-1"></i> Free Testing: ₦0
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-4 p-3 mb-4 bg-white">
            <form action="" method="GET" class="row g-2 align-items-center">
                <div class="col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" name="search" class="form-control bg-light border-0 small" placeholder="Search university courses (e.g. Computer Science, Anatomy, Law)..." value="<?= e($search) ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <select name="faculty" class="form-select bg-light border-0 small" onchange="this.form.submit()">
                        <?php foreach ($faculties as $fKey => $fName): ?>
                            <option value="<?= e($fKey) ?>" <?= $faculty === $fKey ? 'selected' : '' ?>><?= e($fName) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="level" class="form-select bg-light border-0 small" onchange="this.form.submit()">
                        <option value="">All Levels</option>
                        <option value="100 Level" <?= $level === '100 Level' ? 'selected' : '' ?>>100 Level</option>
                        <option value="200 Level" <?= $level === '200 Level' ? 'selected' : '' ?>>200 Level</option>
                        <option value="300 Level" <?= $level === '300 Level' ? 'selected' : '' ?>>300 Level</option>
                        <option value="400 Level" <?= $level === '400 Level' ? 'selected' : '' ?>>400 Level</option>
                        <option value="500 Level" <?= $level === '500 Level' ? 'selected' : '' ?>>500 Level</option>
                    </select>
                </div>
                <div class="col-md-1 text-end">
                    <button type="submit" class="btn btn-primary rounded-pill w-100 fw-bold">Filter</button>
                </div>
            </form>
        </div>

        <div class="d-flex align-items-center gap-2 overflow-x-auto pb-3 mb-4 text-nowrap">
            <?php foreach ($faculties as $fKey => $fName):
                if ($fKey === '') continue;
                $isActive = ($faculty === $fKey);
            ?>
                <a href="<?= url('courses/university.php?' . http_build_query(array_merge($_GET, ['faculty' => $fKey]))) ?>"
                   class="btn btn-sm rounded-pill px-3 fw-semibold <?= $isActive ? 'btn-primary' : 'btn-white border text-secondary' ?>">
                    <?= e($fName) ?>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="row g-4">
            <?php if (!empty($courses)): ?>
                <?php foreach ($courses as $c):
                    $hasTeacher = !empty($c['has_active_teacher']);
                    $durationHours = max(1, (int)(($c['duration_minutes'] ?? 1800) / 60));
                    $cThumb = function_exists('get_course_thumbnail_url')
                        ? get_course_thumbnail_url($c['thumbnail'] ?? '', 'university', $c['slug'] ?? ($c['title'] ?? ''))
                        : ($c['thumbnail'] ?: 'https://images.unsplash.com/photo-1523240795612-9a054b0db644?w=600&q=80');
                ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card h-100 border-0 shadow-sm rounded-4 overflow-hidden d-flex flex-column hover-lift">
                            <div class="position-relative">
                                <a href="<?= url('courses/details.php?slug=' . urlencode($c['slug'])) ?>">
                                    <img src="<?= e($cThumb) ?>" class="card-img-top" style="height: 180px; object-fit: cover;" alt="<?= e($c['title']) ?>" onerror="this.src='https://images.unsplash.com/photo-1523240795612-9a054b0db644?w=600&q=80';">
                                </a>
                                <span class="position-absolute top-0 start-0 m-3 badge bg-primary text-white rounded-pill px-3 py-1 fw-bold small">
                                    <i class="bi bi-mortarboard-fill me-1"></i> University
                                </span>
                                <?php if ($hasTeacher): ?>
                                    <span class="position-absolute top-0 end-0 m-3 badge bg-success text-white rounded-pill px-2 py-1 fw-bold small shadow-sm">
                                        <i class="bi bi-patch-check-fill me-1"></i> Staffed
                                    </span>
                                <?php else: ?>
                                    <span class="position-absolute top-0 end-0 m-3 badge bg-warning text-dark rounded-pill px-2 py-1 fw-bold small shadow-sm">
                                        <i class="bi bi-clock me-1"></i> Awaiting Staff
                                    </span>
                                <?php endif; ?>
                            </div>

                            <div class="card-body p-4 d-flex flex-column">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="badge bg-secondary bg-opacity-10 text-secondary rounded-pill px-2 py-1 small text-capitalize">
                                        <?= e($c['level'] ?? 'Undergraduate') ?>
                                    </span>
                                    <span class="text-muted small">
                                        <i class="bi bi-clock me-1"></i> <?= $durationHours ?> hrs
                                    </span>
                                </div>

                                <h4 class="card-title fw-bold fs-5 mb-2">
                                    <a href="<?= url('courses/details.php?slug=' . urlencode($c['slug'])) ?>" class="text-decoration-none text-main hover-primary">
                                        <?= e($c['title']) ?>
                                    </a>
                                </h4>
                                <p class="card-text text-muted small mb-3 flex-grow-1 lh-base line-clamp-3">
                                    <?= e($c['short_description']) ?>
                                </p>

                                <div class="p-2 rounded-3 bg-light mb-3 small text-muted">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <?php if ($hasTeacher): ?>
                                            <a href="<?= url('teacher-profile.php?id=' . (int)$c['teacher_id']) ?>" class="fw-bold text-primary text-decoration-none hover-underline text-truncate d-inline-block" style="max-width: 170px;">
                                                <i class="bi bi-person-badge-fill me-1"></i><?= e($c['teacher_name']) ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-warning fw-semibold fst-italic text-nowrap"><i class="bi bi-person-x me-1"></i>Teacher: Awaiting Assignment</span>
                                        <?php endif; ?>
                                        <span><i class="bi bi-collection-play me-1"></i> <?= (int)$c['lesson_count'] ?: 12 ?> Modules</span>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-between align-items-center pt-2 mb-3 border-top border-light">
                                    <div>
                                        <span class="text-muted small d-block" style="font-size:0.75rem;">Official Fee:</span>
                                        <?php if (defined('FREE_TESTING_MODE') && FREE_TESTING_MODE): ?>
                                            <span class="fw-bold text-success fs-6">₦0 <span class="text-muted small text-decoration-line-through fw-normal">₦5,000</span></span>
                                        <?php else: ?>
                                            <span class="fw-bold text-success fs-6">₦5,000</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-2 py-1 small">
                                            24/7 AI Tutor
                                        </span>
                                    </div>
                                </div>

                                <?php if ($hasTeacher): ?>
                                    <a href="<?= url('courses/details.php?slug=' . urlencode($c['slug'])) ?>" class="btn btn-primary rounded-pill w-100 py-2 fw-bold mt-auto" data-feedback="click">
                                        Select Course &amp; Enroll &rarr;
                                    </a>
                                <?php else: ?>
                                    <a href="<?= url('courses/details.php?slug=' . urlencode($c['slug'])) ?>" class="btn btn-outline-secondary rounded-pill w-100 py-2 fw-semibold mt-auto" data-feedback="click">
                                        <i class="bi bi-eye me-1"></i> Explore Syllabus (Restricted)
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12 text-center py-5">
                    <i class="bi bi-mortarboard text-muted" style="font-size: 3.5rem;"></i>
                    <h4 class="fw-bold mt-3">No university courses found.</h4>
                    <p class="text-muted">Try adjusting your faculty selection or search keywords.</p>
                    <a href="<?= url('courses/university.php') ?>" class="btn btn-primary rounded-pill px-4 fw-bold">View All University Courses</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include BASE_PATH . '/includes/layouts/footer.php'; ?>
