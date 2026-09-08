<?php
/**
 * StudyMe AI Platform - Forgot Password
 */
require_once dirname(__DIR__) . '/config/main.php';

$message = '';
$error = '';

if (is_post()) {
    $email = trim($_POST['email'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $message = 'If an account matches that email address, a password reset link has been dispatched.';
    }
}

include BASE_PATH . '/includes/layouts/header.php';
?>

<div class="py-5 bg-light-subtle" style="min-height: calc(100vh - 120px); display: flex; align-items: center;">
    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-md-7 col-lg-5">
                <div class="card border-0 shadow-lg rounded-4 p-4 p-md-5">
                    <div class="text-center mb-4">
                        <div class="p-3 bg-warning bg-opacity-10 text-warning rounded-circle d-inline-flex mb-3">
                            <i class="bi bi-key-fill fs-2"></i>
                        </div>
                        <h2 class="fw-bold mb-1">Reset Password</h2>
                        <p class="text-muted small">Enter your email address to receive password reset instructions.</p>
                    </div>

                    <?php if ($message): ?>
                        <div class="alert alert-success rounded-3 mb-4"><i class="bi bi-check-circle-fill me-2"></i> <?= e($message) ?></div>
                    <?php endif; ?>

                    <?php if ($error): ?>
                        <div class="alert alert-danger rounded-3 mb-4"><i class="bi bi-exclamation-triangle-fill me-2"></i> <?= e($error) ?></div>
                    <?php endif; ?>

                    <form action="<?= url('auth/forgot-password.php') ?>" method="POST">
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Email Address</label>
                            <input type="email" name="email" class="form-control form-control-lg rounded-3 fs-6" required placeholder="name@example.com">
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg rounded-pill w-100 py-3 fw-bold mb-3 shadow" data-feedback="click">
                            Send Reset Link <i class="bi bi-arrow-right ms-2"></i>
                        </button>

                        <div class="text-center text-muted small">
                            Remember your password? <a href="<?= url('auth/login.php') ?>" class="text-primary fw-bold text-decoration-none">Return to login</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include BASE_PATH . '/includes/layouts/footer.php'; ?>
