<?php
/**
 * StudyMe AI Platform — Teacher Performance Analytics
 */
require_once dirname(__DIR__) . '/config/main.php';

secure_page(ROLE_TEACHER);
$user = current_user();
$pdo  = getDBConnection();
$uid  = $user['id'];

// Resolve teacher ID
$stmt = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ? LIMIT 1");
$stmt->execute([$uid]);
$teacher = $stmt->fetch(PDO::FETCH_ASSOC);
$tid = $teacher ? (int)$teacher['id'] : 0;

$totalStudents = 0;
$totalCourses  = 0;
$totalQuizzes  = 0;
$totalTasks    = 0;

if ($tid) {
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT e.student_id) FROM enrollments e JOIN courses c ON e.course_id = c.id WHERE c.teacher_id = ?");
    $stmt->execute([$tid]);
    $totalStudents = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM courses WHERE teacher_id = ?");
    $stmt->execute([$tid]);
    $totalCourses = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM quizzes q JOIN courses c ON q.course_id = c.id WHERE c.teacher_id = ?");
    $stmt->execute([$tid]);
    $totalQuizzes = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM assignments a JOIN courses c ON a.course_id = c.id WHERE c.teacher_id = ?");
    $stmt->execute([$tid]);
    $totalTasks = (int)$stmt->fetchColumn();
}

include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <p class="text-muted mb-1 fw-semibold text-uppercase small"><i class="bi bi-graph-up-arrow text-primary me-1"></i> Instructor Suite</p>
        <h2 class="fw-bold mb-0">Performance Analytics</h2>
    </div>
</div>

<div class="row g-4 mb-5">
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon blue"><i class="bi bi-journal-code"></i></div>
            <div><div class="stat-value"><?= $totalCourses ?></div><p class="stat-label">Active Courses</p></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon green"><i class="bi bi-people-fill"></i></div>
            <div><div class="stat-value"><?= $totalStudents ?></div><p class="stat-label">Total Students</p></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon gold"><i class="bi bi-patch-question-fill"></i></div>
            <div><div class="stat-value"><?= $totalQuizzes ?></div><p class="stat-label">Quizzes Created</p></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon purple"><i class="bi bi-list-task"></i></div>
            <div><div class="stat-value"><?= $totalTasks ?></div><p class="stat-label">Tasks Published</p></div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4 p-4 text-center">
    <i class="bi bi-bar-chart-line-fill text-primary display-4 mb-3"></i>
    <h4 class="fw-bold">Analytics Engine Synchronized</h4>
    <p class="text-muted max-w-md mx-auto mb-0">Student engagement and progress data are updating in real-time across your courses.</p>
</div>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
