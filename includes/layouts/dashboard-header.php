<?php

require_once dirname(__DIR__, 2) . '/config/main.php';

$user = current_user();
$role = current_user_role();

$subscription = null;
if ($role !== ROLE_STUDENT) {
    $subscription = get_user_subscription($user['id'] ?? 0);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow, noarchive, nosnippet">
    <meta name="googlebot" content="noindex, nofollow">
    <title><?= defined('APP_NAME') ? APP_NAME : 'StudyMe' ?> &mdash; AI-Powered Portal</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">

    <link rel="stylesheet" href="<?= asset('css/variables.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/light.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/dark.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/skeleton.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/style.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/pages/dashboard.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/pages/animations.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/responsive.css') ?>">

    <script>
        (function() {
            var saved = localStorage.getItem('studyme_theme') || localStorage.getItem('theme');
            var isDark = saved === 'dark' || (!saved && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches);
            if (isDark) {
                document.documentElement.setAttribute('data-bs-theme', 'dark');
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.setAttribute('data-bs-theme', 'light');
                document.documentElement.classList.remove('dark');
            }
        })();
    </script>
</head>
<body class="dashboard-body light">
<?php include BASE_PATH . '/includes/components/loader.php'; ?>

<div class="dashboard-wrapper">
    <?php include BASE_PATH . '/includes/layouts/dashboard-sidebar.php'; ?>
    <div class="sidebar-backdrop d-lg-none" id="sidebarBackdrop"></div>

    <div class="dashboard-main">
        <header class="dashboard-topbar">
            <div class="d-flex align-items-center gap-2 gap-sm-3 flex-grow-1 me-2" style="min-width: 0; max-width: 480px;">
                <button class="btn dashboard-sidebar-toggle d-lg-none flex-shrink-0" type="button" id="mobileSidebarToggle" aria-label="Toggle Navigation Sidebar" data-feedback="click">
                    <span class="hamburger-box">
                        <span class="hamburger-bar top-bar"></span>
                        <span class="hamburger-bar mid-bar"></span>
                        <span class="hamburger-bar bot-bar"></span>
                    </span>
                </button>
                <div class="topbar-search d-none d-md-block flex-grow-1">
                    <i class="bi bi-search"></i>
                    <input type="text" class="form-control" placeholder="Search courses, lessons, or topics...">
                </div>
            </div>

            <div class="topbar-actions flex-shrink-0">
                <button class="topbar-btn theme-btn" type="button" aria-label="Toggle Theme" data-feedback="click">
                    <i class="bi bi-moon-stars-fill"></i>
                </button>

                <button class="topbar-btn sound-btn" type="button" aria-label="Toggle Sound" data-feedback="none">
                    <i class="bi bi-volume-up-fill"></i>
                </button>

                <?php
                $headerUid = (int)($user['id'] ?? 0);
                $unreadNotifsCount = function_exists('count_unread_notifications') ? count_unread_notifications($headerUid) : 0;
                $unreadAnnsCount   = function_exists('count_unread_announcements') ? count_unread_announcements($headerUid, $role) : 0;
                $totalUnreadHeader = $unreadNotifsCount + $unreadAnnsCount;
                ?>
                <div class="dropdown">
                    <button class="topbar-btn position-relative" type="button" data-bs-toggle="dropdown" aria-expanded="false" data-feedback="click" aria-label="Notifications">
                        <i class="bi bi-bell-fill"></i>
                        <?php if ($totalUnreadHeader > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:0.65rem; padding:3px 6px;">
                                <?= $totalUnreadHeader > 99 ? '99+' : $totalUnreadHeader ?>
                            </span>
                        <?php endif; ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end p-3 shadow-lg border-0 rounded-4" style="width: 320px;">
                        <li class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="fw-bold mb-0">Notifications &amp; Alerts</h6>
                            <span class="badge bg-primary rounded-pill"><?= $totalUnreadHeader ?> New</span>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li class="mb-2">
                            <a class="dropdown-item p-2 rounded-3 text-wrap d-flex align-items-center justify-content-between" href="<?= url('announcements/index.php') ?>">
                                <div>
                                    <div class="fw-bold small text-primary"><i class="bi bi-megaphone-fill me-1 text-warning"></i> Announcements</div>
                                    <div class="text-muted small">Course updates and official broadcasts</div>
                                </div>
                                <?php if ($unreadAnnsCount > 0): ?>
                                    <span class="badge bg-warning text-dark rounded-pill"><?= $unreadAnnsCount ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li class="mb-2">
                            <a class="dropdown-item p-2 rounded-3 text-wrap d-flex align-items-center justify-content-between" href="<?= url($role === 'admin' ? 'admin/notifications.php' : ($role === 'teacher' ? 'teacher/notifications.php' : 'student/notifications.php')) ?>">
                                <div>
                                    <div class="fw-bold small text-dark"><i class="bi bi-inbox-fill me-1 text-primary"></i> Personal Inbox</div>
                                    <div class="text-muted small">Milestones, activity &amp; receipts</div>
                                </div>
                                <?php if ($unreadNotifsCount > 0): ?>
                                    <span class="badge bg-primary rounded-pill"><?= $unreadNotifsCount ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li class="text-center">
                            <a class="small text-decoration-none fw-bold" href="<?= url('announcements/index.php') ?>">View All Announcements &rarr;</a>
                        </li>
                    </ul>
                </div>

                <div class="dropdown">
                    <a href="javascript:void(0)" class="d-flex align-items-center gap-2 text-decoration-none text-main dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                        <?php $headerAvatarUrl = function_exists('get_avatar_url') ? get_avatar_url($user['avatar'] ?? null, $user['first_name'] ?? 'User') : ($user['avatar'] ?? ''); ?>
                        <div class="position-relative d-inline-block flex-shrink-0">
                            <img src="<?= e($headerAvatarUrl) ?>" alt="Avatar" class="topbar-avatar border" style="object-fit: cover; width:36px; height:36px; border-radius:50%;" onerror="this.onerror=null; this.src='https://ui-avatars.com/api/?name=<?= urlencode($user['first_name'] ?? 'User') ?>&background=4f46e5&color=ffffff&bold=true';">
                            <span class="status-pulse-dot position-absolute bottom-0 end-0 bg-success border border-2 border-white rounded-circle" style="width: 10px; height: 10px; z-index: 5;" title="Active Now"></span>
                        </div>
                        <div class="d-none d-sm-block text-start" style="max-width: 140px;">
                            <div class="fw-bold small text-truncate"><?= e($user['first_name'] ?? 'User') ?> <?= e($user['last_name'] ?? '') ?></div>
                            <div class="text-muted text-truncate" style="font-size: 0.75rem; text-transform: capitalize;"><?= e($role ?? 'Student') ?></div>
                        </div>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 rounded-4 p-2 mt-2">
                        <li>
                            <a class="dropdown-item rounded-3 py-2" href="<?= url(($role === 'admin' ? 'admin' : ($role === 'teacher' ? 'teacher' : 'student')) . '/profile.php') ?>">
                                <i class="bi bi-person-circle me-2"></i> My Profile
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item rounded-3 py-2" href="<?= url('payments/history.php') ?>">
                                <i class="bi bi-credit-card me-2"></i> Billing &amp; Subscription
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item rounded-3 py-2" href="<?= url(($role === 'admin' ? 'admin' : ($role === 'teacher' ? 'teacher' : 'student')) . '/settings.php') ?>">
                                <i class="bi bi-gear me-2"></i> Settings
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item rounded-3 py-2 fw-semibold text-danger"
                               href="<?= url('auth/logout.php') ?>"
                               data-feedback="error"
                               aria-label="Log out of StudyMe"
                               onclick="if(typeof StudyMeFeedback!=='undefined'){StudyMeFeedback.error();}return true;">
                                <i class="bi bi-box-arrow-right me-2"></i> Sign Out
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </header>

        <?php 
        $flash = get_flash();
        if (!empty($flash)): 
        ?>
        <div class="px-4 pt-3">
            <?php foreach ($flash as $type => $messages): ?>
                <?php foreach ($messages as $msg): ?>
                    <div class="alert alert-<?= $type === 'error' ? 'danger' : ($type === 'warning' ? 'warning' : 'success') ?> alert-dismissible fade show rounded-3 shadow-sm" role="alert">
                        <i class="bi bi-<?= $type === 'error' ? 'exclamation-octagon' : ($type === 'warning' ? 'exclamation-triangle' : 'check-circle') ?>-fill me-2"></i>
                        <?= e($msg) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <main class="dashboard-content">
