<?php

require_once dirname(__DIR__) . '/config/main.php';

secure_page(ROLE_TEACHER);
if (current_user_role() !== ROLE_ADMIN) {
    require_active_subscription();
}

$user = current_user();
$pdo  = getDBConnection();
$uid  = $user['id'];
$courseFilter = (int)($_GET['course_id'] ?? 0);
$searchQuery = trim($_GET['q'] ?? '');

$stmt = $pdo->prepare("SELECT id, assigned_course_id FROM teachers WHERE user_id = ? LIMIT 1");
$stmt->execute([$uid]);
$teacher = $stmt->fetch(PDO::FETCH_ASSOC);
$tid = $teacher ? (int)$teacher['id'] : 0;
$assignedCourseId = (int)($teacher['assigned_course_id'] ?? 0);

// Get teacher's courses for the filter dropdown
$teacherCourses = [];
if ($tid) {
    $cStmt = $pdo->prepare("SELECT id, title FROM courses WHERE teacher_id = ? OR id = ? ORDER BY title ASC");
    $cStmt->execute([$tid, $assignedCourseId]);
    $teacherCourses = $cStmt->fetchAll(PDO::FETCH_ASSOC);
}

$students = [];
$totalStudents = 0;
$activeLearners = 0;
$completedCourses = 0;
$totalProgressSum = 0;

if ($tid) {
    $params = [$tid, $tid, $assignedCourseId];
    $sql = "
        SELECT e.*, c.title AS course_title, c.academic_level,
               s.id AS student_id, s.student_number, s.bio, s.city, s.country,
               u.id AS student_user_id, u.first_name, u.last_name, u.email, u.phone, u.avatar,
               (SELECT COUNT(*) FROM lesson_progress lp WHERE lp.enrollment_id = e.id AND lp.completed = 1) AS completed_lessons_count,
               (SELECT COUNT(*) FROM quiz_attempts qa JOIN quizzes q ON qa.quiz_id = q.id WHERE (qa.student_id = s.id OR qa.student_id = u.id) AND q.course_id = c.id) AS quiz_attempts_count,
               (SELECT COUNT(*) FROM assignment_submissions sub JOIN assignments a ON sub.assignment_id = a.id WHERE sub.student_id = s.id AND a.course_id = c.id) AS submissions_count,
               (SELECT COUNT(*) FROM live_class_attendance lca JOIN live_classes lc ON lca.live_class_id = lc.id WHERE (lca.student_id = s.id OR lca.user_id = u.id) AND lc.course_id = c.id) AS attendance_count
        FROM enrollments e
        JOIN courses c ON e.course_id = c.id
        JOIN students s ON e.student_id = s.id
        JOIN users u ON s.user_id = u.id
        WHERE (e.teacher_id = ? OR c.teacher_id = ? OR c.id = ?) AND e.status = 'active'
    ";
    
    if ($courseFilter > 0) {
        $sql .= " AND e.course_id = ?";
        $params[] = $courseFilter;
    }
    
    if (!empty($searchQuery)) {
        $sql .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR s.student_number LIKE ?)";
        $searchTerm = "%{$searchQuery}%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    $sql .= " ORDER BY e.enrolled_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $totalStudents = count($students);
    foreach ($students as $st) {
        $prog = (float)($st['progress'] ?? 0);
        $totalProgressSum += $prog;
        if ($prog > 0) {
            $activeLearners++;
        }
        if ($prog >= 100) {
            $completedCourses++;
        }
    }
}

$avgProgress = $totalStudents > 0 ? round($totalProgressSum / $totalStudents, 1) : 0;

