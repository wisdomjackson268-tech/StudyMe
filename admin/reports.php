<?php
/**
 * StudyMe AI Platform — Admin Reports & Platform Analytics
 */
require_once dirname(__DIR__) . '/config/main.php';

secure_page(ROLE_ADMIN);

$pdo = getDBConnection();

// Comprehensive Platform Statistics
$totalUsers       = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalStudents    = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn();
$totalTeachers    = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'teacher'")->fetchColumn();
$totalAdmins      = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
$activeUsers      = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE status = 'active'")->fetchColumn();
$inactiveUsers    = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE status IN ('inactive', 'suspended')")->fetchColumn();

$totalCourses     = (int)$pdo->query("SELECT COUNT(*) FROM courses")->fetchColumn();
$publishedCourses = (int)$pdo->query("SELECT COUNT(*) FROM courses WHERE status = 'published'")->fetchColumn();
$draftCourses     = (int)$pdo->query("SELECT COUNT(*) FROM courses WHERE status = 'draft'")->fetchColumn();
$archivedCourses  = (int)$pdo->query("SELECT COUNT(*) FROM courses WHERE status = 'archived'")->fetchColumn();

$totalLessons     = (int)$pdo->query("SELECT COUNT(*) FROM lessons")->fetchColumn();
$totalQuizzes     = (int)$pdo->query("SELECT COUNT(*) FROM quizzes")->fetchColumn();
$totalQuizAttempts= (int)$pdo->query("SELECT COUNT(*) FROM quiz_attempts")->fetchColumn();
$passedAttempts   = (int)$pdo->query("SELECT COUNT(*) FROM quiz_attempts WHERE passed = 1")->fetchColumn();
$avgQuizScore     = (float)$pdo->query("SELECT COALESCE(AVG(percentage), 0) FROM quiz_attempts")->fetchColumn();

$totalAnnouncements=(int)$pdo->query("SELECT COUNT(*) FROM announcements")->fetchColumn();
$totalRevenue     = (float)$pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'successful'")->fetchColumn();
$totalTransactions= (int)$pdo->query("SELECT COUNT(*) FROM payments WHERE status = 'successful'")->fetchColumn();

