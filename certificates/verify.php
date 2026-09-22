<?php

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

<div class="py-4 py-md-5 bg-light-subtle" style="min-height: calc(100vh - 120px);">
    <div class="container py-2 py-md-4">

        <div class="text-center max-w-700 mx-auto mb-4 mb-md-5 animate-fade-in">
            <div class="rounded-circle bg-warning bg-opacity-10 text-warning d-inline-flex align-items-center justify-content-center p-3 mb-3 shadow-xs" style="width: 72px; height: 72px;">
                <i class="bi bi-patch-check-fill fs-1"></i>
            </div>
            <h1 class="display-6 display-md-5 fw-bold mb-2 text-dark">Certificate Verification</h1>
            <p class="lead text-muted fs-6 fs-md-5">Verify the authenticity of credentials, diplomas, and course completion certificates issued by StudyMe.</p>
        </div>

        <!-- SEARCH INPUT CARD -->
        <div class="row justify-content-center mb-4 mb-md-5">
            <div class="col-12 col-md-10 col-lg-8">
                <div class="card border-0 shadow-sm rounded-4 p-3 p-md-4 bg-white">
                    <form action="<?= url('certificates/verify.php') ?>" method="GET">
                        <label for="certNumber" class="form-label fw-bold small mb-2 text-dark">Enter Certificate ID or Number:</label>
                        <div class="input-group input-group-lg d-flex flex-nowrap">
                            <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-qr-code-scan"></i></span>
                            <input type="text" name="cert" id="certNumber" class="form-control border-start-0 border-end-0 py-2 py-md-3 font-monospace text-uppercase" placeholder="e.g. CERT-2026-..." value="<?= e($certNumber) ?>" required>
                            <button type="submit" class="btn btn-primary px-3 px-md-4 fw-bold shadow-sm d-inline-flex align-items-center gap-1">
                                <i class="bi bi-search"></i> <span class="d-none d-sm-inline">Verify</span>
                            </button>
                        </div>
                        <div class="form-text mt-2 small text-muted">
                            <i class="bi bi-info-circle me-1"></i> You can find the unique Certificate ID at the bottom of any official StudyMe credential.
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <?php if ($searched): ?>
            <div class="row justify-content-center">
                <div class="col-12 col-md-10 col-lg-8">
                    <?php if ($certificate): ?>
                        <div class="card border-0 shadow-lg rounded-4 overflow-hidden border-top border-4 border-success animate-fade-in bg-white">
                            <div class="bg-success bg-opacity-10 p-3 p-md-4 text-center border-bottom border-success border-opacity-25">
                                <div class="d-inline-flex align-items-center gap-2 text-success fw-bold fs-5">
                                    <i class="bi bi-shield-fill-check fs-3"></i> Valid Official Credential
                                </div>
                                <div class="text-muted small mt-1">This certificate has been issued and verified in the StudyMe database.</div>
                            </div>

                            <div class="card-body p-4 p-md-5">
                                <div class="row g-4 align-items-center">
                                    <div class="col-md-8">
                                        <div class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 rounded-pill px-3 py-1 mb-2 fw-semibold small">
                                            <i class="bi bi-check-circle-fill me-1"></i> Verified Student
                                        </div>
                                        <h3 class="fw-bold mb-1 text-dark"><?= e($certificate['student_name']) ?></h3>
                                        <p class="text-primary fw-semibold mb-3 small">Has successfully mastered curriculum requirements for:</p>
                                        <h4 class="fw-bold text-dark mb-4 p-3 bg-light rounded-3 border"><?= e($certificate['course_title']) ?></h4>

                                        <div class="row g-3 small text-muted">
                                            <div class="col-12 col-sm-6">
                                                <div class="fw-bold text-dark"><i class="bi bi-person-fill text-primary me-1"></i> Instructor:</div>
                                                <div><?= e($certificate['teacher_name']) ?></div>
                                            </div>
                                            <div class="col-12 col-sm-6">
                                                <div class="fw-bold text-dark"><i class="bi bi-calendar-check-fill text-success me-1"></i> Date Issued:</div>
                                                <div><?= date('F j, Y', strtotime($certificate['issued_at'])) ?></div>
                                            </div>
                                            <div class="col-12">
                                                <div class="fw-bold text-dark"><i class="bi bi-patch-check-fill text-warning me-1"></i> Certificate ID:</div>
                                                <div class="font-monospace text-primary fw-bold fs-6"><?= e($certificate['certificate_number']) ?></div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-4 text-center border-start-md pt-3 pt-md-0 border-light">
                                        <div class="p-3 bg-light rounded-4 d-inline-block shadow-sm mb-3">
                                            <i class="bi bi-award-fill text-warning" style="font-size: 4.5rem;"></i>
                                        </div>
                                        <div>
                                            <a href="<?= url('certificates/view.php?cert=' . urlencode($certificate['certificate_number'])) ?>" class="btn btn-primary rounded-pill px-4 py-2 w-100 fw-bold shadow-sm d-inline-flex align-items-center justify-content-center gap-2">
                                                <i class="bi bi-eye-fill"></i> <span>View Full Diploma</span>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 text-center border-top border-4 border-danger animate-fade-in bg-white">
                            <div class="rounded-circle bg-danger bg-opacity-10 text-danger d-inline-flex align-items-center justify-content-center p-3 mb-3" style="width: 72px; height: 72px;">
                                <i class="bi bi-shield-x fs-1"></i>
                            </div>
                            <h3 class="fw-bold text-danger mb-2">Certificate Not Found</h3>
                            <p class="text-muted mb-4 max-w-500 mx-auto small">
                                No credential matching <strong>&ldquo;<?= e($certNumber) ?>&rdquo;</strong> could be found in our database records. Please double-check for typing errors.
                            </p>
                            <div>
                                <a href="<?= url('certificates/verify.php') ?>" class="btn btn-outline-secondary rounded-pill px-4 py-2 fw-semibold">
                                    <i class="bi bi-arrow-counterclockwise me-1"></i> Try Another Search
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
