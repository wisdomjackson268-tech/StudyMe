<?php

header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__) . '/config/main.php';
require_once BASE_PATH . '/includes/functions/live_conversations.php';
require_once BASE_PATH . '/includes/functions/uploads.php';

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required.']);
    exit;
}

$user = current_user();
$userId = (int)$user['id'];
$role = current_user_role();
$classId = (int)($_GET['class_id'] ?? $_POST['class_id'] ?? 0);

if (!$classId || !get_live_conversation_access($classId, $userId, $role)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'You do not have access to this conversation.']);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $messages = get_live_conversation_messages($classId, $userId, $role);
    $typingUsers = get_live_typing_users($classId, $userId);
    $students = [];
    if ($role === ROLE_TEACHER || $role === ROLE_ADMIN) {
        $students = get_live_class_enrolled_students($classId);
    }
    echo json_encode([
        'success' => true,
        'messages' => $messages,
        'students' => $students,
        'total_students' => count($students),
        'typing_users' => $typingUsers
    ]);
    exit;
}

$action = $_POST['action'] ?? 'send_message';
if ($action === 'typing') {
    $typingRole = $role === ROLE_TEACHER ? 'teacher' : 'student';
    set_live_typing($classId, $userId, $typingRole, filter_var($_POST['is_typing'] ?? false, FILTER_VALIDATE_BOOLEAN));
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'edit_message') {
    $updated = edit_live_conversation_message($classId, (int)($_POST['message_id'] ?? 0), $userId, $_POST['message_text'] ?? '');
    if (!$updated) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Only your text messages can be edited.']);
        exit;
    }
    echo json_encode(['success' => true, 'message' => 'Message edited.']);
    exit;
}

if ($action === 'delete_message') {
    $deleted = delete_live_conversation_message($classId, (int)($_POST['message_id'] ?? 0), $userId);
    if (!$deleted) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Only your messages can be deleted.']);
        exit;
    }
    echo json_encode(['success' => true, 'message' => 'Message deleted.']);
    exit;
}

$type = $_POST['message_type'] ?? 'text';
$durationSeconds = max(0, min(3600, (int)($_POST['duration_seconds'] ?? 0)));
if ($type === 'text' && !empty($_FILES['video_note']['tmp_name'])) {
    $type = 'video';
}
if ($type === 'text' && !empty($_FILES['voice_note']['tmp_name'])) {
    $type = 'voice';
}
if (!in_array($type, ['text', 'voice', 'image', 'video'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid message type.']);
    exit;
}

$mediaUrl = null;
if ($type === 'voice') {
    if ($role !== ROLE_TEACHER) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Only the teacher can send voice notes.']);
        exit;
    }
    $teacherStmt = getDBConnection()->prepare('SELECT id FROM teachers WHERE user_id = ? LIMIT 1');
    $teacherStmt->execute([$userId]);
    $teacherId = (int)$teacherStmt->fetchColumn();
    $upload = upload_voice_note($_FILES['voice_note'] ?? null, $teacherId);
    if (!$upload['success']) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $upload['error']]);
        exit;
    }
    $mediaUrl = $upload['relative_path'];
}
if ($type === 'image') {
    if ($role !== ROLE_TEACHER) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Only the teacher can share pictures.']);
        exit;
    }
    $teacherStmt = getDBConnection()->prepare('SELECT id FROM teachers WHERE user_id = ? LIMIT 1');
    $teacherStmt->execute([$userId]);
    $teacherId = (int)$teacherStmt->fetchColumn();
    $upload = upload_image($_FILES['image_note'] ?? null, 'live-images/teacher_' . $teacherId);
    if (!$upload['success']) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $upload['error']]);
        exit;
    }
    $mediaUrl = 'uploads/' . ltrim($upload['relative_path'], '/');
}
if ($type === 'video') {
    if ($role !== ROLE_TEACHER) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Only the teacher can share video notes.']);
        exit;
    }
    $teacherStmt = getDBConnection()->prepare('SELECT id FROM teachers WHERE user_id = ? LIMIT 1');
    $teacherStmt->execute([$userId]);
    $teacherId = (int)$teacherStmt->fetchColumn();
    $upload = upload_recorded_media($_FILES['video_note'] ?? null, $teacherId, 'video');
    if (!$upload['success']) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $upload['error']]);
        exit;
    }
    $mediaUrl = $upload['relative_path'];
}

$saved = create_live_conversation_message(
    $classId,
    $userId,
    $role,
    $type,
    $_POST['message_text'] ?? null,
    $mediaUrl,
    (int)($_POST['reply_to_message_id'] ?? 0),
    (int)($_POST['mention_user_id'] ?? 0),
    $durationSeconds
);
if (!$saved) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Could not save your message.']);
    exit;
}

echo json_encode(['success' => true, 'message' => 'Message sent.']);