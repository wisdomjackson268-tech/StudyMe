<?php
/**
 * StudyMe AI Platform — Role-Based Dashboard Sidebar
 *
 * Rendered inside every Student / Teacher / Admin page via
 * includes/layouts/dashboard-header.php.
 *
 * Provides:
 *  - Brand logo
 *  - Role-specific navigation with active-state detection
 *  - User profile snippet
 *  - Prominent logout button at the bottom (visible on desktop & mobile)
 */

$currentRole   = current_user_role();
$currentScript = basename($_SERVER['PHP_SELF']);
$user          = current_user();
$displayName   = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
$roleBadge     = ucfirst($currentRole ?? 'User');
?>
<aside class="dashboard-sidebar" id="dashboardSidebar" aria-label="Main navigation">

    <!-- ── Brand ──────────────────────────────────────────── -->
    <div class="sidebar-brand d-flex align-items-center justify-content-between">
        <a href="<?= url('index.php') ?>" class="d-flex align-items-center gap-2 text-decoration-none">
            <div class="sidebar-logo-icon">
                <i class="bi bi-mortarboard-fill"></i>
            </div>
            <span class="logo-text">StudyMe</span>
        </a>
        <button type="button" class="btn btn-sm btn-outline-secondary d-lg-none rounded-circle p-1 sidebar-close-btn" id="sidebarCloseBtn" aria-label="Close Sidebar" style="width:32px; height:32px; display:inline-flex; align-items:center; justify-content:center;">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>

    <!-- ── Navigation ─────────────────────────────────────── -->
    <nav class="sidebar-nav" id="sidebarNav">

        <?php if ($currentRole === ROLE_ADMIN): ?>
            <!-- ADMIN NAVIGATION -->
            <div class="sidebar-heading">Management</div>
            <a href="<?= url('admin/dashboard.php') ?>" class="sidebar-link <?= $currentScript === 'dashboard.php' ? 'active' : '' ?>">
                <i class="bi bi-speedometer2"></i><span>Command Center</span>
            </a>
            <a href="<?= url('admin/activity.php') ?>" class="sidebar-link <?= $currentScript === 'activity.php' ? 'active' : '' ?>">
                <i class="bi bi-activity text-primary"></i><span>Activity Telemetry</span>
            </a>
            <a href="<?= url('admin/users.php') ?>" class="sidebar-link <?= $currentScript === 'users.php' ? 'active' : '' ?>">
                <i class="bi bi-people-fill"></i><span>Users &amp; Roles</span>
            </a>
            <a href="<?= url('admin/teacher-applications.php') ?>" class="sidebar-link <?= $currentScript === 'teacher-applications.php' ? 'active' : '' ?>">
                <i class="bi bi-patch-check-fill"></i><span>Teacher Applications</span>
            </a>
            <a href="<?= url('admin/courses.php') ?>" class="sidebar-link <?= in_array($currentScript, ['courses.php', 'edit-course.php', 'course-details.php']) ? 'active' : '' ?>">
                <i class="bi bi-collection-play-fill"></i><span>Course Moderation</span>
            </a>
            <a href="<?= url('admin/lessons.php') ?>" class="sidebar-link <?= $currentScript === 'lessons.php' ? 'active' : '' ?>">
                <i class="bi bi-play-btn-fill"></i><span>Lessons Directory</span>
            </a>
            <a href="<?= url('admin/quizzes.php') ?>" class="sidebar-link <?= in_array($currentScript, ['quizzes.php', 'questions.php', 'quiz-results.php']) ? 'active' : '' ?>">
                <i class="bi bi-patch-question-fill"></i><span>Quizzes &amp; Tests</span>
            </a>
            <a href="<?= url('admin/enrollments.php') ?>" class="sidebar-link <?= $currentScript === 'enrollments.php' ? 'active' : '' ?>">
                <i class="bi bi-person-check-fill"></i><span>Enrollments</span>
            </a>
            <a href="<?= url('admin/announcements.php') ?>" class="sidebar-link <?= $currentScript === 'announcements.php' ? 'active' : '' ?>">
                <i class="bi bi-megaphone-fill text-warning"></i><span>Announcements</span>
            </a>
            <a href="<?= url('admin/pricing.php') ?>" class="sidebar-link <?= $currentScript === 'pricing.php' ? 'active' : '' ?>">
                <i class="bi bi-cash-stack text-success"></i><span>Course Pricing</span>
            </a>
            <a href="<?= url('admin/categories.php') ?>" class="sidebar-link <?= $currentScript === 'categories.php' ? 'active' : '' ?>">
                <i class="bi bi-tag-fill"></i><span>Categories</span>
            </a>
            <a href="<?= url('admin/reports.php') ?>" class="sidebar-link <?= $currentScript === 'reports.php' ? 'active' : '' ?>">
                <i class="bi bi-file-earmark-bar-graph-fill text-info"></i><span>Platform Reports</span>
            </a>

            <div class="sidebar-heading mt-3">Finance &amp; AI</div>
            <a href="<?= url('admin/subscriptions.php') ?>" class="sidebar-link <?= $currentScript === 'subscriptions.php' ? 'active' : '' ?>">
                <i class="bi bi-award-fill"></i><span>Subscriptions</span>
            </a>
            <a href="<?= url('admin/referrals.php') ?>" class="sidebar-link <?= $currentScript === 'referrals.php' ? 'active' : '' ?>">
                <i class="bi bi-diagram-3-fill text-warning"></i><span>Referrals &amp; Bonuses</span>
            </a>
            <a href="<?= url('admin/payments.php') ?>" class="sidebar-link <?= $currentScript === 'payments.php' ? 'active' : '' ?>">
                <i class="bi bi-credit-card-fill"></i><span>Financial Ledger</span>
            </a>
            <a href="<?= url('admin/analytics.php') ?>" class="sidebar-link <?= $currentScript === 'analytics.php' ? 'active' : '' ?>">
                <i class="bi bi-graph-up-arrow"></i><span>Analytics</span>
            </a>
            <a href="<?= url('admin/ai-settings.php') ?>" class="sidebar-link <?= $currentScript === 'ai-settings.php' ? 'active' : '' ?>">
                <i class="bi bi-robot"></i><span>AI Engine</span>
            </a>
            <a href="<?= url('admin/notifications.php') ?>" class="sidebar-link <?= $currentScript === 'notifications.php' ? 'active' : '' ?>">
                <i class="bi bi-bell-fill"></i><span>Notifications</span>
            </a>
            <a href="<?= url('admin/profile.php') ?>" class="sidebar-link <?= $currentScript === 'profile.php' ? 'active' : '' ?>">
                <i class="bi bi-person-circle"></i><span>Profile</span>
            </a>
            <a href="<?= url('admin/system-settings.php') ?>" class="sidebar-link <?= $currentScript === 'system-settings.php' ? 'active' : '' ?>">
                <i class="bi bi-sliders"></i><span>System Settings</span>
            </a>

        <?php elseif ($currentRole === ROLE_TEACHER): ?>
            <!-- TEACHER NAVIGATION -->
            <div class="sidebar-heading">Instructor Suite</div>
            <a href="<?= url('teacher/dashboard.php') ?>" class="sidebar-link <?= $currentScript === 'dashboard.php' ? 'active' : '' ?>">
                <i class="bi bi-speedometer2"></i><span>Overview</span>
            </a>
            <a href="<?= url('teacher/activity.php') ?>" class="sidebar-link <?= $currentScript === 'activity.php' ? 'active' : '' ?>">
                <i class="bi bi-activity text-primary"></i><span>Daily Activity</span>
            </a>
            <a href="<?= url('teacher/courses.php') ?>" class="sidebar-link <?= in_array($currentScript, ['courses.php','create-course.php','edit-course.php']) ? 'active' : '' ?>">
                <i class="bi bi-journal-code"></i><span>Course Manager</span>
            </a>
            <a href="<?= url('teacher/lessons.php') ?>" class="sidebar-link <?= in_array($currentScript, ['lessons.php','create-lesson.php','edit-lesson.php']) ? 'active' : '' ?>">
                <i class="bi bi-play-btn-fill"></i><span>Lessons</span>
            </a>
            <a href="<?= url('teacher/quizzes.php') ?>" class="sidebar-link <?= in_array($currentScript, ['quizzes.php','create-quiz.php','edit-quiz.php']) ? 'active' : '' ?>">
                <i class="bi bi-patch-question-fill"></i><span>Quizzes &amp; Tests</span>
            </a>
            <a href="<?= url('teacher/students.php') ?>" class="sidebar-link <?= $currentScript === 'students.php' ? 'active' : '' ?>">
                <i class="bi bi-people-fill"></i><span>Enrolled Students</span>
            </a>
            <a href="<?= url('teacher/announcements.php') ?>" class="sidebar-link <?= in_array($currentScript, ['announcements.php','create-announcement.php','edit-announcement.php']) ? 'active' : '' ?>">
                <i class="bi bi-megaphone-fill text-warning"></i><span>My Announcements</span>
            </a>
            <a href="<?= url('teacher/discussions.php') ?>" class="sidebar-link <?= $currentScript === 'discussions.php' ? 'active' : '' ?>">
                <i class="bi bi-chat-quote-fill text-info"></i><span>Student Q&amp;A Hub</span>
            </a>

            <div class="sidebar-heading mt-3">Performance &amp; Growth</div>
            <a href="<?= url('teacher/referrals.php') ?>" class="sidebar-link <?= $currentScript === 'referrals.php' ? 'active' : '' ?>">
                <i class="bi bi-gift-fill text-warning"></i><span>Refer &amp; Earn (₦1,500)</span>
            </a>
            <a href="<?= url('teacher/earnings.php') ?>" class="sidebar-link <?= $currentScript === 'earnings.php' ? 'active' : '' ?>">
                <i class="bi bi-wallet-fill text-success"></i><span>Earnings &amp; Payouts</span>
            </a>
            <a href="<?= url('teacher/analytics.php') ?>" class="sidebar-link <?= $currentScript === 'analytics.php' ? 'active' : '' ?>">
                <i class="bi bi-graph-up-arrow"></i><span>Analytics</span>
            </a>
            <a href="<?= url('teacher/notifications.php') ?>" class="sidebar-link <?= $currentScript === 'notifications.php' ? 'active' : '' ?>">
                <i class="bi bi-bell-fill"></i><span>Notifications</span>
            </a>
            <a href="<?= url('teacher/profile.php') ?>" class="sidebar-link <?= $currentScript === 'profile.php' ? 'active' : '' ?>">
                <i class="bi bi-person-badge-fill"></i><span>Instructor Profile</span>
            </a>
            <a href="<?= url('teacher/settings.php') ?>" class="sidebar-link <?= $currentScript === 'settings.php' ? 'active' : '' ?>">
                <i class="bi bi-gear-fill"></i><span>Settings</span>
            </a>

        <?php else: ?>
            <!-- STUDENT NAVIGATION -->
            <div class="sidebar-heading">Learning Journey</div>
            <a href="<?= url('student/dashboard.php') ?>" class="sidebar-link <?= $currentScript === 'dashboard.php' ? 'active' : '' ?>">
                <i class="bi bi-speedometer2"></i><span>My Dashboard</span>
            </a>
            <a href="<?= url('student/activity.php') ?>" class="sidebar-link <?= $currentScript === 'activity.php' ? 'active' : '' ?>">
                <i class="bi bi-activity text-primary"></i><span>Daily Activity</span>
                <span class="badge bg-warning text-dark ms-auto" style="font-size: 0.65rem; padding: 2px 7px; border-radius: 50px;">TRACK</span>
            </a>
            <a href="<?= url('student/my-courses.php') ?>" class="sidebar-link <?= in_array($currentScript, ['my-courses.php','course.php','lesson.php']) ? 'active' : '' ?>">
                <i class="bi bi-play-circle-fill"></i><span>My Course</span>
            </a>
            <a href="<?= url('courses/index.php') ?>" class="sidebar-link <?= $currentScript === 'index.php' ? 'active' : '' ?>">
                <i class="bi bi-compass-fill"></i><span>Discover Courses</span>
            </a>
            <a href="<?= url('student/quizzes.php') ?>" class="sidebar-link <?= in_array($currentScript, ['quizzes.php','quiz.php','results.php']) ? 'active' : '' ?>">
                <i class="bi bi-patch-check-fill"></i><span>Quizzes &amp; Practice</span>
            </a>
            <a href="<?= url('student/ai-assistant.php') ?>" class="sidebar-link <?= $currentScript === 'ai-assistant.php' ? 'active' : '' ?>">
                <i class="bi bi-robot" style="color: #F59E0B;"></i><span>AI Tutor</span>
                <span class="badge bg-warning text-dark ms-auto" style="font-size: 0.65rem; padding: 2px 7px; border-radius: 50px;">NEW</span>
            </a>
            <a href="<?= url('student/progress.php') ?>" class="sidebar-link <?= $currentScript === 'progress.php' ? 'active' : '' ?>">
                <i class="bi bi-graph-up-arrow"></i><span>Progress</span>
            </a>
            <a href="<?= url('student/certificates.php') ?>" class="sidebar-link <?= $currentScript === 'certificates.php' ? 'active' : '' ?>">
                <i class="bi bi-award-fill" style="color: #F59E0B;"></i><span>Certificates</span>
            </a>
            <a href="<?= url('student/referrals.php') ?>" class="sidebar-link <?= $currentScript === 'referrals.php' ? 'active' : '' ?>">
                <i class="bi bi-gift-fill text-success"></i><span>Referrals &amp; Rewards</span>
                <span class="badge bg-success ms-auto" style="font-size: 0.65rem; padding: 2px 7px; border-radius: 50px;">EARN</span>
            </a>

            <div class="sidebar-heading mt-3">Account &amp; Notices</div>
            <a href="<?= url('announcements/index.php') ?>" class="sidebar-link <?= $currentScript === 'index.php' && strpos($_SERVER['REQUEST_URI'] ?? '', 'announcements') !== false ? 'active' : '' ?>">
                <i class="bi bi-megaphone-fill text-warning"></i><span>Announcements</span>
            </a>
            <a href="<?= url('student/notifications.php') ?>" class="sidebar-link <?= $currentScript === 'notifications.php' ? 'active' : '' ?>">
                <i class="bi bi-bell-fill"></i><span>Notifications</span>
            </a>
            <a href="<?= url('payments/history.php') ?>" class="sidebar-link <?= $currentScript === 'history.php' ? 'active' : '' ?>">
                <i class="bi bi-credit-card-2-front-fill"></i><span>Subscription</span>
            </a>
            <a href="<?= url('student/profile.php') ?>" class="sidebar-link <?= $currentScript === 'profile.php' ? 'active' : '' ?>">
                <i class="bi bi-person-circle"></i><span>Profile</span>
            </a>
            <a href="<?= url('student/settings.php') ?>" class="sidebar-link <?= $currentScript === 'settings.php' ? 'active' : '' ?>">
                <i class="bi bi-gear-fill"></i><span>Settings</span>
            </a>
        <?php endif; ?>

    </nav><!-- /#sidebarNav -->

    <!-- ── User Profile Pill (desktop) ────────────────────── -->
    <?php 
    $sidebarAvatarUrl = function_exists('get_avatar_url') ? get_avatar_url($user['avatar'] ?? null, $user['first_name'] ?? 'User') : ($user['avatar'] ?? '');
    $profileLink = url(($currentRole === ROLE_ADMIN ? 'admin' : ($currentRole === ROLE_TEACHER ? 'teacher' : 'student')) . '/profile.php');
    ?>
    <a href="<?= $profileLink ?>" class="sidebar-user text-decoration-none" title="Click to view &amp; update profile photo">
        <div class="sidebar-user-avatar overflow-hidden">
            <img src="<?= e($sidebarAvatarUrl) ?>" alt="Avatar" style="width: 100%; height: 100%; object-fit: cover;" onerror="this.onerror=null; this.src='https://ui-avatars.com/api/?name=<?= urlencode($user['first_name'] ?? 'User') ?>&background=4f46e5&color=ffffff&bold=true';">
        </div>
        <div class="sidebar-user-info">
            <div class="sidebar-user-name"><?= htmlspecialchars($displayName ?: 'User', ENT_QUOTES, 'UTF-8') ?></div>
            <div class="sidebar-user-role"><?= htmlspecialchars($roleBadge, ENT_QUOTES, 'UTF-8') ?> &bull; <span class="text-primary fw-bold" style="font-size: 0.72rem;">Edit</span></div>
        </div>
    </a>

    <!-- ── Logout Button ──────────────────────────────────── -->
    <div class="sidebar-logout">
        <a href="<?= url('auth/logout.php') ?>"
           class="sidebar-logout-btn"
           id="sidebarLogoutBtn"
           data-feedback="error"
           aria-label="Log out of StudyMe"
           onclick="return confirmLogout();">
            <i class="bi bi-box-arrow-right"></i>
            <span>Logout</span>
        </a>
    </div>

</aside><!-- /#dashboardSidebar -->

<script>
function confirmLogout() {
    // Play logout sound if feedback system available
    if (typeof StudyMeFeedback !== 'undefined') {
        StudyMeFeedback.error();
    }
    return true; // allow navigation to proceed
}
</script>
