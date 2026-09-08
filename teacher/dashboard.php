<?php
/**
 * StudyMe AI Platform — Teacher Command Center & Instructor Dashboard
 * State-of-the-art Educator Suite for course management, grading, student telemetry & earnings.
 */
require_once dirname(__DIR__) . '/config/main.php';
require_once BASE_PATH . '/includes/functions/activity.php';
require_once BASE_PATH . '/includes/functions/announcements.php';
require_once BASE_PATH . '/includes/functions/referrals.php';

secure_page(ROLE_TEACHER);
if (current_user_role() !== ROLE_ADMIN) {
    require_active_subscription();
}

$user  = current_user();
$pdo   = getDBConnection();
$uid   = (int)$user['id'];

// Log dashboard visit
log_user_activity($uid, 'teacher_dashboard_visit', 'Teacher viewed their dashboard');

// Get teacher record (teacher_id needed for course queries)
$stmt = $pdo->prepare("SELECT * FROM teachers WHERE user_id = ? LIMIT 1");
$stmt->execute([$uid]);
$teacher = $stmt->fetch(PDO::FETCH_ASSOC);
$tid = $teacher ? (int)$teacher['id'] : 0;

if (!$tid && current_user_role() === ROLE_TEACHER) {
    $tNum = 'TCH-' . date('Y') . '-' . str_pad($uid, 4, '0', STR_PAD_LEFT);
    $pdo->prepare("INSERT INTO teachers (user_id, teacher_number, status, created_at) VALUES (?, ?, 'active', NOW())")
        ->execute([$uid, $tNum]);
    $tid = (int)$pdo->lastInsertId();
    $stmt->execute([$uid]);
    $teacher = $stmt->fetch(PDO::FETCH_ASSOC);
}

$teacherNumber = $teacher['teacher_number'] ?? ('TCH-' . date('Y') . '-' . str_pad($uid, 4, '0', STR_PAD_LEFT));

// Wallet & Financials
$wallet = function_exists('get_user_wallet') ? get_user_wallet($uid) : ['available_balance' => 0, 'total_earned' => 0, 'pending_balance' => 0];

$courseCount        = 0;
$publishedCourses   = 0;
$studentCount       = 0;
$totalLessonsCount  = 0;
$totalQuizzesCount  = 0;
$totalTasksCount    = 0;
$recentCourses      = [];
$pendingSubmissions = [];

