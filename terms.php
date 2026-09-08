<?php
/**
 * StudyMe AI Platform - Terms and Conditions
 */
require_once __DIR__ . '/config/main.php';
include BASE_PATH . '/includes/layouts/header.php';
?>

<div class="py-5 bg-light-subtle" style="min-height: calc(100vh - 120px);">
    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5">
                    <h1 class="fw-bold mb-4">Terms and Conditions</h1>
                    <p class="text-muted mb-4">Last Updated: <?= date('F d, Y') ?></p>

                    <div class="terms-content lh-lg text-secondary">
                        <h4 class="text-dark fw-bold mt-4">1. Introduction</h4>
                        <p>Welcome to StudyMe, an AI-powered learning platform. By accessing or using our platform, you agree to be bound by these Terms and Conditions.</p>

                        <h4 class="text-dark fw-bold mt-4">2. User Accounts & Eligibility</h4>
                        <p>You must create an account to access certain features. You are responsible for maintaining the confidentiality of your account credentials and for all activities that occur under your account.</p>

                        <h4 class="text-dark fw-bold mt-4">3. AI-Powered Features & Learning Content</h4>
                        <p>StudyMe provides AI-assisted learning tools, including a 24/7 AI Tutor and Concept Explainer. While we strive for accuracy, AI-generated content is provided for educational support and should not replace professional advice.</p>

                        <h4 class="text-dark fw-bold mt-4">4. Subscriptions & Bank Transfer Payments</h4>
                        <p>Certain features require a paid subscription. Payments are processed securely. If you choose to pay via Bank Transfer, your subscription will only become active upon manual verification by our administration team. You are required to submit accurate payment confirmation details including the correct Payment Reference.</p>
                        
                        <h4 class="text-dark fw-bold mt-4">5. Payment Verification</h4>
                        <p>Bank transfer payments are marked as "Pending" until our team confirms receipt of the funds. Do not share your transaction reference or payment proof publicly. StudyMe reserves the right to reject payments that cannot be verified.</p>

                        <h4 class="text-dark fw-bold mt-4">6. Intellectual Property</h4>
                        <p>All content, including courses, videos, quizzes, and platform design, is the intellectual property of StudyMe and its instructors. You may not copy, distribute, or modify this content without permission.</p>

                        <h4 class="text-dark fw-bold mt-4">7. Acceptable Use</h4>
                        <p>You agree not to misuse the platform. This includes, but is not limited to: submitting false payment confirmations, attempting to manipulate AI systems, scraping content, or harassing other users.</p>

                        <h4 class="text-dark fw-bold mt-4">8. Account Suspension & Termination</h4>
                        <p>We reserve the right to suspend or terminate your account at our sole discretion if you violate these Terms and Conditions.</p>

                        <h4 class="text-dark fw-bold mt-4">9. Limitation of Liability</h4>
                        <p>StudyMe is provided "as is". We are not liable for any indirect, incidental, or consequential damages arising from your use of the platform.</p>

                        <h4 class="text-dark fw-bold mt-4">10. Changes to the Terms</h4>
                        <p>We may modify these Terms at any time. Continued use of the platform after changes constitutes acceptance of the new Terms.</p>

                        <h4 class="text-dark fw-bold mt-4">11. Contact Information</h4>
                        <p>If you have any questions about these Terms, please contact support@studyme.ng.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include BASE_PATH . '/includes/layouts/footer.php'; ?>
