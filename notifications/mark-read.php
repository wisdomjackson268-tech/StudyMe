<?php
/**
 * StudyMe AI Platform — Mark Notification Read API/Handler
 */
require_once dirname(__DIR__) . '/config/main.php';

require_login();
$user   = current_user();
$userId = $user['id'];
$pdo    = getDBConnection();
$notifId= (int)($_POST['id'] ?? $_GET['id'] ?? 0);

if ($notifId > 0) {
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    $stmt->execute([$notifId, $userId]);
} else {
    // Mark all read
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    $stmt->execute([$userId]);
}

if (is_post()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
    exit;
}

$ref = $_SERVER['HTTP_REFERER'] ?? url('student/notifications.php');
redirect($ref);
