<?php

require_once dirname(__DIR__) . '/config/main.php';

$pendingContext = $_SESSION['pending_verification'] ?? null;
$email = trim(strtolower($_GET['email'] ?? ($pendingContext['email'] ?? '')));
$getCode = trim($_GET['code'] ?? '');

$errors = [];
$successMessage = '';
$isVerified = false;
$autoVerified = false;

if (is_logged_in()) {
    $currentUser = current_user();
    if (!empty($currentUser['id'])) {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT email_verified_at FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$currentUser['id']]);
        $verifiedRow = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($verifiedRow && !empty($verifiedRow['email_verified_at'])) {
            $role = current_user_role();
            $dash = $role === 'admin' ? 'admin/dashboard.php' : ($role === 'teacher' ? 'teacher/dashboard.php' : 'student/dashboard.php');
            redirect($dash);
        }
    }
}

if (!empty($email) && !empty($getCode) && strlen($getCode) === 6 && !is_post()) {
    $result = verify_email_code($email, $getCode);
    if ($result['success']) {
        $userId = $result['user_id'];
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {

            $_SESSION[SESSION_USER_ID]   = $user['id'];
            $_SESSION[SESSION_USER_ROLE] = $user['role'];
            $_SESSION[SESSION_USER_DATA] = [
                'id'         => $user['id'],
                'first_name' => $user['first_name'],
                'last_name'  => $user['last_name'],
                'email'      => $user['email'],
                'role'       => $user['role'],
            ];
            unset($_SESSION['pending_verification']);
            $_SESSION['auth_success_vibrate'] = true;

            $isVerified = true;
            $autoVerified = true;
            $successMessage = "Your email ({$email}) has been successfully verified! Welcome to StudyMe.";
        }
    } else {
        $errors[] = $result['message'];
    }
}

if (is_post()) {
    $action = $_POST['action'] ?? 'verify';
    $postEmail = trim(strtolower($_POST['email'] ?? $email));

    if (empty($postEmail)) {
        $errors[] = 'Email address is required to complete verification.';
    } elseif ($action === 'resend') {
        $resendResult = resend_email_verification_code($postEmail);
        if ($resendResult['success']) {
            if (!empty($resendResult['already_verified'])) {
                set_flash('success', $resendResult['message']);
                redirect('auth/login.php');
            } else {
                set_flash('success', $resendResult['message']);
                redirect('auth/verify-email.php?email=' . urlencode($postEmail));
            }
        } else {
            $errors[] = $resendResult['message'];
        }
    } elseif ($action === 'verify') {
        $enteredCode = trim($_POST['code'] ?? '');
        $result = verify_email_code($postEmail, $enteredCode);

        if ($result['success']) {
            $userId = $result['user_id'];
            $pdo = getDBConnection();
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {

                $_SESSION[SESSION_USER_ID]   = $user['id'];
                $_SESSION[SESSION_USER_ROLE] = $user['role'];
                $_SESSION[SESSION_USER_DATA] = [
                    'id'         => $user['id'],
                    'first_name' => $user['first_name'],
                    'last_name'  => $user['last_name'],
                    'email'      => $user['email'],
                    'role'       => $user['role'],
                ];
                unset($_SESSION['pending_verification']);
                $_SESSION['auth_success_vibrate'] = true;

                $pendingCourseId = (int)($_SESSION['pending_course_id'] ?? 0);
                $pendingCourseTitle = $_SESSION['pending_course_title'] ?? '';

                if ($user['role'] === 'teacher') {
                    set_flash('success', 'Email verified! Welcome to StudyMe, ' . $user['first_name'] . '.');
                    redirect('teacher/dashboard.php');
                } elseif ($pendingCourseId > 0) {
                    require_once BASE_PATH . '/includes/functions/courses.php';
                    $courseCheck = course_has_active_teacher($pendingCourseId);

                    if ($courseCheck['can_enroll']) {
                        if (defined('FREE_TESTING_MODE') && FREE_TESTING_MODE) {
                            $stmtSt = $pdo->prepare("SELECT id FROM students WHERE user_id = ? LIMIT 1");
                            $stmtSt->execute([$user['id']]);
                            $stRow = $stmtSt->fetch(PDO::FETCH_ASSOC);
                            if ($stRow) {
                                $assignedTeacherId = !empty($courseCheck['teacher']['id']) ? (int)$courseCheck['teacher']['id'] : null;
                                $pdo->prepare("INSERT INTO enrollments (student_id, course_id, teacher_id, status, progress, enrolled_at) VALUES (?, ?, ?, 'active', 0.00, NOW()) ON DUPLICATE KEY UPDATE status = 'active', teacher_id = IF(VALUES(teacher_id) IS NOT NULL, VALUES(teacher_id), teacher_id)")
                                    ->execute([(int)$stRow['id'], $pendingCourseId, $assignedTeacherId]);
                            }
                            unset($_SESSION['pending_course_id'], $_SESSION['pending_course_slug'], $_SESSION['pending_course_title']);
                            set_flash('success', 'Email verified! You are now enrolled in ' . $pendingCourseTitle . ' with instructor ' . ($courseCheck['teacher']['name'] ?? '') . '.');
                            redirect('student/course.php?id=' . $pendingCourseId);
                        } else {
                            set_flash('success', 'Email verified! Complete enrollment for: ' . $pendingCourseTitle);
                            redirect('student/profile.php?enroll=1');
                        }
                    } else {
                        unset($_SESSION['pending_course_id'], $_SESSION['pending_course_slug'], $_SESSION['pending_course_title']);
                        set_flash('warning', 'Email verified! Notice: ' . $courseCheck['reason'] . ' Please select an active course with an assigned teacher.');
                        redirect('student/dashboard.php');
                    }
                } else {
                    set_flash('success', 'Email verified! Welcome to StudyMe, ' . $user['first_name'] . '.');
                    redirect('student/dashboard.php');
                }
            }
        } else {
            $errors[] = $result['message'];
        }
    }
}

