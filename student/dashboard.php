<?php
/**
 * StudyMe AI Platform — Student Dashboard
 * Tailored strictly to the student's active enrolled course.
 */
require_once dirname(__DIR__) . '/config/main.php';
require_once BASE_PATH . '/includes/functions/enrollments.php';
require_once BASE_PATH . '/includes/functions/activity.php';

// Guard: require login and student/admin role
secure_page(ROLE_STUDENT);

$user   = current_user();
$pdo    = getDBConnection();
$userId = (int)$user['id'];

// Log dashboard visit for tracking
log_user_activity($userId, 'dashboard_visit', 'Student viewed their dashboard');

// Resolve student record
$stmt = $pdo->prepare("SELECT id FROM students WHERE user_id = ? LIMIT 1");
$stmt->execute([$userId]);
$studentRow = $stmt->fetch(PDO::FETCH_ASSOC);
$studentId  = $studentRow ? (int)$studentRow['id'] : 0;

if (!$studentId && current_user_role() === ROLE_STUDENT) {
    $studentNum = 'STD-' . date('Y') . '-' . str_pad($userId, 4, '0', STR_PAD_LEFT);
    $pdo->prepare("INSERT INTO students (user_id, student_number) VALUES (?, ?)")->execute([$userId, $studentNum]);
    $studentId = (int)$pdo->lastInsertId();
}

// Resolve student's single active course
$activeCourse = null;
$courseLessons = [];
$courseQuizzes = [];
$courseTasks   = [];

