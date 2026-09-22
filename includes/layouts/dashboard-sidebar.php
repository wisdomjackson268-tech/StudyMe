<?php

$currentRole   = current_user_role();
$currentScript = basename($_SERVER['PHP_SELF']);
$currentUri    = $_SERVER['REQUEST_URI'] ?? '';
$user          = current_user();
$isSecondaryStudent = $currentRole === ROLE_STUDENT && function_exists('is_secondary_student') && is_secondary_student((int)($user['id'] ?? 0));
$displayName   = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
$roleBadge     = ucfirst($currentRole ?? 'User');
?>
<aside class="dashboard-sidebar" id="dashboardSidebar" aria-label="Main navigation">
    <div class="sidebar-brand d-flex align-items-center justify-content-between">
        <a href="<?= url('index.php') ?>" class="d-flex align-items-center gap-2 text-decoration-none">
            <div class="sidebar-logo-icon">
                <i class="bi bi-mortarboard-fill"></i>
            </div>
            <span class="logo-text">StudyMe</span>
        </a>
        <button type="button" class="btn btn-sm btn-outline-secondary d-lg-none rounded-circle p-1 sidebar-close-btn" id="sidebarCloseBtn" aria-label="Close Sidebar" style="width:34px; height:34px; display:inline-flex; align-items:center; justify-content:center;">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>

    <nav class="sidebar-nav" id="sidebarNav">
        <?php if ($currentRole === ROLE_ADMIN): ?>
            <div class="sidebar-heading">Management &amp; Telemetry</div>
            <a href="<?= url('admin/dashboard.php') ?>" class="sidebar-link <?= $currentScript === 'dashboard.php' ? 'active' : '' ?>">
                <i class="bi bi-speedometer2"></i><span>Command Center</span>
            </a>
            <a href="<?= url('admin/activity.php') ?>" class="sidebar-link <?= $currentScript === 'activity.php' ? 'active' : '' ?>">
                <i class="bi bi-activity text-primary"></i><span>Activity Analysis</span>
            </a>
            <a href="<?= url('admin/users.php') ?>" class="sidebar-link <?= $currentScript === 'users.php' ? 'active' : '' ?>">
                <i class="bi bi-people-fill"></i><span>Users &amp; Roles</span>
            </a>
            <a href="<?= url('admin/teacher-applications.php') ?>" class="sidebar-link <?= $currentScript === 'teacher-applications.php' ? 'active' : '' ?>">
                <i class="bi bi-patch-check-fill text-warning"></i><span>Teacher Status &amp; Apps</span>
            </a>
            <a href="<?= url('admin/courses.php') ?>" class="sidebar-link <?= in_array($currentScript, ['courses.php', 'edit-course.php', 'course-details.php', 'create-course.php']) ? 'active' : '' ?>">
                <i class="bi bi-collection-play-fill"></i><span>Course Moderation</span>
            </a>
            <a href="<?= url('admin/lessons.php') ?>" class="sidebar-link <?= $currentScript === 'lessons.php' ? 'active' : '' ?>">
                <i class="bi bi-play-btn-fill"></i><span>Available Lessons</span>
            </a>
            <a href="<?= url('admin/quizzes.php') ?>" class="sidebar-link <?= in_array($currentScript, ['quizzes.php', 'questions.php', 'quiz-results.php']) ? 'active' : '' ?>">
                <i class="bi bi-patch-question-fill"></i><span>Quizzes &amp; CBT Tests</span>
            </a>
            <a href="<?= url('admin/enrollments.php') ?>" class="sidebar-link <?= $currentScript === 'enrollments.php' ? 'active' : '' ?>">
                <i class="bi bi-person-check-fill"></i><span>Student Enrollments</span>
            </a>
            <a href="<?= url('admin/announcements.php') ?>" class="sidebar-link <?= $currentScript === 'announcements.php' ? 'active' : '' ?>">
                <i class="bi bi-megaphone-fill text-warning"></i><span>Announcements</span>
            </a>
            <a href="<?= url('admin/pricing.php') ?>" class="sidebar-link <?= $currentScript === 'pricing.php' ? 'active' : '' ?>">
                <i class="bi bi-cash-stack text-success"></i><span>Course Pricing</span>
            </a>
            <a href="<?= url('admin/categories.php') ?>" class="sidebar-link <?= $currentScript === 'categories.php' ? 'active' : '' ?>">
                <i class="bi bi-tag-fill"></i><span>Categories &amp; Tracks</span>
            </a>
            <a href="<?= url('admin/reports.php') ?>" class="sidebar-link <?= $currentScript === 'reports.php' ? 'active' : '' ?>">
                <i class="bi bi-file-earmark-bar-graph-fill text-info"></i><span>Platform Reports</span>
            </a>

            <div class="sidebar-heading mt-3">Secondary School Suite</div>
            <a href="<?= url('admin/secondary-subjects.php') ?>" class="sidebar-link <?= $currentScript === 'secondary-subjects.php' ? 'active' : '' ?>">
                <i class="bi bi-book-half text-success"></i><span>Secondary Subjects</span>
            </a>
            <a href="<?= url('admin/secondary-questions.php') ?>" class="sidebar-link <?= $currentScript === 'secondary-questions.php' ? 'active' : '' ?>">
                <i class="bi bi-patch-question-fill text-warning"></i><span>WAEC/NECO/JAMB Bank</span>
            </a>
            <a href="<?= url('admin/secondary-materials.php') ?>" class="sidebar-link <?= $currentScript === 'secondary-materials.php' ? 'active' : '' ?>">
                <i class="bi bi-journal-text text-info"></i><span>Study Materials</span>
            </a>

            <div class="sidebar-heading mt-3">Finance, AI &amp; Settings</div>
            <a href="<?= url('admin/payments.php') ?>" class="sidebar-link <?= $currentScript === 'payments.php' ? 'active' : '' ?>">
                <i class="bi bi-credit-card-fill text-success"></i><span>Financial Ledger</span>
            </a>
            <a href="<?= url('admin/subscriptions.php') ?>" class="sidebar-link <?= $currentScript === 'subscriptions.php' ? 'active' : '' ?>">
                <i class="bi bi-award-fill"></i><span>Subscriptions</span>
            </a>
            <a href="<?= url('admin/referrals.php') ?>" class="sidebar-link <?= $currentScript === 'referrals.php' ? 'active' : '' ?>">
                <i class="bi bi-diagram-3-fill text-warning"></i><span>Referrals &amp; Bonuses</span>
            </a>
            <a href="<?= url('admin/analytics.php') ?>" class="sidebar-link <?= $currentScript === 'analytics.php' ? 'active' : '' ?>">
                <i class="bi bi-graph-up-arrow"></i><span>Platform Analytics</span>
            </a>
            <a href="<?= url('admin/ai-settings.php') ?>" class="sidebar-link <?= $currentScript === 'ai-settings.php' ? 'active' : '' ?>">
                <i class="bi bi-robot text-warning"></i><span>AI Engine Settings</span>
            </a>
            <a href="<?= url('admin/notifications.php') ?>" class="sidebar-link <?= $currentScript === 'notifications.php' ? 'active' : '' ?>">
                <i class="bi bi-bell-fill"></i><span>Notifications</span>
            </a>
            <a href="<?= url('admin/profile.php') ?>" class="sidebar-link <?= $currentScript === 'profile.php' ? 'active' : '' ?>">
                <i class="bi bi-person-circle"></i><span>Admin Profile</span>
            </a>
            <a href="<?= url('admin/system-settings.php') ?>" class="sidebar-link <?= $currentScript === 'system-settings.php' ? 'active' : '' ?>">
                <i class="bi bi-sliders"></i><span>System Settings</span>
            </a>

        <?php elseif ($currentRole === ROLE_TEACHER): ?>
            <div class="sidebar-heading">Instructor Suite</div>
            <a href="<?= url('teacher/dashboard.php') ?>" class="sidebar-link <?= $currentScript === 'dashboard.php' ? 'active' : '' ?>">
                <i class="bi bi-speedometer2"></i><span>Overview Dashboard</span>
            </a>
            <a href="<?= url('teacher/activity.php') ?>" class="sidebar-link <?= $currentScript === 'activity.php' ? 'active' : '' ?>">
                <i class="bi bi-activity text-primary"></i><span>Daily Activity Analysis</span>
            </a>
            <a href="<?= url('teacher/courses.php') ?>" class="sidebar-link <?= in_array($currentScript, ['courses.php','create-course.php','edit-course.php']) ? 'active' : '' ?>">
                <i class="bi bi-journal-code"></i><span>Course Manager</span>
            </a>
            <a href="<?= url('teacher/lessons.php') ?>" class="sidebar-link <?= in_array($currentScript, ['lessons.php','create-lesson.php','edit-lesson.php']) ? 'active' : '' ?>">
                <i class="bi bi-play-btn-fill"></i><span>Available Lessons</span>
            </a>

            <a href="<?= url('teacher/quizzes.php') ?>" class="sidebar-link <?= in_array($currentScript, ['quizzes.php','create-quiz.php','edit-quiz.php']) ? 'active' : '' ?>">
                <i class="bi bi-patch-question-fill"></i><span>Available Quizzes &amp; Tests</span>
            </a>
            <a href="<?= url('teacher/students.php') ?>" class="sidebar-link <?= $currentScript === 'students.php' ? 'active' : '' ?>">
                <i class="bi bi-people-fill"></i><span>Enrolled Students</span>
            </a>
            <a href="<?= url('teacher/live-classes.php') ?>" class="sidebar-link <?= $currentScript === 'live-classes.php' ? 'active' : '' ?>">
                <i class="bi bi-broadcast text-danger"></i><span>Live Classes &amp; Streams</span>
                <span class="badge bg-danger ms-auto" style="font-size: 0.65rem; padding: 2px 7px; border-radius: 50px;">LIVE</span>
            </a>
            <a href="<?= url('teacher/discussions.php') ?>" class="sidebar-link <?= $currentScript === 'discussions.php' ? 'active' : '' ?>">
                <i class="bi bi-chat-quote-fill text-info"></i><span>Student Q&amp;A Hub</span>
            </a>
            <a href="<?= url('teacher/announcements.php') ?>" class="sidebar-link <?= in_array($currentScript, ['announcements.php','create-announcement.php','edit-announcement.php']) ? 'active' : '' ?>">
                <i class="bi bi-megaphone-fill text-warning"></i><span>My Announcements</span>
            </a>

            <div class="sidebar-heading mt-3">Earnings &amp; Profile</div>
            <a href="<?= url('teacher/earnings.php') ?>" class="sidebar-link <?= $currentScript === 'earnings.php' ? 'active' : '' ?>">
                <i class="bi bi-wallet-fill text-success"></i><span>Earnings &amp; Payouts</span>
            </a>
            <a href="<?= url('teacher/analytics.php') ?>" class="sidebar-link <?= $currentScript === 'analytics.php' ? 'active' : '' ?>">
                <i class="bi bi-graph-up-arrow"></i><span>Teaching Analytics</span>
            </a>
            <a href="<?= url('teacher/referrals.php') ?>" class="sidebar-link <?= $currentScript === 'referrals.php' ? 'active' : '' ?>">
                <i class="bi bi-gift-fill text-warning"></i><span>Refer &amp; Earn (₦1,500)</span>
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

        <?php elseif ($isSecondaryStudent): ?>
            <div class="sidebar-heading">Secondary School Hub</div>
            <a href="<?= url('student/secondary-dashboard.php') ?>" class="sidebar-link <?= $currentScript === 'secondary-dashboard.php' ? 'active' : '' ?>">
                <i class="bi bi-speedometer2 text-success"></i><span>Dashboard</span>
            </a>
            <a href="<?= url('student/secondary-subjects.php') ?>" class="sidebar-link <?= in_array($currentScript, ['secondary-subjects.php', 'secondary-subject.php']) ? 'active' : '' ?>">
                <i class="bi bi-journals text-primary"></i><span>My Subjects</span>
            </a>
            <a href="<?= url('student/secondary-lessons.php') ?>" class="sidebar-link <?= $currentScript === 'secondary-lessons.php' ? 'active' : '' ?>">
                <i class="bi bi-journal-bookmark-fill text-info"></i><span>Syllabus &amp; Lessons</span>
                <span class="badge bg-info ms-auto" style="font-size: 0.65rem; padding: 2px 7px; border-radius: 50px;">STUDY</span>
            </a>
            <a href="<?= url('student/secondary-materials.php') ?>" class="sidebar-link <?= $currentScript === 'secondary-materials.php' ? 'active' : '' ?>">
                <i class="bi bi-file-earmark-text-fill text-warning"></i><span>Revision Notes</span>
            </a>
            <a href="<?= url('student/secondary-ai-tutor.php') ?>" class="sidebar-link <?= $currentScript === 'secondary-ai-tutor.php' ? 'active' : '' ?>">
                <i class="bi bi-robot" style="color: #F59E0B;"></i><span>AI Tutor</span>
                <span class="badge bg-warning text-dark ms-auto" style="font-size: 0.65rem; padding: 2px 7px; border-radius: 50px;">AI 24/7</span>
            </a>

            <div class="sidebar-heading mt-3">Examinations &amp; CBT</div>
            <a href="<?= url('student/secondary-past-questions.php') ?>" class="sidebar-link <?= $currentScript === 'secondary-past-questions.php' ? 'active' : '' ?>">
                <i class="bi bi-patch-question-fill text-primary"></i><span>Past Questions Hub</span>
                <span class="badge bg-primary ms-auto" style="font-size: 0.65rem; padding: 2px 7px; border-radius: 50px;">HUB</span>
            </a>
            <a href="<?= url('student/secondary-waec.php') ?>" class="sidebar-link <?= $currentScript === 'secondary-waec.php' ? 'active' : '' ?>">
                <i class="bi bi-award-fill text-warning"></i><span>WAEC Practice</span>
            </a>
            <a href="<?= url('student/secondary-neco.php') ?>" class="sidebar-link <?= $currentScript === 'secondary-neco.php' ? 'active' : '' ?>">
                <i class="bi bi-patch-check-fill text-success"></i><span>NECO Practice</span>
            </a>
            <a href="<?= url('student/secondary-jamb.php') ?>" class="sidebar-link <?= $currentScript === 'secondary-jamb.php' ? 'active' : '' ?>">
                <i class="bi bi-lightning-charge-fill text-info"></i><span>JAMB UTME Mock</span>
            </a>
            <a href="<?= url('student/secondary-practice.php') ?>" class="sidebar-link <?= $currentScript === 'secondary-practice.php' ? 'active' : '' ?>">
                <i class="bi bi-cpu-fill text-danger"></i><span>CBT Test Engine</span>
                <span class="badge bg-danger ms-auto" style="font-size: 0.65rem; padding: 2px 7px; border-radius: 50px;">CBT</span>
            </a>
            <a href="<?= url('student/secondary-practice-history.php') ?>" class="sidebar-link <?= $currentScript === 'secondary-practice-history.php' ? 'active' : '' ?>">
                <i class="bi bi-clock-history text-secondary"></i><span>Practice History</span>
            </a>

            <div class="sidebar-heading mt-3">Analytics &amp; Daily</div>
            <a href="<?= url('student/secondary-progress.php') ?>" class="sidebar-link <?= $currentScript === 'secondary-progress.php' ? 'active' : '' ?>">
                <i class="bi bi-graph-up-arrow text-primary"></i><span>My Progress</span>
            </a>
            <a href="<?= url('student/secondary-activity.php') ?>" class="sidebar-link <?= $currentScript === 'secondary-activity.php' ? 'active' : '' ?>">
                <i class="bi bi-activity text-success"></i><span>Daily Activity</span>
            </a>

            <div class="sidebar-heading mt-3">Finance &amp; Account</div>
            <a href="<?= url('student/secondary-referrals.php') ?>" class="sidebar-link <?= $currentScript === 'secondary-referrals.php' ? 'active' : '' ?>">
                <i class="bi bi-gift-fill text-warning"></i><span>Refer &amp; Earn (₦1,000)</span>
                <span class="badge bg-warning text-dark ms-auto" style="font-size: 0.65rem; padding: 2px 7px; border-radius: 50px;">₦1,000</span>
            </a>
            <a href="<?= url('student/secondary-wallet.php') ?>" class="sidebar-link <?= $currentScript === 'secondary-wallet.php' ? 'active' : '' ?>">
                <i class="bi bi-wallet2 text-success"></i><span>Wallet / Balance</span>
            </a>
            <a href="<?= url('student/secondary-notifications.php') ?>" class="sidebar-link <?= $currentScript === 'secondary-notifications.php' ? 'active' : '' ?>">
                <i class="bi bi-bell-fill text-info"></i><span>Notifications</span>
            </a>
            <a href="<?= url('student/profile.php') ?>" class="sidebar-link <?= $currentScript === 'profile.php' ? 'active' : '' ?>">
                <i class="bi bi-person-circle"></i><span>Profile</span>
            </a>
            <a href="<?= url('student/settings.php') ?>" class="sidebar-link <?= $currentScript === 'settings.php' ? 'active' : '' ?>">
                <i class="bi bi-gear-fill"></i><span>Account Settings</span>
            </a>
        <?php else: ?>
            <div class="sidebar-heading">Learning Hub</div>
            <a href="<?= url('student/dashboard.php') ?>" class="sidebar-link <?= $currentScript === 'dashboard.php' ? 'active' : '' ?>">
                <i class="bi bi-speedometer2"></i><span>My Dashboard</span>
            </a>
            <a href="<?= url('student/activity.php') ?>" class="sidebar-link <?= $currentScript === 'activity.php' ? 'active' : '' ?>">
                <i class="bi bi-activity text-primary"></i><span>Daily Activity &amp; Analysis</span>
                <span class="badge bg-warning text-dark ms-auto" style="font-size: 0.65rem; padding: 2px 7px; border-radius: 50px;">TRACK</span>
            </a>
            <a href="<?= url('student/my-courses.php') ?>" class="sidebar-link <?= in_array($currentScript, ['my-courses.php','course.php','lesson.php']) ? 'active' : '' ?>">
                <i class="bi bi-play-circle-fill"></i><span>My Course &amp; Lessons</span>
            </a>
            <a href="<?= url('student/notes.php') ?>" class="sidebar-link <?= $currentScript === 'notes.php' ? 'active' : '' ?>">
                <i class="bi bi-soundwave text-danger"></i><span>Voice &amp; Video Notes</span>
                <span class="badge bg-danger ms-auto" style="font-size: 0.65rem; padding: 2px 7px; border-radius: 50px;">NEW</span>
            </a>
            <a href="<?= url('student/quizzes.php') ?>" class="sidebar-link <?= in_array($currentScript, ['quizzes.php','quiz.php','results.php']) ? 'active' : '' ?>">
                <i class="bi bi-patch-check-fill text-success"></i><span>Available Quizzes &amp; Tests</span>
            </a>
            <a href="<?= url('student/live-classes.php') ?>" class="sidebar-link <?= $currentScript === 'live-classes.php' ? 'active' : '' ?>">
                <i class="bi bi-broadcast text-danger"></i><span>Live Classes &amp; Replays</span>
                <span class="badge bg-danger ms-auto" style="font-size: 0.65rem; padding: 2px 7px; border-radius: 50px;">LIVE</span>
            </a>
            <a href="<?= url('student/ai-assistant.php') ?>" class="sidebar-link <?= $currentScript === 'ai-assistant.php' ? 'active' : '' ?>">
                <i class="bi bi-robot" style="color: #F59E0B;"></i><span>24/7 AI Study Tutor</span>
                <span class="badge bg-warning text-dark ms-auto" style="font-size: 0.65rem; padding: 2px 7px; border-radius: 50px;">AI</span>
            </a>
            <a href="<?= url('student/progress.php') ?>" class="sidebar-link <?= $currentScript === 'progress.php' ? 'active' : '' ?>">
                <i class="bi bi-graph-up-arrow"></i><span>Progress &amp; Analytics</span>
            </a>
            <a href="<?= url('student/certificates.php') ?>" class="sidebar-link <?= $currentScript === 'certificates.php' ? 'active' : '' ?>">
                <i class="bi bi-award-fill" style="color: #F59E0B;"></i><span>Verifiable Certificates</span>
            </a>

            <div class="sidebar-heading mt-3">Discover &amp; Instructors</div>
            <a href="<?= url('courses/index.php') ?>" class="sidebar-link <?= $currentScript === 'index.php' && strpos($currentUri, 'courses') !== false ? 'active' : '' ?>">
                <i class="bi bi-compass-fill"></i><span>Browse Course Directory</span>
            </a>
            <a href="<?= url('courses/technology.php') ?>" class="sidebar-link <?= $currentScript === 'technology.php' ? 'active' : '' ?>">
                <i class="bi bi-code-slash text-primary"></i><span>Technology Bootcamps</span>
            </a>
            <a href="<?= url('courses/secondary.php') ?>" class="sidebar-link <?= $currentScript === 'secondary.php' ? 'active' : '' ?>">
                <i class="bi bi-book-half text-success"></i><span>WAEC / NECO / JAMB Prep</span>
            </a>
            <a href="<?= url('courses/university.php') ?>" class="sidebar-link <?= $currentScript === 'university.php' ? 'active' : '' ?>">
                <i class="bi bi-mortarboard-fill text-info"></i><span>University Degree Modules</span>
            </a>
            <a href="<?= url('teachers.php') ?>" class="sidebar-link <?= $currentScript === 'teachers.php' ? 'active' : '' ?>">
                <i class="bi bi-person-badge-fill text-warning"></i><span>Teacher Status &amp; Directory</span>
            </a>
            <a href="<?= url('student/referrals.php') ?>" class="sidebar-link <?= $currentScript === 'referrals.php' ? 'active' : '' ?>">
                <i class="bi bi-gift-fill text-success"></i><span>Refer &amp; Earn (₦1,500)</span>
                <span class="badge bg-success ms-auto" style="font-size: 0.65rem; padding: 2px 7px; border-radius: 50px;">EARN</span>
            </a>

            <div class="sidebar-heading mt-3">Account &amp; Notices</div>
            <a href="<?= url('announcements/index.php') ?>" class="sidebar-link <?= $currentScript === 'index.php' && strpos($currentUri, 'announcements') !== false ? 'active' : '' ?>">
                <i class="bi bi-megaphone-fill text-warning"></i><span>Official Announcements</span>
            </a>
            <a href="<?= url('student/notifications.php') ?>" class="sidebar-link <?= $currentScript === 'notifications.php' ? 'active' : '' ?>">
                <i class="bi bi-bell-fill"></i><span>Notifications &amp; Inbox</span>
            </a>
            <a href="<?= url('payments/history.php') ?>" class="sidebar-link <?= $currentScript === 'history.php' ? 'active' : '' ?>">
                <i class="bi bi-credit-card-2-front-fill"></i><span>Billing &amp; Subscription</span>
            </a>
            <a href="<?= url('student/profile.php') ?>" class="sidebar-link <?= $currentScript === 'profile.php' ? 'active' : '' ?>">
                <i class="bi bi-person-circle"></i><span>Student Profile</span>
            </a>
            <a href="<?= url('student/settings.php') ?>" class="sidebar-link <?= $currentScript === 'settings.php' ? 'active' : '' ?>">
                <i class="bi bi-gear-fill"></i><span>Settings</span>
            </a>
        <?php endif; ?>
    </nav>

    <?php 
    $sidebarAvatarUrl = function_exists('get_avatar_url') ? get_avatar_url($user['avatar'] ?? null, $user['first_name'] ?? 'User') : ($user['avatar'] ?? '');
    $profileLink = url(($currentRole === ROLE_ADMIN ? 'admin' : ($currentRole === ROLE_TEACHER ? 'teacher' : 'student')) . '/profile.php');
    ?>
    <a href="<?= $profileLink ?>" class="sidebar-user text-decoration-none" title="Click to view &amp; update profile photo">
        <div class="sidebar-user-avatar overflow-hidden position-relative flex-shrink-0">
            <img src="<?= e($sidebarAvatarUrl) ?>" alt="Avatar" style="width: 100%; height: 100%; object-fit: cover;" onerror="this.onerror=null; this.src='https://ui-avatars.com/api/?name=<?= urlencode($user['first_name'] ?? 'User') ?>&background=1e40af&color=ffffff&bold=true';">
            <span class="status-pulse-dot position-absolute bottom-0 end-0 bg-success border border-2 border-dark rounded-circle" style="width: 10px; height: 10px; z-index: 5;" title="Active Now"></span>
        </div>
        <div class="sidebar-user-info">
            <div class="sidebar-user-name"><?= htmlspecialchars($displayName ?: 'User', ENT_QUOTES, 'UTF-8') ?></div>
            <div class="sidebar-user-role"><?= htmlspecialchars($roleBadge, ENT_QUOTES, 'UTF-8') ?> &bull; <span class="text-primary fw-bold" style="font-size: 0.72rem;">Edit Profile</span></div>
        </div>
    </a>

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
</aside>

<script>
function confirmLogout() {
    if (typeof StudyMeFeedback !== 'undefined') {
        StudyMeFeedback.error();
    }
    return confirm("Are you sure you want to log out of StudyMe?");
}
</script>
