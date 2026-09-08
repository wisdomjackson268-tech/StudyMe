<?php
/**
 * StudyMe AI Platform — Step 2: Teacher Profile Information & Registration
 * Completes teacher registration with assigned course & category context.
 */
require_once dirname(__DIR__) . '/config/main.php';

if (is_logged_in()) {
    $role = current_user_role();
    $dash = $role === 'admin' ? 'admin/dashboard.php' : ($role === 'teacher' ? 'teacher/dashboard.php' : 'student/dashboard.php');
    redirect($dash);
}

$pdo = getDBConnection();

// ENFORCE STEP 1 REQUIREMENT: Must have selected a teaching course
$selectedCategoryId = (int)($_SESSION['teacher_reg_category_id'] ?? 0);
$selectedCourseId   = (int)($_SESSION['teacher_reg_course_id']   ?? 0);

if ($selectedCategoryId <= 0 || $selectedCourseId <= 0) {
    set_flash('info', 'Please select the course or subject you want to teach before registering.');
    redirect('auth/teacher-course-select.php');
}

// Fetch selected course & category details
$stmtCat = $pdo->prepare("SELECT name, slug FROM categories WHERE id = ? LIMIT 1");
$stmtCat->execute([$selectedCategoryId]);
$categoryRow = $stmtCat->fetch(PDO::FETCH_ASSOC);

$stmtCourse = $pdo->prepare("SELECT title, slug FROM courses WHERE id = ? LIMIT 1");
$stmtCourse->execute([$selectedCourseId]);
$courseRow = $stmtCourse->fetch(PDO::FETCH_ASSOC);

if (!$courseRow) {
    // If id was a subject id, lookup subject title
    $stmtSubj = $pdo->prepare("SELECT name AS title, slug FROM subjects WHERE id = ? LIMIT 1");
    $stmtSubj->execute([$selectedCourseId]);
    $courseRow = $stmtSubj->fetch(PDO::FETCH_ASSOC);
}

$categoryName = $categoryRow['name'] ?? 'Academic Category';
$courseTitle  = $courseRow['title']  ?? 'Selected Course';

$errors = [];
$firstName = '';
$lastName = '';
$email = '';
$phone = '';
$qualification = '';
$specialization = '';
$experienceYears = 1;
$bio = '';

if (is_post()) {
    $firstName       = trim($_POST['first_name'] ?? '');
    $lastName        = trim($_POST['last_name'] ?? '');
    $email           = trim($_POST['email'] ?? '');
    $phone           = trim($_POST['phone'] ?? '');
    $qualification   = trim($_POST['qualification'] ?? '');
    $specialization  = trim($_POST['specialization'] ?? '');
    $experienceYears = (int)($_POST['experience_years'] ?? 1);
    $bio             = trim($_POST['bio'] ?? '');
    $password        = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirmation'] ?? '';

    if (empty($firstName) || empty($lastName)) {
        $errors[] = 'First name and last name are required.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if (empty($qualification)) {
        $errors[] = 'Highest qualification is required.';
    }
    if (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    }
    if ($password !== $passwordConfirm) {
        $errors[] = 'Passwords do not match.';
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = 'An account with this email address already exists. Please log in.';
        } else {
            try {
                $pdo->beginTransaction();

                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $username = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $firstName . $lastName)) . rand(100, 999);

                // 1. Create Teacher User Record
                $stmtUser = $pdo->prepare("
                    INSERT INTO users (first_name, last_name, username, email, password, phone, role, status, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, 'teacher', 'active', NOW())
                ");
                $stmtUser->execute([$firstName, $lastName, $username, $email, $hashedPassword, $phone]);
                $userId = (int)$pdo->lastInsertId();

                // 2. Create Teacher Details with Assigned Course & Category
                $teacherNum = 'TCH-' . date('Y') . '-' . str_pad($userId, 4, '0', STR_PAD_LEFT);
                $stmtTeacher = $pdo->prepare("
                    INSERT INTO teachers (user_id, assigned_course_id, assigned_category_id, teacher_number, qualification, specialization, experience_years, bio, status, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())
                ");
                $stmtTeacher->execute([
                    $userId,
                    $selectedCourseId,
                    $selectedCategoryId,
                    $teacherNum,
                    $qualification,
                    !empty($specialization) ? $specialization : $courseTitle,
                    $experienceYears,
                    $bio
                ]);
                $newTeacherId = (int)$pdo->lastInsertId();

                // Permanently link course -> teacher in courses table
                if ($newTeacherId > 0 && $selectedCourseId > 0) {
                    $pdo->prepare("UPDATE courses SET teacher_id = ? WHERE id = ?")
                        ->execute([$newTeacherId, $selectedCourseId]);
                }

                // 3. Create Teacher Application Record
                $pdo->prepare("
                    INSERT INTO teacher_applications (user_id, qualification, specialization, experience_years, application_message, status, created_at)
                    VALUES (?, ?, ?, ?, ?, 'approved', NOW())
                ")->execute([$userId, $qualification, $courseTitle, $experienceYears, $bio]);

                // 4. Generate Teacher Referral Code & Wallet
                if (function_exists('get_user_referral_code')) {
                    get_user_referral_code($userId);
                }

                // 5. Record any pending referral that brought this teacher
                if (function_exists('record_pending_referral')) {
                    record_pending_referral($userId);
                }

                $pdo->commit();

                // Clear temporary teacher course selection session
                unset($_SESSION['teacher_reg_category_id'], $_SESSION['teacher_reg_course_id']);

                // Create Session
                $_SESSION[SESSION_USER_ID]   = $userId;
                $_SESSION[SESSION_USER_ROLE] = 'teacher';
                $_SESSION[SESSION_USER_DATA] = [
                    'id'         => $userId,
                    'first_name' => $firstName,
                    'last_name'  => $lastName,
                    'email'      => $email,
                    'role'       => 'teacher',
                ];

                set_flash('success', 'Teacher account created successfully! You are assigned to teach: ' . $courseTitle);
                redirect('teacher/dashboard.php');
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log("Teacher Registration Error: " . $e->getMessage());
                $errors[] = 'Failed to create teacher account: ' . $e->getMessage();
            }
        }
    }
}

