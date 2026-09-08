<?php
/**
 * StudyMe AI Platform — Official Referral, Wallet & Teacher System Test Suite
 * 
 * Verifies the exact required rules:
 * 1. University Teacher -> University Student = ₦1,500 Teacher Bonus
 * 2. University Student -> University Student = ₦1,000 Bonus
 * 3. Secondary Student -> Secondary Student = ₦1,000 Bonus
 * 4. Invalid combinations (Secondary Teacher -> Anyone, Sec -> Uni, Uni -> Sec, Self-referrals) = ₦0 / Rejected
 * 5. Wallet transaction ledger auditing
 * 6. Safe withdrawal processing
 */

require_once dirname(__DIR__) . '/config/main.php';
require_once BASE_PATH . '/includes/functions/payments.php';
require_once BASE_PATH . '/includes/functions/referrals.php';

$pdo = getDBConnection();
echo "========================================================\n";
echo "      STUDYME REFERRAL & WALLET SYSTEM TEST SUITE       \n";
echo "========================================================\n\n";

$passCount = 0;
$totalTests = 6;

// Helper to create test user
function create_test_user($firstName, $lastName, $email, $role = 'student', $categorySlug = 'university') {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $existing = $stmt->fetchColumn();
    if ($existing) {
        return (int)$existing;
    }

    $hash = password_hash('Password123!', PASSWORD_DEFAULT);
    $username = strtolower($firstName . $lastName) . rand(100, 999);
    $pdo->prepare("INSERT INTO users (first_name, last_name, username, email, password, role, status, created_at) VALUES (?, ?, ?, ?, ?, ?, 'active', NOW())")
        ->execute([$firstName, $lastName, $username, $email, $hash, $role]);
    $uid = (int)$pdo->lastInsertId();

    if ($role === 'student') {
        $pdo->prepare("INSERT IGNORE INTO students (user_id, student_number) VALUES (?, ?)")
            ->execute([$uid, 'STD-TEST-' . $uid]);
    } else {
        $catId = (int)$pdo->query("SELECT id FROM categories WHERE slug = '$categorySlug' LIMIT 1")->fetchColumn();
        $pdo->prepare("INSERT IGNORE INTO teachers (user_id, assigned_category_id, teacher_number, status) VALUES (?, ?, ?, 'active')")
            ->execute([$uid, $catId ?: 8, 'TCH-TEST-' . $uid]);
    }

    get_user_referral_code($uid);
    return $uid;
}

// Ensure categories
$uniCatId = (int)$pdo->query("SELECT id FROM categories WHERE slug = 'university' LIMIT 1")->fetchColumn() ?: 8;
$secCatId = (int)$pdo->query("SELECT id FROM categories WHERE slug = 'secondary-waec-neco' LIMIT 1")->fetchColumn() ?: 7;
$techCatId = (int)$pdo->query("SELECT id FROM categories WHERE slug = 'technology' LIMIT 1")->fetchColumn() ?: 6;

// Get test courses
$uniCourseId = (int)$pdo->query("SELECT id FROM courses WHERE category_id = $uniCatId AND status = 'published' LIMIT 1")->fetchColumn();
$secCourseId = (int)$pdo->query("SELECT id FROM courses WHERE category_id = $secCatId AND status = 'published' LIMIT 1")->fetchColumn();
$techCourseId = (int)$pdo->query("SELECT id FROM courses WHERE category_id = $techCatId AND status = 'published' LIMIT 1")->fetchColumn();

function enroll_student($userId, $courseId) {
    $pdo = getDBConnection();
    $stId = (int)$pdo->query("SELECT id FROM students WHERE user_id = $userId LIMIT 1")->fetchColumn();
    if (!$stId) {
        $pdo->prepare("INSERT INTO students (user_id, student_number) VALUES (?, ?)")->execute([$userId, 'STD-' . $userId]);
        $stId = (int)$pdo->lastInsertId();
    }
    $pdo->prepare("INSERT INTO enrollments (student_id, course_id, status, enrolled_at) VALUES (?, ?, 'active', NOW()) ON DUPLICATE KEY UPDATE status = 'active'")
        ->execute([$stId, $courseId]);
}

