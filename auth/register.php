<?php
/**
 * StudyMe AI Platform — User Registration with Course Preservation
 */
require_once dirname(__DIR__) . '/config/main.php';

if (is_logged_in()) {
    $role = current_user_role();
    $pendingCourseId = (int)($_SESSION['pending_course_id'] ?? 0);
    if ($role === 'student' && $pendingCourseId > 0 && !(defined('FREE_TESTING_MODE') && FREE_TESTING_MODE)) {
        redirect('payments/checkout.php?course_id=' . $pendingCourseId);
    }
    $dash = $role === 'admin' ? 'admin/dashboard.php' : ($role === 'teacher' ? 'teacher/dashboard.php' : 'student/dashboard.php');
    redirect($dash);
}

$errors    = [];
$firstName = '';
$lastName  = '';
$email     = '';
$phone     = '';
$role      = 'student';

// Pending course info (set by courses/details.php when unauthenticated)
$pendingCourseId    = (int)($_SESSION['pending_course_id']    ?? 0);
$pendingCourseTitle = $_SESSION['pending_course_title'] ?? '';

if (is_post()) {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName  = trim($_POST['last_name']  ?? '');
    $email     = trim($_POST['email']      ?? '');
    $phone     = trim($_POST['phone']      ?? '');
    $password  = $_POST['password']               ?? '';
    $passwordConfirm = $_POST['password_confirmation'] ?? '';
    $role      = in_array($_POST['role'] ?? '', ['student', 'teacher']) ? $_POST['role'] : 'student';

    if (empty($firstName) || empty($lastName)) {
        $errors[] = 'First name and last name are required.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    }
    if ($password !== $passwordConfirm) {
        $errors[] = 'Passwords do not match.';
    }

    if (empty($errors)) {
        $pdo  = getDBConnection();
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = 'An account with this email already exists. Please log in.';
        } else {
            try {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $username = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $firstName . $lastName)) . rand(10, 999);

                $pdo->beginTransaction();

                $stmt = $pdo->prepare("
                    INSERT INTO users (first_name, last_name, username, email, password, phone, role, status, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'active', NOW())
                ");
                $stmt->execute([$firstName, $lastName, $username, $email, $hashedPassword, $phone, $role]);
                $userId = $pdo->lastInsertId();

                if ($role === 'student') {
                    $studentNum = 'STD-' . date('Y') . '-' . str_pad($userId, 4, '0', STR_PAD_LEFT);
                    $pdo->prepare("INSERT INTO students (user_id, student_number) VALUES (?, ?)")
                        ->execute([$userId, $studentNum]);
                } else {
                    $teacherNum = 'TCH-' . str_pad($userId, 3, '0', STR_PAD_LEFT);
                    $pdo->prepare("INSERT INTO teachers (user_id, teacher_number) VALUES (?, ?)")
                        ->execute([$userId, $teacherNum]);
                }

                $pdo->commit();

                // Record pending referral if a ref code was provided
                if (function_exists('record_pending_referral')) {
                    record_pending_referral($userId);
                }

                // Create session
                $_SESSION[SESSION_USER_ID]   = $userId;
                $_SESSION[SESSION_USER_ROLE] = $role;
                $_SESSION[SESSION_USER_DATA] = [
                    'id'         => $userId,
                    'first_name' => $firstName,
                    'last_name'  => $lastName,
                    'email'      => $email,
                    'role'       => $role,
                ];

                // Track registration activity
                if (function_exists('log_user_activity')) {
                    log_user_activity(
                        $userId,
                        'registered',
                        ucfirst($role) . ' account created. Email: ' . $email
                    );
                }

                $_SESSION['auth_success_vibrate'] = true;

                // Send welcome notification
                try {
                    $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, created_at) VALUES (?, ?, ?, 'welcome', NOW())")
                        ->execute([$userId, 'Welcome to StudyMe AI!', 'Your account is ready. Start learning with AI-powered courses today!']);
                } catch (Exception $e) { /* non-critical */ }

                // ── Redirect based on role + pending course ──
                if ($role === 'teacher') {
                    set_flash('success', 'Welcome to StudyMe, ' . $firstName . '! Your instructor account is ready.');
                    redirect('teacher/dashboard.php');
                } elseif (defined('FREE_TESTING_MODE') && FREE_TESTING_MODE) {
                    // In FREE_TESTING_MODE: auto-enroll pending course if selected and go to dashboard
                    if ($pendingCourseId > 0) {
                        $stmtSt = $pdo->prepare("SELECT id FROM students WHERE user_id = ? LIMIT 1");
                        $stmtSt->execute([$userId]);
                        $stRow = $stmtSt->fetch(PDO::FETCH_ASSOC);
                        if ($stRow) {
                            $pdo->prepare("INSERT INTO enrollments (student_id, course_id, status, progress, enrolled_at) VALUES (?, ?, 'active', 0.00, NOW()) ON DUPLICATE KEY UPDATE status = 'active'")
                                ->execute([(int)$stRow['id'], $pendingCourseId]);
                        }
                        unset($_SESSION['pending_course_id'], $_SESSION['pending_course_slug'], $_SESSION['pending_course_title']);
                    }
                    set_flash('success', 'Welcome to StudyMe, ' . $firstName . '! Your account is active with free testing access.');
                    redirect('student/dashboard.php');
                } elseif ($pendingCourseId > 0) {
                    set_flash('success', 'Account created! Please review your profile before completing payment for: ' . $pendingCourseTitle);
                    redirect('student/profile.php?enroll=1');
                } else {
                    set_flash('success', 'Welcome to StudyMe, ' . $firstName . '! Explore courses and start learning.');
                    redirect('student/dashboard.php');
                }
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $errors[] = 'Registration error. Please try again.';
                error_log('Registration error: ' . $e->getMessage());
            }
        }
    }
}

