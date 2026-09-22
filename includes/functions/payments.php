<?php
function get_user_payments($userId, $limit = 20, $offset = 0) {
    $pdo = getDBConnection();
    try {
        $stmt = $pdo->prepare("
            SELECT p.*, c.title AS course_title
            FROM payments p
            LEFT JOIN courses c ON p.course_id = c.id
            WHERE p.user_id = ?
            ORDER BY p.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$userId, (int)$limit, (int)$offset]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

function get_all_payments($status = null, $limit = 50, $offset = 0) {
    $pdo = getDBConnection();
    try {
        $where = ["1=1"];
        $params = [];
        if ($status) { $where[] = "p.status = ?"; $params[] = $status; }
        $params[] = (int)$limit;
        $params[] = (int)$offset;
        $stmt = $pdo->prepare("
            SELECT p.*, CONCAT(u.first_name,' ',u.last_name) AS user_name, u.email, c.title AS course_title
            FROM payments p
            JOIN users u ON p.user_id = u.id
            LEFT JOIN courses c ON p.course_id = c.id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY p.created_at DESC LIMIT ? OFFSET ?
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

function get_total_revenue() {
    $pdo = getDBConnection();
    try {
        $stmt = $pdo->prepare("SELECT SUM(amount) FROM payments WHERE status = 'successful'");
        $stmt->execute();
        return (float)$stmt->fetchColumn();
    } catch (Exception $e) {
        return 0.00;
    }
}

function create_payment_record($userId, $amount, $paymentMethod, $reference, $courseId = null, $subscriptionId = null) {
    $pdo = getDBConnection();
    try {
        $stmt = $pdo->prepare("INSERT INTO payments (user_id, course_id, subscription_id, amount, payment_method, transaction_reference, status, created_at) VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())");
        $stmt->execute([$userId, $courseId, $subscriptionId, $amount, $paymentMethod, $reference]);
        return (int)$pdo->lastInsertId();
    } catch (Exception $e) {
        error_log("create_payment_record error: " . $e->getMessage());
        return false;
    }
}

function update_payment_status($reference, $status) {
    $pdo = getDBConnection();
    try {
        $paidAt = ($status === 'successful') ? date('Y-m-d H:i:s') : null;
        $stmt = $pdo->prepare("UPDATE payments SET status = ?, paid_at = ? WHERE transaction_reference = ?");
        return $stmt->execute([$status, $paidAt, $reference]);
    } catch (Exception $e) {
        return false;
    }
}