$cooldownInfo = !empty($email) ? check_resend_cooldown($email, 60) : ['allowed' => true, 'seconds_remaining' => 0];

$seo_options = [
    'title'      => 'Verify Email Address | StudyMe',
    'is_private' => true,
    'noindex'    => true
];
include BASE_PATH . '/includes/layouts/header.php';
?>

<div class="auth-page-wrapper py-4 py-md-5 bg-light-subtle position-relative overflow-hidden d-flex align-items-center" style="min-height: calc(100vh - 120px);">
    <div class="auth-bg-blob auth-bg-blob-1"></div>
    <div class="auth-bg-blob auth-bg-blob-2"></div>

    <div class="container position-relative py-2 py-sm-3">
        <div class="row justify-content-center">
            <div class="col-12 col-sm-10 col-md-8 col-lg-6 col-xl-5 text-center">

                <div class="card auth-card border-0 shadow-lg rounded-4 p-3 p-sm-4 p-md-5 bg-white position-relative">

                    <?php if ($isVerified): ?>

                        <div class="auth-icon-box bg-success bg-opacity-10 text-success rounded-circle d-inline-flex align-items-center justify-content-center mx-auto mb-3 shadow-sm" style="width: 72px; height: 72px;">
                            <i class="bi bi-patch-check-fill fs-1"></i>
                        </div>
                        <h2 class="fw-extrabold text-dark mb-2">Email Verified!</h2>
                        <p class="text-muted small mb-4"><?= e($successMessage) ?></p>

                        <div class="submit-box-wrapper d-grid gap-2">
                            <?php if (current_user_role() === 'teacher'): ?>
                                <a href="<?= url('teacher/dashboard.php') ?>" class="btn btn-primary btn-lg rounded-pill fw-bold py-3 shadow-sm hover-lift d-flex align-items-center justify-content-center gap-2">
                                    <span>Go to Instructor Suite</span>
                                    <i class="bi bi-arrow-right fs-5"></i>
                                </a>
                            <?php else: ?>
                                <a href="<?= url('student/dashboard.php') ?>" class="btn btn-primary btn-lg rounded-pill fw-bold py-3 shadow-sm hover-lift d-flex align-items-center justify-content-center gap-2">
                                    <span>Start Learning on Dashboard</span>
                                    <i class="bi bi-arrow-right fs-5"></i>
                                </a>
                            <?php endif; ?>
                        </div>

                    <?php else: ?>

                        <div class="auth-icon-box bg-primary bg-opacity-10 text-primary rounded-4 d-inline-flex align-items-center justify-content-center mx-auto mb-3 shadow-sm" style="width: 64px; height: 64px;">
                            <i class="bi bi-shield-lock-fill fs-2"></i>
                        </div>
                        <h2 class="fw-extrabold text-dark mb-1" style="font-size: 1.65rem;">Verify Your Email</h2>
                        <p class="text-muted small mb-3">We sent a 6-digit confirmation code to:</p>

                        <?php if (!empty($email)): ?>
                            <div class="mb-4">
                                <span class="badge bg-light text-dark border border-subtle px-3 py-2 rounded-pill fs-6 fw-bold shadow-xs">
                                    <i class="bi bi-envelope-fill text-primary me-1"></i> <?= e($email) ?>
                                </span>
                            </div>
                        <?php endif; ?>

                        <?php foreach (get_flash() as $type => $messages): ?>
                            <?php foreach ($messages as $msg): ?>
                                <div class="alert alert-<?= $type === 'error' ? 'danger' : ($type === 'info' ? 'info' : 'success') ?> rounded-4 mb-3 p-3 border-0 shadow-sm text-start d-flex align-items-center gap-2">
                                    <i class="bi <?= $type === 'error' ? 'bi-exclamation-circle-fill text-danger' : ($type === 'info' ? 'bi-info-circle-fill text-info' : 'bi-check-circle-fill text-success') ?> fs-5"></i>
                                    <div class="small fw-semibold"><?= $msg ?></div>
                                </div>
                            <?php endforeach; ?>
                        <?php endforeach; ?>

                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger rounded-4 mb-4 border-0 shadow-sm p-3 text-start">
                                <div class="d-flex align-items-start gap-2">
                                    <i class="bi bi-exclamation-triangle-fill text-danger fs-5 flex-shrink-0 mt-1"></i>
                                    <div class="flex-grow-1">
                                        <strong class="d-block mb-1">Verification Notice:</strong>
                                        <ul class="mb-0 ps-3 small">
                                            <?php foreach ($errors as $err): ?>
                                                <li class="py-1"><?= $err ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <form action="<?= url('auth/verify-email.php') ?>" method="POST" id="verifyForm">
                            <input type="hidden" name="action" value="verify">

                            <?php if (empty($email)): ?>
                                <div class="mb-3 text-start">
                                    <label class="form-label small fw-bold text-dark">Registered Email Address <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-envelope"></i></span>
                                        <input type="email" name="email" class="form-control form-control-lg fs-6 border-start-0 py-2" required placeholder="Enter your registered email">
                                    </div>
                                </div>
                            <?php else: ?>
                                <input type="hidden" name="email" value="<?= e($email) ?>">
                            <?php endif; ?>

                            <div class="mb-4">
                                <label for="verificationCodeInput" class="form-label small fw-bold text-muted text-uppercase d-block mb-2">Enter 6-Digit Code</label>
                                <input type="text"
                                       name="code"
                                       id="verificationCodeInput"
                                       class="form-control form-control-lg text-center rounded-3 fw-bold border-2"
                                       style="letter-spacing: 10px; font-size: 1.85rem; font-family: var(--font-mono, monospace); min-height: 58px;"
                                       maxlength="6"
                                       pattern="[0-9]{6}"
                                       inputmode="numeric"
                                       autocomplete="one-time-code"
                                       value="<?= e($getCode) ?>"
                                       placeholder="••••••"
                                       required
                                       autofocus>
                                <small class="text-muted d-block mt-2">Codes expire 15 minutes after issuance.</small>
                            </div>

                            <div class="submit-box-wrapper mb-3">
                                <button type="submit" class="btn btn-primary btn-lg rounded-pill w-100 py-3 fw-bold shadow-sm d-flex align-items-center justify-content-center gap-2 hover-lift" data-feedback="success">
                                    <span>Confirm &amp; Activate Account</span>
                                    <i class="bi bi-arrow-right fs-5"></i>
                                </button>
                            </div>
                        </form>

                        <div class="border-top pt-3 mt-3">
                            <p class="text-muted small mb-2">Didn't receive the email in your inbox or spam?</p>

                            <form action="<?= url('auth/verify-email.php') ?>" method="POST" id="resendForm" class="d-inline">
                                <input type="hidden" name="action" value="resend">
                                <input type="hidden" name="email" value="<?= e($email) ?>">

                                <button type="submit"
                                        id="resendBtn"
                                        class="btn btn-outline-secondary btn-sm rounded-pill px-4 py-2 fw-semibold d-inline-flex align-items-center gap-1"
                                        <?= !$cooldownInfo['allowed'] ? 'disabled' : '' ?>>
                                    <i class="bi bi-arrow-clockwise"></i>
                                    <span id="resendBtnText">
                                        <?= !$cooldownInfo['allowed'] ? 'Resend in ' . $cooldownInfo['seconds_remaining'] . 's' : 'Resend Code' ?>
                                    </span>
                                </button>
                            </form>
                        </div>

                        <div class="text-center mt-3 pt-2 border-top">
                            <a href="<?= url('auth/register.php') ?>" class="small text-muted text-decoration-none">
                                <i class="bi bi-pencil-square me-1"></i> Wrong email address? Register again
                            </a>
                        </div>

                    <?php endif; ?>

                </div>

                <div class="text-center mt-3">
                    <a href="<?= url('index.php') ?>" class="small text-muted text-decoration-none">
                        <i class="bi bi-arrow-left me-1"></i> Back to Homepage
                    </a>
                </div>

            </div>
        </div>
    </div>
</div>

<?php if (!$isVerified && !$cooldownInfo['allowed']): ?>
<script>
document.addEventListener("DOMContentLoaded", () => {
    let secondsLeft = <?= (int)$cooldownInfo['seconds_remaining'] ?>;
    const btn = document.getElementById("resendBtn");
    const btnText = document.getElementById("resendBtnText");

    if (btn && btnText && secondsLeft > 0) {
        const interval = setInterval(() => {
            secondsLeft--;
            if (secondsLeft <= 0) {
                clearInterval(interval);
                btn.removeAttribute("disabled");
                btnText.textContent = "Resend Code";
            } else {
                btnText.textContent = "Resend in " + secondsLeft + "s";
            }
        }, 1000);
    }
});
</script>
<?php endif; ?>

<?php include BASE_PATH . '/includes/layouts/footer.php'; ?>
