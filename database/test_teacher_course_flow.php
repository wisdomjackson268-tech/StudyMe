<?php

require_once dirname(__DIR__) . '/config/main.php';
require_once BASE_PATH . '/includes/functions/enrollments.php';

$pdo = getDBConnection();
echo "\n=======================================================\n";
echo "   STUDYME TEACHER -> COURSE -> STUDENT FLOW TEST      \n";
echo "=======================================================\n\n";

$passCount = 0;
$failCount = 0;

function assert_test($description, $condition) {
    global $passCount, $failCount;
    if ($condition) {
        echo " ✔ PASS: $description\n";
        $passCount++;
    } else {
        echo " ❌ FAIL: $description\n";
        $failCount++;
    }
}

try {
    $timestamp = time();

    echo "1. TEACHER REGISTRATION & COURSE SELECTION:\n";

    $tEmail = "test_prof_{$timestamp}@studyme.ng";
    $tPassword = password_hash('Password123!', PASSWORD_DEFAULT);

    $pdo->prepare("
        INSERT INTO users (first_name, last_name, username, email, password, role, status, created_at)
        VALUES ('Professor', 'Testing', 'proftest{$timestamp}', ?, ?, 'teacher', 'active', NOW())
    ")->execute([$tEmail, $tPassword]);
    $teacherUserId = (int)$pdo->lastInsertId();

    $tNum = 'TCH-' . date('Y') . '-' . str_pad($teacherUserId, 4, '0', STR_PAD_LEFT);
    $pdo->prepare("
        INSERT INTO teachers (user_id, teacher_number, qualification, specialization, status, created_at)
        VALUES (?, ?, 'Ph.D. in Computer Science', 'Artificial Intelligence', 'active', NOW())
    ")->execute([$teacherUserId, $tNum]);
    $teacherId = (int)$pdo->lastInsertId();

    assert_test("Teacher user created with role 'teacher'", $teacherUserId > 0 && $teacherId > 0);

    $stmtC = $pdo->query("SELECT id, title FROM courses WHERE category_id = (SELECT id FROM categories WHERE slug = 'technology' LIMIT 1) LIMIT 1");
    $existingCourse = $stmtC->fetch(PDO::FETCH_ASSOC);

    if ($existingCourse) {
        $pdo->prepare("UPDATE courses SET teacher_id = ? WHERE id = ?")->execute([$teacherId, $existingCourse['id']]);
        $pdo->prepare("UPDATE teachers SET assigned_course_id = ? WHERE id = ?")->execute([$existingCourse['id'], $teacherId]);

        $checkTeacher = $pdo->prepare("SELECT assigned_course_id FROM teachers WHERE id = ?");
        $checkTeacher->execute([$teacherId]);
        $assignedId = (int)$checkTeacher->fetchColumn();

        assert_test("Teacher assigned to existing course '{$existingCourse['title']}'", $assignedId === (int)$existingCourse['id']);
    }

    echo "\n2. TEACHER CREATING NEW UNIVERSITY COURSE WITH LEVEL/YEAR:\n";

    $uniCatId = (int)$pdo->query("SELECT id FROM categories WHERE slug = 'university' LIMIT 1")->fetchColumn();
    $deptId   = (int)$pdo->query("SELECT id FROM departments LIMIT 1")->fetchColumn();

    $newCourseTitle = "Advanced Machine Intelligence (CSC 401) - $timestamp";
    $newCourseSlug  = "csc-401-ami-$timestamp";
    $academicLevel  = "400 Level";
    $academicYear   = "Year 4";

    $stmtCreateCourse = $pdo->prepare("
        INSERT INTO courses (
            teacher_id, category_id, department_id, title, slug,
            short_description, description, thumbnail, level,
            academic_level, academic_year, price, status, created_at
        )
        VALUES (?, ?, ?, ?, ?, 'Advanced machine learning systems', 'Full syllabus', 'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?w=500', 'advanced', ?, ?, 5000.00, 'published', NOW())
    ");
    $stmtCreateCourse->execute([$teacherId, $uniCatId, $deptId, $newCourseTitle, $newCourseSlug, $academicLevel, $academicYear]);
    $newCourseId = (int)$pdo->lastInsertId();

    $pdo->prepare("UPDATE teachers SET assigned_course_id = ? WHERE id = ?")->execute([$newCourseId, $teacherId]);

    $stmtVerifyCourse = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
    $stmtVerifyCourse->execute([$newCourseId]);
    $vCourse = $stmtVerifyCourse->fetch(PDO::FETCH_ASSOC);

    assert_test("New Course created with ID $newCourseId", $newCourseId > 0);
    assert_test("Course assigned to Teacher ID $teacherId", (int)$vCourse['teacher_id'] === $teacherId);
    assert_test("Course University Level is '400 Level'", $vCourse['academic_level'] === '400 Level');
    assert_test("Course Academic Year is 'Year 4'", $vCourse['academic_year'] === 'Year 4');

    echo "\n3. TEACHER PUBLISHING LESSONS & QUIZZES:\n";

    $pdo->prepare("INSERT INTO course_sections (course_id, title, sort_order) VALUES (?, 'Module 1: Deep Neural Networks', 1)")
        ->execute([$newCourseId]);
    $sectionId = (int)$pdo->lastInsertId();

    $pdo->prepare("
        INSERT INTO lessons (section_id, title, slug, description, content, video_duration, video_url, is_free, sort_order, status, created_at)
        VALUES (?, 'Introduction to Convolutional Networks', 'intro-convnets-$timestamp', 'Lesson 1', '<p>Neural Network Content</p>', 45, 'https://www.youtube.com/watch?v=aircAruvnKk', 1, 1, 'published', NOW())
    ")->execute([$sectionId]);
    $lessonId = (int)$pdo->lastInsertId();

    $pdo->prepare("
        INSERT INTO quizzes (course_id, title, description, time_limit_minutes, passing_score, max_attempts, status, created_at)
        VALUES (?, 'Module 1 Mastery Quiz', 'Evaluation of CNNs', 15, 70.00, 3, 'published', NOW())
    ")->execute([$newCourseId]);
    $quizId = (int)$pdo->lastInsertId();

    assert_test("Section and Lesson created under Course", $sectionId > 0 && $lessonId > 0);
    assert_test("Assessment Quiz created under Course", $quizId > 0);

    echo "\n4. STUDENT REGISTRATION & ENROLLMENT TRACKING:\n";

    $sEmail = "test_student_{$timestamp}@studyme.ng";
    $sPassword = password_hash('Password123!', PASSWORD_DEFAULT);

    $pdo->prepare("
        INSERT INTO users (first_name, last_name, username, email, password, role, status, created_at)
        VALUES ('Kelvin', 'Student', 'kelvinstud{$timestamp}', ?, ?, 'student', 'active', NOW())
    ")->execute([$sEmail, $sPassword]);
    $studentUserId = (int)$pdo->lastInsertId();

    $sNum = 'STD-' . date('Y') . '-' . str_pad($studentUserId, 4, '0', STR_PAD_LEFT);
    $pdo->prepare("INSERT INTO students (user_id, student_number) VALUES (?, ?)")->execute([$studentUserId, $sNum]);
    $studentId = (int)$pdo->lastInsertId();

    assert_test("Student user created with role 'student'", $studentUserId > 0 && $studentId > 0);

    $enrollResult = enroll_student_in_course($studentId, $newCourseId);
    assert_test("Student successfully enrolled in Course", $enrollResult['success'] === true);

    $stmtEnrollCheck = $pdo->prepare("SELECT * FROM enrollments WHERE student_id = ? AND course_id = ?");
    $stmtEnrollCheck->execute([$studentId, $newCourseId]);
    $enrollRow = $stmtEnrollCheck->fetch(PDO::FETCH_ASSOC);

    assert_test("Enrollment status is 'active'", ($enrollRow['status'] ?? '') === 'active');
    assert_test("Enrollment correctly tracks teacher_id = $teacherId", (int)($enrollRow['teacher_id'] ?? 0) === $teacherId);

    echo "\n5. STUDENT DATA ISOLATION & TEACHER SCOPING:\n";

    $activeCourseData = get_student_active_course($studentId);
    assert_test("Student active course resolved to '$newCourseTitle'", ($activeCourseData['course_title'] ?? '') === $newCourseTitle);
    assert_test("Student active course includes teacher name 'Professor Testing'", ($activeCourseData['teacher_name'] ?? '') === 'Professor Testing');
    assert_test("Student active course includes Level '400 Level'", ($activeCourseData['academic_level'] ?? '') === '400 Level');

    $stmtTchStudents = $pdo->prepare("
        SELECT e.*, u.first_name, u.last_name
        FROM enrollments e
        JOIN students s ON e.student_id = s.id
        JOIN users u ON s.user_id = u.id
        WHERE (e.teacher_id = ? OR e.course_id IN (SELECT id FROM courses WHERE teacher_id = ?))
    ");
    $stmtTchStudents->execute([$teacherId, $teacherId]);
    $tchStudents = $stmtTchStudents->fetchAll(PDO::FETCH_ASSOC);

    assert_test("Teacher sees Kelvin in their enrolled students list", count($tchStudents) >= 1 && $tchStudents[0]['first_name'] === 'Kelvin');

    echo "\n6. ROLE INTEGRITY VERIFICATION:\n";

    $stmtRole1 = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmtRole1->execute([$studentUserId]);
    $roleStudent = $stmtRole1->fetchColumn();

    $stmtRole2 = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmtRole2->execute([$teacherUserId]);
    $roleTeacher = $stmtRole2->fetchColumn();

    $stmtRole3 = $pdo->query("SELECT role FROM users WHERE email = 'admin@studyme.ng' LIMIT 1");
    $roleAdmin = $stmtRole3->fetchColumn();

    assert_test("Student user preserves strict role 'student'", $roleStudent === 'student');
    assert_test("Teacher user preserves strict role 'teacher'", $roleTeacher === 'teacher');
    assert_test("Admin user preserves strict role 'admin'", $roleAdmin === 'admin');

    $pdo->prepare("DELETE FROM users WHERE id IN (?, ?)")->execute([$teacherUserId, $studentUserId]);
    $pdo->prepare("DELETE FROM courses WHERE id = ?")->execute([$newCourseId]);

    echo "\n-------------------------------------------------------\n";
    echo "TEST COMPLETE: $passCount passed, $failCount failed.\n";
    echo "-------------------------------------------------------\n\n";

} catch (Exception $e) {
    echo "❌ FATAL TEST EXCEPTION: " . $e->getMessage() . "\n";
    exit(1);
}