include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<style>
/* Responsive Mobile & iPad Optimizations */
.metric-icon-box {
    width: 48px;
    height: 48px;
    flex-shrink: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 12px;
}
@media (max-width: 575.98px) {
    .metric-icon-box {
        width: 40px;
        height: 40px;
        font-size: 1.1rem !important;
    }
    .metric-number {
        font-size: 1.35rem !important;
    }
    .metric-label {
        font-size: 0.72rem !important;
    }
}
.student-mobile-card {
    border-radius: 16px;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    border: 1px solid rgba(0, 0, 0, 0.06);
    background: #ffffff;
}
.student-mobile-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.08), 0 8px 10px -6px rgba(0, 0, 0, 0.04) !important;
}
.stat-pill {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 6px 10px;
    font-size: 0.8rem;
    text-align: center;
    flex: 1;
}
.search-filter-card {
    border-radius: 16px;
    border: 1px solid rgba(0, 0, 0, 0.05);
}
.touch-target {
    min-height: 44px;
}
</style>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
    <div>
        <p class="text-muted mb-1 fw-semibold text-uppercase small"><i class="bi bi-people-fill text-warning me-1"></i> Instructor Suite</p>
        <h2 class="fw-bold mb-0 fs-3 fs-md-2">My Enrolled Students</h2>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url('teacher/live-classes.php') ?>" class="btn btn-outline-danger rounded-pill px-3 px-sm-4 fw-bold touch-target d-inline-flex align-items-center">
            <i class="bi bi-broadcast me-1 me-sm-2"></i> <span>Host Live Class</span>
        </a>
    </div>
</div>

<!-- Responsive Quick Metric Cards (2x2 on Mobile, 4x1 on Desktop) -->
<div class="row g-2 g-sm-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm rounded-4 p-2 p-sm-3 bg-white h-100">
            <div class="d-flex align-items-center gap-2 gap-sm-3">
                <div class="metric-icon-box bg-primary bg-opacity-10 text-primary fs-4">
                    <i class="bi bi-people"></i>
                </div>
                <div class="overflow-hidden">
                    <h3 class="fw-bold mb-0 text-main metric-number"><?= number_format($totalStudents) ?></h3>
                    <span class="text-muted small fw-medium text-truncate d-block metric-label">Students</span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm rounded-4 p-2 p-sm-3 bg-white h-100">
            <div class="d-flex align-items-center gap-2 gap-sm-3">
                <div class="metric-icon-box bg-info bg-opacity-10 text-info fs-4">
                    <i class="bi bi-lightning-charge"></i>
                </div>
                <div class="overflow-hidden">
                    <h3 class="fw-bold mb-0 text-main metric-number"><?= number_format($activeLearners) ?></h3>
                    <span class="text-muted small fw-medium text-truncate d-block metric-label">Active Learners</span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm rounded-4 p-2 p-sm-3 bg-white h-100">
            <div class="d-flex align-items-center gap-2 gap-sm-3">
                <div class="metric-icon-box bg-success bg-opacity-10 text-success fs-4">
                    <i class="bi bi-check2-circle"></i>
                </div>
                <div class="overflow-hidden">
                    <h3 class="fw-bold mb-0 text-main metric-number"><?= number_format($completedCourses) ?></h3>
                    <span class="text-muted small fw-medium text-truncate d-block metric-label">Graduated (100%)</span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm rounded-4 p-2 p-sm-3 bg-white h-100">
            <div class="d-flex align-items-center gap-2 gap-sm-3">
                <div class="metric-icon-box bg-warning bg-opacity-10 text-warning fs-4">
                    <i class="bi bi-graph-up-arrow"></i>
                </div>
                <div class="overflow-hidden">
                    <h3 class="fw-bold mb-0 text-main metric-number"><?= $avgProgress ?>%</h3>
                    <span class="text-muted small fw-medium text-truncate d-block metric-label">Avg Progress</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Responsive Search & Filter Bar -->
