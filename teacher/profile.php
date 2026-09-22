<?php

require_once dirname(__DIR__) . '/config/main.php';
require_once BASE_PATH . '/includes/functions/users.php';
require_once BASE_PATH . '/includes/functions/uploads.php';

secure_page(ROLE_TEACHER);

$user = current_user();
$pdo  = getDBConnection();
$uid  = (int)($user['id'] ?? 0);

if ($uid <= 0) {
    set_flash('error', 'Please log in to view your profile.');
    redirect('auth/login.php');
}

$userRecord = get_user_by_id($uid);
if (!$userRecord) {
    init_session();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 86400, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    @session_destroy();
    set_flash('error', 'User account not found. Please log in again.');
    redirect('auth/login.php');
}

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
    try {
        $tNum = 'TCH-' . date('Y') . '-' . str_pad($uid, 4, '0', STR_PAD_LEFT);
        $pdo->prepare("INSERT INTO teachers (user_id, teacher_number, status, created_at) VALUES (?, ?, 'active', NOW())")
            ->execute([$uid, $tNum]);
        $tid = (int)$pdo->lastInsertId();
        $stmt->execute([$uid]);
        $teacher = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $stmt->execute([$uid]);
        $teacher = $stmt->fetch(PDO::FETCH_ASSOC);
        $tid = $teacher ? (int)$teacher['id'] : 0;
    }
} else {
    $tid = (int)$teacher['id'];
}

$totalCoursesCount = (int)$pdo->query("SELECT COUNT(*) FROM courses WHERE teacher_id = {$tid}")->fetchColumn();
$totalStudentsCount = (int)$pdo->query("SELECT COUNT(DISTINCT student_id) FROM enrollments WHERE course_id IN (SELECT id FROM courses WHERE teacher_id = {$tid})")->fetchColumn();
$totalLessonsCount = (int)$pdo->query("SELECT COUNT(*) FROM lessons WHERE section_id IN (SELECT id FROM course_sections WHERE course_id IN (SELECT id FROM courses WHERE teacher_id = {$tid}))")->fetchColumn();

$errors = [];
$activeTab = $_GET['tab'] ?? 'details';