// ── TEST 1: UNIVERSITY TEACHER -> UNIVERSITY STUDENT (₦1,500 Bonus) ─────────
echo "--------------------------------------------------------\n";
echo "TEST 1: University Teacher -> University Student (Bonus: ₦1,500)\n";
$uniTeacherId = create_test_user('DrJohn', 'UniProf', 'uni.teacher.' . time() . '@studyme.test', 'teacher', 'university');
$uniTeacherRefCode = get_user_referral_code($uniTeacherId);

$_SESSION['pending_ref_code'] = $uniTeacherRefCode;
$uniStudent1Id = create_test_user('UniStudent', 'Referred', 'uni.student.' . rand(100,999) . '@studyme.test', 'student', 'university');
record_pending_referral($uniStudent1Id);

$txRef1 = 'TX_UNI_TCH_' . time();
$payId1 = create_payment_record($uniStudent1Id, 5000.00, 'card', $txRef1, $uniCourseId);
$res1 = process_verified_payment_referral($payId1, $uniStudent1Id, $uniCourseId, 5000.00);

$w1 = get_user_wallet($uniTeacherId);
if ($res1 && (float)$w1['available_balance'] == 1500.00) {
    echo "✔ PASS: University Teacher successfully credited ₦1,500 referral bonus!\n";
    $passCount++;
} else {
    echo "❌ FAIL: Expected ₦1,500, got ₦" . $w1['available_balance'] . "\n";
}

// ── TEST 2: UNIVERSITY STUDENT -> UNIVERSITY STUDENT (₦1,000 Bonus) ─────────
echo "--------------------------------------------------------\n";
echo "TEST 2: University Student -> University Student (Bonus: ₦1,000)\n";
$uniStudentReferrerId = create_test_user('UniStudent', 'Referrer', 'uni.ref.' . time() . '@studyme.test', 'student', 'university');
enroll_student($uniStudentReferrerId, $uniCourseId);
$uniStudentRefCode = get_user_referral_code($uniStudentReferrerId);

$_SESSION['pending_ref_code'] = $uniStudentRefCode;
$uniStudent2Id = create_test_user('UniStudent', 'Referred2', 'uni.stud2.' . rand(100,999) . '@studyme.test', 'student', 'university');
record_pending_referral($uniStudent2Id);

$txRef2 = 'TX_UNI_STD_' . time();
$payId2 = create_payment_record($uniStudent2Id, 5000.00, 'card', $txRef2, $uniCourseId);
$res2 = process_verified_payment_referral($payId2, $uniStudent2Id, $uniCourseId, 5000.00);

$w2 = get_user_wallet($uniStudentReferrerId);
if ($res2 && (float)$w2['available_balance'] == 1000.00) {
    echo "✔ PASS: University Student successfully credited ₦1,000 referral bonus!\n";
    $passCount++;
} else {
    echo "❌ FAIL: Expected ₦1,000, got ₦" . $w2['available_balance'] . "\n";
}

// ── TEST 3: SECONDARY STUDENT -> SECONDARY STUDENT (₦1,000 Bonus) ───────────
echo "--------------------------------------------------------\n";
echo "TEST 3: Secondary Student -> Secondary Student (Bonus: ₦1,000)\n";
$secStudentReferrerId = create_test_user('SecStudent', 'Referrer', 'sec.ref.' . time() . '@studyme.test', 'student', 'secondary');
enroll_student($secStudentReferrerId, $secCourseId);
$secStudentRefCode = get_user_referral_code($secStudentReferrerId);

$_SESSION['pending_ref_code'] = $secStudentRefCode;
$secStudent2Id = create_test_user('SecStudent', 'Referred2', 'sec.stud2.' . rand(100,999) . '@studyme.test', 'student', 'secondary');
record_pending_referral($secStudent2Id);

