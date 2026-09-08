<?php
/**
 * StudyMe AI Platform - User Login with Course Preservation
 */
require_once dirname(__DIR__) . '/config/main.php';

if (is_logged_in()) {
    $role = current_user_role();
    // In normal mode, redirect to pending course checkout if set; in FREE_TESTING_MODE, open dashboard directly
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
$identifier = '';

if (is_post()) {
    $identifier = trim($_POST['identifier'] ?? '');
    $password   = $_POST['password'] ?? '';

    if (empty($identifier) || empty($password)) {
        $errors[] = 'Please enter your email/username and password.';
    } else {
        $pdo  = getDBConnection();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE (email = ? OR username = ?) LIMIT 1");
        $stmt->execute([$identifier, $identifier]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            // Check account status
            if ($user['status'] === 'inactive' || $user['status'] === 'suspended') {
                $errors[] = 'Your account has been deactivated. Please contact support.';
            } else {
                // Setup session
                $_SESSION[SESSION_USER_ID]   = $user['id'];
                $_SESSION[SESSION_USER_ROLE] = $user['role'];
                $_SESSION[SESSION_USER_DATA] = [
                    'id'         => $user['id'],
                    'first_name' => $user['first_name'],
                    'last_name'  => $user['last_name'],
                    'email'      => $user['email'],
                    'role'       => $user['role'],
                ];

                // Regenerate session id on successful login to prevent fixation
                if (function_exists('session_regenerate_id')) {
                    session_regenerate_id(true);
                }

                // Update last login
                $pdo->prepare("UPDATE users SET last_login_at = NOW(), status = IF(status='pending','active',status) WHERE id = ?")
                    ->execute([$user['id']]);

                // Track login activity
                if (function_exists('log_user_activity')) {
                    log_user_activity(
                        $user['id'],
                        'login',
                        'User logged in from ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown')
                    );
                }

                $_SESSION['auth_success_vibrate'] = true;
                set_flash('success', 'Welcome back, ' . $user['first_name'] . '!');

                // ── Preserve pending course ─────────────────────
                $pendingCourseId = (int)($_SESSION['pending_course_id'] ?? 0);

                if ($user['role'] === 'admin') {
                    unset($_SESSION['pending_course_id'], $_SESSION['pending_course_slug'], $_SESSION['pending_course_title']);
                    redirect('admin/dashboard.php');
                } elseif ($user['role'] === 'teacher') {
                    unset($_SESSION['pending_course_id'], $_SESSION['pending_course_slug'], $_SESSION['pending_course_title']);
                    redirect('teacher/dashboard.php');
                } else {
                    // Student: In FREE_TESTING_MODE, open dashboard directly without payment redirect
                    if (defined('FREE_TESTING_MODE') && FREE_TESTING_MODE) {
                        // Ensure student record exists
                        $stmtSt = $pdo->prepare("SELECT id FROM students WHERE user_id = ? LIMIT 1");
                        $stmtSt->execute([$user['id']]);
                        $sRow = $stmtSt->fetch(PDO::FETCH_ASSOC);
                        $studentId = $sRow ? (int)$sRow['id'] : 0;
                        if (!$studentId) {
                            $sNum = 'STD-' . date('Y') . '-' . str_pad($user['id'], 4, '0', STR_PAD_LEFT);
                            $pdo->prepare("INSERT IGNORE INTO students (user_id, student_number) VALUES (?, ?)")
                                ->execute([$user['id'], $sNum]);
                            $studentId = (int)$pdo->lastInsertId();
                        }
                        // If they had a pending course, activate free enrollment
                        if ($pendingCourseId > 0 && $studentId > 0) {
                            $pdo->prepare("INSERT INTO enrollments (student_id, course_id, status, progress, enrolled_at) VALUES (?, ?, 'active', 0.00, NOW()) ON DUPLICATE KEY UPDATE status = 'active'")
                                ->execute([$studentId, $pendingCourseId]);
                            unset($_SESSION['pending_course_id'], $_SESSION['pending_course_slug'], $_SESSION['pending_course_title']);
                        }
                        redirect('student/dashboard.php');
                    } else {
                        // Normal Paid Mode: redirect to profile setup/checkout if pending course
                        if ($pendingCourseId > 0) {
                            // Verify student record exists
                            $stmtSt = $pdo->prepare("SELECT id FROM students WHERE user_id = ? LIMIT 1");
                            $stmtSt->execute([$user['id']]);
                            if (!$stmtSt->fetch()) {
                                // Create student record if missing
                                $sNum = 'STD-' . date('Y') . '-' . str_pad($user['id'], 4, '0', STR_PAD_LEFT);
                                $pdo->prepare("INSERT IGNORE INTO students (user_id, student_number) VALUES (?, ?)")
                                    ->execute([$user['id'], $sNum]);
                            }
                            redirect('student/profile.php?enroll=1');
                        }
                        redirect('student/dashboard.php');
                    }
                }
            }
        } else {
            // Detailed debug logging for authentication failures (no plaintext passwords)
            try {
                if ($user) {
                    $hash = $user['password'] ?? '';
                    $algo = '';
                    if (strpos($hash, '$2y$') === 0 || strpos($hash, '$2a$') === 0) $algo = 'bcrypt';
                    elseif (strpos($hash, '$argon2') === 0) $algo = 'argon2';
                    else $algo = 'unknown';
                    error_log("Auth failure: identifier={$identifier}, user_id={$user['id']}, hash_algo={$algo}, hash_len=" . strlen($hash) . ", ip=" . (
                        
                        
                        ($_SERVER['REMOTE_ADDR'] ?? 'unknown')
                    ));
                } else {
                    error_log("Auth failure: identifier={$identifier}, user_found=0, ip=" . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
                }
            } catch (Exception $e) {
                // non-fatal
            }

            $errors[] = 'Invalid email/username or password.';
        }
    }
}

// Preserve any pending course context in form hints
$pendingTitle = $_SESSION['pending_course_title'] ?? '';

include BASE_PATH . '/includes/layouts/header.php';
?>

<div class="py-5 bg-light-subtle" style="min-height: calc(100vh - 120px); display:flex; align-items:center;">
    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-md-7 col-lg-5">

                <?php if (!empty($pendingTitle)): ?>
                <div class="alert alert-info rounded-4 mb-4 border-0 shadow-sm small">
                    <i class="bi bi-bookmark-fill text-primary me-2"></i>
                    <strong>Course saved:</strong> <?= e($pendingTitle) ?> — Log in to continue to payment.
                </div>
                <?php endif; ?>

                <div class="card border-0 shadow-lg rounded-4 p-4 p-md-5">
                    <div class="text-center mb-4">
                        <div class="p-3 bg-primary bg-opacity-10 text-primary rounded-circle d-inline-flex mb-3">
                            <i class="bi bi-person-fill-lock fs-2"></i>
                        </div>
                        <h2 class="fw-bold mb-1">Welcome Back</h2>
                        <p class="text-muted small">Sign in to your StudyMe AI learning account.</p>
                    </div>

                    <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger rounded-3 mb-4">
                        <ul class="mb-0 ps-3">
                            <?php foreach ($errors as $err): ?>
                            <li><?= e($err) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>

                    <?php foreach (get_flash() as $type => $messages): ?>
                    <?php foreach ($messages as $msg): ?>
                    <div class="alert alert-<?= $type==='error'?'danger':$type ?> rounded-3 mb-4"><?= e($msg) ?></div>
                    <?php endforeach; ?>
                    <?php endforeach; ?>

                    <form action="<?= url('auth/login.php') ?>" method="POST">
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Email or Username</label>
                            <input type="text" name="identifier" class="form-control form-control-lg rounded-3 fs-6"
                                   value="<?= e($identifier) ?>" required placeholder="you@example.com" autofocus>
                        </div>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label small fw-bold mb-0">Password</label>
                                <a href="<?= url('auth/forgot-password.php') ?>" class="small text-decoration-none">Forgot password?</a>
                            </div>
                            <input type="password" name="password" class="form-control form-control-lg rounded-3 fs-6"
                                   required placeholder="Enter password">
                        </div>
                        <button type="submit" class="btn btn-primary btn-lg rounded-pill w-100 py-3 fw-bold mb-3 shadow"
                                data-feedback="success">
                            Sign In <i class="bi bi-box-arrow-in-right ms-2"></i>
                        </button>
                        <div class="text-center text-muted small">
                            Don't have an account?
                            <a href="<?= url('auth/register.php') ?>" class="text-primary fw-bold text-decoration-none">Register here</a>
                        </div>
                    </form>

                    <!-- Demo Credentials -->
                    <div class="mt-4 pt-3 border-top border-secondary border-opacity-25 p-3 bg-light rounded-3 small">
                        <div class="fw-bold text-muted mb-1"><i class="bi bi-info-circle me-1"></i>Demo Credentials (Pass: Password123!)</div>
                        <div class="text-muted">Student: <code>student@studyme.ng</code></div>
                        <div class="text-muted">Teacher: <code>teacher@studyme.ng</code></div>
                        <div class="text-muted">Admin:   <code>admin@studyme.ng</code></div>
                    </div>
                </div>

                <div class="text-center mt-3">
                    <a href="<?= url('index.php') ?>" class="small text-muted text-decoration-none">
                        <i class="bi bi-arrow-left me-1"></i> Back to Home
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include BASE_PATH . '/includes/layouts/footer.php'; ?>
