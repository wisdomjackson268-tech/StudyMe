<?php
/**
 * StudyMe AI Platform — Course Search Results Page
 */
require_once dirname(__DIR__) . '/config/main.php';

$pdo   = getDBConnection();
$query = trim($_GET['q'] ?? '');

$courses = [];
if (!empty($query)) {
    $searchTerm = "%$query%";
    $stmt = $pdo->prepare("
        SELECT c.*, cat.name AS category_name, cat.slug AS category_slug,
               CONCAT(u.first_name, ' ', u.last_name) AS teacher_name,
               t.rating AS teacher_rating
        FROM courses c
        LEFT JOIN categories cat ON c.category_id = cat.id
        JOIN teachers t ON c.teacher_id = t.id
        JOIN users u ON t.user_id = u.id
        WHERE (c.title LIKE ? OR c.short_description LIKE ? OR cat.name LIKE ?)
          AND c.status = 'published'
        ORDER BY c.featured DESC, c.created_at DESC
    ");
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Check enrollment status if user is logged in
$enrolledCourseIds = [];
if (is_logged_in()) {
    $userId = current_user('id');
    $stmtSt = $pdo->prepare("SELECT id FROM students WHERE user_id = ? LIMIT 1");
    $stmtSt->execute([$userId]);
    $st = $stmtSt->fetch(PDO::FETCH_ASSOC);
    if ($st) {
        $stmtEn = $pdo->prepare("SELECT course_id FROM enrollments WHERE student_id = ? AND status = 'active'");
        $stmtEn->execute([$st['id']]);
        $enrolledCourseIds = $stmtEn->fetchAll(PDO::FETCH_COLUMN);
    }
}

$seo_options = [
    'title'       => (!empty($query) ? 'Search: "' . e($query) . '"' : 'Search Courses') . ' | StudyMe',
    'description' => 'Search and discover courses, technology bootcamps, university subjects, and exam prep modules on StudyMe.',
    'canonical'   => get_canonical_url('courses/index.php'),
    'noindex'     => true,
    'breadcrumbs' => [
        ['name' => 'Home', 'url' => url('index.php')],
        ['name' => 'Courses', 'url' => url('courses/index.php')],
        ['name' => 'Search', 'url' => url('courses/search.php')]
    ]
];

include BASE_PATH . '/includes/layouts/header.php';
?>

<div class="py-5 bg-light-subtle" style="min-height: calc(100vh - 120px);">
    <div class="container py-4">
        <!-- Search Header -->
        <div class="mb-4">
            <h2 class="fw-bold mb-2">Search Results for: <span class="text-primary">"<?= e($query) ?>"</span></h2>
            <p class="text-muted mb-4">Found <strong><?= count($courses) ?></strong> course<?= count($courses) !== 1 ? 's' : '' ?> matching your query.</p>

            <!-- Search bar -->
            <form action="<?= url('courses/search.php') ?>" method="GET" class="row g-2 mb-5">
                <div class="col-md-6 col-lg-5">
                    <div class="input-group input-group-lg shadow-sm rounded-pill overflow-hidden bg-white">
                        <span class="input-group-text bg-transparent border-0 ps-4"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" name="q" class="form-control border-0 bg-transparent fs-6" value="<?= e($query) ?>" placeholder="Search another course or topic...">
                        <button class="btn btn-primary px-4 fw-bold" type="submit">Search</button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Course Cards -->
        <?php if (!empty($courses)): ?>
        <div class="row g-4">
            <?php foreach ($courses as $c): ?>
            <?php $isEnrolled = in_array((int)$c['id'], $enrolledCourseIds, true); ?>
            <div class="col-md-6 col-lg-4">
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden h-100 d-flex flex-column hover-lift transition">
                    <div class="position-relative">
                        <img src="<?= e($c['thumbnail'] ?: 'https://images.unsplash.com/photo-1498050108023-c5249f4df085?w=400&q=80') ?>"
                             class="card-img-top" style="height: 180px; object-fit: cover;" alt="<?= e($c['title']) ?>">
                        <span class="badge bg-dark bg-opacity-75 text-white position-absolute top-0 start-0 m-3 rounded-pill px-3 py-1 small">
                            <?= ucfirst(e($c['level'])) ?>
                        </span>
                    </div>
                    <div class="card-body p-4 d-flex flex-column flex-grow-1">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-muted small"><i class="bi bi-person-badge me-1"></i><?= e($c['teacher_name']) ?></span>
                            <span class="text-warning small fw-bold"><i class="bi bi-star-fill me-1"></i><?= number_format((float)($c['teacher_rating'] ?? 4.5), 1) ?></span>
                        </div>
                        <h5 class="fw-bold mb-2 line-clamp-2"><?= e($c['title']) ?></h5>
                        <p class="text-muted small mb-3 line-clamp-2 flex-grow-1"><?= e($c['short_description']) ?></p>
                        
                        <div class="d-flex justify-content-between align-items-center pt-3 border-top mt-auto">
                            <div>
                                <span class="fs-5 fw-bold text-main">₦<?= number_format((float)$c['price']) ?></span>
                            </div>
                            <div class="d-flex gap-2">
                                <a href="<?= url('courses/details.php?slug=' . e($c['slug'])) ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                                    View Details
                                </a>
                                <?php if ($isEnrolled): ?>
                                <a href="<?= url('student/course.php?id=' . $c['id']) ?>" class="btn btn-sm btn-success rounded-pill px-3">
                                    Continue
                                </a>
                                <?php else: ?>
                                <form action="<?= url('courses/details.php?slug=' . e($c['slug'])) ?>" method="POST" class="d-inline">
                                    <input type="hidden" name="enroll" value="1">
                                    <button type="submit" class="btn btn-sm btn-primary rounded-pill px-3">
                                        Enroll Now
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="card border-0 shadow-sm rounded-4 p-5 text-center my-4">
            <i class="bi bi-search text-muted display-3 mb-3"></i>
            <h4 class="fw-bold">No Courses Found</h4>
            <p class="text-muted mb-4">We couldn't find any courses matching "<?= e($query) ?>". Try searching for web development, cybersecurity, python, math, or biology.</p>
            <a href="<?= url('courses/index.php') ?>" class="btn btn-primary rounded-pill px-4 mx-auto">Browse All Courses</a>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include BASE_PATH . '/includes/layouts/footer.php'; ?>
