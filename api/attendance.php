<?php

header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__) . '/config/main.php';
require_once BASE_PATH . '/includes/functions/live_classes.php';

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required. Please log in.']);
    exit;
}

$user   = current_user();
$userId = (int)$user['id'];
$role   = current_user_role();
$pdo    = getDBConnection();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'POST') {

    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);
    if (!is_array($data)) {
        $data = $_POST;
    }

    $action  = $data['action'] ?? 'mark_attendance';
    $classId = (int)($data['class_id'] ?? 0);

    if (!$classId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing live class ID.']);
        exit;
    }

    if ($action === 'mark_attendance') {
        if ($role !== ROLE_STUDENT && $role !== ROLE_ADMIN) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Only enrolled students can mark attendance for live sessions.']);
            exit;
        }

        $stmt = $pdo->prepare("SELECT id FROM students WHERE user_id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $studentId = (int)$stmt->fetchColumn();

        if (!$studentId && $role === ROLE_ADMIN) {

            $studentNum = 'ADM-' . date('Y') . '-' . str_pad($userId, 4, '0', STR_PAD_LEFT);
            $pdo->prepare("INSERT INTO students (user_id, student_number) VALUES (?, ?) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)")
                ->execute([$userId, $studentNum]);
            $studentId = (int)$pdo->lastInsertId();
        }

        if (!$studentId) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Student profile not found.']);
            exit;
        }

        $result = mark_live_class_attendance($classId, $studentId, $userId);
        if (!$result['success']) {
            http_response_code(400);
        }
        echo json_encode($result);
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid action.']);
    exit;
}

if ($method === 'GET') {
    $classId = (int)($_GET['class_id'] ?? 0);
    if (!$classId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing live class ID.']);
        exit;
    }

    $stats = get_live_class_attendance_stats($classId);
    if (!$stats) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Live class session not found.']);
        exit;
    }

    $response = ['success' => true, 'stats' => $stats];

    if ($role === ROLE_TEACHER || $role === ROLE_ADMIN) {
        $attendees = get_live_class_attendance_list($classId);
        $response['attendees'] = $attendees;
    } else {

        $stmt = $pdo->prepare("SELECT id FROM students WHERE user_id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $studentId = (int)$stmt->fetchColumn();
        $attended = $studentId ? has_student_attended_live_class($classId, $studentId) : null;
        $response['student_attended'] = (bool)$attended;
        $response['attended_at'] = $attended['attended_at'] ?? null;
    }

    echo json_encode($response);
    exit;
}
