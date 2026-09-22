<?php

define('CLI_MODE', true);
require_once dirname(__DIR__) . '/config/main.php';
require_once BASE_PATH . '/includes/functions/users.php';

$pdo = getDBConnection();
$demoPassword = 'Password123!';
$passwordHash = password_hash($demoPassword, PASSWORD_DEFAULT);

echo "========================================================================\n";
echo " StudyMe Platform — Demo Accounts Synchronizer & Login Tester\n";
echo "========================================================================\n\n";

$demoAccounts = [
    [
        'first_name' => 'Super',
        'last_name'  => 'Admin',
        'username'   => 'admin',
        'email'      => 'admin@studyme.ng',
        'role'       => 'admin',
        'phone'      => '+2348000000001'
    ],
    [
        'first_name' => 'Dr. Marcus',
        'last_name'  => 'Okafor',
        'username'   => 'teacher_marcus',
        'email'      => 'teacher@studyme.ng',
        'role'       => 'teacher',
        'phone'      => '+2348000000002',
        'qualification' => 'PhD in Computer Science',
        'bio'        => 'Senior Tech Lecturer & Systems Architect'
    ],
    [
        'first_name' => 'Alex',
        'last_name'  => 'Johnson',
        'username'   => 'alex_stem',
        'email'      => 'alex@studyme.ng',
        'role'       => 'teacher',
        'phone'      => '+2348000000003',
        'qualification' => 'M.Sc in Physics & Applied Mathematics',
        'bio'        => 'Secondary & University STEM Educator'
    ],
    [
        'first_name' => 'David',
        'last_name'  => 'Adeyemi',
        'username'   => 'student_david',
        'email'      => 'student@studyme.ng',
        'role'       => 'student',
        'phone'      => '+2348000000004'
    ],
    [
        'first_name' => 'Amina',
        'last_name'  => 'Bello',
        'username'   => 'student_amina',
        'email'      => 'amina@studyme.ng',
        'role'       => 'student',
        'phone'      => '+2348000000005'
    ]
];

echo "1. Synchronizing Demo Accounts with valid hash for '{$demoPassword}'...\n";

foreach ($demoAccounts as $acc) {

    $stmt = $pdo->prepare("SELECT id, role FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$acc['email']]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        $userId = (int)$existing['id'];
        $stmtUpdate = $pdo->prepare("
            UPDATE users
            SET password = ?, status = 'active', email_verified_at = IFNULL(email_verified_at, NOW()), role = ?, first_name = ?, last_name = ?, username = ?
            WHERE id = ?
        ");
        $stmtUpdate->execute([$passwordHash, $acc['role'], $acc['first_name'], $acc['last_name'], $acc['username'], $userId]);
        echo "   ✔ Updated existing demo user: {$acc['email']} (User ID: {$userId}, Role: {$acc['role']})\n";
    } else {
        $stmtInsert = $pdo->prepare("
            INSERT INTO users (first_name, last_name, username, email, password, phone, role, status, email_verified_at, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'active', NOW(), NOW())
        ");
        $stmtInsert->execute([$acc['first_name'], $acc['last_name'], $acc['username'], $acc['email'], $passwordHash, $acc['phone'], $acc['role']]);
        $userId = (int)$pdo->lastInsertId();
        echo "   ✔ Created new demo user: {$acc['email']} (User ID: {$userId}, Role: {$acc['role']})\n";
    }

    if ($acc['role'] === 'student') {
        $stmtSt = $pdo->prepare("SELECT id FROM students WHERE user_id = ? LIMIT 1");
        $stmtSt->execute([$userId]);
        if (!$stmtSt->fetch()) {
            $sNum = 'STD-' . date('Y') . '-' . str_pad($userId, 4, '0', STR_PAD_LEFT);
            $pdo->prepare("INSERT INTO students (user_id, student_number) VALUES (?, ?)")
                ->execute([$userId, $sNum]);
            echo "     + Created students table record for User {$userId}\n";
        }
    } elseif ($acc['role'] === 'teacher') {
        $stmtTch = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ? LIMIT 1");
        $stmtTch->execute([$userId]);
        if (!$stmtTch->fetch()) {
            $tNum = 'TCH-' . str_pad($userId, 3, '0', STR_PAD_LEFT);
            $pdo->prepare("INSERT INTO teachers (user_id, teacher_number, qualification, bio, experience_years, status, created_at) VALUES (?, ?, ?, ?, 8, 'approved', NOW())")
                ->execute([$userId, $tNum, $acc['qualification'] ?? 'M.Sc', $acc['bio'] ?? 'Instructor']);
            echo "     + Created teachers table record for User {$userId}\n";
        }
    }
}

echo "\n2. Verifying Password Hash & Authentication Logic for all Demo Accounts...\n";

$passCount = 0;
$failCount = 0;

foreach ($demoAccounts as $acc) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$acc['email']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo "   ✖ FAIL: User {$acc['email']} not found in database!\n";
        $failCount++;
        continue;
    }

    $verified = password_verify($demoPassword, $user['password']);
    if ($verified && $user['status'] === 'active') {
        echo "   ✔ PASS: {$acc['email']} verified successfully (Role: {$user['role']})\n";
        $passCount++;
    } else {
        echo "   ✖ FAIL: {$acc['email']} failed password verification!\n";
        $failCount++;
    }
}

echo "\n========================================================================\n";
echo " DEMO LOGIN RESULTS: {$passCount} Passed, {$failCount} Failed\n";
echo "========================================================================\n";

if ($failCount === 0) {
    echo " All demo credentials verified and 100% operational!\n";
    exit(0);
} else {
    exit(1);
}
