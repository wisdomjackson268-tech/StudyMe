<?php

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
            <div class="col-lg-4 col-md-6">
                <a class="logo text-white mb-3 text-decoration-none d-inline-flex align-items-center gap-2" href="<?= function_exists('url') ? url('index.php') : 'index.php' ?>">
                    <i class="bi bi-mortarboard-fill text-warning fs-2"></i>
                    <span class="fs-4 fw-bold">StudyMe</span>
                </a>
                <p class="text-white-50 small mb-3 pe-lg-3 lh-base">
                    StudyMe is an advanced AI-powered learning platform designed to help students master skills with 24/7 AI tutoring, learn from certified expert teachers, track real-time progress, and earn verifiable digital certificates.
                </p>
                <div class="d-flex align-items-center gap-2 flex-wrap mb-3">
                    <a href="https://x.com/StudyMe910" target="_blank" rel="noopener noreferrer" class="social-icon-btn" aria-label="X (formerly Twitter)" title="Follow StudyMe on X"><i class="bi bi-twitter-x"></i></a>
                    <a href="https://youtube.com/@studyme910" target="_blank" rel="noopener noreferrer" class="social-icon-btn" aria-label="YouTube" title="Subscribe to StudyMe on YouTube"><i class="bi bi-youtube"></i></a>
                    <a href="https://www.tiktok.com/@study.me32" target="_blank" rel="noopener noreferrer" class="social-icon-btn" aria-label="TikTok" title="Follow StudyMe on TikTok"><i class="bi bi-tiktok"></i></a>
                    <a href="https://www.linkedin.com/in/wisdom-jackson-b19941345" target="_blank" rel="noopener noreferrer" class="social-icon-btn" aria-label="LinkedIn" title="Connect with Wisdom Jackson on LinkedIn"><i class="bi bi-linkedin"></i></a>
                    <a href="https://www.facebook.com/share/1BYXM81J2o/?mibextid=wwXIfr" target="_blank" rel="noopener noreferrer" class="social-icon-btn" aria-label="Facebook" title="Visit StudyMe on Facebook"><i class="bi bi-facebook"></i></a>
                    <a href="https://github.com/wisdomjackson268-tech" target="_blank" rel="noopener noreferrer" class="social-icon-btn" aria-label="GitHub" title="Visit StudyMe GitHub Profile"><i class="bi bi-github"></i></a>
                </div>
            </div>

            <div class="col-lg-2 col-6">
                <h5>Platform</h5>
                <a href="<?= function_exists('url') ? url('courses/index.php') : 'courses/index.php' ?>" class="footer-link">Browse Courses</a>
                <a href="<?= function_exists('url') ? url('ai-learning.php') : 'ai-learning.php' ?>" class="footer-link">AI Learning Tutor</a>
                <a href="<?= function_exists('url') ? url('teachers.php') : 'teachers.php' ?>" class="footer-link">Expert Teachers</a>
                <a href="<?= function_exists('url') ? url('pricing.php') : 'pricing.php' ?>" class="footer-link">Pricing &amp; Plans</a>
                <a href="<?= function_exists('url') ? url('certificates/verify.php') : 'certificates/verify.php' ?>" class="footer-link">Verify Certificate</a>
            </div>

            <div class="col-lg-2 col-6">
                <h5>Resources</h5>
                <a href="<?= function_exists('url') ? url('help.php') : 'help.php' ?>" class="footer-link">Help Center</a>
                <a href="<?= function_exists('url') ? url('faq.php') : 'faq.php' ?>" class="footer-link">FAQ</a>
                <a href="<?= function_exists('url') ? url('blog/index.php') : 'blog/index.php' ?>" class="footer-link">Blog &amp; Articles</a>
                <a href="<?= function_exists('url') ? url('community.php') : 'community.php' ?>" class="footer-link">Community Hub</a>
                <a href="<?= function_exists('url') ? url('about.php') : 'about.php' ?>" class="footer-link">About StudyMe</a>
                <a href="<?= function_exists('url') ? url('privacy.php') : 'privacy.php' ?>" class="footer-link">Privacy Policy</a>
                <a href="<?= function_exists('url') ? url('terms.php') : 'terms.php' ?>" class="footer-link">Terms &amp; Conditions</a>
            </div>

            <div class="col-lg-4 col-md-6">
                <h5>Get in Touch</h5>
                <div class="d-flex flex-column gap-2 text-white-50 small mb-3">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-telephone-fill text-warning"></i>
                        <a href="tel:09026849170" class="text-white-50 text-decoration-none hover-white">09026849170</a>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-envelope-fill text-warning"></i>
                        <a href="mailto:studyme910@gmail.com" class="text-white-50 text-decoration-none hover-white">studyme910@gmail.com</a>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-envelope-at-fill text-warning"></i>
                        <a href="mailto:wisdomjackson268@gmail.com" class="text-white-50 text-decoration-none hover-white">wisdomjackson268@gmail.com</a>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-github text-warning"></i>
                        <a href="https://github.com/wisdomjackson268-tech" target="_blank" rel="noopener noreferrer" class="text-white-50 text-decoration-none hover-white">wisdomjackson268-tech</a>
                    </div>
                </div>
                <div class="pt-2">
                    <a href="<?= function_exists('url') ? url('contact.php') : 'contact.php' ?>" class="btn btn-outline-warning btn-sm rounded-pill px-3 fw-semibold">
                        <i class="bi bi-chat-dots-fill me-1"></i> Open Contact Page
                    </a>
                </div>
            </div>
        </div>

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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= function_exists('asset') ? asset('js/theme.js') : 'assets/js/theme.js' ?>"></script>
<script src="<?= function_exists('asset') ? asset('js/skeleton.js') : 'assets/js/skeleton.js' ?>"></script>
<script src="<?= function_exists('asset') ? asset('js/loader.js') : 'assets/js/loader.js' ?>"></script>
<script src="<?= function_exists('asset') ? asset('js/feedback.js') : 'assets/js/feedback.js' ?>"></script>
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