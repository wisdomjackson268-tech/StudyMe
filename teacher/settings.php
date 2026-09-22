<?php

require_once dirname(__DIR__) . '/config/main.php';
require_once BASE_PATH . '/includes/functions/users.php';
require_once BASE_PATH . '/includes/functions/activity.php';

secure_page(ROLE_TEACHER);

$user = current_user();
$userId = (int)$user['id'];
$errors = [];
$pdo = getDBConnection();

// 1. Handle Password Change
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
        log_user_activity($userId, 'password_changed', 'Instructor changed account password');
        set_flash('success', 'Your instructor password has been changed successfully.');
        redirect('teacher/settings.php');
    }
}

// 2. Handle Instructor Account Disablement
if (is_post() && isset($_POST['disable_account'])) {
    $confirmPassword = $_POST['confirm_password_disable'] ?? '';
    $disableReason   = trim($_POST['disable_reason'] ?? 'Not specified');
    $userRecord      = get_user_by_id($userId);

    if (!password_verify($confirmPassword, $userRecord['password'])) {
        $errors[] = 'Incorrect password entered. Account could not be disabled.';
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE users SET status = 'inactive' WHERE id = ?");
            $stmt->execute([$userId]);

            log_user_activity($userId, 'account_disabled', "Instructor disabled account. Reason: {$disableReason}");

            $_SESSION = [];
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000,
                    $params["path"], $params["domain"],
                    $params["secure"], $params["httponly"]
                );
            }
            session_destroy();

            session_start();
            set_flash('info', 'Your instructor account has been deactivated. You can contact support if you wish to reactivate your access.');
            redirect('auth/login.php');
            exit;
        } catch (Exception $e) {
            error_log("Error disabling instructor account: " . $e->getMessage());
            $errors[] = 'An error occurred while disabling your account. Please try again.';
        }
    }
}

