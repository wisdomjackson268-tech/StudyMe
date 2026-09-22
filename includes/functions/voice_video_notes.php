<?php
/**
 * Voice & Video Notes Model and Access Control Functions
 * Exclusively for University Teacher recording and University Student playback.
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/uploads.php';

/**
 * Check if a teacher has authorization to record/manage notes for a course
 */
function can_teacher_record_for_course($teacherId, $courseId) {
    $pdo = getDBConnection();
    try {
        $stmt = $pdo->prepare("
            SELECT c.id, c.title, c.academic_level, cat.slug as cat_slug
            FROM courses c
            INNER JOIN teachers t ON t.id = ?
            LEFT JOIN categories cat ON cat.id = c.category_id
            WHERE c.id = ?
              AND (c.teacher_id = t.id OR t.assigned_course_id = c.id)
            LIMIT 1
        ");
        $stmt->execute([(int)$teacherId, (int)$courseId]);
        $course = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$course) {
            return false;
        }

        // Must be a university course
        if (!empty($course['cat_slug']) && $course['cat_slug'] !== 'university') {
            // Also verify if academic_level is university (e.g. 100 Level, etc)
            if (empty($course['academic_level']) || stripos($course['academic_level'], 'Level') === false) {
                return false;
            }
        }

        return $course;
    } catch (Exception $e) {
        error_log("can_teacher_record_for_course error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get all courses available for a university teacher to record notes
 */
function get_teacher_assigned_courses($teacherId) {
    $pdo = getDBConnection();
    try {
        $stmt = $pdo->prepare("
            SELECT DISTINCT c.id, c.title, c.slug, c.academic_level, c.academic_year, cat.name as category_name
            FROM courses c
            INNER JOIN teachers t ON t.id = ?
            LEFT JOIN categories cat ON cat.id = c.category_id
            WHERE (c.teacher_id = t.id OR t.assigned_course_id = c.id)
              AND c.status = 'published'
              AND (cat.slug = 'university' OR c.academic_level LIKE '%Level%')
            ORDER BY c.title ASC
        ");
        $stmt->execute([(int)$teacherId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("get_teacher_assigned_courses error: " . $e->getMessage());
        return [];
    }
}

/**
 * Get lessons under sections for a specific course
 */
function get_course_lessons_for_notes($courseId, $teacherId = null) {
    $pdo = getDBConnection();
    try {
        if ($teacherId !== null && !can_teacher_record_for_course($teacherId, $courseId)) {
            return [];
        }

        $stmt = $pdo->prepare("
            SELECT l.id, l.title, l.sort_order, cs.title as section_title
            FROM lessons l
            INNER JOIN course_sections cs ON cs.id = l.section_id
            WHERE cs.course_id = ? AND l.status = 'published'
            ORDER BY cs.sort_order ASC, l.sort_order ASC
        ");
        $stmt->execute([(int)$courseId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("get_course_lessons_for_notes error: " . $e->getMessage());
        return [];
    }
}

/**
 * Create a new voice or video note
 */
function create_voice_video_note(array $data) {
    $pdo = getDBConnection();
    try {
        $teacherId = (int)$data['teacher_id'];
        $courseId = (int)$data['course_id'];

        if (!can_teacher_record_for_course($teacherId, $courseId)) {
            return ['success' => false, 'error' => 'You are not authorized to post notes to this course.'];
        }

        $stmt = $pdo->prepare("
            INSERT INTO voice_video_notes (
                teacher_id, course_id, lesson_id, title, description,
                media_type, file_path, file_size, mime_type, duration_seconds, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $teacherId,
            $courseId,
            !empty($data['lesson_id']) ? (int)$data['lesson_id'] : null,
            trim($data['title']),
            !empty($data['description']) ? trim($data['description']) : null,
            $data['media_type'] === 'video' ? 'video' : 'voice',
            $data['file_path'],
            (int)($data['file_size'] ?? 0),
            $data['mime_type'] ?? 'audio/webm',
            (int)($data['duration_seconds'] ?? 0),
            in_array($data['status'] ?? 'published', ['draft', 'published', 'archived']) ? $data['status'] : 'published'
        ]);

        $id = (int)$pdo->lastInsertId();
        return ['success' => true, 'id' => $id];
    } catch (Exception $e) {
        error_log("create_voice_video_note error: " . $e->getMessage());
        return ['success' => false, 'error' => 'Database error creating note: ' . $e->getMessage()];
    }
}

/**
 * Update existing voice/video note metadata
 */
function update_voice_video_note($noteId, $teacherId, array $data) {
    $pdo = getDBConnection();
    try {
        // Verify ownership
        $stmt = $pdo->prepare("SELECT id, course_id FROM voice_video_notes WHERE id = ? AND teacher_id = ? LIMIT 1");
        $stmt->execute([(int)$noteId, (int)$teacherId]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$existing) {
            return ['success' => false, 'error' => 'Note not found or permission denied.'];
        }

        $updates = [];
        $params = [];

        if (isset($data['title']) && trim($data['title']) !== '') {
            $updates[] = "title = ?";
            $params[] = trim($data['title']);
        }

        if (isset($data['description'])) {
            $updates[] = "description = ?";
            $params[] = trim($data['description']) !== '' ? trim($data['description']) : null;
        }

        if (isset($data['lesson_id'])) {
            $updates[] = "lesson_id = ?";
            $params[] = !empty($data['lesson_id']) ? (int)$data['lesson_id'] : null;
        }

        if (isset($data['status']) && in_array($data['status'], ['draft', 'published', 'archived'])) {
            $updates[] = "status = ?";
            $params[] = $data['status'];
        }

        if (isset($data['course_id']) && (int)$data['course_id'] !== (int)$existing['course_id']) {
            if (!can_teacher_record_for_course($teacherId, (int)$data['course_id'])) {
                return ['success' => false, 'error' => 'You cannot move note to an unassigned course.'];
            }
            $updates[] = "course_id = ?";
            $params[] = (int)$data['course_id'];
        }

        if (empty($updates)) {
            return ['success' => true, 'message' => 'No changes made.'];
        }

        $params[] = (int)$noteId;
        $params[] = (int)$teacherId;

        $sql = "UPDATE voice_video_notes SET " . implode(', ', $updates) . " WHERE id = ? AND teacher_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return ['success' => true];
    } catch (Exception $e) {
        error_log("update_voice_video_note error: " . $e->getMessage());
        return ['success' => false, 'error' => 'Database error updating note: ' . $e->getMessage()];
    }
}

/**
 * Delete voice/video note and remove physical media file
 */
function delete_voice_video_note($noteId, $teacherId) {
    $pdo = getDBConnection();
    try {
        $stmt = $pdo->prepare("SELECT id, file_path FROM voice_video_notes WHERE id = ? AND teacher_id = ? LIMIT 1");
        $stmt->execute([(int)$noteId, (int)$teacherId]);
        $note = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$note) {
            return ['success' => false, 'error' => 'Note not found or permission denied.'];
        }

        // Delete physical file if exists
        $filePath = $note['file_path'];
        $fullPath = (strpos($filePath, BASE_PATH) === 0) ? $filePath : (BASE_PATH . '/' . ltrim($filePath, '/'));
        if (file_exists($fullPath) && is_file($fullPath)) {
            @unlink($fullPath);
        }

        $stmt = $pdo->prepare("DELETE FROM voice_video_notes WHERE id = ? AND teacher_id = ?");
        $stmt->execute([(int)$noteId, (int)$teacherId]);

        return ['success' => true];
    } catch (Exception $e) {
        error_log("delete_voice_video_note error: " . $e->getMessage());
        return ['success' => false, 'error' => 'Database error deleting note: ' . $e->getMessage()];
    }
}

/**
 * Get single note by ID
 */
function get_voice_video_note_by_id($noteId) {
    $pdo = getDBConnection();
    try {
        $stmt = $pdo->prepare("
            SELECT n.*,
                   c.title as course_title, c.slug as course_slug, c.academic_level,
                   l.title as lesson_title,
                   CONCAT(u.first_name, ' ', u.last_name) as teacher_name, u.avatar as teacher_avatar
            FROM voice_video_notes n
            INNER JOIN courses c ON c.id = n.course_id
            INNER JOIN teachers t ON t.id = n.teacher_id
            INNER JOIN users u ON u.id = t.user_id
            LEFT JOIN lessons l ON l.id = n.lesson_id
            WHERE n.id = ?
            LIMIT 1
        ");
        $stmt->execute([(int)$noteId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Exception $e) {
        error_log("get_voice_video_note_by_id error: " . $e->getMessage());
        return null;
    }
}

/**
 * Get notes created by a teacher with optional filters
 */
function get_teacher_voice_video_notes($teacherId, array $filters = []) {
    $pdo = getDBConnection();
    try {
        $where = ["n.teacher_id = ?"];
        $params = [(int)$teacherId];

        if (!empty($filters['course_id'])) {
            $where[] = "n.course_id = ?";
            $params[] = (int)$filters['course_id'];
        }

        if (!empty($filters['lesson_id'])) {
            $where[] = "n.lesson_id = ?";
            $params[] = (int)$filters['lesson_id'];
        }

        if (!empty($filters['media_type']) && in_array($filters['media_type'], ['voice', 'video'])) {
            $where[] = "n.media_type = ?";
            $params[] = $filters['media_type'];
        }

        if (!empty($filters['status']) && in_array($filters['status'], ['draft', 'published', 'archived'])) {
            $where[] = "n.status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['search'])) {
            $where[] = "(n.title LIKE ? OR n.description LIKE ?)";
            $params[] = '%' . $filters['search'] . '%';
            $params[] = '%' . $filters['search'] . '%';
        }

        $sql = "
            SELECT n.*,
                   c.title as course_title, c.slug as course_slug, c.academic_level,
                   l.title as lesson_title
            FROM voice_video_notes n
            INNER JOIN courses c ON c.id = n.course_id
            LEFT JOIN lessons l ON l.id = n.lesson_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY n.created_at DESC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("get_teacher_voice_video_notes error: " . $e->getMessage());
        return [];
    }
}

/**
 * Check if a student is authorized to view a specific note
 */
function can_student_access_note($studentId, $noteId) {
    $pdo = getDBConnection();
    try {
        $stmt = $pdo->prepare("
            SELECT n.id, n.course_id, n.teacher_id, n.status
            FROM voice_video_notes n
            INNER JOIN enrollments e ON e.course_id = n.course_id AND e.student_id = ? AND e.status = 'active'
            WHERE n.id = ? AND n.status = 'published'
            LIMIT 1
        ");
        $stmt->execute([(int)$studentId, (int)$noteId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ? true : false;
    } catch (Exception $e) {
        error_log("can_student_access_note error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get published notes for an enrolled university student
 */
function get_student_voice_video_notes($studentId, array $filters = []) {
    $pdo = getDBConnection();
    try {
        $where = [
            "e.student_id = ?",
            "e.status = 'active'",
            "n.status = 'published'"
        ];
        $params = [(int)$studentId];

        if (!empty($filters['course_id'])) {
            $where[] = "n.course_id = ?";
            $params[] = (int)$filters['course_id'];
        }

        if (!empty($filters['lesson_id'])) {
            $where[] = "n.lesson_id = ?";
            $params[] = (int)$filters['lesson_id'];
        }

        if (!empty($filters['media_type']) && in_array($filters['media_type'], ['voice', 'video'])) {
            $where[] = "n.media_type = ?";
            $params[] = $filters['media_type'];
        }

        if (!empty($filters['search'])) {
            $where[] = "(n.title LIKE ? OR n.description LIKE ?)";
            $params[] = '%' . $filters['search'] . '%';
            $params[] = '%' . $filters['search'] . '%';
        }

        $sql = "
            SELECT n.*,
                   c.title as course_title, c.slug as course_slug, c.academic_level,
                   l.title as lesson_title,
                   CONCAT(u.first_name, ' ', u.last_name) as teacher_name, u.avatar as teacher_avatar
            FROM voice_video_notes n
            INNER JOIN enrollments e ON e.course_id = n.course_id
            INNER JOIN courses c ON c.id = n.course_id
            INNER JOIN teachers t ON t.id = n.teacher_id
            INNER JOIN users u ON u.id = t.user_id
            LEFT JOIN lessons l ON l.id = n.lesson_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY n.created_at DESC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("get_student_voice_video_notes error: " . $e->getMessage());
        return [];
    }
}

/**
 * Get voice & video notes statistics for a teacher
 */
function get_teacher_notes_stats($teacherId) {
    $pdo = getDBConnection();
    try {
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_notes,
                SUM(CASE WHEN media_type = 'voice' THEN 1 ELSE 0 END) as total_voice,
                SUM(CASE WHEN media_type = 'video' THEN 1 ELSE 0 END) as total_video,
                SUM(CASE WHEN status = 'published' THEN 1 ELSE 0 END) as total_published,
                SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as total_drafts,
                COALESCE(SUM(duration_seconds), 0) as total_duration_seconds
            FROM voice_video_notes
            WHERE teacher_id = ?
        ");
        $stmt->execute([(int)$teacherId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [
            'total_notes' => 0,
            'total_voice' => 0,
            'total_video' => 0,
            'total_published' => 0,
            'total_drafts' => 0,
            'total_duration_seconds' => 0
        ];
    } catch (Exception $e) {
        error_log("get_teacher_notes_stats error: " . $e->getMessage());
        return [
            'total_notes' => 0,
            'total_voice' => 0,
            'total_video' => 0,
            'total_published' => 0,
            'total_drafts' => 0,
            'total_duration_seconds' => 0
        ];
    }
}

/**
 * Format duration in seconds to MM:SS or HH:MM:SS
 */
function format_note_duration($seconds) {
    $seconds = (int)$seconds;
    if ($seconds <= 0) {
        return '00:00';
    }
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    $secs = $seconds % 60;

    if ($hours > 0) {
        return sprintf('%02d:%02d:%02d', $hours, $minutes, $secs);
    }
    return sprintf('%02d:%02d', $minutes, $secs);
}
