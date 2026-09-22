<?php

require_once __DIR__ . '/config/main.php';
include BASE_PATH . '/includes/layouts/header.php';
?>

<div class="py-4 py-md-5 bg-body-tertiary min-vh-100">
    <div class="container py-2 py-md-4">
        
        <!-- Hero Header -->
        <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 mb-4 text-center bg-gradient-hero overflow-hidden position-relative" style="background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #1e3a8a 100%); color: #ffffff;">
            <div class="position-relative" style="z-index: 2;">
                <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-white bg-opacity-10 p-3 mb-3 border border-white border-opacity-20 shadow-sm">
                    <i class="bi bi-file-earmark-text fs-1 text-warning"></i>
                </div>
                <h1 class="fw-bold display-5 text-white mb-2" style="color: #ffffff !important; text-shadow: 0 2px 10px rgba(0,0,0,0.3);">Terms &amp; Conditions</h1>
                <p class="text-white-50 mb-3 mx-auto" style="max-width: 620px; font-size: 1.05rem;">
                    The legal terms and user guidelines governing your access to and use of StudyMe's courses, AI features, and services.
                </p>
                <div class="d-flex align-items-center justify-content-center gap-2 flex-wrap">
                    <span class="badge bg-warning text-dark rounded-pill px-3 py-2 font-monospace fw-bold">
                        <i class="bi bi-clock-history me-1"></i> Updated: <?= date('F d, Y') ?>
                    </span>
                    <span class="badge bg-white bg-opacity-15 text-white border border-white border-opacity-25 rounded-pill px-3 py-2 font-monospace">
                        <i class="bi bi-shield-check me-1"></i> User Agreement
                    </span>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- Sidebar Navigation (Sticky on Desktop) -->
            <div class="col-lg-3 d-none d-lg-block">
                <div class="sticky-top" style="top: 90px; z-index: 10;">
                    <div class="card border-0 shadow-sm rounded-4 p-3 bg-body">
                        <h6 class="fw-bold text-uppercase text-muted px-3 pt-2 mb-3" style="font-size: 0.75rem; letter-spacing: 0.05em;">
                            <i class="bi bi-list-nested me-1"></i> Jump to Section
                        </h6>
                        <div class="nav flex-column nav-pills small gap-1">
                            <a href="#tsec-1" class="nav-link text-body-secondary rounded-3 py-2 px-3 fw-semibold"><i class="bi bi-1-circle me-2 text-primary"></i>Introduction</a>
                            <a href="#tsec-2" class="nav-link text-body-secondary rounded-3 py-2 px-3 fw-semibold"><i class="bi bi-2-circle me-2 text-primary"></i>User Eligibility</a>
                            <a href="#tsec-3" class="nav-link text-body-secondary rounded-3 py-2 px-3 fw-semibold"><i class="bi bi-3-circle me-2 text-primary"></i>AI Content Disclaimer</a>
                            <a href="#tsec-4" class="nav-link text-body-secondary rounded-3 py-2 px-3 fw-semibold"><i class="bi bi-4-circle me-2 text-primary"></i>Subscriptions &amp; Payments</a>
                            <a href="#tsec-5" class="nav-link text-body-secondary rounded-3 py-2 px-3 fw-semibold"><i class="bi bi-5-circle me-2 text-primary"></i>Bank Transfer Rules</a>
                            <a href="#tsec-6" class="nav-link text-body-secondary rounded-3 py-2 px-3 fw-semibold"><i class="bi bi-6-circle me-2 text-primary"></i>Intellectual Property</a>
                            <a href="#tsec-7" class="nav-link text-body-secondary rounded-3 py-2 px-3 fw-semibold"><i class="bi bi-7-circle me-2 text-primary"></i>Acceptable Use</a>
                            <a href="#tsec-8" class="nav-link text-body-secondary rounded-3 py-2 px-3 fw-semibold"><i class="bi bi-8-circle me-2 text-primary"></i>Account Suspension</a>
                            <a href="#tsec-9" class="nav-link text-body-secondary rounded-3 py-2 px-3 fw-semibold"><i class="bi bi-9-circle me-2 text-primary"></i>Limitation of Liability</a>
                            <a href="#tsec-10" class="nav-link text-body-secondary rounded-3 py-2 px-3 fw-semibold"><i class="bi bi-arrow-repeat me-2 text-primary"></i>Terms Updates</a>
                            <a href="#tsec-11" class="nav-link text-body-secondary rounded-3 py-2 px-3 fw-semibold"><i class="bi bi-envelope-paper me-2 text-primary"></i>Contact Information</a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Terms Document Column -->
            <div class="col-lg-9">
                <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 bg-body">
                    <div class="terms-body text-body-secondary lh-lg" style="font-size: 0.98rem;">
                        
                        <!-- Section 1 -->
                        <div id="tsec-1" class="policy-section mb-4 pb-3 border-bottom border-light-subtle">
                            <h4 class="fw-bold text-main d-flex align-items-center gap-2 mb-3">
                                <span class="badge bg-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; font-size: 0.85rem;">1</span>
                                Introduction
                            </h4>
                            <p>Welcome to StudyMe, an AI-powered digital learning ecosystem for University students, WAEC/NECO secondary candidates, and accredited instructors. By accessing or registering an account on our platform, you agree to be bound by these Terms &amp; Conditions.</p>
                        </div>

                        <!-- Section 2 -->
                        <div id="tsec-2" class="policy-section mb-4 pb-3 border-bottom border-light-subtle">
                            <h4 class="fw-bold text-main d-flex align-items-center gap-2 mb-3">
                                <span class="badge bg-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; font-size: 0.85rem;">2</span>
                                User Accounts &amp; Eligibility
                            </h4>
                            <p>You must create an account to access interactive courses, video lessons, quizzes, and AI features. You are responsible for keeping your account password confidential and for all learning activity conducted under your credentials.</p>
                        </div>

                        <!-- Section 3 -->
                        <div id="tsec-3" class="policy-section mb-4 pb-3 border-bottom border-light-subtle">
                            <h4 class="fw-bold text-main d-flex align-items-center gap-2 mb-3">
                                <span class="badge bg-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; font-size: 0.85rem;">3</span>
                                AI-Powered Features &amp; Content Disclaimer
                            </h4>
                            <p>StudyMe provides AI-assisted learning tools, including a 24/7 AI Tutor, Concept Explainer, Quiz Crafter, and Automated Study Notes. While we strive for extreme academic accuracy, AI-generated explanations are intended for supplemental study assistance and should be cross-referenced with your official course syllabus and accredited textbook materials.</p>
                        </div>

                        <!-- Section 4 -->
                        <div id="tsec-4" class="policy-section mb-4 pb-3 border-bottom border-light-subtle">
                            <h4 class="fw-bold text-main d-flex align-items-center gap-2 mb-3">
                                <span class="badge bg-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; font-size: 0.85rem;">4</span>
                                Subscriptions &amp; Payments
                            </h4>
                            <p>Access to full course catalog tracks and premium AI capabilities requires an active subscription plan. Pricing is explicitly listed on our <a href="<?= url('pricing.php') ?>" class="text-primary fw-semibold">Pricing Page</a>. All payments are billed according to your selected plan billing cycle.</p>
                        </div>

                        <!-- Section 5 -->
                        <div id="tsec-5" class="policy-section mb-4 pb-3 border-bottom border-light-subtle">
                            <h4 class="fw-bold text-main d-flex align-items-center gap-2 mb-3">
                                <span class="badge bg-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; font-size: 0.85rem;">5</span>
                                Bank Transfer Payments &amp; Verification
                            </h4>
                            <p>If you choose to pay via Direct Bank Transfer, your subscription will be activated upon verification by our finance operations team. You are required to submit accurate payment reference details. Submissions containing fraudulent or invalid transaction references will be rejected and may result in immediate account termination.</p>
                        </div>

                        <!-- Section 6 -->
                        <div id="tsec-6" class="policy-section mb-4 pb-3 border-bottom border-light-subtle">
                            <h4 class="fw-bold text-main d-flex align-items-center gap-2 mb-3">
                                <span class="badge bg-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; font-size: 0.85rem;">6</span>
                                Intellectual Property Rights
                            </h4>
                            <p>All platform materials—including video lessons, custom examination question banks, platform layout code, logos, and course assets—are the exclusive intellectual property of StudyMe and its verified instructors. You may not reproduce, redistribute, or reverse-engineer any portion of the platform without written permission.</p>
                        </div>

                        <!-- Section 7 -->
                        <div id="tsec-7" class="policy-section mb-4 pb-3 border-bottom border-light-subtle">
                            <h4 class="fw-bold text-main d-flex align-items-center gap-2 mb-3">
                                <span class="badge bg-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; font-size: 0.85rem;">7</span>
                                Acceptable Use Policy
                            </h4>
                            <p>You agree not to engage in unauthorized activities, including submitting false payment receipts, scraping platform content, attempting to prompt-inject AI engines for malicious purposes, or harassing instructors or fellow students in Q&amp;A discussion forums.</p>
                        </div>

                        <!-- Section 8 -->
                        <div id="tsec-8" class="policy-section mb-4 pb-3 border-bottom border-light-subtle">
                            <h4 class="fw-bold text-main d-flex align-items-center gap-2 mb-3">
                                <span class="badge bg-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; font-size: 0.85rem;">8</span>
                                Account Suspension &amp; Termination
                            </h4>
                            <p>StudyMe reserves the right to temporarily suspend or permanently terminate user accounts that violate these Terms and Conditions or engage in harmful platform conduct without prior notice.</p>
                        </div>

                        <!-- Section 9 -->
                        <div id="tsec-9" class="policy-section mb-4 pb-3 border-bottom border-light-subtle">
                            <h4 class="fw-bold text-main d-flex align-items-center gap-2 mb-3">
                                <span class="badge bg-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; font-size: 0.85rem;">9</span>
                                Limitation of Liability
                            </h4>
                            <p>StudyMe is provided on an "as is" and "as available" basis. We are not liable for any indirect, incidental, or consequential damages resulting from network interruptions or third-party service outages.</p>
                        </div>

                        <!-- Section 10 -->
                        <div id="tsec-10" class="policy-section mb-4 pb-3 border-bottom border-light-subtle">
                            <h4 class="fw-bold text-main d-flex align-items-center gap-2 mb-3">
                                <span class="badge bg-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; font-size: 0.85rem;">10</span>
                                Changes to these Terms
                            </h4>
                            <p>We may modify these Terms at any time. Continued usage of the StudyMe platform following posted modifications constitutes full acceptance of the updated Terms.</p>
                        </div>

                        <!-- Section 11 -->
                        <div id="tsec-11" class="policy-section mb-2">
                            <h4 class="fw-bold text-main d-flex align-items-center gap-2 mb-3">
                                <span class="badge bg-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; font-size: 0.85rem;">11</span>
                                Contact Information
                            </h4>
                            <p>If you have any questions regarding these Terms &amp; Conditions, please reach out to us at <a href="mailto:studyme910@gmail.com" class="fw-semibold text-primary">studyme910@gmail.com</a>, call <a href="tel:09026849170" class="fw-semibold text-primary">09026849170</a>, or submit an inquiry through our <a href="<?= url('contact.php') ?>" class="fw-semibold text-primary">Contact Page</a>.</p>
                        </div>

                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<?php include BASE_PATH . '/includes/layouts/footer.php'; ?>
