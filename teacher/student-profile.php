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

$tid = (int)$teacher['id'];
$assignedCourseId = (int)($teacher['assigned_course_id'] ?? 0);

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
    $tid,
    $tid,
    $assignedCourseId,
]);
$student = $studentStmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    set_flash('error', 'You can only view profiles of students enrolled in your courses.');
    redirect('teacher/students.php');
}

$studentId = (int)$student['student_id'];

// 1. Courses enrolled with this teacher
$coursesStmt = $pdo->prepare(" 
    SELECT DISTINCT c.id, c.title, c.academic_level, e.enrolled_at, e.progress, e.status AS enrollment_status,
           (SELECT COUNT(*) FROM lessons l JOIN course_sections cs ON l.section_id = cs.id WHERE cs.course_id = c.id) AS total_lessons,
           (SELECT COUNT(*) FROM lesson_progress lp WHERE lp.enrollment_id = e.id AND lp.completed = 1) AS completed_lessons
    FROM enrollments e
    JOIN courses c ON c.id = e.course_id
    WHERE e.student_id = ?
      AND e.status = 'active'
      AND (e.teacher_id = ? OR c.teacher_id = ? OR c.id = ?)
    ORDER BY e.enrolled_at DESC
");
$coursesStmt->execute([$studentId, $tid, $tid, $assignedCourseId]);
$courses = $coursesStmt->fetchAll(PDO::FETCH_ASSOC);

// 2. Completed lessons total
$completedStmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM lesson_progress lp 
    JOIN enrollments e ON e.id = lp.enrollment_id 
    JOIN courses c ON c.id = e.course_id
    WHERE e.student_id = ? AND lp.completed = 1 AND (c.teacher_id = ? OR c.id = ?)
");
$completedStmt->execute([$studentId, $tid, $assignedCourseId]);
$completedLessons = (int)$completedStmt->fetchColumn();

// 3. Quiz Attempts in this teacher's courses
$quizzesStmt = $pdo->prepare("
    SELECT qa.*, q.title AS quiz_title, q.pass_percentage, c.title AS course_title
    FROM quiz_attempts qa
    JOIN quizzes q ON qa.quiz_id = q.id
    JOIN courses c ON q.course_id = c.id
    WHERE (qa.student_id = ? OR qa.student_id = ?) 
      AND (c.teacher_id = ? OR c.id = ?)
    ORDER BY qa.started_at DESC
");
$quizzesStmt->execute([$studentId, $studentUserId, $tid, $assignedCourseId]);
$quizAttempts = $quizzesStmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate Quiz Average
$totalQuizScore = 0;
$quizCount = count($quizAttempts);
foreach ($quizAttempts as $qa) {
    $totalQuizScore += (float)($qa['percentage'] ?? 0);
}
$avgQuizScore = $quizCount > 0 ? round($totalQuizScore / $quizCount, 1) : 0;

// 4. Assignment Submissions in this teacher's courses
$submissionsStmt = $pdo->prepare("
    SELECT sub.*, a.title AS assignment_title, a.max_score, a.due_date, c.title AS course_title
    FROM assignment_submissions sub
    JOIN assignments a ON sub.assignment_id = a.id
    JOIN courses c ON a.course_id = c.id
    WHERE sub.student_id = ? AND (c.teacher_id = ? OR c.id = ?)
    ORDER BY sub.submitted_at DESC
");
$submissionsStmt->execute([$studentId, $tid, $assignedCourseId]);
$submissions = $submissionsStmt->fetchAll(PDO::FETCH_ASSOC);

// 5. Live Class Attendance for this teacher's live sessions
$attendanceStmt = $pdo->prepare("
    SELECT lca.*, lc.topic, lc.start_time, lc.status AS live_status, c.title AS course_title
    FROM live_class_attendance lca
    JOIN live_classes lc ON lca.live_class_id = lc.id
    JOIN courses c ON lc.course_id = c.id
    WHERE (lca.student_id = ? OR lca.user_id = ?) 
      AND (lc.teacher_id = ? OR c.teacher_id = ? OR c.id = ?)
    ORDER BY lca.attended_at DESC
");
$attendanceStmt->execute([$studentId, $studentUserId, $tid, $tid, $assignedCourseId]);
$attendances = $attendanceStmt->fetchAll(PDO::FETCH_ASSOC);

// 6. Recent Completed Lessons timeline
$lessonProgressStmt = $pdo->prepare("
    SELECT lp.*, l.title AS lesson_title, cs.title AS section_title, c.title AS course_title
    FROM lesson_progress lp
    JOIN lessons l ON lp.lesson_id = l.id
    JOIN course_sections cs ON l.section_id = cs.id
    JOIN courses c ON cs.course_id = c.id
    JOIN enrollments e ON e.id = lp.enrollment_id
    WHERE e.student_id = ? AND lp.completed = 1 AND (c.teacher_id = ? OR c.id = ?)
    ORDER BY lp.completed_at DESC
    LIMIT 20
");
$lessonProgressStmt->execute([$studentId, $tid, $assignedCourseId]);
$recentLessons = $lessonProgressStmt->fetchAll(PDO::FETCH_ASSOC);

$fullName = trim($student['first_name'] . ' ' . $student['last_name']);
$avatarUrl = function_exists('get_avatar_url')
    ? get_avatar_url($student['avatar'] ?? null, $fullName)
    : ($student['avatar'] ?? '');

include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<style>
/* Responsive Mobile & iPad Optimizations for Student Profile */
@media (max-width: 991.98px) {
    .student-detail-header-tabs {
        flex-wrap: nowrap !important;
        overflow-x: auto !important;
        -webkit-overflow-scrolling: touch;
        white-space: nowrap !important;
        scrollbar-width: none;
    }
    .student-detail-header-tabs::-webkit-scrollbar {
        display: none;
    }
    .student-detail-header-tabs .nav-item {
        flex-shrink: 0;
    }
}
.touch-target {
    min-height: 44px;
}
</style>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
    <div>
        <a href="<?= url('teacher/students.php') ?>" class="text-decoration-none small text-muted"><i class="bi bi-arrow-left me-1"></i>Back to enrolled students</a>
        <h2 class="fw-bold mb-0 mt-2 fs-3 fs-md-2">Student Dossier &amp; Details</h2>
    </div>
    <div class="d-flex gap-2">
        <a href="mailto:<?= e($student['email']) ?>" class="btn btn-outline-primary rounded-pill px-3 px-sm-4 fw-semibold touch-target d-inline-flex align-items-center">
            <i class="bi bi-envelope-fill me-2"></i> <span>Send Email</span>
        </a>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Left Column: Student Bio & Info Card -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm rounded-4 p-4 text-center h-100">
            <img src="<?= e($avatarUrl) ?>" alt="<?= e($fullName) ?>" class="rounded-circle border border-4 border-primary-subtle shadow-sm mx-auto mb-3" style="width: 120px; height: 120px; object-fit: cover;" onerror="this.onerror=null; this.src='https://ui-avatars.com/api/?name=<?= urlencode($fullName) ?>&background=1e40af&color=ffffff&bold=true';">
            <h3 class="h4 fw-bold mb-1 text-main"><?= e($fullName) ?></h3>
            <p class="text-muted small mb-3">
                <span class="badge bg-light text-secondary border px-3 py-1"><?= e($student['student_number'] ?: 'STUDENT-' . $student['student_id']) ?></span>
            </p>
            
            <div class="d-flex justify-content-center gap-2 flex-wrap mb-4">
                <?php if (!empty($student['city']) || !empty($student['country'])): ?>
                    <span class="badge bg-light text-secondary border rounded-pill px-3 py-2"><i class="bi bi-geo-alt me-1 text-danger"></i><?= e(trim(($student['city'] ?? '') . (!empty($student['city']) && !empty($student['country']) ? ', ' : '') . ($student['country'] ?? ''))) ?></span>
                <?php endif; ?>
                <span class="badge bg-success-subtle text-success border border-success border-opacity-25 rounded-pill px-3 py-2"><i class="bi bi-check-circle me-1"></i>Active Student</span>
            </div>

            <div class="text-start border-top pt-3">
                <h6 class="fw-bold text-uppercase small text-muted mb-3"><i class="bi bi-info-circle me-1"></i> Contact &amp; Details</h6>
                <div class="small mb-2 d-flex align-items-center gap-2">
                    <i class="bi bi-envelope text-primary fs-6"></i>
                    <span class="text-muted text-break"><?= e($student['email']) ?></span>
                </div>
                <?php if (!empty($student['phone'])): ?>
                    <div class="small mb-2 d-flex align-items-center gap-2">
                        <i class="bi bi-telephone text-success fs-6"></i>
                        <span class="text-muted"><?= e($student['phone']) ?></span>
                    </div>
                <?php endif; ?>
                <?php if (!empty($student['state'])): ?>
                    <div class="small mb-2 d-flex align-items-center gap-2">
                        <i class="bi bi-pin-map text-warning fs-6"></i>
                        <span class="text-muted"><?= e($student['state']) ?></span>
                    </div>
                <?php endif; ?>
                <div class="small mb-2 d-flex align-items-center gap-2">
                    <i class="bi bi-calendar3 text-secondary fs-6"></i>
                    <span class="text-muted">Joined StudyMe: <?= date('M d, Y', strtotime($student['created_at'])) ?></span>
                </div>
            </div>

            <?php if (!empty($student['bio'])): ?>
                <div class="text-start border-top pt-3 mt-3">
                    <h6 class="fw-bold text-uppercase small text-muted mb-2"><i class="bi bi-chat-square-quote me-1"></i> Student Bio</h6>
                    <p class="text-secondary small mb-0 lh-base"><?= e($student['bio']) ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Right Column: Learning Analytics & KPI Counters -->
    <div class="col-lg-8">
        <!-- Quick Stats Grid -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
                <div class="card border-0 shadow-sm rounded-4 p-3 bg-white text-center h-100">
                    <div class="fs-4 text-primary mb-1"><i class="bi bi-journal-check"></i></div>
                    <div class="fs-4 fw-bold text-main"><?= $completedLessons ?></div>
                    <div class="small text-muted">Completed Lessons</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card border-0 shadow-sm rounded-4 p-3 bg-white text-center h-100">
                    <div class="fs-4 text-warning mb-1"><i class="bi bi-patch-question"></i></div>
                    <div class="fs-4 fw-bold text-main"><?= $avgQuizScore ?>%</div>
                    <div class="small text-muted">Avg Quiz Score</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card border-0 shadow-sm rounded-4 p-3 bg-white text-center h-100">
                    <div class="fs-4 text-info mb-1"><i class="bi bi-file-earmark-check"></i></div>
                    <div class="fs-4 fw-bold text-main"><?= count($submissions) ?></div>
                    <div class="small text-muted">Tasks Submitted</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card border-0 shadow-sm rounded-4 p-3 bg-white text-center h-100">
                    <div class="fs-4 text-danger mb-1"><i class="bi bi-broadcast"></i></div>
                    <div class="fs-4 fw-bold text-main"><?= count($attendances) ?></div>
                    <div class="small text-muted">Live Classes Attended</div>
                </div>
            </div>
        </div>

        <!-- Navigation Tabs for Detailed Student Records -->
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
            <div class="card-header bg-white border-0 pt-3 px-3 pb-0">
                <ul class="nav nav-tabs card-header-tabs student-detail-header-tabs" id="studentDetailTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active fw-bold text-main" id="courses-tab" data-bs-toggle="tab" data-bs-target="#courses-pane" type="button" role="tab">
                            <i class="bi bi-book me-1"></i> Courses (<?= count($courses) ?>)
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link fw-bold text-main" id="quizzes-tab" data-bs-toggle="tab" data-bs-target="#quizzes-pane" type="button" role="tab">
                            <i class="bi bi-patch-question me-1"></i> Quizzes (<?= count($quizAttempts) ?>)
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link fw-bold text-main" id="tasks-tab" data-bs-toggle="tab" data-bs-target="#tasks-pane" type="button" role="tab">
                            <i class="bi bi-file-earmark-check me-1"></i> Tasks (<?= count($submissions) ?>)
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link fw-bold text-main" id="live-tab" data-bs-toggle="tab" data-bs-target="#live-pane" type="button" role="tab">
                            <i class="bi bi-broadcast me-1"></i> Live Class (<?= count($attendances) ?>)
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link fw-bold text-main" id="lessons-tab" data-bs-toggle="tab" data-bs-target="#lessons-pane" type="button" role="tab">
                            <i class="bi bi-check-circle me-1"></i> Lessons (<?= count($recentLessons) ?>)
                        </button>
                    </li>
                </ul>
            </div>

            <div class="card-body p-4">
                <div class="tab-content" id="studentDetailTabsContent">
                    <!-- Tab 1: Enrolled Courses -->
                    <div class="tab-pane fade show active" id="courses-pane" role="tabpanel">
                        <?php if (!empty($courses)): ?>
                            <div class="d-flex flex-column gap-3">
                                <?php foreach ($courses as $c): ?>
                                    <div class="p-3 border rounded-3 bg-light bg-opacity-50">
                                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                                            <div>
                                                <h6 class="fw-bold mb-0 text-main"><?= e($c['title']) ?></h6>
                                                <small class="text-muted"><?= e($c['academic_level'] ?? 'Online Course') ?> &bull; Enrolled <?= date('M d, Y', strtotime($c['enrolled_at'])) ?></small>
                                            </div>
                                            <span class="badge bg-primary-subtle text-primary border border-primary border-opacity-25 rounded-pill px-3 py-1">
                                                <?= (int)$c['progress'] ?>% Completed
                                            </span>
                                        </div>
                                        <div class="progress mb-2" style="height: 7px; border-radius: 10px;">
                                            <div class="progress-bar <?= (int)$c['progress'] >= 100 ? 'bg-success' : 'bg-primary' ?>" style="width: <?= (float)$c['progress'] ?>%;"></div>
                                        </div>
                                        <div class="small text-muted d-flex justify-content-between">
                                            <span>Lessons: <?= (int)$c['completed_lessons'] ?> / <?= (int)$c['total_lessons'] ?> completed</span>
                                            <span>Status: <strong class="text-success"><?= ucfirst($c['enrollment_status'] ?? 'active') ?></strong></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-muted text-center py-4 mb-0">No active course enrollments found.</p>
                        <?php endif; ?>
                    </div>

                    <!-- Tab 2: Quizzes & Assessment Records -->
                    <div class="tab-pane fade" id="quizzes-pane" role="tabpanel">
                        <?php if (!empty($quizAttempts)): ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light small">
                                        <tr>
                                            <th>Quiz Title</th>
                                            <th>Course</th>
                                            <th>Score</th>
                                            <th>Percentage</th>
                                            <th>Status</th>
                                            <th class="text-end">Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($quizAttempts as $qa): ?>
                                            <tr>
                                                <td class="fw-semibold text-main"><?= e($qa['quiz_title']) ?></td>
                                                <td class="small text-muted"><?= e($qa['course_title']) ?></td>
                                                <td><span class="fw-bold"><?= (int)$qa['score'] ?></span></td>
                                                <td><span class="fw-semibold text-primary"><?= (float)$qa['percentage'] ?>%</span></td>
                                                <td>
                                                    <?php if (!empty($qa['passed'])): ?>
                                                        <span class="badge bg-success-subtle text-success rounded-pill px-2 py-1"><i class="bi bi-check-circle me-1"></i>Passed</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger-subtle text-danger rounded-pill px-2 py-1"><i class="bi bi-x-circle me-1"></i>Needs Review</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-end small text-muted"><?= date('M d, Y', strtotime($qa['started_at'] ?? 'now')) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4 text-muted">
                                <i class="bi bi-patch-question display-6 mb-2 d-block"></i>
                                <p class="mb-0">This student has not attempted any quizzes in your courses yet.</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Tab 3: Assignment & Task Submissions -->
                    <div class="tab-pane fade" id="tasks-pane" role="tabpanel">
                        <?php if (!empty($submissions)): ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light small">
                                        <tr>
                                            <th>Task Title</th>
                                            <th>Course</th>
                                            <th>Submitted</th>
                                            <th>Score</th>
                                            <th>Status</th>
                                            <th class="text-end">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($submissions as $sub): ?>
                                            <tr>
                                                <td class="fw-semibold text-main"><?= e($sub['assignment_title']) ?></td>
                                                <td class="small text-muted"><?= e($sub['course_title']) ?></td>
                                                <td class="small text-muted"><?= date('M d, Y', strtotime($sub['submitted_at'])) ?></td>
                                                <td>
                                                    <?php if ($sub['score'] !== null): ?>
                                                        <span class="fw-bold text-success"><?= e($sub['score']) ?> / <?= e($sub['max_score'] ?? 100) ?></span>
                                                    <?php else: ?>
                                                        <span class="text-muted small">Ungraded</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($sub['status'] === 'graded'): ?>
                                                        <span class="badge bg-success-subtle text-success rounded-pill px-2 py-1">Graded</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning-subtle text-warning rounded-pill px-2 py-1">Pending Grade</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-end">
                                                    <a href="<?= url('teacher/submissions.php') ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                                                        View Task
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4 text-muted">
                                <i class="bi bi-file-earmark-check display-6 mb-2 d-block"></i>
                                <p class="mb-0">No assignment submissions recorded for this student yet.</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Tab 4: Live Class Attendance -->
                    <div class="tab-pane fade" id="live-pane" role="tabpanel">
                        <?php if (!empty($attendances)): ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light small">
                                        <tr>
                                            <th>Class Topic</th>
                                            <th>Course</th>
                                            <th>Attended Date</th>
                                            <th class="text-end">Attendance Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($attendances as $att): ?>
                                            <tr>
                                                <td class="fw-semibold text-main"><?= e($att['topic'] ?? 'Live Session') ?></td>
                                                <td class="small text-muted"><?= e($att['course_title'] ?? 'Course') ?></td>
                                                <td class="small text-muted"><?= date('M d, Y h:i A', strtotime($att['attended_at'])) ?></td>
                                                <td class="text-end">
                                                    <span class="badge bg-success-subtle text-success rounded-pill px-3 py-1">
                                                        <i class="bi bi-check2-circle me-1"></i>Present
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4 text-muted">
                                <i class="bi bi-broadcast display-6 mb-2 d-block"></i>
                                <p class="mb-0">This student has not attended any live class sessions yet.</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Tab 5: Completed Lessons Timeline -->
                    <div class="tab-pane fade" id="lessons-pane" role="tabpanel">
                        <?php if (!empty($recentLessons)): ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($recentLessons as $rl): ?>
                                    <div class="list-group-item d-flex align-items-center justify-content-between px-0 py-3">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="rounded-circle bg-success bg-opacity-10 text-success p-2 fs-6">
                                                <i class="bi bi-check-lg"></i>
                                            </div>
                                            <div>
                                                <h6 class="fw-bold mb-0 text-main"><?= e($rl['lesson_title']) ?></h6>
                                                <small class="text-muted"><?= e($rl['section_title']) ?> &bull; <?= e($rl['course_title']) ?></small>
                                            </div>
                                        </div>
                                        <span class="small text-muted"><?= date('M d, Y', strtotime($rl['completed_at'])) ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4 text-muted">
                                <i class="bi bi-journal-x display-6 mb-2 d-block"></i>
                                <p class="mb-0">No lesson completion records recorded yet.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>

