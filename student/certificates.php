<?php

require_once dirname(__DIR__) . '/config/main.php';
require_once BASE_PATH . '/includes/functions/certificates.php';

secure_page(ROLE_STUDENT);

$user = current_user();
$pdo = getDBConnection();
$userId = (int)$user['id'];

$stmt = $pdo->prepare("SELECT id FROM students WHERE user_id = ? LIMIT 1");
$stmt->execute([$userId]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);
$studentId = $student ? (int)$student['id'] : 0;

$certificates = get_student_certificates($studentId);

include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
    <div>
        <h1 class="h3 fw-bold mb-1">
            <span class="p-2 bg-warning bg-opacity-10 text-warning rounded-3 d-inline-flex align-items-center justify-content-center me-2" style="width:38px; height:38px;">
                <i class="bi bi-award-fill fs-5"></i>
            </span>
            My Certificates &amp; Diplomas
        </h1>
        <p class="text-muted mb-0 small">View, download, print, or share official credentials earned upon curriculum mastery.</p>
    </div>
    
    <div class="d-flex gap-2 flex-wrap">
        <a href="<?= url('certificates/verify.php') ?>" class="btn btn-outline-primary rounded-pill px-4 py-2 fw-semibold d-inline-flex align-items-center gap-2">
            <i class="bi bi-qr-code-scan text-primary"></i> <span>Verification Portal</span>
        </a>
    </div>
</div>

<?php if (!empty($certificates)): ?>
    <div class="row g-3 g-md-4 mb-5">
        <?php foreach ($certificates as $cert): ?>
            <div class="col-12 col-md-6 col-lg-4">
                <div class="card h-100 border-0 shadow-sm rounded-4 overflow-hidden d-flex flex-column bg-white transition-hover">
                    <!-- Top Ribbon -->
                    <div class="p-4 text-white text-center position-relative" style="background: linear-gradient(135deg, #0F172A 0%, #1E293B 100%);">
                        <div class="rounded-circle bg-warning bg-opacity-25 text-warning d-inline-flex align-items-center justify-content-center p-3 mb-2" style="width: 60px; height: 60px;">
                            <i class="bi bi-patch-check-fill fs-2"></i>
                        </div>
                        <h6 class="fw-bold mb-1 font-monospace text-warning"><?= e($cert['certificate_number']) ?></h6>
                        <small class="text-light opacity-75 d-inline-flex align-items-center gap-1">
                            <i class="bi bi-calendar-check-fill text-warning small"></i>
                            <span>Issued <?= date('M j, Y', strtotime($cert['issued_at'])) ?></span>
                        </small>
                    </div>

                    <!-- Body -->
                    <div class="card-body p-4 d-flex flex-column">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 rounded-pill px-3 py-1 fw-semibold small">
                                <i class="bi bi-check-circle-fill me-1"></i> Verified Diploma
                            </span>
                        </div>

                        <h5 class="fw-bold mb-2 text-dark line-clamp-2"><?= e($cert['course_title']) ?></h5>
                        <p class="text-muted small mb-4 d-inline-flex align-items-center gap-1">
                            <i class="bi bi-person-fill text-primary"></i>
                            <span>Instructor: <strong><?= e($cert['teacher_name']) ?></strong></span>
                        </p>

                        <!-- Actions -->
                        <div class="mt-auto d-flex gap-2">
                            <a href="<?= url('certificates/view.php?cert=' . urlencode($cert['certificate_number'])) ?>" class="btn btn-primary rounded-pill w-100 py-2 fw-bold shadow-sm d-inline-flex align-items-center justify-content-center gap-2">
                                <i class="bi bi-eye-fill"></i> <span>View Diploma</span>
                            </a>
                            <a href="<?= url('certificates/view.php?cert=' . urlencode($cert['certificate_number'])) ?>" class="btn btn-outline-secondary rounded-circle d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;" title="Print / Download PDF">
                                <i class="bi bi-printer-fill fs-6"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 text-center my-4 bg-white">
        <div class="rounded-circle bg-warning bg-opacity-10 text-warning d-inline-flex align-items-center justify-content-center p-4 mb-3 mx-auto" style="width: 80px; height: 80px;">
            <i class="bi bi-award fs-1"></i>
        </div>
        <h4 class="fw-bold text-dark mb-2">No certificates earned yet</h4>
        <p class="text-muted max-w-500 mx-auto mb-4 small">Complete 100% of curriculum lessons and pass the final evaluation to automatically unlock your official StudyMe certificate.</p>
        <div>
            <a href="<?= url('student/my-courses.php') ?>" class="btn btn-primary rounded-pill px-4 py-2 fw-bold shadow-sm d-inline-flex align-items-center gap-2">
                <i class="bi bi-play-circle-fill"></i> <span>Continue Learning</span>
            </a>
        </div>
    </div>
<?php endif; ?>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
