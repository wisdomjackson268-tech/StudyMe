<?php
/**
 * StudyMe AI Platform — Shared Public Footer Layout
 */
$isLoggedIn = function_exists('is_logged_in') && is_logged_in();
$userRole = function_exists('current_user_role') ? current_user_role() : null;

$dashboardUrl = 'auth/login.php';
if ($isLoggedIn) {
    if ($userRole === 'admin') {
        $dashboardUrl = 'admin/dashboard.php';
    } elseif ($userRole === 'teacher') {
        $dashboardUrl = 'teacher/dashboard.php';
    } else {
        $dashboardUrl = 'student/dashboard.php';
    }
}
?>
<footer class="footer">
    <div class="container">
        <div class="row g-4">
            <!-- Brand Column -->
            <div class="col-lg-4 col-md-6">
                <a class="logo text-white mb-3 text-decoration-none d-inline-flex align-items-center gap-2" href="<?= function_exists('url') ? url('index.php') : 'index.php' ?>">
                    <i class="bi bi-mortarboard-fill text-warning fs-2"></i>
                    <span class="fs-4 fw-bold">StudyMe</span>
                </a>
                <p class="text-white-50 small mb-4 pe-lg-4 lh-base">
                    StudyMe is an advanced AI-powered learning platform designed to help students master skills with 24/7 AI tutoring, learn from certified expert teachers, track real-time progress, and earn verifiable digital certificates.
                </p>
                <div class="d-flex align-items-center gap-2">
                    <a href="https://facebook.com/studymehq" target="_blank" rel="noopener noreferrer" class="social-icon-btn" aria-label="Facebook"><i class="bi bi-facebook"></i></a>
                    <a href="https://twitter.com/studyme_ai" target="_blank" rel="noopener noreferrer" class="social-icon-btn" aria-label="Twitter X"><i class="bi bi-twitter-x"></i></a>
                    <a href="https://instagram.com/studymehq" target="_blank" rel="noopener noreferrer" class="social-icon-btn" aria-label="Instagram"><i class="bi bi-instagram"></i></a>
                    <a href="https://linkedin.com/company/studyme-ai" target="_blank" rel="noopener noreferrer" class="social-icon-btn" aria-label="LinkedIn"><i class="bi bi-linkedin"></i></a>
                    <a href="https://youtube.com/@studyme-ai" target="_blank" rel="noopener noreferrer" class="social-icon-btn" aria-label="YouTube"><i class="bi bi-youtube"></i></a>
                </div>
            </div>

            <!-- Platform Links -->
            <div class="col-lg-2 col-6">
                <h5>Platform</h5>
                <a href="<?= function_exists('url') ? url('courses/index.php') : 'courses/index.php' ?>" class="footer-link">Browse Courses</a>
                <a href="<?= function_exists('url') ? url('ai-learning.php') : 'ai-learning.php' ?>" class="footer-link">AI Learning Tutor</a>
                <a href="<?= function_exists('url') ? url('teachers.php') : 'teachers.php' ?>" class="footer-link">Expert Teachers</a>
                <a href="<?= function_exists('url') ? url('pricing.php') : 'pricing.php' ?>" class="footer-link">Pricing &amp; Plans</a>
                <a href="<?= function_exists('url') ? url('certificates/verify.php') : 'certificates/verify.php' ?>" class="footer-link">Verify Certificate</a>
            </div>

            <!-- Resources Links -->
            <div class="col-lg-2 col-6">
                <h5>Resources</h5>
                <a href="<?= function_exists('url') ? url('help.php') : 'help.php' ?>" class="footer-link">Help Center</a>
                <a href="<?= function_exists('url') ? url('faq.php') : 'faq.php' ?>" class="footer-link">FAQ</a>
                <a href="<?= function_exists('url') ? url('blog/index.php') : 'blog/index.php' ?>" class="footer-link">Blog &amp; Articles</a>
                <a href="<?= function_exists('url') ? url('community.php') : 'community.php' ?>" class="footer-link">Community Hub</a>
                <a href="<?= function_exists('url') ? url('contact.php') : 'contact.php' ?>" class="footer-link">Contact Support</a>
            </div>

            <!-- Company Links -->
            <div class="col-lg-4 col-md-6">
                <h5>Company &amp; Legal</h5>
                <a href="<?= function_exists('url') ? url('about.php') : 'about.php' ?>" class="footer-link">About StudyMe</a>
                <a href="<?= function_exists('url') ? url('careers.php') : 'careers.php' ?>" class="footer-link">Careers &amp; Opportunities</a>
                <a href="<?= function_exists('url') ? url('auth/teacher-course-select.php') : 'auth/teacher-course-select.php' ?>" class="footer-link">Become an Instructor</a>
                <a href="<?= function_exists('url') ? url('auth/teacher-login.php') : 'auth/teacher-login.php' ?>" class="footer-link">Teacher Login Portal</a>
                <a href="<?= function_exists('url') ? url('privacy.php') : 'privacy.php' ?>" class="footer-link">Privacy Policy</a>
                <a href="<?= function_exists('url') ? url('terms.php') : 'terms.php' ?>" class="footer-link">Terms &amp; Conditions</a>
                <a href="<?= function_exists('url') ? url('cookies.php') : 'cookies.php' ?>" class="footer-link">Cookie Policy</a>
            </div>
        </div>

        <!-- Account Quick Bar -->
        <div class="row pt-4 mt-4 border-top border-secondary border-opacity-25 align-items-center justify-content-between">
            <div class="col-md-6 mb-3 mb-md-0">
                <div class="d-flex align-items-center gap-2 flex-wrap text-white-50 small">
                    <span>Account:</span>
                    <?php if ($isLoggedIn): ?>
                        <a href="<?= function_exists('url') ? url($dashboardUrl) : $dashboardUrl ?>" class="badge bg-primary text-decoration-none px-3 py-2 rounded-pill">
                            <i class="bi bi-grid-fill me-1"></i> Go to <?= ucfirst($userRole ?? 'User') ?> Dashboard
                        </a>
                        <a href="<?= function_exists('url') ? url('auth/logout.php') : 'auth/logout.php' ?>" class="text-white-50 text-decoration-none hover-white ms-2">
                            <i class="bi bi-box-arrow-right me-1"></i> Sign Out
                        </a>
                    <?php else: ?>
                        <a href="<?= function_exists('url') ? url('auth/login.php') : 'auth/login.php' ?>" class="text-white-50 text-decoration-none hover-white">
                            <i class="bi bi-box-arrow-in-right me-1"></i> Sign In
                        </a>
                        <span class="text-secondary">&bull;</span>
                        <a href="<?= function_exists('url') ? url('auth/register.php') : 'auth/register.php' ?>" class="text-warning text-decoration-none fw-semibold">
                            <i class="bi bi-person-plus-fill me-1"></i> Get Started Free
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-md-6 text-md-end">
                <p class="mb-0 text-white-50 small">&copy; <?= date('Y') ?> StudyMe AI-Powered Learning Platform. All rights reserved.</p>
            </div>
        </div>
    </div>
</footer>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>

<!-- Theme JS -->
<script src="<?= function_exists('asset') ? asset('js/theme.js') : 'assets/js/theme.js' ?>"></script>

<!-- Skeleton JS -->
<script src="<?= function_exists('asset') ? asset('js/skeleton.js') : 'assets/js/skeleton.js' ?>"></script>

<!-- Loader JS -->
<script src="<?= function_exists('asset') ? asset('js/loader.js') : 'assets/js/loader.js' ?>"></script>

<!-- Feedback System JS -->
<script src="<?= function_exists('asset') ? asset('js/feedback.js') : 'assets/js/feedback.js' ?>"></script>

<!-- Main App JS -->
<script src="<?= function_exists('asset') ? asset('js/app.js') : 'assets/js/app.js' ?>"></script>

<?php if (isset($_SESSION['auth_success_vibrate'])): ?>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        if (typeof StudyMeFeedback !== 'undefined') {
            StudyMeFeedback.success();
        } else if (navigator.vibrate) {
            navigator.vibrate(150);
        }
    });
</script>
<?php unset($_SESSION['auth_success_vibrate']); endif; ?>

</body>
</html>