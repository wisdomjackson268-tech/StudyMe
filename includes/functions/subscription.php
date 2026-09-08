<?php
/**
 * StudyMe AI Platform - Subscription & Payment-First Middleware Functions
 */

require_once dirname(__DIR__, 2) . '/config/database.php';

/**
 * Retrieve all active subscription plans.
 *
 * @return array
 */
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

/**
 * Retrieve a subscription plan by slug.
 *
 * @param string $slug
 * @return array|null
 */
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

/**
 * Retrieve a subscription plan by ID.
 *
 * @param int $id
 * @return array|null
 */
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

/**
 * Get active subscription for a specific user.
 *
 * @param int $userId
 * @return array|null
 */
function get_user_subscription($userId) {
    $pdo = getDBConnection();
    try {
        // Query through student record or direct user match
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
            // Check if user is a teacher with subscription
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

/**
 * Determine if a user has an active, valid subscription.
 *
 * @param int|null $userId
 * @return bool
 */
function has_active_subscription($userId = null) {
    // In Free Testing / Development Mode, all authenticated users have full access
    if ((defined('FREE_TESTING_MODE') && FREE_TESTING_MODE === true) || (defined('DEVELOPMENT_MODE') && DEVELOPMENT_MODE === true)) {
        return true;
    }

    if ($userId === null) {
        $userId = current_user('id');
    }

    if (!$userId) {
        return false;
    }

    // Admins always have complete access
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

    // Check if subscription has expired
    if (!empty($sub['ends_at'])) {
        $expires = strtotime($sub['ends_at']);
        if ($expires < time()) {
            return false;
        }
    }

    return true;
}

/**
 * Server-Side Middleware: Enforces active paid subscription.
 * If user is not active, redirects directly to the activation page.
 *
 * @return void
 */
function require_active_subscription() {
    require_login();

    // In Free Testing / Development Mode, bypass subscription enforcement
    if ((defined('FREE_TESTING_MODE') && FREE_TESTING_MODE === true) || (defined('DEVELOPMENT_MODE') && DEVELOPMENT_MODE === true)) {
        return;
    }

    // Admins bypass subscription gate
    if (has_role(ROLE_ADMIN)) {
        return;
    }

    if (!has_active_subscription()) {
        set_flash('warning', 'Please activate your subscription plan to unlock full dashboard access.');
        redirect('payments/activate.php');
    }
}

/**
 * Create a new payment intent and pending subscription order.
 *
 * @param int $userId
 * @param int $planId
 * @param string $paymentMethod
 * @return array
 */
function create_subscription_order($userId, $planId, $paymentMethod = 'card') {
    $pdo = getDBConnection();
    $plan = get_plan_by_id($planId);

    if (!$plan) {
        throw new Exception("Invalid subscription plan selected.");
    }

    // Generate unique reference
    $reference = 'SM-' . strtoupper(bin2hex(random_bytes(6))) . '-' . time();

    // Ensure student record exists
    $stmt = $pdo->prepare("SELECT id FROM students WHERE user_id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    $studentId = $student ? $student['id'] : $userId;

    // Create Subscription record (status: pending)
    $stmt = $pdo->prepare("
        INSERT INTO subscriptions (student_id, plan_id, status, created_at)
        VALUES (?, ?, 'pending', NOW())
    ");
    $stmt->execute([$studentId, $planId]);
    $subscriptionId = $pdo->lastInsertId();

    // Create Payment record (status: pending)
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

/**
 * Complete and activate a subscription upon verified payment.
 *
 * @param string $transactionRef
 * @return bool
 */
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

        // 1. Mark payment as successful
        $stmt = $pdo->prepare("
            UPDATE payments 
            SET status = 'successful', paid_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$payment['id']]);

        // 2. Activate Subscription with 30-day duration
        if (!empty($payment['subscription_id'])) {
            $stmt = $pdo->prepare("
                UPDATE subscriptions 
                SET status = 'active', starts_at = NOW(), ends_at = DATE_ADD(NOW(), INTERVAL 30 DAY)
                WHERE id = ?
            ");
            $stmt->execute([$payment['subscription_id']]);
        }

        // 3. Mark user status as active
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
