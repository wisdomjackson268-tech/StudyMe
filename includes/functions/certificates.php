<?php
/**
 * StudyMe AI Platform — Certificate Helper Functions
 */

/**
 * Issue a certificate for a student who has completed a course.
 */
function issue_certificate($studentId, $courseId, $enrollmentId) {
    $pdo = getDBConnection();
    try {
        // Check if already issued
        $stmt = $pdo->prepare("SELECT id FROM certificates WHERE student_id = ? AND course_id = ?");
        $stmt->execute([$studentId, $courseId]);
        if ($stmt->fetch()) return true; // Already issued

        $certNumber = 'STUDYME-' . strtoupper(substr(md5($studentId . $courseId . time()), 0, 8));
        $stmt = $pdo->prepare("INSERT INTO certificates (student_id, course_id, enrollment_id, certificate_number, issued_at) VALUES (?, ?, ?, ?, NOW())");
        return $stmt->execute([$studentId, $courseId, $enrollmentId, $certNumber]);
    } catch (Exception $e) {
        error_log("issue_certificate error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get all certificates for a student.
 */
function get_student_certificates($studentId) {
    $pdo = getDBConnection();
    try {
        $stmt = $pdo->prepare("
            SELECT cert.*, c.title AS course_title, c.thumbnail,
                   CONCAT(u.first_name,' ',u.last_name) AS teacher_name,
                   cert.certificate_number
            FROM certificates cert
            JOIN courses c ON cert.course_id = c.id
            JOIN teachers t ON c.teacher_id = t.id
            JOIN users u ON t.user_id = u.id
            WHERE cert.student_id = ?
            ORDER BY cert.issued_at DESC
        ");
        $stmt->execute([$studentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Get a certificate by number.
 */
function get_certificate_by_number($certNumber) {
    $pdo = getDBConnection();
    try {
        $stmt = $pdo->prepare("
            SELECT cert.*, c.title AS course_title,
                   CONCAT(u.first_name,' ',u.last_name) AS student_name,
                   CONCAT(tu.first_name,' ',tu.last_name) AS teacher_name
            FROM certificates cert
            JOIN courses c ON cert.course_id = c.id
            JOIN students s ON cert.student_id = s.id
            JOIN users u ON s.user_id = u.id
            JOIN teachers t ON c.teacher_id = t.id
            JOIN users tu ON t.user_id = tu.id
            WHERE cert.certificate_number = ?
            LIMIT 1
        ");
        $stmt->execute([$certNumber]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Check if a student has a certificate for a given course.
 */
function has_certificate($studentId, $courseId) {
    $pdo = getDBConnection();
    try {
        $stmt = $pdo->prepare("SELECT id FROM certificates WHERE student_id = ? AND course_id = ?");
        $stmt->execute([$studentId, $courseId]);
        return (bool)$stmt->fetch();
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Auto-issue certificate when enrollment hits 100% progress.
 */
function maybe_issue_certificate($studentId, $courseId, $enrollmentId) {
    $pdo = getDBConnection();
    try {
        // Check enrollment progress
        $stmt = $pdo->prepare("SELECT progress, status FROM enrollments WHERE id = ?");
        $stmt->execute([$enrollmentId]);
        $enrollment = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$enrollment) return false;

        if ((float)$enrollment['progress'] >= 100.00) {
            // Check if course has certificates enabled
            $stmt = $pdo->prepare("SELECT certificate_enabled FROM courses WHERE id = ?");
            $stmt->execute([$courseId]);
            $course = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($course && $course['certificate_enabled']) {
                return issue_certificate($studentId, $courseId, $enrollmentId);
            }
        }
        return false;
    } catch (Exception $e) {
        return false;
    }
}
