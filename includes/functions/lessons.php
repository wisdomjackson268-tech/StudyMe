<?php
require_once dirname(__DIR__, 2) . '/config/database.php';

function get_lesson_by_id($lessonId) {
    $pdo = getDBConnection();
    try {
        $stmt = $pdo->prepare("
            SELECT l.*, s.course_id, s.title AS section_title, 
                   c.title AS course_title, c.slug AS course_slug, c.academic_level, c.academic_year,
                   c.category_id, cat.name AS category_name, cat.slug AS category_slug,
                   c.teacher_id,
                   CONCAT(u.first_name, ' ', u.last_name) AS teacher_name,
                   u.avatar AS teacher_avatar,
                   t.qualification AS teacher_qualification,
                   t.specialization AS teacher_specialization,
                   t.rating AS teacher_rating,
                   t.bio AS teacher_bio
            FROM lessons l
            JOIN course_sections s ON l.section_id = s.id
            JOIN courses c ON s.course_id = c.id
            LEFT JOIN categories cat ON c.category_id = cat.id
            LEFT JOIN teachers t ON c.teacher_id = t.id
            LEFT JOIN users u ON t.user_id = u.id
            WHERE l.id = ?
            LIMIT 1
        ");
        $stmt->execute([$lessonId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Exception $e) {
        error_log("Error in get_lesson_by_id: " . $e->getMessage());
        return null;
    }
}

function complete_lesson_and_update_progress($enrollmentId, $lessonId, $courseId) {
    $pdo = getDBConnection();
    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            INSERT INTO lesson_progress (enrollment_id, lesson_id, completed, completed_at)
            VALUES (?, ?, 1, NOW())
            ON DUPLICATE KEY UPDATE completed = 1, completed_at = NOW()
        ");
        $stmt->execute([$enrollmentId, $lessonId]);

        $stmt = $pdo->prepare("
            SELECT COUNT(l.id)
            FROM lessons l
            JOIN course_sections s ON l.section_id = s.id
            WHERE s.course_id = ? AND l.status = 'published'
        ");
        $stmt->execute([$courseId]);
        $totalLessons = (int)$stmt->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT COUNT(lp.id)
            FROM lesson_progress lp
            JOIN lessons l ON lp.lesson_id = l.id
            JOIN course_sections s ON l.section_id = s.id
            WHERE lp.enrollment_id = ? AND lp.completed = 1 AND s.course_id = ?
        ");
        $stmt->execute([$enrollmentId, $courseId]);
        $completedLessons = (int)$stmt->fetchColumn();

        $progress = 0.00;
        if ($totalLessons > 0) {
            $progress = round(($completedLessons / $totalLessons) * 100, 2);
        }

        $stmt = $pdo->prepare("
            UPDATE enrollments 
            SET progress = ?, status = ?
            WHERE id = ?
        ");
        $status = ($progress >= 100.00) ? 'completed' : 'active';
        $stmt->execute([$progress, $status, $enrollmentId]);

        $pdo->commit();
        return $progress;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error completing lesson: " . $e->getMessage());
        return false;
    }
}