<div class="card border-0 shadow-sm rounded-4 p-3 mb-4 search-filter-card bg-white">
    <form method="GET" action="<?= url('teacher/students.php') ?>" class="row g-2 g-md-3 align-items-center">
        <div class="col-12 col-md-5 col-lg-5">
            <div class="input-group">
                <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-search"></i></span>
                <input type="text" name="q" value="<?= e($searchQuery) ?>" class="form-control border-start-0 touch-target" placeholder="Search by name, email, or ID...">
            </div>
        </div>
        <div class="col-12 col-md-4 col-lg-4">
            <select name="course_id" class="form-select touch-target" onchange="this.form.submit()">
                <option value="0">All Taught Courses (<?= count($teacherCourses) ?>)</option>
                <?php foreach ($teacherCourses as $tc): ?>
                    <option value="<?= (int)$tc['id'] ?>" <?= $courseFilter === (int)$tc['id'] ? 'selected' : '' ?>><?= e($tc['title']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-3 col-lg-3 d-flex gap-2">
            <button type="submit" class="btn btn-primary rounded-pill flex-grow-1 fw-semibold touch-target">
                <i class="bi bi-funnel me-1"></i> Filter
            </button>
            <?php if ($courseFilter > 0 || !empty($searchQuery)): ?>
                <a href="<?= url('teacher/students.php') ?>" class="btn btn-outline-secondary rounded-pill px-3 touch-target d-flex align-items-center justify-content-center" title="Clear Filters">
                    <i class="bi bi-x-lg"></i>
                </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<?php if (!empty($students)): ?>
    <!-- ========================================== -->
    <!-- 1. MOBILE & IPAD/TABLET VIEW (Cards Layout) -->
    <!-- Displayed on screens < 992px               -->
    <!-- ========================================== -->
    <div class="d-lg-none">
        <div class="row g-3">
            <?php foreach ($students as $s): ?>
                <?php 
                $studentName = trim($s['first_name'] . ' ' . $s['last_name']);
                $studentAvatar = function_exists('get_avatar_url') ? get_avatar_url($s['avatar'] ?? null, $studentName) : ($s['avatar'] ?? ''); 
                $progVal = (float)$s['progress'];
                $progColor = $progVal >= 100 ? 'bg-success' : ($progVal > 40 ? 'bg-primary' : 'bg-warning');
                ?>
                <div class="col-12 col-md-6">
                    <div class="card student-mobile-card shadow-sm p-3 p-sm-4 h-100">
                        <!-- Student Header Info -->
                        <div class="d-flex align-items-start gap-3 mb-3">
                            <img src="<?= e($studentAvatar) ?>" class="rounded-circle border border-2 border-primary-subtle shadow-xs flex-shrink-0" style="width: 52px; height: 52px; object-fit: cover;" alt="<?= e($studentName) ?>" onerror="this.onerror=null; this.src='https://ui-avatars.com/api/?name=<?= urlencode($studentName) ?>&background=4f46e5&color=ffffff&bold=true';">
                            <div class="flex-grow-1 min-w-0">
                                <a href="<?= url('teacher/student-profile.php?user_id=' . (int)$s['student_user_id']) ?>" class="fw-bold text-main text-decoration-none d-block text-truncate fs-6">
                                    <?= e($studentName) ?>
                                </a>
                                <div class="d-flex align-items-center gap-1 flex-wrap mt-1">
                                    <span class="badge bg-light text-secondary border small"><?= e($s['student_number'] ?: 'STUDENT-' . $s['student_id']) ?></span>
                                    <?php if ($progVal >= 100): ?>
                                        <span class="badge bg-success-subtle text-success border border-success border-opacity-25 small">Graduated</span>
                                    <?php else: ?>
                                        <span class="badge bg-primary-subtle text-primary border border-primary border-opacity-25 small">Active Learner</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <a href="mailto:<?= e($s['email']) ?>" class="btn btn-sm btn-light border rounded-circle p-2 flex-shrink-0" title="Email Student">
                                <i class="bi bi-envelope-fill text-muted"></i>
                            </a>
                        </div>

                        <!-- Enrolled Course Badge -->
                        <div class="p-2 px-3 rounded-3 bg-light bg-opacity-75 mb-3 border">
                            <div class="fw-semibold text-main text-truncate small"><?= e($s['course_title']) ?></div>
                            <div class="small text-muted d-flex justify-content-between flex-wrap gap-1 mt-1" style="font-size: 0.75rem;">
                                <span><i class="bi bi-mortarboard me-1"></i><?= e($s['academic_level'] ?? 'Online') ?></span>
                                <span><i class="bi bi-calendar-event me-1"></i><?= date('M d, Y', strtotime($s['enrolled_at'])) ?></span>
                            </div>
                        </div>

                        <!-- Learning Progress Bar -->
                        <div class="mb-3">
                            <div class="d-flex align-items-center justify-content-between mb-1">
                                <span class="small fw-semibold text-main"><?= (int)$progVal ?>% completed</span>
                                <span class="small text-muted"><?= (int)$s['completed_lessons_count'] ?> lessons completed</span>
                            </div>
                            <div class="progress" style="height: 7px; border-radius: 10px;">
                                <div class="progress-bar <?= $progColor ?>" style="width: <?= $progVal ?>%;"></div>
                            </div>
                        </div>

                        <!-- 3-Pill Engagement Stats -->
                        <div class="d-flex gap-2 mb-3">
                            <div class="stat-pill">
                                <div class="fw-bold text-primary mb-0"><?= (int)$s['quiz_attempts_count'] ?></div>
                                <span class="text-muted d-block" style="font-size: 0.7rem;">Quizzes</span>
                            </div>
                            <div class="stat-pill">
                                <div class="fw-bold text-info mb-0"><?= (int)$s['submissions_count'] ?></div>
                                <span class="text-muted d-block" style="font-size: 0.7rem;">Tasks</span>
                            </div>
                            <div class="stat-pill">
                                <div class="fw-bold text-danger mb-0"><?= (int)$s['attendance_count'] ?></div>
                                <span class="text-muted d-block" style="font-size: 0.7rem;">Live</span>
                            </div>
                        </div>

                        <!-- Contact chips -->
                        <div class="small text-muted mb-3 d-flex flex-column gap-1" style="font-size: 0.8rem;">
                            <div class="text-truncate">
                                <i class="bi bi-envelope me-1 text-primary"></i> <a href="mailto:<?= e($s['email']) ?>" class="text-muted text-decoration-none"><?= e($s['email']) ?></a>
                            </div>
                            <?php if (!empty($s['phone'])): ?>
                                <div>
                                    <i class="bi bi-telephone me-1 text-success"></i> <a href="tel:<?= e($s['phone']) ?>" class="text-muted text-decoration-none"><?= e($s['phone']) ?></a>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($s['city']) || !empty($s['country'])): ?>
                                <div class="text-truncate">
                                    <i class="bi bi-geo-alt me-1 text-danger"></i> <?= e(trim(($s['city'] ?? '') . (!empty($s['city']) && !empty($s['country']) ? ', ' : '') . ($s['country'] ?? ''))) ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Card Action -->
                        <div class="mt-auto pt-2 border-top">
                            <a href="<?= url('teacher/student-profile.php?user_id=' . (int)$s['student_user_id']) ?>" class="btn btn-primary rounded-pill w-100 fw-semibold touch-target d-flex align-items-center justify-content-center">
                                <i class="bi bi-person-bounding-box me-2"></i> View Full Dossier &amp; Details
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- 2. DESKTOP VIEW (Full Data Table Layout)   -->
    <!-- Displayed on screens >= 992px              -->
    <!-- ========================================== -->
    <div class="d-none d-lg-block">
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4">Student</th>
                            <th>Enrolled Course</th>
                            <th>Contact &amp; Location</th>
                            <th>Learning Progress</th>
                            <th>Engagement</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $s): ?>
                            <?php 
                            $studentName = trim($s['first_name'] . ' ' . $s['last_name']);
                            $studentAvatar = function_exists('get_avatar_url') ? get_avatar_url($s['avatar'] ?? null, $studentName) : ($s['avatar'] ?? ''); 
                            $progVal = (float)$s['progress'];
                            $progColor = $progVal >= 100 ? 'bg-success' : ($progVal > 40 ? 'bg-primary' : 'bg-warning');
                            ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="d-flex align-items-center gap-3">
                                        <img src="<?= e($studentAvatar) ?>" class="rounded-circle border border-2 border-primary-subtle shadow-xs flex-shrink-0" style="width:44px; height:44px; object-fit:cover;" alt="<?= e($studentName) ?>" onerror="this.onerror=null; this.src='https://ui-avatars.com/api/?name=<?= urlencode($studentName) ?>&background=4f46e5&color=ffffff&bold=true';">
                                        <div>
                                            <a href="<?= url('teacher/student-profile.php?user_id=' . (int)$s['student_user_id']) ?>" class="fw-bold text-main text-decoration-none">
                                                <?= e($studentName) ?>
                                            </a>
                                            <div class="small text-muted">
                                                <span class="badge bg-light text-secondary border me-1"><?= e($s['student_number'] ?: 'STUDENT-' . $s['student_id']) ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="fw-semibold text-main"><?= e($s['course_title']) ?></div>
                                    <small class="text-muted d-block"><?= e($s['academic_level'] ?? 'Online') ?> &bull; Enrolled <?= date('M d, Y', strtotime($s['enrolled_at'])) ?></small>
                                </td>
                                <td>
                                    <div class="small text-main"><i class="bi bi-envelope me-1 text-primary"></i> <a href="mailto:<?= e($s['email']) ?>" class="text-main text-decoration-none"><?= e($s['email']) ?></a></div>
                                    <?php if (!empty($s['phone'])): ?>
                                        <div class="small text-muted"><i class="bi bi-telephone me-1 text-success"></i> <a href="tel:<?= e($s['phone']) ?>" class="text-muted text-decoration-none"><?= e($s['phone']) ?></a></div>
                                    <?php endif; ?>
                                    <?php if (!empty($s['city']) || !empty($s['country'])): ?>
                                        <div class="small text-muted"><i class="bi bi-geo-alt me-1 text-danger"></i> <?= e(trim(($s['city'] ?? '') . (!empty($s['city']) && !empty($s['country']) ? ', ' : '') . ($s['country'] ?? ''))) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td style="min-width: 170px;">
                                    <div class="d-flex align-items-center justify-content-between mb-1">
                                        <span class="small fw-semibold text-main"><?= (int)$progVal ?>% completed</span>
                                        <span class="small text-muted"><?= (int)$s['completed_lessons_count'] ?> lessons</span>
                                    </div>
                                    <div class="progress" style="height:6px; border-radius:10px;">
                                        <div class="progress-bar <?= $progColor ?>" style="width: <?= $progVal ?>%;"></div>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex flex-wrap gap-1">
                                        <span class="badge bg-primary-subtle text-primary border border-primary border-opacity-10 rounded-pill px-2 py-1 small" title="Quizzes Taken">
                                            <i class="bi bi-patch-question me-1"></i><?= (int)$s['quiz_attempts_count'] ?> quizzes
                                        </span>
                                        <span class="badge bg-info-subtle text-info border border-info border-opacity-10 rounded-pill px-2 py-1 small" title="Tasks Submitted">
                                            <i class="bi bi-file-earmark-check me-1"></i><?= (int)$s['submissions_count'] ?> tasks
                                        </span>
                                        <span class="badge bg-danger-subtle text-danger border border-danger border-opacity-10 rounded-pill px-2 py-1 small" title="Live Classes Attended">
                                            <i class="bi bi-broadcast me-1"></i><?= (int)$s['attendance_count'] ?> live
                                        </span>
                                    </div>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="btn-group">
                                        <a href="<?= url('teacher/student-profile.php?user_id=' . (int)$s['student_user_id']) ?>" class="btn btn-sm btn-primary rounded-pill px-3 fw-semibold shadow-xs">
                                            <i class="bi bi-person-bounding-box me-1"></i> Full Details
                                        </a>
                                        <a href="mailto:<?= e($s['email']) ?>" class="btn btn-sm btn-light border rounded-circle ms-1 p-2" title="Email Student">
                                            <i class="bi bi-envelope-fill text-muted"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="card border-0 shadow-sm rounded-4 p-4 p-sm-5 text-center my-4 bg-white">
        <div class="rounded-circle bg-primary bg-opacity-10 text-primary p-4 d-inline-block mx-auto mb-3 fs-2">
            <i class="bi bi-people"></i>
        </div>
        <h4 class="fw-bold">No Students Found</h4>
        <p class="text-muted max-w-md mx-auto mb-3">
            <?php if (!empty($searchQuery) || $courseFilter > 0): ?>
                No enrolled students matched your search criteria. Try clearing filters or searching for another student.
            <?php else: ?>
                Students who enroll in your courses will appear here with real-time course progress, quiz records, task submissions, and live attendance tracking.
            <?php endif; ?>
        </p>
        <?php if (!empty($searchQuery) || $courseFilter > 0): ?>
            <div>
                <a href="<?= url('teacher/students.php') ?>" class="btn btn-outline-primary rounded-pill px-4 fw-semibold touch-target d-inline-flex align-items-center">
                    <i class="bi bi-arrow-counterclockwise me-1"></i> Clear Filters
                </a>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>


