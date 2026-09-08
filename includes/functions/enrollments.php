<?php
/**
 * StudyMe AI Platform — Student Course Enrollment Helpers
 * Enforces strict One-Course-Per-Student policy.
 */
require_once dirname(__DIR__, 2) . '/config/database.php';

/**
 * Check if student is enrolled in a specific course.
 */
function is_student_enrolled($studentId, $courseId) {
    $pdo = getDBConnection();
    try {
        $stmt = $pdo->prepare("SELECT id FROM enrollments WHERE student_id = ? AND course_id = ? AND status = 'active' LIMIT 1");
        $stmt->execute([$studentId, $courseId]);
        return (bool)$stmt->fetch();
    } catch (Exception $e) {
        error_log("Error in is_student_enrolled: " . $e->getMessage());
        return false;
    }
}

/**
 * Get a specific enrollment record by student ID and course ID.
 */
function get_enrollment($studentId, $courseId) {
    $pdo = getDBConnection();
    try {
        $stmt = $pdo->prepare("SELECT * FROM enrollments WHERE student_id = ? AND course_id = ? AND status = 'active' LIMIT 1");
        $stmt->execute([(int)$studentId, (int)$courseId]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return $res ?: null;
    } catch (Exception $e) {
        error_log("Error in get_enrollment: " . $e->getMessage());
        return null;
    }
}

if (!function_exists('get_student_active_course')) {
    /**
     * Get a student's single active course enrollment if it exists.
     * Safely accepts a student_id or user_id, or defaults to the current logged-in user.
     * Uses LEFT JOINs so courses without assigned teachers still load correctly.
     * 
     * @param int|null $studentIdOrUserId
     * @return array|null
     */
    function get_student_active_course($studentIdOrUserId = null) {
        $pdo = getDBConnection();
        try {
            $studentId = (int)$studentIdOrUserId;

            // If not provided, try resolving from current session
            if ($studentId <= 0 && function_exists('is_logged_in') && is_logged_in()) {
                $uid = (int)current_user('id');
                if ($uid > 0) {
                    $st = $pdo->prepare("SELECT id FROM students WHERE user_id = ? LIMIT 1");
                    $st->execute([$uid]);
                    $studentId = (int)$st->fetchColumn();
                }
            }

            if ($studentId <= 0) {
                return null;
            }

            // Verify if this ID is in students table, or if it was passed as a user_id
            $stmtCheck = $pdo->prepare("SELECT id FROM students WHERE id = ? LIMIT 1");
            $stmtCheck->execute([$studentId]);
            $resolvedStudentId = (int)$stmtCheck->fetchColumn();

            if ($resolvedStudentId <= 0) {
                // Try checking if it's a user_id
                $stmtCheckUser = $pdo->prepare("SELECT id FROM students WHERE user_id = ? LIMIT 1");
                $stmtCheckUser->execute([$studentId]);
                $resolvedStudentId = (int)$stmtCheckUser->fetchColumn();
            }

            if ($resolvedStudentId <= 0) {
                return null;
            }

            $stmt = $pdo->prepare("
                SELECT e.*, c.id AS course_id, c.title AS course_title, c.slug AS course_slug, c.thumbnail,
                       c.category_id, cat.name AS category_name, cat.slug AS category_slug,
                       c.teacher_id,
                       CONCAT(u.first_name, ' ', u.last_name) AS teacher_name,
                       t.qualification AS teacher_qualification,
                       u.avatar AS teacher_avatar
                FROM enrollments e
                JOIN courses c ON e.course_id = c.id
                LEFT JOIN categories cat ON c.category_id = cat.id
                LEFT JOIN teachers t ON c.teacher_id = t.id
                LEFT JOIN users u ON t.user_id = u.id
                WHERE e.student_id = ? AND e.status = 'active'
                ORDER BY e.enrolled_at DESC
                LIMIT 1
            ");
            $stmt->execute([$resolvedStudentId]);
            $course = $stmt->fetch(PDO::FETCH_ASSOC);
            return $course ?: null;
        } catch (Exception $e) {
            error_log("Error in get_student_active_course: " . $e->getMessage());
            return null;
        }
    }
}

/**
 * Enroll a student in a course with strict One-Course-Per-Student policy.
 * Returns array ['success' => bool, 'message' => string]
 */
function enroll_student_in_course($studentId, $courseId) {
    $pdo = getDBConnection();
    try {
        // 1. Check if student already has an active course enrollment elsewhere
        $activeCourse = get_student_active_course($studentId);
        if ($activeCourse && (int)$activeCourse['course_id'] !== (int)$courseId) {
            return [
                'success' => false,
                'message' => 'Access Restricted: You are currently enrolled in "' . htmlspecialchars($activeCourse['course_title']) . '". Students are restricted to ONE course at a time.'
            ];
        }

        // 2. Perform enrollment
        $stmt = $pdo->prepare("
            INSERT INTO enrollments (student_id, course_id, status, progress, enrolled_at)
            VALUES (?, ?, 'active', 0.00, NOW())
            ON DUPLICATE KEY UPDATE status = 'active'
        ");
        $success = $stmt->execute([$studentId, $courseId]);

        if ($success) {
            return [
                'success' => true,
                'message' => 'Successfully enrolled in course!'
            ];
        }

        return [
            'success' => false,
            'message' => 'Enrollment failed. Please try again.'
        ];
    } catch (Exception $e) {
        error_log("Error enrolling student: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Database error during enrollment.'
        ];
    }
}

/**
 * Get student's enrolled courses.
 */
function get_student_enrollments($studentId) {
    $pdo = getDBConnection();
    try {
        $stmt = $pdo->prepare("
            SELECT e.*, c.title, c.thumbnail, c.slug, 
                   CONCAT(u.first_name, ' ', u.last_name) AS teacher_name
            FROM enrollments e
            JOIN courses c ON e.course_id = c.id
            JOIN teachers t ON c.teacher_id = t.id
            JOIN users u ON t.user_id = u.id
            WHERE e.student_id = ? AND e.status = 'active'
            ORDER BY e.enrolled_at DESC
        ");
        $stmt->execute([$studentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error fetching enrollments: " . $e->getMessage());
        return [];
    }
}

