<?php
/**
 * StudyMe AI Platform — Student Referral & Wallet Dashboard
 */
require_once dirname(__DIR__) . '/config/main.php';
require_once BASE_PATH . '/includes/functions/referrals.php';

require_login();
$user   = current_user();
$userId = (int)$user['id'];
$pdo    = getDBConnection();

// Fetch or generate unique referral code
$refCode = get_user_referral_code($userId);
$refLink = get_base_url() . '/auth/register.php?ref=' . $refCode;

// Fetch Wallet Metrics
$wallet = get_user_wallet($userId);

// Fetch User's Category & Role to display exact bonus rule
$userRole = current_user_role();
$userCategorySlug = 'technology';
$bonusRateText = '₦1,500 per Technology course referral';

if ($userRole === 'teacher') {
    $bonusRateText = '₦1,000 commission per course referral';
} else {
    // Get student active course category
    $stmtCat = $pdo->prepare("
        SELECT cat.slug, cat.name
        FROM students s
        JOIN enrollments e ON s.id = e.student_id
        JOIN courses c ON e.course_id = c.id
        JOIN categories cat ON c.category_id = cat.id
        WHERE s.user_id = ? AND e.status = 'active'
        ORDER BY e.enrolled_at DESC LIMIT 1
    ");
    $stmtCat->execute([$userId]);
    $catRow = $stmtCat->fetch(PDO::FETCH_ASSOC);
    if ($catRow) {
        $userCategorySlug = $catRow['slug'];
        if ($userCategorySlug === 'university' || $userCategorySlug === 'uni') {
            $bonusRateText = '₦1,000 per University course referral';
        } elseif ($userCategorySlug === 'secondary-waec-neco' || $userCategorySlug === 'secondary') {
            $bonusRateText = '₦500 per Secondary School course referral';
        } else {
            $bonusRateText = '₦1,500 per Technology course referral';
        }
    }
}

// Handle Withdrawal Form Submission
$withdrawalMessage = '';
$withdrawalSuccess = false;
if (is_post() && isset($_POST['action']) && $_POST['action'] === 'withdraw') {
    $wAmount        = (float)($_POST['amount'] ?? 0);
    $wBankName      = trim($_POST['bank_name'] ?? '');
    $wAccountNumber = trim($_POST['account_number'] ?? '');
    $wAccountName   = trim($_POST['account_name'] ?? '');

    $result = request_withdrawal($userId, $wAmount, $wBankName, $wAccountNumber, $wAccountName);
    $withdrawalSuccess = $result['success'];
    $withdrawalMessage = $result['message'];

    if ($withdrawalSuccess) {
        set_flash('success', $withdrawalMessage);
        redirect('student/referrals.php');
    }
}

// Fetch Referral Activity History
$stmtRefs = $pdo->prepare("
    SELECT r.*, 
           CONCAT(u.first_name, ' ', u.last_name) AS referred_name,
           u.email AS referred_email,
           c.title AS course_title
    FROM referrals r
    JOIN users u ON r.referred_user_id = u.id
    LEFT JOIN courses c ON r.course_id = c.id
    WHERE r.referrer_id = ?
    ORDER BY r.created_at DESC
");
$stmtRefs->execute([$userId]);
$referralsList = $stmtRefs->fetchAll(PDO::FETCH_ASSOC);

// Fetch Withdrawal History
$stmtW = $pdo->prepare("SELECT * FROM withdrawals WHERE user_id = ? ORDER BY created_at DESC");
$stmtW->execute([$userId]);
$withdrawalsList = $stmtW->fetchAll(PDO::FETCH_ASSOC);

// Fetch Wallet Transactions Ledger
$stmtTx = $pdo->prepare("SELECT * FROM wallet_transactions WHERE user_id = ? ORDER BY created_at DESC");
$stmtTx->execute([$userId]);
$transactionsList = $stmtTx->fetchAll(PDO::FETCH_ASSOC);

include BASE_PATH . '/includes/layouts/dashboard-header.php';

?>

<div class="container-fluid py-4">
    
    <!-- Page Title Header -->
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
        <div>
            <h1 class="h3 fw-bold mb-1">Referral Rewards &amp; Wallet</h1>
            <p class="text-muted small mb-0">Share StudyMe with friends and earn verified cash bonuses directly to your wallet.</p>
        </div>
        <div class="badge bg-primary bg-opacity-10 text-primary px-3 py-2 rounded-pill fw-bold">
            <i class="bi bi-gift-fill me-1"></i> Your Rate: <?= e($bonusRateText) ?>
        </div>
    </div>

    <?php if (!empty($withdrawalMessage) && !$withdrawalSuccess): ?>
        <div class="alert alert-danger rounded-4 mb-4">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= e($withdrawalMessage) ?>
        </div>
    <?php endif; ?>

    <!-- Wallet Summary Cards -->
    <div class="row g-4 mb-5">
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-4 p-4 h-100 bg-white">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <span class="text-muted small fw-bold text-uppercase">Available Balance</span>
                    <div class="p-2 bg-success bg-opacity-10 text-success rounded-circle">
                        <i class="bi bi-wallet2 fs-4"></i>
                    </div>
                </div>
                <h2 class="display-6 fw-bold text-success mb-1">₦<?= number_format((float)$wallet['available_balance'], 2) ?></h2>
                <small class="text-muted">Ready for instant withdrawal</small>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-4 p-4 h-100 bg-white">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <span class="text-muted small fw-bold text-uppercase">Pending Bonus</span>
                    <div class="p-2 bg-warning bg-opacity-10 text-warning rounded-circle">
                        <i class="bi bi-clock-history fs-4"></i>
                    </div>
                </div>
                <h2 class="display-6 fw-bold text-warning mb-1">₦<?= number_format((float)$wallet['pending_balance'], 2) ?></h2>
                <small class="text-muted">Awaiting payment verification</small>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-4 p-4 h-100 bg-white">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <span class="text-muted small fw-bold text-uppercase">Total Earned</span>
                    <div class="p-2 bg-primary bg-opacity-10 text-primary rounded-circle">
                        <i class="bi bi-graph-up-arrow fs-4"></i>
                    </div>
                </div>
                <h2 class="display-6 fw-bold text-primary mb-1">₦<?= number_format((float)$wallet['total_earned'], 2) ?></h2>
                <small class="text-muted">Lifetime referral earnings</small>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-4 p-4 h-100 bg-white">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <span class="text-muted small fw-bold text-uppercase">Total Withdrawn</span>
                    <div class="p-2 bg-secondary bg-opacity-10 text-secondary rounded-circle">
                        <i class="bi bi-arrow-down-circle fs-4"></i>
                    </div>
                </div>
                <h2 class="display-6 fw-bold text-secondary mb-1">₦<?= number_format((float)$wallet['total_withdrawn'], 2) ?></h2>
                <small class="text-muted">Transferred to bank account</small>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-5">
        <!-- Referral Link Share Section -->
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm rounded-4 p-4 h-100 bg-white">
                <h4 class="fw-bold mb-2 d-flex align-items-center gap-2">
                    <i class="bi bi-link-45deg text-primary fs-3"></i> Your Unique Referral Link
                </h4>
                <p class="text-muted small mb-4">
                    Share your referral link with friends. When they register and complete their course payment, your referral bonus will be credited automatically to your wallet.
                </p>

                <div class="input-group input-group-lg mb-3">
                    <input type="text" id="refLinkInput" class="form-control bg-light fw-semibold fs-6" value="<?= e($refLink) ?>" readonly>
                    <button type="button" class="btn btn-primary px-4 fw-bold" onclick="copyReferralLink()">
                        <i class="bi bi-clipboard-check me-1" id="copyBtnIcon"></i> Copy Link
                    </button>
                </div>

                <div class="alert alert-info border-0 rounded-3 mb-0 small">
                    <i class="bi bi-info-circle-fill me-2"></i>
                    <strong>Bonus Policy:</strong> Referral bonuses become valid only after the referred student completes their course payment. Technology referrals generate ₦1,500, University referrals generate ₦1,000, Secondary referrals generate ₦500, and Teacher referrals generate ₦1,000.
                </div>
            </div>
        </div>

        <!-- Withdrawal Form Section -->
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm rounded-4 p-4 h-100 bg-white">
                <h4 class="fw-bold mb-2 d-flex align-items-center gap-2">
                    <i class="bi bi-bank text-success fs-4"></i> Request Cash Withdrawal
                </h4>
                <p class="text-muted small mb-3">
                    Available Balance: <strong class="text-success">₦<?= number_format((float)$wallet['available_balance'], 2) ?></strong>
                </p>

                <form action="<?= url('student/referrals.php') ?>" method="POST">
                    <input type="hidden" name="action" value="withdraw">
                    
                    <div class="mb-2">
                        <label for="amount" class="form-label fw-semibold small mb-1">Amount (₦)</label>
                        <input type="number" step="0.01" min="500" max="<?= (float)$wallet['available_balance'] ?>" name="amount" id="amount" class="form-control" placeholder="Minimum ₦500.00" required>
                    </div>

                    <div class="mb-2">
                        <label for="bank_name" class="form-label fw-semibold small mb-1">Bank Name</label>
                        <input type="text" name="bank_name" id="bank_name" class="form-control" placeholder="e.g. Access Bank, GTBank, Kuda" required>
                    </div>

                    <div class="mb-2">
                        <label for="account_number" class="form-label fw-semibold small mb-1">Account Number</label>
                        <input type="text" name="account_number" id="account_number" class="form-control" placeholder="10-digit Account Number" maxlength="10" required>
                    </div>

                    <div class="mb-3">
                        <label for="account_name" class="form-label fw-semibold small mb-1">Account Name</label>
                        <input type="text" name="account_name" id="account_name" class="form-control" placeholder="Account Holder Name" required>
                    </div>

                    <button type="submit" class="btn btn-success w-100 rounded-pill fw-bold py-2 shadow-sm" <?= (float)$wallet['available_balance'] < 500 ? 'disabled' : '' ?>>
                        <i class="bi bi-send-fill me-1"></i> Submit Withdrawal Request
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Successful Referrals Activity Table -->
    <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white overflow-hidden">
        <div class="card-header bg-white py-3 px-4 border-0 d-flex align-items-center justify-content-between">
            <h5 class="fw-bold mb-0">Referral Activity History</h5>
            <span class="badge bg-secondary rounded-pill px-3"><?= count($referralsList) ?> Total Referrals</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small text-uppercase text-muted">
                    <tr>
                        <th class="ps-4">Referred Student</th>
                        <th>Enrolled Course</th>
                        <th>Category</th>
                        <th>Payment Amount</th>
                        <th>Bonus Earned</th>
                        <th>Status</th>
                        <th class="pe-4">Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($referralsList)): ?>
                        <?php foreach ($referralsList as $ref): ?>
                            <tr>
                                <td class="ps-4 fw-semibold text-dark">
                                    <?= e($ref['referred_name']) ?>
                                    <div class="small text-muted font-monospace"><?= e($ref['referred_email']) ?></div>
                                </td>
                                <td><?= e($ref['course_title'] ?: 'Pending Enrollment') ?></td>
                                <td>
                                    <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-2 py-1 small">
                                        <?= e($ref['course_category'] ?: $ref['referrer_category'] ?: 'Technology') ?>
                                    </span>
                                </td>
                                <td class="fw-bold">₦<?= number_format((float)$ref['payment_amount'], 2) ?></td>
                                <td class="fw-bold text-success">₦<?= number_format((float)$ref['bonus_amount'], 2) ?></td>
                                <td>
                                    <?php if ($ref['status'] === 'completed'): ?>
                                        <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 py-1">
                                            <i class="bi bi-check-circle-fill me-1"></i> Verified &amp; Paid
                                        </span>
                                    <?php elseif ($ref['status'] === 'pending'): ?>
                                        <span class="badge bg-warning bg-opacity-10 text-warning rounded-pill px-3 py-1">
                                            <i class="bi bi-hourglass-split me-1"></i> Pending Payment
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary bg-opacity-10 text-secondary rounded-pill px-3 py-1">
                                            <?= ucfirst($ref['status']) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="pe-4 small text-muted"><?= date('M d, Y · H:i', strtotime($ref['created_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="bi bi-people fs-1 d-block mb-2 text-muted"></i>
                                You haven't referred any students yet. Copy your referral link above and share it to start earning!
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Wallet Transaction Ledger Table -->
    <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden mb-5">
        <div class="card-header bg-white py-3 px-4 border-0">
            <h5 class="fw-bold mb-0"><i class="bi bi-receipt text-primary me-2"></i>Wallet Transaction Ledger</h5>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small text-uppercase text-muted">
                    <tr>
                        <th class="ps-4">Date &amp; Time</th>
                        <th>Type</th>
                        <th>Description</th>
                        <th>Amount</th>
                        <th class="pe-4">Balance After</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($transactionsList)): ?>
                        <?php foreach ($transactionsList as $tx): ?>
                            <tr>
                                <td class="ps-4 small text-muted"><?= date('M d, Y · H:i', strtotime($tx['created_at'])) ?></td>
                                <td>
                                    <?php if ($tx['type'] === 'referral_bonus'): ?>
                                        <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 py-1">Bonus Credit</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger bg-opacity-10 text-danger rounded-pill px-3 py-1">Withdrawal Debit</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small fw-semibold"><?= e($tx['description']) ?></td>
                                <td class="fw-bold <?= (float)$tx['amount'] >= 0 ? 'text-success' : 'text-danger' ?>">
                                    <?= (float)$tx['amount'] >= 0 ? '+' : '' ?>₦<?= number_format(abs((float)$tx['amount']), 2) ?>
                                </td>
                                <td class="pe-4 font-monospace small">₦<?= number_format((float)$tx['balance_after'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center py-4 text-muted small">No transactions recorded yet.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Withdrawal Requests History Table -->
    <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden">
        <div class="card-header bg-white py-3 px-4 border-0">
            <h5 class="fw-bold mb-0">Withdrawal History</h5>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small text-uppercase text-muted">
                    <tr>
                        <th class="ps-4">Request Date</th>
                        <th>Amount</th>
                        <th>Bank Details</th>
                        <th>Account Name</th>
                        <th>Status</th>
                        <th class="pe-4">Processed Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($withdrawalsList)): ?>
                        <?php foreach ($withdrawalsList as $w): ?>
                            <tr>
                                <td class="ps-4 small text-muted"><?= date('M d, Y · H:i', strtotime($w['created_at'])) ?></td>
                                <td class="fw-bold text-dark">₦<?= number_format((float)$w['amount'], 2) ?></td>
                                <td>
                                    <div class="fw-semibold"><?= e($w['bank_name']) ?></div>
                                    <small class="font-monospace text-muted"><?= e($w['account_number']) ?></small>
                                </td>
                                <td><?= e($w['account_name']) ?></td>
                                <td>
                                    <?php if ($w['status'] === 'completed'): ?>
                                        <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 py-1">Completed</span>
                                    <?php elseif ($w['status'] === 'pending'): ?>
                                        <span class="badge bg-warning bg-opacity-10 text-warning rounded-pill px-3 py-1">Pending Approval</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger bg-opacity-10 text-danger rounded-pill px-3 py-1">Rejected</span>
                                    <?php endif; ?>
                                </td>
                                <td class="pe-4 small text-muted"><?= $w['processed_at'] ? date('M d, Y · H:i', strtotime($w['processed_at'])) : '—' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted small">No withdrawal requests found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<script>
function copyReferralLink() {
    const linkInput = document.getElementById('refLinkInput');
    linkInput.select();
    linkInput.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(linkInput.value);

    const icon = document.getElementById('copyBtnIcon');
    icon.className = 'bi bi-check2-all text-success me-1';
    alert('Referral link copied to clipboard!');
}
</script>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
