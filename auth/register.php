<?php

require_once dirname(__DIR__) . '/config/main.php';
require_once BASE_PATH . '/includes/functions/courses.php';
require_once BASE_PATH . '/includes/functions/referrals.php';

if (is_logged_in()) {
    $role = current_user_role();
    $pendingCourseId = (int)($_SESSION['pending_course_id'] ?? 0);
    if ($role === 'student' && $pendingCourseId > 0 && !(defined('FREE_TESTING_MODE') && FREE_TESTING_MODE)) {
        redirect('payments/checkout.php?course_id=' . $pendingCourseId);
    }
    $dash = $role === 'admin' ? 'admin/dashboard.php' : ($role === 'teacher' ? 'teacher/dashboard.php' : 'student/dashboard.php');
    redirect($dash);
}

$errors    = [];
$firstName = '';
$lastName  = '';
$email     = '';
$phone     = '';
$role      = 'student';

if (isset($_GET['action']) && $_GET['action'] === 'change_course') {
    unset($_SESSION['pending_course_id'], $_SESSION['pending_course_slug'], $_SESSION['pending_course_title']);
}

if (isset($_GET['secondary']) && $_GET['secondary'] == '1') {
    $secondaryBundleCourse = get_secondary_bundle_course();
    if ($secondaryBundleCourse) {
        $_SESSION['pending_course_id']    = (int)$secondaryBundleCourse['id'];
        $_SESSION['pending_course_slug']  = $secondaryBundleCourse['slug'] ?? 'secondary-waec-neco';
        $_SESSION['pending_course_title'] = $secondaryBundleCourse['title'] ?? 'Secondary School Complete Bundle';
    }
}

if (!empty($_GET['course_id'])) {
    $paramCourseId = (int)$_GET['course_id'];
    if ($paramCourseId > 0) {
        $cCheck = course_has_active_teacher($paramCourseId);
        if ($cCheck['can_enroll']) {
            $_SESSION['pending_course_id']    = $paramCourseId;
            $_SESSION['pending_course_slug']  = $cCheck['course']['slug'] ?? '';
            $_SESSION['pending_course_title'] = $cCheck['course']['title'] ?? '';
        } else {
            $errors[] = "Notice: There is no active teacher available for " . ($cCheck['course']['title'] ?? 'the selected course') . ". {$cCheck['reason']} Please choose a course with an active instructor.";
            unset($_SESSION['pending_course_id'], $_SESSION['pending_course_slug'], $_SESSION['pending_course_title']);
        }
    }
}

$pendingCourseId    = (int)($_SESSION['pending_course_id'] ?? 0);
$pendingCourseTitle = $_SESSION['pending_course_title'] ?? '';
$pendingTeacherInfo = null;

if ($pendingCourseId > 0) {
    $courseCheck = course_has_active_teacher($pendingCourseId);
    if (!$courseCheck['can_enroll']) {
        $denialReason = $courseCheck['reason'];
        unset($_SESSION['pending_course_id'], $_SESSION['pending_course_slug'], $_SESSION['pending_course_title']);
        $pendingCourseId = 0;
        $pendingCourseTitle = '';
        $errors[] = "Registration Notice: There is no active teacher available for the selected course ({$denialReason}). Please choose a course with an assigned instructor.";
    } else {
        $pendingTeacherInfo = $courseCheck['teacher'];
    }
}

