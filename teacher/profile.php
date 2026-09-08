<?php
/**
 * StudyMe AI Platform — Teacher Profile Manager
 * Allows registered educators to edit their full public credentials, bio, qualifications,
 * education history, skills, experience, and profile picture.
 */
require_once dirname(__DIR__) . '/config/main.php';
require_once BASE_PATH . '/includes/functions/uploads.php';

secure_page(ROLE_TEACHER);
$user = current_user();
$pdo  = getDBConnection();
$uid  = (int)$user['id'];

// Fetch or create teacher record
$stmt = $pdo->prepare("
    SELECT t.*, c.title AS course_title, cat.name AS category_name
    FROM teachers t
    LEFT JOIN courses c ON (t.assigned_course_id = c.id OR c.teacher_id = t.id)
    LEFT JOIN categories cat ON (t.assigned_category_id = cat.id OR c.category_id = cat.id)
    WHERE t.user_id = ? LIMIT 1
");
$stmt->execute([$uid]);
$teacher = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$teacher) {
    $tNum = 'TCH-' . date('Y') . '-' . str_pad($uid, 4, '0', STR_PAD_LEFT);
    $pdo->prepare("INSERT INTO teachers (user_id, teacher_number, status, created_at) VALUES (?, ?, 'active', NOW())")
        ->execute([$uid, $tNum]);
    $tid = (int)$pdo->lastInsertId();
    $stmt->execute([$uid]);
    $teacher = $stmt->fetch(PDO::FETCH_ASSOC);
} else {
    $tid = (int)$teacher['id'];
}

$errors = [];
if (is_post()) {
    $firstName       = trim($_POST['first_name'] ?? '');
    $lastName        = trim($_POST['last_name'] ?? '');
    $phone           = trim($_POST['phone'] ?? '');
    $avatarUrlInput  = trim($_POST['avatar'] ?? '');
    $specialization  = trim($_POST['specialization'] ?? '');
    $qualification   = trim($_POST['qualification'] ?? '');
    $education       = trim($_POST['education'] ?? '');
    $experienceYears = (int)($_POST['experience_years'] ?? 1);
    $skills          = trim($_POST['skills'] ?? '');
    $bio             = trim($_POST['bio'] ?? '');
    $website         = trim($_POST['website'] ?? '');

    if (empty($firstName) || empty($lastName)) {
        $errors[] = 'First name and last name are required.';
    }

    // Handle avatar photo upload or URL fallback
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

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // 1. Update users table
            $stmtUser = $pdo->prepare("
                UPDATE users 
                SET first_name = ?, last_name = ?, phone = ?, avatar = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmtUser->execute([$firstName, $lastName, $phone, $finalAvatar, $uid]);

            // 2. Update teachers table
            $stmtTch = $pdo->prepare("
                UPDATE teachers 
                SET specialization = ?, qualification = ?, education = ?, 
                    experience_years = ?, skills = ?, bio = ?, website = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmtTch->execute([
                $specialization, $qualification, $education,
                $experienceYears, $skills, $bio, $website, $tid
            ]);

            $pdo->commit();

            // 3. Update session data
            $_SESSION[SESSION_USER_DATA]['first_name'] = $firstName;
            $_SESSION[SESSION_USER_DATA]['last_name']  = $lastName;
            $_SESSION[SESSION_USER_DATA]['avatar']     = $finalAvatar;

            set_flash('success', 'Your instructor profile and photo have been updated and are now live across StudyMe!');
            redirect('teacher/profile.php');
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log("Teacher Profile Update Error: " . $e->getMessage());
            $errors[] = 'Failed to update profile: ' . $e->getMessage();
        }
    }
}

// Refresh user info
$user = current_user();