// Category distribution
$categoryStats = $pdo->query("
    SELECT cat.name AS category_name, COUNT(c.id) AS course_count,
           (SELECT COUNT(e.id) FROM enrollments e JOIN courses c2 ON e.course_id = c2.id WHERE c2.category_id = cat.id) AS student_count
    FROM categories cat
    LEFT JOIN courses c ON cat.id = c.category_id
    GROUP BY cat.id, cat.name
    ORDER BY student_count DESC
")->fetchAll(PDO::FETCH_ASSOC);

include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
    <div>
        <p class="text-muted mb-1 fw-semibold text-uppercase small"><i class="bi bi-graph-up-arrow text-primary me-1"></i> Admin Command Center</p>
        <h2 class="fw-bold mb-0">System Analytics & Reports</h2>
    </div>
</div>

<!-- Primary Financial & User Metrics -->
<div class="row g-4 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon green"><i class="bi bi-cash-stack"></i></div>
            <div>
                <div class="stat-value">₦<?= number_format($totalRevenue) ?></div>
                <p class="stat-label">Verified Revenue (<?= $totalTransactions ?> txns)</p>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon blue"><i class="bi bi-people-fill"></i></div>
            <div>
                <div class="stat-value"><?= number_format($totalUsers) ?></div>
                <p class="stat-label">Total Users (<?= $activeUsers ?> active)</p>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon gold"><i class="bi bi-collection-play-fill"></i></div>
            <div>
                <div class="stat-value"><?= number_format($totalCourses) ?></div>
                <p class="stat-label">Courses (<?= $publishedCourses ?> published)</p>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon purple"><i class="bi bi-patch-question-fill"></i></div>
            <div>
                <div class="stat-value"><?= number_format($totalQuizAttempts) ?></div>
                <p class="stat-label">Quiz Attempts (<?= number_format($avgQuizScore, 1) ?>% avg)</p>
            </div>
        </div>
    </div>
</div>

<!-- Detailed Metrics Grids -->
<div class="row g-4 mb-4">
    <!-- User Breakdown -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm rounded-4 p-4 h-100">
            <h5 class="fw-bold mb-3"><i class="bi bi-people text-primary me-2"></i>User Demographics & Status</h5>
            <ul class="list-group list-group-flush small">
                <li class="list-group-item d-flex justify-content-between px-0 py-2 border-0">
                    <span class="text-muted">Students Registered</span>
                    <span class="fw-bold text-main"><?= number_format($totalStudents) ?></span>
                </li>
                <li class="list-group-item d-flex justify-content-between px-0 py-2 border-0">
                    <span class="text-muted">Instructors / Teachers</span>
                    <span class="fw-bold text-main"><?= number_format($totalTeachers) ?></span>
                </li>
                <li class="list-group-item d-flex justify-content-between px-0 py-2 border-0">
                    <span class="text-muted">Platform Administrators</span>
                    <span class="fw-bold text-main"><?= number_format($totalAdmins) ?></span>
                </li>
                <li class="list-group-item d-flex justify-content-between px-0 py-2 border-0">
                    <span class="text-muted">Active Accounts</span>
                    <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3"><?= number_format($activeUsers) ?></span>
                </li>
                <li class="list-group-item d-flex justify-content-between px-0 py-2 border-0">
                    <span class="text-muted">Inactive / Deactivated Accounts</span>
                    <span class="badge bg-secondary bg-opacity-10 text-muted rounded-pill px-3"><?= number_format($inactiveUsers) ?></span>
                </li>
            </ul>
        </div>
    </div>

    <!-- Course Content Stats -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm rounded-4 p-4 h-100">
            <h5 class="fw-bold mb-3"><i class="bi bi-journal-bookmark text-success me-2"></i>Curriculum & Materials Breakdown</h5>
            <ul class="list-group list-group-flush small">
                <li class="list-group-item d-flex justify-content-between px-0 py-2 border-0">
                    <span class="text-muted">Total Published Courses</span>
                    <span class="fw-bold text-main"><?= number_format($publishedCourses) ?></span>
                </li>
                <li class="list-group-item d-flex justify-content-between px-0 py-2 border-0">
                    <span class="text-muted">Draft Courses</span>
                    <span class="fw-bold text-warning"><?= number_format($draftCourses) ?></span>
                </li>
                <li class="list-group-item d-flex justify-content-between px-0 py-2 border-0">
                    <span class="text-muted">Archived Courses</span>
                    <span class="fw-bold text-secondary"><?= number_format($archivedCourses) ?></span>
                </li>
                <li class="list-group-item d-flex justify-content-between px-0 py-2 border-0">
                    <span class="text-muted">Total Lessons Created</span>
                    <span class="fw-bold text-main"><?= number_format($totalLessons) ?></span>
                </li>
                <li class="list-group-item d-flex justify-content-between px-0 py-2 border-0">
                    <span class="text-muted">Total Interactive Quizzes</span>
                    <span class="fw-bold text-main"><?= number_format($totalQuizzes) ?></span>
                </li>
                <li class="list-group-item d-flex justify-content-between px-0 py-2 border-0">
                    <span class="text-muted">Broadcast Announcements</span>
                    <span class="fw-bold text-main"><?= number_format($totalAnnouncements) ?></span>
                </li>
            </ul>
        </div>
    </div>
</div>

<!-- Category Performance Table -->
<div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
    <div class="card-header bg-transparent border-0 p-4 pb-0">
        <h5 class="fw-bold mb-0"><i class="bi bi-tag-fill text-warning me-2"></i>Category Performance</h5>
    </div>
    <div class="table-responsive p-4 pt-2">
        <table class="table table-hover align-middle mb-0 small">
            <thead class="bg-light">
                <tr>
                    <th>Category</th>
                    <th>Available Courses</th>
                    <th>Enrolled Students</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($categoryStats as $c): ?>
                <tr>
                    <td class="fw-bold text-main"><?= e($c['category_name'] ?: 'Unassigned') ?></td>
                    <td><span class="badge bg-light text-muted border"><?= (int)$c['course_count'] ?> courses</span></td>
                    <td><span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3"><?= (int)$c['student_count'] ?> students</span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