$allPublishedCourses = [];
if ($pendingCourseId <= 0) {
    $pdo = getDBConnection();
    try {
        $coursesStmt = $pdo->query("
            SELECT c.id, c.title, c.category_id, cat.name AS category_name, cat.slug AS category_slug,
                   t.id AS teacher_id, t.status AS teacher_status, u.status AS user_status,
                   CONCAT(u.first_name, ' ', u.last_name) AS teacher_name
            FROM courses c
            LEFT JOIN categories cat ON c.category_id = cat.id
            LEFT JOIN teachers t ON c.teacher_id = t.id
            LEFT JOIN users u ON t.user_id = u.id
            WHERE c.status = 'published'
            ORDER BY cat.name ASC, c.title ASC
        ");
        $allPublishedCourses = $coursesStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error fetching courses for registration: " . $e->getMessage());
    }
}

$submittedCourseId = $pendingCourseId;

if (is_post()) {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName  = trim($_POST['last_name']  ?? '');
    $email     = trim(strtolower($_POST['email'] ?? ''));
    $phone     = trim($_POST['phone']      ?? '');
    $password  = $_POST['password'] ?? '';
    $role      = in_array($_POST['role'] ?? '', ['student', 'teacher']) ? $_POST['role'] : 'student';

    if (empty($firstName) || empty($lastName)) {
        $errors[] = 'First name and last name are required.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    }

    if ($role === 'student') {
        $submittedCourseId = (int)($_POST['course_id'] ?? $_SESSION['pending_course_id'] ?? 0);

        if ($submittedCourseId <= 0) {
            $errors[] = 'Course Selection Required: You must choose a course to complete registration.';
        } else {
            $check = course_has_active_teacher($submittedCourseId);
            if (!$check['can_enroll']) {
                $errors[] = "Registration Denied: There is no teacher available for this course. {$check['reason']} Please choose a course with an assigned active instructor.";
            } else {
                $_SESSION['pending_course_id']    = $submittedCourseId;
                $_SESSION['pending_course_slug']  = $check['course']['slug'] ?? '';
                $_SESSION['pending_course_title'] = $check['course']['title'] ?? '';
                $pendingCourseId                  = $submittedCourseId;
                $pendingCourseTitle               = $check['course']['title'] ?? '';
                $pendingTeacherInfo               = $check['teacher'];
            }
        }
    }

    if (empty($errors)) {
        $pdo  = getDBConnection();
        $studentTrack = 'university';
        if ($role === 'student' && $submittedCourseId > 0) {
            $courseTrackStmt = $pdo->prepare("SELECT cat.slug FROM courses c JOIN categories cat ON cat.id = c.category_id WHERE c.id = ? LIMIT 1");
            $courseTrackStmt->execute([$submittedCourseId]);
            $categorySlug = strtolower((string)($courseTrackStmt->fetchColumn() ?? ''));
            if (in_array($categorySlug, ['secondary', 'secondary-waec-neco'], true)) {
                $studentTrack = 'secondary';
            }
        }

        $stmt = $pdo->prepare("SELECT id, email, first_name, last_name, role, status, email_verified_at FROM users WHERE LOWER(TRIM(email)) = LOWER(TRIM(?)) LIMIT 1");
        $stmt->execute([$email]);
        $existingUser = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existingUser) {

            if (empty($existingUser['email_verified_at']) || $existingUser['status'] === 'pending') {
                try {
                    $userId = (int)$existingUser['id'];
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                    $upStmt = $pdo->prepare("
                        UPDATE users
                        SET first_name = ?, last_name = ?, phone = ?, password = ?, status = 'active', email_verified_at = NOW()
                        WHERE id = ?
                    ");
                    $upStmt->execute([$firstName, $lastName, $phone, $hashedPassword, $userId]);

                    if ($role === 'student') {
                        $stCheck = $pdo->prepare("SELECT id FROM students WHERE user_id = ? LIMIT 1");
                        $stCheck->execute([$userId]);
                        if (!$stCheck->fetch()) {
                            $studentNum = 'STD-' . date('Y') . '-' . str_pad($userId, 4, '0', STR_PAD_LEFT);
                            $pdo->prepare("INSERT INTO students (user_id, student_number, student_type, academic_level, target_exam) VALUES (?, ?, ?, ?, ?)")
                                ->execute([
                                    $userId,
                                    $studentNum,
                                    $studentTrack,
                                    $studentTrack === 'secondary' ? 'SS3 / WAEC Candidate' : '100 Level / Undergraduate',
                                    $studentTrack === 'secondary' ? 'WAEC / JAMB / NECO' : 'Degree Programme'
                                ]);
                        }
                    }

                    $_SESSION[SESSION_USER_ID] = $userId;
                    $_SESSION[SESSION_USER_ROLE] = $role;
                    $_SESSION[SESSION_USER_DATA] = [
                        'id' => $userId,
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                        'email' => $email,
                        'role' => $role,
                    ];

                    set_flash('success', 'Your account is ready. Welcome to StudyMe!');
                    if ($role === 'teacher') {
                        redirect('teacher/dashboard.php');
                    }
                    $studentCategoryStmt = $pdo->prepare("SELECT 1 FROM students s JOIN enrollments e ON e.student_id = s.id AND e.status = 'active' JOIN courses c ON c.id = e.course_id JOIN categories cat ON cat.id = c.category_id WHERE s.user_id = ? AND cat.slug IN ('secondary-waec-neco', 'secondary') LIMIT 1");
                    $studentCategoryStmt->execute([$userId]);
                    redirect($studentCategoryStmt->fetchColumn() ? 'student/secondary-dashboard.php' : 'student/dashboard.php');
                } catch (Exception $e) {
                    error_log("Error refreshing unverified user: " . $e->getMessage());
                    $errors[] = "An unverified account with this email exists. Please check your inbox or request a new verification code.";
                }
            } else {

                $loginUrl = url('auth/login.php?identifier=' . urlencode($email));
                $forgotUrl = url('auth/forgot-password.php?email=' . urlencode($email));
                $errors[] = "An account with the email <strong>" . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . "</strong> already exists.<br class='d-none d-sm-inline'> " .
                            "<a href='{$loginUrl}' class='btn btn-sm btn-primary rounded-pill px-3 py-1 mt-2 me-2 text-white fw-bold shadow-sm'><i class='bi bi-box-arrow-in-right me-1'></i> Log In</a>" .
                            "<a href='{$forgotUrl}' class='btn btn-sm btn-outline-secondary rounded-pill px-3 py-1 mt-2'><i class='bi bi-key me-1'></i> Reset Password</a>";
            }
        } else {

            try {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $usernameBase = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $firstName . $lastName));
                $usernameBase = $usernameBase !== '' ? $usernameBase : 'student';
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

                $pdo->beginTransaction();

                $stmt = $pdo->prepare("
                    INSERT INTO users (first_name, last_name, username, email, password, phone, role, status, email_verified_at, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'active', NOW(), NOW())
                ");
                $stmt->execute([$firstName, $lastName, $username, $email, $hashedPassword, $phone, $role]);
                $userId = (int)$pdo->lastInsertId();

                if ($role === 'student') {
                    $studentNum = 'STD-' . date('Y') . '-' . str_pad($userId, 4, '0', STR_PAD_LEFT);
                    $pdo->prepare("INSERT INTO students (user_id, student_number, student_type, academic_level, target_exam) VALUES (?, ?, ?, ?, ?)")
                        ->execute([
                            $userId,
                            $studentNum,
                            $studentTrack,
                            $studentTrack === 'secondary' ? 'SS3 / WAEC Candidate' : '100 Level / Undergraduate',
                            $studentTrack === 'secondary' ? 'WAEC / JAMB / NECO' : 'Degree Programme'
                        ]);
                } else {
                    $teacherNum = 'TCH-' . str_pad($userId, 3, '0', STR_PAD_LEFT);
                    $pdo->prepare("INSERT INTO teachers (user_id, teacher_number) VALUES (?, ?)")
                        ->execute([$userId, $teacherNum]);
                }

                $pdo->commit();

                if (function_exists('record_pending_referral')) {
                    record_pending_referral($userId);
                }

                $_SESSION[SESSION_USER_ID] = $userId;
                $_SESSION[SESSION_USER_ROLE] = $role;
                $_SESSION[SESSION_USER_DATA] = [
                    'id' => $userId,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => $email,
                    'role' => $role,
                ];

                if (function_exists('log_user_activity')) {
                    log_user_activity(
                        $userId,
                        'registered',
                        ucfirst($role) . ' account registered. Email: ' . $email
                    );
                }

                try {
                    $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, created_at) VALUES (?, ?, ?, 'welcome', NOW())")
                        ->execute([$userId, 'Welcome to StudyMe!', 'Your account is ready. Start learning with StudyMe.']);
                } catch (Exception $e) {  }

                    set_flash('success', "Your {$role} account has been created. Welcome to StudyMe!");
                    if ($role === 'teacher') {
                        redirect('teacher/dashboard.php');
                    }
                    $studentCategoryStmt = $pdo->prepare("SELECT 1 FROM students s JOIN enrollments e ON e.student_id = s.id AND e.status = 'active' JOIN courses c ON c.id = e.course_id JOIN categories cat ON cat.id = c.category_id WHERE s.user_id = ? AND cat.slug IN ('secondary-waec-neco', 'secondary') LIMIT 1");
                    $studentCategoryStmt->execute([$userId]);
                    redirect($studentCategoryStmt->fetchColumn() ? 'student/secondary-dashboard.php' : 'student/dashboard.php');
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log('Registration error: ' . $e->getMessage());
                $errors[] = (defined('APP_ENV') && APP_ENV === 'development')
                    ? 'Registration error: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')
                    : 'Registration error. Please try again.';
            }
        }
    }
}

