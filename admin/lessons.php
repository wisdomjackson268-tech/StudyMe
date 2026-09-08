<?php
/**
 * StudyMe AI Platform — Admin Lessons Management
 */
require_once dirname(__DIR__) . '/config/main.php';

secure_page(ROLE_ADMIN);

$pdo     = getDBConnection();
$search  = trim($_GET['search'] ?? '');
$courseId = (int)($_GET['course_id'] ?? 0);

$whereClauses = [];
$params       = [];

if ($search !== '') {
    $whereClauses[] = "(l.title LIKE ? OR c.title LIKE ?)";
    $s = "%$search%";
    $params = array_merge($params, [$s, $s]);
}
if ($courseId > 0) {
    $whereClauses[] = "cs.course_id = ?";
    $params[] = $courseId;
}

$whereSQL = $whereClauses ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

$stmt = $pdo->prepare("
    SELECT l.*, cs.title AS section_title, c.id AS course_id, c.title AS course_title,
           CONCAT(u.first_name, ' ', u.last_name) AS teacher_name
    FROM lessons l
    JOIN course_sections cs ON l.section_id = cs.id
    JOIN courses c ON cs.course_id = c.id
    JOIN teachers t ON c.teacher_id = t.id
    JOIN users u ON t.user_id = u.id
    $whereSQL
    ORDER BY c.title ASC, cs.sort_order ASC, l.sort_order ASC
");
$stmt->execute($params);
$lessons = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Courses for filter
$courses = $pdo->query("SELECT id, title FROM courses ORDER BY title ASC")->fetchAll(PDO::FETCH_ASSOC);

$totalLessons   = count($lessons);
$totalPublished = count(array_filter($lessons, fn($l) => $l['status'] === 'published'));
$totalFree      = count(array_filter($lessons, fn($l) => (bool)$l['is_free']));

include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
    <div>
        <p class="text-muted mb-1 fw-semibold text-uppercase small"><i class="bi bi-play-btn-fill text-primary me-1"></i> Admin Command Center</p>
        <h2 class="fw-bold mb-0">Lessons Directory</h2>
    </div>
    <div class="text-muted small">Total: <strong><?= $totalLessons ?></strong> lessons</div>
</div>

<!-- Stats -->
<div class="row g-3 mb-4">
    <div class="col-sm-4">
        <div class="stat-card">
            <div class="stat-icon blue"><i class="bi bi-collection-play-fill"></i></div>
            <div><div class="stat-value"><?= $totalLessons ?></div><p class="stat-label">Total Lessons</p></div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="stat-card">
            <div class="stat-icon green"><i class="bi bi-check-circle-fill"></i></div>
            <div><div class="stat-value"><?= $totalPublished ?></div><p class="stat-label">Published</p></div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="stat-card">
            <div class="stat-icon gold"><i class="bi bi-unlock-fill"></i></div>
            <div><div class="stat-value"><?= $totalFree ?></div><p class="stat-label">Free Previews</p></div>
        </div>
    </div>
</div>

<!-- Search & Filter -->
<div class="card border-0 shadow-sm rounded-4 p-3 mb-4">
    <form method="GET" class="row g-2 align-items-end">
        <div class="col-md-5">
            <input type="text" name="search" class="form-control rounded-3" placeholder="Search lesson or course title..." value="<?= e($search) ?>">
        </div>
        <div class="col-md-4">
            <select name="course_id" class="form-select rounded-3">
                <option value="">All Courses</option>
                <?php foreach ($courses as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $courseId === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['title']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-primary rounded-pill w-100"><i class="bi bi-search me-1"></i> Filter</button>
        </div>
        <div class="col-md-1">
            <a href="<?= url('admin/lessons.php') ?>" class="btn btn-outline-secondary rounded-pill w-100"><i class="bi bi-x"></i></a>
        </div>
    </form>
</div>

<!-- Lessons Table -->
<div class="card border-0 shadow-sm rounded-4 overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
            <thead class="bg-light" style="font-size:0.78rem; text-transform:uppercase; letter-spacing:0.04em;">
                <tr>
                    <th class="ps-4">Lesson Title</th>
                    <th>Course</th>
                    <th>Section</th>
                    <th>Teacher</th>
                    <th>Duration</th>
                    <th>Status</th>
                    <th class="text-end pe-4">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($lessons as $l): ?>
                <tr>
                    <td class="ps-4">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-play-circle-fill text-primary fs-5"></i>
                            <div>
                                <div class="fw-bold text-main"><?= e($l['title']) ?></div>
                                <?php if ($l['is_free']): ?>
                                <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-2" style="font-size:0.7rem;">Free Preview</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td>
                        <a href="<?= url('admin/course-details.php?id=' . $l['course_id']) ?>" class="text-decoration-none fw-semibold text-main">
                            <?= e(substr($l['course_title'], 0, 30)) ?><?= strlen($l['course_title']) > 30 ? '...' : '' ?>
                        </a>
                    </td>
                    <td><span class="badge bg-light text-muted border"><?= e($l['section_title']) ?></span></td>
                    <td><?= e($l['teacher_name']) ?></td>
                    <td class="text-muted"><?= max(1, (int)($l['video_duration'] / 60)) ?> mins</td>
                    <td>
                        <span class="badge rounded-pill <?= $l['status'] === 'published' ? 'bg-success' : 'bg-secondary' ?>">
                            <?= ucfirst(e($l['status'])) ?>
                        </span>
                    </td>
                    <td class="text-end pe-4">
                        <a href="<?= url('courses/details.php?slug=' . urlencode(strtolower(str_replace(' ', '-', $l['course_title'])))) ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3" target="_blank">
                            <i class="bi bi-eye me-1"></i> Preview
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($lessons)): ?>
                <tr>
                    <td colspan="7" class="text-center py-5 text-muted">No lessons found matching your query.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
