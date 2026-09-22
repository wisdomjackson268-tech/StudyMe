<?php
require_once dirname(__DIR__, 2) . '/config/database.php';

function get_subscription_plans() {
    $pdo = getDBConnection();
    try {
        $stmt = $pdo->query("SELECT * FROM subscription_plans WHERE status = 'active' ORDER BY price ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error fetching subscription plans: " . $e->getMessage());
        return [];
    }
}

if (!function_exists('get_plan_by_slug')) {
    function get_plan_by_slug($slug) {
        $pdo = getDBConnection();
        try {
            $stmt = $pdo->prepare("SELECT * FROM subscription_plans WHERE slug = ? AND status = 'active' LIMIT 1");
            $stmt->execute([$slug]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Exception $e) {
            error_log("Error fetching plan by slug: " . $e->getMessage());
            return null;
        }
    }
}

if (!function_exists('get_plan_by_id')) {
    function get_plan_by_id($id) {
        $pdo = getDBConnection();
        try {
            $stmt = $pdo->prepare("SELECT * FROM subscription_plans WHERE id = ? LIMIT 1");
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Exception $e) {
            error_log("Error fetching plan by ID: " . $e->getMessage());
            return null;
        }
    }
}

if (!function_exists('get_user_subscription')) {
    function get_user_subscription($userId) {
        $pdo = getDBConnection();
        try {
            $stmt = $pdo->prepare("
                SELECT s.*, p.name AS plan_name, p.slug AS plan_slug, p.price AS plan_price, p.billing_cycle
                FROM subscriptions s
                JOIN subscription_plans p ON s.plan_id = p.id
                JOIN students st ON s.student_id = st.id
                WHERE st.user_id = ?
                ORDER BY s.id DESC
                LIMIT 1
            ");
            $stmt->execute([$userId]);
            $sub = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$sub) {
                $stmt = $pdo->prepare("
                    SELECT s.*, p.name AS plan_name, p.slug AS plan_slug, p.price AS plan_price, p.billing_cycle
                    FROM subscriptions s
                    JOIN subscription_plans p ON s.plan_id = p.id
                    WHERE s.student_id = ?
                    ORDER BY s.id DESC
                    LIMIT 1
                ");
                $stmt->execute([$userId]);
                $sub = $stmt->fetch(PDO::FETCH_ASSOC);
            }

            return $sub ?: null;
        } catch (Exception $e) {
            error_log("Error fetching user subscription: " . $e->getMessage());
            return null;
        }
    }
}

if (!function_exists('has_active_subscription')) {
    function has_active_subscription($userId = null) {
        if ((defined('FREE_TESTING_MODE') && FREE_TESTING_MODE === true) || (defined('DEVELOPMENT_MODE') && DEVELOPMENT_MODE === true)) {
            return true;
        }

        if ($userId === null) {
            $userId = current_user('id');
        }

        if (!$userId) {
            return false;
        }

        if (has_role(ROLE_ADMIN)) {
            return true;
        }

        $sub = get_user_subscription($userId);
        if (!$sub) {
            return false;
        }

        if ($sub['status'] !== 'active') {
            return false;
        }

        if (!empty($sub['ends_at'])) {
            $expires = strtotime($sub['ends_at']);
            if ($expires < time()) {
                return false;
            }
        }

        return true;
    }
}

if (!function_exists('require_active_subscription')) {
    function require_active_subscription() {
        require_login();

        if ((defined('FREE_TESTING_MODE') && FREE_TESTING_MODE === true) || (defined('DEVELOPMENT_MODE') && DEVELOPMENT_MODE === true)) {
            return;
        }

        if (has_role(ROLE_ADMIN)) {
            return;
        }

        if (!has_active_subscription()) {
            set_flash('warning', 'Please activate your subscription plan to unlock full dashboard access.');
            redirect('payments/activate.php');
        }
    }
}

function create_subscription_order($userId, $planId, $paymentMethod = 'card') {
    $pdo = getDBConnection();
    $plan = get_plan_by_id($planId);

    if (!$plan) {
        throw new Exception("Invalid subscription plan selected.");
    }

    $reference = 'SM-' . strtoupper(bin2hex(random_bytes(6))) . '-' . time();

    $stmt = $pdo->prepare("SELECT id FROM students WHERE user_id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    $studentId = $student ? $student['id'] : $userId;

    $stmt = $pdo->prepare("
        INSERT INTO subscriptions (student_id, plan_id, status, created_at)
        VALUES (?, ?, 'pending', NOW())
    ");
    $stmt->execute([$studentId, $planId]);
    $subscriptionId = $pdo->lastInsertId();

    $stmt = $pdo->prepare("
        INSERT INTO payments (user_id, subscription_id, amount, currency, payment_method, transaction_reference, status, created_at)
        VALUES (?, ?, ?, 'NGN', ?, ?, 'pending', NOW())
    ");
    $stmt->execute([$userId, $subscriptionId, $plan['price'], $paymentMethod, $reference]);
    $paymentId = $pdo->lastInsertId();

    return [
        'payment_id'            => $paymentId,
        'subscription_id'       => $subscriptionId,
        'transaction_reference' => $reference,
        'amount'                => $plan['price'],
        'plan'                  => $plan
    ];
}

function complete_subscription_activation($transactionRef) {
    $pdo = getDBConnection();
    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT * FROM payments WHERE transaction_reference = ? LIMIT 1");
        $stmt->execute([$transactionRef]);
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$payment || $payment['status'] === 'successful') {
            $pdo->commit();
            return true;
        }

        $stmt = $pdo->prepare("
            UPDATE payments 
            SET status = 'successful', paid_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$payment['id']]);

        if (!empty($payment['subscription_id'])) {
            $stmt = $pdo->prepare("
                UPDATE subscriptions 
                SET status = 'active', starts_at = NOW(), ends_at = DATE_ADD(NOW(), INTERVAL 30 DAY)
                WHERE id = ?
            ");
            $stmt->execute([$payment['subscription_id']]);
        }

        $stmt = $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ?");
        $stmt->execute([$payment['user_id']]);

        $pdo->commit();
        return true;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Failed to activate subscription: " . $e->getMessage());
        return false;
    }
}
