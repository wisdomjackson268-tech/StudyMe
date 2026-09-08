<?php
/**
 * StudyMe AI Platform — Official Course Confirmation & Payment Checkout
 */
require_once dirname(__DIR__) . '/config/main.php';
require_once BASE_PATH . '/includes/functions/enrollments.php';
require_once BASE_PATH . '/includes/functions/pricing.php';

require_login();
$user     = current_user();
$userId   = (int)$user['id'];
$userName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
$role     = current_user_role();
$pdo      = getDBConnection();

$courseId = (int)($_GET['course_id'] ?? $_SESSION['pending_course_id'] ?? 0);
$planSlug = trim($_GET['plan'] ?? '');

$course        = null;
$categoryName  = 'Academic Course';
$expectedPrice = 0.00;
$itemName      = 'StudyMe Course Enrollment';
$itemDesc      = '';

if ($courseId > 0) {
    // 1. Resolve student record & check active course limit
    $stmtSt = $pdo->prepare("SELECT id FROM students WHERE user_id = ? LIMIT 1");
    $stmtSt->execute([$userId]);
    $stRow = $stmtSt->fetch(PDO::FETCH_ASSOC);
    $studentId = $stRow ? (int)$stRow['id'] : 0;

    if ($studentId) {
        $activeCourse = get_student_active_course($studentId);
        if ($activeCourse && (int)$activeCourse['course_id'] !== $courseId) {
            set_flash('error', 'Access Restricted: You already have an active enrollment in "' . htmlspecialchars($activeCourse['course_title']) . '". Students are restricted to ONE course at a time.');
            redirect('student/my-courses.php');
        }
    }

    // 2. Fetch course from database
    $stmtC = $pdo->prepare("
        SELECT c.*, cat.name AS category_name, cat.slug AS category_slug,
               CONCAT(u.first_name, ' ', u.last_name) AS teacher_name
        FROM courses c
        LEFT JOIN categories cat ON c.category_id = cat.id
        JOIN teachers t ON c.teacher_id = t.id
        JOIN users u ON t.user_id = u.id
        WHERE c.id = ? AND c.status = 'published' LIMIT 1
    ");
    $stmtC->execute([$courseId]);
    $course = $stmtC->fetch(PDO::FETCH_ASSOC);

    if (!$course) {
        set_flash('error', 'Course not found or currently unavailable.');
        redirect('courses/index.php');
    }

    // 3. Server-side price calculation strictly from database
    $expectedPrice = get_course_official_price($courseId);
    $categoryName  = $course['category_name'] ?: 'Technology';
    $itemName      = $course['title'];
    $itemDesc      = 'Instructor: ' . $course['teacher_name'] . ' | Category: ' . $categoryName;
} else {
    // Teacher Access Suite
    $rates = get_official_pricing_rates();
    if ($planSlug === 'teacher' || $role === ROLE_TEACHER) {
        $expectedPrice = $rates['teacher'];
        $itemName      = 'Instructor Suite License';
        $categoryName  = 'Teacher & Professional';
        $itemDesc      = 'Official StudyMe Instructor Publishing, Student Analytics & Course Creation License';
    } else {
        $expectedPrice = $rates['tech'];
        $itemName      = 'Technology Course Enrollment';
        $categoryName  = 'Technology';
        $itemDesc      = 'Single Course Academic Learning Access with 24/7 AI Tutor';
    }
}

// Generate unique transaction reference
$txRef = 'SM_TX_' . time() . '_' . rand(1000, 9999);

$pageTitle = 'Confirm Enrollment & Payment — StudyMe';
include BASE_PATH . '/includes/layouts/header.php';
?>

<div class="py-5 bg-light-subtle" style="min-height: calc(100vh - 120px);">
    <div class="container py-4">
        <!-- Progress Steps -->
        <div class="row justify-content-center mb-5">
            <div class="col-lg-8">
                <div class="d-flex justify-content-between position-relative">
                    <div class="text-center" style="width: 33%;">
                        <div class="rounded-circle bg-success text-white d-inline-flex align-items-center justify-content-center fw-bold mb-2 shadow" style="width: 44px; height: 44px;">✓</div>
                        <div class="small fw-bold text-success">1. Select Course</div>
                    </div>
                    <div class="text-center" style="width: 33%;">
                        <div class="rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center fw-bold mb-2 shadow" style="width: 44px; height: 44px;">2</div>
                        <div class="small fw-bold text-primary">2. Confirm &amp; Pay</div>
                    </div>
                    <div class="text-center" style="width: 33%;">
                        <div class="rounded-circle bg-secondary bg-opacity-25 text-muted d-inline-flex align-items-center justify-content-center fw-bold mb-2" style="width: 44px; height: 44px;">3</div>
                        <div class="small text-muted">3. Course Activated</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 justify-content-center">
            <!-- Left: Confirmation Summary Card (Part 17 & 20) -->
            <div class="col-lg-5">
                <div class="card border-0 shadow-lg rounded-4 p-4 p-md-5 h-100 d-flex flex-column">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 py-1 fw-bold">
                            Confirm Your Enrollment
                        </span>
                        <span class="badge bg-success rounded-pill px-3 py-1">Verified Server Price</span>
                    </div>

                    <h3 class="fw-bold mb-2 text-main"><?= e($itemName) ?></h3>
                    <p class="text-muted small mb-4"><?= e($itemDesc) ?></p>
                    
                    <!-- Details Breakdown Box -->
                    <div class="p-4 bg-light rounded-4 mb-4 border border-subtle">
                        <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                            <span class="text-muted small">Student Name:</span>
                            <span class="fw-bold text-main"><?= e($userName ?: 'Student') ?></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                            <span class="text-muted small">Course Category:</span>
                            <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-2"><?= e($categoryName) ?></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                            <span class="text-muted small">Access Period:</span>
                            <span class="fw-semibold text-main small">Lifetime Course Access</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center pt-2">
                            <span class="text-muted small">Official Price:</span>
                            <span class="fs-4 fw-bold text-success">₦<?= number_format($expectedPrice, 2) ?></span>
                        </div>
                    </div>

                    <h6 class="fw-bold mb-2 small text-uppercase text-muted">What's Included:</h6>
                    <ul class="list-unstyled small text-muted d-flex flex-column gap-2 mb-4">
                        <li><i class="bi bi-shield-check text-success me-2"></i> Dedicated access to <?= e($itemName) ?></li>
                        <li><i class="bi bi-robot text-warning me-2"></i> 24/7 AI Tutor Guidance &amp; Practice Explanations</li>
                        <li><i class="bi bi-patch-check-fill text-info me-2"></i> Verifiable Certificate upon completion</li>
                        <li><i class="bi bi-graph-up-arrow text-primary me-2"></i> Real-time progress &amp; quiz telemetry</li>
                    </ul>

                    <div class="mt-auto pt-3 border-top d-flex justify-content-between align-items-center">
                        <a href="<?= url('courses/index.php') ?>" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                            <i class="bi bi-arrow-left me-1"></i> Change Course
                        </a>
                        <div class="text-end">
                            <div class="text-muted" style="font-size:0.75rem;">Total Amount Due:</div>
                            <span class="fs-4 fw-bold text-main">₦<?= number_format($expectedPrice, 2) ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right: Payment Method & Verification Form -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-lg rounded-4 p-4 p-md-5">
                    <h4 class="fw-bold mb-2">Complete Payment</h4>
                    <p class="text-muted small mb-4">Select your preferred payment channel. Access is activated upon server verification.</p>

                    <?php if (defined('FREE_TESTING_MODE') && FREE_TESTING_MODE): ?>
                    <div class="alert alert-success border-success border-opacity-25 rounded-4 p-3 mb-4 shadow-sm">
                        <div class="d-flex align-items-center gap-3">
                            <div class="p-2 bg-success text-white rounded-circle flex-shrink-0">
                                <i class="bi bi-shield-check fs-4"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="fw-bold small text-success-emphasis">Free Testing Mode Active</div>
                                <div class="text-muted" style="font-size: 0.8rem;">Instant activation is available without payment, or you may test payment channels below.</div>
                            </div>
                            <?php if ($courseId > 0): ?>
                                <a href="<?= url('payments/activate.php?course_id=' . $courseId . '&payment_method=free_test') ?>" class="btn btn-sm btn-success rounded-pill fw-bold text-nowrap">
                                    Activate Free &rarr;
                                </a>
                            <?php elseif ($role === ROLE_TEACHER): ?>
                                <a href="<?= url('payments/activate.php?payment_method=free_test') ?>" class="btn btn-sm btn-success rounded-pill fw-bold text-nowrap">
                                    Activate Teacher &rarr;
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <form action="<?= url('payments/activate.php') ?>" method="POST" id="checkoutForm">
                        <input type="hidden" name="course_id" value="<?= $courseId ?>">
                        <input type="hidden" name="tx_ref" value="<?= $txRef ?>">
                        <input type="hidden" name="plan_slug" value="<?= e($planSlug) ?>">

                        <!-- Option 1: Card / Instant -->
                        <div class="p-3 rounded-3 border mb-3 bg-light d-flex align-items-center gap-3">
                            <input class="form-check-input mt-0 flex-shrink-0" type="radio" name="payment_method" id="payMethodCard" value="card" checked>
                            <label class="form-check-label flex-grow-1 cursor-pointer" for="payMethodCard">
                                <div class="fw-bold text-main small"><i class="bi bi-credit-card-fill text-primary me-2"></i>Instant Online Payment (Debit / Credit Card)</div>
                                <small class="text-muted">Instant verification and course activation</small>
                            </label>
                        </div>

                        <!-- Option 2: Bank Transfer -->
                        <div class="p-3 rounded-3 border mb-4 bg-light d-flex align-items-center gap-3">
                            <input class="form-check-input mt-0 flex-shrink-0" type="radio" name="payment_method" id="payMethodBank" value="bank_transfer">
                            <label class="form-check-label flex-grow-1 cursor-pointer" for="payMethodBank">
                                <div class="fw-bold text-main small"><i class="bi bi-bank text-success me-2"></i>Direct Bank Transfer Verification</div>
                                <small class="text-muted">Direct transfer to StudyMe ledger account</small>
                            </label>
                        </div>

                        <div class="p-3 rounded-3 bg-warning bg-opacity-10 border border-warning border-opacity-25 mb-4 text-warning-emphasis small">
                            <i class="bi bi-shield-lock-fill me-1"></i>
                            <strong>Security Verification:</strong> The server computes the official price from the database record and validates transaction references before enrollment activation.
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg rounded-pill w-100 fw-bold py-3 shadow mb-3" data-feedback="success">
                            Proceed to Payment — ₦<?= number_format($expectedPrice, 0) ?> <i class="bi bi-arrow-right ms-1"></i>
                        </button>

                        <div class="text-center">
                            <a href="<?= url('courses/index.php') ?>" class="text-muted small text-decoration-none">
                                <i class="bi bi-arrow-left me-1"></i> Return to Course Catalog
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include BASE_PATH . '/includes/layouts/footer.php'; ?>
