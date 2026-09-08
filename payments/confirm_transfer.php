<?php
/**
 * StudyMe AI Platform - Confirm Bank Transfer & Course Activation
 */
require_once dirname(__DIR__) . '/config/main.php';
require_once BASE_PATH . '/includes/functions/courses.php';
require_once BASE_PATH . '/includes/functions/pricing.php';

require_login();
$user     = current_user();
$userId   = (int)$user['id'];
$role     = current_user_role();
$dashUrl  = $role === 'admin' ? 'admin/dashboard.php' : ($role === 'teacher' ? 'teacher/dashboard.php' : 'student/dashboard.php');
$courseId = (int)($_GET['course_id'] ?? $_POST['course_id'] ?? $_SESSION['pending_course_id'] ?? 0);

if (is_post()) {
    $reference    = trim($_POST['reference'] ?? ('SM_BT_' . time() . '_' . rand(100, 999)));
    $transferDate = $_POST['transfer_date'] ?? date('Y-m-d');
    $bankRef      = trim($_POST['bank_reference'] ?? '');
    $courseId     = (int)($_POST['course_id'] ?? 0);

    if ($courseId > 0) {
        $amount = get_course_official_price($courseId);
    } else {
        $amount = 10000.00;
    }

    $pdo = getDBConnection();
    
    // Check if reference already exists
    $stmt = $pdo->prepare("SELECT id FROM payments WHERE transaction_reference = ?");
    $stmt->execute([$reference]);
    if ($stmt->fetch()) {
        $reference = 'SM_BT_' . time() . '_' . rand(1000, 9999);
    }

    // Forward to activation to verify and activate
    ?>
    <!DOCTYPE html>
    <html>
    <head><title>Processing Transfer...</title></head>
    <body onload="document.forms[0].submit()">
        <form action="<?= url('payments/activate.php') ?>" method="POST">
            <input type="hidden" name="course_id" value="<?= $courseId ?>">
            <input type="hidden" name="tx_ref" value="<?= htmlspecialchars($reference) ?>">
            <input type="hidden" name="payment_method" value="bank_transfer">
        </form>
    </body>
    </html>
    <?php
    exit;
}

$course = $courseId > 0 ? get_course_by_id($courseId) : null;
$price  = $courseId > 0 ? get_course_official_price($courseId) : 10000.00;

$pageTitle = 'Bank Transfer Instructions — StudyMe';
include BASE_PATH . '/includes/layouts/header.php';
?>

<div class="py-5 bg-light-subtle d-flex align-items-center" style="min-height: calc(100vh - 120px);">
    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-lg-6">
                <div class="card border-0 shadow-lg rounded-4 p-4 p-md-5">
                    <h3 class="fw-bold mb-2">Direct Bank Transfer</h3>
                    <p class="text-muted small mb-4">Transfer the exact course amount to our dedicated ledger account below for instant verification.</p>

                    <div class="p-3 bg-light rounded-3 mb-4 border">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted small">Course:</span>
                            <span class="fw-bold text-main"><?= e($course['title'] ?? 'Course Enrollment') ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted small">Bank Name:</span>
                            <span class="fw-bold text-main">StudyMe National Bank</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted small">Account Name:</span>
                            <span class="fw-bold text-main">StudyMe Global Tech Ltd</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted small">Account Number:</span>
                            <span class="fw-bold text-primary fs-5">0123456789</span>
                        </div>
                        <div class="d-flex justify-content-between pt-2 border-top">
                            <span class="text-muted small">Amount to Transfer:</span>
                            <span class="fs-4 fw-bold text-success">₦<?= number_format($price, 2) ?></span>
                        </div>
                    </div>

                    <form method="POST">
                        <input type="hidden" name="course_id" value="<?= $courseId ?>">
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Bank Transaction Reference / Narration</label>
                            <input type="text" name="bank_reference" class="form-control py-2 rounded-3" placeholder="e.g. TRF-982342918" required>
                        </div>
                        <button type="submit" class="btn btn-primary btn-lg rounded-pill w-100 fw-bold py-3 shadow mb-3">
                            I Have Sent ₦<?= number_format($price, 0) ?> — Verify &amp; Activate
                        </button>
                        <div class="text-center">
                            <a href="<?= url('payments/checkout.php?course_id=' . $courseId) ?>" class="small text-muted text-decoration-none">
                                <i class="bi bi-arrow-left me-1"></i> Return to Payment Methods
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include BASE_PATH . '/includes/layouts/footer.php'; ?>
