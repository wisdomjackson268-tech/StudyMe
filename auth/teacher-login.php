<?php

require_once dirname(__DIR__) . '/config/main.php';

if (is_logged_in()) {
    $role = current_user_role();
    if ($role === 'teacher') {
        redirect('teacher/dashboard.php');
    } elseif ($role === 'admin') {
        redirect('admin/dashboard.php');
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
        $stmt = $pdo->prepare("SELECT * FROM users WHERE (LOWER(TRIM(email)) = LOWER(TRIM(?)) OR username = ?) AND role IN ('teacher', 'admin') LIMIT 1");
        $stmt->execute([$identifier, $identifier]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            if ($user['status'] === 'inactive' || $user['status'] === 'suspended') {
                $errors[] = 'Your instructor account has been deactivated. Please contact administrator.';
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

                $pdo->prepare("UPDATE users SET last_login_at = NOW(), status = IF(status='pending','active',status) WHERE id = ?")
                    ->execute([$user['id']]);

                set_flash('success', 'Welcome back to the Instructor Suite, ' . $user['first_name'] . '!');
                redirect($user['role'] === 'admin' ? 'admin/dashboard.php' : 'teacher/dashboard.php');
            }
        } else {
            $errors[] = 'Invalid instructor credentials. If you are a student, please use student login.';
        }
    }
}

$seo_options = [
    'title'      => 'Teacher Sign In | StudyMe Instructor Suite',
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
            <div class="col-12 col-sm-10 col-md-8 col-lg-6 col-xl-5">

                <div class="card auth-card border-0 shadow-lg rounded-4 overflow-hidden bg-white position-relative">
                    <div class="card-header p-4 text-center border-0 text-white" style="background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #312e81 100%) !important;">
                        <div class="auth-icon-box bg-warning bg-opacity-20 text-warning rounded-4 d-inline-flex align-items-center justify-content-center mb-2 fs-2 shadow-sm" style="width: 56px; height: 56px;">
                            <i class="bi bi-person-workspace"></i>
                        </div>
                        <h2 class="h3 fw-bold text-white mb-1">Instructor Sign In</h2>
                        <p class="text-white-50 small mb-0">Access your assigned courses, lessons, quizzes &amp; student analytics</p>
                    </div>

                    <div class="card-body p-3 p-sm-4 p-md-5">

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

                        <form action="<?= url('auth/teacher-login.php') ?>" method="POST" id="teacherLoginForm" novalidate>
                            <div class="mb-3">
                                <label for="identifier" class="form-label fw-semibold small text-dark">Teacher Email or Username <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-envelope"></i></span>
                                    <input type="text" name="identifier" id="identifier" class="form-control form-control-lg fs-6 border-start-0 py-2" placeholder="teacher@studyme.ng" value="<?= e($identifier) ?>" required autocomplete="username">
                                </div>
                            </div>

                            <div class="mb-4">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <label for="password" class="form-label fw-semibold small text-dark mb-0">Password <span class="text-danger">*</span></label>
                                    <a href="<?= url('auth/forgot-password.php') ?>" class="small text-primary text-decoration-none fw-semibold">Forgot password?</a>
                                </div>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-shield-lock"></i></span>
                                    <input type="password" name="password" id="password" class="form-control form-control-lg fs-6 border-start-0 border-end-0 py-2" placeholder="Enter password" required autocomplete="current-password">
                                    <button type="button" class="btn btn-outline-secondary border-start-0 bg-light text-muted" onclick="togglePasswordVisibility('password', this)" title="Toggle password visibility">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="submit-box-wrapper mb-3">
                                <button type="submit" class="btn btn-warning btn-lg rounded-pill w-100 fw-bold shadow-sm text-dark py-3 d-flex align-items-center justify-content-center gap-2 hover-lift" data-feedback="click">
                                    <span>Sign In to Instructor Suite</span>
                                    <i class="bi bi-box-arrow-in-right fs-5"></i>
                                </button>
                            </div>
                        </form>

                        <div class="mt-3 pt-3 border-top border-light-subtle">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="fw-bold small text-muted text-uppercase" style="font-size: 0.72rem;">
                                    <i class="bi bi-lightning-charge-fill text-warning me-1"></i> Quick Demo Fill
                                </span>
                                <span class="badge bg-light text-muted border small">Pass: Password123!</span>
                            </div>
                            <div class="row g-2">
                                <div class="col-6">
                                    <button type="button" class="btn btn-sm btn-outline-warning text-dark w-100 rounded-3 py-2 fw-semibold" onclick="fillTeacherDemo('teacher@studyme.ng', 'Password123!')">
                                        <i class="bi bi-person-workspace d-block mb-1 fs-5 text-warning"></i>
                                        <span class="small d-block" style="font-size: 0.75rem;">Teacher</span>
                                    </button>
                                </div>
                                <div class="col-6">
                                    <button type="button" class="btn btn-sm btn-outline-dark w-100 rounded-3 py-2 fw-semibold" onclick="fillTeacherDemo('admin@studyme.ng', 'Password123!')">
                                        <i class="bi bi-shield-lock d-block mb-1 fs-5 text-dark"></i>
                                        <span class="small d-block" style="font-size: 0.75rem;">Admin</span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="text-center pt-3 border-top mt-3">
                            <p class="small text-muted mb-2">Want to join the StudyMe educator faculty?</p>
                            <a href="<?= url('auth/teacher-register.php') ?>" class="btn btn-outline-primary btn-sm rounded-pill px-4 py-2 fw-bold">
                                Apply as Teacher &rarr;
                            </a>
                        </div>
                        <div class="text-center pt-2">
                            <a href="<?= url('auth/login.php') ?>" class="text-decoration-none small text-muted hover-underline">
                                <i class="bi bi-mortarboard me-1"></i> Student Sign In Instead
                            </a>
                        </div>

                    </div>
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

function fillTeacherDemo(email, pass) {
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
