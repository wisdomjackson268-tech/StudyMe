<?php
/**
 * StudyMe AI Platform — Digital Certificate Viewer
 */
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

<div class="py-5 bg-light-subtle" style="min-height: calc(100vh - 120px);">
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <a href="<?= url('certificates/verify.php') ?>" class="btn btn-outline-secondary btn-sm rounded-pill">
                <i class="bi bi-arrow-left me-1"></i> Verification Search
            </a>
            <button onclick="window.print();" class="btn btn-primary btn-sm rounded-pill fw-bold">
                <i class="bi bi-printer-fill me-1"></i> Print / Save as PDF
            </button>
        </div>

        <!-- Certificate Frame -->
        <div class="card border-0 shadow-lg rounded-4 p-4 p-md-5 bg-white text-center position-relative overflow-hidden" 
             style="border: 12px double #0F172A !important; min-height: 520px;">
            
            <div class="mb-4">
                <div class="p-3 bg-warning bg-opacity-10 text-warning rounded-circle d-inline-flex mb-2 fs-1">
                    <i class="bi bi-mortarboard-fill"></i>
                </div>
                <h2 class="display-6 fw-bold text-uppercase text-dark tracking-wide mb-1">Certificate of Completion</h2>
                <p class="text-muted text-uppercase tracking-widest small">StudyMe AI-Powered Learning Platform</p>
            </div>

            <p class="text-muted fs-5 mb-2">This is to proudly certify that</p>
            <h1 class="display-5 fw-bold text-primary mb-3 font-serif"><?= e($cert['student_name']) ?></h1>
            <p class="text-muted fs-5 mb-3">has successfully mastered all curriculum lessons and passed the final evaluation for</p>
            <h2 class="fw-bold text-dark mb-4 py-2 px-4 bg-light rounded-pill d-inline-block mx-auto border"><?= e($cert['course_title']) ?></h2>

            <div class="row pt-5 mt-4 border-top align-items-end justify-content-between text-start">
                <div class="col-sm-4 text-center">
                    <div class="fw-bold text-dark border-bottom border-dark pb-1 mb-1"><?= e($cert['teacher_name']) ?></div>
                    <div class="text-muted small">Course Instructor</div>
                </div>
                
                <div class="col-sm-4 text-center my-3 my-sm-0">
                    <div class="p-2 border rounded-3 d-inline-block bg-light">
                        <i class="bi bi-patch-check-fill text-warning fs-3"></i>
                        <div class="font-monospace small fw-bold text-dark"><?= e($cert['certificate_number']) ?></div>
                    </div>
                </div>

                <div class="col-sm-4 text-center">
                    <div class="fw-bold text-dark border-bottom border-dark pb-1 mb-1"><?= date('F j, Y', strtotime($cert['issued_at'])) ?></div>
                    <div class="text-muted small">Date Issued</div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
@media print {
    body { background: white !important; }
    .navbar, footer, .btn { display: none !important; }
    .card { box-shadow: none !important; border: 4px solid #000 !important; }
}
</style>

<?php include BASE_PATH . '/includes/layouts/footer.php'; ?>