$seo_options = [
    'title'      => 'Teacher Registration | StudyMe Instructor Suite',
    'is_private' => true,
    'noindex'    => true
];
include BASE_PATH . '/includes/layouts/header.php';
?>

<div class="py-5 bg-light-subtle" style="min-height: calc(100vh - 120px);">
    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-lg-8 col-md-10">
                <div class="card border-0 shadow-lg rounded-4 overflow-hidden">
                    <div class="card-header bg-dark text-white p-4 text-center border-0" style="background: linear-gradient(135deg, #1e1b4b 0%, #312e81 100%) !important;">
                        <span class="badge bg-warning text-dark rounded-pill px-3 py-1 fw-bold text-uppercase small mb-2">
                            Step 2 of 2 — Profile Information
                        </span>
                        <h2 class="display-6 fw-bold text-white mb-1">Complete Teacher Account</h2>
                        <p class="text-white-50 small mb-0">Fill in your instructor details to access your teaching dashboard</p>
                    </div>

                    <div class="card-body p-4 p-md-5">

                        <!-- Assigned Course Banner -->
                        <div class="alert alert-primary border-primary border-opacity-25 rounded-4 p-3 mb-4 d-flex align-items-center justify-content-between flex-wrap gap-3">
                            <div class="d-flex align-items-center gap-3">
                                <div class="p-2 bg-primary text-white rounded-circle fs-3">
                                    <i class="bi bi-mortarboard-fill"></i>
                                </div>
                                <div>
                                    <span class="text-uppercase small fw-bold text-muted">Assigned Teaching Course:</span>
                                    <h5 class="fw-bold text-primary mb-0"><?= e($courseTitle) ?></h5>
                                    <small class="text-secondary">Faculty: <strong><?= e($categoryName) ?></strong></small>
                                </div>
                            </div>
                            <a href="<?= url('auth/teacher-course-select.php') ?>" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                                <i class="bi bi-pencil-square me-1"></i> Change Course
                            </a>
                        </div>

                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger rounded-3 mb-4">
                                <ul class="mb-0 small ps-3">
                                    <?php foreach ($errors as $err): ?>
                                        <li><?= e($err) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <form action="<?= url('auth/teacher-register.php') ?>" method="POST">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="first_name" class="form-label fw-semibold small">First Name</label>
                                    <input type="text" name="first_name" id="first_name" class="form-control py-2" placeholder="e.g. John" value="<?= e($firstName) ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="last_name" class="form-label fw-semibold small">Last Name</label>
                                    <input type="text" name="last_name" id="last_name" class="form-control py-2" placeholder="e.g. Doe" value="<?= e($lastName) ?>" required>
                                </div>

                                <div class="col-md-6">
                                    <label for="email" class="form-label fw-semibold small">Email Address</label>
                                    <input type="email" name="email" id="email" class="form-control py-2" placeholder="john.doe@example.com" value="<?= e($email) ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="phone" class="form-label fw-semibold small">Phone Number</label>
                                    <input type="text" name="phone" id="phone" class="form-control py-2" placeholder="+234 800 000 0000" value="<?= e($phone) ?>">
                                </div>

                                <div class="col-md-6">
                                    <label for="qualification" class="form-label fw-semibold small">Highest Qualification</label>
                                    <input type="text" name="qualification" id="qualification" class="form-control py-2" placeholder="e.g. B.Sc, M.Sc, Ph.D, Certified Educator" value="<?= e($qualification) ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="experience_years" class="form-label fw-semibold small">Teaching Experience (Years)</label>
                                    <input type="number" name="experience_years" id="experience_years" class="form-control py-2" min="0" max="50" value="<?= (int)$experienceYears ?>" required>
                                </div>

                                <div class="col-12">
                                    <label for="bio" class="form-label fw-semibold small">Instructor Bio / Specialization Overview</label>
                                    <textarea name="bio" id="bio" rows="3" class="form-control" placeholder="Briefly describe your teaching background and expertise in <?= e($courseTitle) ?>..."><?= e($bio) ?></textarea>
                                </div>

                                <div class="col-md-6">
                                    <label for="password" class="form-label fw-semibold small">Create Password</label>
                                    <input type="password" name="password" id="password" class="form-control py-2" placeholder="Minimum 6 characters" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="password_confirmation" class="form-label fw-semibold small">Confirm Password</label>
                                    <input type="password" name="password_confirmation" id="password_confirmation" class="form-control py-2" placeholder="Repeat password" required>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-warning btn-lg rounded-pill w-100 py-3 fw-bold text-dark shadow fs-5 mt-4" data-feedback="success">
                                Complete Registration &amp; Open Dashboard &rarr;
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include BASE_PATH . '/includes/layouts/footer.php'; ?>
