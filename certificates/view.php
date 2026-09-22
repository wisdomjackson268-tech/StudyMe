<?php

require_once dirname(__DIR__) . '/config/main.php';
require_once BASE_PATH . '/includes/functions/certificates.php';

$certNumber = trim($_GET['cert'] ?? '');
if (empty($certNumber)) {
    redirect('certificates/verify.php');
}

$cert = get_certificate_by_number($certNumber);
if (!$cert) {
    redirect('certificates/verify.php');
}

include BASE_PATH . '/includes/layouts/header.php';
?>

<style>
/* ==========================================================================
   PREMIUM RESPONSIVE CERTIFICATE STYLES (MOBILE, IPAD, DESKTOP, PRINT)
   ========================================================================== */

.cert-page-wrapper {
    min-height: calc(100vh - 120px);
    background: linear-gradient(180deg, #F8FAFC 0%, #EEF2F6 100%);
}

.cert-frame-container {
    background: #FFFFFF;
    border: 10px double #0F172A;
    border-radius: 1.5rem;
    box-shadow: 0 20px 40px -15px rgba(15, 23, 42, 0.12), 0 0 0 1px rgba(15, 23, 42, 0.05);
    position: relative;
    overflow: hidden;
}

/* Elegant Corner Ornaments */
.cert-corner-ornament {
    position: absolute;
    width: 48px;
    height: 48px;
    border-color: #D97706;
    border-style: solid;
    pointer-events: none;
    opacity: 0.85;
}
.cert-corner-tl { top: 12px; left: 12px; border-width: 3px 0 0 3px; border-top-left-radius: 8px; }
.cert-corner-tr { top: 12px; right: 12px; border-width: 3px 3px 0 0; border-top-right-radius: 8px; }
.cert-corner-bl { bottom: 12px; left: 12px; border-width: 0 0 3px 3px; border-bottom-left-radius: 8px; }
.cert-corner-br { bottom: 12px; right: 12px; border-width: 0 3px 3px 0; border-bottom-right-radius: 8px; }

/* Golden Seal Badge */
.cert-seal-badge {
    width: 76px;
    height: 76px;
    background: linear-gradient(135deg, #F59E0B 0%, #D97706 50%, #B45309 100%);
    color: #FFFFFF;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 10px 20px -5px rgba(217, 119, 6, 0.4), inset 0 0 0 3px rgba(255, 255, 255, 0.4);
    position: relative;
}

.cert-seal-badge i {
    font-size: 2.25rem;
    filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.2));
}

/* Typography Responsive Scaling */
.cert-main-title {
    font-size: clamp(1.4rem, 4vw, 2.4rem);
    font-weight: 800;
    letter-spacing: 0.08em;
    color: #0F172A;
}

.cert-student-name {
    font-size: clamp(1.6rem, 5vw, 2.75rem);
    font-weight: 800;
    color: #1E40AF;
    font-family: Georgia, 'Times New Roman', serif;
    text-shadow: 0 1px 2px rgba(30, 64, 175, 0.1);
    word-break: break-word;
}

.cert-course-badge {
    font-size: clamp(1.05rem, 3vw, 1.4rem);
    font-weight: 700;
    color: #0F172A;
    background: #F1F5F9;
    border: 1px solid #CBD5E1;
    border-radius: 50px;
    padding: 0.6rem 1.5rem;
    display: inline-block;
    max-width: 100%;
    word-break: break-word;
}

/* Responsive Footer / Signature Section */
.cert-signature-line {
    border-bottom: 2px solid #0F172A;
    font-weight: 700;
    color: #0F172A;
    padding-bottom: 4px;
    margin-bottom: 4px;
}

.cert-auth-box {
    background: #F8FAFC;
    border: 1px solid #E2E8F0;
    border-radius: 12px;
    padding: 0.75rem 1.25rem;
}

/* Mobile & iPad Tablet Adjustments */
@media (max-width: 767.98px) {
    .cert-frame-container {
        border-width: 6px double #0F172A !important;
        padding: 1.5rem 1rem !important;
        border-radius: 1rem;
    }
    .cert-corner-ornament {
        width: 28px;
        height: 28px;
        top: 6px;
        left: 6px;
    }
    .cert-corner-tr { right: 6px; }
    .cert-corner-bl { bottom: 6px; }
    .cert-corner-br { bottom: 6px; right: 6px; }
    .cert-seal-badge {
        width: 60px;
        height: 60px;
    }
    .cert-seal-badge i {
        font-size: 1.75rem;
    }
    .cert-signatures-row {
        gap: 1.5rem;
    }
}

/* iPad & Tablet Specific */
@media (min-width: 768px) and (max-width: 1024px) {
    .cert-frame-container {
        border-width: 8px double #0F172A !important;
        padding: 2.5rem 2rem !important;
    }
}

