<?php
/**
 * StudyMe AI Platform — Cookie Policy
 */
require_once __DIR__ . '/config/main.php';
include BASE_PATH . '/includes/layouts/header.php';
?>

<div class="py-5 bg-light-subtle" style="min-height: calc(100vh - 120px);">
    <div class="container py-4">
        <!-- Header -->
        <div class="text-center max-w-700 mx-auto mb-5 animate-fade-in">
            <div class="p-3 bg-primary bg-opacity-10 text-primary rounded-circle d-inline-flex mb-3">
                <i class="bi bi-cookie fs-1"></i>
            </div>
            <h1 class="display-5 fw-bold mb-2">Cookie Policy</h1>
            <p class="lead text-muted">Learn how StudyMe uses cookies and local storage to personalize your learning experience.</p>
            <div class="small text-muted">Last Updated: August 2026</div>
        </div>

        <div class="row justify-content-center">
            <div class="col-lg-9">
                <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 text-secondary lh-lg">
                    
                    <h4 class="fw-bold text-main mb-3">1. What Are Cookies &amp; Local Storage?</h4>
                    <p>
                        Cookies are small text files placed on your browser or device when you visit websites. Local storage provides similar client-side data persistence with greater storage capacity. StudyMe uses these technologies to maintain secure user sessions, remember your light/dark mode theme preferences, and track audio feedback settings.
                    </p>

                    <h4 class="fw-bold text-main mt-4 mb-3">2. How StudyMe Uses Cookies</h4>
                    <p>StudyMe uses cookies strictly for essential and functional purposes:</p>
                    <ul>
                        <li><strong>Essential Authentication Cookies:</strong> Required to maintain your logged-in session, distinguish user roles (Student, Teacher, Admin), and protect against Cross-Site Request Forgery (CSRF).</li>
                        <li><strong>Preferences &amp; Customization:</strong> Local storage is used to store your preferred appearance (Light Mode vs. Dark Mode) and UI sound effect toggle state.</li>
                        <li><strong>Course &amp; Lesson State:</strong> Used to temporarily store video playback timestamps so you can resume lessons seamlessly without losing progress.</li>
                    </ul>

                    <h4 class="fw-bold text-main mt-4 mb-3">3. No Third-Party Tracking Cookies</h4>
                    <p>
                        StudyMe does NOT sell your data, nor do we deploy intrusive third-party advertising tracking cookies. Your learning activity and AI tutor interactions remain confidential within the platform.
                    </p>

                    <h4 class="fw-bold text-main mt-4 mb-3">4. Managing &amp; Disabling Cookies</h4>
                    <p>
                        You can manage or disable cookies through your browser settings (Chrome, Firefox, Safari, Edge). Please note that disabling essential session cookies will prevent you from signing in to your StudyMe student or instructor dashboard.
                    </p>

                    <h4 class="fw-bold text-main mt-4 mb-3">5. Contact Us</h4>
                    <p class="mb-0">
                        If you have questions regarding our cookie practices, please contact our data privacy team at <a href="mailto:privacy@studyme.ng" class="fw-bold text-primary">privacy@studyme.ng</a> or visit our <a href="<?= url('contact.php') ?>" class="fw-bold text-primary">Contact Page</a>.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include BASE_PATH . '/includes/layouts/footer.php'; ?>
