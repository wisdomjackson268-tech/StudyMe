<?php

require_once dirname(__DIR__) . '/config/main.php';
require_once BASE_PATH . '/includes/functions/courses.php';

if (is_logged_in()) {
    $role = current_user_role();
    $dash = $role === 'admin' ? 'admin/dashboard.php' : ($role === 'teacher' ? 'teacher/dashboard.php' : 'student/dashboard.php');
    redirect($dash);
}

$pdo = getDBConnection();

$stmtCats = $pdo->query("SELECT id, name, slug FROM categories WHERE slug IN ('technology', 'university') AND status = 'active' ORDER BY id ASC");
$allowedCategories = $stmtCats->fetchAll(PDO::FETCH_ASSOC);

$coursesByCategory = [];
foreach ($allowedCategories as $cat) {
    $stmtC = $pdo->prepare("
        SELECT id, title, slug, academic_level, teacher_id
        FROM courses
        WHERE category_id = ? AND status = 'published'
        ORDER BY title ASC
    ");
    $stmtC->execute([$cat['id']]);
    $coursesByCategory[$cat['id']] = [
        'category_name' => $cat['name'],
        'category_slug' => $cat['slug'],
        'courses'       => $stmtC->fetchAll(PDO::FETCH_ASSOC)
    ];
}

$selectedCategoryId = (int)($_SESSION['teacher_reg_category_id'] ?? ($allowedCategories[0]['id'] ?? 0));
$selectedCourseId   = (int)($_SESSION['teacher_reg_course_id']   ?? 0);
$courseChoiceType   = !empty($_SESSION['teacher_reg_create_new']) ? 'new' : ($selectedCourseId > 0 ? 'existing' : 'existing');

$errors          = [];
$firstName       = '';
$lastName        = '';
$email           = '';
$phone           = '';
$qualification   = '';
$specialization  = '';
$experienceYears = 1;
$bio             = '';
$newCourseTitle  = '';
$newCourseLevel  = '100 Level';
$newCourseDesc   = '';

if (is_post()) {
    $firstName          = trim($_POST['first_name'] ?? '');
    $lastName           = trim($_POST['last_name'] ?? '');
    $email              = trim(strtolower($_POST['email'] ?? ''));
    $phone              = trim($_POST['phone'] ?? '');
    $qualification      = trim($_POST['qualification'] ?? '');
    $specialization     = trim($_POST['specialization'] ?? '');
    $experienceYears    = (int)($_POST['experience_years'] ?? 1);
    $bio                = trim($_POST['bio'] ?? '');
    $password           = $_POST['password'] ?? '';
    $courseChoiceType   = trim($_POST['course_choice_type'] ?? 'existing');
    $selectedCategoryId = (int)($_POST['category_id'] ?? 0);
    $selectedCourseId   = (int)($_POST['course_id'] ?? 0);
    $newCourseTitle     = trim($_POST['new_course_title'] ?? '');
    $newCourseLevel     = trim($_POST['new_course_level'] ?? '100 Level');
    $newCourseDesc      = trim($_POST['new_course_desc'] ?? '');

    if (empty($firstName) || empty($lastName)) {
        $errors[] = 'First name and last name are required.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if (empty($qualification)) {
        $errors[] = 'Highest academic qualification is required.';
    }
    if (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    }
    if ($selectedCategoryId <= 0) {
        $errors[] = 'Please select your teaching track (Technology or University).';
    }

    if ($courseChoiceType === 'existing') {
        if ($selectedCourseId <= 0) {
            $errors[] = 'Please select an existing course to teach from the course list.';
        }
    } else {
        if (empty($newCourseTitle)) {
            $errors[] = 'Please enter a title for the new course you wish to create.';
        }
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id, email, first_name, last_name, role, status, email_verified_at FROM users WHERE LOWER(TRIM(email)) = LOWER(TRIM(?)) LIMIT 1");
        $stmt->execute([$email]);
        $existingUser = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existingUser) {
            if (empty($existingUser['email_verified_at']) || $existingUser['status'] === 'pending') {
                try {
                    $userId = (int)$existingUser['id'];
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, phone = ?, password = ?, role = 'teacher', status = 'active', email_verified_at = NOW() WHERE id = ?")
                        ->execute([$firstName, $lastName, $phone, $hashedPassword, $userId]);

                    $_SESSION[SESSION_USER_ID] = $userId;
                    $_SESSION[SESSION_USER_ROLE] = 'teacher';
                    $_SESSION[SESSION_USER_DATA] = [
                        'id' => $userId,
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                        'email' => $email,
                        'role' => 'teacher',
                    ];

                    set_flash('success', 'Your teacher account is ready. Welcome to StudyMe!');
                    redirect('teacher/dashboard.php');
                } catch (Exception $e) {
                    $errors[] = "An unverified account with this email exists. Please verify your email to continue.";
                }
            } else {
                $loginUrl = url('auth/teacher-login.php?identifier=' . urlencode($email));
                $forgotUrl = url('auth/forgot-password.php?email=' . urlencode($email));
                $errors[] = "An account with email <strong>" . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . "</strong> already exists.<br class='d-none d-sm-inline'> " .
                            "<a href='{$loginUrl}' class='btn btn-sm btn-primary rounded-pill px-3 py-1 mt-2 me-2 text-white fw-bold shadow-sm'><i class='bi bi-box-arrow-in-right me-1'></i> Teacher Sign In</a>" .
                            "<a href='{$forgotUrl}' class='btn btn-sm btn-outline-secondary rounded-pill px-3 py-1 mt-2'><i class='bi bi-key me-1'></i> Reset Password</a>";
            }
        } else {
            try {
                $pdo->beginTransaction();

                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $usernameBase = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $firstName . $lastName));
                $usernameBase = $usernameBase !== '' ? $usernameBase : 'teacher';
                $username = $usernameBase;
                $usernameSuffix = 0;
                do {
                    $usernameCheck = $pdo->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
                    $usernameCheck->execute([$username]);
                    if (!$usernameCheck->fetch()) {
                        break;
                    }
                    $usernameSuffix++;
                    $username = $usernameBase . $usernameSuffix;
                } while ($usernameSuffix < 10000);

                $stmtUser = $pdo->prepare("
                    INSERT INTO users (first_name, last_name, username, email, password, phone, role, status, email_verified_at, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, 'teacher', 'active', NOW(), NOW())
                ");
                $stmtUser->execute([$firstName, $lastName, $username, $email, $hashedPassword, $phone]);
                $userId = (int)$pdo->lastInsertId();

                $teacherNum = 'TCH-' . date('Y') . '-' . str_pad($userId, 4, '0', STR_PAD_LEFT);
                $finalAssignedCourseId = null;
                $finalCourseTitle = '';

                if ($courseChoiceType === 'existing') {
                    $finalAssignedCourseId = $selectedCourseId;
                    $stmtTitle = $pdo->prepare("SELECT title FROM courses WHERE id = ? LIMIT 1");
                    $stmtTitle->execute([$selectedCourseId]);
                    $finalCourseTitle = $stmtTitle->fetchColumn() ?: 'Assigned Course';
                } else {

                    $finalCourseTitle = $newCourseTitle;
                    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $newCourseTitle), '-')) . '-' . time();

                    $stmtCatSlug = $pdo->prepare("SELECT slug FROM categories WHERE id = ? LIMIT 1");
                    $stmtCatSlug->execute([$selectedCategoryId]);
                    $catSlug = $stmtCatSlug->fetchColumn();
                    $coursePrice = ($catSlug === 'university') ? 5000.00 : 10000.00;
                    $thumb = function_exists('get_course_thumbnail_url')
                        ? get_course_thumbnail_url('', $catSlug ?: 'technology', $slug)
                        : 'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?w=500&q=80';

                    $stmtNewCourse = $pdo->prepare("
                        INSERT INTO courses (category_id, title, slug, academic_level, academic_year, short_description, description, price, thumbnail, status, created_at)
                        VALUES (?, ?, ?, ?, 'Year 1', ?, ?, ?, ?, 'published', NOW())
                    ");
                    $stmtNewCourse->execute([
                        $selectedCategoryId,
                        $newCourseTitle,
                        $slug,
                        $newCourseLevel,
                        !empty($newCourseDesc) ? $newCourseDesc : 'Comprehensive curriculum instructed by ' . $firstName . ' ' . $lastName,
                        !empty($newCourseDesc) ? $newCourseDesc : 'Full interactive lecture series, downloadable resources, and live session tutorials.',
                        $coursePrice,
                        $thumb
                    ]);
                    $finalAssignedCourseId = (int)$pdo->lastInsertId();

                    $pdo->prepare("INSERT INTO course_sections (course_id, title, sort_order, created_at) VALUES (?, 'Module 1: Course Overview & Foundations', 1, NOW())")
                        ->execute([$finalAssignedCourseId]);
                }

                $stmtTeacher = $pdo->prepare("
                    INSERT INTO teachers (user_id, assigned_course_id, assigned_category_id, teacher_number, qualification, specialization, experience_years, bio, status, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())
                ");
                $stmtTeacher->execute([
                    $userId,
                    $finalAssignedCourseId,
                    $selectedCategoryId,
                    $teacherNum,
                    $qualification,
                    !empty($specialization) ? $specialization : $finalCourseTitle,
                    $experienceYears,
                    $bio
                ]);
                $newTeacherId = (int)$pdo->lastInsertId();

                if ($newTeacherId > 0 && $finalAssignedCourseId > 0) {
                    $pdo->prepare("UPDATE courses SET teacher_id = ? WHERE id = ?")
                        ->execute([$newTeacherId, $finalAssignedCourseId]);
                }

                $pdo->prepare("
                    INSERT INTO teacher_applications (user_id, qualification, specialization, experience_years, application_message, status, created_at)
                    VALUES (?, ?, ?, ?, ?, 'approved', NOW())
                ")->execute([$userId, $qualification, $finalCourseTitle, $experienceYears, $bio]);

                if (function_exists('get_user_referral_code')) {
                    get_user_referral_code($userId);
                }

                if (function_exists('record_pending_referral')) {
                    record_pending_referral($userId);
                }

                $pdo->commit();

                unset($_SESSION['teacher_reg_category_id'], $_SESSION['teacher_reg_course_id'], $_SESSION['teacher_reg_create_new']);

                $_SESSION[SESSION_USER_ID]   = $userId;
                $_SESSION[SESSION_USER_ROLE] = 'teacher';
                $_SESSION[SESSION_USER_DATA] = [
                    'id'         => $userId,
                    'first_name' => $firstName,
                    'last_name'  => $lastName,
                    'email'      => $email,
                    'role'       => 'teacher',
                ];

                set_flash('success', "Teacher account registered successfully! You are assigned to instruct \"{$finalCourseTitle}\". Welcome to your Instructor Suite!");
                redirect('teacher/dashboard.php');
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log("Teacher Registration Error: " . $e->getMessage());
                $errors[] = (defined('APP_ENV') && APP_ENV === 'development')
                    ? 'Failed to complete teacher registration: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')
                    : 'Failed to complete teacher registration. Please try again.';
            }
        }
    }
}

