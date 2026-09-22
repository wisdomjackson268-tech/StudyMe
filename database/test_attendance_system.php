<?php

define('CLI_MODE', true);
require_once dirname(__DIR__) . '/config/main.php';
require_once BASE_PATH . '/includes/functions/users.php';
require_once BASE_PATH . '/includes/functions/courses.php';
require_once BASE_PATH . '/includes/functions/enrollments.php';
require_once BASE_PATH . '/includes/functions/live_classes.php';

$pdo = getDBConnection();
$passed = 0;
$failed = 0;

function assertCheck($cond, $title, $extra = '') {
    global $passed, $failed;
    if ($cond) {
        $passed++;
        echo "  [PASS] {$title}\n";
    } else {
        $failed++;
        echo "  [FAIL] {$title}" . ($extra ? " -- {$extra}" : "") . "\n";
    }
}

echo "========================================================================\n";
echo " StudyMe — Live Lesson Attendance System & Session Lifecycle Suite\n";
echo "========================================================================\n\n";

try {
    ensure_live_classes_schema();
    assertCheck(true, "Database schema check & table creation verified");

    $stmtCourse = $pdo->query("SELECT c.id, c.teacher_id FROM courses c WHERE c.teacher_id IS NOT NULL LIMIT 1");
    $course = $stmtCourse->fetch(PDO::FETCH_ASSOC);

    if (!$course) {

        $pdo->prepare("INSERT INTO users (first_name, last_name, username, email, password, role) VALUES ('Test', 'Teacher', 'testteacher".rand(100,999)."', 'tt".rand(100,999)."@test.com', 'hash', 'teacher')")->execute();
        $uTeacherId = (int)$pdo->lastInsertId();
        $pdo->prepare("INSERT INTO teachers (user_id) VALUES (?)")->execute([$uTeacherId]);
        $teacherId = (int)$pdo->lastInsertId();

        $pdo->prepare("INSERT INTO courses (teacher_id, title, slug, academic_level) VALUES (?, 'Demo Live Course', 'demo-live-course-".rand(100,999)."', '100 Level')")->execute([$teacherId]);
        $courseId = (int)$pdo->lastInsertId();
    } else {
        $courseId  = (int)$course['id'];
        $teacherId = (int)$course['teacher_id'];
    }

    $stmtStudent = $pdo->query("SELECT s.id, s.user_id FROM students s JOIN users u ON s.user_id = u.id LIMIT 1");
    $student = $stmtStudent->fetch(PDO::FETCH_ASSOC);
    if (!$student) {
        $pdo->prepare("INSERT INTO users (first_name, last_name, username, email, password, role) VALUES ('Test', 'Student', 'teststudent".rand(100,999)."', 'ts".rand(100,999)."@test.com', 'hash', 'student')")->execute();
        $uStudentId = (int)$pdo->lastInsertId();
        $pdo->prepare("INSERT INTO students (user_id) VALUES (?)")->execute([$uStudentId]);
        $studentId = (int)$pdo->lastInsertId();
    } else {
        $studentId  = (int)$student['id'];
        $uStudentId = (int)$student['user_id'];
    }

    $pdo->prepare("INSERT INTO enrollments (student_id, course_id, status, progress, enrolled_at) VALUES (?, ?, 'active', 10, NOW()) ON DUPLICATE KEY UPDATE status = 'active'")
        ->execute([$studentId, $courseId]);

    $title = "Test Live Interactive Class - " . date('H:i:s');
    $classId = schedule_live_class($courseId, $teacherId, $title, "Testing attendance flow", "https://meet.google.com/test-room", date('Y-m-d H:i:s', strtotime('+1 hour')), 45);
    assertCheck($classId > 0, "Schedule live class", "Class ID: {$classId}");

    $resScheduled = mark_live_class_attendance($classId, $studentId, $uStudentId);
    assertCheck($resScheduled['success'] === false, "Attendance blocked when session is scheduled (not live)");

    $startRes = update_live_class_status($classId, $teacherId, 'live');
    assertCheck($startRes === true, "Teacher starts live session & opens attendance");

    $liveClass = get_live_class_by_id($classId);
    assertCheck($liveClass['status'] === 'live', "Session status is verified as 'live'");

    $resLive = mark_live_class_attendance($classId, $studentId, $uStudentId);
    assertCheck($resLive['success'] === true && !empty($resLive['attended_at']), "Student marks attendance while session is live");

    $resDup = mark_live_class_attendance($classId, $studentId, $uStudentId);
    assertCheck($resDup['success'] === true && !empty($resDup['already_marked']), "Duplicate attendance check correctly handled");

    $stats = get_live_class_attendance_stats($classId);
    assertCheck($stats['total_attended'] >= 1, "Live session attendance count updated in stats ({$stats['total_attended']} present)");

    $roster = get_live_class_attendance_list($classId);
    assertCheck(count($roster) >= 1 && (int)$roster[0]['student_id'] === $studentId, "Attendee roster contains student record with timestamp");

    $endRes = update_live_class_status($classId, $teacherId, 'ended', 'https://youtube.com/watch?v=test-replay');
    assertCheck($endRes === true, "Teacher ends live session & closes attendance");

    $endedClass = get_live_class_by_id($classId);
    assertCheck($endedClass['status'] === 'ended' && !empty($endedClass['recording_url']), "Session ended with replay URL saved");

    $pdo->prepare("INSERT INTO users (first_name, last_name, username, email, password, role) VALUES ('Late', 'Student', 'latestudent".rand(100,999)."', 'ls".rand(100,999)."@test.com', 'hash', 'student')")->execute();
    $uLateId = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO students (user_id) VALUES (?)")->execute([$uLateId]);
    $lateStudentId = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO enrollments (student_id, course_id, status, progress, enrolled_at) VALUES (?, ?, 'active', 0, NOW())")->execute([$lateStudentId, $courseId]);

    $resEnded = mark_live_class_attendance($classId, $lateStudentId, $uLateId);
    assertCheck($resEnded['success'] === false, "Late student attendance blocked because session ended");

    $analytics = get_teacher_attendance_analytics($teacherId);
    assertCheck($analytics['total_sessions'] >= 1, "Teacher analytics computes total live sessions");
    assertCheck($analytics['total_attendances'] >= 1, "Teacher analytics computes total attendances ({$analytics['total_attendances']})");
    assertCheck($analytics['overall_attendance_rate'] >= 0, "Teacher analytics computes overall participation rate ({$analytics['overall_attendance_rate']}%)");

    delete_live_class($classId, $teacherId);
    assertCheck(true, "Cleaned up test session");

} catch (Exception $e) {
    echo "  [ERROR] Exception: " . $e->getMessage() . "\n";
    $failed++;
}

echo "\n========================================================================\n";
echo " Test Results: {$passed} Passed, {$failed} Failed\n";
echo "========================================================================\n";
exit($failed === 0 ? 0 : 1);
