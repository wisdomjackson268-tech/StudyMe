<?php
/**
 * StudyMe AI Platform — Universal Responsive Public Navbar
 */
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
    <div class="container">
        <!-- Brand Logo -->
        <a class="navbar-brand logo d-flex align-items-center gap-2" href="<?= url('index.php') ?>" aria-label="StudyMe Home">
            <div class="logo-icon-wrapper">
                <i class="bi bi-mortarboard-fill"></i>
            </div>
            <span class="logo-text">StudyMe</span>
        </a>

        <!-- Mobile Navbar Toggler (Modern Animated Hamburger) -->
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

        <!-- Navbar Menu & Actions -->
        <div class="collapse navbar-collapse" id="navbarMenu">
            <ul class="navbar-nav mx-auto mb-2 mb-lg-0 nav-menu-list">
                <li class="nav-item">
                    <a class="nav-link <?= is_nav_active('index', $currentUri) ?>" href="<?= url('index.php') ?>">
                        <i class="bi bi-house-door-fill d-lg-none me-2 text-primary"></i> Home
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= is_nav_active('courses', $currentUri) ?>" href="<?= url('courses/index.php') ?>">
                        <i class="bi bi-collection-play-fill d-lg-none me-2 text-primary"></i> Courses
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= is_nav_active('ai-learning', $currentUri) ?>" href="<?= url('ai-learning.php') ?>">
                        <i class="bi bi-stars d-lg-none me-2 text-warning"></i> AI Learning
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= is_nav_active('teachers', $currentUri) ?>" href="<?= url('teachers.php') ?>">
                        <i class="bi bi-person-badge-fill d-lg-none me-2 text-info"></i> Teachers
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= is_nav_active('pricing', $currentUri) ?>" href="<?= url('pricing.php') ?>">
                        <i class="bi bi-tags-fill d-lg-none me-2 text-success"></i> Pricing
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= is_nav_active('about', $currentUri) ?>" href="<?= url('about.php') ?>">
                        <i class="bi bi-info-circle-fill d-lg-none me-2 text-secondary"></i> About
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= is_nav_active('contact', $currentUri) ?>" href="<?= url('contact.php') ?>">
                        <i class="bi bi-envelope-fill d-lg-none me-2 text-warning"></i> Contact
                    </a>
                </li>
            </ul>

            <!-- Navbar Right Actions -->
            <div class="navbar-actions d-flex align-items-center gap-2">
                <!-- Theme Switcher -->
                <button class="btn theme-btn" type="button" aria-label="Toggle Theme" data-feedback="click" title="Switch Theme (Dark / Light)">
                    <i class="bi bi-moon-stars-fill"></i>
                </button>

                <!-- Sound Controller -->
                <button class="btn theme-btn sound-btn" type="button" aria-label="Toggle Sound" data-feedback="none" title="Toggle Sound Feedback">
                    <i class="bi bi-volume-up-fill"></i>
                </button>

                <?php if ($isLoggedIn): ?>
                    <a href="<?= url($dashUrl) ?>" class="btn btn-primary rounded-pill px-4 fw-bold nav-cta-btn" data-feedback="click">
                        <i class="bi bi-grid-fill me-1"></i> Dashboard
                    </a>
                <?php else: ?>
                    <a href="<?= url('auth/teacher-course-select.php') ?>" class="btn btn-outline-warning btn-sm rounded-pill fw-bold text-nowrap d-none d-xl-inline-flex nav-teacher-cta">
                        <i class="bi bi-person-workspace me-1"></i> Become a Teacher
                    </a>
                    <a href="<?= url('auth/login.php') ?>" class="btn btn-login" data-feedback="click">
                        <i class="bi bi-box-arrow-in-right d-lg-none me-1"></i> Login
                    </a>
                    <a href="<?= url('auth/register.php') ?>" class="btn btn-start" data-feedback="success">
                        <span>Get Started</span> <i class="bi bi-arrow-right-short ms-1"></i>
                    </a>
                <?php endif; ?>
            </div>
            
            <!-- Mobile Secondary Navigation Row (Teachers Link & Help) -->
            <?php if (!$isLoggedIn): ?>
                <div class="d-lg-none pt-3 mt-3 border-top border-secondary border-opacity-10 text-center">
                    <a href="<?= url('auth/teacher-course-select.php') ?>" class="text-warning text-decoration-none small fw-bold d-inline-flex align-items-center gap-1">
                        <i class="bi bi-mortarboard me-1"></i> Want to teach on StudyMe? Apply as Instructor &rarr;
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</nav>

<!-- Backdrop overlay for mobile menu -->
<div class="navbar-mobile-backdrop" id="navbarMobileBackdrop"></div>