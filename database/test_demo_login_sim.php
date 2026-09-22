<?php

define('CLI_MODE', true);
require_once dirname(__DIR__) . '/config/main.php';

$pdo = getDBConnection();
$demoPassword = 'Password123!';

$tests = [
    [
        'label' => 'Student Demo (student@studyme.ng)',
        'identifier' => 'student@studyme.ng',
        'password' => $demoPassword,
        'expected_role' => 'student',
        'expected_destination' => 'student/dashboard.php'
    ],
    [
        'label' => 'University Student Demo (amina@studyme.ng)',
        'identifier' => 'amina@studyme.ng',
        'password' => $demoPassword,
        'expected_role' => 'student',
        'expected_destination' => 'student/dashboard.php'
    ],
    [
        'label' => 'Primary Teacher Demo (teacher@studyme.ng)',
        'identifier' => 'teacher@studyme.ng',
        'password' => $demoPassword,
        'expected_role' => 'teacher',
        'expected_destination' => 'teacher/dashboard.php'
    ],
    [
        'label' => 'Secondary Teacher Demo (alex@studyme.ng)',
        'identifier' => 'alex@studyme.ng',
        'password' => $demoPassword,
        'expected_role' => 'teacher',
        'expected_destination' => 'teacher/dashboard.php'
    ],
    [
        'label' => 'Super Admin Demo (admin@studyme.ng)',
        'identifier' => 'admin@studyme.ng',
        'password' => $demoPassword,
        'expected_role' => 'admin',
        'expected_destination' => 'admin/dashboard.php'
    ]
];

echo "========================================================================\n";
echo " StudyMe Platform — Full Demo Login Simulation Suite\n";
echo "========================================================================\n\n";

$passed = 0;
$failed = 0;

foreach ($tests as $t) {
    echo "Testing: {$t['label']}...\n";

    $stmt = $pdo->prepare("SELECT * FROM users WHERE (email = ? OR username = ?) LIMIT 1");
    $stmt->execute([$t['identifier'], $t['identifier']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo "   ✖ FAIL: User {$t['identifier']} not found.\n";
        $failed++;
        continue;
    }

    if (!password_verify($t['password'], $user['password'])) {
        echo "   ✖ FAIL: Password verification failed for {$t['identifier']}.\n";
        $failed++;
        continue;
    }

    if ($user['status'] !== 'active') {
        echo "   ✖ FAIL: User status is not active (Status: {$user['status']}).\n";
        $failed++;
        continue;
    }

    if ($user['role'] !== $t['expected_role']) {
        echo "   ✖ FAIL: User role mismatch (Got {$user['role']}, expected {$t['expected_role']}).\n";
        $failed++;
        continue;
    }

    $destFile = BASE_PATH . '/' . $t['expected_destination'];
    if (!file_exists($destFile)) {
        echo "   ✖ FAIL: Destination file {$t['expected_destination']} does not exist.\n";
        $failed++;
        continue;
    }

    echo "   ✔ PASS: Credentials valid, role '{$user['role']}' confirmed, destination '{$t['expected_destination']}' verified.\n";
    $passed++;
}

echo "\n========================================================================\n";
echo " DEMO LOGIN SIMULATION: {$passed} Passed, {$failed} Failed\n";
echo "========================================================================\n";

if ($failed === 0) {
    echo " All demo logins are completely functional and verified!\n";
    exit(0);
} else {
    exit(1);
}