if (is_post()) {
    $action = trim($_POST['action'] ?? 'save_profile');

    if ($action === 'save_profile') {
        $firstName       = trim($_POST['first_name'] ?? '');
        $lastName        = trim($_POST['last_name'] ?? '');
        $phone           = trim($_POST['phone'] ?? '');
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

        $finalAvatar = $user['avatar'] ?? '';
        if (isset($_FILES['avatar_file']) && $_FILES['avatar_file']['error'] === UPLOAD_ERR_OK) {
            $imgRes = upload_image($_FILES['avatar_file'], 'avatars');
            if ($imgRes['success']) {
                $finalAvatar = $imgRes['relative_path'];
            } else {
                $errors[] = 'Avatar upload failed: ' . $imgRes['error'];
            }
        }

        if (empty($errors)) {
            try {
                $pdo->beginTransaction();

                $stmtUser = $pdo->prepare("
                    UPDATE users
                    SET first_name = ?, last_name = ?, phone = ?, avatar = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmtUser->execute([$firstName, $lastName, $phone, $finalAvatar, $uid]);

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

                $_SESSION[SESSION_USER_DATA]['first_name'] = $firstName;
                $_SESSION[SESSION_USER_DATA]['last_name']  = $lastName;
                $_SESSION[SESSION_USER_DATA]['avatar']     = $finalAvatar;

                $_SESSION['auth_success_vibrate'] = true;
                set_flash('success', 'Instructor profile credentials and photo updated successfully!');
                redirect('teacher/profile.php?tab=details');
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $errors[] = 'Failed to update profile: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'change_password') {
        $activeTab = 'security';
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword     = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        $userRec = get_user_by_id($uid);
        if (!password_verify($currentPassword, $userRec['password'])) {
            $errors[] = 'Current password entered was incorrect.';
        }
        if (strlen($newPassword) < 6) {
            $errors[] = 'New password must be at least 6 characters long.';
        }
        if ($newPassword !== $confirmPassword) {
            $errors[] = 'New passwords do not match.';
        }

        if (empty($errors)) {
            update_user_password($uid, $newPassword);
            $_SESSION['auth_success_vibrate'] = true;
            set_flash('success', 'Your password has been securely updated.');
            redirect('teacher/profile.php?tab=security');
        }
    }
}

$userRecord = get_user_by_id($uid);
$stmt->execute([$uid]);
$teacher = $stmt->fetch(PDO::FETCH_ASSOC);

include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<style>
.profile-summary-card, .profile-form-card, .profile-nav-card { min-width: 0; }
.profile-summary-card .profile-stat { min-width: 0; }
.profile-summary-card .profile-stat small { display: block; line-height: 1.25; }
.profile-upload-row { min-width: 0; }
.profile-photo-picker {
    position: relative;
    border: 1px solid rgba(99, 102, 241, .2);
    background: linear-gradient(135deg, rgba(255,255,255,.96), rgba(239,246,255,.9), rgba(236,253,245,.9));
    box-shadow: 0 12px 30px rgba(15, 23, 42, .08), inset 0 1px 0 rgba(255,255,255,.8);
    transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease;
    cursor: pointer;
    overflow: hidden;
    isolation: isolate;
}
.profile-photo-picker::before {
    content: "";
    position: absolute;
    inset: -25% auto auto -10%;
    width: 220px;
    height: 220px;
    background: radial-gradient(circle, rgba(59,130,246,.18), transparent 60%);
    z-index: -1;
}
.profile-photo-picker::after {
    content: "";
    position: absolute;
    inset: auto -20% -30% auto;
    width: 210px;
    height: 210px;
    background: radial-gradient(circle, rgba(16,185,129,.14), transparent 60%);
    z-index: -1;
}
.profile-photo-picker:hover, .profile-photo-picker:focus-within {
    transform: translateY(-1px);
    border-color: rgba(79, 70, 229, .4);
    box-shadow: 0 18px 34px rgba(37, 99, 235, .12), inset 0 1px 0 rgba(255,255,255,.9);
}
.profile-photo-picker input[type="file"] { position: absolute; width: 1px; height: 1px; opacity: 0; pointer-events: none; }
.profile-upload-row input[type="file"] { max-width: 100%; }
@media (max-width: 767.98px) {
    .profile-summary-card, .profile-form-card { padding: 1rem !important; }
    .profile-summary-card .row { text-align: center; }
    .profile-summary-card .col-md { text-align: center; }
    .profile-summary-card .d-flex { justify-content: center; }
    .profile-summary-card .profile-meta { justify-content: center; gap: .5rem !important; }
    .profile-summary-card .profile-meta > span:not(:first-child)::before { content: ''; }
    .profile-summary-card .profile-stats { display: grid !important; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .5rem; padding: .75rem !important; }
    .profile-summary-card .profile-stats > div { border: 0 !important; padding: .25rem !important; min-width: 0; }
    .profile-summary-card .profile-stats small { font-size: .65rem !important; }
    .profile-nav-card { padding: .75rem !important; }
    .profile-nav-card .nav { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .profile-nav-card .nav-link { font-size: .8rem; min-height: 44px; }
    .profile-upload-row { align-items: flex-start !important; flex-direction: column; }
    .profile-upload-row > div, .profile-upload-row input[type="file"] { width: 100%; }
    .profile-form-card h4 { font-size: 1.15rem; }
    .profile-form-card .border-top { justify-content: stretch !important; }
    .profile-form-card .border-top button { width: 100%; }
}
@media (min-width: 768px) and (max-width: 991.98px) {
    .profile-summary-card .profile-stats { margin-top: .5rem; }
}
</style>

<div class="card border-0 shadow-sm rounded-4 p-4 mb-4 bg-surface profile-summary-card">
    <div class="row align-items-center g-4">
        <div class="col-md-auto text-center text-md-start">
            <div class="position-relative d-inline-block">
                <?php $tchAvatarUrl = function_exists('get_avatar_url') ? get_avatar_url($userRecord['avatar'] ?? null, $userRecord['first_name'] ?? 'Instructor') : ($userRecord['avatar'] ?? ''); ?>
                <img src="<?= e($tchAvatarUrl) ?>"
                     id="headerAvatarImg"
                     alt="Avatar"
                     class="rounded-circle border border-3 border-primary shadow-sm"
                     style="width: 100px; height: 100px; object-fit: cover;"
                     onerror="this.onerror=null; this.src='https://ui-avatars.com/api/?name=<?= urlencode($userRecord['first_name'] ?? 'Instructor') ?>&background=1e40af&color=ffffff&bold=true';">
                <span class="status-pulse-dot position-absolute top-0 end-0 bg-success border border-2 border-white rounded-circle" style="width: 14px; height: 14px; z-index: 5;" title="Active Now"></span>
                <label for="avatarFileInput" class="position-absolute bottom-0 end-0 bg-primary text-white rounded-circle p-2 shadow cursor-pointer d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;" title="Change Photo">
                    <i class="bi bi-camera-fill small"></i>
                </label>
            </div>
        </div>

        <div class="col-md">
            <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                <h2 class="h4 fw-bold mb-0 text-main"><?= e($userRecord['first_name'] . ' ' . $userRecord['last_name']) ?></h2>
                <span class="badge bg-primary-subtle text-primary border border-primary border-opacity-25 rounded-pill px-3 py-1 fw-bold small">
                    <i class="bi bi-person-badge-fill me-1"></i> Certified Instructor
                </span>
                <span class="badge bg-success-subtle text-success border border-success border-opacity-25 rounded-pill px-3 py-1 fw-bold small">
                    <i class="bi bi-check-circle-fill me-1"></i> Active
                </span>
            </div>

            <div class="d-flex align-items-center gap-3 flex-wrap text-muted small mb-2 profile-meta">
                <span><i class="bi bi-hash me-1"></i> Staff ID: <strong class="text-main font-monospace"><?= e($teacher['teacher_number'] ?? 'TCH-' . str_pad($uid, 4, '0', STR_PAD_LEFT)) ?></strong></span>
                <span>&bull;</span>
                <span><i class="bi bi-envelope me-1"></i> <?= e($userRecord['email']) ?></span>
                <?php if (!empty($teacher['specialization'])): ?>
                    <span>&bull;</span>
                    <span><i class="bi bi-mortarboard me-1"></i> <?= e($teacher['specialization']) ?></span>
                <?php endif; ?>
            </div>

            <div class="d-flex align-items-center gap-2 flex-wrap">
                <a href="<?= url('teacher-profile.php?id=' . $tid) ?>" class="btn btn-outline-primary btn-sm rounded-pill px-3 fw-semibold" target="_blank">
                    <i class="bi bi-box-arrow-up-right me-1"></i> View Public Profile
                </a>
            </div>
        </div>

        <div class="col-lg-4 col-md-12">
            <div class="p-3 bg-body-tertiary rounded-3 border d-flex justify-content-around text-center profile-stats">
                <div>
                    <div class="fs-5 fw-bold text-primary"><?= $totalCoursesCount ?></div>
                    <small class="text-muted" style="font-size:0.75rem;">Courses Taught</small>
                </div>
                <div class="border-start ps-3">
                    <div class="fs-5 fw-bold text-success"><?= $totalStudentsCount ?></div>
                    <small class="text-muted" style="font-size:0.75rem;">Total Students</small>
                </div>
                <div class="border-start ps-3">
                    <div class="fs-5 fw-bold text-warning"><?= number_format((float)($teacher['rating'] ?? 5.0), 1) ?> ★</div>
                    <small class="text-muted" style="font-size:0.75rem;">Instructor Rating</small>
                </div>
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
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-surface profile-nav-card">
            <div class="nav flex-column nav-pills gap-1" id="teacherProfileTab" role="tablist">
                <a class="nav-link text-start rounded-3 py-2.5 px-3 d-flex align-items-center gap-2 <?= $activeTab === 'details' ? 'active bg-primary text-white' : 'text-main' ?>"
                   href="<?= url('teacher/profile.php?tab=details') ?>">
                    <i class="bi bi-person-lines-fill"></i>
                    <span class="fw-semibold">Edit Instructor Info</span>
                </a>
                <a class="nav-link text-start rounded-3 py-2.5 px-3 d-flex align-items-center gap-2 <?= $activeTab === 'security' ? 'active bg-primary text-white' : 'text-main' ?>"
                   href="<?= url('teacher/profile.php?tab=security') ?>">
                    <i class="bi bi-shield-lock-fill"></i>
                    <span class="fw-semibold">Account &amp; Security</span>
                </a>
                <a class="nav-link text-start rounded-3 py-2.5 px-3 d-flex align-items-center gap-2 text-main"
                   href="<?= url('teacher/earnings.php') ?>">
                    <i class="bi bi-wallet2 text-success"></i>
                    <span class="fw-semibold">Earnings &amp; Payouts</span>
                </a>
                <a class="nav-link text-start rounded-3 py-2.5 px-3 d-flex align-items-center gap-2 text-main"
                   href="<?= url('teacher/courses.php') ?>">
                    <i class="bi bi-journal-code text-primary"></i>
                    <span class="fw-semibold">Course Manager</span>
                </a>
            </div>
        </div>
    </div>

    <div class="col-md-9">
        <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 bg-surface profile-form-card">
            <?php if ($activeTab === 'security'): ?>

                <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
                    <div>
                        <h4 class="fw-bold mb-1">Account Security &amp; Password</h4>
                        <p class="text-muted small mb-0">Manage instructor portal authentication and credentials.</p>
                    </div>
                </div>

                <form action="<?= url('teacher/profile.php?tab=security') ?>" method="POST">
                    <input type="hidden" name="action" value="change_password">

                    <div class="mb-3">
                        <label for="currentPassword" class="form-label fw-semibold small">Current Password <span class="text-danger">*</span></label>
                        <input type="password" name="current_password" id="currentPassword" class="form-control rounded-3 py-2" required>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label for="newPassword" class="form-label fw-semibold small">New Password <span class="text-danger">*</span></label>
                            <input type="password" name="new_password" id="newPassword" class="form-control rounded-3 py-2" placeholder="Minimum 6 characters" required>
                        </div>
                        <div class="col-md-6">
                            <label for="confirmPassword" class="form-label fw-semibold small">Confirm New Password <span class="text-danger">*</span></label>
                            <input type="password" name="confirm_password" id="confirmPassword" class="form-control rounded-3 py-2" placeholder="Re-enter new password" required>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary rounded-pill px-5 fw-bold shadow-sm">
                        <i class="bi bi-shield-check me-1"></i> Save New Password
                    </button>
                </form>

            <?php else: ?>

                <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
                    <div>
                        <h4 class="fw-bold mb-1">Instructor Profile Details</h4>
                        <p class="text-muted small mb-0">Update your public credentials, academic biography, and contact preferences.</p>
                    </div>
                </div>

                <form action="<?= url('teacher/profile.php?tab=details') ?>" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="save_profile">

                    <label for="avatarFileInput" class="profile-photo-picker p-3 rounded-4 mb-4 d-flex align-items-center justify-content-between flex-wrap gap-3 profile-upload-row">
                        <div class="d-flex align-items-center gap-3">
                            <img src="<?= e($tchAvatarUrl) ?>"
                                 id="avatarPreviewSnippet"
                                 alt="Avatar"
                                 class="rounded-circle border"
                                 style="width: 50px; height: 50px; object-fit: cover;"
                                 onerror="this.onerror=null; this.src='https://ui-avatars.com/api/?name=<?= urlencode($userRecord['first_name'] ?? 'Instructor') ?>&background=1e40af&color=ffffff&bold=true';">
                            <div>
                                <div class="fw-bold text-main"><i class="bi bi-cloud-arrow-up-fill text-primary me-2"></i>Update profile picture</div>
                                <div class="text-muted small">Click anywhere here to choose a professional JPG, PNG or WebP headshot.</div>
                            </div>
                        </div>
                        <span class="btn btn-primary btn-sm rounded-pill px-3"><i class="bi bi-image me-1"></i>Choose image</span>
                        <input type="file" name="avatar_file" id="avatarFileInput" accept="image/jpeg,image/png,image/webp">
                    </label>

                    <h5 class="fw-bold fs-6 mb-3 text-primary"><i class="bi bi-person-fill me-1"></i> Personal &amp; Contact Information</h5>
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
                        <div class="col-md-6">
                            <label for="website" class="form-label fw-semibold small">Personal Website or Portfolio</label>
                            <input type="url" name="website" id="website" class="form-control rounded-3 py-2" value="<?= e($teacher['website'] ?? '') ?>" placeholder="https://yourportfolio.com">
                        </div>
                    </div>

                    <h5 class="fw-bold fs-6 mb-3 text-primary"><i class="bi bi-lock-fill me-1"></i> Verified Staff Credentials (Locked)</h5>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small d-flex justify-content-between align-items-center">
                                <span>Official Email</span>
                                <span class="badge bg-light text-muted border" style="font-size:0.7rem;"><i class="bi bi-shield-check text-success me-1"></i> Verified &amp; Locked</span>
                            </label>
                            <input type="email" class="form-control rounded-3 py-2 bg-body-tertiary" value="<?= e($userRecord['email']) ?>" readonly disabled>
                            <div class="form-text small">Your verified instructor login email cannot be edited directly.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small d-flex justify-content-between align-items-center">
                                <span>Instructor Staff ID</span>
                                <span class="badge bg-light text-muted border" style="font-size:0.7rem;"><i class="bi bi-lock-fill text-muted me-1"></i> System Assigned</span>
                            </label>
                            <input type="text" class="form-control rounded-3 py-2 bg-body-tertiary font-monospace" value="<?= e($teacher['teacher_number'] ?? 'TCH-' . str_pad($uid, 4, '0', STR_PAD_LEFT)) ?>" readonly disabled>
                            <div class="form-text small">Platform-assigned instructor identification code.</div>
                        </div>
                    </div>

                    <h5 class="fw-bold fs-6 mb-3 text-primary"><i class="bi bi-mortarboard-fill me-1"></i> Professional &amp; Teaching Credentials</h5>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label for="specialization" class="form-label fw-semibold small">Primary Specialization</label>
                            <input type="text" name="specialization" id="specialization" class="form-control rounded-3 py-2" value="<?= e($teacher['specialization'] ?? '') ?>" placeholder="e.g. Senior Python & AI Instructor">
                        </div>
                        <div class="col-md-6">
                            <label for="qualification" class="form-label fw-semibold small">Highest Qualification</label>
                            <input type="text" name="qualification" id="qualification" class="form-control rounded-3 py-2" value="<?= e($teacher['qualification'] ?? '') ?>" placeholder="e.g. M.Sc. Computer Science">
                        </div>
                        <div class="col-md-6">
                            <label for="education" class="form-label fw-semibold small">University / Institution</label>
                            <input type="text" name="education" id="education" class="form-control rounded-3 py-2" value="<?= e($teacher['education'] ?? '') ?>" placeholder="e.g. University of Lagos">
                        </div>
                        <div class="col-md-6">
                            <label for="experienceYears" class="form-label fw-semibold small">Years of Teaching Experience</label>
                            <input type="number" name="experience_years" id="experienceYears" class="form-control rounded-3 py-2" value="<?= (int)($teacher['experience_years'] ?? 1) ?>" min="0" max="60">
                        </div>
                        <div class="col-12">
                            <label for="skills" class="form-label fw-semibold small">Key Skills &amp; Subject Areas</label>
                            <input type="text" name="skills" id="skills" class="form-control rounded-3 py-2" value="<?= e($teacher['skills'] ?? '') ?>" placeholder="e.g. Python, React, Data Structures, Calculus, Physics">
                            <div class="form-text small">Separate topics with commas.</div>
                        </div>
                        <div class="col-12">
                            <label for="bio" class="form-label fw-semibold small">Public Biography &amp; Teaching Philosophy</label>
                            <textarea name="bio" id="bio" rows="4" class="form-control rounded-3" placeholder="Describe your background, industry experience, and what students will learn in your courses..."><?= e($teacher['bio'] ?? '') ?></textarea>
                        </div>
                    </div>

                    <div class="pt-3 border-top d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary rounded-pill px-5 py-2.5 fw-bold shadow-sm" data-feedback="click">
                            <i class="bi bi-check2-circle me-1"></i> Save Instructor Changes
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
