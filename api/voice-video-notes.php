<?php
/**
 * Voice & Video Notes API Handler
 * Handles media uploads from MediaRecorder, CRUD actions, and dynamic lesson lookups.
 */

header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__) . '/config/main.php';
require_once BASE_PATH . '/includes/functions/auth.php';
require_once BASE_PATH . '/includes/functions/voice_video_notes.php';
require_once BASE_PATH . '/includes/functions/uploads.php';

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required. Please log in.']);
    exit;
}

$user = current_user();
$userId = (int)$user['id'];
$role = current_user_role();
$pdo = getDBConnection();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Helper to get teacher record
function get_current_teacher_id($userId) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ? LIMIT 1");
    $stmt->execute([(int)$userId]);
    return (int)$stmt->fetchColumn();
}

// Helper to get student record
function get_current_student_id($userId) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT id FROM students WHERE user_id = ? LIMIT 1");
    $stmt->execute([(int)$userId]);
    return (int)$stmt->fetchColumn();
}

if ($method === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // If JSON body was sent
    if (empty($action)) {
        $raw = file_get_contents('php://input');
        $json = json_decode($raw, true);
        if (is_array($json)) {
            $_POST = array_merge($_POST, $json);
            $action = $_POST['action'] ?? '';
        }
    }

    // 1. Upload & Publish / Save Draft Recording
    if ($action === 'upload_recording') {
        if ($role !== ROLE_TEACHER && $role !== ROLE_ADMIN) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Only university teachers can record and publish notes.']);
            exit;
        }

        $teacherId = get_current_teacher_id($userId);
        if (!$teacherId) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Teacher profile not found.']);
            exit;
        }

        $title = trim($_POST['title'] ?? '');
        $courseId = (int)($_POST['course_id'] ?? 0);
        $lessonId = !empty($_POST['lesson_id']) ? (int)$_POST['lesson_id'] : null;
        $description = trim($_POST['description'] ?? '');
        $mediaType = ($_POST['media_type'] ?? 'voice') === 'video' ? 'video' : 'voice';
        $durationSeconds = (int)($_POST['duration_seconds'] ?? 0);
        $status = in_array($_POST['status'] ?? 'published', ['draft', 'published', 'archived']) ? $_POST['status'] : 'published';

        if (empty($title)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Please provide a title for your note.']);
            exit;
        }

        if (!$courseId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Please select a university course.']);
            exit;
        }

        if (!can_teacher_record_for_course($teacherId, $courseId)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'You do not have permission to publish notes to this course.']);
            exit;
        }

        if (empty($_FILES['media_file'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'No media file received. Please record your note before saving.']);
            exit;
        }

        $uploadRes = upload_recorded_media($_FILES['media_file'], $teacherId, $mediaType);
        if (!$uploadRes['success']) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $uploadRes['error']]);
            exit;
        }

        $saveRes = create_voice_video_note([
            'teacher_id'       => $teacherId,
            'course_id'        => $courseId,
            'lesson_id'        => $lessonId,
            'title'            => $title,
            'description'      => $description,
            'media_type'       => $mediaType,
            'file_path'        => $uploadRes['relative_path'],
            'file_size'        => $uploadRes['size'],
            'mime_type'        => $uploadRes['mime'],
            'duration_seconds' => $durationSeconds,
            'status'           => $status
        ]);

        if (!$saveRes['success']) {
            // Delete uploaded file if DB insertion failed
            if (!empty($uploadRes['path']) && file_exists($uploadRes['path'])) {
                @unlink($uploadRes['path']);
            }
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $saveRes['error']]);
            exit;
        }

        echo json_encode([
            'success' => true,
            'message' => ($mediaType === 'video' ? 'Video note' : 'Voice note') . ' saved successfully!',
            'note_id' => $saveRes['id'],
            'redirect' => APP_URL . '/teacher/notes.php'
        ]);
        exit;
    }

    // 2. Update Note Metadata
    if ($action === 'update_note') {
        if ($role !== ROLE_TEACHER && $role !== ROLE_ADMIN) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Unauthorized action.']);
            exit;
        }

        $teacherId = get_current_teacher_id($userId);
        $noteId = (int)($_POST['note_id'] ?? 0);

        if (!$noteId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Note ID is required.']);
            exit;
        }

        $updateData = [];
        if (isset($_POST['title'])) $updateData['title'] = $_POST['title'];
        if (isset($_POST['description'])) $updateData['description'] = $_POST['description'];
        if (isset($_POST['lesson_id'])) $updateData['lesson_id'] = $_POST['lesson_id'];
        if (isset($_POST['status'])) $updateData['status'] = $_POST['status'];
        if (isset($_POST['course_id'])) $updateData['course_id'] = (int)$_POST['course_id'];

        $res = update_voice_video_note($noteId, $teacherId, $updateData);
        if (!$res['success']) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $res['error'] ?? 'Update failed.']);
            exit;
        }

        echo json_encode(['success' => true, 'message' => 'Note updated successfully.']);
        exit;
    }

    // 3. Delete Note
    if ($action === 'delete_note') {
        if ($role !== ROLE_TEACHER && $role !== ROLE_ADMIN) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Unauthorized action.']);
            exit;
        }

        $teacherId = get_current_teacher_id($userId);
        $noteId = (int)($_POST['note_id'] ?? 0);

        if (!$noteId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Note ID is required.']);
            exit;
        }

        $res = delete_voice_video_note($noteId, $teacherId);
        if (!$res['success']) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $res['error'] ?? 'Delete failed.']);
            exit;
        }

        echo json_encode(['success' => true, 'message' => 'Note deleted successfully.']);
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid action.']);
    exit;
}

// GET Requests
if ($method === 'GET') {
    $action = $_GET['action'] ?? '';

    // 1. Get lessons for a course (used dynamically in recording studios & filters)
    if ($action === 'get_lessons') {
        $courseId = (int)($_GET['course_id'] ?? 0);
        if (!$courseId) {
            echo json_encode(['success' => true, 'lessons' => []]);
            exit;
        }

        $teacherId = ($role === ROLE_TEACHER) ? get_current_teacher_id($userId) : null;
        $lessons = get_course_lessons_for_notes($courseId, $teacherId);

        echo json_encode(['success' => true, 'lessons' => $lessons]);
        exit;
    }

    // 2. Get single note detail with permission check
    if ($action === 'get_note') {
        $noteId = (int)($_GET['note_id'] ?? 0);
        if (!$noteId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Note ID required.']);
            exit;
        }

        $note = get_voice_video_note_by_id($noteId);
        if (!$note) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Note not found.']);
            exit;
        }

        if ($role === ROLE_STUDENT) {
            $studentId = get_current_student_id($userId);
            if (!can_student_access_note($studentId, $noteId)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Access denied. You are not enrolled in this course.']);
                exit;
            }
        } elseif ($role === ROLE_TEACHER) {
            $teacherId = get_current_teacher_id($userId);
            if ((int)$note['teacher_id'] !== $teacherId) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Access denied.']);
                exit;
            }
        }

        $note['formatted_duration'] = format_note_duration($note['duration_seconds']);
        $note['media_url'] = APP_URL . '/' . ltrim($note['file_path'], '/');

        echo json_encode(['success' => true, 'note' => $note]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid GET action.']);
    exit;
}
