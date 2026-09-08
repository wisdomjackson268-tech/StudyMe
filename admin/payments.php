<?php
/**
 * StudyMe AI Platform - Admin Payment Verification
 */
require_once dirname(__DIR__) . '/config/main.php';

require_login();
if (current_user_role() !== 'admin') {
    redirect('index.php');
}

$pdo = getDBConnection();

// Handle Approve / Reject
if (is_post() && isset($_POST['payment_id'], $_POST['action'])) {
    $paymentId = (int)$_POST['payment_id'];
    $action = $_POST['action'];

    // Get payment details
    $stmt = $pdo->prepare("SELECT * FROM payments WHERE id = ?");
    $stmt->execute([$paymentId]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($payment && $payment['status'] === 'pending') {
        if ($action === 'approve') {
            // Mark payment successful
            $pdo->prepare("UPDATE payments SET status = 'successful', paid_at = NOW() WHERE id = ?")->execute([$paymentId]);
            
            // Extract plan_id from metadata if available
            $meta = json_decode($payment['metadata'], true);
            $planId = $meta['plan_id'] ?? 1; // Fallback

            // Give the user an active subscription
            $stmt = $pdo->prepare("SELECT id FROM subscriptions WHERE student_id = (SELECT id FROM students WHERE user_id = ?) LIMIT 1");
            $stmt->execute([$payment['user_id']]);
            $sub = $stmt->fetch();

            if ($sub) {
                // Update existing
                $pdo->prepare("UPDATE subscriptions SET plan_id = ?, status = 'active', starts_at = NOW(), ends_at = DATE_ADD(NOW(), INTERVAL 1 MONTH) WHERE id = ?")
                    ->execute([$planId, $sub['id']]);
            } else {
                // Determine if student or teacher
                $stmt = $pdo->prepare("SELECT id, 'student' as type FROM students WHERE user_id = ? UNION SELECT id, 'teacher' as type FROM teachers WHERE user_id = ?");
                $stmt->execute([$payment['user_id'], $payment['user_id']]);
                $roleRec = $stmt->fetch();

                if ($roleRec) {
                    $pdo->prepare("INSERT INTO subscriptions (student_id, plan_id, status, starts_at, ends_at) VALUES (?, ?, 'active', NOW(), DATE_ADD(NOW(), INTERVAL 1 MONTH))")
                        ->execute([$roleRec['id'], $planId]);
                }
            }

            set_flash('success', 'Payment approved and subscription activated.');
        } elseif ($action === 'reject') {
            $pdo->prepare("UPDATE payments SET status = 'rejected' WHERE id = ?")->execute([$paymentId]);
            set_flash('error', 'Payment rejected.');
        }
    }
    redirect('admin/payments.php');
}

// Fetch all payments
$stmt = $pdo->prepare("
    SELECT p.*, u.first_name, u.last_name, u.email 
    FROM payments p 
    JOIN users u ON p.user_id = u.id 
    ORDER BY p.created_at DESC
");
$stmt->execute();
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

include BASE_PATH . '/includes/layouts/admin-header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 fw-bold mb-0">Payment Verification</h1>
    </div>

    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">User</th>
                            <th>Reference</th>
                            <th>Amount</th>
                            <th>Date / Method</th>
                            <th>Status</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($payments)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">No payments found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($payments as $pay): 
                                $meta = !empty($pay['metadata']) ? json_decode($pay['metadata'], true) : [];
                            ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-bold"><?= e($pay['first_name'] . ' ' . $pay['last_name']) ?></div>
                                    <div class="small text-muted"><?= e($pay['email']) ?></div>
                                </td>
                                <td>
                                    <code><?= e($pay['transaction_reference']) ?></code>
                                    <?php if (!empty($meta['bank_reference'])): ?>
                                        <div class="small text-muted">Bank Ref: <?= e($meta['bank_reference']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="fw-bold">₦<?= number_format($pay['amount'], 2) ?></td>
                                <td>
                                    <div><?= date('M d, Y', strtotime($pay['created_at'])) ?></div>
                                    <span class="badge bg-secondary"><?= e(strtoupper(str_replace('_', ' ', $pay['payment_method']))) ?></span>
                                </td>
                                <td>
                                    <?php if ($pay['status'] === 'pending'): ?>
                                        <span class="badge bg-warning text-dark"><i class="bi bi-hourglass-split me-1"></i> Pending</span>
                                    <?php elseif ($pay['status'] === 'successful'): ?>
                                        <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i> Successful</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i> <?= e(ucfirst($pay['status'])) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-4">
                                    <?php if ($pay['status'] === 'pending'): ?>
                                    <form action="<?= url('admin/payments.php') ?>" method="POST" class="d-inline-block">
                                        <input type="hidden" name="payment_id" value="<?= $pay['id'] ?>">
                                        <input type="hidden" name="action" value="approve">
                                        <button type="submit" class="btn btn-sm btn-success rounded-pill" onclick="return confirm('Approve this payment?')">Approve</button>
                                    </form>
                                    <form action="<?= url('admin/payments.php') ?>" method="POST" class="d-inline-block">
                                        <input type="hidden" name="payment_id" value="<?= $pay['id'] ?>">
                                        <input type="hidden" name="action" value="reject">
                                        <button type="submit" class="btn btn-sm btn-danger rounded-pill" onclick="return confirm('Reject this payment?')">Reject</button>
                                    </form>
                                    <?php else: ?>
                                        <span class="text-muted small">Verified</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include BASE_PATH . '/includes/layouts/admin-footer.php'; ?>
