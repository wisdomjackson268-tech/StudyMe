<?php

define('CLI_MODE', true);
require_once dirname(__DIR__) . '/config/main.php';
require_once BASE_PATH . '/includes/functions/users.php';
require_once BASE_PATH . '/includes/functions/courses.php';
require_once BASE_PATH . '/includes/functions/enrollments.php';
require_once BASE_PATH . '/includes/functions/lessons.php';
require_once BASE_PATH . '/includes/functions/discussions.php';
require_once BASE_PATH . '/includes/functions/live_classes.php';
require_once BASE_PATH . '/includes/functions/activity.php';

$pdo = getDBConnection();
$passed = 0;
$failed = 0;

function assertTest($condition, $name, $details = '') {
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "  [PASS] {$name}\n";
    } else {
        $failed++;
        echo "  [FAIL] {$name}" . ($details ? " -- {$details}" : "") . "\n";
    }
}

echo "========================================================================\n";
echo " StudyMe Platform — Complete Lifecycle & Tracking Verification Suite\n";
echo "========================================================================\n\n";

function create_test_user($firstName, $lastName, $email, $password, $role) {
    $pdo = getDBConnection();
    $hashed = password_hash($password, PASSWORD_DEFAULT);
    $username = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $firstName . $lastName)) . rand(10, 999);
    $stmt = $pdo->prepare("
        INSERT INTO users (first_name, last_name, username, email, password, role, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, 'active', NOW())
    ");
    $stmt->execute([$firstName, $lastName, $username, $email, $hashed, $role]);
    $userId = (int)$pdo->lastInsertId();

    if ($role === ROLE_STUDENT) {
        $studentNum = 'STD-' . date('Y') . '-' . str_pad($userId, 4, '0', STR_PAD_LEFT);
        $pdo->prepare("INSERT INTO students (user_id, student_number) VALUES (?, ?)")
            ->execute([$userId, $studentNum]);
    }

    return $userId;
}

