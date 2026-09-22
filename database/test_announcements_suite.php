<?php

require_once dirname(__DIR__) . '/config/main.php';
require_once BASE_PATH . '/includes/functions/announcements.php';
require_once BASE_PATH . '/includes/functions/notifications.php';

$pdo = getDBConnection();
echo "=======================================================\n";
echo "    STUDYME ANNOUNCEMENT SYSTEM SPECIFICATION TESTS    \n";
echo "=======================================================\n\n";

$passCount = 0;
$totalCount = 5;

function create_ann_test_user($firstName, $lastName, $email, $role = 'student') {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT id, role, first_name, last_name, email FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($existing) {
        return $existing;
    }

    $hash = password_hash('password123', PASSWORD_DEFAULT);
    $username = strtolower($firstName . $lastName) . rand(100, 999);
    $pdo->prepare("INSERT INTO users (first_name, last_name, username, email, password, role, status, created_at) VALUES (?, ?, ?, ?, ?, ?, 'active', NOW())")
        ->execute([$firstName, $lastName, $username, $email, $hash, $role]);
    $uid = (int)$pdo->lastInsertId();

    if ($role === 'student') {
        $pdo->prepare("INSERT IGNORE INTO students (user_id, student_number) VALUES (?, ?)")
            ->execute([$uid, 'STD-ANN-' . $uid]);
    } else {
        $pdo->prepare("INSERT IGNORE INTO teachers (user_id, teacher_number) VALUES (?, ?)")
            ->execute([$uid, 'TCH-ANN-' . $uid]);
    }

    return ['id' => $uid, 'role' => $role, 'first_name' => $firstName, 'last_name' => $lastName, 'email' => $email];
}

function get_or_create_course($title, $slug, $teacherUserId) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT id FROM courses WHERE slug = ? LIMIT 1");
    $stmt->execute([$slug]);
    $cid = $stmt->fetchColumn();
    if ($cid) return (int)$cid;

    $stmtT = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ? LIMIT 1");
    $stmtT->execute([$teacherUserId]);
    $tid = $stmtT->fetchColumn() ?: 1;

    $pdo->prepare("INSERT INTO courses (teacher_id, title, slug, price, status, created_at) VALUES (?, ?, ?, 10000.00, 'published', NOW())")
        ->execute([$tid, $title, $slug]);
    return (int)$pdo->lastInsertId();
}

function enroll_test_student($userId, $courseId) {
    $pdo = getDBConnection();
    $stId = (int)$pdo->query("SELECT id FROM students WHERE user_id = $userId LIMIT 1")->fetchColumn();
    if (!$stId) {
        $pdo->prepare("INSERT INTO students (user_id, student_number) VALUES (?, ?)")->execute([$userId, 'STD-' . $userId]);
        $stId = (int)$pdo->lastInsertId();
    }
    $pdo->prepare("INSERT INTO enrollments (student_id, course_id, status, enrolled_at) VALUES (?, ?, 'active', NOW()) ON DUPLICATE KEY UPDATE status = 'active'")
        ->execute([$stId, $courseId]);
}

$teacherA = create_ann_test_user('Teacher', 'John', 'teacher.john@studyme.test', 'teacher');
$teacherB = create_ann_test_user('Teacher', 'Sarah', 'teacher.sarah@studyme.test', 'teacher');
$adminObj = create_ann_test_user('Admin', 'Super', 'admin.super@studyme.test', 'admin');

$webDevCourseId = get_or_create_course('Web Development Bootcamp', 'ann-test-web-dev', $teacherA['id']);
$cyberCourseId  = get_or_create_course('Cybersecurity Specialist', 'ann-test-cyber', $teacherB['id']);
$mathCourseId   = get_or_create_course('Mathematics Masterclass', 'ann-test-math', $teacherB['id']);

$pdo->prepare("UPDATE teachers SET assigned_course_id = ? WHERE user_id = ?")->execute([$webDevCourseId, $teacherA['id']]);
$pdo->prepare("UPDATE teachers SET assigned_course_id = ? WHERE user_id = ?")->execute([$cyberCourseId, $teacherB['id']]);

$studentWebDev = create_ann_test_user('Student', 'WebDev', 'student.webdev@studyme.test', 'student');
$studentCyber  = create_ann_test_user('Student', 'Cyber', 'student.cyber@studyme.test', 'student');
$studentMath   = create_ann_test_user('Student', 'Math', 'student.math@studyme.test', 'student');

enroll_test_student($studentWebDev['id'], $webDevCourseId);
enroll_test_student($studentCyber['id'], $cyberCourseId);
enroll_test_student($studentMath['id'], $mathCourseId);

echo "--------------------------------------------------------\n";
echo "TEST 1: Teacher A (Web Dev) Announcement Targeting\n";

$res1 = create_announcement_entry(
    "New Web Development lesson available.",
    "Module 5 is live now for all Web Dev students.",
    $teacherA['id'],
    'teacher',
    'course',
    $webDevCourseId,
    'normal',
    'published'
);

