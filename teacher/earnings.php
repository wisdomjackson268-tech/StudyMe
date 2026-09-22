<?php

require_once dirname(__DIR__) . '/config/main.php';
require_once BASE_PATH . '/includes/functions/referrals.php';

secure_page(ROLE_TEACHER);
$user   = current_user();
$userId = (int)$user['id'];
$pdo    = getDBConnection();

$refCode = get_user_referral_code($userId);
$refLink = get_base_url() . '/auth/register.php?ref=' . $refCode;

$wallet = get_user_wallet($userId);

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
        redirect('teacher/earnings.php');
    } else {
        set_flash('error', $withdrawalMessage);
    }
}

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

$stmtW = $pdo->prepare("SELECT * FROM withdrawals WHERE user_id = ? ORDER BY created_at DESC");
$stmtW->execute([$userId]);
$withdrawalsList = $stmtW->fetchAll(PDO::FETCH_ASSOC);

$stmtTx = $pdo->prepare("SELECT * FROM wallet_transactions WHERE user_id = ? ORDER BY created_at DESC");
$stmtTx->execute([$userId]);
$transactionsList = $stmtTx->fetchAll(PDO::FETCH_ASSOC);

include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
    <div>
        <p class="text-muted mb-1 fw-semibold text-uppercase small">
            <i class="bi bi-wallet-fill text-warning me-1"></i> Instructor Suite
        </p>
        <h1 class="h3 fw-bold mb-1">Earnings &amp; Referral Wallet</h1>
        <p class="text-muted small mb-0">Track your referral bonuses, available balances, payouts, and financial transactions.</p>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#withdrawalModal">
            <i class="bi bi-cash-stack me-1"></i> Request Withdrawal
        </button>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4 p-4 mb-4" style="background: linear-gradient(135deg, #1e1b4b 0%, #312e81 100%); color:#fff;">
    <div class="row align-items-center g-3">
        <div class="col-lg-8">
            <span class="badge bg-warning text-dark rounded-pill px-3 py-1 fw-bold text-uppercase small mb-2">
                <i class="bi bi-gift-fill me-1"></i> Teacher Referral Bonus: ₦1,500 per Student
            </span>
            <h4 class="fw-bold mb-1 text-white">Your Unique Instructor Referral Link</h4>
            <p class="text-white-50 small mb-3">Earn ₦1,500 instant cash bonus into your wallet whenever a student registers and enrolls through your link.</p>
            <div class="input-group">
                <input type="text" id="teacherRefInput" class="form-control rounded-start-pill bg-white text-dark font-monospace small py-2 px-3 border-0" value="<?= e($refLink) ?>" readonly>
                <button class="btn btn-warning rounded-end-pill px-4 fw-bold" type="button" onclick="copyTeacherRefLink()">
                    <i class="bi bi-clipboard me-1"></i> <span id="copyBtnText">Copy Link</span>
                </button>
            </div>
        </div>
        <div class="col-lg-4 text-center d-none d-lg-block">
            <i class="bi bi-award-fill text-warning" style="font-size: 5rem; opacity: 0.9;"></i>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon gold"><i class="bi bi-wallet2"></i></div>
            <div>
                <div class="stat-value">₦<?= number_format((float)($wallet['total_earned'] ?? 0), 2) ?></div>
                <p class="stat-label">Lifetime Earnings</p>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon green"><i class="bi bi-cash-coin"></i></div>
            <div>
                <div class="stat-value text-success">₦<?= number_format((float)($wallet['available_balance'] ?? 0), 2) ?></div>
                <p class="stat-label">Available Balance</p>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon blue"><i class="bi bi-hourglass-split"></i></div>
            <div>
                <div class="stat-value text-info">₦<?= number_format((float)($wallet['pending_balance'] ?? 0), 2) ?></div>
                <p class="stat-label">Pending Balance</p>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon purple"><i class="bi bi-bank"></i></div>
            <div>
                <div class="stat-value">₦<?= number_format((float)($wallet['total_withdrawn'] ?? 0), 2) ?></div>
                <p class="stat-label">Total Withdrawn</p>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
    <div class="card-header bg-white border-bottom p-3">
        <ul class="nav nav-pills card-header-pills gap-2" id="walletTabs" role="tablist">
            <li class="nav-item">
                <button class="nav-link active rounded-pill fw-bold" id="tx-tab" data-bs-toggle="pill" data-bs-target="#tab-transactions" type="button" role="tab">
                    <i class="bi bi-receipt me-1"></i> Transaction Ledger (<?= count($transactionsList) ?>)
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link rounded-pill fw-bold" id="with-tab" data-bs-toggle="pill" data-bs-target="#tab-withdrawals" type="button" role="tab">
                    <i class="bi bi-clock-history me-1"></i> Payout History (<?= count($withdrawalsList) ?>)
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link rounded-pill fw-bold" id="ref-tab" data-bs-toggle="pill" data-bs-target="#tab-referrals" type="button" role="tab">
                    <i class="bi bi-people-fill me-1"></i> Referred Students (<?= count($referralsList) ?>)
                </button>
            </li>
        </ul>
    </div>
    <div class="card-body p-0">
        <div class="tab-content" id="walletTabsContent">

            <div class="tab-pane fade show active p-4" id="tab-transactions" role="tabpanel">
                <?php if (!empty($transactionsList)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light small">
                                <tr>
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th>Description</th>
                                    <th>Amount</th>
                                    <th>Balance After</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($transactionsList as $tx): ?>
                                    <tr>
                                        <td class="small text-muted"><?= date('d M Y, h:i A', strtotime($tx['created_at'])) ?></td>
                                        <td>
                                            <?php if ($tx['type'] === 'referral_bonus'): ?>
                                                <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-2 py-1">Bonus Credit</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger bg-opacity-10 text-danger rounded-pill px-2 py-1">Withdrawal Debit</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="small fw-semibold"><?= e($tx['description']) ?></td>
                                        <td class="fw-bold <?= (float)$tx['amount'] >= 0 ? 'text-success' : 'text-danger' ?>">
                                            <?= (float)$tx['amount'] >= 0 ? '+' : '' ?>₦<?= number_format(abs((float)$tx['amount']), 2) ?>
                                        </td>
                                        <td class="small font-monospace">₦<?= number_format((float)$tx['balance_after'], 2) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-receipt fs-1 mb-2 d-block"></i>
                        <p class="mb-0">No wallet transactions recorded yet. Share your referral link to begin earning.</p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="tab-pane fade p-4" id="tab-withdrawals" role="tabpanel">
                <?php if (!empty($withdrawalsList)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light small">
                                <tr>
                                    <th>Request Date</th>
                                    <th>Amount</th>
                                    <th>Bank &amp; Account</th>
                                    <th>Account Name</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($withdrawalsList as $w): ?>
                                    <tr>
                                        <td class="small text-muted"><?= date('d M Y, h:i A', strtotime($w['created_at'])) ?></td>
                                        <td class="fw-bold text-main">₦<?= number_format((float)$w['amount'], 2) ?></td>
                                        <td class="small"><?= e($w['bank_name']) ?> — <?= e($w['account_number']) ?></td>
                                        <td class="small"><?= e($w['account_name']) ?></td>
                                        <td>
                                            <?php if ($w['status'] === 'completed'): ?>
                                                <span class="badge bg-success rounded-pill px-3 py-1">Completed</span>
                                            <?php elseif ($w['status'] === 'rejected'): ?>
                                                <span class="badge bg-danger rounded-pill px-3 py-1">Rejected</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning text-dark rounded-pill px-3 py-1">Pending Verification</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-bank fs-1 mb-2 d-block"></i>
                        <p class="mb-0">No withdrawal requests submitted yet.</p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="tab-pane fade p-4" id="tab-referrals" role="tabpanel">
                <?php if (!empty($referralsList)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light small">
                                <tr>
                                    <th>Date</th>
                                    <th>Student</th>
                                    <th>Enrolled Course</th>
                                    <th>Bonus Earned</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($referralsList as $r): ?>
                                    <tr>
                                        <td class="small text-muted"><?= date('d M Y', strtotime($r['created_at'])) ?></td>
                                        <td class="fw-semibold small"><?= e($r['referred_name']) ?></td>
                                        <td class="small"><?= e($r['course_title'] ?: 'University Program') ?></td>
                                        <td class="fw-bold text-success">₦<?= number_format((float)$r['bonus_amount'], 2) ?></td>
                                        <td>
                                            <?php if ($r['status'] === 'completed'): ?>
                                                <span class="badge bg-success rounded-pill px-2 py-1 small">Earned</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary rounded-pill px-2 py-1 small"><?= ucfirst($r['status']) ?></span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-people fs-1 mb-2 d-block"></i>
                        <p class="mb-0">No referred students yet. Share your referral link with students.</p>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>

<div class="modal fade" id="withdrawalModal" tabindex="-1" aria-labelledby="withdrawalModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow-lg">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="withdrawalModalLabel">
                    <i class="bi bi-bank text-primary me-2"></i>Request Earnings Withdrawal
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="<?= url('teacher/earnings.php') ?>">
                <input type="hidden" name="action" value="withdraw">
                <div class="modal-body py-4">
                    <div class="p-3 bg-light rounded-3 mb-3 d-flex justify-content-between align-items-center">
                        <span class="text-muted small">Available to Withdraw:</span>
                        <span class="fw-bold text-success fs-5">₦<?= number_format((float)$wallet['available_balance'], 2) ?></span>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small">Withdrawal Amount (₦)</label>
                        <input type="number" step="100" min="500" max="<?= (float)$wallet['available_balance'] ?>" name="amount" class="form-control rounded-3" required placeholder="Minimum ₦500.00">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small">Bank Name</label>
                        <input type="text" name="bank_name" class="form-control rounded-3" required placeholder="e.g. Access Bank, GTBank, Zenith, OPay">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small">Account Number (10 Digits)</label>
                        <input type="text" name="account_number" pattern="\d{10}" maxlength="10" class="form-control rounded-3" required placeholder="0123456789">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small">Account Name (Must match bank records)</label>
                        <input type="text" name="account_name" class="form-control rounded-3" required placeholder="Full account name">
                    </div>

                    <div class="alert alert-info rounded-3 small mb-0">
                        <i class="bi bi-info-circle me-1"></i> During Free Testing Mode, withdrawal requests remain securely recorded in your ledger as <strong>Pending Verification</strong>.
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-5 fw-bold" <?= (float)$wallet['available_balance'] < 500 ? 'disabled' : '' ?>>
                        Submit Request
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function copyTeacherRefLink() {
    const input = document.getElementById('teacherRefInput');
    input.select();
    input.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(input.value).then(() => {
        const btn = document.getElementById('copyBtnText');
        btn.textContent = 'Copied!';
        setTimeout(() => { btn.textContent = 'Copy Link'; }, 2500);
    });
}
</script>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
