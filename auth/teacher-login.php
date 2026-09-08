<?php
/**
 * StudyMe AI Platform — Dedicated Teacher Sign In Portal
 */
require_once dirname(__DIR__) . '/config/main.php';

if (is_logged_in()) {
    $role = current_user_role();
    if ($role === 'teacher') {
        redirect('teacher/dashboard.php');
    } elseif ($role === 'admin') {
        redirect('admin/dashboard.php');
    } else {
        redirect('student/dashboard.php');
    }
}

$errors     = [];
$identifier = '';

if (is_post()) {
    $identifier = trim($_POST['identifier'] ?? '');
    $password   = $_POST['password'] ?? '';

    if (empty($identifier) || empty($password)) {
        $errors[] = 'Please enter your email/username and password.';
    } else {
        $pdo  = getDBConnection();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE (email = ? OR username = ?) AND role IN ('teacher', 'admin') LIMIT 1");
        $stmt->execute([$identifier, $identifier]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            if ($user['status'] === 'inactive' || $user['status'] === 'suspended') {
                $errors[] = 'Your instructor account has been deactivated. Please contact administrator.';
            } else {
                $_SESSION[SESSION_USER_ID]   = $user['id'];
                $_SESSION[SESSION_USER_ROLE] = $user['role'];
                $_SESSION[SESSION_USER_DATA] = [
                    'id'         => $user['id'],
                    'first_name' => $user['first_name'],
                    'last_name'  => $user['last_name'],
                    'email'      => $user['email'],
                    'role'       => $user['role'],
                ];

                $pdo->prepare("UPDATE users SET last_login_at = NOW(), status = IF(status='pending','active',status) WHERE id = ?")
                    ->execute([$user['id']]);

                set_flash('success', 'Welcome back to the Instructor Suite, ' . $user['first_name'] . '!');
                redirect($user['role'] === 'admin' ? 'admin/dashboard.php' : 'teacher/dashboard.php');
            }
        } else {
            $errors[] = 'Invalid teacher credentials. If you are a student, please use student login.';
        }
    }
}

$seo_options = [
    'title'      => 'Teacher Login | StudyMe Instructor Suite',
    'is_private' => true,
    'noindex'    => true
];
include BASE_PATH . '/includes/layouts/header.php';
?>

<div class="py-5 bg-light-subtle d-flex align-items-center" style="min-height: calc(100vh - 120px);">
    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-lg-5 col-md-7">
                <div class="card border-0 shadow-lg rounded-4 overflow-hidden">
                    <div class="card-header bg-dark text-white p-4 text-center border-0" style="background: linear-gradient(135deg, #1e1b4b 0%, #312e81 100%) !important;">
                        <div class="p-3 bg-warning bg-opacity-20 text-warning rounded-circle d-inline-flex mb-2 fs-2">
                            <i class="bi bi-person-workspace"></i>
                        </div>
                        <h3 class="fw-bold text-white mb-1">Teacher Sign In</h3>
                        <p class="text-white-50 small mb-0">Access your assigned course, lessons, quizzes &amp; student analytics</p>
                    </div>
                    <div class="card-body p-4 p-md-5">
                        
                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger rounded-3 mb-4">
                                <ul class="mb-0 small ps-3">
                                    <?php foreach ($errors as $err): ?>
                                        <li><?= e($err) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <form action="<?= url('auth/teacher-login.php') ?>" method="POST">
                            <div class="mb-3">
                                <label for="identifier" class="form-label fw-semibold small">Teacher Email or Username</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-envelope"></i></span>
                                    <input type="text" name="identifier" id="identifier" class="form-control border-start-0" placeholder="teacher@studyme.online" value="<?= e($identifier) ?>" required>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label for="password" class="form-label fw-semibold small">Password</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-lock"></i></span>
                                    <input type="password" name="password" id="password" class="form-control border-start-0" placeholder="••••••••" required>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-warning btn-lg rounded-pill w-100 fw-bold shadow text-dark py-3 mb-3" data-feedback="click">
                                <i class="bi bi-box-arrow-in-right me-1"></i> Sign In to Instructor Suite
                            </button>
                        </form>

                        <div class="text-center pt-3 border-top mt-3">
                            <p class="small text-muted mb-2">Want to become a verified educator on StudyMe?</p>
                            <a href="<?= url('auth/teacher-course-select.php') ?>" class="btn btn-outline-primary btn-sm rounded-pill px-4 fw-bold">
                                Become a Teacher &rarr;
                            </a>
                        </div>
                        <div class="text-center pt-2">
                            <a href="<?= url('auth/login.php') ?>" class="text-decoration-none small text-secondary">
                                Student Sign In Instead
                            </a>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include BASE_PATH . '/includes/layouts/footer.php'; ?>
