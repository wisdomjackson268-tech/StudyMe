<?php
/**
 * StudyMe AI Platform — Student Settings Page
 */
require_once dirname(__DIR__) . '/config/main.php';
require_once BASE_PATH . '/includes/functions/users.php';

secure_page(ROLE_STUDENT);

$user = current_user();
$userId = $user['id'];
$errors = [];

if (is_post() && isset($_POST['change_password'])) {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword     = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    $userRecord = get_user_by_id($userId);

    if (!password_verify($currentPassword, $userRecord['password'])) {
        $errors[] = 'Your current password was entered incorrectly.';
    }
    if (strlen($newPassword) < 6) {
        $errors[] = 'New password must be at least 6 characters long.';
    }
    if ($newPassword !== $confirmPassword) {
        $errors[] = 'New passwords do not match.';
    }

    if (empty($errors)) {
        update_user_password($userId, $newPassword);
        set_flash('success', 'Your password has been changed successfully.');
        redirect('student/settings.php');
    }
}

include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
    <div>
        <h1 class="h3 fw-bold mb-1"><i class="bi bi-gear-fill text-primary me-2"></i> Account Settings</h1>
        <p class="text-muted mb-0">Manage password security, platform preferences, and notification preferences.</p>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <!-- Change Password Card -->
        <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 mb-4">
            <h4 class="fw-bold mb-4 text-dark"><i class="bi bi-shield-lock-fill text-primary me-2"></i> Change Password</h4>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger rounded-3 p-3 mb-4 shadow-sm">
                    <ul class="mb-0 small ps-3">
                        <?php foreach ($errors as $err): ?>
                            <li><?= e($err) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form action="<?= url('student/settings.php') ?>" method="POST">
                <input type="hidden" name="change_password" value="1">
                
                <div class="mb-3">
                    <label for="currentPassword" class="form-label fw-semibold small">Current Password <span class="text-danger">*</span></label>
                    <input type="password" name="current_password" id="currentPassword" class="form-control py-2 rounded-3" required>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label for="newPassword" class="form-label fw-semibold small">New Password <span class="text-danger">*</span></label>
                        <input type="password" name="new_password" id="newPassword" class="form-control py-2 rounded-3" placeholder="Min. 6 characters" required>
                    </div>
                    <div class="col-md-6">
                        <label for="confirmPassword" class="form-label fw-semibold small">Confirm New Password <span class="text-danger">*</span></label>
                        <input type="password" name="confirm_password" id="confirmPassword" class="form-control py-2 rounded-3" placeholder="Repeat new password" required>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold" data-feedback="click">
                    <i class="bi bi-key-fill me-1"></i> Update Password
                </button>
            </form>
        </div>

        <!-- Preferences Card -->
        <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 mb-4">
            <h4 class="fw-bold mb-4 text-dark"><i class="bi bi-sliders text-primary me-2"></i> Platform Preferences</h4>

            <div class="d-flex align-items-center justify-content-between py-3 border-bottom">
                <div>
                    <h6 class="fw-bold mb-1">Theme Preferences</h6>
                    <p class="text-muted small mb-0">Switch between Light and Dark mode appearance.</p>
                </div>
                <button type="button" class="btn btn-outline-secondary rounded-pill px-4 theme-btn" data-feedback="click">
                    Toggle Theme
                </button>
            </div>

            <div class="d-flex align-items-center justify-content-between py-3">
                <div>
                    <h6 class="fw-bold mb-1">UI Sound Effects</h6>
                    <p class="text-muted small mb-0">Enable audio feedback on button clicks and notifications.</p>
                </div>
                <button type="button" class="btn btn-outline-secondary rounded-pill px-4 sound-btn" data-feedback="click">
                    Toggle Sounds
                </button>
            </div>
        </div>
    </div>
</div>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
