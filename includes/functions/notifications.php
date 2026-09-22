<?php
function create_notification($userId, $title, $message, $type = 'general', $link = null) {
    $pdo = getDBConnection();
    try {
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, link) VALUES (?, ?, ?, ?, ?)");
        return $stmt->execute([$userId, $title, $message, $type, $link]);
    } catch (Exception $e) {
        error_log("create_notification error: " . $e->getMessage());
        return false;
    }
}

function get_user_notifications($userId, $limit = 50, $offset = 0) {
    $pdo = getDBConnection();
    try {
        $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?");
        $stmt->execute([$userId, (int)$limit, (int)$offset]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

function count_unread_notifications($userId) {
    $pdo = getDBConnection();
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}

function mark_notification_read($notificationId, $userId) {
    $pdo = getDBConnection();
    try {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        return $stmt->execute([$notificationId, $userId]);
    } catch (Exception $e) {
        return false;
    }
}

function mark_all_notifications_read($userId) {
    $pdo = getDBConnection();
    try {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
        return $stmt->execute([$userId]);
    } catch (Exception $e) {
        return false;
    }
}

function delete_notification($notificationId, $userId) {
    $pdo = getDBConnection();
    try {
        $stmt = $pdo->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
        return $stmt->execute([$notificationId, $userId]);
    } catch (Exception $e) {
        return false;
    }
}

function delete_all_read_notifications($userId) {
    $pdo = getDBConnection();
    try {
        $stmt = $pdo->prepare("DELETE FROM notifications WHERE user_id = ? AND is_read = 1");
        return $stmt->execute([$userId]);
    } catch (Exception $e) {
        return false;
    }
}

function get_notification_by_id($notificationId, $userId) {
    $pdo = getDBConnection();
    try {
        $stmt = $pdo->prepare("SELECT * FROM notifications WHERE id = ? AND user_id = ? LIMIT 1");
        $stmt->execute([$notificationId, $userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return null;
    }
}

