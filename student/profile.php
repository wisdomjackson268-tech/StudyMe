<?php
/**
 * StudyMe AI Platform — Student Profile Management & Enrollment Setup
 */
require_once dirname(__DIR__) . '/config/main.php';
require_once BASE_PATH . '/includes/functions/users.php';
require_once BASE_PATH . '/includes/functions/uploads.php';
require_once BASE_PATH . '/includes/functions/courses.php';
require_once BASE_PATH . '/includes/functions/pricing.php';

secure_page(ROLE_STUDENT);

$user = current_user();
$pdo = getDBConnection();
$userId = (int)$user['id'];

// Get fresh user record
$userRecord = get_user_by_id($userId);
$studentRecord = get_student_by_user_id($userId);

// Check if user is in an enrollment flow
$pendingCourseId = (int)($_SESSION['pending_course_id'] ?? 0);
$isEnrollFlow = ($pendingCourseId > 0) || !empty($_GET['enroll']);
$pendingCourse = null;
$pendingPrice = 0.00;

if ($pendingCourseId > 0) {
    $pendingCourse = get_course_by_id($pendingCourseId);
    if ($pendingCourse) {
        $pendingPrice = get_course_official_price($pendingCourseId);
    }
}

$errors = [];

if (is_post()) {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName  = trim($_POST['last_name'] ?? '');
    $username  = trim($_POST['username'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');
    $bio       = trim($_POST['bio'] ?? '');
    $city      = trim($_POST['city'] ?? '');
    $country   = trim($_POST['country'] ?? '');
    $action    = trim($_POST['action'] ?? 'save');

    if (empty($firstName) || empty($lastName)) {
        $errors[] = 'First name and last name cannot be empty.';
    }

    // Avatar upload handling
    $avatarPath = $userRecord['avatar'] ?? null;
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $imgRes = upload_image($_FILES['avatar'], 'avatars');
        if ($imgRes['success']) {
            $avatarPath = $imgRes['relative_path'];
        } else {
            $errors[] = 'Avatar upload failed: ' . $imgRes['error'];
        }
    }

    // Check unique username if changed
    if (!empty($username) && $username !== ($userRecord['username'] ?? '')) {
        $stmtU = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ? LIMIT 1");
        $stmtU->execute([$username, $userId]);
        if ($stmtU->fetch()) {
            $errors[] = 'Username is already taken. Please choose another.';
        }
    }

    if (empty($errors)) {
        // 1. Update users table
        $stmt = $pdo->prepare("
            UPDATE users 
            SET first_name = ?, last_name = ?, username = ?, phone = ?, avatar = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$firstName, $lastName, $username ?: ($userRecord['username'] ?? null), $phone, $avatarPath, $userId]);

        // 2. Update students table
        if ($studentRecord) {
            $stmt = $pdo->prepare("
                UPDATE students
                SET bio = ?, city = ?, country = ?, updated_at = NOW()
                WHERE user_id = ?
            ");
            $stmt->execute([$bio, $city, $country, $userId]);
        }

        // 3. Update session
        $_SESSION[SESSION_USER_DATA]['first_name'] = $firstName;
        $_SESSION[SESSION_USER_DATA]['last_name']  = $lastName;
        $_SESSION[SESSION_USER_DATA]['avatar']     = $avatarPath;

        $_SESSION['auth_success_vibrate'] = true;

        if ($action === 'continue_to_payment' && $pendingCourseId > 0) {
            if (defined('FREE_TESTING_MODE') && FREE_TESTING_MODE) {
                if ($studentRecord) {
                    $pdo->prepare("INSERT INTO enrollments (student_id, course_id, status, progress, enrolled_at) VALUES (?, ?, 'active', 0.00, NOW()) ON DUPLICATE KEY UPDATE status = 'active'")
                        ->execute([(int)$studentRecord['id'], $pendingCourseId]);
                }
                unset($_SESSION['pending_course_id'], $_SESSION['pending_course_slug'], $_SESSION['pending_course_title']);
                set_flash('success', 'Profile updated! You have full free testing access to your course.');
                redirect('student/course.php?id=' . $pendingCourseId);
            } else {
                set_flash('success', 'Profile updated! Proceeding to payment confirmation.');
                redirect('payments/checkout.php?course_id=' . $pendingCourseId);
            }
        } else {
            set_flash('success', 'Profile details and picture updated successfully!');
            if ($isEnrollFlow && $pendingCourseId > 0) {
                redirect('student/profile.php?enroll=1');
            } else {
                redirect('student/profile.php');
            }
        }
    }
}

include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<?php if ($isEnrollFlow && $pendingCourse): ?>
    <!-- ─── ONBOARDING STEPPER HEADER ────────────────────────── -->
    <div class="card border-0 shadow-sm rounded-4 p-4 mb-4 bg-primary text-white position-relative overflow-hidden">
        <div class="row align-items-center g-3 position-relative" style="z-index: 2;">
            <div class="col-lg-7">
                <span class="badge bg-white text-primary rounded-pill px-3 py-1 fw-bold small text-uppercase mb-2">
                    <i class="bi bi-person-check-fill me-1"></i> Step 3 of 5: Profile Confirmation
                </span>
                <h3 class="fw-bold text-white mb-1">Set Up Your Learner Profile</h3>
                <p class="text-white-50 small mb-0">Confirm your details and add a photo before completing payment for your selected course.</p>
            </div>
            <div class="col-lg-5 text-lg-end">
                <div class="p-3 bg-white bg-opacity-15 rounded-3 d-inline-block text-start border border-white border-opacity-25">
                    <div class="small text-white-50 text-uppercase fw-semibold" style="font-size:0.75rem;">Selected Course:</div>
                    <div class="fw-bold text-white text-truncate max-w-250"><?= e($pendingCourse['title']) ?></div>
                    <div class="d-flex justify-content-between align-items-center gap-3 mt-1">
                        <span class="badge bg-success rounded-pill px-2">₦<?= number_format($pendingPrice, 0) ?></span>
                        <a href="<?= url('payments/checkout.php?course_id=' . $pendingCourseId) ?>" class="text-white small fw-bold text-decoration-underline">
                            Skip to Payment &rarr;
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
        <div>
            <h1 class="h3 fw-bold mb-1"><i class="bi bi-person-circle text-primary me-2"></i> Student Profile</h1>
            <p class="text-muted mb-0">Manage your personal information, profile photo, and learning preferences.</p>
        </div>
    </div>
<?php endif; ?>

<div class="row justify-content-center">
    <div class="col-lg-9">
        <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5">
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger rounded-3 p-3 mb-4 shadow-sm">
                    <ul class="mb-0 small ps-3">
                        <?php foreach ($errors as $err): ?>
                            <li><?= e($err) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form action="<?= url('student/profile.php' . ($isEnrollFlow ? '?enroll=1' : '')) ?>" method="POST" enctype="multipart/form-data">
                
                <!-- Avatar Section -->
                <div class="d-flex align-items-center gap-4 mb-4 pb-4 border-bottom flex-wrap">
                    <div class="position-relative">
                        <?php $stdAvatarUrl = function_exists('get_avatar_url') ? get_avatar_url($userRecord['avatar'] ?? null, $userRecord['first_name'] ?? 'Student') : ($userRecord['avatar'] ?? ''); ?>
                        <img src="<?= e($stdAvatarUrl) ?>" 
                             id="studentAvatarPreview"
                             alt="Avatar" 
                             class="rounded-circle border border-3 border-primary shadow-sm" 
                             style="width: 96px; height: 96px; object-fit: cover;"
                             onerror="this.onerror=null; this.src='https://ui-avatars.com/api/?name=<?= urlencode($userRecord['first_name'] ?? 'Student') ?>&background=4f46e5&color=ffffff&bold=true';">
                    </div>
                    <div class="flex-grow-1">
                        <h6 class="fw-bold mb-1">Profile Photo</h6>
                        <p class="text-muted small mb-2">Upload a clear photo (JPG, PNG or WebP). Maximum file size: 10 MB.</p>
                        <input type="file" name="avatar" id="avatarInput" class="form-control form-control-sm rounded-pill" accept="image/jpeg,image/png,image/webp,image/gif">
                    </div>
                </div>

                <!-- Personal Information -->
                <h5 class="fw-bold mb-3 text-primary"><i class="bi bi-card-text me-2"></i> Account Information</h5>
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label for="firstName" class="form-label fw-semibold small">First Name <span class="text-danger">*</span></label>
                        <input type="text" name="first_name" id="firstName" class="form-control py-2 rounded-3" value="<?= e($userRecord['first_name']) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="lastName" class="form-label fw-semibold small">Last Name <span class="text-danger">*</span></label>
                        <input type="text" name="last_name" id="lastName" class="form-control py-2 rounded-3" value="<?= e($userRecord['last_name']) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="username" class="form-label fw-semibold small">Username</label>
                        <input type="text" name="username" id="username" class="form-control py-2 rounded-3" value="<?= e($userRecord['username'] ?? '') ?>" placeholder="e.g. jdoe24">
                        <div class="form-text">Your unique platform handle.</div>
                    </div>
                    <div class="col-md-6">
                        <label for="email" class="form-label fw-semibold small">Email Address</label>
                        <input type="email" class="form-control py-2 rounded-3 bg-light" value="<?= e($userRecord['email']) ?>" readonly disabled>
                        <div class="form-text">Verified account email.</div>
                    </div>
                    <div class="col-md-6">
                        <label for="phone" class="form-label fw-semibold small">Phone Number</label>
                        <input type="tel" name="phone" id="phone" class="form-control py-2 rounded-3" value="<?= e($userRecord['phone']) ?>" placeholder="+234 800 000 0000">
                    </div>
                    <div class="col-md-6">
                        <label for="studentNum" class="form-label fw-semibold small">Student Matric / Number</label>
                        <input type="text" class="form-control py-2 rounded-3 bg-light" value="<?= e($studentRecord['student_number'] ?? 'STD-' . str_pad($userId, 4, '0', STR_PAD_LEFT)) ?>" readonly disabled>
                    </div>
                </div>

                <!-- Location & Learning Goals -->
                <h5 class="fw-bold mb-3 text-primary"><i class="bi bi-geo-alt me-2"></i> Location &amp; Goals</h5>
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label for="city" class="form-label fw-semibold small">City / State</label>
                        <input type="text" name="city" id="city" class="form-control py-2 rounded-3" value="<?= e($studentRecord['city'] ?? '') ?>" placeholder="e.g. Lagos, Abuja, Port Harcourt">
                    </div>
                    <div class="col-md-6">
                        <label for="country" class="form-label fw-semibold small">Country</label>
                        <input type="text" name="country" id="country" class="form-control py-2 rounded-3" value="<?= e($studentRecord['country'] ?? 'Nigeria') ?>" placeholder="e.g. Nigeria">
                    </div>
                    <div class="col-12">
                        <label for="bio" class="form-label fw-semibold small">About Me / Career Goals</label>
                        <textarea name="bio" id="bio" rows="3" class="form-control rounded-3" placeholder="Share your learning background, study focus, or career aspirations..."><?= e($studentRecord['bio'] ?? '') ?></textarea>
                    </div>
                </div>

                <div class="pt-3 border-top d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <?php if ($isEnrollFlow && $pendingCourseId > 0): ?>
                        <a href="<?= url('courses/details.php?slug=' . urlencode($pendingCourse['slug'] ?? '')) ?>" class="btn btn-outline-secondary rounded-pill px-4 fw-semibold">
                            <i class="bi bi-arrow-left me-1"></i> Back to Course
                        </a>
                        <div class="d-flex gap-2">
                            <button type="submit" name="action" value="save" class="btn btn-outline-primary rounded-pill px-4 fw-bold">
                                Save Profile
                            </button>
                            <button type="submit" name="action" value="continue_to_payment" class="btn btn-primary rounded-pill px-5 fw-bold shadow">
                                Continue to Payment &rarr;
                            </button>
                        </div>
                    <?php else: ?>
                        <div></div>
                        <button type="submit" name="action" value="save" class="btn btn-primary rounded-pill px-5 fw-bold shadow-sm" data-feedback="click">
                            <i class="bi bi-save-fill me-2"></i> Save Profile Changes
                        </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const avatarIn = document.getElementById('avatarInput');
    const avatarPrev = document.getElementById('studentAvatarPreview');
    if (avatarIn && avatarPrev) {
        avatarIn.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    avatarPrev.src = e.target.result;
                };
                reader.readAsDataURL(this.files[0]);
            }
        });
    }
});
</script>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>