$seo_options = [
    'title'       => 'Create Student Account | StudyMe AI Learning Platform',
    'description' => 'Register for StudyMe to start learning with certified Nigerian university instructors, WAEC/JAMB exam prep, and AI tutoring.'
];

include BASE_PATH . '/includes/layouts/header.php';
?>

<div class="auth-page-wrapper py-4 py-md-5 bg-light-subtle position-relative overflow-hidden" style="min-height: calc(100vh - 120px);">

    <div class="auth-bg-blob auth-bg-blob-1"></div>
    <div class="auth-bg-blob auth-bg-blob-2"></div>

    <div class="container position-relative py-2 py-sm-3">

        <div class="row justify-content-center mb-3 mb-md-4">
            <div class="col-12 col-md-10 col-lg-7">
                <div class="stepper-track bg-white p-2 p-sm-3 rounded-4 shadow-sm border border-subtle d-flex justify-content-between align-items-center">
                    <?php
                    $steps = ['Select Course', 'Create Account', 'Verify Email', 'Start Learning'];
                    $active = 1;
                    foreach ($steps as $i => $step):
                    ?>
                    <div class="stepper-item text-center flex-fill position-relative">
                        <div class="stepper-circle rounded-circle d-inline-flex align-items-center justify-content-center fw-bold shadow-sm mb-1"
                             style="width: 32px; height: 32px; font-size: 0.8rem;
                                    background: <?= $i < $active ? '#10b981' : ($i == $active ? '#2563eb' : '#f1f5f9') ?>;
                                    color: <?= $i <= $active ? '#ffffff' : '#64748b' ?>;
                                    border: 2px solid <?= $i == $active ? '#bfdbfe' : 'transparent' ?>;">
                            <?= $i < $active ? '<i class="bi bi-check-lg"></i>' : ($i + 1) ?>
                        </div>
                        <div class="stepper-label d-none d-sm-block fw-<?= $i === $active ? 'bold' : 'medium' ?> text-<?= $i === $active ? 'primary' : ($i < $active ? 'success' : 'muted') ?>" style="font-size: 0.75rem;">
                            <?= $step ?>
                        </div>
                    </div>
                    <?php if ($i < count($steps) - 1): ?>
                    <div class="stepper-line flex-fill" style="height: 3px; background: <?= $i < $active ? '#10b981' : '#e2e8f0' ?>; margin: 0 4px 10px 4px; border-radius: 4px;"></div>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="row justify-content-center">
            <div class="col-12 col-sm-11 col-md-9 col-lg-7 col-xl-6">

                <?php if ($pendingCourseId > 0 && !empty($pendingCourseTitle) && $pendingTeacherInfo): ?>
                <div class="alert alert-success rounded-4 mb-3 border-0 shadow-sm p-3 p-sm-4 d-flex align-items-center justify-content-between flex-wrap gap-2 animate-fade-in">
                    <div class="d-flex align-items-start gap-3">
                        <div class="rounded-circle bg-success text-white p-2 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 42px; height: 42px;">
                            <i class="bi bi-patch-check-fill fs-5"></i>
                        </div>
                        <div>
                            <div class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-1 mb-1 small fw-bold">
                                Course Selected
                            </div>
                            <h6 class="fw-bold text-dark mb-1"><?= e($pendingCourseTitle) ?></h6>
                            <div class="text-muted small">
                                <i class="bi bi-person-workspace text-primary me-1"></i> Instructor: <strong><?= e($pendingTeacherInfo['name'] ?? 'StudyMe Faculty') ?></strong>
                            </div>
                        </div>
                    </div>
                    <a href="<?= url('auth/register.php?action=change_course') ?>" class="btn btn-sm btn-outline-success rounded-pill px-3 py-1 fw-semibold">
                        <i class="bi bi-arrow-repeat me-1"></i> Change
                    </a>
                </div>
                <?php endif; ?>

                <div class="card auth-card border-0 shadow-lg rounded-4 p-3 p-sm-4 p-md-5 bg-white position-relative">

                    <div class="text-center mb-4">
                        <div class="auth-icon-box bg-primary bg-opacity-10 text-primary rounded-4 d-inline-flex align-items-center justify-content-center mb-3 shadow-sm"
                             style="width: 64px; height: 64px;">
                            <i class="bi bi-mortarboard-fill fs-2"></i>
                        </div>
                        <h2 class="fw-extrabold text-dark tracking-tight mb-1" style="font-size: 1.65rem;">Create Your Account</h2>
                        <p class="text-muted small mb-0">Join thousands of students learning with AI tutors and university faculty.</p>
                    </div>

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
                                <strong class="d-block mb-1">Please review the following:</strong>
                                <ul class="mb-0 ps-3 small">
                                    <?php foreach ($errors as $err): ?>
                                    <li class="py-1"><?= $err ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <form action="<?= url('auth/register.php') ?>" method="POST" id="registerForm" novalidate>
                        <input type="hidden" name="role" value="student">

                        <?php if ($pendingCourseId > 0): ?>
                            <input type="hidden" name="course_id" value="<?= (int)$pendingCourseId ?>">
                        <?php else: ?>

                            <div class="mb-3 p-3 bg-light-subtle rounded-4 border border-subtle">
                                <label for="course_select" class="form-label small fw-bold text-dark d-flex align-items-center gap-2 mb-2">
                                    <span class="badge bg-primary rounded-circle p-1 d-inline-flex"><i class="bi bi-book-half text-white" style="font-size: 0.8rem;"></i></span>
                                    Select Desired Course <span class="text-danger">*</span>
                                </label>
                                <select name="course_id" id="course_select" class="form-select form-select-lg rounded-3 fs-6 py-2" required onchange="handleCourseSelection(this)">
                                    <option value="">-- Choose a Course from Database --</option>
                                    <?php
                                    $grouped = [];
                                    foreach ($allPublishedCourses as $crs) {
                                        $grouped[$crs['category_name'] ?? 'General'][] = $crs;
                                    }
                                    foreach ($grouped as $catName => $cList):
                                    ?>
                                        <optgroup label="<?= e($catName) ?>">
                                            <?php foreach ($cList as $crs):
                                                $isSec = (($crs['category_slug'] ?? '') === 'secondary-waec-neco' || ($crs['category_slug'] ?? '') === 'secondary');
                                                $hasTeacher = !empty($crs['teacher_id']) && ($crs['teacher_status'] === 'active') && ($crs['user_status'] === 'active');
                                                $isAvailable = $isSec || $hasTeacher;
                                                $statusLabel = $isSec ? 'StudyMe Curriculum' : ($hasTeacher ? 'Instructor: ' . $crs['teacher_name'] : 'Instructor Pending');
                                            ?>
                                                <option value="<?= (int)$crs['id'] ?>"
                                                        data-available="<?= $isAvailable ? '1' : '0' ?>"
                                                        data-teacher="<?= e($isSec ? 'StudyMe Central Curriculum' : ($hasTeacher ? $crs['teacher_name'] : 'None')) ?>"
                                                        data-category="<?= e($catName) ?>"
                                                        <?= ($submittedCourseId == $crs['id']) ? 'selected' : '' ?>>
                                                    <?= e($crs['title']) ?> (<?= e($statusLabel) ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    <?php endforeach; ?>
                                </select>
                                <div id="courseStatusNotice" class="mt-2"></div>
                                <div class="form-text text-muted small mt-1">
                                    <i class="bi bi-info-circle me-1"></i> Live course catalog queried directly from the platform database.
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="row g-2 g-sm-3 mb-3">
                            <div class="col-12 col-sm-6">
                                <label for="first_name" class="form-label small fw-bold text-dark">First Name <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-person"></i></span>
                                    <input type="text" name="first_name" id="first_name" class="form-control form-control-lg fs-6 border-start-0 py-2"
                                           value="<?= e($firstName) ?>" required placeholder="e.g. David" autocomplete="given-name">
                                </div>
                            </div>
                            <div class="col-12 col-sm-6">
                                <label for="last_name" class="form-label small fw-bold text-dark">Last Name <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-person"></i></span>
                                    <input type="text" name="last_name" id="last_name" class="form-control form-control-lg fs-6 border-start-0 py-2"
                                           value="<?= e($lastName) ?>" required placeholder="e.g. Okonkwo" autocomplete="family-name">
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label small fw-bold text-dark">Email Address <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-envelope"></i></span>
                                <input type="email" name="email" id="email" class="form-control form-control-lg fs-6 border-start-0 py-2"
                                       value="<?= e($email) ?>" required placeholder="you@example.com" autocomplete="email" inputmode="email">
                            </div>
                            <div class="form-text small text-muted">A 6-digit verification code will be sent to this email.</div>
                        </div>

                        <div class="mb-3">
                            <label for="phone" class="form-label small fw-bold text-dark">Phone Number (Optional)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-telephone"></i></span>
                                <input type="tel" name="phone" id="phone" class="form-control form-control-lg fs-6 border-start-0 py-2"
                                       value="<?= e($phone) ?>" placeholder="+234 801 234 5678" autocomplete="tel">
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="password" class="form-label small fw-bold text-dark">Create Password <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-shield-lock"></i></span>
                                <input type="password" name="password" id="password" class="form-control form-control-lg fs-6 border-start-0 border-end-0 py-2"
                                       required placeholder="Min 6 characters" autocomplete="new-password">
                                <button type="button" class="btn btn-outline-secondary border-start-0 bg-light text-muted" id="togglePasswordBtn" onclick="togglePasswordVisibility('password', this)" title="Toggle password visibility">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="submit-box-wrapper mb-3">
                            <button type="submit" id="submitBtn" class="btn btn-primary btn-lg rounded-pill w-100 py-3 fw-bold shadow-sm d-flex align-items-center justify-content-center gap-2 hover-lift">
                                <span><?= $pendingCourseId > 0 ? 'Create Account & Continue' : 'Create My Free Account' ?></span>
                                <i class="bi bi-arrow-right fs-5"></i>
                            </button>
                        </div>

                        <div class="text-center py-2 border-top border-light-subtle">
                            <span class="text-muted small">Already registered?</span>
                            <a href="<?= url('auth/login.php') ?>" class="text-primary fw-bold text-decoration-none small ms-1 hover-underline">
                                Log in to your account <i class="bi bi-arrow-right-short"></i>
                            </a>
                        </div>
                    </form>
                </div>

                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-3 px-2">
                    <a href="<?= url('index.php') ?>" class="small text-muted text-decoration-none">
                        <i class="bi bi-arrow-left me-1"></i> Back to Homepage
                    </a>
                    <a href="<?= url('auth/teacher-register.php') ?>" class="small text-primary fw-semibold text-decoration-none">
                        <i class="bi bi-person-workspace me-1"></i> Are you a Teacher? Apply here
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

function handleCourseSelection(selectEl) {
    const notice = document.getElementById('courseStatusNotice');
    if (!notice) return;

    if (!selectEl.value) {
        notice.innerHTML = '';
        return;
    }

    const selectedOption = selectEl.options[selectEl.selectedIndex];
    const isAvailable = selectedOption.getAttribute('data-available') === '1';
    const teacherName = selectedOption.getAttribute('data-teacher') || 'Assigned Instructor';

    if (!isAvailable) {
        notice.innerHTML = `
            <div class="alert alert-warning py-2 px-3 small rounded-3 mb-0 border-0 shadow-sm d-flex align-items-center gap-2">
                <i class="bi bi-exclamation-triangle-fill text-warning fs-5 flex-shrink-0"></i>
                <div>
                    <strong>Instructor Pending:</strong> This course is being staffed. Please select an active course to register immediately.
                </div>
            </div>
        `;
    } else {
        notice.innerHTML = `
            <div class="alert alert-success py-2 px-3 small rounded-3 mb-0 border-0 shadow-sm d-flex align-items-center gap-2">
                <i class="bi bi-check-circle-fill text-success fs-5 flex-shrink-0"></i>
                <div>
                    <strong>Verified Instructor:</strong> ${teacherName}
                </div>
            </div>
        `;
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const courseSelect = document.getElementById('course_select');
    if (courseSelect && courseSelect.value) {
        handleCourseSelection(courseSelect);
    }
});
</script>

<?php include BASE_PATH . '/includes/layouts/footer.php'; ?>