include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
    <div>
        <h1 class="h3 fw-bold mb-1">
            <span class="p-2 bg-primary bg-opacity-10 text-primary rounded-3 d-inline-flex align-items-center justify-content-center me-2" style="width:38px; height:38px;">
                <i class="bi bi-gear-fill fs-5"></i>
            </span>
            Instructor Account Settings
        </h1>
        <p class="text-muted mb-0 small">Manage your password security, appearance preferences, and account status.</p>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-12 col-lg-8">

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger rounded-4 p-3 mb-4 shadow-sm">
                <div class="d-flex align-items-center gap-2 mb-2 fw-bold">
                    <i class="bi bi-exclamation-triangle-fill fs-5"></i>
                    <span>Please correct the following:</span>
                </div>
                <ul class="mb-0 small ps-3">
                    <?php foreach ($errors as $err): ?>
                        <li><?= e($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- 1. CHANGE PASSWORD CARD -->
        <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 mb-4 bg-white">
            <h4 class="fw-bold mb-4 text-dark d-flex align-items-center gap-2">
                <i class="bi bi-shield-lock-fill text-primary"></i>
                <span>Change Password</span>
            </h4>

            <form action="<?= url('teacher/settings.php') ?>" method="POST">
                <input type="hidden" name="change_password" value="1">

                <div class="mb-3">
                    <label for="currentPassword" class="form-label fw-bold small">Current Password <span class="text-danger">*</span></label>
                    <input type="password" name="current_password" id="currentPassword" class="form-control py-2 rounded-3" placeholder="Enter current password" required>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label for="newPassword" class="form-label fw-bold small">New Password <span class="text-danger">*</span></label>
                        <input type="password" name="new_password" id="newPassword" class="form-control py-2 rounded-3" placeholder="Min. 6 characters" required>
                    </div>
                    <div class="col-md-6">
                        <label for="confirmPassword" class="form-label fw-bold small">Confirm New Password <span class="text-danger">*</span></label>
                        <input type="password" name="confirm_password" id="confirmPassword" class="form-control py-2 rounded-3" placeholder="Repeat new password" required>
                    </div>
                </div>

                <button type="submit" class="btn btn-warning rounded-pill px-4 fw-bold text-dark shadow-sm" data-feedback="click">
                    <i class="bi bi-key-fill me-1"></i> Update Password
                </button>
            </form>
        </div>

        <!-- 2. PLATFORM PREFERENCES CARD -->
        <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 mb-4 bg-white">
            <h4 class="fw-bold mb-4 text-dark d-flex align-items-center gap-2">
                <i class="bi bi-sliders text-primary"></i>
                <span>Platform Preferences</span>
            </h4>

            <div class="d-flex align-items-center justify-content-between py-3 border-bottom">
                <div>
                    <h6 class="fw-bold mb-1 text-dark">Theme Preferences</h6>
                    <p class="text-muted small mb-0">Switch between Light and Dark mode appearance.</p>
                </div>
                <button type="button" class="btn btn-outline-secondary rounded-pill px-4 theme-btn" data-feedback="click">
                    <i class="bi bi-moon-stars me-1"></i> Toggle Theme
                </button>
            </div>

            <div class="d-flex align-items-center justify-content-between py-3 border-bottom">
                <div>
                    <h6 class="fw-bold mb-1 text-dark">Interactive Sound Effects</h6>
                    <p class="text-muted small mb-0">Enable or mute UI audio feedback on action completions.</p>
                </div>
                <button type="button" class="btn btn-outline-secondary rounded-pill px-4 sound-btn" data-feedback="none">
                    <i class="bi bi-volume-up me-1"></i> Toggle Sound
                </button>
            </div>

            <div class="d-flex align-items-center justify-content-between py-3">
                <div>
                    <h6 class="fw-bold mb-1 text-dark">Profile Details</h6>
                    <p class="text-muted small mb-0">Update your instructor bio, qualifications, and avatar.</p>
                </div>
                <a href="<?= url('teacher/profile.php') ?>" class="btn btn-outline-primary rounded-pill px-4 fw-semibold">
                    <i class="bi bi-person-badge me-1"></i> Edit Profile
                </a>
            </div>
        </div>

        <!-- 3. DANGER ZONE: DISABLE / DEACTIVATE ACCOUNT -->
        <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 mb-4 bg-white border-top border-4 border-danger">
            <div class="d-flex align-items-center gap-2 mb-2 text-danger">
                <i class="bi bi-exclamation-octagon-fill fs-4"></i>
                <h4 class="fw-bold mb-0">Danger Zone</h4>
            </div>
            <h5 class="fw-bold text-dark mb-2">Disable / Deactivate Instructor Account</h5>
            <p class="text-muted small mb-4 lh-base">
                Temporarily deactivating your instructor account will unpublish active courses, disable login access, and sign you out across all devices. 
                Your course materials, earnings history, and student records remain preserved.
            </p>

            <div>
                <button type="button" class="btn btn-outline-danger rounded-pill px-4 fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#disableAccountModal">
                    <i class="bi bi-person-x-fill me-1"></i> Disable Instructor Account
                </button>
            </div>
        </div>

    </div>
</div>

<!-- DISABLE ACCOUNT CONFIRMATION MODAL -->
<div class="modal fade" id="disableAccountModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-danger">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>Deactivate Instructor Account
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="<?= url('teacher/settings.php') ?>" method="POST">
                <input type="hidden" name="disable_account" value="1">
                
                <div class="modal-body py-4">
                    <div class="alert alert-warning rounded-3 small p-3 mb-3">
                        <i class="bi bi-info-circle-fill me-1"></i>
                        Are you sure you want to deactivate your StudyMe instructor account? You will be signed out immediately.
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Reason for Deactivation (Optional):</label>
                        <select name="disable_reason" class="form-select rounded-3">
                            <option value="Taking a teaching sabbatical">Taking a teaching break</option>
                            <option value="Switching institutions">Switching teaching role</option>
                            <option value="Personal reasons">Personal reasons</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="confirmPasswordDisableTeacher" class="form-label small fw-bold text-dark">
                            Confirm Your Password <span class="text-danger">*</span>
                        </label>
                        <input type="password" name="confirm_password_disable" id="confirmPasswordDisableTeacher" class="form-control rounded-3" placeholder="Enter your current password" required>
                    </div>

                    <div class="form-check small text-muted">
                        <input class="form-check-input" type="checkbox" id="confirmCheckboxTeacher" required>
                        <label class="form-check-label" for="confirmCheckboxTeacher">
                            I understand that my account and courses will become inactive until reactivation.
                        </label>
                    </div>
                </div>

                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger rounded-pill px-4 fw-bold shadow-sm">
                        <i class="bi bi-person-x-fill me-1"></i> Confirm Deactivation
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
