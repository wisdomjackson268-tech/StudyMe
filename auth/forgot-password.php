<?php

require_once dirname(__DIR__) . '/config/main.php';

$message = '';
$error = '';
$email = trim($_GET['email'] ?? '');

if (is_post()) {
    $email = trim(strtolower($_POST['email'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT id, first_name FROM users WHERE LOWER(TRIM(email)) = LOWER(TRIM(?)) LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $token = bin2hex(random_bytes(24));
            try {
                $pdo->prepare("INSERT INTO password_resets (email, token, created_at) VALUES (?, ?, NOW())")
                    ->execute([$email, $token]);
            } catch (Exception $e) {  }
            $message = 'If an account matches that email address, password reset instructions have been dispatched.';
        } else {
            $message = 'If an account matches that email address, password reset instructions have been dispatched.';
        }
    }
}

$seo_options = [
    'title'      => 'Forgot Password | StudyMe',
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
                <div class="card auth-card border-0 shadow-lg rounded-4 p-3 p-sm-4 p-md-5 bg-white position-relative">
                    <div class="text-center mb-4">
                        <div class="auth-icon-box bg-warning bg-opacity-10 text-warning rounded-4 d-inline-flex align-items-center justify-content-center mb-3 shadow-sm" style="width: 64px; height: 64px;">
                            <i class="bi bi-key-fill fs-2"></i>
                        </div>
                        <h2 class="fw-extrabold text-dark mb-1" style="font-size: 1.65rem;">Reset Password</h2>
                        <p class="text-muted small mb-0">Enter your registered email address to receive recovery instructions.</p>
                    </div>

                    <?php if ($message): ?>
                        <div class="alert alert-success rounded-4 mb-4 border-0 shadow-sm p-3 d-flex align-items-center gap-2">
                            <i class="bi bi-check-circle-fill text-success fs-5"></i>
                            <div class="small fw-semibold"><?= e($message) ?></div>
                        </div>
                    <?php endif; ?>

                    <?php if ($error): ?>
                        <div class="alert alert-danger rounded-4 mb-4 border-0 shadow-sm p-3 d-flex align-items-center gap-2">
                            <i class="bi bi-exclamation-triangle-fill text-danger fs-5"></i>
                            <div class="small fw-semibold"><?= e($error) ?></div>
                        </div>
                    <?php endif; ?>

                    <form action="<?= url('auth/forgot-password.php') ?>" method="POST" id="forgotForm" novalidate>
                        <div class="mb-4">
                            <label for="email" class="form-label small fw-bold text-dark">Email Address <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-envelope"></i></span>
                                <input type="email" name="email" id="email" class="form-control form-control-lg fs-6 border-start-0 py-2" value="<?= e($email) ?>" required placeholder="you@example.com" autofocus autocomplete="email" inputmode="email">
                            </div>
                        </div>

                        <div class="submit-box-wrapper mb-3">
                            <button type="submit" class="btn btn-primary btn-lg rounded-pill w-100 py-3 fw-bold shadow-sm d-flex align-items-center justify-content-center gap-2 hover-lift" data-feedback="click">
                                <span>Send Reset Instructions</span>
                                <i class="bi bi-send-fill fs-6"></i>
                            </button>
                        </div>

                        <div class="text-center py-2 border-top border-light-subtle">
                            <span class="text-muted small">Remember your password?</span>
                            <a href="<?= url('auth/login.php') ?>" class="text-primary fw-bold text-decoration-none small ms-1 hover-underline">
                                Return to Sign In <i class="bi bi-arrow-right-short"></i>
                            </a>
                        </div>
                    </form>
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

<?php include BASE_PATH . '/includes/layouts/footer.php'; ?>
