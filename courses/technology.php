<?php
/**
 * StudyMe AI Platform — Technology Courses Category
 * Displays modern technology bootcamps & skills courses with official ₦10,000 pricing and teacher profile links.
 */
require_once dirname(__DIR__) . '/config/main.php';
require_once BASE_PATH . '/includes/functions/courses.php';
require_once BASE_PATH . '/includes/functions/pricing.php';

$pdo = getDBConnection();
$search = trim($_GET['search'] ?? '');
$level  = trim($_GET['level'] ?? '');

$sql = "
    SELECT c.*, cat.name AS category_name, cat.slug AS category_slug,
           CONCAT(u.first_name, ' ', u.last_name) AS teacher_name,
           (SELECT COUNT(*) FROM lessons l JOIN course_sections cs ON l.section_id = cs.id WHERE cs.course_id = c.id) AS lesson_count
    FROM courses c
    JOIN categories cat ON c.category_id = cat.id
    LEFT JOIN teachers t ON c.teacher_id = t.id
    LEFT JOIN users u ON t.user_id = u.id
    WHERE cat.slug = 'technology' AND c.status = 'published'
";
$params = [];

if ($search !== '') {
    $sql .= " AND (c.title LIKE ? OR c.short_description LIKE ? OR c.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($level !== '') {
    $sql .= " AND c.level = ?";
    $params[] = $level;
}

$sql .= " ORDER BY c.featured DESC, c.title ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Technology & Modern Skills Courses — StudyMe';
include BASE_PATH . '/includes/layouts/header.php';
?>