if ($tid) {
    // Total courses & published count
    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS total,
               SUM(CASE WHEN status = 'published' THEN 1 ELSE 0 END) AS published
        FROM courses WHERE teacher_id = ?
    ");
    $stmt->execute([$tid]);
    $cStats = $stmt->fetch(PDO::FETCH_ASSOC);
    $courseCount = (int)($cStats['total'] ?? 0);
    $publishedCourses = (int)($cStats['published'] ?? 0);

    // Total unique enrolled students across instructor's courses
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT e.student_id)
        FROM enrollments e
        JOIN courses c ON c.id = e.course_id
        WHERE c.teacher_id = ? AND e.status = 'active'
    ");
    $stmt->execute([$tid]);
    $studentCount = (int)$stmt->fetchColumn();

    // Total lessons created by instructor
    $stmtL = $pdo->prepare("
        SELECT COUNT(*) FROM lessons l
        JOIN course_sections cs ON l.section_id = cs.id
        JOIN courses c ON cs.course_id = c.id
        WHERE c.teacher_id = ?
    ");
    $stmtL->execute([$tid]);
    $totalLessonsCount = (int)$stmtL->fetchColumn();

    // Total quizzes
    $stmtQ = $pdo->prepare("
        SELECT COUNT(*) FROM quizzes q
        JOIN courses c ON q.course_id = c.id
        WHERE c.teacher_id = ?
    ");
    $stmtQ->execute([$tid]);
    $totalQuizzesCount = (int)$stmtQ->fetchColumn();

    // Total tasks
    $stmtT = $pdo->prepare("
        SELECT COUNT(*) FROM assignments a
        JOIN courses c ON a.course_id = c.id
        WHERE c.teacher_id = ?
    ");
    $stmtT->execute([$tid]);
    $totalTasksCount = (int)$stmtT->fetchColumn();

    // Recent courses with metrics
    $stmt = $pdo->prepare("
        SELECT c.*, cat.name AS category_name,
               (SELECT COUNT(DISTINCT e.student_id) FROM enrollments e WHERE e.course_id = c.id AND e.status = 'active') AS student_count,
               (SELECT COUNT(*) FROM lessons l JOIN course_sections cs ON l.section_id = cs.id WHERE cs.course_id = c.id) AS lesson_count,
               (SELECT COUNT(*) FROM quizzes q WHERE q.course_id = c.id) AS quiz_count,
               (SELECT COUNT(*) FROM assignments a WHERE a.course_id = c.id) AS task_count
        FROM courses c
        LEFT JOIN categories cat ON c.category_id = cat.id
        WHERE c.teacher_id = ?
        ORDER BY c.created_at DESC LIMIT 4
    ");
    $stmt->execute([$tid]);
    $recentCourses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Pending student submissions waiting for grading
    $stmtSub = $pdo->prepare("
        SELECT sub.*, a.title AS assignment_title, a.max_score, c.title AS course_title,
               u.first_name, u.last_name, u.email, u.avatar
        FROM assignment_submissions sub
        JOIN assignments a ON sub.assignment_id = a.id
        JOIN courses c ON a.course_id = c.id
        JOIN students s ON sub.student_id = s.id
        JOIN users u ON s.user_id = u.id
        WHERE c.teacher_id = ?
        ORDER BY sub.submitted_at DESC
        LIMIT 5
    ");
    $stmtSub->execute([$tid]);
    $pendingSubmissions = $stmtSub->fetchAll(PDO::FETCH_ASSOC);

    // Recent student questions needing reply
    $recentQuestions = function_exists('get_teacher_course_questions') 
        ? array_slice(get_teacher_course_questions($tid ?: $uid, null, 'my_courses'), 0, 4) 
        : [];
}

// Instructor Announcements
$stmtTchAnn = $pdo->prepare("
    SELECT a.*, c.title AS course_title 
    FROM announcements a 
    LEFT JOIN courses c ON a.course_id = c.id 
    WHERE a.created_by = ? 
    ORDER BY a.created_at DESC 
    LIMIT 3
");
$stmtTchAnn->execute([$uid]);
$recentTeacherAnns = $stmtTchAnn->fetchAll(PDO::FETCH_ASSOC);

include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<!-- ── 1. Instructor Hero Command Banner ──────────────────────────── -->
<div class="teacher-hero-card p-4 p-md-5 mb-5">
    <div class="position-relative" style="z-index: 2;">
        <div class="row align-items-center g-4">
            <div class="col-lg-8">
                <div class="d-flex align-items-center gap-2 flex-wrap mb-3">
                    <span class="badge bg-warning text-dark px-3 py-1 rounded-pill fw-bold text-uppercase" style="font-size: 0.78rem; letter-spacing: 0.05em;">
                        <i class="bi bi-person-badge-fill me-1"></i> Certified Instructor
                    </span>
                    <span class="badge bg-white bg-opacity-15 text-white border border-white border-opacity-25 px-3 py-1 rounded-pill fw-medium" style="font-size: 0.78rem;">
                        <i class="bi bi-hash me-1"></i> <?= e($teacherNumber) ?>
                    </span>
                    <span class="badge bg-success bg-opacity-25 text-white border border-success border-opacity-50 px-3 py-1 rounded-pill fw-medium" style="font-size: 0.78rem;">
                        <i class="bi bi-check-circle-fill me-1"></i> Active Status
                    </span>
                </div>
                
                <h1 class="display-6 fw-bold mb-2 text-white">
                    Welcome back, <?= e($user['first_name'] ?? 'Instructor') ?>! 🎓
                </h1>
                
                <p class="text-white-50 mb-4" style="max-width: 680px; font-size: 1rem; line-height: 1.6;">
                    Here is what is happening across your courses today. Track student enrollments, grade submitted tasks, manage your video lessons, and use AI-assisted tools to speed up your teaching workflow.
                </p>

                <div class="d-flex gap-2 flex-wrap">
                    <a href="<?= url('teacher/create-course.php') ?>" class="btn btn-warning text-dark fw-bold rounded-pill px-4 py-2 shadow-sm" data-feedback="click">
                        <i class="bi bi-plus-circle me-1"></i> Create New Course
                    </a>
                    <a href="<?= url('teacher/create-lesson.php') ?>" class="btn btn-light fw-bold rounded-pill px-4 py-2" data-feedback="click">
                        <i class="bi bi-play-circle me-1"></i> Add Lesson
                    </a>
                    <a href="<?= url('teacher/create-quiz.php') ?>" class="btn btn-outline-light rounded-pill px-4 py-2 fw-semibold" data-feedback="click">
                        <i class="bi bi-patch-question me-1"></i> Build Quiz
                    </a>
                    <a href="<?= url('teacher/profile.php') ?>" class="btn btn-outline-light rounded-pill px-4 py-2 fw-semibold" data-feedback="click">
                        <i class="bi bi-pencil-square me-1"></i> Edit Profile
                    </a>
                </div>
            </div>

            <div class="col-lg-4 text-lg-end">
                <div class="bg-white bg-opacity-10 backdrop-blur rounded-4 p-4 text-start border border-white border-opacity-20 d-inline-block w-100 shadow-sm" style="max-width: 360px;">
                    <div class="d-flex align-items-center justify-content-between mb-3 border-bottom border-white border-opacity-20 pb-2">
                        <span class="text-white-50 small fw-bold text-uppercase">Instructor Snapshot</span>
                        <a href="<?= url('teacher/profile.php') ?>" class="text-warning text-decoration-none small fw-bold">Live Profile &rarr;</a>
                    </div>
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <a href="<?= url('teacher/profile.php') ?>" class="position-relative text-decoration-none flex-shrink-0" title="Click to update instructor photo">
                            <?php $dashTchAvatar = function_exists('get_avatar_url') ? get_avatar_url($user['avatar'] ?? null, $user['first_name'] ?? 'Instructor') : ($user['avatar'] ?? ''); ?>
                            <div class="rounded-circle overflow-hidden bg-primary bg-opacity-25 border border-2 border-white d-flex align-items-center justify-content-center" style="width: 56px; height: 56px;">
                                <img src="<?= e($dashTchAvatar) ?>" alt="Avatar" style="width: 100%; height: 100%; object-fit: cover;" onerror="this.onerror=null; this.src='https://ui-avatars.com/api/?name=<?= urlencode($user['first_name'] ?? 'Instructor') ?>&background=4f46e5&color=ffffff&bold=true';">
                            </div>
                            <span class="position-absolute bottom-0 end-0 bg-warning text-dark rounded-circle d-flex align-items-center justify-content-center shadow" style="width: 18px; height: 18px; font-size: 9px;">
                                <i class="bi bi-camera-fill"></i>
                            </span>
                        </a>
                        <div>
                            <div class="fw-bold text-white mb-0"><?= e($user['first_name'] . ' ' . $user['last_name']) ?></div>
                            <div class="text-white-50 small"><?= e($teacher['specialization'] ?: 'Academic Specialist') ?> &bull; <a href="<?= url('teacher/profile.php') ?>" class="text-warning text-decoration-underline" style="font-size:0.75rem;">Change Photo</a></div>
                        </div>
                    </div>
                    <div class="row g-2 text-center text-white small pt-1">
                        <div class="col-4 bg-white bg-opacity-10 rounded-3 p-2">
                            <div class="fw-bold fs-6"><?= $courseCount ?></div>
                            <div class="text-white-50" style="font-size: 0.7rem;">Courses</div>
                        </div>
                        <div class="col-4 bg-white bg-opacity-10 rounded-3 p-2">
                            <div class="fw-bold fs-6"><?= $totalLessonsCount ?></div>
                            <div class="text-white-50" style="font-size: 0.7rem;">Lessons</div>
                        </div>
                        <div class="col-4 bg-white bg-opacity-10 rounded-3 p-2">
                            <div class="fw-bold fs-6 text-warning"><?= $studentCount ?></div>
                            <div class="text-white-50" style="font-size: 0.7rem;">Students</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ── 2. Performance Stats Overview ──────────────────────────────── -->
<div class="row g-4 mb-5">
    <!-- Total Courses -->
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon blue"><i class="bi bi-journal-code"></i></div>
            <div class="flex-grow-1">
                <div class="stat-value"><?= $courseCount ?></div>
                <p class="stat-label">Total Courses</p>
                <div class="d-flex align-items-center gap-1 mt-1">
                    <span class="badge bg-success bg-opacity-10 text-success rounded-pill small px-2 py-0" style="font-size: 0.72rem;">
                        <i class="bi bi-check-circle me-1"></i><?= $publishedCourses ?> Published
                    </span>
                    <a href="<?= url('teacher/courses.php') ?>" class="small text-decoration-none ms-auto text-primary fw-bold" style="font-size: 0.78rem;">View &rarr;</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Students Enrolled -->
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon green"><i class="bi bi-people-fill"></i></div>
            <div class="flex-grow-1">
                <div class="stat-value"><?= number_format($studentCount) ?></div>
                <p class="stat-label">Students Enrolled</p>
                <div class="d-flex align-items-center gap-1 mt-1">
                    <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill small px-2 py-0" style="font-size: 0.72rem;">
                        <i class="bi bi-mortarboard me-1"></i>Active Learners
                    </span>
                    <a href="<?= url('teacher/students.php') ?>" class="small text-decoration-none ms-auto text-success fw-bold" style="font-size: 0.78rem;">Roster &rarr;</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Total Earnings -->
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon gold"><i class="bi bi-wallet-fill"></i></div>
            <div class="flex-grow-1">
                <div class="stat-value">₦<?= number_format((float)($wallet['total_earned'] ?? 0), 2) ?></div>
                <p class="stat-label">Lifetime Earnings</p>
                <div class="d-flex align-items-center gap-1 mt-1">
                    <span class="badge bg-warning bg-opacity-15 text-dark rounded-pill small px-2 py-0" style="font-size: 0.72rem;">
                        <i class="bi bi-graph-up me-1"></i>All-Time
                    </span>
                    <a href="<?= url('teacher/earnings.php') ?>" class="small text-decoration-none ms-auto text-warning text-dark fw-bold" style="font-size: 0.78rem;">Ledger &rarr;</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Available Wallet Balance -->
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon purple"><i class="bi bi-cash-stack"></i></div>
            <div class="flex-grow-1">
                <div class="stat-value">₦<?= number_format((float)($wallet['available_balance'] ?? 0), 2) ?></div>
                <p class="stat-label">Available Payout</p>
                <div class="d-flex align-items-center gap-1 mt-1">
                    <a href="<?= url('teacher/earnings.php') ?>" class="btn btn-sm btn-primary rounded-pill px-3 py-0 fw-bold" style="font-size: 0.75rem;">
                        <i class="bi bi-arrow-up-right-circle me-1"></i> Withdraw
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ── 3. AI Educator Studio Co-Pilot ────────────────────────────── -->
<div class="ai-card mb-5">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <div class="ai-badge mb-0">
            <i class="bi bi-robot"></i> StudyMe AI Instructor Co-Pilot
        </div>
        <span class="badge bg-white bg-opacity-15 text-white border border-white border-opacity-25 rounded-pill px-3 py-1 small">
            <i class="bi bi-stars text-warning me-1"></i> 24/7 AI Curriculum Generator
        </span>
    </div>

    <div class="row align-items-center g-4">
        <div class="col-lg-7">
            <h3 class="fw-bold mb-2 text-white">Create syllabi, quiz questions, and summaries in seconds.</h3>
            <p class="mb-3 text-white-50" style="font-size: 0.95rem; line-height: 1.6;">
                Leverage StudyMe's integrated AI engine to auto-generate full multi-module course curricula, construct randomized multiple-choice assessment questions, or produce concise downloadable study guides for your students.
            </p>
            <div class="d-flex gap-2 flex-wrap">
                <button class="btn btn-warning text-dark fw-bold rounded-pill px-4 py-2"
                        data-bs-toggle="offcanvas" data-bs-target="#aiAssistantDrawer"
                        data-feedback="click">
                    <i class="bi bi-stars me-1"></i> Launch AI Course Assistant
                </button>
                <a href="<?= url('ai-learning.php') ?>" class="btn btn-outline-light rounded-pill px-4 py-2 fw-semibold" target="_blank">
                    <i class="bi bi-box-arrow-up-right me-1"></i> AI Learning Hub
                </a>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="row g-2">
                <div class="col-6">
                    <div class="p-3 rounded-4 bg-white bg-opacity-10 border border-white border-opacity-15 text-white h-100">
                        <i class="bi bi-file-earmark-text text-warning fs-4 mb-2 d-block"></i>
                        <h6 class="fw-bold mb-1 text-white">Syllabus Builder</h6>
                        <small class="text-white-50 d-block" style="font-size: 0.78rem;">Generate structured modules &amp; lesson outlines</small>
                    </div>
                </div>
                <div class="col-6">
                    <div class="p-3 rounded-4 bg-white bg-opacity-10 border border-white border-opacity-15 text-white h-100">
                        <i class="bi bi-patch-question text-info fs-4 mb-2 d-block"></i>
                        <h6 class="fw-bold mb-1 text-white">AI Quiz Crafter</h6>
                        <small class="text-white-50 d-block" style="font-size: 0.78rem;">Build 10-question tests with answer keys instantly</small>
                    </div>
                </div>
                <div class="col-6">
                    <div class="p-3 rounded-4 bg-white bg-opacity-10 border border-white border-opacity-15 text-white h-100">
                        <i class="bi bi-card-checklist text-success fs-4 mb-2 d-block"></i>
                        <h6 class="fw-bold mb-1 text-white">Task Prompts</h6>
                        <small class="text-white-50 d-block" style="font-size: 0.78rem;">Draft hands-on practical assignment tasks</small>
                    </div>
                </div>
                <div class="col-6">
                    <div class="p-3 rounded-4 bg-white bg-opacity-10 border border-white border-opacity-15 text-white h-100">
                        <i class="bi bi-journal-check text-danger fs-4 mb-2 d-block"></i>
                        <h6 class="fw-bold mb-1 text-white">Summaries</h6>
                        <small class="text-white-50 d-block" style="font-size: 0.78rem;">Produce high-yield revision study notes</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ── 4. Instructor Referral & Cash Bonus Banner ─────────────────── -->
<?php
$tchRefCode = function_exists('get_user_referral_code') ? get_user_referral_code($uid) : ('TCH-' . $uid);
$tchRefLink = function_exists('get_base_url') ? (get_base_url() . '/auth/register.php?ref=' . $tchRefCode) : url('auth/register.php?ref=' . $tchRefCode);
?>
<div class="card border-0 shadow-sm rounded-4 p-4 mb-5" style="background: linear-gradient(135deg, #1e1b4b 0%, #312e81 100%); color:#fff; border: 1px solid rgba(255,255,255,0.12) !important;">
    <div class="row align-items-center g-3">
        <div class="col-lg-8">
            <span class="badge bg-warning text-dark rounded-pill px-3 py-1 fw-bold text-uppercase small mb-2">
                <i class="bi bi-gift-fill me-1"></i> Instructor Referral Program: ₦1,500 per Student
            </span>
            <h4 class="fw-bold mb-1 text-white">Refer Students &amp; Earn Cash</h4>
            <p class="text-white-50 small mb-3">Earn ₦1,500 instant cash bonus into your wallet whenever a student registers and enrolls through your referral link.</p>
            <div class="input-group" style="max-width: 580px;">
                <input type="text" id="dashboardTchRefInput" class="form-control rounded-start-pill bg-white text-dark font-monospace small py-2 px-3 border-0 fw-semibold" value="<?= e($tchRefLink) ?>" readonly>
                <button class="btn btn-warning rounded-end-pill px-4 fw-bold text-dark" type="button" onclick="copyDashboardTchRefLink()">
                    <i class="bi bi-clipboard me-1"></i> <span id="dashCopyBtnText">Copy Link</span>
                </button>
            </div>
        </div>
        <div class="col-lg-4 text-lg-end">
            <a href="<?= url('teacher/referrals.php') ?>" class="btn btn-outline-light rounded-pill px-4 fw-bold shadow-sm">
                <i class="bi bi-wallet-fill me-1"></i> View Referral Hub &rarr;
            </a>
        </div>
    </div>
</div>

<!-- ── 5. Your Courses Showcase ───────────────────────────────────── -->
<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-3">
    <div>
        <h4 class="fw-bold mb-1 text-main"><i class="bi bi-journal-code text-primary me-2"></i>My Teaching Courses</h4>
        <p class="text-muted small mb-0">Active curriculum tracks assigned to your instructor account.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url('teacher/create-course.php') ?>" class="btn btn-sm btn-primary rounded-pill px-3 fw-bold" data-feedback="click">
            <i class="bi bi-plus-circle me-1"></i> New Course
        </a>
        <a href="<?= url('teacher/courses.php') ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-3 fw-semibold">
            View All (<?= $courseCount ?>) &rarr;
        </a>
    </div>
</div>

<?php if (!empty($recentCourses)): ?>
<div class="row g-4 mb-5">
    <?php foreach ($recentCourses as $c): ?>
        <?php 
        $tdThumb = function_exists('get_course_thumbnail_url') 
            ? get_course_thumbnail_url($c['thumbnail'] ?? '', $c['category_name'] ?? 'technology') 
            : ($c['thumbnail'] ?? 'https://images.unsplash.com/photo-1498050108023-c5249f4df085?w=500&q=80'); 
        ?>
        <div class="col-md-6 col-xl-3">
            <div class="course-card-pro h-100 d-flex flex-column">
                <div class="course-card-thumb">
                    <img src="<?= e($tdThumb) ?>" alt="<?= e($c['title']) ?>"
                         onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1498050108023-c5249f4df085?w=500&q=80';">
                    <span class="badge <?= $c['status'] === 'published' ? 'bg-success' : 'bg-warning text-dark' ?> position-absolute top-0 end-0 m-2 rounded-pill px-3 py-1 shadow-sm font-monospace" style="font-size: 0.72rem;">
                        <?= ucfirst($c['status'] ?? 'draft') ?>
                    </span>
                    <span class="badge bg-dark bg-opacity-75 text-white position-absolute bottom-0 start-0 m-2 rounded-pill px-2 py-1 small">
                        <?= e($c['category_name'] ?? 'Curriculum') ?>
                    </span>
                </div>

                <div class="p-3 d-flex flex-column flex-grow-1">
                    <h6 class="fw-bold mb-1 text-main line-clamp-2" title="<?= e($c['title']) ?>">
                        <?= e($c['title']) ?>
                    </h6>
                    
                    <div class="row g-2 text-center my-3 p-2 bg-light rounded-3 small border border-subtle">
                        <div class="col-4">
                            <div class="fw-bold text-main"><?= (int)($c['student_count'] ?? 0) ?></div>
                            <div class="text-muted" style="font-size: 0.7rem;">Students</div>
                        </div>
                        <div class="col-4">
                            <div class="fw-bold text-main"><?= (int)($c['lesson_count'] ?? 0) ?></div>
                            <div class="text-muted" style="font-size: 0.7rem;">Lessons</div>
                        </div>
                        <div class="col-4">
                            <div class="fw-bold text-main"><?= (int)($c['quiz_count'] ?? 0) ?></div>
                            <div class="text-muted" style="font-size: 0.7rem;">Quizzes</div>
                        </div>
                    </div>

                    <div class="d-flex gap-2 pt-2 border-top mt-auto">
                        <a href="<?= url('teacher/edit-course.php?id=' . (int)$c['id']) ?>" class="btn btn-sm btn-outline-primary rounded-pill flex-grow-1 fw-semibold" data-feedback="click">
                            <i class="bi bi-pencil-square me-1"></i> Edit
                        </a>
                        <a href="<?= url('teacher/lessons.php?course_id=' . (int)$c['id']) ?>" class="btn btn-sm btn-outline-info rounded-pill px-3 fw-semibold" title="Manage Lessons">
                            <i class="bi bi-play-btn me-1"></i> Lessons
                        </a>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<?php else: ?>
<div class="card border-0 shadow-sm rounded-4 p-5 text-center mb-5 bg-card">
    <div class="rounded-circle bg-primary bg-opacity-10 text-primary d-inline-flex align-items-center justify-content-center mx-auto mb-3" style="width: 80px; height: 80px;">
        <i class="bi bi-journal-plus fs-1"></i>
    </div>
    <h4 class="fw-bold mb-1">No Courses Created Yet</h4>
    <p class="text-muted mb-4" style="max-width: 480px; margin: 0 auto;">Start building your curriculum! Create video lessons, attach quizzes, and inspire thousands of students across StudyMe.</p>
    <div>
        <a href="<?= url('teacher/create-course.php') ?>" class="btn btn-primary rounded-pill px-4 py-2 fw-bold" data-feedback="click">
            <i class="bi bi-plus-circle me-1"></i> Create Your First Course
        </a>
    </div>
</div>
<?php endif; ?>

<!-- ── 5. Student Submissions & Announcements Row ─────────────────── -->
<div class="row g-4 mb-5">
    <!-- Student Task Submissions & Grading Queue -->
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm rounded-4 p-4 h-100 bg-card">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <div>
                    <h5 class="fw-bold mb-0 text-main">
                        <i class="bi bi-file-earmark-check-fill text-primary me-2"></i>Grading &amp; Task Submissions
                    </h5>
                    <small class="text-muted">Student work submitted for your review</small>
                </div>
                <a href="<?= url('teacher/submissions.php') ?>" class="small text-decoration-none fw-bold text-primary">
                    View All &rarr;
                </a>
            </div>

            <?php if (!empty($pendingSubmissions)): ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead class="bg-light">
                            <tr>
                                <th>Student</th>
                                <th>Task</th>
                                <th>Submitted</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pendingSubmissions as $sub): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="rounded-circle bg-primary bg-opacity-10 text-primary fw-bold d-flex align-items-center justify-content-center flex-shrink-0" style="width: 32px; height: 32px; font-size: 0.8rem;">
                                                <?= strtoupper(substr($sub['first_name'] ?? 'S', 0, 1)) ?>
                                            </div>
                                            <div>
                                                <div class="fw-bold text-main"><?= e($sub['first_name'] . ' ' . $sub['last_name']) ?></div>
                                                <small class="text-muted" style="font-size: 0.72rem;"><?= e($sub['course_title']) ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="fw-semibold text-main line-clamp-1"><?= e($sub['assignment_title']) ?></div>
                                        <span class="badge <?= $sub['status'] === 'graded' ? 'bg-success' : 'bg-warning text-dark' ?> rounded-pill" style="font-size: 0.68rem;">
                                            <?= ucfirst($sub['status'] ?? 'pending') ?>
                                        </span>
                                    </td>
                                    <td class="text-muted" style="font-size: 0.75rem;">
                                        <?= date('M d, g:i A', strtotime($sub['submitted_at'])) ?>
                                    </td>
                                    <td class="text-end">
                                        <a href="<?= url('teacher/submissions.php') ?>" class="btn btn-xs btn-outline-primary rounded-pill px-2 py-1 fw-bold" style="font-size: 0.75rem;">
                                            Grade
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-4 my-auto">
                    <i class="bi bi-check-circle-fill text-success fs-1 mb-2 d-block"></i>
                    <h6 class="fw-bold text-main mb-1">All Caught Up!</h6>
                    <p class="text-muted small mb-3">There are no pending student assignment submissions requiring grading at this time.</p>
                    <a href="<?= url('teacher/create-task.php') ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-bold">
                        <i class="bi bi-plus-circle me-1"></i> Post New Student Task
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Instructor Announcements & Broadcasts -->
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm rounded-4 p-4 h-100 bg-card">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <div>
                    <h5 class="fw-bold mb-0 text-main">
                        <i class="bi bi-megaphone-fill text-warning me-2"></i>Announcements
                    </h5>
                    <small class="text-muted">Broadcast updates to your students</small>
                </div>
                <a href="<?= url('teacher/create-announcement.php') ?>" class="btn btn-sm btn-warning rounded-pill px-3 fw-bold text-dark" data-feedback="click">
                    <i class="bi bi-plus-circle me-1"></i> Post
                </a>
            </div>

            <?php if (!empty($recentTeacherAnns)): ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($recentTeacherAnns as $ann): ?>
                        <?php 
                        $stats = function_exists('get_announcement_telemetry') 
                            ? get_announcement_telemetry($ann['id']) 
                            : ['read_count' => 0, 'recipients_count' => 0]; 
                        ?>
                        <div class="list-group-item px-0 py-3 border-light bg-transparent">
                            <div class="d-flex justify-content-between align-items-start gap-2 mb-1">
                                <a href="<?= url('announcements/view.php?id=' . (int)$ann['id']) ?>" class="fw-bold text-main text-decoration-none hover-primary line-clamp-1">
                                    <?= e($ann['title']) ?>
                                </a>
                                <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill small flex-shrink-0">
                                    <?= e($ann['course_title'] ?: 'General') ?>
                                </span>
                            </div>
                            <p class="text-muted small mb-2 line-clamp-2">
                                <?= e(mb_strimwidth(strip_tags($ann['content']), 0, 95, '...')) ?>
                            </p>
                            <div class="d-flex align-items-center justify-content-between text-muted" style="font-size: 0.75rem;">
                                <span><i class="bi bi-eye me-1 text-success"></i><?= (int)($stats['read_count'] ?? 0) ?> read</span>
                                <span><i class="bi bi-calendar3 me-1"></i><?= date('M d, Y', strtotime($ann['created_at'])) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="pt-3 border-top text-center mt-auto">
                    <a href="<?= url('teacher/announcements.php') ?>" class="small text-decoration-none fw-bold text-primary">
                        Manage All Announcements &rarr;
                    </a>
                </div>
            <?php else: ?>
                <div class="text-center py-4 my-auto">
                    <i class="bi bi-megaphone text-muted fs-1 mb-2 d-block"></i>
                    <h6 class="fw-bold text-main mb-1">No Announcements Yet</h6>
                    <p class="text-muted small mb-3">Post updates, assignment due date reminders, or study links directly to enrolled students.</p>
                    <a href="<?= url('teacher/create-announcement.php') ?>" class="btn btn-sm btn-warning text-dark rounded-pill px-3 fw-bold">
                        <i class="bi bi-plus-circle me-1"></i> Post First Announcement
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ── 6. Student Lesson Q&A Discussions Hub ─────────────────────── -->
<div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 mb-5 bg-card">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4 pb-3 border-bottom">
        <div>
            <h5 class="fw-bold mb-1 text-main">
                <i class="bi bi-chat-quote-fill text-info me-2"></i>Student Lesson Questions &amp; Discussions
            </h5>
            <small class="text-muted">Questions asked by enrolled learners across your courses needing your expert answers.</small>
        </div>
        <a href="<?= url('teacher/discussions.php') ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-bold">
            Open Q&amp;A Hub &rarr;
        </a>
    </div>

    <?php if (!empty($recentQuestions)): ?>
        <div class="row g-3">
            <?php foreach ($recentQuestions as $rq): ?>
                <div class="col-md-6">
                    <div class="p-3 rounded-4 border bg-light h-100 d-flex flex-column justify-content-between">
                        <div>
                            <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                                <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill small"><?= e($rq['course_title']) ?></span>
                                <span class="badge <?= $rq['status'] === 'answered' ? 'bg-success' : 'bg-warning text-dark' ?> rounded-pill small">
                                    <?= $rq['status'] === 'answered' ? 'Answered' : 'Needs Response' ?>
                                </span>
                            </div>
                            <div class="fw-bold text-main small mb-1 line-clamp-1">
                                <i class="bi bi-play-circle text-muted me-1"></i><?= e($rq['lesson_title']) ?>
                            </div>
                            <p class="text-secondary small mb-3 line-clamp-2"><?= e(mb_strimwidth(strip_tags($rq['question']), 0, 110, '...')) ?></p>
                        </div>
                        <div class="d-flex align-items-center justify-content-between pt-2 border-top border-subtle">
                            <small class="text-muted" style="font-size: 0.75rem;">
                                From <strong><?= e($rq['first_name'] . ' ' . $rq['last_name']) ?></strong>
                            </small>
                            <a href="<?= url('teacher/discussions.php#question-' . (int)$rq['id']) ?>" class="btn btn-xs btn-primary rounded-pill px-3 py-1 fw-bold" style="font-size: 0.75rem;">
                                Answer &rarr;
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="text-center py-4 text-muted">
            <i class="bi bi-chat-check text-success fs-1 mb-2 d-block"></i>
            <h6 class="fw-bold text-main">No pending student questions</h6>
            <p class="small mb-0">When students ask questions on your lessons, they will show up here for your review and reply.</p>
        </div>
    <?php endif; ?>
</div>

<!-- ── 7. Instructor Toolkit Quick Actions Grid ──────────────────── -->
<div class="mb-4">
    <h4 class="fw-bold mb-1 text-main"><i class="bi bi-grid-fill text-primary me-2"></i>Instructor Suite &amp; Tools</h4>
    <p class="text-muted small mb-3">Quick direct access to all course creation, examination, grading, and financial modules.</p>
</div>

<div class="row g-3 mb-5">
    <!-- Course Manager -->
    <div class="col-6 col-md-4 col-xl-3">
        <a href="<?= url('teacher/courses.php') ?>" class="teacher-action-card h-100">
            <div class="teacher-action-icon" style="background: rgba(37, 99, 235, 0.12); color: #2563EB;">
                <i class="bi bi-journal-code"></i>
            </div>
            <div>
                <div class="fw-bold text-main">Course Manager</div>
                <small class="text-muted" style="font-size: 0.78rem;">Manage <?= $courseCount ?> courses &amp; tracks</small>
            </div>
        </a>
    </div>

    <!-- Lessons Studio -->
    <div class="col-6 col-md-4 col-xl-3">
        <a href="<?= url('teacher/lessons.php') ?>" class="teacher-action-card h-100">
            <div class="teacher-action-icon" style="background: rgba(16, 185, 129, 0.12); color: #10B981;">
                <i class="bi bi-play-btn-fill"></i>
            </div>
            <div>
                <div class="fw-bold text-main">Lesson Studio</div>
                <small class="text-muted" style="font-size: 0.78rem;">Upload &amp; organize video lessons</small>
            </div>
        </a>
    </div>

    <!-- Quizzes & Tests -->
    <div class="col-6 col-md-4 col-xl-3">
        <a href="<?= url('teacher/quizzes.php') ?>" class="teacher-action-card h-100">
            <div class="teacher-action-icon" style="background: rgba(245, 158, 11, 0.12); color: #F59E0B;">
                <i class="bi bi-patch-question-fill"></i>
            </div>
            <div>
                <div class="fw-bold text-main">Quizzes &amp; Tests</div>
                <small class="text-muted" style="font-size: 0.78rem;">Create multiple-choice exams</small>
            </div>
        </a>
    </div>

    <!-- Student Roster -->
    <div class="col-6 col-md-4 col-xl-3">
        <a href="<?= url('teacher/students.php') ?>" class="teacher-action-card h-100">
            <div class="teacher-action-icon" style="background: rgba(139, 92, 246, 0.12); color: #8B5CF6;">
                <i class="bi bi-people-fill"></i>
            </div>
            <div>
                <div class="fw-bold text-main">Enrolled Students</div>
                <small class="text-muted" style="font-size: 0.78rem;"><?= $studentCount ?> learners enrolled</small>
            </div>
        </a>
    </div>

    <!-- Tasks & Assignments -->
    <div class="col-6 col-md-4 col-xl-3">
        <a href="<?= url('teacher/tasks.php') ?>" class="teacher-action-card h-100">
            <div class="teacher-action-icon" style="background: rgba(239, 68, 68, 0.12); color: #EF4444;">
                <i class="bi bi-list-task"></i>
            </div>
            <div>
                <div class="fw-bold text-main">Tasks &amp; Projects</div>
                <small class="text-muted" style="font-size: 0.78rem;">Assign homework &amp; projects</small>
            </div>
        </a>
    </div>

    <!-- Grading Queue -->
    <div class="col-6 col-md-4 col-xl-3">
        <a href="<?= url('teacher/submissions.php') ?>" class="teacher-action-card h-100">
            <div class="teacher-action-icon" style="background: rgba(6, 182, 212, 0.12); color: #06B6D4;">
                <i class="bi bi-file-earmark-check-fill"></i>
            </div>
            <div>
                <div class="fw-bold text-main">Grading Queue</div>
                <small class="text-muted" style="font-size: 0.78rem;">Score submitted student work</small>
            </div>
        </a>
    </div>

    <!-- Downloadable Resources -->
    <div class="col-6 col-md-4 col-xl-3">
        <a href="<?= url('teacher/resources.php') ?>" class="teacher-action-card h-100">
            <div class="teacher-action-icon" style="background: rgba(236, 72, 153, 0.12); color: #EC4899;">
                <i class="bi bi-file-earmark-arrow-down-fill"></i>
            </div>
            <div>
                <div class="fw-bold text-main">Course Resources</div>
                <small class="text-muted" style="font-size: 0.78rem;">PDFs, worksheets &amp; slides</small>
            </div>
        </a>
    </div>

    <!-- Earnings & Payouts -->
    <div class="col-6 col-md-4 col-xl-3">
        <a href="<?= url('teacher/earnings.php') ?>" class="teacher-action-card h-100">
            <div class="teacher-action-icon" style="background: rgba(20, 184, 166, 0.12); color: #14B8A6;">
                <i class="bi bi-wallet2"></i>
            </div>
            <div>
                <div class="fw-bold text-main">Earnings &amp; Wallet</div>
                <small class="text-muted" style="font-size: 0.78rem;">₦<?= number_format((float)($wallet['available_balance'] ?? 0), 2) ?> available</small>
            </div>
        </a>
    </div>

    <!-- Refer & Earn -->
    <div class="col-6 col-md-4 col-xl-3">
        <a href="<?= url('teacher/referrals.php') ?>" class="teacher-action-card h-100 border-warning border-opacity-50">
            <div class="teacher-action-icon" style="background: rgba(245, 158, 11, 0.15); color: #F59E0B;">
                <i class="bi bi-gift-fill"></i>
            </div>
            <div>
                <div class="fw-bold text-main">Refer &amp; Earn</div>
                <small class="text-warning fw-bold" style="font-size: 0.78rem;">₦1,500 bonus / student</small>
            </div>
        </a>
    </div>

    <!-- Student Q&A Discussions -->
    <div class="col-6 col-md-4 col-xl-3">
        <a href="<?= url('teacher/discussions.php') ?>" class="teacher-action-card h-100 border-info border-opacity-50">
            <div class="teacher-action-icon" style="background: rgba(6, 182, 212, 0.15); color: #06B6D4;">
                <i class="bi bi-chat-quote-fill"></i>
            </div>
            <div>
                <div class="fw-bold text-main">Student Q&amp;A Hub</div>
                <small class="text-info fw-bold" style="font-size: 0.78rem;">Answer lesson questions</small>
            </div>
        </a>
    </div>
</div>

<script>
function copyDashboardTchRefLink() {
    const input = document.getElementById("dashboardTchRefInput");
    if (input) {
        input.select();
        input.setSelectionRange(0, 99999);
        navigator.clipboard.writeText(input.value).then(() => {
            const btnText = document.getElementById("dashCopyBtnText");
            if (btnText) {
                btnText.innerText = "Copied!";
                setTimeout(() => {
                    btnText.innerText = "Copy Link";
                }, 2500);
            }
        });
    }
}
</script>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
