<?php
/**
 * StudyMe AI Platform — Student Certificates Page
 */
require_once dirname(__DIR__) . '/config/main.php';
require_once BASE_PATH . '/includes/functions/certificates.php';

secure_page(ROLE_STUDENT);

$user = current_user();
$pdo = getDBConnection();
$userId = $user['id'];

// Resolve student ID
$stmt = $pdo->prepare("SELECT id FROM students WHERE user_id = ? LIMIT 1");
$stmt->execute([$userId]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);
$studentId = $student ? (int)$student['id'] : 0;

$certificates = get_student_certificates($studentId);

include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
    <div>
        <h1 class="h3 fw-bold mb-1"><i class="bi bi-award-fill text-warning me-2"></i> My Certificates &amp; Diplomas</h1>
        <p class="text-muted mb-0">View, download, or share digital credentials earned upon course completion.</p>
    </div>
    <a href="<?= url('certificates/verify.php') ?>" class="btn btn-outline-primary rounded-pill px-4 fw-bold" data-feedback="click">
        <i class="bi bi-qr-code-scan me-1"></i> Verification Portal
    </a>
</div>

<?php if (!empty($certificates)): ?>
    <div class="row g-4 mb-5">
        <?php foreach ($certificates as $cert): ?>
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 border-0 shadow-sm rounded-4 overflow-hidden hover-lift d-flex flex-column">
                    <div class="p-4 bg-gradient text-white text-center" style="background: linear-gradient(135deg, #0F172A 0%, #1E293B 100%);">
                        <i class="bi bi-patch-check-fill text-warning fs-1 mb-2"></i>
                        <h6 class="fw-bold mb-1 font-monospace text-warning"><?= e($cert['certificate_number']) ?></h6>
                        <small class="text-white-50">Issued: <?= date('M j, Y', strtotime($cert['issued_at'])) ?></small>
                    </div>

                    <div class="card-body p-4 d-flex flex-column">
                        <h5 class="fw-bold mb-2 line-clamp-2 text-main"><?= e($cert['course_title']) ?></h5>
                        <p class="text-muted small mb-4">Instructor: <?= e($cert['teacher_name']) ?></p>

                        <div class="mt-auto d-flex gap-2">
                            <a href="<?= url('certificates/view.php?cert=' . urlencode($cert['certificate_number'])) ?>" class="btn btn-primary rounded-pill w-100 py-2 fw-bold" data-feedback="click">
                                <i class="bi bi-eye-fill me-1"></i> View Diploma
                            </a>
                            <a href="<?= url('certificates/download.php?cert=' . urlencode($cert['certificate_number'])) ?>" class="btn btn-outline-secondary rounded-pill p-2" title="Print/Download PDF">
                                <i class="bi bi-download"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="card border-0 shadow-sm rounded-4 p-5 text-center my-4">
        <div class="p-3 bg-warning bg-opacity-10 text-warning rounded-circle d-inline-flex mb-3 fs-2 mx-auto">
            <i class="bi bi-award"></i>
        </div>
        <h4 class="fw-bold">No certificates earned yet</h4>
        <p class="text-muted max-w-500 mx-auto mb-4">Complete 100% of video lessons and pass required course quizzes to automatically unlock your official StudyMe certificate.</p>
        <div>
            <a href="<?= url('student/my-courses.php') ?>" class="btn btn-primary rounded-pill px-4 py-2 fw-bold" data-feedback="click">
                <i class="bi bi-play-circle-fill me-1"></i> Continue My Courses
            </a>
        </div>
    </div>
<?php endif; ?>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
