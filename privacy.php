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
                    <i class="bi bi-shield-check fs-1 text-warning"></i>
                </div>
                <h1 class="fw-bold display-5 text-white mb-2" style="color: #ffffff !important; text-shadow: 0 2px 10px rgba(0,0,0,0.3);">Privacy Policy</h1>
                <p class="text-white-50 mb-3 mx-auto" style="max-width: 620px; font-size: 1.05rem;">
                    Transparent information on how StudyMe collects, uses, and safeguards your account data, learning activity, and AI tutor interactions.
                </p>
                <div class="d-flex align-items-center justify-content-center gap-2 flex-wrap">
                    <span class="badge bg-warning text-dark rounded-pill px-3 py-2 font-monospace fw-bold">
                        <i class="bi bi-clock-history me-1"></i> Updated: <?= date('F d, Y') ?>
                    </span>
                    <span class="badge bg-white bg-opacity-15 text-white border border-white border-opacity-25 rounded-pill px-3 py-2 font-monospace">
                        <i class="bi bi-lock-fill me-1"></i> Data Encryption Active
                    </span>
                    <span class="badge bg-success bg-opacity-25 text-white border border-success border-opacity-50 rounded-pill px-3 py-2 font-monospace">
                        <i class="bi bi-check-circle-fill me-1"></i> NDPR &amp; GDPR Compliant
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
                            <a href="#sec-1" class="nav-link text-body-secondary rounded-3 py-2 px-3 fw-semibold"><i class="bi bi-1-circle me-2 text-primary"></i>Information We Collect</a>
                            <a href="#sec-2" class="nav-link text-body-secondary rounded-3 py-2 px-3 fw-semibold"><i class="bi bi-2-circle me-2 text-primary"></i>Account Information</a>
                            <a href="#sec-3" class="nav-link text-body-secondary rounded-3 py-2 px-3 fw-semibold"><i class="bi bi-3-circle me-2 text-primary"></i>AI Interaction Data</a>
                            <a href="#sec-4" class="nav-link text-body-secondary rounded-3 py-2 px-3 fw-semibold"><i class="bi bi-4-circle me-2 text-primary"></i>Payment Verification</a>
                            <a href="#sec-5" class="nav-link text-body-secondary rounded-3 py-2 px-3 fw-semibold"><i class="bi bi-5-circle me-2 text-primary"></i>How We Use Data</a>
                            <a href="#sec-6" class="nav-link text-body-secondary rounded-3 py-2 px-3 fw-semibold"><i class="bi bi-6-circle me-2 text-primary"></i>Cookies &amp; Tracking</a>
                            <a href="#sec-7" class="nav-link text-body-secondary rounded-3 py-2 px-3 fw-semibold"><i class="bi bi-7-circle me-2 text-primary"></i>Data Security</a>
                            <a href="#sec-8" class="nav-link text-body-secondary rounded-3 py-2 px-3 fw-semibold"><i class="bi bi-8-circle me-2 text-primary"></i>Data Retention</a>
                            <a href="#sec-9" class="nav-link text-body-secondary rounded-3 py-2 px-3 fw-semibold"><i class="bi bi-9-circle me-2 text-primary"></i>Third-Party Services</a>
                            <a href="#sec-10" class="nav-link text-body-secondary rounded-3 py-2 px-3 fw-semibold"><i class="bi bi-shield-check me-2 text-primary"></i>User Rights</a>
                            <a href="#sec-11" class="nav-link text-body-secondary rounded-3 py-2 px-3 fw-semibold"><i class="bi bi-person-heart me-2 text-primary"></i>Children's Privacy</a>
                            <a href="#sec-12" class="nav-link text-body-secondary rounded-3 py-2 px-3 fw-semibold"><i class="bi bi-arrow-repeat me-2 text-primary"></i>Policy Changes</a>
                            <a href="#sec-13" class="nav-link text-body-secondary rounded-3 py-2 px-3 fw-semibold"><i class="bi bi-envelope-paper me-2 text-primary"></i>Contact Us</a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Policy Document Column -->
            <div class="col-lg-9">
                <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 bg-body">
                    <div class="privacy-body text-body-secondary lh-lg" style="font-size: 0.98rem;">
                        
                        <!-- Introduction Box -->
                        <div class="p-4 rounded-4 mb-4 border border-primary border-opacity-25 bg-primary bg-opacity-10 text-main">
                            <div class="d-flex gap-3">
                                <i class="bi bi-info-circle-fill text-primary fs-3 flex-shrink-0 mt-1"></i>
                                <div>
                                    <h5 class="fw-bold mb-1 text-primary">Overview</h5>
                                    <p class="mb-0 small text-secondary">
                                        At StudyMe, accessible from <strong><?= get_base_url() ?></strong>, one of our main priorities is the privacy of our visitors and registered students &amp; instructors. This Privacy Policy outlines the types of information recorded and how we utilize it to power personalized AI education.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Section 1 -->
                        <div id="sec-1" class="policy-section mb-4 pb-3 border-bottom border-light-subtle">
                            <h4 class="fw-bold text-main d-flex align-items-center gap-2 mb-3">
                                <span class="badge bg-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; font-size: 0.85rem;">1</span>
                                Information We Collect
                            </h4>
                            <p>We collect information you provide directly to us, such as when you create or modify your user account, request support, communicate with instructors, or interact with our learning modules.</p>
                        </div>

                        <!-- Section 2 -->
                        <div id="sec-2" class="policy-section mb-4 pb-3 border-bottom border-light-subtle">
                            <h4 class="fw-bold text-main d-flex align-items-center gap-2 mb-3">
                                <span class="badge bg-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; font-size: 0.85rem;">2</span>
                                Account Information
                            </h4>
                            <p>When you register for an account on StudyMe, we ask for your basic identification details, including your full name, email address, telephone number, user role (Student or Instructor), academic level, and profile avatar.</p>
                        </div>

                        <!-- Section 3 -->
                        <div id="sec-3" class="policy-section mb-4 pb-3 border-bottom border-light-subtle">
                            <h4 class="fw-bold text-main d-flex align-items-center gap-2 mb-3">
                                <span class="badge bg-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; font-size: 0.85rem;">3</span>
                                Learning Activity &amp; AI Interaction Data
                            </h4>
                            <p>As an AI-powered learning platform, we collect data on your academic progress, course enrollments, lesson completions, and quiz score analytics. We also process the prompts, queries, and assignment submissions you send to our <strong>StudyMe AI Tutor</strong> to deliver personalized study explanations and continuously refine our intelligent recommendation engines.</p>
                            
                            <div class="p-3 rounded-3 bg-warning bg-opacity-10 border border-warning border-opacity-25 mt-3 mb-2 small text-main">
                                <i class="bi bi-robot text-warning me-1 fw-bold"></i> <strong>AI Data Privacy Assurance:</strong> Your AI prompts are used strictly for instructional feedback and session memory. We do not sell your personal prompts to external data brokers.
                            </div>
                        </div>

                        <!-- Section 4 -->
                        <div id="sec-4" class="policy-section mb-4 pb-3 border-bottom border-light-subtle">
                            <h4 class="fw-bold text-main d-flex align-items-center gap-2 mb-3">
                                <span class="badge bg-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; font-size: 0.85rem;">4</span>
                                Payment &amp; Verification Information
                            </h4>
                            <p>If you purchase a subscription or course via Bank Transfer or automated payment gateway, we collect transaction verification details (such as transfer date, amount, transaction reference number, and payment proof screenshots). We do not store sensitive credit card PINs or raw banking passwords on our servers.</p>
                        </div>

                        <!-- Section 5 -->
                        <div id="sec-5" class="policy-section mb-4 pb-3 border-bottom border-light-subtle">
                            <h4 class="fw-bold text-main d-flex align-items-center gap-2 mb-3">
                                <span class="badge bg-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; font-size: 0.85rem;">5</span>
                                How We Use Information
                            </h4>
                            <p>We use the information we collect to:</p>
                            <ul class="mb-0 ps-4 text-secondary">
                                <li>Provide, maintain, and personalize your StudyMe course experience.</li>
                                <li>Verify bank transfer subscription payments and issue certificate accreditation.</li>
                                <li>Analyze learning habits to generate tailored study guides and quiz recommendations.</li>
                                <li>Send administrative notices, course updates, and security alerts.</li>
                            </ul>
                        </div>

                        <!-- Section 6 -->
                        <div id="sec-6" class="policy-section mb-4 pb-3 border-bottom border-light-subtle">
                            <h4 class="fw-bold text-main d-flex align-items-center gap-2 mb-3">
                                <span class="badge bg-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; font-size: 0.85rem;">6</span>
                                Cookies &amp; Session Tracking
                            </h4>
                            <p>We use HTTP cookies and secure session tokens to keep you logged in across learning sessions, remember your theme preference (Light/Dark mode), and track platform telemetry. You can instruct your web browser to block all cookies, though certain interactive features may be limited.</p>
                        </div>

                        <!-- Section 7 -->
                        <div id="sec-7" class="policy-section mb-4 pb-3 border-bottom border-light-subtle">
                            <h4 class="fw-bold text-main d-flex align-items-center gap-2 mb-3">
                                <span class="badge bg-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; font-size: 0.85rem;">7</span>
                                Data Security
                            </h4>
                            <p>We implement 256-bit SSL encryption, database access controls, password hashing (`BCRYPT`), and automated session strict-mode checks to safeguard your personal credentials. While no internet transmission method is 100% immune, we apply commercial security best practices.</p>
                        </div>

                        <!-- Section 8 -->
                        <div id="sec-8" class="policy-section mb-4 pb-3 border-bottom border-light-subtle">
                            <h4 class="fw-bold text-main d-flex align-items-center gap-2 mb-3">
                                <span class="badge bg-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; font-size: 0.85rem;">8</span>
                                Data Retention
                            </h4>
                            <p>We retain your account data for as long as your profile remains active. If you request account closure, we securely anonymize or delete your records, except where accounting or legal compliance mandates retention.</p>
                        </div>

                        <!-- Section 9 -->
                        <div id="sec-9" class="policy-section mb-4 pb-3 border-bottom border-light-subtle">
                            <h4 class="fw-bold text-main d-flex align-items-center gap-2 mb-3">
                                <span class="badge bg-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; font-size: 0.85rem;">9</span>
                                Third-Party Services
                            </h4>
                            <p>We may integrate trusted third-party services (such as Paystack for payment processing, Google Fonts, and Cloud storage infrastructure) solely to fulfill service operations. These providers handle your data according to their respective privacy standards.</p>
                        </div>

                        <!-- Section 10 -->
                        <div id="sec-10" class="policy-section mb-4 pb-3 border-bottom border-light-subtle">
                            <h4 class="fw-bold text-main d-flex align-items-center gap-2 mb-3">
                                <span class="badge bg-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; font-size: 0.85rem;">10</span>
                                User Rights &amp; Choice
                            </h4>
                            <p>You have the right to access, export, modify, or request deletion of your personal data at any time. You can update your information inside your <a href="<?= url('student/profile.php') ?>" class="text-primary fw-semibold">Account Profile Settings</a> or by contacting our compliance desk.</p>
                        </div>

                        <!-- Section 11 -->
                        <div id="sec-11" class="policy-section mb-4 pb-3 border-bottom border-light-subtle">
                            <h4 class="fw-bold text-main d-flex align-items-center gap-2 mb-3">
                                <span class="badge bg-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; font-size: 0.85rem;">11</span>
                                Children's Privacy
                            </h4>
                            <p>Our platform is intended for university students, secondary school candidates, and accredited instructors. We do not knowingly collect personal data from children under 13 without verifiable parental/guardian consent.</p>
                        </div>

                        <!-- Section 12 -->
                        <div id="sec-12" class="policy-section mb-4 pb-3 border-bottom border-light-subtle">
                            <h4 class="fw-bold text-main d-flex align-items-center gap-2 mb-3">
                                <span class="badge bg-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; font-size: 0.85rem;">12</span>
                                Changes to this Policy
                            </h4>
                            <p>We may update our Privacy Policy periodically to reflect new features or regulations. Any changes will be posted on this page with an updated "Last Modified" date.</p>
                        </div>

                        <!-- Section 13 -->
                        <div id="sec-13" class="policy-section mb-2">
                            <h4 class="fw-bold text-main d-flex align-items-center gap-2 mb-3">
                                <span class="badge bg-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; font-size: 0.85rem;">13</span>
                                Contact &amp; Privacy Desk
                            </h4>
                            <p>If you have any questions, concerns, or data requests regarding this Privacy Policy, please reach out to us through any of our official support channels:</p>
                            
                            <div class="row g-3 mt-2">
                                <div class="col-sm-6">
                                    <div class="p-3 rounded-4 border bg-body-tertiary h-100 d-flex align-items-center gap-3">
                                        <div class="rounded-circle bg-primary bg-opacity-10 text-primary p-3 flex-shrink-0">
                                            <i class="bi bi-envelope-fill fs-4"></i>
                                        </div>
                                        <div>
                                            <small class="text-muted d-block">Email Compliance Desk</small>
                                            <a href="mailto:studyme910@gmail.com" class="fw-bold text-primary text-decoration-none">studyme910@gmail.com</a>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="p-3 rounded-4 border bg-body-tertiary h-100 d-flex align-items-center gap-3">
                                        <div class="rounded-circle bg-success bg-opacity-10 text-success p-3 flex-shrink-0">
                                            <i class="bi bi-telephone-fill fs-4"></i>
                                        </div>
                                        <div>
                                            <small class="text-muted d-block">Phone / WhatsApp Support</small>
                                            <a href="tel:09026849170" class="fw-bold text-success text-decoration-none">+234 902 684 9170</a>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="text-center pt-4">
                                <a href="<?= url('contact.php') ?>" class="btn btn-primary rounded-pill px-4 py-2 fw-bold shadow-sm">
                                    <i class="bi bi-chat-left-text me-1"></i> Visit Official Contact Page &rarr;
                                </a>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<?php include BASE_PATH . '/includes/layouts/footer.php'; ?>