include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
    <div>
        <p class="text-muted mb-1 fw-semibold text-uppercase small">
            <i class="bi bi-person-badge-fill text-primary me-1"></i> Instructor Suite
        </p>
        <h1 class="h3 fw-bold mb-1">Edit Instructor Profile</h1>
        <p class="text-muted small mb-0">Update your public biography, credentials, teaching experience, and avatar visible on the platform.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url('teacher-profile.php?id=' . $tid) ?>" class="btn btn-outline-primary rounded-pill px-4 fw-bold" target="_blank">
            <i class="bi bi-eye-fill me-1"></i> View Public Profile
        </a>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger rounded-4 p-3 mb-4 shadow-sm">
        <ul class="mb-0 ps-3">
            <?php foreach ($errors as $err): ?>
                <li><?= e($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="row g-4">
    <!-- Left Column: Edit Form -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5">
            <form method="POST" action="<?= url('teacher/profile.php') ?>" enctype="multipart/form-data">
                
                <h5 class="fw-bold mb-3 border-bottom pb-2 text-primary">
                    <i class="bi bi-person-lines-fill me-1"></i> 1. Personal &amp; Contact Information
                </h5>

                <!-- Profile Photo Upload Card -->
                <div class="p-3 bg-light rounded-4 mb-4 border d-flex flex-column flex-sm-row align-items-center gap-4">
                    <div class="position-relative">
                        <?php $tAvatarUrl = function_exists('get_avatar_url') ? get_avatar_url($user['avatar'] ?? null, $user['first_name'] ?? 'Instructor') : ($user['avatar'] ?? ''); ?>
                        <img src="<?= e($tAvatarUrl) ?>" 
                             id="avatarPreviewImg"
                             class="rounded-circle border border-3 border-primary shadow-sm"
                             style="width: 85px; height: 85px; object-fit: cover;"
                             alt="<?= e($user['first_name']) ?>"
                             onerror="this.onerror=null; this.src='https://ui-avatars.com/api/?name=<?= urlencode($user['first_name'] ?? 'Instructor') ?>&background=4f46e5&color=ffffff&bold=true';">
                    </div>
                    <div class="flex-grow-1">
                        <label class="form-label fw-bold small text-dark mb-1">Upload Profile Picture</label>
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <input type="file" name="avatar_file" id="avatarFileInput" class="form-control form-control-sm rounded-pill" accept="image/jpeg,image/png,image/webp,image/gif">
                        </div>
                        <div class="text-muted" style="font-size:0.75rem;">Supported: JPG, PNG, WEBP (Max 10MB). Image updates across all courses and student hubs.</div>
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

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold small">Email Address</label>
                        <input type="email" class="form-control rounded-3 bg-light" value="<?= e($user['email']) ?>" readonly>
                        <small class="text-muted">Managed by account settings</small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small">Phone Number</label>
                        <input type="tel" name="phone" class="form-control rounded-3" value="<?= e($user['phone'] ?? '') ?>" placeholder="+234...">
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-bold small">Or Photo Direct URL (Optional)</label>
                    <input type="url" name="avatar" id="avatarUrlInput" class="form-control rounded-3" value="<?= (strpos($user['avatar'] ?? '', 'http') === 0) ? e($user['avatar']) : '' ?>" placeholder="https://images.unsplash.com/... or cloud image link">
                    <small class="text-muted">You can also provide a direct web image link instead of uploading a file.</small>
                </div>

                <h5 class="fw-bold mb-3 border-bottom pb-2 text-primary mt-4">
                    <i class="bi bi-mortarboard-fill me-1"></i> 2. Academic &amp; Professional Credentials
                </h5>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold small">Highest Qualification</label>
                        <input type="text" name="qualification" class="form-control rounded-3" value="<?= e($teacher['qualification'] ?? '') ?>" placeholder="e.g. Ph.D. in Computer Science / M.Sc. Physics">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small">Specialization</label>
                        <input type="text" name="specialization" class="form-control rounded-3" value="<?= e($teacher['specialization'] ?? '') ?>" placeholder="e.g. Artificial Intelligence &amp; Cloud Systems">
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold small">Teaching Experience (Years)</label>
                        <input type="number" name="experience_years" class="form-control rounded-3" min="0" max="60" value="<?= (int)($teacher['experience_years'] ?? 1) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small">Website / Portfolio Link</label>
                        <input type="url" name="website" class="form-control rounded-3" value="<?= e($teacher['website'] ?? '') ?>" placeholder="https://yourportfolio.com">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold small">Education Background</label>
                    <textarea name="education" class="form-control rounded-3" rows="2" placeholder="e.g. B.Sc. Computer Engineering (University of Lagos, 2018)&#10;M.Sc. Artificial Intelligence (2021)"><?= e($teacher['education'] ?? '') ?></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold small">Skills &amp; Technologies Taught</label>
                    <input type="text" name="skills" class="form-control rounded-3" value="<?= e($teacher['skills'] ?? '') ?>" placeholder="e.g. Python, Machine Learning, PyTorch, React, Cloud Architecture">
                </div>

                <div class="mb-4">
                    <label class="form-label fw-bold small">Public Instructor Biography</label>
                    <textarea name="bio" class="form-control rounded-3" rows="5" placeholder="Share your teaching philosophy, career background, and what students will gain in your courses..."><?= e($teacher['bio'] ?? '') ?></textarea>
                </div>

                <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                    <button type="submit" class="btn btn-primary rounded-pill px-5 py-3 fw-bold shadow">
                        <i class="bi bi-check2-circle me-1"></i> Save &amp; Publish Profile
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Right Column: Profile Summary & Assigned Course -->
    <div class="col-lg-4">
        <!-- Assigned Course Info -->
        <div class="card border-0 shadow-sm rounded-4 p-4 mb-4">
            <h5 class="fw-bold mb-3"><i class="bi bi-journal-bookmark-fill text-success me-2"></i>Assigned Teaching Course</h5>
            <?php if (!empty($teacher['course_title'])): ?>
                <div class="p-3 bg-light rounded-3 mb-3">
                    <div class="fw-bold text-main mb-1"><?= e($teacher['course_title']) ?></div>
                    <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill small">
                        <?= e($teacher['category_name'] ?? 'Academic Course') ?>
                    </span>
                </div>
                <p class="text-muted small mb-0">Students who enroll in this course are assigned to you and can message you through your profile.</p>
            <?php else: ?>
                <p class="text-muted small mb-0">No course currently assigned. You can request a course assignment via platform support.</p>
            <?php endif; ?>
        </div>

        <!-- Public Preview Card -->
        <div class="card border-0 shadow-sm rounded-4 p-4 text-center">
            <h6 class="fw-bold text-muted small text-uppercase mb-3">Public Card Preview</h6>
            <div class="position-relative d-inline-block mx-auto mb-3">
                <img src="<?= e($tAvatarUrl) ?>" 
                     id="publicCardPreviewImg"
                     class="rounded-circle border border-3 border-light shadow-sm"
                     style="width: 90px; height: 90px; object-fit: cover;"
                     alt="<?= e($user['first_name']) ?>"
                     onerror="this.onerror=null; this.src='https://ui-avatars.com/api/?name=<?= urlencode($user['first_name'] ?? 'Instructor') ?>&background=4f46e5&color=ffffff&bold=true';">
            </div>
            <h5 class="fw-bold mb-1"><?= e($user['first_name'] . ' ' . $user['last_name']) ?></h5>
            <div class="badge bg-primary bg-opacity-10 text-primary mb-2 rounded-pill small px-3">
                <?= e($teacher['qualification'] ?: 'Certified Instructor') ?>
            </div>
            <p class="text-muted small mb-3"><?= e($teacher['specialization'] ?: 'Academic Specialist') ?></p>
            
            <a href="<?= url('teacher-profile.php?id=' . $tid) ?>" class="btn btn-sm btn-outline-primary rounded-pill px-4 fw-bold" target="_blank">
                <i class="bi bi-box-arrow-up-right me-1"></i> Preview Live Page
            </a>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const fileInput = document.getElementById('avatarFileInput');
    const previewImg = document.getElementById('avatarPreviewImg');
    const cardImg = document.getElementById('publicCardPreviewImg');
    const urlInput = document.getElementById('avatarUrlInput');

    if (fileInput) {
        fileInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    if (previewImg) previewImg.src = e.target.result;
                    if (cardImg) cardImg.src = e.target.result;
                };
                reader.readAsDataURL(this.files[0]);
            }
        });
    }

    if (urlInput) {
        urlInput.addEventListener('input', function() {
            if (this.value.trim().length > 5) {
                if (previewImg) previewImg.src = this.value.trim();
                if (cardImg) cardImg.src = this.value.trim();
            }
        });
    }
});
</script>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>

