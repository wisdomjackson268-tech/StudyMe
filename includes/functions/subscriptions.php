<?php
/**
 * StudyMe AI Platform — Subscription Helper Functions
 */
require_once dirname(__DIR__, 2) . '/config/database.php';

/**
 * Get all subscription plans.
 */
function get_all_plans($status = 'active') {
    $pdo = getDBConnection();
    try {
        $stmt = $pdo->prepare("SELECT * FROM subscription_plans WHERE status = ? ORDER BY price ASC");
        $stmt->execute([$status]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Get a plan by slug.
 */
function get_plan_by_slug($slug) {
    $pdo = getDBConnection();
    try {
        $stmt = $pdo->prepare("SELECT * FROM subscription_plans WHERE slug = ? LIMIT 1");
        $stmt->execute([$slug]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Get a plan by id.
 */
function get_plan_by_id($planId) {
    $pdo = getDBConnection();
    try {
        $stmt = $pdo->prepare("SELECT * FROM subscription_plans WHERE id = ? LIMIT 1");
        $stmt->execute([$planId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Get the current subscription for a student.
 */
function get_user_subscription($userId) {
    if (!$userId) return null;
    $pdo = getDBConnection();
    try {
        $stmt = $pdo->prepare("
            SELECT s.*, sp.name AS plan_name, sp.features
            FROM subscriptions s
            JOIN subscription_plans sp ON s.plan_id = sp.id
            JOIN students st ON s.student_id = st.id
            WHERE st.user_id = ? AND s.status = 'active'
            ORDER BY s.created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Check if user has an active subscription.
 */
function has_active_subscription($userId) {
    if ((defined('FREE_TESTING_MODE') && FREE_TESTING_MODE === true) || (defined('DEVELOPMENT_MODE') && DEVELOPMENT_MODE === true)) return true;
    return get_user_subscription($userId) !== null;
}

/**
 * Require active subscription — redirect if not active (unless dev/testing mode).
 */
function require_active_subscription() {
    if ((defined('FREE_TESTING_MODE') && FREE_TESTING_MODE === true) || (defined('DEVELOPMENT_MODE') && DEVELOPMENT_MODE === true)) {
        return; // free access in testing mode
    }
    $user = current_user();
    if (!$user) {
        redirect('auth/login.php');
    }
    if (!has_active_subscription($user['id'])) {
        set_flash('warning', 'Please subscribe to a plan to access the dashboard.');
        redirect('pricing.php');
    }
}

/**
 * Create a subscription.
 */
function create_subscription($studentId, $planId, $months = 1) {
    $pdo = getDBConnection();
    try {
        $starts = date('Y-m-d H:i:s');
        $ends = date('Y-m-d H:i:s', strtotime("+$months month"));
        $stmt = $pdo->prepare("INSERT INTO subscriptions (student_id, plan_id, status, starts_at, ends_at) VALUES (?, ?, 'active', ?, ?)");
        return $stmt->execute([$studentId, $planId, $starts, $ends]);
    } catch (Exception $e) {
        error_log("create_subscription error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get all subscriptions (admin).
 */
function get_all_subscriptions($status = null, $limit = 50, $offset = 0) {
    $pdo = getDBConnection();
    try {
        $where = ["1=1"];
        $params = [];
        if ($status) { $where[] = "s.status = ?"; $params[] = $status; }
        $params[] = (int)$limit;
        $params[] = (int)$offset;
        $stmt = $pdo->prepare("
            SELECT s.*, sp.name AS plan_name, sp.price,
                   CONCAT(u.first_name,' ',u.last_name) AS student_name, u.email
            FROM subscriptions s
            JOIN subscription_plans sp ON s.plan_id = sp.id
            JOIN students st ON s.student_id = st.id
            JOIN users u ON st.user_id = u.id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY s.created_at DESC LIMIT ? OFFSET ?
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}
