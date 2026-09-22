<?php

require_once dirname(__DIR__) . '/config/main.php';

secure_page();
$user = current_user();
$role = current_user_role();
$pdo = getDBConnection();

$payments = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM payments WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$user['id']]);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching payment history: " . $e->getMessage());
}

include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-5">
    <div>
        <h1 class="greeting-title mb-1">Billing &amp; Subscriptions</h1>
        <p class="text-muted mb-0">Manage your payment methods, plans, and history.</p>
    </div>
</div>

<?php if (defined('DEVELOPMENT_MODE') && DEVELOPMENT_MODE === true): ?>
    <div class="alert alert-success border-success border-opacity-25 rounded-4 p-4 mb-5 shadow-sm d-flex gap-3">
        <div class="fs-2 text-success"><i class="bi bi-gift-fill"></i></div>
        <div>
            <h5 class="fw-bold mb-1">Development Free Access Active</h5>
            <p class="mb-0 small text-success-emphasis">
                StudyMe is currently in Free Development Mode. No credit card charges or bank transfers are required. All features, courses, and AI tutors are fully unlocked.
            </p>
        </div>
    </div>
<?php endif; ?>

<div class="card border-0 shadow-sm rounded-4 p-4 mb-5">
    <h5 class="fw-bold mb-4">Billing History</h5>
    <?php if (!empty($payments)): ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle small">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Reference</th>
                        <th>Method</th>
                        <th>Amount</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $p): ?>
                        <tr>
                            <td><?= date('M d, Y H:i', strtotime($p['created_at'])) ?></td>
                            <td><code><?= e($p['transaction_reference']) ?></code></td>
                            <td><?= ucfirst(str_replace('_', ' ', e($p['payment_method']))) ?></td>
                            <td class="fw-bold">₦<?= number_format($p['amount'], 2) ?></td>
                            <td>
                                <?php if ($p['status'] === 'successful'): ?>
                                    <span class="badge bg-success-subtle text-success rounded-pill px-2">Successful</span>
                                <?php elseif ($p['status'] === 'pending'): ?>
                                    <span class="badge bg-warning-subtle text-warning rounded-pill px-2">Pending Verification</span>
                                <?php else: ?>
                                    <span class="badge bg-danger-subtle text-danger rounded-pill px-2">Failed</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="text-center py-5">
            <i class="bi bi-credit-card text-muted" style="font-size: 2.5rem;"></i>
            <h6 class="fw-bold mt-3">No payments recorded.</h6>
            <p class="text-muted small">You haven't initiated any payment transactions yet.</p>
        </div>
    <?php endif; ?>
</div>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
