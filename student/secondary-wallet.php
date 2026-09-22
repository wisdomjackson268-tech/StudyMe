<?php

require_once dirname(__DIR__) . '/config/main.php';
require_once BASE_PATH . '/includes/functions/referrals.php';
require_once BASE_PATH . '/includes/functions/activity.php';

secure_page(ROLE_STUDENT);

$user   = current_user();
$userId = (int)$user['id'];
$pdo    = getDBConnection();

$wallet = get_user_wallet($userId);
$errors = [];
$successMsg = '';

// Handle Bank Withdrawal Request
if (is_post() && isset($_POST['action']) && $_POST['action'] === 'request_withdrawal') {
    $amount        = (float)($_POST['amount'] ?? 0);
    $bankName      = trim($_POST['bank_name'] ?? '');
    $accountNumber = trim($_POST['account_number'] ?? '');
    $accountName   = trim($_POST['account_name'] ?? '');

    $avail = (float)($wallet['available_balance'] ?? 0);

    if ($amount < 1000) {
        $errors[] = 'Minimum withdrawal amount is ₦1,000.00.';
    } elseif ($amount > $avail) {
        $errors[] = 'Insufficient wallet balance. You have ₦' . number_format($avail, 2) . ' available.';
    } elseif (empty($bankName) || empty($accountNumber) || empty($accountName)) {
        $errors[] = 'Please provide complete Nigerian bank details (Bank name, Account number, Account name).';
    } else {
        $pdo->beginTransaction();
        try {
            // Deduct available balance
            $updWallet = $pdo->prepare("
                UPDATE wallets
                SET available_balance = available_balance - ?,
                    total_withdrawn = total_withdrawn + ?,
                    updated_at = NOW()
                WHERE user_id = ? AND available_balance >= ?
            ");
            $updWallet->execute([$amount, $amount, $userId, $amount]);

            if ($updWallet->rowCount() > 0) {
                // Record withdrawal request
                $stmtW = $pdo->prepare("
                    INSERT INTO withdrawals (user_id, amount, bank_name, account_number, account_name, status, created_at)
                    VALUES (?, ?, ?, ?, ?, 'pending', NOW())
                ");
                $stmtW->execute([$userId, $amount, $bankName, $accountNumber, $accountName]);
                $wId = (int)$pdo->lastInsertId();

                // Record transaction
                $curBal = $avail - $amount;
                $stmtTx = $pdo->prepare("
                    INSERT INTO wallet_transactions (user_id, type, amount, description, balance_after, reference, created_at)
                    VALUES (?, 'withdrawal', ?, ?, ?, ?, NOW())
                ");
                $txDesc = "WITHDRAWAL_REQUEST -₦" . number_format($amount, 2) . " to {$bankName} ({$accountNumber})";
                $stmtTx->execute([$userId, $amount, $txDesc, $curBal, 'WD-' . $wId]);

                log_user_activity($userId, 'requested_withdrawal', "Requested ₦" . number_format($amount, 2) . " bank withdrawal");

                $pdo->commit();
                $successMsg = "Your withdrawal request for ₦" . number_format($amount, 2) . " has been submitted successfully and is pending admin review.";
                $wallet = get_user_wallet($userId);
            } else {
                $pdo->rollBack();
                $errors[] = 'Could not process withdrawal. Please verify your available balance.';
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Withdrawal request error: " . $e->getMessage());
            $errors[] = 'An error occurred while submitting your withdrawal. Please try again.';
        }
    }
}

// Fetch Withdrawal History
$stmtWList = $pdo->prepare("SELECT * FROM withdrawals WHERE user_id = ? ORDER BY created_at DESC LIMIT 20");
$stmtWList->execute([$userId]);
$withdrawals = $stmtWList->fetchAll(PDO::FETCH_ASSOC);

// Fetch Wallet Transactions
$stmtTxList = $pdo->prepare("SELECT * FROM wallet_transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 30");
$stmtTxList->execute([$userId]);
$transactions = $stmtTxList->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Secondary Wallet & Payouts | StudyMe';
include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <a href="<?= url('student/secondary-dashboard.php') ?>" class="text-decoration-none text-muted small">
                <i class="bi bi-arrow-left me-1"></i> Dashboard
            </a>
            <span class="text-muted small">&bull;</span>
            <span class="text-muted small">Financial Ledger</span>
        </div>
        <h2 class="fw-bold mb-0 text-dark">
            <i class="bi bi-wallet2 text-success me-2"></i> Secondary Student Wallet &amp; Payouts
        </h2>
    </div>

    <a href="<?= url('student/secondary-referrals.php') ?>" class="btn btn-warning rounded-pill px-4 fw-bold text-dark">
        <i class="bi bi-gift-fill me-1"></i> Refer &amp; Earn ₦1,000
    </a>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger rounded-4 mb-4">
    <?php foreach ($errors as $err): ?>
        <div><i class="bi bi-exclamation-triangle-fill me-1"></i> <?= e($err) ?></div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (!empty($successMsg)): ?>
<div class="alert alert-success rounded-4 mb-4">
    <i class="bi bi-check-circle-fill me-1"></i> <?= e($successMsg) ?>
</div>
<?php endif; ?>

<!-- BALANCE CARDS -->
<div class="row g-4 mb-5">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm rounded-4 p-4 h-100 text-white" style="background: linear-gradient(135deg, #065F46 0%, #059669 100%);">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="text-white-75 small fw-bold text-uppercase">Available for Payout</span>
                <i class="bi bi-wallet2 fs-4 text-warning"></i>
            </div>
            <h2 class="display-6 fw-bold mb-3">₦<?= number_format((float)($wallet['available_balance'] ?? 0), 2) ?></h2>
            <button type="button" class="btn btn-warning rounded-pill fw-bold text-dark w-100" data-bs-toggle="modal" data-bs-target="#withdrawModal" <?= (float)($wallet['available_balance'] ?? 0) < 1000 ? 'disabled' : '' ?>>
                <i class="bi bi-bank me-1"></i> Request Bank Withdrawal
            </button>
            <?php if ((float)($wallet['available_balance'] ?? 0) < 1000): ?>
                <small class="text-white-50 d-block text-center mt-2" style="font-size: 0.75rem;">Minimum withdrawal: ₦1,000.00</small>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card border-0 shadow-sm rounded-4 p-4 h-100 bg-white border">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="text-muted small fw-bold text-uppercase">Total Referral Earnings</span>
                <i class="bi bi-gift-fill fs-4 text-primary"></i>
            </div>
            <h2 class="display-6 fw-bold text-dark mb-2">₦<?= number_format((float)($wallet['total_earned'] ?? 0), 2) ?></h2>
            <span class="text-muted small">₦1,000 earned per successful invite</span>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card border-0 shadow-sm rounded-4 p-4 h-100 bg-white border">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="text-muted small fw-bold text-uppercase">Total Withdrawn</span>
                <i class="bi bi-check-circle-fill fs-4 text-success"></i>
            </div>
            <h2 class="display-6 fw-bold text-dark mb-2">₦<?= number_format((float)($wallet['total_withdrawn'] ?? 0), 2) ?></h2>
            <span class="text-success small fw-semibold">Disbursed to Bank Account</span>
        </div>
    </div>
</div>

<!-- TRANSACTION & WITHDRAWAL TABS -->
<div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-5 bg-white border">
    <div class="card-header bg-white p-3 p-md-4 border-bottom">
        <h5 class="fw-bold text-dark mb-0">
            <i class="bi bi-journal-text text-primary me-2"></i> Financial Audit &amp; Transaction Ledger
        </h5>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
            <thead class="bg-light">
                <tr>
                    <th class="ps-4">Reference &amp; Type</th>
                    <th>Description</th>
                    <th>Date</th>
                    <th>Amount</th>
                    <th class="text-end pe-4">Balance After</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($transactions)): ?>
                    <?php foreach ($transactions as $tx): ?>
                    <tr>
                        <td class="ps-4">
                            <span class="badge bg-dark font-monospace me-1"><?= e($tx['reference'] ?: 'TX-' . $tx['id']) ?></span>
                            <span class="badge <?= $tx['type'] === 'referral_bonus' ? 'bg-success' : 'bg-danger' ?> rounded-pill">
                                <?= strtoupper(e($tx['type'])) ?>
                            </span>
                        </td>
                        <td class="fw-semibold text-dark"><?= e($tx['description']) ?></td>
                        <td class="text-muted"><?= date('M d, Y h:i A', strtotime($tx['created_at'])) ?></td>
                        <td class="fw-bold <?= $tx['type'] === 'referral_bonus' ? 'text-success' : 'text-danger' ?>">
                            <?= $tx['type'] === 'referral_bonus' ? '+₦' : '-₦' ?><?= number_format((float)$tx['amount'], 2) ?>
                        </td>
                        <td class="text-end pe-4 fw-bold font-monospace">
                            ₦<?= number_format((float)$tx['balance_after'], 2) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="text-center py-5 text-muted">
                            No financial transactions recorded yet.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- BANK WITHDRAWAL MODAL -->
<div class="modal fade" id="withdrawModal" tabindex="-1" aria-labelledby="withdrawModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-light border-bottom p-4">
                <h5 class="modal-title fw-bold text-dark" id="withdrawModalLabel">
                    <i class="bi bi-bank text-primary me-2"></i> Request Bank Payout
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="<?= url('student/secondary-wallet.php') ?>">
                <input type="hidden" name="action" value="request_withdrawal">

                <div class="modal-body p-4">
                    <div class="p-3 bg-light rounded-3 border mb-3">
                        <div class="d-flex justify-content-between small text-muted mb-1">
                            <span>Available Balance:</span>
                            <span class="fw-bold text-success">₦<?= number_format((float)($wallet['available_balance'] ?? 0), 2) ?></span>
                        </div>
                        <small class="text-muted" style="font-size: 0.75rem;">Payouts are processed directly to your Nigerian commercial or microfinance bank account.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Withdrawal Amount (₦):</label>
                        <input type="number" name="amount" class="form-control form-control-lg rounded-3" min="1000" max="<?= (float)($wallet['available_balance'] ?? 0) ?>" step="100" value="<?= min(5000, (float)($wallet['available_balance'] ?? 0)) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Bank Name:</label>
                        <input type="text" name="bank_name" class="form-control rounded-3" placeholder="e.g. Access Bank, OPay, GTBank, Kuda..." required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Account Number (10 digits):</label>
                        <input type="text" name="account_number" class="form-control rounded-3 font-monospace" maxlength="10" placeholder="0123456789" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Account Name:</label>
                        <input type="text" name="account_name" class="form-control rounded-3" placeholder="Full name matching bank records" required>
                    </div>
                </div>

                <div class="modal-footer bg-light border-top p-3">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success rounded-pill px-4 fw-bold">Submit Payout Request</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
