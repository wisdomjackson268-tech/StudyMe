<?php
/**
 * StudyMe AI Platform — Admin Profile & Security Page
 */
require_once dirname(__DIR__) . '/config/main.php';
require_once BASE_PATH . '/includes/functions/users.php';
require_once BASE_PATH . '/includes/functions/uploads.php';

secure_page(ROLE_ADMIN);
$user = current_user();
$userId = (int)$user['id'];
$pdo  = getDBConnection();
$errors = [];

// Handle Profile Details & Avatar Update
if (is_post() && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $firstName      = trim($_POST['first_name'] ?? '');
    $lastName       = trim($_POST['last_name'] ?? '');
    $avatarUrlInput = trim($_POST['avatar_url'] ?? '');

    $finalAvatar = $user['avatar'] ?? '';
    if (isset($_FILES['avatar_file']) && $_FILES['avatar_file']['error'] === UPLOAD_ERR_OK) {
        $imgRes = upload_image($_FILES['avatar_file'], 'avatars');
        if ($imgRes['success']) {
            $finalAvatar = $imgRes['relative_path'];
        } else {
            $errors[] = 'Avatar upload failed: ' . $imgRes['error'];
        }
    } elseif (!empty($avatarUrlInput)) {
        $finalAvatar = $avatarUrlInput;
    }

    if (!empty($firstName) && !empty($lastName) && empty($errors)) {
        try {
            $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, avatar = ?, updated_at = NOW() WHERE id = ?")
                ->execute([$firstName, $lastName, $finalAvatar, $userId]);
            
            $_SESSION[SESSION_USER_DATA]['first_name'] = $firstName;
            $_SESSION[SESSION_USER_DATA]['last_name']  = $lastName;
            $_SESSION[SESSION_USER_DATA]['avatar']     = $finalAvatar;
            
            set_flash('success', 'Admin profile and photo updated successfully!');
            redirect('admin/profile.php');
        } catch (Exception $e) {
            set_flash('error', 'Update error: ' . $e->getMessage());
        }
    } else {
        if (empty($firstName) || empty($lastName)) {
            $errors[] = 'First name and last name are required.';
        }
    }
}

// Handle Password Change
if (is_post() && isset($_POST['action']) && $_POST['action'] === 'change_password') {
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
        set_flash('success', 'Admin password changed successfully.');
        redirect('admin/profile.php');
    }
}

// Refresh current user data
$user = current_user();

include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <p class="text-muted mb-1 fw-semibold text-uppercase small"><i class="bi bi-person-circle text-primary me-1"></i> Admin Command Center</p>
        <h2 class="fw-bold mb-0">Administrator Profile &amp; Security</h2>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger rounded-4 p-3 mb-4 shadow-sm max-w-2xl">
        <ul class="mb-0 small ps-3">
            <?php foreach ($errors as $err): ?>
                <li><?= e($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="row g-4">
    <!-- Profile Information Card -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm rounded-4 p-4 h-100 bg-white">
            <h4 class="fw-bold mb-4 text-dark"><i class="bi bi-person-lines-fill text-primary me-2"></i> Profile &amp; Photo</h4>
            <form action="<?= url('admin/profile.php') ?>" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="update_profile">

                <!-- Avatar Upload & Preview -->
                <div class="p-3 bg-light rounded-4 mb-4 border d-flex align-items-center gap-3">
                    <?php $adminAvatar = function_exists('get_avatar_url') ? get_avatar_url($user['avatar'] ?? null, $user['first_name'] ?? 'Admin') : ($user['avatar'] ?? ''); ?>
                    <img src="<?= e($adminAvatar) ?>" 
                         id="adminAvatarPreview"
                         class="rounded-circle border border-3 border-primary shadow-sm"
                         style="width: 76px; height: 76px; object-fit: cover;"
                         alt="<?= e($user['first_name']) ?>"
                         onerror="this.onerror=null; this.src='https://ui-avatars.com/api/?name=<?= urlencode($user['first_name'] ?? 'Admin') ?>&background=4f46e5&color=ffffff&bold=true';">
                    <div class="flex-grow-1">
                        <label class="form-label fw-bold small text-dark mb-1">Update Avatar Photo</label>
                        <input type="file" name="avatar_file" id="adminAvatarFile" class="form-control form-control-sm rounded-pill mb-1" accept="image/jpeg,image/png,image/webp,image/gif">
                        <div class="text-muted" style="font-size:0.75rem;">JPG, PNG, WEBP (Max 10MB)</div>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold small">First Name</label>
                        <input type="text" name="first_name" class="form-control rounded-3" value="<?= e($user['first_name']) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small">Last Name</label>
                        <input type="text" name="last_name" class="form-control rounded-3" value="<?= e($user['last_name']) ?>" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold small">Email Address</label>
                    <input type="email" class="form-control rounded-3 bg-light" value="<?= e($user['email']) ?>" readonly>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-bold small">Or Avatar URL (Optional)</label>
                    <input type="url" name="avatar_url" id="adminAvatarUrl" class="form-control rounded-3" value="<?= (strpos($user['avatar'] ?? '', 'http') === 0) ? e($user['avatar']) : '' ?>" placeholder="https://images.unsplash.com/...">
                </div>

                <button type="submit" class="btn btn-primary rounded-pill px-5 fw-bold py-2 shadow-sm" data-feedback="click">
                    <i class="bi bi-save me-1"></i> Save Changes
                </button>
            </form>
        </div>
    </div>

    <!-- Password Security Card -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm rounded-4 p-4 h-100 bg-white">
            <h4 class="fw-bold mb-4 text-dark"><i class="bi bi-shield-lock-fill text-primary me-2"></i> Change Password</h4>
            <form action="<?= url('admin/profile.php') ?>" method="POST">
                <input type="hidden" name="action" value="change_password">
                
                <div class="mb-3">
                    <label for="currentPassword" class="form-label fw-bold small">Current Password</label>
                    <input type="password" name="current_password" id="currentPassword" class="form-control rounded-3" required>
                </div>

                <div class="mb-3">
                    <label for="newPassword" class="form-label fw-bold small">New Password</label>
                    <input type="password" name="new_password" id="newPassword" class="form-control rounded-3" placeholder="Min. 6 characters" required>
                </div>

                <div class="mb-4">
                    <label for="confirmPassword" class="form-label fw-bold small">Confirm New Password</label>
                    <input type="password" name="confirm_password" id="confirmPassword" class="form-control rounded-3" placeholder="Repeat new password" required>
                </div>

                <button type="submit" class="btn btn-warning rounded-pill px-5 fw-bold py-2 text-dark shadow-sm" data-feedback="click">
                    <i class="bi bi-key me-1"></i> Update Password
                </button>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const fileIn = document.getElementById('adminAvatarFile');
    const prevImg = document.getElementById('adminAvatarPreview');
    const urlIn = document.getElementById('adminAvatarUrl');

    if (fileIn) {
        fileIn.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    if (prevImg) prevImg.src = e.target.result;
                };
                reader.readAsDataURL(this.files[0]);
            }
        });
    }

    if (urlIn) {
        urlIn.addEventListener('input', function() {
            if (this.value.trim().length > 5) {
                if (prevImg) prevImg.src = this.value.trim();
            }
        });
    }
});
</script>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>

