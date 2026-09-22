<?php

require_once dirname(__DIR__) . '/config/main.php';

secure_page(ROLE_ADMIN);

$pdo     = getDBConnection();
$search  = trim($_GET['search'] ?? '');
$status  = trim($_GET['status'] ?? '');

$whereClauses = [];
$params       = [];

if ($search !== '') {
    $whereClauses[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR c.title LIKE ?)";
    $s = "%$search%";
    $params = array_merge($params, [$s, $s, $s, $s]);
}
if ($status !== '') {
    $whereClauses[] = "e.status = ?";
    $params[] = $status;
}

$whereSQL = $whereClauses ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

$stmt = $pdo->prepare("
    SELECT e.*, c.title AS course_title, c.thumbnail, c.slug AS course_slug,
           u.id AS user_id, u.first_name, u.last_name, u.email, u.avatar,
           (SELECT COUNT(*) FROM lesson_progress lp WHERE lp.enrollment_id = e.id AND lp.completed = 1) AS completed_lessons_count
    FROM enrollments e
    JOIN courses c ON e.course_id = c.id
    JOIN students s ON e.student_id = s.id
    JOIN users u ON s.user_id = u.id
    $whereSQL
    ORDER BY e.enrolled_at DESC
");
$stmt->execute($params);
$enrollments = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalEnrollments   = count($enrollments);
$activeEnrollments  = count(array_filter($enrollments, fn($e) => $e['status'] === 'active'));
$completedEnrollments = count(array_filter($enrollments, fn($e) => (float)$e['progress'] >= 100 || $e['status'] === 'completed'));

include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
    <div>
        <p class="text-muted mb-1 fw-semibold text-uppercase small"><i class="bi bi-person-check-fill text-primary me-1"></i> Admin Command Center</p>
        <h2 class="fw-bold mb-0">Student Enrollments</h2>
    </div>
    <div class="text-muted small">Total: <strong><?= $totalEnrollments ?></strong> enrollments</div>
</div>

<div class="row g-3 mb-4">
    <div class="col-sm-4">
        <div class="stat-card">
            <div class="stat-icon blue"><i class="bi bi-mortarboard-fill"></i></div>
            <div><div class="stat-value"><?= $totalEnrollments ?></div><p class="stat-label">Total Enrollments</p></div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="stat-card">
            <div class="stat-icon green"><i class="bi bi-play-circle-fill"></i></div>
            <div><div class="stat-value"><?= $activeEnrollments ?></div><p class="stat-label">Active Learners</p></div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="stat-card">
            <div class="stat-icon gold"><i class="bi bi-award-fill"></i></div>
            <div><div class="stat-value"><?= $completedEnrollments ?></div><p class="stat-label">Completed Courses</p></div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4 p-3 mb-4">
    <form method="GET" class="row g-2 align-items-end">
        <div class="col-md-6">
            <input type="text" name="search" class="form-control rounded-3" placeholder="Search student name, email, or course title..." value="<?= e($search) ?>">
        </div>
        <div class="col-md-3">
            <select name="status" class="form-select rounded-3">
                <option value="">All Statuses</option>
                <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Completed</option>
                <option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
            </select>
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-primary rounded-pill w-100"><i class="bi bi-search me-1"></i> Filter</button>
        </div>
        <div class="col-md-1">
            <a href="<?= url('admin/enrollments.php') ?>" class="btn btn-outline-secondary rounded-pill w-100"><i class="bi bi-x"></i></a>
        </div>
    </form>
</div>

<div class="card border-0 shadow-sm rounded-4 overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
            <thead class="bg-light" style="font-size:0.78rem; text-transform:uppercase; letter-spacing:0.04em;">
                <tr>
                    <th class="ps-4">Student</th>
                    <th>Course</th>
                    <th>Progress</th>
                    <th>Status</th>
                    <th>Enrolled Date</th>
                    <th class="text-end pe-4">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($enrollments as $en): ?>
                <tr>
                    <td class="ps-4">
                        <div class="d-flex align-items-center gap-3">
                            <div class="rounded-circle bg-primary bg-opacity-10 text-primary fw-bold d-flex align-items-center justify-content-center" style="width:36px;height:36px;">
                                <?= strtoupper(substr($en['first_name'], 0, 1)) ?>
                            </div>
                            <div>
                                <div class="fw-bold text-main"><?= e($en['first_name'] . ' ' . $en['last_name']) ?></div>
                                <div class="text-muted" style="font-size:0.75rem;"><?= e($en['email']) ?></div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="fw-semibold text-main"><?= e($en['course_title']) ?></div>
                    </td>
                    <td style="min-width:140px;">
                        <div class="d-flex align-items-center gap-2">
                            <div class="progress flex-grow-1" style="height:6px;">
                                <div class="progress-bar bg-success" style="width:<?= (float)$en['progress'] ?>%;"></div>
                            </div>
                            <span class="text-muted small fw-bold"><?= (int)$en['progress'] ?>%</span>
                        </div>
                    </td>
                    <td>
                        <span class="badge rounded-pill <?= $en['status'] === 'active' ? 'bg-success' : 'bg-secondary' ?>">
                            <?= ucfirst(e($en['status'])) ?>
                        </span>
                    </td>
                    <td class="text-muted"><?= date('d M Y', strtotime($en['enrolled_at'])) ?></td>
                    <td class="text-end pe-4">
                        <a href="<?= url('admin/users.php?edit_id=' . $en['user_id']) ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                            <i class="bi bi-person me-1"></i> User Profile
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($enrollments)): ?>
                <tr>
                    <td colspan="6" class="text-center py-5 text-muted">No student enrollments found.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
