<?php
/**
 * StudyMe AI Platform — Admin System Analytics
 */
require_once dirname(__DIR__) . '/config/main.php';

secure_page(ROLE_ADMIN);

$pdo = getDBConnection();

$totalUsers    = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalStudents = (int)$pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
$totalTeachers = (int)$pdo->query("SELECT COUNT(*) FROM teachers")->fetchColumn();
$totalCourses  = (int)$pdo->query("SELECT COUNT(*) FROM courses")->fetchColumn();

include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <p class="text-muted mb-1 fw-semibold text-uppercase small"><i class="bi bi-graph-up-arrow text-primary me-1"></i> Admin Command Center</p>
        <h2 class="fw-bold mb-0">Platform System Analytics</h2>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon blue"><i class="bi bi-people-fill"></i></div>
            <div><div class="stat-value"><?= $totalUsers ?></div><p class="stat-label">Total Users</p></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon green"><i class="bi bi-mortarboard-fill"></i></div>
            <div><div class="stat-value"><?= $totalStudents ?></div><p class="stat-label">Registered Students</p></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon gold"><i class="bi bi-person-badge-fill"></i></div>
            <div><div class="stat-value"><?= $totalTeachers ?></div><p class="stat-label">Instructors</p></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon purple"><i class="bi bi-collection-play-fill"></i></div>
            <div><div class="stat-value"><?= $totalCourses ?></div><p class="stat-label">Total Courses</p></div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4 p-5 text-center">
    <i class="bi bi-cpu-fill text-primary display-4 mb-3"></i>
    <h4 class="fw-bold">Platform Metrics Operational</h4>
    <p class="text-muted small mb-0">All real-time database counters and telemetry are active.</p>
</div>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
