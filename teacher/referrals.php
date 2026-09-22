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

$bonusPerStudent = 1500.00;

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
        redirect('teacher/referrals.php');
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

$totalReferredCount = count($referralsList);
$successfulReferrals = 0;
$totalReferralEarnings = 0.00;

foreach ($referralsList as $ref) {
    if (($ref['status'] ?? '') === 'completed') {
        $successfulReferrals++;
        $totalReferralEarnings += (float)($ref['bonus_amount'] ?? $bonusPerStudent);
    }
}

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
            <i class="bi bi-gift-fill text-warning me-1"></i> Instructor Affiliate &amp; Growth Program
        </p>
        <h1 class="h3 fw-bold mb-1">Refer Students &amp; Earn Cash</h1>
        <p class="text-muted small mb-0">Earn <strong>₦1,500 instant cash bonus</strong> for every student who registers and enrolls through your instructor link.</p>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#withdrawalModal">
            <i class="bi bi-cash-stack me-1"></i> Request Payout
        </button>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 mb-5" style="background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #312e81 100%); color:#fff; border: 1px solid rgba(255,255,255,0.1);">
    <div class="row align-items-center g-4">
        <div class="col-lg-8">
            <span class="badge bg-warning text-dark rounded-pill px-3 py-1 fw-bold text-uppercase small mb-3">
                <i class="bi bi-stars me-1"></i> ₦1,500 Commission Per Enrolled Student
            </span>
            <h2 class="display-6 fw-bold mb-2 text-white">Your Personal Referral Link</h2>
            <p class="text-white-50 mb-4" style="max-width: 600px; font-size: 0.95rem; line-height: 1.6;">
                Share this link on WhatsApp, Telegram, YouTube, social media, or in your classroom. When learners sign up using your link, they are attributed to your instructor wallet.
            </p>

            <div class="input-group mb-3" style="max-width: 640px;">
                <input type="text" id="teacherRefInput" class="form-control rounded-start-pill bg-white text-dark font-monospace py-3 px-4 border-0 fw-semibold" value="<?= e($refLink) ?>" readonly>
                <button class="btn btn-warning rounded-end-pill px-4 fw-bold text-dark" type="button" onclick="copyTeacherRefLink()">
                    <i class="bi bi-clipboard-check me-1"></i> <span id="copyBtnText">Copy Link</span>
                </button>
            </div>

            <div class="d-flex align-items-center gap-3 text-white-50 small flex-wrap">
                <span><i class="bi bi-hash text-warning me-1"></i>Referral Code: <strong class="text-white font-monospace"><?= e($refCode) ?></strong></span>
                <span>&bull;</span>
                <span><i class="bi bi-lightning-charge-fill text-warning me-1"></i>Instant wallet crediting upon enrollment</span>
            </div>
        </div>

        <div class="col-lg-4 text-lg-end">
            <div class="bg-white bg-opacity-10 backdrop-blur rounded-4 p-4 text-start border border-white border-opacity-20 d-inline-block w-100 shadow-sm" style="max-width: 320px;">
                <div class="text-white-50 small fw-bold text-uppercase mb-2">Referral Commission Rate</div>
                <div class="display-6 fw-bold text-warning mb-1">₦1,500</div>
                <p class="text-white-50 small mb-3">Per student enrollment</p>
                <div class="pt-2 border-top border-white border-opacity-20">
                    <div class="d-flex justify-content-between text-white small mb-1">
                        <span>Total Referred:</span>
                        <strong><?= $totalReferredCount ?></strong>
                    </div>
                    <div class="d-flex justify-content-between text-white small">
                        <span>Commission Earned:</span>
                        <strong class="text-success">₦<?= number_format($totalReferralEarnings, 2) ?></strong>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-5">
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon blue"><i class="bi bi-people-fill"></i></div>
            <div>
                <div class="stat-value"><?= $totalReferredCount ?></div>
                <p class="stat-label">Total Referrals</p>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon green"><i class="bi bi-person-check-fill"></i></div>
            <div>
                <div class="stat-value"><?= $successfulReferrals ?></div>
                <p class="stat-label">Active Enrollments</p>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon gold"><i class="bi bi-wallet-fill"></i></div>
            <div>
                <div class="stat-value">₦<?= number_format((float)($wallet['total_earned'] ?? 0), 2) ?></div>
                <p class="stat-label">Total Earned</p>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon purple"><i class="bi bi-cash-stack"></i></div>
            <div>
                <div class="stat-value">₦<?= number_format((float)($wallet['available_balance'] ?? 0), 2) ?></div>
                <p class="stat-label">Available Payout</p>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-5">

    <div class="col-lg-4">
        <div class="card border-0 shadow-sm rounded-4 p-4 h-100 bg-card">
            <h5 class="fw-bold mb-3 text-main"><i class="bi bi-info-circle-fill text-primary me-2"></i>How It Works</h5>

            <div class="d-flex gap-3 mb-3">
                <div class="rounded-circle bg-primary bg-opacity-10 text-primary fw-bold d-flex align-items-center justify-content-center flex-shrink-0" style="width:36px; height:36px;">1</div>
                <div>
                    <div class="fw-bold text-main small mb-1">Share Your Link</div>
                    <p class="text-muted small mb-0">Copy your unique referral link and share it with potential students.</p>
                </div>
            </div>

            <div class="d-flex gap-3 mb-3">
                <div class="rounded-circle bg-warning bg-opacity-15 text-warning fw-bold d-flex align-items-center justify-content-center flex-shrink-0" style="width:36px; height:36px;">2</div>
                <div>
                    <div class="fw-bold text-main small mb-1">Student Enrolls</div>
                    <p class="text-muted small mb-0">The student signs up on StudyMe and enrolls into their chosen course.</p>
                </div>
            </div>

            <div class="d-flex gap-3">
                <div class="rounded-circle bg-success bg-opacity-10 text-success fw-bold d-flex align-items-center justify-content-center flex-shrink-0" style="width:36px; height:36px;">3</div>
                <div>
                    <div class="fw-bold text-main small mb-1">Get Paid ₦1,500</div>
                    <p class="text-muted small mb-0">₦1,500 is credited straight into your wallet, ready to withdraw to your Nigerian bank account.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card border-0 shadow-sm rounded-4 p-4 h-100 bg-card">
            <h5 class="fw-bold mb-3 text-main"><i class="bi bi-clock-history text-success me-2"></i>Referral Activity</h5>

            <?php if (!empty($referralsList)): ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead class="bg-light">
                            <tr>
                                <th>Referred Student</th>
                                <th>Course</th>
                                <th>Date</th>
                                <th>Bonus</th>
                                <th class="text-end">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($referralsList as $ref): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold text-main"><?= e($ref['referred_name']) ?></div>
                                        <small class="text-muted"><?= e($ref['referred_email']) ?></small>
                                    </td>
                                    <td><?= e($ref['course_title'] ?: 'Enrolled Course') ?></td>
                                    <td class="text-muted"><?= date('M d, Y', strtotime($ref['created_at'])) ?></td>
                                    <td class="fw-bold text-success">₦<?= number_format((float)($ref['bonus_amount'] ?? $bonusPerStudent), 2) ?></td>
                                    <td class="text-end">
                                        <span class="badge <?= ($ref['status'] ?? '') === 'completed' ? 'bg-success' : 'bg-warning text-dark' ?> rounded-pill">
                                            <?= ucfirst($ref['status'] ?? 'pending') ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-5 my-auto">
                    <i class="bi bi-people text-muted fs-1 mb-2 d-block"></i>
                    <h6 class="fw-bold text-main mb-1">No Referrals Yet</h6>
                    <p class="text-muted small mb-3">Copy your referral link above and share it with learners to start earning ₦1,500 per enrollment!</p>
                    <button class="btn btn-warning text-dark rounded-pill px-4 fw-bold" onclick="copyTeacherRefLink()">
                        <i class="bi bi-clipboard me-1"></i> Copy Referral Link
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="modal fade" id="withdrawalModal" tabindex="-1" aria-labelledby="withdrawalModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow-lg">
            <div class="modal-header border-bottom pb-3">
                <h5 class="modal-title fw-bold" id="withdrawalModalLabel">
                    <i class="bi bi-cash-stack text-success me-2"></i> Request Payout
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="<?= url('teacher/referrals.php') ?>">
                <input type="hidden" name="action" value="withdraw">
                <div class="modal-body p-4">
                    <div class="p-3 bg-light rounded-3 mb-3 border">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-muted small">Available Balance:</span>
                            <strong class="fs-5 text-success">₦<?= number_format((float)($wallet['available_balance'] ?? 0), 2) ?></strong>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small">Withdrawal Amount (₦) <span class="text-danger">*</span></label>
                        <input type="number" step="100" min="1000" max="<?= (float)($wallet['available_balance'] ?? 0) ?>" name="amount" class="form-control rounded-3" placeholder="Min. ₦1,000" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small">Bank Name <span class="text-danger">*</span></label>
                        <input type="text" name="bank_name" class="form-control rounded-3" placeholder="e.g. Access Bank, GTBank, Zenith, OPay" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small">Account Number <span class="text-danger">*</span></label>
                        <input type="text" name="account_number" class="form-control rounded-3" placeholder="10-digit NUBAN number" maxlength="10" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small">Account Name <span class="text-danger">*</span></label>
                        <input type="text" name="account_name" class="form-control rounded-3" placeholder="Full name matching bank account" required>
                    </div>
                </div>
                <div class="modal-footer border-top pt-3">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold">Submit Payout Request</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function copyTeacherRefLink() {
    const input = document.getElementById("teacherRefInput");
    input.select();
    input.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(input.value).then(() => {
        const btnText = document.getElementById("copyBtnText");
        btnText.innerText = "Copied!";
        setTimeout(() => {
            btnText.innerText = "Copy Link";
        }, 2500);
    });
}
</script>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