$pageTitle = 'Teacher Registration | StudyMe Instructor Suite';
include BASE_PATH . '/includes/layouts/header.php';
?>

<div class="auth-page-wrapper py-4 py-md-5 bg-light-subtle position-relative overflow-hidden" style="min-height: calc(100vh - 120px);">
    <div class="container position-relative py-2 py-sm-3">
        <div class="row justify-content-center">
            <div class="col-12 col-md-11 col-lg-9 col-xl-8">

                <div class="card auth-card border-0 shadow-lg rounded-4 overflow-hidden bg-white">

                    <div class="card-header p-4 p-md-5 text-center border-0 text-white"
                         style="background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #312e81 100%) !important;">
                        <div class="auth-icon-box bg-warning bg-opacity-20 text-warning rounded-4 d-inline-flex align-items-center justify-content-center mb-3 shadow-sm"
                             style="width: 60px; height: 60px;">
                            <i class="bi bi-mortarboard-fill fs-2"></i>
                        </div>
                        <h1 class="h2 fw-bold text-white mb-2">Join the StudyMe Educator Faculty</h1>
                        <p class="text-white-50 col-lg-10 mx-auto mb-0 small">
                            Teach university &amp; technology students, host live interactive classes, manage real-time attendance, and earn revenue from your expertise.
                        </p>
                    </div>

                    <div class="card-body p-3 p-sm-4 p-md-5">

                        <?php foreach (get_flash() as $type => $messages): ?>
                            <?php foreach ($messages as $msg): ?>
                                <div class="alert alert-<?= $type === 'error' ? 'danger' : ($type === 'info' ? 'info' : 'success') ?> rounded-4 mb-3 p-3 border-0 shadow-sm d-flex align-items-center gap-2">
                                    <i class="bi <?= $type === 'error' ? 'bi-exclamation-circle-fill text-danger' : ($type === 'info' ? 'bi-info-circle-fill text-info' : 'bi-check-circle-fill text-success') ?> fs-5"></i>
                                    <div class="small fw-semibold"><?= $msg ?></div>
                                </div>
                            <?php endforeach; ?>
                        <?php endforeach; ?>

                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger rounded-4 mb-4 border-0 shadow-sm p-3">
                                <div class="d-flex align-items-start gap-2">
                                    <i class="bi bi-exclamation-triangle-fill text-danger fs-5 flex-shrink-0 mt-1"></i>
                                    <div class="flex-grow-1">
                                        <strong class="d-block mb-1">Please resolve the following issues:</strong>
                                        <ul class="mb-0 ps-3 small">
                                            <?php foreach ($errors as $err): ?>
                                                <li class="py-1"><?= $err ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <form action="<?= url('auth/teacher-register.php') ?>" method="POST" id="teacherRegisterForm" novalidate>

                            <div class="mb-4">
                                <h5 class="fw-bold text-dark border-bottom pb-2 mb-3 d-flex align-items-center gap-2">
                                    <span class="badge bg-primary rounded-circle p-1 d-inline-flex"><i class="bi bi-person-badge-fill text-white" style="font-size: 0.85rem;"></i></span>
                                    <span>1. Instructor Credentials</span>
                                </h5>

                                <div class="row g-2 g-sm-3">
                                    <div class="col-12 col-sm-6">
                                        <label for="first_name" class="form-label fw-semibold small">First Name <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-person"></i></span>
                                            <input type="text" name="first_name" id="first_name" class="form-control form-control-lg fs-6 border-start-0 py-2" placeholder="e.g. Sarah" value="<?= e($firstName) ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-12 col-sm-6">
                                        <label for="last_name" class="form-label fw-semibold small">Last Name <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-person"></i></span>
                                            <input type="text" name="last_name" id="last_name" class="form-control form-control-lg fs-6 border-start-0 py-2" placeholder="e.g. Jenkins" value="<?= e($lastName) ?>" required>
                                        </div>
                                    </div>

                                    <div class="col-12 col-sm-6">
                                        <label for="email" class="form-label fw-semibold small">Email Address <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-envelope"></i></span>
                                            <input type="email" name="email" id="email" class="form-control form-control-lg fs-6 border-start-0 py-2" placeholder="sarah.jenkins@university.edu" value="<?= e($email) ?>" required inputmode="email">
                                        </div>
                                    </div>

                                    <div class="col-12 col-sm-6">
                                        <label for="phone" class="form-label fw-semibold small">Phone Number</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-telephone"></i></span>
                                            <input type="tel" name="phone" id="phone" class="form-control form-control-lg fs-6 border-start-0 py-2" placeholder="+234 801 234 5678" value="<?= e($phone) ?>">
                                        </div>
                                    </div>

                                    <div class="col-12 col-sm-6">
                                        <label for="qualification" class="form-label fw-semibold small">Highest Qualification <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-award"></i></span>
                                            <input type="text" name="qualification" id="qualification" class="form-control form-control-lg fs-6 border-start-0 py-2" placeholder="e.g. Ph.D., M.Sc., B.Sc." value="<?= e($qualification) ?>" required>
                                        </div>
                                    </div>

                                    <div class="col-12 col-sm-6">
                                        <label for="experience_years" class="form-label fw-semibold small">Teaching Experience (Years) <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-calendar-check"></i></span>
                                            <input type="number" name="experience_years" id="experience_years" class="form-control form-control-lg fs-6 border-start-0 py-2" min="0" max="60" value="<?= (int)$experienceYears ?>" required>
                                        </div>
                                    </div>

                                    <div class="col-12">
                                        <label for="specialization" class="form-label fw-semibold small">Area of Specialization</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-lightbulb"></i></span>
                                            <input type="text" name="specialization" id="specialization" class="form-control form-control-lg fs-6 border-start-0 py-2" placeholder="e.g. Software Engineering, Machine Learning, Organic Chemistry" value="<?= e($specialization) ?>">
                                        </div>
                                    </div>

                                    <div class="col-12">
                                        <label for="bio" class="form-label fw-semibold small">Instructor Bio / Overview</label>
                                        <textarea name="bio" id="bio" rows="3" class="form-control fs-6 rounded-3" placeholder="Briefly introduce yourself, your academic credentials, and teaching philosophy..."><?= e($bio) ?></textarea>
                                    </div>

                                    <div class="col-12">
                                        <label for="password" class="form-label fw-semibold small">Create Password <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-shield-lock"></i></span>
                                            <input type="password" name="password" id="password" class="form-control form-control-lg fs-6 border-start-0 border-end-0 py-2" placeholder="Minimum 6 characters" required autocomplete="new-password">
                                            <button type="button" class="btn btn-outline-secondary border-start-0 bg-light text-muted" onclick="togglePasswordVisibility('password', this)" title="Toggle password visibility">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-4">
                                <h5 class="fw-bold text-dark border-bottom pb-2 mb-3 d-flex align-items-center gap-2">
                                    <span class="badge bg-primary rounded-circle p-1 d-inline-flex"><i class="bi bi-journal-bookmark-fill text-white" style="font-size: 0.85rem;"></i></span>
                                    <span>2. Teaching Track &amp; Course Setup</span>
                                </h5>

                                <p class="text-muted small mb-3">Every verified educator is linked to an active course. You can select an existing published curriculum or create a custom course right now.</p>

                                <div class="mb-3">
                                    <label class="form-label fw-bold small text-muted text-uppercase">Select Teaching Track <span class="text-danger">*</span></label>
                                    <div class="row g-2 g-sm-3">
                                        <?php foreach ($allowedCategories as $cat):
                                            $isCatActive = ($selectedCategoryId === (int)$cat['id']);
                                        ?>
                                            <div class="col-12 col-sm-6">
                                                <div class="card p-3 border-2 rounded-4 category-card cursor-pointer transition-all <?= $isCatActive ? 'border-primary bg-primary bg-opacity-10 shadow-sm' : 'border-light-subtle bg-light-subtle' ?>"
                                                     onclick="selectCategory(<?= (int)$cat['id'] ?>)"
                                                     id="catCard_<?= (int)$cat['id'] ?>"
                                                     style="cursor: pointer;">
                                                    <div class="form-check d-flex align-items-center gap-2 m-0">
                                                        <input class="form-check-input" type="radio" name="category_id" id="catRadio_<?= (int)$cat['id'] ?>" value="<?= (int)$cat['id'] ?>" <?= $isCatActive ? 'checked' : '' ?> onchange="onCategoryChanged(<?= (int)$cat['id'] ?>)">
                                                        <label class="form-check-label fw-bold text-dark mb-0 cursor-pointer" for="catRadio_<?= (int)$cat['id'] ?>" style="cursor: pointer;">
                                                            <?= ($cat['slug'] === 'university') ? '<i class="bi bi-mortarboard-fill text-primary me-1"></i> University Faculty' : '<i class="bi bi-laptop-fill text-success me-1"></i> Technology &amp; Coding' ?>
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <div class="card border rounded-4 p-3 p-sm-4 mb-3 bg-white shadow-sm">
                                    <label class="form-label fw-bold text-dark mb-3 small text-uppercase text-muted">Course Assignment Mode:</label>

                                    <div class="d-flex flex-column flex-sm-row gap-2 mb-3">
                                        <div class="form-check form-check-inline flex-fill p-3 border rounded-3 bg-light-subtle m-0">
                                            <input class="form-check-input ms-0 me-2" type="radio" name="course_choice_type" id="choice_existing" value="existing" <?= $courseChoiceType === 'existing' ? 'checked' : '' ?> onchange="toggleCourseChoice('existing')">
                                            <label class="form-check-label fw-bold text-dark cursor-pointer" for="choice_existing">
                                                <i class="bi bi-list-check text-primary me-1"></i> Select Existing Course
                                            </label>
                                        </div>
                                        <div class="form-check form-check-inline flex-fill p-3 border rounded-3 bg-light-subtle m-0">
                                            <input class="form-check-input ms-0 me-2" type="radio" name="course_choice_type" id="choice_new" value="new" <?= $courseChoiceType === 'new' ? 'checked' : '' ?> onchange="toggleCourseChoice('new')">
                                            <label class="form-check-label fw-bold text-dark cursor-pointer" for="choice_new">
                                                <i class="bi bi-plus-circle-fill text-success me-1"></i> Create New Course
                                            </label>
                                        </div>
                                    </div>

                                    <div id="existingCourseBlock" style="<?= $courseChoiceType === 'new' ? 'display:none;' : '' ?>">
                                        <label for="course_id" class="form-label fw-semibold small">Choose Course from Database <span class="text-danger">*</span></label>
                                        <select name="course_id" id="course_id" class="form-select form-select-lg fs-6 py-2 rounded-3">
                                            <option value="">-- Select a Course to Instruct --</option>
                                            <?php foreach ($coursesByCategory as $catId => $catData): ?>
                                                <optgroup label="<?= e($catData['category_name']) ?>" class="cat-group-<?= $catId ?>">
                                                    <?php foreach ($catData['courses'] as $crs): ?>
                                                        <option value="<?= (int)$crs['id'] ?>" data-cat="<?= $catId ?>" <?= ($selectedCourseId === (int)$crs['id']) ? 'selected' : '' ?>>
                                                            <?= e($crs['title']) ?> (<?= e($crs['academic_level'] ?? 'General') ?>)
                                                        </option>
                                                    <?php endforeach; ?>
                                                </optgroup>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div id="newCourseBlock" style="<?= $courseChoiceType === 'new' ? '' : 'display:none;' ?>">
                                        <div class="row g-2 g-sm-3">
                                            <div class="col-12 col-sm-8">
                                                <label for="new_course_title" class="form-label fw-semibold small">New Course Title <span class="text-danger">*</span></label>
                                                <input type="text" name="new_course_title" id="new_course_title" class="form-control form-control-lg fs-6 py-2 rounded-3" placeholder="e.g. Advanced Artificial Intelligence & Robotics" value="<?= e($newCourseTitle) ?>">
                                            </div>
                                            <div class="col-12 col-sm-4">
                                                <label for="new_course_level" class="form-label fw-semibold small">Academic Level</label>
                                                <select name="new_course_level" id="new_course_level" class="form-select form-select-lg fs-6 py-2 rounded-3">
                                                    <option value="100 Level" <?= $newCourseLevel === '100 Level' ? 'selected' : '' ?>>100 Level (Beginner)</option>
                                                    <option value="200 Level" <?= $newCourseLevel === '200 Level' ? 'selected' : '' ?>>200 Level (Intermediate)</option>
                                                    <option value="300 Level" <?= $newCourseLevel === '300 Level' ? 'selected' : '' ?>>300 Level (Advanced)</option>
                                                    <option value="400 Level" <?= $newCourseLevel === '400 Level' ? 'selected' : '' ?>>400 Level (Expert)</option>
                                                    <option value="Professional" <?= $newCourseLevel === 'Professional' ? 'selected' : '' ?>>Professional / Industry</option>
                                                </select>
                                            </div>
                                            <div class="col-12">
                                                <label for="new_course_desc" class="form-label fw-semibold small">Short Course Overview</label>
                                                <textarea name="new_course_desc" id="new_course_desc" rows="2" class="form-control fs-6 rounded-3" placeholder="What will students learn in this course?"><?= e($newCourseDesc) ?></textarea>
                                            </div>
                                        </div>
                                    </div>

                                </div>
                            </div>

                            <div class="submit-box-wrapper mb-3">
                                <button type="submit" id="submitTeacherBtn" class="btn btn-primary btn-lg rounded-pill w-100 py-3 fw-bold shadow-sm d-flex align-items-center justify-content-center gap-2 hover-lift">
                                    <span>Complete Registration &amp; Open Teacher Suite</span>
                                    <i class="bi bi-arrow-right fs-5"></i>
                                </button>
                            </div>

                            <div class="text-center py-2 border-top border-light-subtle">
                                <span class="text-muted small">Already a registered instructor?</span>
                                <a href="<?= url('auth/teacher-login.php') ?>" class="text-primary fw-bold text-decoration-none small ms-1 hover-underline">
                                    Teacher Sign In <i class="bi bi-arrow-right-short"></i>
                                </a>
                            </div>

                        </form>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-3 px-2">
                    <a href="<?= url('index.php') ?>" class="small text-muted text-decoration-none">
                        <i class="bi bi-arrow-left me-1"></i> Back to Homepage
                    </a>
                    <a href="<?= url('auth/register.php') ?>" class="small text-primary fw-semibold text-decoration-none">
                        <i class="bi bi-mortarboard me-1"></i> Student Registration
                    </a>
                </div>

            </div>
        </div>
    </div>
