<?php
/**
 * StudyMe AI Platform — Email Verification Interface
 */
require_once dirname(__DIR__) . '/config/main.php';

$token   = trim($_GET['token'] ?? '');
$success = false;
$message = '';

if (!empty($token)) {
    $pdo  = getDBConnection();
    $stmt = $pdo->prepare("SELECT id, first_name FROM users WHERE MD5(CONCAT(email, id)) = ? LIMIT 1");
    $stmt->execute([$token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $pdo->prepare("UPDATE users SET email_verified_at = NOW(), status = 'active' WHERE id = ?")->execute([$user['id']]);
        $success = true;
        $message = "Email successfully verified! Welcome to StudyMe, {$user['first_name']}.";
    } else {
        $message = "Invalid or expired verification link.";
    }
} else {
    $message = "Please check your inbox and click the verification link sent to your email.";
}

include BASE_PATH . '/includes/layouts/header.php';
?>

<div class="py-5 bg-light-subtle d-flex align-items-center" style="min-height: calc(100vh - 120px);">
    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-md-7 col-lg-5 text-center">
                <div class="card border-0 shadow-lg rounded-4 p-4 p-md-5">
                    <div class="p-3 bg-<?= $success ? 'success' : 'primary' ?> bg-opacity-10 text-<?= $success ? 'success' : 'primary' ?> rounded-circle d-inline-flex mx-auto mb-3 fs-1">
                        <i class="bi bi-<?= $success ? 'patch-check-fill' : 'envelope-check-fill' ?>"></i>
                    </div>
                    <h3 class="fw-bold mb-2"><?= $success ? 'Email Verified!' : 'Email Verification' ?></h3>
                    <p class="text-muted mb-4"><?= e($message) ?></p>

                    <div class="d-flex justify-content-center gap-2">
                        <?php if (is_logged_in()): ?>
                            <a href="<?= url('student/dashboard.php') ?>" class="btn btn-primary rounded-pill px-4 fw-bold">Go to Dashboard</a>
                        <?php else: ?>
                            <a href="<?= url('auth/login.php') ?>" class="btn btn-primary rounded-pill px-4 fw-bold">Sign In to Account</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include BASE_PATH . '/includes/layouts/footer.php'; ?>
