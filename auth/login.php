<?php

require_once dirname(__DIR__) . '/config/main.php';

if (is_logged_in()) {
    $role = current_user_role();

    if (!empty($_SESSION['pending_course_id']) && !(defined('FREE_TESTING_MODE') && FREE_TESTING_MODE)) {
        $pendingId = (int)$_SESSION['pending_course_id'];
        if ($role === 'student') {
            redirect('payments/checkout.php?course_id=' . $pendingId);
        }
    }
    if ($role === 'admin') {
        redirect('admin/dashboard.php');
    } elseif ($role === 'teacher') {
        redirect('teacher/dashboard.php');
    } else {
        redirect('student/dashboard.php');
    }
}

$errors     = [];
$identifier = trim($_GET['identifier'] ?? ($_GET['email'] ?? ''));

if (is_post()) {
    $identifier = trim($_POST['identifier'] ?? '');
    $password   = $_POST['password'] ?? '';

    if (empty($identifier) || empty($password)) {
        $errors[] = 'Please enter your email/username and password.';
    } else {
        $pdo  = getDBConnection();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE (LOWER(TRIM(email)) = LOWER(TRIM(?)) OR username = ?) LIMIT 1");
        $stmt->execute([$identifier, $identifier]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {

            if ($user['status'] === 'inactive' || $user['status'] === 'suspended') {
                $errors[] = 'Your account has been deactivated. Please contact support.';
            } else {

                $_SESSION[SESSION_USER_ID]   = $user['id'];
                $_SESSION[SESSION_USER_ROLE] = $user['role'];
                $_SESSION[SESSION_USER_DATA] = [
                    'id'         => $user['id'],
                    'first_name' => $user['first_name'],
                    'last_name'  => $user['last_name'],
                    'email'      => $user['email'],
                    'role'       => $user['role'],
                ];

                if (function_exists('session_regenerate_id')) {
                    session_regenerate_id(true);
                }

                $pdo->prepare("UPDATE users SET last_login_at = NOW(), status = IF(status='pending','active',status) WHERE id = ?")
                    ->execute([$user['id']]);

                if (function_exists('log_user_activity')) {
                    log_user_activity(
                        $user['id'],
                        'login',
                        'User logged in from ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown')
                    );
                }

                $_SESSION['auth_success_vibrate'] = true;
                set_flash('success', 'Welcome back, ' . $user['first_name'] . '!');

                $pendingCourseId = (int)($_SESSION['pending_course_id'] ?? 0);

                if ($user['role'] === 'admin') {
                    unset($_SESSION['pending_course_id'], $_SESSION['pending_course_slug'], $_SESSION['pending_course_title']);
                    redirect('admin/dashboard.php');
                } elseif ($user['role'] === 'teacher') {
                    unset($_SESSION['pending_course_id'], $_SESSION['pending_course_slug'], $_SESSION['pending_course_title']);
                    redirect('teacher/dashboard.php');
                } else {

                    if (defined('FREE_TESTING_MODE') && FREE_TESTING_MODE) {

                        $stmtSt = $pdo->prepare("SELECT id FROM students WHERE user_id = ? LIMIT 1");
                        $stmtSt->execute([$user['id']]);
                        $sRow = $stmtSt->fetch(PDO::FETCH_ASSOC);
                        $studentId = $sRow ? (int)$sRow['id'] : 0;
                        if (!$studentId) {
                            $sNum = 'STD-' . date('Y') . '-' . str_pad($user['id'], 4, '0', STR_PAD_LEFT);
                            $pdo->prepare("INSERT IGNORE INTO students (user_id, student_number, student_type, academic_level, target_exam) VALUES (?, ?, 'university', '100 Level / Undergraduate', 'Degree Programme')")
                                ->execute([$user['id'], $sNum]);
                            $studentId = (int)$pdo->lastInsertId();
                        }

                        if ($pendingCourseId > 0 && $studentId > 0) {
                            $pdo->prepare("INSERT INTO enrollments (student_id, course_id, status, progress, enrolled_at) VALUES (?, ?, 'active', 0.00, NOW()) ON DUPLICATE KEY UPDATE status = 'active'")
                                ->execute([$studentId, $pendingCourseId]);
                            unset($_SESSION['pending_course_id'], $_SESSION['pending_course_slug'], $_SESSION['pending_course_title']);
                        }
                        $studentCategoryStmt = $pdo->prepare("SELECT 1 FROM students s JOIN enrollments e ON e.student_id = s.id AND e.status = 'active' JOIN courses c ON c.id = e.course_id JOIN categories cat ON cat.id = c.category_id WHERE s.user_id = ? AND cat.slug IN ('secondary-waec-neco', 'secondary') LIMIT 1");
                        $studentCategoryStmt->execute([$user['id']]);
                        redirect($studentCategoryStmt->fetchColumn() ? 'student/secondary-dashboard.php' : 'student/dashboard.php');
                    } else {

                        if ($pendingCourseId > 0) {
                            $stmtSt = $pdo->prepare("SELECT id FROM students WHERE user_id = ? LIMIT 1");
                            $stmtSt->execute([$user['id']]);
                            if (!$stmtSt->fetch()) {
                                $sNum = 'STD-' . date('Y') . '-' . str_pad($user['id'], 4, '0', STR_PAD_LEFT);
                                $pdo->prepare("INSERT IGNORE INTO students (user_id, student_number, student_type, academic_level, target_exam) VALUES (?, ?, 'university', '100 Level / Undergraduate', 'Degree Programme')")
                                    ->execute([$user['id'], $sNum]);
                            }
                            redirect('student/profile.php?enroll=1');
                        } else {
                            $studentCategoryStmt = $pdo->prepare("SELECT 1 FROM students s JOIN enrollments e ON e.student_id = s.id AND e.status = 'active' JOIN courses c ON c.id = e.course_id JOIN categories cat ON cat.id = c.category_id WHERE s.user_id = ? AND cat.slug IN ('secondary-waec-neco', 'secondary') LIMIT 1");
                            $studentCategoryStmt->execute([$user['id']]);
                            redirect($studentCategoryStmt->fetchColumn() ? 'student/secondary-dashboard.php' : 'student/dashboard.php');
                        }
                    }
                }
            }
        } else {
            $errors[] = 'Invalid email/username or password. Please try again.';
        }
    }
}

