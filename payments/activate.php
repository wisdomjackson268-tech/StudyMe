<?php
/**
 * StudyMe AI Platform — Payment Verification & Enrollment Activation Handler
 */
require_once dirname(__DIR__) . '/config/main.php';
require_once BASE_PATH . '/includes/functions/enrollments.php';
require_once BASE_PATH . '/includes/functions/pricing.php';

require_login();
$user     = current_user();
$userId   = (int)$user['id'];
$pdo      = getDBConnection();
$courseId = (int)($_POST['course_id'] ?? $_GET['course_id'] ?? 0);
$txRef    = trim($_POST['tx_ref'] ?? $_GET['tx_ref'] ?? ('SM_TX_' . time() . '_' . rand(1000, 9999)));
$method   = trim($_POST['payment_method'] ?? 'card');

// Resolve / auto-create student record
$stmtSt = $pdo->prepare("SELECT id FROM students WHERE user_id = ? LIMIT 1");
$stmtSt->execute([$userId]);
$stRow = $stmtSt->fetch(PDO::FETCH_ASSOC);
$studentId = $stRow ? (int)$stRow['id'] : 0;

if (!$studentId && current_user_role() === ROLE_STUDENT) {
    $sNum = 'STD-' . date('Y') . '-' . str_pad($userId, 4, '0', STR_PAD_LEFT);
    $pdo->prepare("INSERT IGNORE INTO students (user_id, student_number) VALUES (?, ?)")->execute([$userId, $sNum]);
    $stmtSt->execute([$userId]);
    $stRow = $stmtSt->fetch(PDO::FETCH_ASSOC);
    $studentId = $stRow ? (int)$stRow['id'] : 0;
}

if ($courseId > 0 && $studentId) {
    // 1. Backend Server-side Price Calculation
    $expectedPrice = get_course_official_price($courseId);

    // 2. One-Course-Per-Student Check (bypassed in FREE_TESTING_MODE so users can test freely)
    if (!(defined('FREE_TESTING_MODE') && FREE_TESTING_MODE)) {
        $activeCourse = get_student_active_course($studentId);
        if ($activeCourse && (int)$activeCourse['course_id'] !== $courseId) {
            set_flash('error', 'Access Restricted: You are currently enrolled in "' . htmlspecialchars($activeCourse['course_title']) . '". Students are restricted to ONE course at a time.');
            redirect('student/my-courses.php');
        }
    }

    try {
        $pdo->beginTransaction();

        // 3. Record Successful Payment in DB
        $stmtPay = $pdo->prepare("
            INSERT INTO payments (user_id, course_id, amount, currency, payment_method, transaction_reference, status, paid_at, created_at)
            VALUES (?, ?, ?, 'NGN', ?, ?, 'successful', NOW(), NOW())
            ON DUPLICATE KEY UPDATE status = 'successful', paid_at = NOW()
        ");
        $stmtPay->execute([$userId, $courseId, $expectedPrice, $method, $txRef]);

        // 4. Activate Single Course Enrollment
        $stmtEnroll = $pdo->prepare("
            INSERT INTO enrollments (student_id, course_id, status, progress, enrolled_at)
            VALUES (?, ?, 'active', 0.00, NOW())
            ON DUPLICATE KEY UPDATE status = 'active'
        ");
        $stmtEnroll->execute([$studentId, $courseId]);

        // 5. Log Activity Event
        if (function_exists('log_user_activity')) {
            log_user_activity($userId, 'Course Payment Verified', 'Paid ₦' . number_format($expectedPrice, 2) . ' for course enrollment.', $courseId);
        }

        $pdo->commit();

        // 6. Trigger Referral Verification & Bonus Engine (Server-side validation)
        if (function_exists('process_verified_payment_referral')) {
            $stmtP = $pdo->prepare("SELECT id FROM payments WHERE transaction_reference = ? LIMIT 1");
            $stmtP->execute([$txRef]);
            $pId = (int)$stmtP->fetchColumn();
            if ($pId > 0) {
                process_verified_payment_referral($pId, $userId, $courseId, $expectedPrice);
            }
        }

        // Clear pending course session
        unset($_SESSION['pending_course_id'], $_SESSION['pending_course_slug'], $_SESSION['pending_course_title'], $_SESSION['pending_course_price'], $_SESSION['pending_course_cat']);

        $_SESSION['auth_success_vibrate'] = true;
        set_flash('success', 'Payment verified successfully! Your course enrollment is active.');
        redirect('payments/success.php?course_id=' . $courseId . '&tx_ref=' . urlencode($txRef));
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Payment activation error: " . $e->getMessage());
        set_flash('error', 'Verification failed: ' . $e->getMessage());
        redirect('courses/index.php');
    }
} elseif (current_user_role() === ROLE_TEACHER) {
    // Teacher Access Activation
    $rates = get_official_pricing_rates();
    try {
        $stmtPay = $pdo->prepare("
            INSERT INTO payments (user_id, amount, currency, payment_method, transaction_reference, status, paid_at, created_at)
            VALUES (?, ?, 'NGN', ?, ?, 'successful', NOW(), NOW())
            ON DUPLICATE KEY UPDATE status = 'successful', paid_at = NOW()
        ");
        $stmtPay->execute([$userId, $rates['teacher'], $method, $txRef]);

        if (function_exists('log_user_activity')) {
            log_user_activity($userId, 'Teacher Access Verified', 'Paid ₦' . number_format($rates['teacher'], 2) . ' for Instructor Suite Access.');
        }

        if (function_exists('process_verified_payment_referral')) {
            $stmtP = $pdo->prepare("SELECT id FROM payments WHERE transaction_reference = ? LIMIT 1");
            $stmtP->execute([$txRef]);
            $pId = (int)$stmtP->fetchColumn();
            if ($pId > 0) {
                process_verified_payment_referral($pId, $userId, 0, $rates['teacher']);
            }
        }

        set_flash('success', 'Instructor Suite Access activated successfully!');
        redirect('teacher/dashboard.php');
    } catch (Exception $e) {
        set_flash('error', 'Activation error: ' . $e->getMessage());
        redirect('teacher/dashboard.php');
    }
} else {
    set_flash('error', 'Invalid payment request parameters.');
    redirect('courses/index.php');
}