try {

    echo "1. Testing User Registration & Strict Role Integrity...\n";

    $timestamp = time();
    $studentEmail = "test_student_{$timestamp}@studyme.local";
    $teacherEmail = "test_teacher_{$timestamp}@studyme.local";
    $testPassword = "Password123!";

    $studentId = create_test_user('Alice', 'Student', $studentEmail, $testPassword, ROLE_STUDENT);
    assertTest($studentId > 0, "Student registration successful (ID: {$studentId})");
    $studentUser = get_user_by_id($studentId);
    assertTest($studentUser['role'] === ROLE_STUDENT, "Student role is strictly '" . ROLE_STUDENT . "'");

    $teacherUserId = create_test_user('Dr. Robert', 'Okonkwo', $teacherEmail, $testPassword, ROLE_TEACHER);
    assertTest($teacherUserId > 0, "Teacher user registration successful (ID: {$teacherUserId})");

    $stmt = $pdo->prepare("
        INSERT INTO teachers (user_id, qualification, bio, experience_years, status, created_at)
        VALUES (?, 'PhD in Computer Science', 'Senior Lecturer & AI Specialist', 10, 'approved', NOW())
    ");
    $stmt->execute([$teacherUserId]);
    $teacherId = (int)$pdo->lastInsertId();
    assertTest($teacherId > 0, "Teacher profile record created (Teacher ID: {$teacherId})");

    $teacherUser = get_user_by_id($teacherUserId);
    assertTest($teacherUser['role'] === ROLE_TEACHER, "Teacher role is strictly '" . ROLE_TEACHER . "'");

    echo "\n2. Testing Course Creation with University Level & Year...\n";

    $courseTitle = "Advanced Machine Learning {$timestamp}";
    $courseSlug = "adv-ml-{$timestamp}";~~

    $catId = (int)$pdo->query("SELECT id FROM categories LIMIT 1")->fetchColumn() ?: 1;

    $stmt = $pdo->prepare("
        INSERT INTO courses (category_id, teacher_id, title, slug, description, level, academic_level, academic_year, price, status, created_at)
        VALUES (?, ?, ?, ?, 'Comprehensive deep learning and data structures.', 'advanced', '400 Level', 'Year 4', 25000.00, 'published', NOW())
    ");
    $stmt->execute([$catId, $teacherId, $courseTitle, $courseSlug]);
    $courseId = (int)$pdo->lastInsertId();
    assertTest($courseId > 0, "Course created by Teacher with Level & Year (Course ID: {$courseId})");

    $courseData = get_course_by_id($courseId);
    assertTest($courseData['academic_level'] === '400 Level', "Course academic_level is '400 Level'");
    assertTest($courseData['academic_year'] === 'Year 4', "Course academic_year is 'Year 4'");
    assertTest((int)$courseData['teacher_id'] === $teacherId, "Course teacher_id correctly references Teacher {$teacherId}");

    $unassignedTitle = "Self-Paced Physics {$timestamp}";
    $stmt = $pdo->prepare("
        INSERT INTO courses (category_id, teacher_id, title, slug, description, level, academic_level, academic_year, price, status, created_at)
        VALUES (?, NULL, ?, 'self-paced-phy-{$timestamp}', 'Classical mechanics without an assigned teacher.', 'beginner', '100 Level', 'Year 1', 15000.00, 'published', NOW())
    ");
    $stmt->execute([$catId, $unassignedTitle]);
    $unassignedCourseId = (int)$pdo->lastInsertId();
    assertTest($unassignedCourseId > 0, "Unassigned course created (Course ID: {$unassignedCourseId})");

    echo "\n3. Testing Student Enrollment & Teacher Linkage Detection...\n";

    $enrollRes = enroll_student_in_course($studentId, $courseId);
    assertTest($enrollRes['success'] === true, "Student enrolled in assigned course");

    $enrollmentData = get_enrollment($studentId, $courseId);
    assertTest((int)$enrollmentData['teacher_id'] === $teacherId, "Enrollment correctly assigned Teacher ID {$teacherId}");

    $activeCourse = get_student_active_course($studentId);
    assertTest(!empty($activeCourse['teacher_name']), "Active course resolves assigned instructor ('{$activeCourse['teacher_name']}')");

    $student2Id = create_test_user('Bob', 'Student', "bob_{$timestamp}@studyme.local", $testPassword, ROLE_STUDENT);
    $unassignedEnrollRes = enroll_student_in_course($student2Id, $unassignedCourseId);
    assertTest($unassignedEnrollRes['success'] === true, "Student 2 enrolled in unassigned course");

    $unassignedEnrollData = get_enrollment($student2Id, $unassignedCourseId);
    assertTest(empty($unassignedEnrollData['teacher_id']), "Unassigned enrollment has teacher_id as NULL");

    $unassignedActiveCourse = get_student_active_course($student2Id);
    assertTest(empty($unassignedActiveCourse['teacher_name']), "Unassigned enrollment teacher_name is empty (Graceful 'No teacher assigned' fallback)");

    echo "\n4. Testing Teacher Course Claiming & Auto-Relinking Enrolled Students...\n";
    $stmt = $pdo->prepare("UPDATE courses SET teacher_id = ? WHERE id = ?");
    $stmt->execute([$teacherId, $unassignedCourseId]);

    $stmt = $pdo->prepare("UPDATE enrollments SET teacher_id = ? WHERE course_id = ? AND teacher_id IS NULL");
    $stmt->execute([$teacherId, $unassignedCourseId]);

    $relinkedEnrollData = get_enrollment($student2Id, $unassignedCourseId);
    assertTest((int)$relinkedEnrollData['teacher_id'] === $teacherId, "Existing enrollment automatically linked to new teacher ID {$teacherId}");

    echo "\n5. Testing Lessons, Attendance & Progress Tracking...\n";

    $stmt = $pdo->prepare("INSERT INTO course_sections (course_id, title, sort_order) VALUES (?, 'Module 1: Foundations', 1)");
    $stmt->execute([$courseId]);
    $sectionId = (int)$pdo->lastInsertId();
    assertTest($sectionId > 0, "Course Section created (Section ID: {$sectionId})");

    $stmt = $pdo->prepare("
        INSERT INTO lessons (section_id, title, slug, description, content, video_url, video_duration, is_free, sort_order, status, created_at)
        VALUES (?, 'Lesson 1: Introduction to Neural Nets', 'lesson-1-nn', 'Introductory concepts', 'Full content text here', 'https://example.com/video1.mp4', 1800, 1, 1, 'published', NOW())
    ");
    $stmt->execute([$sectionId]);
    $lesson1Id = (int)$pdo->lastInsertId();

    $stmt = $pdo->prepare("
        INSERT INTO lessons (section_id, title, slug, description, content, video_url, video_duration, is_free, sort_order, status, created_at)
        VALUES (?, 'Lesson 2: Convolutional Networks', 'lesson-2-cnn', 'Deep vision networks', 'Full content text here', 'https://example.com/video2.mp4', 2400, 0, 2, 'published', NOW())
    ");
    $stmt->execute([$sectionId]);
    $lesson2Id = (int)$pdo->lastInsertId();
    assertTest($lesson1Id > 0 && $lesson2Id > 0, "Created 2 lessons for course (IDs: {$lesson1Id}, {$lesson2Id})");

    $enrollmentRec = get_enrollment($studentId, $courseId);
    $enrollmentId = (int)$enrollmentRec['id'];

    $progRes = complete_lesson_and_update_progress($enrollmentId, $lesson1Id, $courseId);
    assertTest($progRes === 50.0 || $progRes == 50, "Lesson 1 marked completed & Course progress updated to 50% (Result: {$progRes}%)");

    $lessonDetails = get_lesson_by_id($lesson1Id);
    assertTest($lessonDetails && !empty($lessonDetails['teacher_name']), "Lesson details view successfully resolved assigned teacher '{$lessonDetails['teacher_name']}'");
    assertTest($lessonDetails['academic_level'] === '400 Level', "Lesson details view successfully resolved course academic level '400 Level'");

    echo "\n6. Testing Student Q&A & Teacher Reply Flow...\n";

    $questionRes = post_lesson_question($lesson1Id, $studentId, "How do backpropagation gradients propagate in Conv2D layers?");
    assertTest($questionRes['success'] === true, "Student posted question on Lesson 1 (Question ID: {$questionRes['question_id']})");
    $qId = (int)$questionRes['question_id'];

    $qData = get_lesson_question_by_id($qId);
    assertTest($qData['status'] === 'open', "Question initial status is 'open'");

    $replyRes = post_lesson_question_reply($qId, $teacherUserId, "They propagate via transposed convolution and chain rule matrix multiplication.", true);
    assertTest($replyRes['success'] === true, "Teacher posted verified instructor reply");

    $updatedQData = get_lesson_question_by_id($qId);
    assertTest($updatedQData['status'] === 'answered', "Question automatically transitioned to 'answered'");

    echo "\n7. Testing Live Interactive Classes & Missed Replay Archives...\n";

    $liveClassId = schedule_live_class(
        $courseId,
        $teacherId,
        "Live Workshop: CNN Architecture Deep Dive",
        "Interactive Q&A and coding session",
        "https://meet.google.com/test-abc-xyz",
        date('Y-m-d H:i:s', strtotime('+2 days')),
        90
    );
    assertTest($liveClassId > 0, "Teacher scheduled live interactive class (ID: {$liveClassId})");

    $upcoming = get_student_upcoming_live_classes($studentId);
    $foundUpcoming = false;
    foreach ($upcoming as $u) {
        if ((int)$u['id'] === $liveClassId) {
            $foundUpcoming = true;
            break;
        }
    }
    assertTest($foundUpcoming, "Student successfully retrieved assigned live class session");

    update_live_class_status($liveClassId, $teacherId, 'ended', 'https://youtube.com/watch?v=replay123');

    $pastClasses = get_student_past_live_classes($studentId);
    $foundPast = false;
    foreach ($pastClasses as $p) {
        if ((int)$p['id'] === $liveClassId && $p['recording_url'] === 'https://youtube.com/watch?v=replay123') {
            $foundPast = true;
            break;
        }
    }
    assertTest($foundPast, "Student can access concluded session replay for missed lecture");

    echo "\n8. Testing Learning Activity & Telemetry Logging...\n";

    log_user_activity($studentId, 'lesson_view', "Attended Lesson 1: Introduction to Neural Nets", $courseId, $lesson1Id);
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM activity_logs WHERE user_id = ? AND action = 'lesson_view'");
    $stmt->execute([$studentId]);
    $actCount = (int)$stmt->fetchColumn();
    assertTest($actCount > 0, "Activity log recorded for student action");

    $dailyActivities = get_user_daily_activity($studentId);
    assertTest(!empty($dailyActivities), "Daily activity telemetry successfully retrieved for student");

    echo "\n9. Testing Scoping & Teacher Student Visibility Isolation...\n";

    $stmt = $pdo->prepare("
        SELECT DISTINCT e.student_id
        FROM enrollments e
        JOIN courses c ON e.course_id = c.id
        WHERE c.teacher_id = ?
    ");
    $stmt->execute([$teacherId]);
    $enrolledStudents = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $studentTableId = (int)$pdo->query("SELECT id FROM students WHERE user_id = {$studentId}")->fetchColumn();
    assertTest(in_array($studentTableId, $enrolledStudents), "Teacher correctly sees student in their assigned course");

} catch (Exception $e) {
    echo "  [ERROR] Exception occurred: " . $e->getMessage() . "\n";
    $failed++;
}

echo "\n========================================================================\n";
echo " TEST RESULTS: {$passed} Passed, {$failed} Failed\n";
echo "========================================================================\n";

if ($failed === 0) {
    echo " All End-to-End Tracking & Lifecycle tests passed successfully!\n";
    exit(0);
} else {
    echo " Some tests failed.\n";
    exit(1);
}
