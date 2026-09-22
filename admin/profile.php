<?php

require_once dirname(__DIR__) . '/config/main.php';
require_once BASE_PATH . '/includes/functions/users.php';
require_once BASE_PATH . '/includes/functions/uploads.php';

secure_page(ROLE_ADMIN);

$user   = current_user();
$userId = (int)$user['id'];
$pdo    = getDBConnection();

$errors    = [];
$activeTab = $_GET['tab'] ?? 'details';

if (is_post() && isset($_POST['action']) && $_POST['action'] === 'save_profile') {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName  = trim($_POST['last_name'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');

    $finalAvatar = $user['avatar'] ?? '';
    if (isset($_FILES['avatar_file']) && $_FILES['avatar_file']['error'] === UPLOAD_ERR_OK) {
        $imgRes = upload_image($_FILES['avatar_file'], 'avatars');
        if ($imgRes['success']) {
            $finalAvatar = $imgRes['relative_path'];
        } else {
            $errors[] = 'Avatar upload failed: ' . $imgRes['error'];
        }
    }

    if (empty($firstName) || empty($lastName)) {
        $errors[] = 'First name and last name are required.';
    }

    if (empty($errors)) {
        try {
            $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, phone = ?, avatar = ?, updated_at = NOW() WHERE id = ?")
                ->execute([$firstName, $lastName, $phone, $finalAvatar, $userId]);

            $_SESSION[SESSION_USER_DATA]['first_name'] = $firstName;
            $_SESSION[SESSION_USER_DATA]['last_name']  = $lastName;
            $_SESSION[SESSION_USER_DATA]['avatar']     = $finalAvatar;

            $_SESSION['auth_success_vibrate'] = true;
            set_flash('success', 'Administrator profile details and photo updated successfully.');
            redirect('admin/profile.php?tab=details');
        } catch (Exception $e) {
            $errors[] = 'Update error: ' . $e->getMessage();
        }
    }
}

if (is_post() && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $activeTab       = 'security';
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword     = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    $userRecord = get_user_by_id($userId);

    if (!password_verify($currentPassword, $userRecord['password'])) {
        $errors[] = 'Current password entered was incorrect.';
    }
    if (strlen($newPassword) < 6) {
        $errors[] = 'New password must be at least 6 characters long.';
    }
    if ($newPassword !== $confirmPassword) {
        $errors[] = 'New passwords do not match.';
    }

    if (empty($errors)) {
        update_user_password($userId, $newPassword);
        $_SESSION['auth_success_vibrate'] = true;
        set_flash('success', 'Admin master password changed securely.');
        redirect('admin/profile.php?tab=security');
    }
}

$userRecord = get_user_by_id($userId);

include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<style>
.profile-photo-picker { border: 1px dashed rgba(13, 110, 253, .45); background: linear-gradient(135deg, rgba(13, 110, 253, .06), rgba(25, 135, 84, .06)); transition: border-color .2s ease, background .2s ease; cursor: pointer; }
.profile-photo-picker:hover, .profile-photo-picker:focus-within { border-color: rgba(13, 110, 253, .9); background: linear-gradient(135deg, rgba(13, 110, 253, .11), rgba(25, 135, 84, .09)); }
.profile-photo-picker input[type="file"] { position: absolute; width: 1px; height: 1px; opacity: 0; pointer-events: none; }
</style>

<div class="card border-0 shadow-sm rounded-4 p-4 mb-4 bg-surface">
    <div class="row align-items-center g-4">
        <div class="col-md-auto text-center text-md-start">
            <div class="position-relative d-inline-block">
                <?php $admAvatarUrl = function_exists('get_avatar_url') ? get_avatar_url($userRecord['avatar'] ?? null, $userRecord['first_name'] ?? 'Admin') : ($userRecord['avatar'] ?? ''); ?>
                <img src="<?= e($admAvatarUrl) ?>"
                     id="headerAvatarImg"
                     alt="Avatar"
                     class="rounded-circle border border-3 border-danger shadow-sm"
                     style="width: 100px; height: 100px; object-fit: cover;"
                     onerror="this.onerror=null; this.src='https://ui-avatars.com/api/?name=<?= urlencode($userRecord['first_name'] ?? 'Admin') ?>&background=dc2626&color=ffffff&bold=true';">
                <label for="avatarFileInput" class="position-absolute bottom-0 end-0 bg-danger text-white rounded-circle p-2 shadow cursor-pointer d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;" title="Change Photo">
                    <i class="bi bi-camera-fill small"></i>
                </label>
            </div>
        </div>

        <div class="col-md">
            <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                <h2 class="h4 fw-bold mb-0 text-main"><?= e($userRecord['first_name'] . ' ' . $userRecord['last_name']) ?></h2>
                <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 rounded-pill px-3 py-1 fw-bold small">
                    <i class="bi bi-shield-lock-fill me-1"></i> System Administrator
                </span>
                <span class="badge bg-success-subtle text-success border border-success border-opacity-25 rounded-pill px-3 py-1 fw-bold small">
                    <i class="bi bi-check-circle-fill me-1"></i> Root Access
                </span>
            </div>

            <div class="d-flex align-items-center gap-3 flex-wrap text-muted small mb-2">
                <span><i class="bi bi-hash me-1"></i> Admin ID: <strong class="text-main font-monospace">ADM-<?= str_pad($userId, 4, '0', STR_PAD_LEFT) ?></strong></span>
                <span>&bull;</span>
                <span><i class="bi bi-envelope me-1"></i> <?= e($userRecord['email']) ?></span>
                <?php if (!empty($userRecord['phone'])): ?>
                    <span>&bull;</span>
                    <span><i class="bi bi-telephone me-1"></i> <?= e($userRecord['phone']) ?></span>
                <?php endif; ?>
            </div>

            <div class="text-muted small">
                Administrator since <?= date('M d, Y', strtotime($userRecord['created_at'])) ?>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger rounded-4 p-3 mb-4 shadow-sm">
        <ul class="mb-0 small ps-3">
            <?php foreach ($errors as $err): ?>
                <li><?= e($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="row g-4">

    <div class="col-md-3">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-surface">
            <div class="nav flex-column nav-pills gap-1" id="adminProfileTab" role="tablist">
                <a class="nav-link text-start rounded-3 py-2.5 px-3 d-flex align-items-center gap-2 <?= $activeTab === 'details' ? 'active bg-primary text-white' : 'text-main' ?>"
                   href="<?= url('admin/profile.php?tab=details') ?>">
                    <i class="bi bi-person-lines-fill"></i>
                    <span class="fw-semibold">Edit Admin Info</span>
                </a>
                <a class="nav-link text-start rounded-3 py-2.5 px-3 d-flex align-items-center gap-2 <?= $activeTab === 'security' ? 'active bg-primary text-white' : 'text-main' ?>"
                   href="<?= url('admin/profile.php?tab=security') ?>">
                    <i class="bi bi-shield-lock-fill"></i>
                    <span class="fw-semibold">Account &amp; Security</span>
                </a>
                <a class="nav-link text-start rounded-3 py-2.5 px-3 d-flex align-items-center gap-2 text-main"
                   href="<?= url('admin/system-settings.php') ?>">
                    <i class="bi bi-sliders text-secondary"></i>
                    <span class="fw-semibold">System Settings</span>
                </a>
                <a class="nav-link text-start rounded-3 py-2.5 px-3 d-flex align-items-center gap-2 text-main"
                   href="<?= url('admin/activity.php') ?>">
                    <i class="bi bi-activity text-primary"></i>
                    <span class="fw-semibold">Audit Logs</span>
                </a>
            </div>
        </div>
    </div>

    <div class="col-md-9">
        <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 bg-surface">
            <?php if ($activeTab === 'security'): ?>

                <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
                    <div>
                        <h4 class="fw-bold mb-1">Administrator Master Password</h4>
                        <p class="text-muted small mb-0">Update the administrative access password for platform management.</p>
                    </div>
                </div>

                <form action="<?= url('admin/profile.php?tab=security') ?>" method="POST">
                    <input type="hidden" name="action" value="change_password">

                    <div class="mb-3">
                        <label for="currentPassword" class="form-label fw-semibold small">Current Password <span class="text-danger">*</span></label>
                        <input type="password" name="current_password" id="currentPassword" class="form-control rounded-3 py-2" required>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label for="newPassword" class="form-label fw-semibold small">New Master Password <span class="text-danger">*</span></label>
                            <input type="password" name="new_password" id="newPassword" class="form-control rounded-3 py-2" placeholder="Minimum 6 characters" required>
                        </div>
                        <div class="col-md-6">
                            <label for="confirmPassword" class="form-label fw-semibold small">Confirm New Password <span class="text-danger">*</span></label>
                            <input type="password" name="confirm_password" id="confirmPassword" class="form-control rounded-3 py-2" placeholder="Re-enter new password" required>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-danger rounded-pill px-5 fw-bold shadow-sm">
                        <i class="bi bi-shield-check me-1"></i> Update Master Password
                    </button>
                </form>

            <?php else: ?>

                <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
                    <div>
                        <h4 class="fw-bold mb-1">Administrator Profile Details</h4>
                        <p class="text-muted small mb-0">Update your name, contact phone, and administrative avatar.</p>
                    </div>
                </div>

                <form action="<?= url('admin/profile.php?tab=details') ?>" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="save_profile">

                    <label for="avatarFileInput" class="profile-photo-picker p-3 rounded-4 mb-4 d-flex align-items-center justify-content-between flex-wrap gap-3">
                        <div class="d-flex align-items-center gap-3">
                            <img src="<?= e($admAvatarUrl) ?>"
                                 id="avatarPreviewSnippet"
                                 alt="Avatar"
                                 class="rounded-circle border"
                                 style="width: 50px; height: 50px; object-fit: cover;"
                                 onerror="this.onerror=null; this.src='https://ui-avatars.com/api/?name=<?= urlencode($userRecord['first_name'] ?? 'Admin') ?>&background=dc2626&color=ffffff&bold=true';">
                            <div>
                                <div class="fw-bold text-main"><i class="bi bi-cloud-arrow-up-fill text-primary me-2"></i>Update profile picture</div>
                                <div class="text-muted small">Click anywhere here to choose a JPG, PNG or WebP image up to 10 MB.</div>
                            </div>
                        </div>
                        <span class="btn btn-primary btn-sm rounded-pill px-3"><i class="bi bi-image me-1"></i>Choose image</span>
                        <input type="file" name="avatar_file" id="avatarFileInput" accept="image/jpeg,image/png,image/webp">
                    </label>

                    <h5 class="fw-bold fs-6 mb-3 text-primary"><i class="bi bi-person-fill me-1"></i> Personal Information</h5>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label for="firstName" class="form-label fw-semibold small">First Name <span class="text-danger">*</span></label>
                            <input type="text" name="first_name" id="firstName" class="form-control rounded-3 py-2" value="<?= e($userRecord['first_name']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="lastName" class="form-label fw-semibold small">Last Name <span class="text-danger">*</span></label>
                            <input type="text" name="last_name" id="lastName" class="form-control rounded-3 py-2" value="<?= e($userRecord['last_name']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="phone" class="form-label fw-semibold small">Phone Number</label>
                            <input type="tel" name="phone" id="phone" class="form-control rounded-3 py-2" value="<?= e($userRecord['phone']) ?>" placeholder="+234 800 000 0000">
                        </div>
                    </div>

                    <h5 class="fw-bold fs-6 mb-3 text-primary"><i class="bi bi-lock-fill me-1"></i> System Protected Credentials (Locked)</h5>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small d-flex justify-content-between align-items-center">
                                <span>Root Admin Email</span>
                                <span class="badge bg-light text-muted border" style="font-size:0.7rem;"><i class="bi bi-shield-check text-success me-1"></i> Verified &amp; Locked</span>
                            </label>
                            <input type="email" class="form-control rounded-3 py-2 bg-body-tertiary" value="<?= e($userRecord['email']) ?>" readonly disabled>
                            <div class="form-text small">System root administrator login email is immutable.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small d-flex justify-content-between align-items-center">
                                <span>Administrator Staff ID</span>
                                <span class="badge bg-light text-muted border" style="font-size:0.7rem;"><i class="bi bi-lock-fill text-muted me-1"></i> System Assigned</span>
                            </label>
                            <input type="text" class="form-control rounded-3 py-2 bg-body-tertiary font-monospace" value="ADM-<?= str_pad($userId, 4, '0', STR_PAD_LEFT) ?>" readonly disabled>
                            <div class="form-text small">Master system administrator identifier.</div>
                        </div>
                    </div>

                    <div class="pt-3 border-top d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary rounded-pill px-5 py-2.5 fw-bold shadow-sm" data-feedback="click">
                            <i class="bi bi-check2-circle me-1"></i> Save Administrator Changes
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const fileInput  = document.getElementById('avatarFileInput');
    const headerImg  = document.getElementById('headerAvatarImg');
    const snippetImg = document.getElementById('avatarPreviewSnippet');

    if (fileInput) {
        fileInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    if (headerImg) headerImg.src = e.target.result;
                    if (snippetImg) snippetImg.src = e.target.result;
                };
                reader.readAsDataURL(this.files[0]);
            }
        });
    }
});
</script>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
