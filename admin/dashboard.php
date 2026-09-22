<?php

require_once dirname(__DIR__) . '/config/main.php';
require_once BASE_PATH . '/includes/functions/activity.php';
require_once BASE_PATH . '/includes/functions/announcements.php';

secure_page(ROLE_ADMIN);

$user = current_user();
$pdo  = getDBConnection();

$totalUsers          = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$newUsersThisWeek    = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();

$totalStudents       = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn();
$newStudentsThisMonth = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();

$totalTeachers       = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'teacher'")->fetchColumn();
$approvedTeachers    = (int)$pdo->query("SELECT COUNT(*) FROM teachers WHERE status IN ('approved', 'active')")->fetchColumn();
$pendingApplications = (int)$pdo->query("SELECT COUNT(*) FROM teacher_applications WHERE status = 'pending'")->fetchColumn();

$totalCourses        = (int)$pdo->query("SELECT COUNT(*) FROM courses")->fetchColumn();
$publishedCourses    = (int)$pdo->query("SELECT COUNT(*) FROM courses WHERE status = 'published'")->fetchColumn();
$totalLessons        = (int)$pdo->query("SELECT COUNT(*) FROM lessons WHERE status = 'published'")->fetchColumn();

$totalEnrollments    = (int)$pdo->query("SELECT COUNT(*) FROM enrollments WHERE status = 'active'")->fetchColumn();
$avgStudentProgress  = (float)$pdo->query("SELECT COALESCE(AVG(progress), 0) FROM enrollments WHERE status = 'active'")->fetchColumn();

$totalRevenue        = (float)$pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'successful'")->fetchColumn();
$revenueThisMonth    = (float)$pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'successful' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();
$successfulPaymentsCount = (int)$pdo->query("SELECT COUNT(*) FROM payments WHERE status = 'successful'")->fetchColumn();
$activeSubscriptions = (int)$pdo->query("SELECT COUNT(*) FROM subscriptions WHERE status = 'active'")->fetchColumn();

$totalQuizAttempts   = (int)$pdo->query("SELECT COUNT(*) FROM quiz_attempts")->fetchColumn();
$totalLiveClasses    = (int)$pdo->query("SELECT COUNT(*) FROM live_classes")->fetchColumn();