</div>

<script>
function togglePasswordVisibility(inputId, btn) {
    const input = document.getElementById(inputId);
    const icon = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('bi-eye');
        icon.classList.add('bi-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('bi-eye-slash');
        icon.classList.add('bi-eye');
    }
}

function selectCategory(catId) {
    const radio = document.getElementById('catRadio_' + catId);
    if (radio) {
        radio.checked = true;
        onCategoryChanged(catId);
    }
}

function onCategoryChanged(catId) {
    document.querySelectorAll('.category-card').forEach(card => {
        card.classList.remove('border-primary', 'bg-primary', 'bg-opacity-10', 'shadow-sm');
        card.classList.add('border-light-subtle', 'bg-light-subtle');
    });
    const activeCard = document.getElementById('catCard_' + catId);
    if (activeCard) {
        activeCard.classList.add('border-primary', 'bg-primary', 'bg-opacity-10', 'shadow-sm');
        activeCard.classList.remove('border-light-subtle', 'bg-light-subtle');
    }
}

function toggleCourseChoice(type) {
    const existingBlock = document.getElementById('existingCourseBlock');
    const newBlock = document.getElementById('newCourseBlock');
    if (type === 'existing') {
        existingBlock.style.display = 'block';
        newBlock.style.display = 'none';
    } else {
        existingBlock.style.display = 'none';
        newBlock.style.display = 'block';
    }
}
</script>

<?php include BASE_PATH . '/includes/layouts/footer.php'; ?>