<div class="py-5 bg-light-subtle" style="min-height: calc(100vh - 120px);">
    <div class="container py-4">
        
        <!-- Breadcrumb & Header -->
        <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb small">
                <li class="breadcrumb-item"><a href="<?= url('index.php') ?>" class="text-decoration-none">Home</a></li>
                <li class="breadcrumb-item"><a href="<?= url('courses/index.php') ?>" class="text-decoration-none">Courses</a></li>
                <li class="breadcrumb-item active" aria-current="page">Technology</li>
            </ol>
        </nav>

        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
            <div>
                <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 py-1 fw-bold text-uppercase small mb-2">
                    <i class="bi bi-code-slash me-1"></i> Industry Tech Bootcamps
                </span>
                <h1 class="display-6 fw-bold mb-1">Technology &amp; Modern Skills</h1>
                <p class="text-muted mb-0">Learn full-stack development, AI engineering, cybersecurity, cloud architecture, and data science.</p>
            </div>
            <div class="p-3 bg-white rounded-4 border shadow-sm d-flex align-items-center gap-3">
                <div>
                    <span class="text-muted small d-block" style="font-size:0.75rem;">Official Rate:</span>
                    <span class="fw-bold text-success fs-5">₦10,000 / Course</span>
                </div>
                <?php if (defined('FREE_TESTING_MODE') && FREE_TESTING_MODE): ?>
                    <span class="badge bg-success rounded-pill px-3 py-2 fw-bold">
                        <i class="bi bi-check-circle-fill me-1"></i> Free Testing: ₦0
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Search & Filter Controls -->
        <div class="card border-0 shadow-sm rounded-4 p-3 mb-4">
            <form method="GET" class="row g-2 align-items-center">
                <div class="col-md-7">
                    <div class="input-group">
                        <span class="input-group-text bg-transparent border-end-0 text-muted"><i class="bi bi-search"></i></span>
                        <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Search tech course (e.g. Web Development, Python, Cloud)…" value="<?= e($search) ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <select name="level" class="form-select">
                        <option value="">All Experience Levels</option>
                        <option value="beginner" <?= $level==='beginner'?'selected':'' ?>>Beginner Friendly</option>
                        <option value="intermediate" <?= $level==='intermediate'?'selected':'' ?>>Intermediate</option>
                        <option value="advanced" <?= $level==='advanced'?'selected':'' ?>>Advanced / Specialist</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary rounded-pill w-100 fw-bold">Filter</button>
                    <?php if (!empty($search) || !empty($level)): ?>
                        <a href="<?= url('courses/technology.php') ?>" class="btn btn-outline-secondary rounded-pill" title="Reset Filters"><i class="bi bi-x-lg"></i></a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Courses Grid -->
        <div class="row g-4" id="techCoursesGrid">
            <?php if (!empty($courses)): ?>
                <?php foreach ($courses as $c): ?>
                    <?php 
                    $officialPrice = 10000.00;
                    $durationHours = max(1, (int)(($c['duration_minutes'] ?? 3600) / 60));
                    $techThumb = function_exists('get_course_thumbnail_url') ? get_course_thumbnail_url($c['thumbnail'] ?? '', 'technology') : ($c['thumbnail'] ?? 'https://images.unsplash.com/photo-1498050108023-c5249f4df085?w=600&q=80');
                    ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card h-100 border-0 shadow-sm rounded-4 overflow-hidden d-flex flex-column hover-lift transition">
                            
                            <!-- Thumbnail with Category Badge -->
                            <div class="position-relative">
                                <a href="<?= url('courses/details.php?slug=' . urlencode($c['slug'])) ?>">
                                    <img src="<?= e($techThumb) ?>"
                                         class="card-img-top w-100" style="height: 180px; object-fit: cover;" alt="<?= e($c['title']) ?>"
                                         onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1498050108023-c5249f4df085?w=600&q=80';">
                                </a>
                                <span class="position-absolute top-0 start-0 m-3 badge bg-primary text-white rounded-pill px-3 py-1 fw-bold small">
                                    <i class="bi bi-code-slash me-1"></i> Technology
                                </span>
                                <?php if (defined('FREE_TESTING_MODE') && FREE_TESTING_MODE): ?>
                                    <span class="position-absolute top-0 end-0 m-3 badge bg-success text-white rounded-pill px-3 py-1 fw-bold small">
                                        Free Testing
                                    </span>
                                <?php endif; ?>
                            </div>

                            <div class="card-body p-4 d-flex flex-column">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="badge bg-secondary bg-opacity-10 text-secondary rounded-pill px-2 py-1 small text-capitalize">
                                        <?= e($c['level'] ?? 'Beginner') ?>
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
                                        <?php if (!empty($c['teacher_id']) && !empty($c['teacher_name'])): ?>
                                            <a href="<?= url('teacher-profile.php?id=' . (int)$c['teacher_id']) ?>" class="fw-bold text-primary text-decoration-none hover-underline text-truncate d-inline-block" style="max-width: 170px;">
                                                <i class="bi bi-person-badge-fill me-1"></i><?= e($c['teacher_name']) ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted fst-italic text-nowrap"><i class="bi bi-person-x me-1"></i>Teacher: Currently unavailable</span>
                                        <?php endif; ?>
                                        <span><i class="bi bi-collection-play me-1"></i> <?= (int)$c['lesson_count'] ?: 18 ?> Lessons</span>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-between align-items-center pt-2 mb-3 border-top border-light">
                                    <div>
                                        <span class="text-muted small d-block" style="font-size:0.75rem;">Official Fee:</span>
                                        <?php if (defined('FREE_TESTING_MODE') && FREE_TESTING_MODE): ?>
                                            <span class="fw-bold text-success fs-6">₦0 <span class="text-muted small text-decoration-line-through fw-normal">₦10,000</span></span>
                                        <?php else: ?>
                                            <span class="fw-bold text-success fs-6">₦10,000</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-2 py-1 small">
                                            24/7 AI Tutor
                                        </span>
                                    </div>
                                </div>

                                <a href="<?= url('courses/details.php?slug=' . urlencode($c['slug'])) ?>" class="btn btn-outline-primary rounded-pill w-100 py-2 fw-bold mt-auto" data-feedback="click">
                                    Select Course &amp; Enroll &rarr;
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12 text-center py-5">
                    <i class="bi bi-code-slash text-muted" style="font-size: 3.5rem;"></i>
                    <h4 class="fw-bold mt-3">No technology courses found.</h4>
                    <p class="text-muted">Try adjusting your search criteria.</p>
                    <a href="<?= url('courses/technology.php') ?>" class="btn btn-primary rounded-pill px-4 fw-bold">View All Technology Courses</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include BASE_PATH . '/includes/layouts/footer.php'; ?>