/* Print Optimization */
@media print {
    body {
        background: #FFFFFF !important;
        margin: 0;
        padding: 0;
    }
    .cert-page-wrapper {
        background: transparent !important;
        min-height: auto !important;
        padding: 0 !important;
    }
    .cert-action-bar, .navbar, footer {
        display: none !important;
    }
    .cert-frame-container {
        box-shadow: none !important;
        border: 4px solid #0F172A !important;
        page-break-inside: avoid;
        margin: 0 auto;
        max-width: 100% !important;
    }
}
</style>

<div class="cert-page-wrapper py-3 py-md-5">
    <div class="container py-2 py-md-4">
        
        <!-- TOP ACTION BAR -->
        <div class="cert-action-bar d-flex justify-content-between align-items-center mb-3 mb-md-4 flex-wrap gap-2">
            <a href="<?= url('certificates/verify.php?cert=' . urlencode($cert['certificate_number'])) ?>" class="btn btn-outline-secondary btn-sm rounded-pill px-3 py-2 fw-semibold d-inline-flex align-items-center gap-1">
                <i class="bi bi-shield-check text-success fs-6"></i> <span>Verify Certificate</span>
            </a>
            
            <div class="d-flex gap-2">
                <button type="button" onclick="window.print();" class="btn btn-primary btn-sm rounded-pill px-4 py-2 fw-bold shadow-sm d-inline-flex align-items-center gap-2">
                    <i class="bi bi-printer-fill fs-6"></i> <span>Print / Save as PDF</span>
                </button>
            </div>
        </div>

        <!-- MAIN CERTIFICATE FRAME -->
        <div class="cert-frame-container p-4 p-md-5 text-center">
            <!-- Corner Accents -->
            <div class="cert-corner-ornament cert-corner-tl"></div>
            <div class="cert-corner-ornament cert-corner-tr"></div>
            <div class="cert-corner-ornament cert-corner-bl"></div>
            <div class="cert-corner-ornament cert-corner-br"></div>

            <!-- HEADER SECTION -->
            <div class="mb-3 mb-md-4">
                <div class="cert-seal-badge mb-2 mb-md-3">
                    <i class="bi bi-award-fill"></i>
                </div>
                <h2 class="cert-main-title text-uppercase mb-1">Certificate of Completion</h2>
                <div class="d-inline-flex align-items-center gap-2 text-muted text-uppercase small fw-bold tracking-widest">
                    <i class="bi bi-shield-fill-check text-primary"></i>
                    <span>StudyMe Official Credential</span>
                </div>
            </div>

            <!-- RECIPIENT BODY -->
            <p class="text-muted fs-6 fs-md-5 mb-1">This is to officially and proudly certify that</p>
            <h1 class="cert-student-name my-2 my-md-3"><?= e($cert['student_name']) ?></h1>
            <p class="text-muted fs-6 fs-md-5 mb-3 px-2 max-w-700 mx-auto">
                has successfully fulfilled all curriculum requirements, completed required coursework, and demonstrated mastery in
            </p>
            
            <div class="mb-4 mb-md-5 px-2">
                <div class="cert-course-badge">
                    <i class="bi bi-mortarboard-fill text-warning me-2"></i>
                    <?= e($cert['course_title']) ?>
                </div>
            </div>

            <!-- FOOTER / SIGNATURES / AUTHENTICATION -->
            <div class="row pt-4 pt-md-5 border-top border-2 align-items-center justify-content-between text-center cert-signatures-row">
                
                <!-- Instructor Signature -->
                <div class="col-12 col-sm-4 mb-3 mb-sm-0">
                    <div class="cert-signature-line mx-auto" style="max-width: 220px;">
                        <?= e($cert['teacher_name']) ?>
                    </div>
                    <div class="text-muted small fw-semibold">
                        <i class="bi bi-person-check-fill text-primary me-1"></i>Course Instructor
                    </div>
                </div>

                <!-- Center Digital Seal & Certificate ID -->
                <div class="col-12 col-sm-4 mb-3 mb-sm-0">
                    <div class="cert-auth-box d-inline-block text-center shadow-xs">
                        <div class="d-flex align-items-center justify-content-center gap-1 mb-1">
                            <i class="bi bi-patch-check-fill text-warning fs-5"></i>
                            <span class="small fw-bold text-dark text-uppercase">Verified Credential</span>
                        </div>
                        <div class="font-monospace small fw-bold text-primary">
                            <?= e($cert['certificate_number']) ?>
                        </div>
                    </div>
                </div>

                <!-- Date Issued -->
                <div class="col-12 col-sm-4">
                    <div class="cert-signature-line mx-auto" style="max-width: 220px;">
                        <?= date('F j, Y', strtotime($cert['issued_at'])) ?>
                    </div>
                    <div class="text-muted small fw-semibold">
                        <i class="bi bi-calendar-check-fill text-success me-1"></i>Date Issued
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<?php include BASE_PATH . '/includes/layouts/footer.php'; ?>
