<?php

require_once dirname(__DIR__) . '/config/main.php';
require_once BASE_PATH . '/includes/functions/users.php';

secure_page(ROLE_TEACHER);
if (current_user_role() !== ROLE_ADMIN) {
    require_active_subscription();
}

$pdo = getDBConnection();
$teacherUser = current_user();
$studentUserId = (int)($_GET['user_id'] ?? 0);

if ($studentUserId < 1) {
    set_flash('error', 'Student profile not found.');
    redirect('teacher/students.php');
}

$teacherStmt = $pdo->prepare("SELECT id, assigned_course_id FROM teachers WHERE user_id = ? LIMIT 1");
$teacherStmt->execute([(int)$teacherUser['id']]);
$teacher = $teacherStmt->fetch(PDO::FETCH_ASSOC);

if (!$teacher) {
    set_flash('error', 'Instructor profile not found.');
    redirect('teacher/students.php');
}

$accessSql = "
    SELECT DISTINCT s.id AS student_id, s.student_number, s.bio, s.city, s.state, s.country,
           s.created_at AS student_created_at, u.id AS user_id, u.first_name, u.last_name,
           u.email, u.phone, u.avatar, u.created_at
    FROM students s
    JOIN users u ON u.id = s.user_id
    JOIN enrollments e ON e.student_id = s.id
    JOIN courses c ON c.id = e.course_id
    WHERE s.user_id = ?
      AND e.status = 'active'
      AND (e.teacher_id = ? OR c.teacher_id = ? OR c.id = ?)
    LIMIT 1
";
$studentStmt = $pdo->prepare($accessSql);
$studentStmt->execute([
    $studentUserId,
    (int)$teacher['id'],
    (int)$teacher['id'],
    (int)($teacher['assigned_course_id'] ?? 0),
]);
$student = $studentStmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    set_flash('error', 'You can only view profiles of students enrolled in your courses.');
    redirect('teacher/students.php');
}

$coursesStmt = $pdo->prepare(" 
    SELECT DISTINCT c.id, c.title, c.academic_level, e.enrolled_at, e.progress
    FROM enrollments e
    JOIN courses c ON c.id = e.course_id
    WHERE e.student_id = ?
      AND e.status = 'active'
      AND (e.teacher_id = ? OR c.teacher_id = ? OR c.id = ?)
    ORDER BY e.enrolled_at DESC
");
$coursesStmt->execute([
    (int)$student['student_id'],
    (int)$teacher['id'],
    (int)$teacher['id'],
    (int)($teacher['assigned_course_id'] ?? 0),
]);
$courses = $coursesStmt->fetchAll(PDO::FETCH_ASSOC);

$completedStmt = $pdo->prepare("SELECT COUNT(*) FROM lesson_progress lp JOIN enrollments e ON e.id = lp.enrollment_id WHERE e.student_id = ? AND lp.completed = 1");
$completedStmt->execute([(int)$student['student_id']]);
$completedLessons = (int)$completedStmt->fetchColumn();

$fullName = trim($student['first_name'] . ' ' . $student['last_name']);
$avatarUrl = function_exists('get_avatar_url')
    ? get_avatar_url($student['avatar'] ?? null, $fullName)
    : ($student['avatar'] ?? '');

include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
    <div>
        <a href="<?= url('teacher/students.php') ?>" class="text-decoration-none small text-muted"><i class="bi bi-arrow-left me-1"></i>Back to enrolled students</a>
        <h2 class="fw-bold mb-0 mt-2">Student Profile</h2>
    </div>
    <span class="badge bg-success-subtle text-success border border-success border-opacity-25 rounded-pill px-3 py-2"><i class="bi bi-shield-check me-1"></i>Enrolled student</span>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm rounded-4 p-4 text-center h-100">
            <img src="<?= e($avatarUrl) ?>" alt="<?= e($fullName) ?>" class="rounded-circle border border-4 border-primary-subtle shadow-sm mx-auto mb-3" style="width: 132px; height: 132px; object-fit: cover;" onerror="this.onerror=null; this.src='https://ui-avatars.com/api/?name=<?= urlencode($fullName) ?>&background=1e40af&color=ffffff&bold=true';">
            <h3 class="h4 fw-bold mb-1"><?= e($fullName) ?></h3>
            <p class="text-muted small mb-3"><i class="bi bi-person-badge me-1"></i><?= e($student['student_number'] ?: 'Student') ?></p>
            <div class="d-flex justify-content-center gap-2 flex-wrap mb-4">
                <?php if (!empty($student['city']) || !empty($student['country'])): ?>
                    <span class="badge bg-light text-secondary border rounded-pill px-3 py-2"><i class="bi bi-geo-alt me-1"></i><?= e(trim(($student['city'] ?? '') . (!empty($student['city']) && !empty($student['country']) ? ', ' : '') . ($student['country'] ?? ''))) ?></span>
                <?php endif; ?>
                <span class="badge bg-primary-subtle text-primary border border-primary border-opacity-25 rounded-pill px-3 py-2"><i class="bi bi-book me-1"></i><?= count($courses) ?> course<?= count($courses) === 1 ? '' : 's' ?></span>
            </div>
            <div class="text-start border-top pt-3">
                <div class="small text-muted mb-2"><i class="bi bi-envelope me-2 text-primary"></i><?= e($student['email']) ?></div>
                <?php if (!empty($student['phone'])): ?><div class="small text-muted"><i class="bi bi-telephone me-2 text-primary"></i><?= e($student['phone']) ?></div><?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card border-0 shadow-sm rounded-4 p-4 mb-4">
            <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
                <h4 class="fw-bold mb-0"><i class="bi bi-person-lines-fill text-primary me-2"></i>About the student</h4>
                <span class="text-muted small"><i class="bi bi-calendar3 me-1"></i>Joined <?= date('M Y', strtotime($student['created_at'])) ?></span>
            </div>
            <?php if (!empty($student['bio'])): ?>
                <p class="text-secondary lh-lg mb-0" style="white-space: pre-line;"><?= e($student['bio']) ?></p>
            <?php else: ?>
                <div class="p-3 bg-body-tertiary rounded-3 text-muted small"><i class="bi bi-info-circle me-2"></i>This student has not added a bio yet.</div>
            <?php endif; ?>
        </div>

        <div class="card border-0 shadow-sm rounded-4 p-4 mb-4">
            <div class="row g-3 text-center">
                <div class="col-sm-6"><div class="p-3 rounded-3 bg-primary-subtle"><div class="fs-3 fw-bold text-primary"><?= $completedLessons ?></div><div class="small text-muted">Completed lessons</div></div></div>
                <div class="col-sm-6"><div class="p-3 rounded-3 bg-success-subtle"><div class="fs-3 fw-bold text-success"><?= count($courses) ?></div><div class="small text-muted">Active courses</div></div></div>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-4 p-4">
            <h4 class="fw-bold mb-3"><i class="bi bi-journal-bookmark-fill text-success me-2"></i>Enrolled with you</h4>
            <?php foreach ($courses as $course): ?>
                <div class="d-flex align-items-center justify-content-between gap-3 py-3 border-bottom">
                    <div><div class="fw-semibold text-main"><?= e($course['title']) ?></div><div class="small text-muted"><?= e($course['academic_level'] ?? 'Course') ?> &bull; Enrolled <?= date('M d, Y', strtotime($course['enrolled_at'])) ?></div></div>
                    <span class="badge bg-success-subtle text-success rounded-pill px-3 py-2"><?= (int)$course['progress'] ?>% complete</span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