$pendingTitle = $_SESSION['pending_course_title'] ?? '';

$seo_options = [
    'title'       => 'Sign In | StudyMe AI Learning Platform',
    'description' => 'Log in to your StudyMe student account to access interactive courses, live classes, and AI tutor.'
];

include BASE_PATH . '/includes/layouts/header.php';
?>

<div class="auth-page-wrapper py-4 py-md-5 bg-light-subtle position-relative overflow-hidden d-flex align-items-center" style="min-height: calc(100vh - 120px);">
    <div class="auth-bg-blob auth-bg-blob-1"></div>
    <div class="auth-bg-blob auth-bg-blob-2"></div>

    <div class="container position-relative py-2 py-sm-3">
        <div class="row justify-content-center">
            <div class="col-12 col-sm-10 col-md-8 col-lg-6 col-xl-5">

                <?php if (!empty($pendingTitle)): ?>
                <div class="alert alert-info rounded-4 mb-3 border-0 shadow-sm p-3 small d-flex align-items-center gap-2">
                    <i class="bi bi-bookmark-star-fill text-primary fs-5"></i>
                    <div>
                        <strong>Course selected:</strong> <?= e($pendingTitle) ?> — Sign in to proceed.
                    </div>
                </div>
                <?php endif; ?>

                <div class="card auth-card border-0 shadow-lg rounded-4 p-3 p-sm-4 p-md-5 bg-white position-relative">
                    <div class="text-center mb-4">
                        <div class="auth-icon-box bg-primary bg-opacity-10 text-primary rounded-4 d-inline-flex align-items-center justify-content-center mb-3 shadow-sm"
                             style="width: 64px; height: 64px;">
                            <i class="bi bi-person-fill-lock fs-2"></i>
                        </div>
                        <h2 class="fw-extrabold text-dark mb-1" style="font-size: 1.65rem;">Welcome Back</h2>
                        <p class="text-muted small mb-0">Sign in to your StudyMe AI learning account.</p>
                    </div>

                    <?php foreach (get_flash() as $type => $messages): ?>
                        <?php foreach ($messages as $msg): ?>
                            <div class="alert alert-<?= $type === 'error' ? 'danger' : ($type === 'info' ? 'info' : 'success') ?> rounded-4 mb-3 p-3 border-0 shadow-sm d-flex align-items-center gap-2">
                                <i class="bi <?= $type === 'error' ? 'bi-exclamation-circle-fill text-danger' : ($type === 'info' ? 'bi-info-circle-fill text-info' : 'bi-check-circle-fill text-success') ?> fs-5"></i>
                                <div class="small fw-semibold"><?= $msg ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endforeach; ?>

                    <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger rounded-4 mb-4 border-0 shadow-sm p-3">
                        <div class="d-flex align-items-start gap-2">
                            <i class="bi bi-exclamation-triangle-fill text-danger fs-5 flex-shrink-0 mt-1"></i>
                            <div class="flex-grow-1">
                                <ul class="mb-0 ps-3 small">
                                    <?php foreach ($errors as $err): ?>
                                    <li class="py-1"><?= $err ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <form action="<?= url('auth/login.php') ?>" method="POST" id="loginForm" novalidate>
                        <div class="mb-3">
                            <label for="identifier" class="form-label small fw-bold text-dark">Email or Username <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-person"></i></span>
                                <input type="text" name="identifier" id="identifier" class="form-control form-control-lg fs-6 border-start-0 py-2"
                                       value="<?= e($identifier) ?>" required placeholder="you@example.com" autocomplete="username">
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label for="password" class="form-label small fw-bold text-dark mb-0">Password <span class="text-danger">*</span></label>
                                <a href="<?= url('auth/forgot-password.php') ?>" class="small text-primary text-decoration-none fw-semibold">Forgot password?</a>
                            </div>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-shield-lock"></i></span>
                                <input type="password" name="password" id="password" class="form-control form-control-lg fs-6 border-start-0 border-end-0 py-2"
                                       required placeholder="Enter your password" autocomplete="current-password">
                                <button type="button" class="btn btn-outline-secondary border-start-0 bg-light text-muted" onclick="togglePasswordVisibility('password', this)" title="Toggle password visibility">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="submit-box-wrapper mb-3">
                            <button type="submit" class="btn btn-primary btn-lg rounded-pill w-100 py-3 fw-bold shadow-sm d-flex align-items-center justify-content-center gap-2 hover-lift"
                                    data-feedback="success">
                                <span>Sign In</span>
                                <i class="bi bi-box-arrow-in-right fs-5"></i>
                            </button>
                        </div>

                        <div class="text-center py-2 border-top border-light-subtle">
                            <span class="text-muted small">Don't have an account?</span>
                            <a href="<?= url('auth/register.php') ?>" class="text-primary fw-bold text-decoration-none small ms-1 hover-underline">
                                Register here <i class="bi bi-arrow-right-short"></i>
                            </a>
                        </div>
                    </form>

                    <div class="mt-4 pt-3 border-top border-light-subtle">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="fw-bold small text-muted text-uppercase" style="font-size: 0.72rem;">
                                <i class="bi bi-lightning-charge-fill text-warning me-1"></i> Quick 1-Click Demo Login
                            </span>
                            <span class="badge bg-light text-muted border small">Pass: Password123!</span>
                        </div>
                        <div class="row g-2">
                            <div class="col-4">
                                <button type="button" class="btn btn-sm btn-outline-primary w-100 rounded-3 py-2 fw-semibold demo-pill-btn" onclick="fillDemo('student@studyme.ng', 'Password123!')">
                                    <i class="bi bi-mortarboard d-block mb-1 fs-5 text-primary"></i>
                                    <span class="small d-block" style="font-size: 0.75rem;">Student</span>
                                </button>
                            </div>
                            <div class="col-4">
                                <button type="button" class="btn btn-sm btn-outline-warning text-dark w-100 rounded-3 py-2 fw-semibold demo-pill-btn" onclick="fillDemo('teacher@studyme.ng', 'Password123!')">
                                    <i class="bi bi-person-workspace d-block mb-1 fs-5 text-warning"></i>
                                    <span class="small d-block" style="font-size: 0.75rem;">Teacher</span>
                                </button>
                            </div>
                            <div class="col-4">
                                <button type="button" class="btn btn-sm btn-outline-dark w-100 rounded-3 py-2 fw-semibold demo-pill-btn" onclick="fillDemo('admin@studyme.ng', 'Password123!')">
                                    <i class="bi bi-shield-lock d-block mb-1 fs-5 text-dark"></i>
                                    <span class="small d-block" style="font-size: 0.75rem;">Admin</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-3 px-2">
                    <a href="<?= url('index.php') ?>" class="small text-muted text-decoration-none">
                        <i class="bi bi-arrow-left me-1"></i> Back to Homepage
                    </a>
                    <a href="<?= url('auth/teacher-login.php') ?>" class="small text-primary fw-semibold text-decoration-none">
                        <i class="bi bi-person-workspace me-1"></i> Teacher Portal Sign In
                    </a>
                </div>

            </div>
        </div>
    </div>
</div>

<script>
function togglePasswordVisibility(inputId, btn) {
    const input = document.getElementById(inputId);
    const icon = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('bi-eye');
        icon.classList.add('bi-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('bi-eye-slash');
        icon.classList.add('bi-eye');
    }
}

function fillDemo(email, pass) {
    const idInput = document.getElementById('identifier');
    const passInput = document.getElementById('password');
    if (idInput && passInput) {
        idInput.value = email;
        passInput.value = pass;
        idInput.classList.add('is-valid');
        passInput.classList.add('is-valid');
        if (typeof StudyMeFeedback !== 'undefined') {
            StudyMeFeedback.click();
        }
    }
}
</script>

<?php include BASE_PATH . '/includes/layouts/footer.php'; ?>
