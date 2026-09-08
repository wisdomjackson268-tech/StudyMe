<?php
/**
 * StudyMe AI Platform — Certificate Verification Portal
 */
require_once dirname(__DIR__) . '/config/main.php';
require_once BASE_PATH . '/includes/functions/certificates.php';

$certNumber = trim($_GET['cert'] ?? $_POST['cert_number'] ?? '');
$certificate = null;
$searched = !empty($certNumber);

if ($searched) {
    $certificate = get_certificate_by_number($certNumber);
}

include BASE_PATH . '/includes/layouts/header.php';
?>

<div class="py-5 bg-light-subtle" style="min-height: calc(100vh - 120px);">
    <div class="container py-4">
        <!-- Header -->
        <div class="text-center max-w-700 mx-auto mb-5 animate-fade-in">
            <div class="p-3 bg-warning bg-opacity-10 text-warning rounded-circle d-inline-flex mb-3">
                <i class="bi bi-patch-check-fill fs-1"></i>
            </div>
            <h1 class="display-5 fw-bold mb-2">Certificate Verification</h1>
            <p class="lead text-muted">Verify the authenticity of credentials, diplomas, and course completion certificates issued by StudyMe.</p>
        </div>

        <!-- Verification Search Form -->
        <div class="row justify-content-center mb-5">
            <div class="col-lg-7">
                <div class="card border-0 shadow-sm rounded-4 p-4">
                    <form action="<?= url('certificates/verify.php') ?>" method="GET">
                        <label for="certNumber" class="form-label fw-semibold small mb-2">Enter Certificate ID or Number:</label>
                        <div class="input-group input-group-lg">
                            <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-qr-code-scan"></i></span>
                            <input type="text" name="cert" id="certNumber" class="form-control border-start-0 border-end-0 py-3" placeholder="e.g. STUDYME-A1B2C3D4" value="<?= e($certNumber) ?>" required>
                            <button type="submit" class="btn btn-primary px-4 fw-bold" data-feedback="click">
                                <i class="bi bi-search me-1"></i> Verify
                            </button>
                        </div>
                        <div class="form-text mt-2 small text-muted">
                            You can find the Certificate ID at the bottom of any official StudyMe certificate.
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Verification Result -->
        <?php if ($searched): ?>
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <?php if ($certificate): ?>
                        <div class="card border-0 shadow-lg rounded-4 overflow-hidden border-top border-4 border-success animate-fade-in">
                            <div class="bg-success bg-opacity-10 p-4 text-center border-bottom border-success border-opacity-25">
                                <div class="d-inline-flex align-items-center gap-2 text-success fw-bold fs-5">
                                    <i class="bi bi-shield-fill-check fs-3"></i> Valid Official Credential
                                </div>
                                <div class="text-muted small mt-1">This certificate has been issued and verified in the StudyMe database.</div>
                            </div>
                            
                            <div class="card-body p-4 p-md-5">
                                <div class="row g-4 align-items-center">
                                    <div class="col-md-8">
                                        <h3 class="fw-bold mb-1 text-main"><?= e($certificate['student_name']) ?></h3>
                                        <p class="text-primary fw-semibold mb-3">Has successfully completed all requirements for:</p>
                                        <h4 class="fw-bold text-dark mb-4 p-3 bg-light rounded-3"><?= e($certificate['course_title']) ?></h4>

                                        <div class="row g-3 small text-muted">
                                            <div class="col-sm-6">
                                                <div class="fw-bold text-main">Instructor:</div>
                                                <div><?= e($certificate['teacher_name']) ?></div>
                                            </div>
                                            <div class="col-sm-6">
                                                <div class="fw-bold text-main">Date Issued:</div>
                                                <div><?= date('F j, Y', strtotime($certificate['issued_at'])) ?></div>
                                            </div>
                                            <div class="col-12">
                                                <div class="fw-bold text-main">Certificate Number:</div>
                                                <div class="font-monospace text-primary fw-bold"><?= e($certificate['certificate_number']) ?></div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-4 text-center border-start-md border-light">
                                        <div class="p-3 bg-light rounded-4 d-inline-block shadow-sm mb-3">
                                            <i class="bi bi-award text-warning" style="font-size: 5rem;"></i>
                                        </div>
                                        <div>
                                            <a href="<?= url('certificates/view.php?cert=' . urlencode($certificate['certificate_number'])) ?>" class="btn btn-primary rounded-pill px-4 btn-sm fw-bold">
                                                <i class="bi bi-eye-fill me-1"></i> View Full Diploma
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="card border-0 shadow-sm rounded-4 p-5 text-center border-top border-4 border-danger animate-fade-in">
                            <div class="p-3 bg-danger bg-opacity-10 text-danger rounded-circle d-inline-flex mb-3 fs-1">
                                <i class="bi bi-shield-x"></i>
                            </div>
                            <h3 class="fw-bold text-danger mb-2">Certificate Not Found</h3>
                            <p class="text-muted mb-4 max-w-500 mx-auto">
                                No credential matching <strong>&ldquo;<?= e($certNumber) ?>&rdquo;</strong> could be found in our database records. Please verify the code and check for typing mistakes.
                            </p>
                            <div>
                                <a href="<?= url('contact.php?subject=' . urlencode('Certificate Verification Query: ' . $certNumber)) ?>" class="btn btn-outline-secondary rounded-pill px-4 fw-bold">
                                    <i class="bi bi-headset me-1"></i> Contact Support for Assistance
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include BASE_PATH . '/includes/layouts/footer.php'; ?>