if ($studentId) {
    $activeCourse = get_student_active_course($studentId);

    if ($activeCourse) {
        $cId = (int)$activeCourse['course_id'];

        // Lessons for this active course
        $stmtL = $pdo->prepare("
            SELECT l.*, cs.title AS section_title,
                   (SELECT completed FROM lesson_progress lp WHERE lp.lesson_id = l.id AND lp.enrollment_id = ?) AS is_completed
            FROM lessons l
            JOIN course_sections cs ON l.section_id = cs.id
            WHERE cs.course_id = ? AND l.status = 'published'
            ORDER BY cs.sort_order ASC, l.sort_order ASC
            LIMIT 6
        ");
        $stmtL->execute([$activeCourse['id'], $cId]);
        $courseLessons = $stmtL->fetchAll(PDO::FETCH_ASSOC);

        // Quizzes for this active course
        $stmtQ = $pdo->prepare("
            SELECT q.*, 
                   (SELECT COUNT(*) FROM questions qst WHERE qst.quiz_id = q.id) AS question_count,
                   (SELECT qa.passed FROM quiz_attempts qa WHERE qa.quiz_id = q.id AND qa.student_id = ? ORDER BY qa.id DESC LIMIT 1) AS last_passed
            FROM quizzes q
            WHERE q.course_id = ? AND q.status = 'published'
            ORDER BY q.created_at ASC
        ");
        $stmtQ->execute([$studentId, $cId]);
        $courseQuizzes = $stmtQ->fetchAll(PDO::FETCH_ASSOC);

        // Tasks / Assignments for this active course
        $stmtA = $pdo->prepare("
            SELECT a.*, 
                   (SELECT sub.status FROM assignment_submissions sub WHERE sub.assignment_id = a.id AND sub.student_id = ? LIMIT 1) AS sub_status
            FROM assignments a
            WHERE a.course_id = ? AND a.status = 'published'
            ORDER BY a.created_at ASC
        ");
        $stmtA->execute([$studentId, $cId]);
        $courseTasks = $stmtA->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Global stats
$certCount = 0;
if ($studentId) {
    $stmtCert = $pdo->prepare("SELECT COUNT(*) FROM certificates WHERE student_id = ?");
    $stmtCert->execute([$studentId]);
    $certCount = (int)$stmtCert->fetchColumn();
}

include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<!-- Greeting Header -->
<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
    <div class="d-flex align-items-center gap-3">
        <div class="position-relative">
            <?php $dashStudentAvatar = function_exists('get_avatar_url') ? get_avatar_url($user['avatar'] ?? null, $user['first_name'] ?? 'Student') : ($user['avatar'] ?? ''); ?>
            <a href="<?= url('student/profile.php') ?>" class="text-decoration-none" title="Click to update profile photo">
                <img src="<?= e($dashStudentAvatar) ?>" 
                     alt="<?= e($user['first_name']) ?>" 
                     class="rounded-circle border border-3 border-primary shadow-sm"
                     style="width: 60px; height: 60px; object-fit: cover;"
                     onerror="this.onerror=null; this.src='https://ui-avatars.com/api/?name=<?= urlencode($user['first_name'] ?? 'Student') ?>&background=4f46e5&color=ffffff&bold=true';">
                <span class="position-absolute bottom-0 end-0 bg-primary text-white rounded-circle d-flex align-items-center justify-content-center shadow" style="width: 20px; height: 20px; font-size: 10px;">
                    <i class="bi bi-camera-fill"></i>
                </span>
            </a>
        </div>
        <div>
            <p class="text-muted mb-0 fw-semibold text-uppercase small">
                <i class="bi bi-stars text-warning me-1"></i> Student Command Center
            </p>
            <h1 class="greeting-title mb-0 fw-bold fs-3">
                Welcome, <?= e($user['first_name']) ?> 👋
            </h1>
            <p class="text-muted small mb-0">Master your concepts with personalized lessons &amp; AI tutor feedback. <a href="<?= url('student/profile.php') ?>" class="text-primary text-decoration-none fw-semibold">Edit Photo</a></p>
        </div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="<?= url('student/profile.php') ?>" class="btn btn-outline-primary rounded-pill px-3 py-2 fw-semibold btn-sm d-flex align-items-center gap-1" title="Update Profile Picture">
            <i class="bi bi-person-circle"></i> <span>Update Profile</span>
        </a>
        <?php if (!$activeCourse): ?>
            <a href="<?= url('courses/university.php') ?>" class="btn btn-outline-primary rounded-pill px-3 fw-bold" data-feedback="click">
                <i class="bi bi-mortarboard-fill me-1"></i> University
            </a>
            <a href="<?= url('courses/technology.php') ?>" class="btn btn-outline-primary rounded-pill px-3 fw-bold" data-feedback="click">
                <i class="bi bi-code-slash me-1"></i> Tech
            </a>
            <a href="<?= url('courses/secondary.php') ?>" class="btn btn-outline-success rounded-pill px-3 fw-bold" data-feedback="click">
                <i class="bi bi-book-half me-1"></i> Secondary (WAEC/JAMB)
            </a>
        <?php endif; ?>
    </div>
</div>

<?php if ($activeCourse): ?>
<!-- ── Active Course Showcase Banner ────────────────────────── -->
<div class="card border-0 shadow-lg rounded-4 p-4 p-md-5 mb-5 overflow-hidden position-relative" style="background: linear-gradient(135deg, #1e1b4b 0%, #1e3a8a 100%); color:#fff;">
    <div class="row align-items-center g-4 position-relative" style="z-index:2;">
        <div class="col-lg-8">
            <span class="badge bg-success bg-opacity-25 text-success-emphasis border border-success border-opacity-50 rounded-pill px-3 py-1 mb-3 fw-bold small text-uppercase">
                <i class="bi bi-shield-check me-1"></i> Active Enrolled Course
            </span>
            <h2 class="display-6 fw-bold mb-2 text-white"><?= e($activeCourse['course_title']) ?></h2>
            
            <p class="text-white-50 mb-3 small">
                <?php if (!empty($activeCourse['teacher_id']) && !empty($activeCourse['teacher_name'])): ?>
                    <i class="bi bi-person-badge-fill me-1"></i>Teacher: <a href="<?= url('teacher-profile.php?id=' . (int)$activeCourse['teacher_id']) ?>" class="text-white fw-bold text-decoration-underline" target="_blank"><?= e($activeCourse['teacher_name']) ?></a>
                <?php elseif (($activeCourse['category_slug'] ?? '') === 'secondary-waec-neco'): ?>
                    <i class="bi bi-book-half me-1"></i>StudyMe Academic Curriculum
                <?php else: ?>
                    <i class="bi bi-person-x me-1"></i>Teacher: Currently unavailable
                <?php endif; ?>
                <span class="mx-2">|</span>
                Enrolled <?= date('d M Y', strtotime($activeCourse['enrolled_at'])) ?>
            </p>

            <div class="d-flex align-items-center gap-3 mb-4">
                <div class="progress flex-grow-1 bg-white bg-opacity-20 rounded-pill" style="height:10px;">
                    <div class="progress-bar bg-success rounded-pill" style="width: <?= (float)$activeCourse['progress'] ?>%;"></div>
                </div>
                <span class="fw-bold fs-5 text-white"><?= (int)$activeCourse['progress'] ?>% Completed</span>
            </div>

            <div class="d-flex gap-2 flex-wrap">
                <a href="<?= url('student/course.php?id=' . $activeCourse['course_id']) ?>" class="btn btn-primary btn-lg rounded-pill px-5 fw-bold shadow" data-feedback="click">
                    <i class="bi bi-play-circle-fill me-2"></i> Continue Learning
                </a>
                <a href="<?= url('student/ai-assistant.php') ?>" class="btn btn-outline-light btn-lg rounded-pill px-4 fw-bold">
                    <i class="bi bi-robot text-warning me-2"></i> Ask AI Tutor
                </a>
            </div>
        </div>
        <div class="col-lg-4 text-center">
            <?php $actThumb = function_exists('get_course_thumbnail_url') ? get_course_thumbnail_url($activeCourse['thumbnail'] ?? '', $activeCourse['category_slug'] ?? 'technology') : ($activeCourse['thumbnail'] ?? 'https://images.unsplash.com/photo-1498050108023-c5249f4df085?w=600&q=80'); ?>
            <img src="<?= e($actThumb) ?>"
                 class="rounded-4 shadow-lg border border-white border-opacity-10 w-100 img-fluid" style="max-height:220px; object-fit:cover;" alt="<?= e($activeCourse['course_title']) ?>"
                 onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1498050108023-c5249f4df085?w=600&q=80';">
        </div>
    </div>
</div>

<!-- Course Content Tabs & Panels -->
<div class="row g-4 mb-5">
    <!-- Left: Lessons Breakdown -->
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm rounded-4 p-4 h-100">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="fw-bold mb-0"><i class="bi bi-collection-play-fill text-primary me-2"></i>Course Lessons</h5>
                <a href="<?= url('student/course.php?id=' . $activeCourse['course_id']) ?>" class="small text-decoration-none fw-bold">View All Modules</a>
            </div>

            <?php if (!empty($courseLessons)): ?>
            <div class="list-group list-group-flush">
                <?php foreach ($courseLessons as $idx => $lsn): ?>
                <div class="list-group-item px-0 py-3 border-light d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-circle d-flex align-items-center justify-content-center <?= $lsn['is_completed'] ? 'bg-success text-white' : 'bg-light text-muted' ?>" style="width:36px;height:36px;">
                            <i class="bi <?= $lsn['is_completed'] ? 'bi-check-lg' : 'bi-play-fill' ?>"></i>
                        </div>
                        <div>
                            <div class="fw-bold text-main small"><?= e($lsn['title']) ?></div>
                            <small class="text-muted"><?= e($lsn['section_title']) ?> · <?= max(1, (int)($lsn['video_duration'] / 60)) ?> mins</small>
                        </div>
                    </div>
                    <a href="<?= url('student/lesson.php?id=' . $lsn['id']) ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                        <?= $lsn['is_completed'] ? 'Review' : 'Play' ?>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <p class="text-muted small mb-0">Lessons are currently being added for this course.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Right: Quizzes & Tasks -->
    <div class="col-lg-5">
        <!-- Quizzes Card -->
        <div class="card border-0 shadow-sm rounded-4 p-4 mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold mb-0"><i class="bi bi-patch-question-fill text-success me-2"></i>Course Quizzes</h5>
                <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-2"><?= count($courseQuizzes) ?> Available</span>
            </div>

            <?php if (!empty($courseQuizzes)): ?>
            <div class="d-flex flex-column gap-2">
                <?php foreach ($courseQuizzes as $qz): ?>
                <div class="p-3 bg-light rounded-3 d-flex justify-content-between align-items-center border border-subtle">
                    <div>
                        <div class="fw-bold text-main small"><?= e($qz['title']) ?></div>
                        <small class="text-muted"><?= (int)$qz['question_count'] ?> Questions · Pass: <?= (int)$qz['passing_score'] ?>%</small>
                    </div>
                    <a href="<?= url('quizzes/attempt.php?id=' . $qz['id']) ?>" class="btn btn-sm btn-primary rounded-pill px-3 fw-bold">
                        Take Quiz
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <p class="text-muted small mb-0">No quizzes published yet for this course.</p>
            <?php endif; ?>
        </div>

        <!-- AI Study Coach Card -->
        <div class="ai-card p-4 rounded-4" style="background: linear-gradient(135deg, #111827 0%, #1e1b4b 100%);">
            <div class="d-flex align-items-center gap-2 mb-2">
                <i class="bi bi-robot text-warning fs-4"></i>
                <h6 class="fw-bold text-white mb-0">24/7 AI Tutor Insight</h6>
            </div>
            <p class="text-white-50 small mb-3">Ask questions regarding <?= e($activeCourse['course_title']) ?> lessons or generate adaptive practice quizzes anytime.</p>
            <a href="<?= url('student/ai-assistant.php') ?>" class="btn btn-warning btn-sm rounded-pill px-4 fw-bold text-dark">
                Open AI Study Coach <i class="bi bi-arrow-right ms-1"></i>
            </a>
        </div>
    </div>
</div>

<?php else: ?>
<!-- ── No Enrolled Course State ──────────────────────────────── -->
<div class="card border-0 shadow-sm rounded-4 p-5 text-center my-4">
    <div class="p-4 bg-primary bg-opacity-10 text-primary rounded-circle d-inline-flex mx-auto mb-3 fs-1">
        <i class="bi bi-mortarboard-fill"></i>
    </div>
    <h3 class="fw-bold mb-2">No Active Enrolled Course</h3>
    <p class="text-muted max-w-md mx-auto mb-4">You are not currently enrolled in a course. Browse our academic catalog, choose a Technology, Secondary, or University course, and meet your AI Tutor.</p>
    <a href="<?= url('courses/index.php') ?>" class="btn btn-primary btn-lg rounded-pill px-5 py-3 fw-bold shadow mx-auto">
        <i class="bi bi-compass me-2"></i> Browse Course Catalog
    </a>
</div>
<?php endif; ?>

<!-- ── Latest Announcements Widget ───────────────────────────── -->
<?php 
$recentStudentAnns = function_exists('get_user_announcements') ? get_user_announcements($userId, ROLE_STUDENT, 3) : [];
?>
<?php if (!empty($recentStudentAnns)): ?>
<div class="card border-0 shadow-sm rounded-4 p-4 mb-4 bg-white">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-megaphone-fill text-warning me-2"></i>Latest Announcements</h5>
        <a href="<?= url('announcements/index.php') ?>" class="small text-decoration-none fw-bold">View All Announcements &rarr;</a>
    </div>
    <div class="list-group list-group-flush">
        <?php foreach ($recentStudentAnns as $ann): ?>
            <a href="<?= url('announcements/view.php?id=' . $ann['id']) ?>" class="list-group-item list-group-item-action px-0 py-3 border-light d-flex justify-content-between align-items-center">
                <div>
                    <div class="fw-bold text-dark mb-1">
                        <?= e($ann['title']) ?>
                        <?php if (empty($ann['is_read'])): ?>
                            <span class="badge bg-success rounded-pill px-2 py-1 small ms-1">NEW</span>
                        <?php endif; ?>
                    </div>
                    <small class="text-muted"><?= e(mb_strimwidth(strip_tags($ann['content']), 0, 100, '...')) ?></small>
                </div>
                <small class="text-muted text-nowrap ms-3"><?= date('M d', strtotime($ann['created_at'])) ?></small>
            </a>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Quick Actions -->
<h5 class="fw-bold mb-3">Quick Navigation</h5>
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <a href="<?= url('student/ai-assistant.php') ?>" class="card border-0 shadow-sm rounded-4 p-4 text-center text-decoration-none hover-lift d-flex flex-column align-items-center gap-2">
            <i class="bi bi-robot text-warning fs-2"></i>
            <span class="fw-semibold small text-main">AI Tutor</span>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="<?= url('student/quizzes.php') ?>" class="card border-0 shadow-sm rounded-4 p-4 text-center text-decoration-none hover-lift d-flex flex-column align-items-center gap-2">
            <i class="bi bi-patch-question-fill text-primary fs-2"></i>
            <span class="fw-semibold small text-main">Quizzes</span>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="<?= url('student/certificates.php') ?>" class="card border-0 shadow-sm rounded-4 p-4 text-center text-decoration-none hover-lift d-flex flex-column align-items-center gap-2">
            <i class="bi bi-award-fill text-success fs-2"></i>
            <span class="fw-semibold small text-main">Certificates (<?= $certCount ?>)</span>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="<?= url('student/profile.php') ?>" class="card border-0 shadow-sm rounded-4 p-4 text-center text-decoration-none hover-lift d-flex flex-column align-items-center gap-2">
            <i class="bi bi-person-circle text-info fs-2"></i>
            <span class="fw-semibold small text-main">My Profile</span>
        </a>
    </div>
</div>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