include BASE_PATH . '/includes/layouts/header.php';
?>

<div class="py-5 bg-light-subtle" style="min-height: calc(100vh - 120px);">
    <div class="container py-4">

        <!-- Onboarding Steps (shown when a course is pending) -->
        <?php if ($pendingCourseId > 0): ?>
        <div class="row justify-content-center mb-4">
            <div class="col-lg-7">
                <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                    <?php
                    $steps = ['Select Course','Create Account','Payment','Start Learning'];
                    $active = 1; // Register is step 2 (index 1)
                    foreach ($steps as $i => $step):
                    ?>
                    <div class="text-center flex-fill">
                        <div class="rounded-circle d-inline-flex align-items-center justify-content-center fw-bold mb-1 shadow-sm"
                             style="width:38px;height:38px;font-size:0.85rem;
                                    background:<?= $i < $active ? '#22c55e' : ($i==$active ? '#3b82f6' : '#e5e7eb') ?>;
                                    color:<?= $i <= $active ? '#fff' : '#9ca3af' ?>;">
                            <?= $i < $active ? '✓' : ($i+1) ?>
                        </div>
                        <div class="small fw-<?= $i===$active?'bold':'normal' ?> text-<?= $i===$active?'primary':'muted' ?>" style="font-size:0.72rem;"><?= $step ?></div>
                    </div>
                    <?php if ($i < count($steps)-1): ?>
                    <div class="flex-fill" style="height:2px;background:<?= $i < $active ? '#22c55e' : '#e5e7eb' ?>;margin-bottom:1.5rem;"></div>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">

                <?php if (!empty($pendingCourseTitle)): ?>
                <div class="alert alert-info rounded-4 mb-4 border-0 shadow-sm small">
                    <i class="bi bi-bookmark-fill text-primary me-2"></i>
                    <strong>Enrolling in:</strong> <?= e($pendingCourseTitle) ?> — Create your account to proceed to payment.
                </div>
                <?php endif; ?>

                <div class="card border-0 shadow-lg rounded-4 p-4 p-md-5">
                    <div class="text-center mb-4">
                        <div class="p-3 bg-primary bg-opacity-10 text-primary rounded-circle d-inline-flex mb-3">
                            <i class="bi bi-mortarboard-fill fs-2"></i>
                        </div>
                        <h2 class="fw-bold mb-1">Create Your StudyMe Account</h2>
                        <p class="text-muted small">Join thousands of learners powered by AI and expert teachers.</p>
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

                    <form action="<?= url('auth/register.php') ?>" method="POST">
                        <div class="row g-3 mb-3">
                            <div class="col-sm-6">
                                <label class="form-label small fw-bold">First Name <span class="text-danger">*</span></label>
                                <input type="text" name="first_name" class="form-control form-control-lg rounded-3 fs-6"
                                       value="<?= e($firstName) ?>" required placeholder="e.g. David">
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label small fw-bold">Last Name <span class="text-danger">*</span></label>
                                <input type="text" name="last_name" class="form-control form-control-lg rounded-3 fs-6"
                                       value="<?= e($lastName) ?>" required placeholder="e.g. Okonkwo">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold">Email Address <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control form-control-lg rounded-3 fs-6"
                                   value="<?= e($email) ?>" required placeholder="david@example.com">
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold">Phone Number <span class="text-muted fw-normal">(Optional)</span></label>
                            <input type="tel" name="phone" class="form-control form-control-lg rounded-3 fs-6"
                                   value="<?= e($phone) ?>" placeholder="+234 800 000 0000">
                        </div>

                        <?php if ($pendingCourseId <= 0): ?>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">I want to register as a:</label>
                            <select name="role" class="form-select form-select-lg rounded-3 fs-6">
                                <option value="student" <?= $role==='student'?'selected':'' ?>>🎓 Student / Learner</option>
                                <option value="teacher" <?= $role==='teacher'?'selected':'' ?>>👨‍🏫 Teacher / Instructor</option>
                            </select>
                        </div>
                        <?php else: ?>
                        <input type="hidden" name="role" value="student">
                        <?php endif; ?>

                        <div class="row g-3 mb-4">
                            <div class="col-sm-6">
                                <label class="form-label small fw-bold">Password <span class="text-danger">*</span></label>
                                <input type="password" name="password" class="form-control form-control-lg rounded-3 fs-6"
                                       required placeholder="Min 6 characters">
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label small fw-bold">Confirm Password <span class="text-danger">*</span></label>
                                <input type="password" name="password_confirmation" class="form-control form-control-lg rounded-3 fs-6"
                                       required placeholder="Repeat password">
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg rounded-pill w-100 py-3 fw-bold mb-3 shadow"
                                data-feedback="success">
                            <?= $pendingCourseId > 0 ? 'Create Account & Continue to Payment' : 'Create My Account' ?>
                            <i class="bi bi-arrow-right ms-2"></i>
                        </button>

                        <div class="text-center text-muted small">
                            Already have an account?
                            <a href="<?= url('auth/login.php') ?>" class="text-primary fw-bold text-decoration-none">Log in here</a>
                        </div>
                    </form>
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