$recentUsers = $pdo->query("
    SELECT id, first_name, last_name, email, role, status, avatar, created_at
    FROM users
    ORDER BY created_at DESC
    LIMIT 6
")->fetchAll(PDO::FETCH_ASSOC);

$recentPayments = $pdo->query("
    SELECT p.id, p.amount, p.status, p.created_at,
           u.first_name, u.last_name, u.email
    FROM payments p
    LEFT JOIN users u ON u.id = p.user_id
    ORDER BY p.created_at DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

$topCourses = $pdo->query("
    SELECT c.id, c.title, c.slug, c.academic_level, c.price, c.status,
           COUNT(e.id) AS enrollment_count,
           CONCAT(u.first_name, ' ', u.last_name) AS teacher_name
    FROM courses c
    LEFT JOIN enrollments e ON e.course_id = c.id
    LEFT JOIN teachers t ON c.teacher_id = t.id
    LEFT JOIN users u ON t.user_id = u.id
    GROUP BY c.id
    ORDER BY enrollment_count DESC, c.id DESC
    LIMIT 4
")->fetchAll(PDO::FETCH_ASSOC);

$pendingAppsList = $pdo->query("
    SELECT ta.*, u.first_name, u.last_name, u.email, u.phone
    FROM teacher_applications ta
    JOIN users u ON ta.user_id = u.id
    WHERE ta.status = 'pending'
    ORDER BY ta.created_at DESC
    LIMIT 3
")->fetchAll(PDO::FETCH_ASSOC);

$recentActivities = $pdo->query("
    SELECT a.*, u.first_name, u.last_name, u.role, u.avatar
    FROM activity_logs a
    LEFT JOIN users u ON a.user_id = u.id
    ORDER BY a.created_at DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

$levelDistribution = $pdo->query("
    SELECT COALESCE(NULLIF(academic_level, ''), 'Unassigned Level') AS level_name, COUNT(*) AS total_courses
    FROM courses
    GROUP BY academic_level
    ORDER BY total_courses DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

$recentAdminAnns = $pdo->query("
    SELECT a.*, CONCAT(u.first_name, ' ', u.last_name) AS author_name
    FROM announcements a
    LEFT JOIN users u ON a.created_by = u.id
    ORDER BY a.created_at DESC
    LIMIT 3
")->fetchAll(PDO::FETCH_ASSOC);

include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<!-- Hero Banner Card -->
<div class="card border-0 shadow-sm rounded-4 mb-4 admin-hero-card position-relative overflow-hidden">
    <div class="admin-hero-glow"></div>
    <div class="card-body p-4 p-lg-5 text-white position-relative" style="z-index: 2;">
        <div class="row align-items-center g-4">
            <div class="col-lg-8">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                    <span class="badge bg-primary bg-opacity-25 text-primary border border-primary border-opacity-25 rounded-pill px-3 py-1 fw-bold">
                        <i class="bi bi-shield-check me-1"></i> SUPER ADMIN COMMAND CENTER
                    </span>
                    <span class="badge bg-success bg-opacity-25 text-success border border-success border-opacity-25 rounded-pill px-3 py-1 fw-bold">
                        <i class="bi bi-broadcast me-1"></i> Live Production Telemetry
                    </span>
                </div>
                <h1 class="display-6 fw-bold mb-2 text-white">Welcome back, <?= e($user['first_name'] ?: 'Administrator') ?>!</h1>
                <p class="text-white-50 fs-6 mb-0 col-xl-10">
                    Real-time operational dashboard monitoring <strong class="text-white"><?= number_format($totalUsers) ?> registered accounts</strong>, <strong class="text-white"><?= number_format($publishedCourses) ?> active courses</strong>, and <strong class="text-white">₦<?= number_format($totalRevenue, 2) ?></strong> in platform revenue.
                </p>
            </div>

            <div class="col-lg-4 text-lg-end">
                <div class="d-flex flex-wrap gap-2 justify-content-lg-end">
                    <a href="<?= url('admin/courses.php') ?>" class="btn btn-light rounded-pill px-4 py-2 fw-bold shadow-sm">
                        <i class="bi bi-collection-play-fill text-primary me-1"></i> Course Moderation
                    </a>
                    <a href="<?= url('admin/users.php') ?>" class="btn btn-outline-light rounded-pill px-4 py-2 fw-bold">
                        <i class="bi bi-people-fill me-1"></i> Manage Users
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Primary KPI Cards Row -->
<div class="row g-3 g-xl-4 mb-4">
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="admin-stat-card h-100">
            <div class="d-flex justify-content-between align-items-start mb-3">
                <div class="admin-stat-icon-wrapper bg-primary bg-opacity-10 text-primary">
                    <i class="bi bi-mortarboard-fill"></i>
                </div>
                <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 py-1 fw-semibold small">
                    <i class="bi bi-arrow-up-right me-1"></i> +<?= number_format($newStudentsThisMonth) ?> this mo
                </span>
            </div>
            <div>
                <span class="text-muted text-uppercase fw-semibold" style="font-size: 0.76rem; letter-spacing: 0.05em;">Total Enrolled Students</span>
                <h2 class="admin-stat-value my-1"><?= number_format($totalStudents) ?></h2>
                <div class="small text-muted mt-2 d-flex align-items-center gap-1">
                    <i class="bi bi-check2-circle text-primary"></i> <?= number_format($totalEnrollments) ?> active course enrollments
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
        <div class="admin-stat-card h-100">
            <div class="d-flex justify-content-between align-items-start mb-3">
                <div class="admin-stat-icon-wrapper bg-warning bg-opacity-10 text-warning">
                    <i class="bi bi-person-badge-fill"></i>
                </div>
                <?php if ($pendingApplications > 0): ?>
                    <a href="<?= url('admin/teacher-applications.php') ?>" class="badge bg-danger text-white rounded-pill px-3 py-1 fw-bold small text-decoration-none">
                        <?= $pendingApplications ?> Pending Review
                    </a>
                <?php else: ?>
                    <span class="badge bg-light text-muted border rounded-pill px-3 py-1 fw-semibold small">
                        All Verified
                    </span>
                <?php endif; ?>
            </div>
            <div>
                <span class="text-muted text-uppercase fw-semibold" style="font-size: 0.76rem; letter-spacing: 0.05em;">Verified Educators</span>
                <h2 class="admin-stat-value my-1"><?= number_format($totalTeachers) ?></h2>
                <div class="small text-muted mt-2 d-flex align-items-center gap-1">
                    <i class="bi bi-patch-check text-warning"></i> <?= number_format($approvedTeachers) ?> active course instructors
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
        <div class="admin-stat-card h-100">
            <div class="d-flex justify-content-between align-items-start mb-3">
                <div class="admin-stat-icon-wrapper bg-info bg-opacity-10 text-info">
                    <i class="bi bi-journal-code"></i>
                </div>
                <span class="badge bg-info bg-opacity-10 text-info rounded-pill px-3 py-1 fw-semibold small">
                    <?= number_format($totalLessons) ?> Lessons
                </span>
            </div>
            <div>
                <span class="text-muted text-uppercase fw-semibold" style="font-size: 0.76rem; letter-spacing: 0.05em;">Curriculum Catalog</span>
                <h2 class="admin-stat-value my-1"><?= number_format($totalCourses) ?></h2>
                <div class="small text-muted mt-2 d-flex align-items-center gap-1">
                    <i class="bi bi-collection-play text-info"></i> <?= number_format($publishedCourses) ?> published &amp; accredited
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
        <div class="admin-stat-card h-100">
            <div class="d-flex justify-content-between align-items-start mb-3">
                <div class="admin-stat-icon-wrapper bg-success bg-opacity-10 text-success">
                    <i class="bi bi-wallet2"></i>
                </div>
                <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 py-1 fw-semibold small">
                    <?= number_format($successfulPaymentsCount) ?> Paid Txns
                </span>
            </div>
            <div>
                <span class="text-muted text-uppercase fw-semibold" style="font-size: 0.76rem; letter-spacing: 0.05em;">Gross Platform Revenue</span>
                <h2 class="admin-stat-value my-1">₦<?= number_format($totalRevenue) ?></h2>
                <div class="small text-muted mt-2 d-flex align-items-center gap-1">
                    <i class="bi bi-graph-up text-success"></i> ₦<?= number_format($revenueThisMonth) ?> in last 30 days
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Secondary Quick-Stats Metric Strip -->
<div class="admin-quick-stats-bar mb-4">
    <div class="row g-3 g-md-4 align-items-center">
        <div class="col-6 col-lg-3 border-end border-opacity-10">
            <div class="admin-quick-stat-item">
                <div class="admin-quick-stat-icon bg-primary bg-opacity-10 text-primary">
                    <i class="bi bi-play-circle-fill"></i>
                </div>
                <div class="text-truncate">
                    <div class="fw-bold fs-5 text-main"><?= number_format($totalLessons) ?></div>
                    <div class="text-muted small text-truncate">Published Video Lessons</div>
                </div>
            </div>
        </div>

        <div class="col-6 col-lg-3 border-end border-opacity-10">
            <div class="admin-quick-stat-item">
                <div class="admin-quick-stat-icon bg-success bg-opacity-10 text-success">
                    <i class="bi bi-graph-up-arrow"></i>
                </div>
                <div class="text-truncate">
                    <div class="fw-bold fs-5 text-main"><?= number_format($avgStudentProgress, 1) ?>%</div>
                    <div class="text-muted small text-truncate">Avg. Completion Rate</div>
                </div>
            </div>
        </div>

        <div class="col-6 col-lg-3 border-end border-opacity-10">
            <div class="admin-quick-stat-item">
                <div class="admin-quick-stat-icon bg-warning bg-opacity-10 text-warning">
                    <i class="bi bi-patch-question-fill"></i>
                </div>
                <div class="text-truncate">
                    <div class="fw-bold fs-5 text-main"><?= number_format($totalQuizAttempts) ?></div>
                    <div class="text-muted small text-truncate">Completed CBT Tests</div>
                </div>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="admin-quick-stat-item">
                <div class="admin-quick-stat-icon bg-danger bg-opacity-10 text-danger">
                    <i class="bi bi-broadcast"></i>
                </div>
                <div class="text-truncate">
                    <div class="fw-bold fs-5 text-main"><?= number_format($totalLiveClasses) ?></div>
                    <div class="text-muted small text-truncate">Live Classes Hosted</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Main Content Grid -->
<div class="row g-4 mb-4">
    <!-- Left Column: Tables & Feeds -->
    <div class="col-lg-8">
        <!-- Recent Users Table -->
        <div class="card admin-table-card mb-4">
            <div class="card-header bg-transparent border-0 p-3 p-md-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h5 class="fw-bold mb-1 text-main">Recent User Registrations</h5>
                    <p class="text-muted small mb-0">Newly enrolled students and registered educators on StudyMe.</p>
                </div>
                <a href="<?= url('admin/users.php') ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-bold">
                    View All Users &rarr;
                </a>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 table-hover-custom">
                    <thead class="bg-surface-alt text-muted" style="font-size: 0.76rem; text-transform: uppercase; letter-spacing: 0.05em;">
                        <tr>
                            <th class="ps-3 ps-md-4 py-3">Account</th>
                            <th class="py-3">Role</th>
                            <th class="py-3">Status</th>
                            <th class="pe-3 pe-md-4 py-3 text-end">Registered Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <?php foreach ($recentUsers as $u):
                            $avatar = function_exists('get_avatar_url') ? get_avatar_url($u['avatar'] ?? null, $u['first_name']) : ($u['avatar'] ?? '');
                            $roleClass = match($u['role']) {
                                'admin' => 'bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25',
                                'teacher' => 'bg-warning bg-opacity-10 text-warning border border-warning border-opacity-50',
                                default => 'bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25'
                            };
                        ?>
                            <tr>
                                <td class="ps-3 ps-md-4 py-3">
                                    <div class="d-flex align-items-center gap-3">
                                        <img src="<?= e($avatar) ?>" alt="<?= htmlspecialchars($u['first_name']) ?>" class="rounded-circle flex-shrink-0" style="width: 38px; height: 38px; object-fit: cover;" onerror="this.onerror=null; this.src='https://ui-avatars.com/api/?name=<?= urlencode($u['first_name']) ?>&background=4f46e5&color=ffffff&bold=true';">
                                        <div class="min-w-0">
                                            <div class="fw-bold text-main text-truncate"><?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?></div>
                                            <div class="text-muted small text-truncate"><?= htmlspecialchars($u['email']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3">
                                    <span class="badge <?= $roleClass ?> rounded-pill px-3 py-1 fw-bold text-capitalize">
                                        <?= htmlspecialchars($u['role']) ?>
                                    </span>
                                </td>
                                <td class="py-3">
                                    <span class="badge rounded-pill px-2 py-1 <?= $u['status'] === 'active' ? 'bg-success bg-opacity-10 text-success' : 'bg-secondary bg-opacity-10 text-secondary' ?> fw-semibold">
                                        <i class="bi bi-circle-fill me-1" style="font-size: 0.45rem;"></i> <?= ucfirst($u['status']) ?>
                                    </span>
                                </td>
                                <td class="pe-3 pe-md-4 py-3 text-end text-muted small whitespace-nowrap">
                                    <?= date('M j, Y', strtotime($u['created_at'])) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($recentUsers)): ?>
                            <tr>
                                <td colspan="4" class="text-center py-4 text-muted">No users registered yet.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Top Enrolled Courses -->
        <div class="card admin-table-card mb-4 p-3 p-md-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="fw-bold mb-1 text-main">Top Enrolled Courses</h5>
                    <p class="text-muted small mb-0">Highest student engagement across faculties.</p>
                </div>
                <a href="<?= url('admin/courses.php') ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-bold">
                    Catalog &rarr;
                </a>
            </div>

            <div class="row g-3">
                <?php foreach ($topCourses as $tc): ?>
                    <div class="col-12 col-md-6">
                        <div class="p-3 rounded-4 border bg-surface h-100 d-flex flex-column justify-content-between">
                            <div>
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <span class="badge bg-light text-muted border rounded-pill px-2 py-1 small">
                                        <?= htmlspecialchars($tc['academic_level'] ?: '100 Level') ?>
                                    </span>
                                    <span class="badge bg-primary bg-opacity-10 text-primary fw-bold rounded-pill px-2 py-1">
                                        <?= (int)$tc['enrollment_count'] ?> Students
                                    </span>
                                </div>
                                <h6 class="fw-bold text-main mb-1"><?= htmlspecialchars($tc['title']) ?></h6>
                                <p class="text-muted small mb-3">Instructor: <?= htmlspecialchars($tc['teacher_name'] ?: 'Unassigned') ?></p>
                            </div>
                            <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                                <span class="fw-bold text-main">₦<?= number_format($tc['price']) ?></span>
                                <a href="<?= url('courses/details.php?id=' . $tc['id']) ?>" class="small text-primary text-decoration-none fw-bold">
                                    View Syllabus &rarr;
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Live Activity Telemetry -->
        <div class="card admin-table-card p-3 p-md-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="fw-bold mb-1 text-main">
                        <i class="bi bi-activity text-primary me-2"></i>Live Activity Telemetry
                    </h5>
                    <p class="text-muted small mb-0">Audit log of logins, lesson views, quiz attempts, and system actions.</p>
                </div>
                <a href="<?= url('admin/activity.php') ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-3 fw-bold">
                    Telemetry Logs
                </a>
            </div>

            <div>
                <?php foreach ($recentActivities as $act):
                    $actUser = trim(($act['first_name'] ?? '') . ' ' . ($act['last_name'] ?? '')) ?: 'User';
                    $badgeBg = match($act['role'] ?? '') {
                        'admin' => 'bg-danger',
                        'teacher' => 'bg-warning text-dark',
                        default => 'bg-primary'
                    };
                ?>
                    <div class="admin-telemetry-item">
                        <div class="d-flex align-items-center gap-3 min-w-0">
                            <div class="rounded-circle p-2 bg-primary bg-opacity-10 text-primary flex-shrink-0">
                                <i class="bi bi-lightning-charge-fill"></i>
                            </div>
                            <div class="min-w-0">
                                <div class="fw-bold text-main small text-truncate">
                                    <?= htmlspecialchars($actUser) ?>
                                    <span class="badge <?= $badgeBg ?> rounded-pill ms-1" style="font-size: 0.65rem;">
                                        <?= strtoupper($act['role'] ?? 'USER') ?>
                                    </span>
                                </div>
                                <div class="text-muted small text-truncate"><?= htmlspecialchars($act['description']) ?></div>
                            </div>
                        </div>
                        <div class="text-muted small whitespace-nowrap flex-shrink-0 text-end">
                            <?= date('M j @ g:i A', strtotime($act['created_at'])) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($recentActivities)): ?>
                    <p class="text-muted small mb-0 py-3 text-center">No platform activity recorded yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Right Column: Widgets -->
    <div class="col-lg-4">
        <!-- Recent Payments Ledger Widget -->
        <div class="card admin-table-card mb-4 p-3 p-md-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold mb-0 text-main">Recent Payments</h5>
                <a href="<?= url('admin/payments.php') ?>" class="small text-decoration-none fw-bold">Ledger &rarr;</a>
            </div>

            <div class="list-group list-group-flush">
                <?php foreach ($recentPayments as $pay):
                    $payUser = trim(($pay['first_name'] ?? '') . ' ' . ($pay['last_name'] ?? '')) ?: 'Student';
                    $statusClass = match($pay['status']) {
                        'successful' => 'text-success',
                        'pending' => 'text-warning',
                        default => 'text-danger'
                    };
                ?>
                    <div class="list-group-item px-0 py-2 border-light d-flex justify-content-between align-items-center bg-transparent">
                        <div class="min-w-0">
                            <div class="fw-bold text-main small text-truncate"><?= htmlspecialchars($payUser) ?></div>
                            <div class="text-muted" style="font-size: 0.75rem;"><?= date('M j, Y @ g:i A', strtotime($pay['created_at'])) ?></div>
                        </div>
                        <div class="text-end flex-shrink-0 ps-2">
                            <div class="fw-bold text-main">₦<?= number_format($pay['amount']) ?></div>
                            <span class="small fw-semibold <?= $statusClass ?>">
                                <i class="bi bi-circle-fill" style="font-size: 0.45rem;"></i> <?= ucfirst($pay['status']) ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($recentPayments)): ?>
                    <p class="text-muted small mb-0 py-3 text-center">No payments recorded yet.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Teacher Applications Review Widget -->
        <div class="card admin-table-card mb-4 p-3 p-md-4 border-top border-4 border-warning">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h6 class="fw-bold mb-0 text-main">Teacher Applications</h6>
                    <small class="text-muted">Educator onboarding review</small>
                </div>
                <span class="badge bg-warning text-dark rounded-pill px-2 py-1 small"><?= $pendingApplications ?> Pending</span>
            </div>

            <?php if (!empty($pendingAppsList)): ?>
                <div class="list-group list-group-flush mb-3">
                    <?php foreach ($pendingAppsList as $app): ?>
                        <div class="list-group-item px-0 py-2 border-light bg-transparent">
                            <div class="fw-bold text-main small"><?= htmlspecialchars($app['first_name'] . ' ' . $app['last_name']) ?></div>
                            <div class="text-muted small mb-1"><?= htmlspecialchars($app['qualification']) ?></div>
                            <a href="<?= url('admin/teacher-applications.php?id=' . $app['id']) ?>" class="btn btn-xs btn-outline-primary rounded-pill px-3 py-1 fw-bold" style="font-size: 0.75rem;">
                                Review Application &rarr;
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-3 text-muted small">
                    <i class="bi bi-check2-circle text-success fs-3 d-block mb-1"></i>
                    All teacher applications have been reviewed!
                </div>
            <?php endif; ?>

            <a href="<?= url('admin/teacher-applications.php') ?>" class="btn btn-light w-100 rounded-pill fw-bold btn-sm">
                Open Applications Manager
            </a>
        </div>

        <!-- Courses by Academic Level Widget -->
        <div class="card admin-table-card mb-4 p-3 p-md-4">
            <h6 class="fw-bold mb-3 text-main">Courses by Academic Level</h6>
            <div class="d-flex flex-column gap-3">
                <?php foreach ($levelDistribution as $ld):
                    $pct = ($totalCourses > 0) ? round(($ld['total_courses'] / $totalCourses) * 100) : 0;
                ?>
                    <div>
                        <div class="d-flex justify-content-between small fw-semibold text-muted mb-1">
                            <span class="text-truncate"><?= htmlspecialchars($ld['level_name']) ?></span>
                            <span class="flex-shrink-0"><?= (int)$ld['total_courses'] ?> (<?= $pct ?>%)</span>
                        </div>
                        <div class="progress" style="height: 6px; border-radius: 10px;">
                            <div class="progress-bar bg-primary" role="progressbar" style="width: <?= $pct ?>%"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Announcements Widget -->
        <div class="card admin-table-card p-3 p-md-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="fw-bold mb-0 text-main"><i class="bi bi-megaphone-fill text-warning me-1"></i>Announcements</h6>
                <a href="<?= url('admin/announcements.php') ?>" class="small text-decoration-none fw-bold">Manage &rarr;</a>
            </div>

            <?php if (!empty($recentAdminAnns)): ?>
                <div class="list-group list-group-flush mb-3">
                    <?php foreach ($recentAdminAnns as $ann): ?>
                        <div class="list-group-item px-0 py-2 border-light bg-transparent">
                            <div class="fw-bold text-main small mb-1"><?= htmlspecialchars($ann['title']) ?></div>
                            <div class="text-muted" style="font-size: 0.75rem;">
                                <?= date('M j, Y', strtotime($ann['created_at'])) ?> · Target: <span class="text-capitalize"><?= htmlspecialchars($ann['target_type']) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-muted small mb-3">No active broadcast notices.</p>
            <?php endif; ?>

            <a href="<?= url('admin/announcements.php') ?>" class="btn btn-outline-primary btn-sm rounded-pill w-100 fw-bold">
                <i class="bi bi-plus-circle me-1"></i> Create Announcement
            </a>
        </div>
    </div>
</div>

<!-- Executive Command Modules Grid -->
<div class="card admin-table-card p-3 p-md-4 mb-4">
    <h5 class="fw-bold mb-3 text-main">Executive Command Modules</h5>
    <div class="row g-3">
        <div class="col-6 col-md-4 col-xl-2">
            <a href="<?= url('admin/users.php') ?>" class="admin-command-tile">
                <div class="admin-command-icon bg-primary bg-opacity-10 text-primary">
                    <i class="bi bi-people-fill"></i>
                </div>
                <span class="fw-bold text-main small d-block">Users &amp; Roles</span>
                <span class="text-muted" style="font-size: 0.72rem;"><?= number_format($totalUsers) ?> Records</span>
            </a>
        </div>

        <div class="col-6 col-md-4 col-xl-2">
            <a href="<?= url('admin/courses.php') ?>" class="admin-command-tile">
                <div class="admin-command-icon bg-success bg-opacity-10 text-success">
                    <i class="bi bi-collection-play-fill"></i>
                </div>
                <span class="fw-bold text-main small d-block">Course Builder</span>
                <span class="text-muted" style="font-size: 0.72rem;"><?= number_format($totalCourses) ?> Courses</span>
            </a>
        </div>

        <div class="col-6 col-md-4 col-xl-2">
            <a href="<?= url('admin/payments.php') ?>" class="admin-command-tile">
                <div class="admin-command-icon bg-warning bg-opacity-10 text-warning">
                    <i class="bi bi-credit-card-fill"></i>
                </div>
                <span class="fw-bold text-main small d-block">Financial Ledger</span>
                <span class="text-muted" style="font-size: 0.72rem;">₦<?= number_format($totalRevenue) ?></span>
            </a>
        </div>

        <div class="col-6 col-md-4 col-xl-2">
            <a href="<?= url('admin/subscriptions.php') ?>" class="admin-command-tile">
                <div class="admin-command-icon bg-danger bg-opacity-10 text-danger">
                    <i class="bi bi-award-fill"></i>
                </div>
                <span class="fw-bold text-main small d-block">Subscriptions</span>
                <span class="text-muted" style="font-size: 0.72rem;"><?= number_format($activeSubscriptions) ?> Active</span>
            </a>
        </div>

        <div class="col-6 col-md-4 col-xl-2">
            <a href="<?= url('admin/analytics.php') ?>" class="admin-command-tile">
                <div class="admin-command-icon bg-info bg-opacity-10 text-info">
                    <i class="bi bi-graph-up-arrow"></i>
                </div>
                <span class="fw-bold text-main small d-block">Analytics Suite</span>
                <span class="text-muted" style="font-size: 0.72rem;">Live Metrics</span>
            </a>
        </div>

        <div class="col-6 col-md-4 col-xl-2">
            <a href="<?= url('admin/ai-settings.php') ?>" class="admin-command-tile">
                <div class="admin-command-icon bg-purple bg-opacity-10" style="color: #8b5cf6;">
                    <i class="bi bi-robot"></i>
                </div>
                <span class="fw-bold text-main small d-block">AI Engine</span>
                <span class="text-muted" style="font-size: 0.72rem;">Gemini / Claude</span>
            </a>
        </div>
    </div>
</div>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>

