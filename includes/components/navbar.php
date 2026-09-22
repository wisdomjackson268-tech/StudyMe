<?php

$currentUri = $_SERVER['REQUEST_URI'] ?? '';
$isLoggedIn = function_exists('is_logged_in') && is_logged_in();
$currentUser = $isLoggedIn ? current_user() : null;
$currentRole = $isLoggedIn ? current_user_role() : null;

$dashUrl = 'auth/login.php';
if ($isLoggedIn) {
    if ($currentRole === 'admin') {
        $dashUrl = 'admin/dashboard.php';
    } elseif ($currentRole === 'teacher') {
        $dashUrl = 'teacher/dashboard.php';
    } else {
        $dashUrl = 'student/dashboard.php';
    }
}

if (!function_exists('is_nav_active')) {
    function is_nav_active($slug, $currentUri) {
        if ($slug === 'index' && ($currentUri === '/' || $currentUri === '' || strpos($currentUri, 'index.php') !== false)) {
            return 'active';
        }
        if ($slug !== 'index' && strpos($currentUri, $slug) !== false) {
            return 'active';
        }
        return '';
    }
}
?>
<nav class="navbar navbar-expand-lg custom-navbar fixed-top" id="mainNavbar">
    <div class="container-fluid px-3 px-lg-4 px-xl-5 navbar-container">
        <a class="navbar-brand logo d-flex align-items-center gap-2" href="<?= url('index.php') ?>" aria-label="StudyMe Home">
            <div class="logo-icon-wrapper shadow-sm">
                <i class="bi bi-mortarboard-fill"></i>
            </div>
            <div class="d-flex flex-column">
                <span class="logo-text fw-bold">StudyMe</span>
                <span class="logo-subtext d-none d-sm-block text-muted">AI-Powered Learning</span>
            </div>
        </a>

        <div class="d-flex align-items-center gap-2 d-lg-none mobile-header-controls">
            <button class="btn mobile-header-btn theme-btn" type="button" aria-label="Toggle Dark/Light Theme" data-feedback="click" title="Switch Theme">
                <i class="bi bi-moon-stars-fill"></i>
            </button>

            <button class="navbar-toggler custom-toggler border-0 shadow-none" 
                    type="button" 
                    data-bs-toggle="collapse" 
                    data-bs-target="#navbarMenu" 
                    aria-controls="navbarMenu" 
                    aria-expanded="false" 
                    aria-label="Toggle navigation"
                    id="navbarTogglerBtn">
                <span class="hamburger-box">
                    <span class="hamburger-bar top-bar"></span>
                    <span class="hamburger-bar mid-bar"></span>
                    <span class="hamburger-bar bot-bar"></span>
                </span>
            </button>
        </div>

        <div class="collapse navbar-collapse" id="navbarMenu">
            <ul class="navbar-nav mx-auto mb-3 mb-lg-0 nav-menu-list">
                <li class="nav-item">
                    <a class="nav-link <?= is_nav_active('index', $currentUri) ?>" href="<?= url('index.php') ?>">
                        <span class="nav-link-icon-box d-lg-none bg-primary bg-opacity-10 text-primary">
                            <i class="bi bi-house-door-fill"></i>
                        </span>
                        <span>Home</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= is_nav_active('courses', $currentUri) ?>" href="<?= url('courses/index.php') ?>">
                        <span class="nav-link-icon-box d-lg-none bg-success bg-opacity-10 text-success">
                            <i class="bi bi-collection-play-fill"></i>
                        </span>
                        <span>Courses</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-nowrap <?= is_nav_active('ai-learning', $currentUri) ?>" href="<?= url('ai-learning.php') ?>">
                        <span class="nav-link-icon-box d-lg-none bg-warning bg-opacity-10 text-warning">
                            <i class="bi bi-stars"></i>
                        </span>
                        <span>AI Learning</span>
                        <span class="badge bg-warning text-dark ms-1 nav-badge-pill">AI</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= is_nav_active('teachers', $currentUri) ?>" href="<?= url('teachers.php') ?>">
                        <span class="nav-link-icon-box d-lg-none bg-info bg-opacity-10 text-info">
                            <i class="bi bi-person-badge-fill"></i>
                        </span>
                        <span>Instructors</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= is_nav_active('pricing', $currentUri) ?>" href="<?= url('pricing.php') ?>">
                        <span class="nav-link-icon-box d-lg-none bg-danger bg-opacity-10 text-danger">
                            <i class="bi bi-tag-fill"></i>
                        </span>
                        <span>Pricing</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= is_nav_active('about', $currentUri) ?>" href="<?= url('about.php') ?>">
                        <span class="nav-link-icon-box d-lg-none bg-secondary bg-opacity-10 text-secondary">
                            <i class="bi bi-info-circle-fill"></i>
                        </span>
                        <span>About</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= is_nav_active('contact', $currentUri) ?>" href="<?= url('contact.php') ?>">
                        <span class="nav-link-icon-box d-lg-none bg-primary bg-opacity-10 text-primary">
                            <i class="bi bi-envelope-fill"></i>
                        </span>
                        <span>Contact</span>
                    </a>
                </li>
            </ul>

            <div class="navbar-actions d-flex align-items-center gap-2 flex-wrap flex-lg-nowrap">
                <button class="btn theme-btn d-none d-lg-inline-flex" type="button" aria-label="Toggle Theme" data-feedback="click" title="Switch Theme (Dark / Light)">
                    <i class="bi bi-moon-stars-fill"></i>
                </button>

                <button class="btn theme-btn sound-btn d-none d-lg-inline-flex" type="button" aria-label="Toggle Sound" data-feedback="none" title="Toggle Sound Feedback">
                    <i class="bi bi-volume-up-fill"></i>
                </button>

                <?php if ($isLoggedIn): ?>
                    <a href="<?= url($dashUrl) ?>" class="btn btn-primary rounded-pill px-3 py-2 fw-bold nav-cta-btn d-inline-flex align-items-center justify-content-center gap-2 text-nowrap" data-feedback="click">
                        <i class="bi bi-grid-fill"></i>
                        <span>Dashboard</span>
                    </a>
                <?php else: ?>
                    <a href="<?= url('auth/teacher-course-select.php') ?>" class="btn btn-outline-warning btn-sm rounded-pill fw-semibold text-nowrap d-none d-xxl-inline-flex nav-teacher-cta px-3 py-1.5">
                        <i class="bi bi-person-workspace me-1"></i> Teach
                    </a>
                    <div class="d-flex align-items-center gap-2 auth-btn-group">
                        <a href="<?= url('auth/login.php') ?>" class="btn btn-login text-nowrap" data-feedback="click">
                            <i class="bi bi-box-arrow-in-right me-1"></i> Log In
                        </a>
                        <a href="<?= url('auth/register.php') ?>" class="btn btn-start text-nowrap" data-feedback="success">
                            <span>Get Started</span> <i class="bi bi-arrow-right-short ms-1"></i>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="d-lg-none pt-3 mt-3 border-top border-secondary border-opacity-10 mobile-drawer-footer">
                <div class="d-flex align-items-center justify-content-between mb-3 px-2">
                    <span class="small text-muted fw-semibold">Interactive Sound Effects:</span>
                    <button class="btn btn-sm btn-light border rounded-pill px-3 py-1 sound-btn d-inline-flex align-items-center gap-2" type="button">
                        <i class="bi bi-volume-up-fill text-primary"></i> <span class="small fw-bold">Toggle Sound</span>
                    </button>
                </div>
                <?php if (!$isLoggedIn): ?>
                    <div class="p-3 bg-light rounded-4 text-center border">
                        <p class="small text-muted mb-2 fw-medium">Are you an educator or university lecturer?</p>
                        <a href="<?= url('auth/teacher-course-select.php') ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-bold w-100">
                            <i class="bi bi-person-workspace me-1"></i> Apply as Course Instructor &rarr;
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<div class="navbar-mobile-backdrop" id="navbarMobileBackdrop"></div>