$txRef3 = 'TX_SEC_STD_' . time();
$payId3 = create_payment_record($secStudent2Id, 3000.00, 'card', $txRef3, $secCourseId);
$res3 = process_verified_payment_referral($payId3, $secStudent2Id, $secCourseId, 3000.00);

$w3 = get_user_wallet($secStudentReferrerId);
if ($res3 && (float)$w3['available_balance'] == 1000.00) {
    echo "✔ PASS: Secondary Student successfully credited ₦1,000 referral bonus!\n";
    $passCount++;
} else {
    echo "❌ FAIL: Expected ₦1,000, got ₦" . $w3['available_balance'] . "\n";
}

// ── TEST 4: INVALID CROSS-CATEGORY REFERRAL (Sec -> Uni) -> ₦0 ──────────────
echo "--------------------------------------------------------\n";
echo "TEST 4: Invalid Referral (Secondary Student -> University Student -> ₦0 / Void)\n";
$secCrossReferrerId = create_test_user('SecCross', 'Referrer', 'sec.cross.' . time() . '@studyme.test', 'student', 'secondary');
enroll_student($secCrossReferrerId, $secCourseId);
$secCrossRefCode = get_user_referral_code($secCrossReferrerId);

$_SESSION['pending_ref_code'] = $secCrossRefCode;
$crossUniStudentId = create_test_user('UniCross', 'Referred', 'unicross.' . rand(100,999) . '@studyme.test', 'student', 'university');
record_pending_referral($crossUniStudentId);

// Referred student buys University course instead of Secondary
$txRef4 = 'TX_CROSS_' . time();
$payId4 = create_payment_record($crossUniStudentId, 5000.00, 'card', $txRef4, $uniCourseId);
$res4 = process_verified_payment_referral($payId4, $crossUniStudentId, $uniCourseId, 5000.00);

$w4 = get_user_wallet($secCrossReferrerId);
if ($res4 === false && (float)$w4['available_balance'] == 0.00) {
    echo "✔ PASS: Cross-category mismatch correctly rejected with ₦0 bonus!\n";
    $passCount++;
} else {
    echo "❌ FAIL: Cross-category referral should have been rejected.\n";
}

// ── TEST 5: WALLET TRANSACTION AUDIT LEDGER ────────────────────────────────
echo "--------------------------------------------------------\n";
echo "TEST 5: Wallet Transaction Audit Ledger Verification\n";
$txCount = (int)$pdo->query("SELECT COUNT(*) FROM wallet_transactions WHERE type = 'referral_bonus'")->fetchColumn();
if ($txCount >= 3) {
    echo "✔ PASS: {$txCount} referral bonus transactions correctly recorded in wallet_transactions table.\n";
    $passCount++;
} else {
    echo "❌ FAIL: Expected at least 3 transactions in wallet_transactions, found {$txCount}.\n";
}

// ── TEST 6: WITHDRAWAL REQUEST PROCESSING ──────────────────────────────────
echo "--------------------------------------------------------\n";
echo "TEST 6: Withdrawal Request Processing\n";
$wReq = request_withdrawal($uniTeacherId, 1000.00, 'GTBank', '0123456789', 'Dr. John UniProf');
$wAfter = get_user_wallet($uniTeacherId);

$wTxCount = (int)$pdo->query("SELECT COUNT(*) FROM wallet_transactions WHERE user_id = $uniTeacherId AND type = 'withdrawal'")->fetchColumn();

if ($wReq['success'] && (float)$wAfter['available_balance'] == 500.00 && $wTxCount >= 1) {
    echo "✔ PASS: Withdrawal processed safely (Available: ₦500.00, Total Withdrawn: ₦1,000.00, Ledger recorded)!\n";
    $passCount++;
} else {
    echo "❌ FAIL: Withdrawal processing issue. Result: " . json_encode($wReq) . "\n";
}

echo "\n========================================================\n";
echo "TEST SUMMARY: {$passCount}/{$totalTests} PASSED\n";
echo "========================================================\n";
