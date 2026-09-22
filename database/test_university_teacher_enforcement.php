<?php

define('CLI_MODE', true);
require_once dirname(__DIR__) . '/config/main.php';
require_once BASE_PATH . '/includes/functions/users.php';
require_once BASE_PATH . '/includes/functions/courses.php';
require_once BASE_PATH . '/includes/functions/enrollments.php';

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
echo " StudyMe — University Student Tracking & Active Teacher Enforcement Suite\n";
echo "========================================================================\n\n";

try {

    $pdo->prepare("INSERT INTO users (first_name, last_name, username, email, password, role, status) VALUES ('Prof', 'Adeyemi', 'prof_adeyemi_".rand(100,999)."', 'prof_".rand(100,999)."@studyme.ng', 'hash', 'teacher', 'active')")->execute();
    $uTeacherId = (int)$pdo->lastInsertId();

    $pdo->prepare("INSERT INTO teachers (user_id, status, qualification) VALUES (?, 'active', 'Ph.D. in Engineering')")->execute([$uTeacherId]);
    $teacherId = (int)$pdo->lastInsertId();

    $stmtCat = $pdo->prepare("SELECT id FROM categories WHERE slug = 'university' LIMIT 1");
    $stmtCat->execute();
    $uCatId = (int)$stmtCat->fetchColumn();
    if (!$uCatId) {
        $pdo->prepare("INSERT INTO categories (name, slug, status) VALUES ('University', 'university', 'active')")->execute();
        $uCatId = (int)$pdo->lastInsertId();
    }

    $pdo->prepare("INSERT INTO courses (category_id, teacher_id, title, slug, academic_level, status) VALUES (?, ?, 'Advanced Thermodynamics', 'advanced-thermo-".rand(100,999)."', '300 Level', 'published')")->execute([$uCatId, $teacherId]);
    $validCourseId = (int)$pdo->lastInsertId();

    $checkValid = course_has_active_teacher($validCourseId);
    assertCheck($checkValid['can_enroll'] === true && !empty($checkValid['teacher']), "Course with active teacher allows enrollment/registration", "Teacher: {$checkValid['teacher']['name']}");

    $pdo->prepare("INSERT INTO courses (category_id, teacher_id, title, slug, academic_level, status) VALUES (?, NULL, 'Unstaffed Quantum Physics', 'unstaffed-quantum-".rand(100,999)."', '400 Level', 'published')")->execute([$uCatId]);
    $unstaffedCourseId = (int)$pdo->lastInsertId();

    $checkUnstaffed = course_has_active_teacher($unstaffedCourseId);
    assertCheck($checkUnstaffed['can_enroll'] === false, "Unstaffed university course DENIES enrollment/registration", $checkUnstaffed['reason']);

    $pdo->prepare("INSERT INTO users (first_name, last_name, username, email, password, role, status) VALUES ('Inactive', 'Teacher', 'inact_".rand(100,999)."', 'inact_".rand(100,999)."@studyme.ng', 'hash', 'teacher', 'inactive')")->execute();
    $uInactId = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO teachers (user_id, status) VALUES (?, 'suspended')")->execute([$uInactId]);
    $inactTeacherId = (int)$pdo->lastInsertId();

    $pdo->prepare("INSERT INTO courses (category_id, teacher_id, title, slug, academic_level, status) VALUES (?, ?, 'Suspended Teacher Course', 'suspended-course-".rand(100,999)."', '200 Level', 'published')")->execute([$uCatId, $inactTeacherId]);
    $suspendedTeacherCourseId = (int)$pdo->lastInsertId();

    $checkSuspended = course_has_active_teacher($suspendedTeacherCourseId);
    assertCheck($checkSuspended['can_enroll'] === false, "Course with inactive/suspended teacher DENIES enrollment/registration", $checkSuspended['reason']);

    $pdo->prepare("INSERT INTO courses (category_id, teacher_id, title, slug, academic_level, status) VALUES (?, ?, 'Draft Course', 'draft-course-".rand(100,999)."', '100 Level', 'draft')")->execute([$uCatId, $teacherId]);
    $draftCourseId = (int)$pdo->lastInsertId();

    $checkDraft = course_has_active_teacher($draftCourseId);
    assertCheck($checkDraft['can_enroll'] === false, "Draft/unpublished course DENIES enrollment/registration", $checkDraft['reason']);

    $stmtSec = $pdo->prepare("SELECT id FROM categories WHERE slug IN ('secondary-waec-neco', 'secondary') LIMIT 1");
    $stmtSec->execute();
    $secCatId = (int)$stmtSec->fetchColumn();
    if ($secCatId) {
        $pdo->prepare("INSERT INTO courses (category_id, teacher_id, title, slug, status) VALUES (?, NULL, 'WAEC Mathematics', 'waec-math-".rand(100,999)."', 'published')")->execute([$secCatId]);
        $secCourseId = (int)$pdo->lastInsertId();
        $checkSec = course_has_active_teacher($secCourseId);
        assertCheck($checkSec['can_enroll'] === true, "Secondary centralized curriculum courses allow enrollment");
        $pdo->prepare("DELETE FROM courses WHERE id = ?")->execute([$secCourseId]);
    }

    $pdo->prepare("DELETE FROM courses WHERE id IN (?, ?, ?, ?)")->execute([$validCourseId, $unstaffedCourseId, $suspendedTeacherCourseId, $draftCourseId]);
    $pdo->prepare("DELETE FROM teachers WHERE id IN (?, ?)")->execute([$teacherId, $inactTeacherId]);
    $pdo->prepare("DELETE FROM users WHERE id IN (?, ?)")->execute([$uTeacherId, $uInactId]);
    assertCheck(true, "Cleaned up temporary test entities");

} catch (Exception $e) {
    echo "  [ERROR] Exception: " . $e->getMessage() . "\n";
    $failed++;
}

echo "\n========================================================================\n";
echo " Test Results: {$passed} Passed, {$failed} Failed\n";
echo "========================================================================\n";
exit($failed === 0 ? 0 : 1);
