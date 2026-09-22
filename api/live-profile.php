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
$classId = (int)($_GET['class_id'] ?? 0);
$profileUserId = (int)($_GET['user_id'] ?? 0);

$class = $classId ? get_live_conversation_access($classId, $userId, $role) : null;
if (!$class || !$profileUserId) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Profile is not available in this live class.']);
    exit;
}

$pdo = getDBConnection();
$stmt = $pdo->prepare('SELECT id, course_id, teacher_id FROM live_classes WHERE id = ? LIMIT 1');
$stmt->execute([$classId]);
$liveClass = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$liveClass) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Live class not found.']);
    exit;
}

$teacherStmt = $pdo->prepare("SELECT u.id AS user_id, CONCAT(u.first_name, ' ', u.last_name) AS full_name,
        u.avatar, t.bio, t.specialization, t.qualification
    FROM teachers t
    JOIN users u ON u.id = t.user_id
    WHERE t.id = ? AND t.status = 'active' LIMIT 1");
$teacherStmt->execute([(int)$liveClass['teacher_id']]);
$teacher = $teacherStmt->fetch(PDO::FETCH_ASSOC);

$profile = null;
if ($teacher && (int)$teacher['user_id'] === $profileUserId) {
    $profile = [
        'user_id' => $profileUserId,
        'name' => trim($teacher['full_name']),
        'role' => 'Teacher',
        'avatar' => function_exists('get_teacher_avatar_url')
            ? get_teacher_avatar_url($teacher['avatar'] ?? null, $teacher['full_name'], (int)$liveClass['teacher_id'])
            : ($teacher['avatar'] ?? ''),
        'headline' => $teacher['specialization'] ?: ($teacher['qualification'] ?: 'Live class instructor'),
        'bio' => $teacher['bio'] ?: 'Instructor for this live class.'
    ];
} else {
    $studentStmt = $pdo->prepare("SELECT u.id AS user_id, CONCAT(u.first_name, ' ', u.last_name) AS full_name,
            u.avatar, s.bio, s.student_type, s.academic_level
        FROM students s
        JOIN users u ON u.id = s.user_id
        JOIN enrollments e ON e.student_id = s.id
        WHERE u.id = ? AND e.course_id = ? AND e.status = 'active'
        LIMIT 1");
    $studentStmt->execute([$profileUserId, (int)$liveClass['course_id']]);
    $student = $studentStmt->fetch(PDO::FETCH_ASSOC);
    if ($student) {
        $profile = [
            'user_id' => $profileUserId,
            'name' => trim($student['full_name']),
            'role' => 'Student',
            'avatar' => function_exists('get_avatar_url')
                ? get_avatar_url($student['avatar'] ?? null, $student['full_name'])
                : ($student['avatar'] ?? ''),
            'headline' => $student['academic_level'] ?: (ucfirst((string)($student['student_type'] ?? '')) . ' student'),
            'bio' => $student['bio'] ?: 'Student enrolled in this live class.'
        ];
    }
}

if (!$profile) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'This person is not part of the live class.']);
    exit;
}

echo json_encode(['success' => true, 'profile' => $profile]);
