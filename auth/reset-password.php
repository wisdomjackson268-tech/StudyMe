<?php
/**
 * StudyMe AI Platform — Reset Password Form
 */
require_once dirname(__DIR__) . '/config/main.php';

$token  = trim($_GET['token'] ?? '');
$errors = [];
$success= false;

if (is_post()) {
    $token   = trim($_POST['token'] ?? '');
    $pass    = $_POST['password'] ?? '';
    $passC   = $_POST['password_confirmation'] ?? '';

    if (strlen($pass) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    }
    if ($pass !== $passC) {
        $errors[] = 'Passwords do not match.';
    }

    if (empty($errors) && !empty($token)) {
        $pdo  = getDBConnection();
        $stmt = $pdo->prepare("SELECT user_id FROM password_resets WHERE token = ? AND expires_at > NOW() LIMIT 1");
        $stmt->execute([$token]);
        $reset = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($reset) {
            $hashed = password_hash($pass, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hashed, $reset['user_id']]);
            $pdo->prepare("DELETE FROM password_resets WHERE token = ?")->execute([$token]);
            $success = true;
        } else {
            $errors[] = 'Invalid or expired password reset link. Please request a new one.';
        }
    }
}

include BASE_PATH . '/includes/layouts/header.php';
?>

<div class="py-5 bg-light-subtle d-flex align-items-center" style="min-height: calc(100vh - 120px);">
    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-md-7 col-lg-5">
                <div class="card border-0 shadow-lg rounded-4 p-4 p-md-5">
                    <div class="text-center mb-4">
                        <div class="p-3 bg-primary bg-opacity-10 text-primary rounded-circle d-inline-flex mb-3 fs-2">
                            <i class="bi bi-key-fill"></i>
                        </div>
                        <h2 class="fw-bold mb-1">Set New Password</h2>
                        <p class="text-muted small">Enter a strong, secure password for your account.</p>
                    </div>

                    <?php if ($success): ?>
                        <div class="alert alert-success rounded-3 text-center mb-4">
                            <i class="bi bi-check-circle-fill me-1"></i> Your password has been successfully reset!
                        </div>
                        <a href="<?= url('auth/login.php') ?>" class="btn btn-primary btn-lg rounded-pill w-100 fw-bold">Sign In Now</a>
                    <?php else: ?>
                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger rounded-3 mb-4">
                                <ul class="mb-0 ps-3 small">
                                    <?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <form method="POST">
                            <input type="hidden" name="token" value="<?= e($token) ?>">
                            <div class="mb-3">
                                <label class="form-label small fw-bold">New Password</label>
                                <input type="password" name="password" class="form-control form-control-lg rounded-3 fs-6" required placeholder="Min 6 characters">
                            </div>
                            <div class="mb-4">
                                <label class="form-label small fw-bold">Confirm New Password</label>
                                <input type="password" name="password_confirmation" class="form-control form-control-lg rounded-3 fs-6" required placeholder="Repeat password">
                            </div>
                            <button type="submit" class="btn btn-primary btn-lg rounded-pill w-100 py-3 fw-bold shadow">
                                Update Password <i class="bi bi-arrow-right ms-1"></i>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include BASE_PATH . '/includes/layouts/footer.php'; ?>