$webDevAnns = get_user_announcements($studentWebDev['id'], 'student');
$cyberAnns  = get_user_announcements($studentCyber['id'], 'student');

$webDevHasIt = false;
foreach ($webDevAnns as $a) {
    if ($a['title'] === "New Web Development lesson available.") $webDevHasIt = true;
}

$cyberHasIt = false;
foreach ($cyberAnns as $a) {
    if ($a['title'] === "New Web Development lesson available.") $cyberHasIt = true;
}

if ($res1['success'] && $webDevHasIt && !$cyberHasIt) {
    echo "✔ PASS: Web Development students RECEIVED announcement; Cybersecurity students DID NOT receive it.\n";
    $passCount++;
} else {
    echo "❌ FAIL: Targeting failed. WebDev: " . ($webDevHasIt ? 'Yes' : 'No') . " | Cyber: " . ($cyberHasIt ? 'Yes' : 'No') . "\n";
}

echo "--------------------------------------------------------\n";
echo "TEST 2: Admin Broadcast to All Users\n";

$res2 = create_announcement_entry(
    "Welcome to StudyMe Platform Wide!",
    "Scheduled maintenance is complete and all systems are operational.",
    $adminObj['id'],
    'admin',
    'all',
    null,
    'important',
    'published'
);

$webDevAnns2 = get_user_announcements($studentWebDev['id'], 'student');
$tchAnns2    = get_user_announcements($teacherA['id'], 'teacher');

$stReceived = false;
foreach ($webDevAnns2 as $a) {
    if ($a['title'] === "Welcome to StudyMe Platform Wide!") $stReceived = true;
}

$tchReceived = false;
foreach ($tchAnns2 as $a) {
    if ($a['title'] === "Welcome to StudyMe Platform Wide!") $tchReceived = true;
}

if ($res2['success'] && $stReceived && $tchReceived) {
    echo "✔ PASS: Admin announcement successfully delivered to all Students and Teachers.\n";
    $passCount++;
} else {
    echo "❌ FAIL: Admin broadcast failed. Student: " . ($stReceived ? 'Yes' : 'No') . " | Teacher: " . ($tchReceived ? 'Yes' : 'No') . "\n";
}

echo "--------------------------------------------------------\n";
echo "TEST 3: Admin Course Targeting (Mathematics Course Only)\n";

$res3 = create_announcement_entry(
    "New Mathematics resources available.",
    "Formulas and past question solutions have been added to the Mathematics module.",
    $adminObj['id'],
    'admin',
    'course',
    $mathCourseId,
    'normal',
    'published'
);

$mathAnns3   = get_user_announcements($studentMath['id'], 'student');
$webDevAnns3 = get_user_announcements($studentWebDev['id'], 'student');

$mathReceived = false;
foreach ($mathAnns3 as $a) {
    if ($a['title'] === "New Mathematics resources available.") $mathReceived = true;
}

$webDevReceived3 = false;
foreach ($webDevAnns3 as $a) {
    if ($a['title'] === "New Mathematics resources available.") $webDevReceived3 = true;
}

if ($res3['success'] && $mathReceived && !$webDevReceived3) {
    echo "✔ PASS: Mathematics students RECEIVED announcement; Web Dev students DID NOT receive it.\n";
    $passCount++;
} else {
    echo "❌ FAIL: Course targeting failed. Math: " . ($mathReceived ? 'Yes' : 'No') . " | WebDev: " . ($webDevReceived3 ? 'Yes' : 'No') . "\n";
}

echo "--------------------------------------------------------\n";
echo "TEST 4: Student Creation Attempt (Must be ACCESS DENIED)\n";

$res4 = create_announcement_entry(
    "Unauthorized Student Post",
    "Students should never be allowed to publish announcements.",
    $studentWebDev['id'],
    'student',
    'all'
);

if (!$res4['success'] && strpos($res4['error'], 'ACCESS DENIED') !== false) {
    echo "✔ PASS: Student creation blocked by server: {$res4['error']}\n";
    $passCount++;
} else {
    echo "❌ FAIL: Student creation was not blocked!\n";
}

echo "--------------------------------------------------------\n";
echo "TEST 5: Teacher Course Spoofing Attempt (Must be REJECTED)\n";

$res5 = create_announcement_entry(
    "Spoofed Teacher Announcement",
    "Teacher A trying to post into Cybersecurity course without authorization.",
    $teacherA['id'],
    'teacher',
    'course',
    $cyberCourseId
);

if (!$res5['success'] && strpos($res5['error'], 'REQUEST REJECTED BY SERVER') !== false) {
    echo "✔ PASS: Teacher course spoofing rejected by server: {$res5['error']}\n";
    $passCount++;
} else {
    echo "❌ FAIL: Teacher course spoofing was not rejected!\n";
}

echo "\n=======================================================\n";
echo "ANNOUNCEMENT SPECIFICATION RESULTS: $passCount / $totalCount Passed!\n";
echo "=======================================================\n";
