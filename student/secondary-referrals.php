<?php

require_once dirname(__DIR__) . '/config/main.php';
require_once BASE_PATH . '/includes/functions/referrals.php';
require_once BASE_PATH . '/includes/functions/activity.php';

secure_page(ROLE_STUDENT);

$user   = current_user();
$userId = (int)$user['id'];
$pdo    = getDBConnection();

log_user_activity($userId, 'viewed_referrals', 'Student accessed Secondary Referral Dashboard');

$refCode = get_user_referral_code($userId);
$refLink = get_base_url() . '/auth/register.php?ref=' . $refCode;
$wallet  = get_user_wallet($userId);

// Fetch Referral statistics from database
$stmtStats = $pdo->prepare("
    SELECT COUNT(*) AS total_referrals,
           COALESCE(SUM(IF(status = 'completed', 1, 0)), 0) AS completed_referrals,
           COALESCE(SUM(IF(status = 'pending', 1, 0)), 0) AS pending_referrals,
           COALESCE(SUM(bonus_amount), 0) AS total_bonus_earned
    FROM referrals
    WHERE referrer_id = ?
");
$stmtStats->execute([$userId]);
$refStats = $stmtStats->fetch(PDO::FETCH_ASSOC);

// Fetch Referral history list
$stmtList = $pdo->prepare("
    SELECT r.*, u.first_name, u.last_name, u.email, u.created_at AS registered_at,
           c.title AS course_name
    FROM referrals r
    JOIN users u ON r.referred_user_id = u.id
    LEFT JOIN courses c ON r.course_id = c.id
    WHERE r.referrer_id = ?
    ORDER BY r.created_at DESC
    LIMIT 50
");
$stmtList->execute([$userId]);
$referralHistory = $stmtList->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Refer & Earn ₦1,000 | StudyMe Secondary';
include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <a href="<?= url('student/secondary-dashboard.php') ?>" class="text-decoration-none text-muted small">
                <i class="bi bi-arrow-left me-1"></i> Dashboard
            </a>
            <span class="text-muted small">&bull;</span>
            <span class="text-muted small">Referral Program</span>
        </div>
        <h2 class="fw-bold mb-0 text-dark">
            <i class="bi bi-gift-fill text-warning me-2"></i> Refer &amp; Earn ₦1,000 per Student
        </h2>
    </div>

    <a href="<?= url('student/secondary-wallet.php') ?>" class="btn btn-outline-success rounded-pill px-4 fw-bold">
        <i class="bi bi-wallet2 me-1"></i> My Wallet (₦<?= number_format((float)($wallet['available_balance'] ?? 0), 2) ?>)
    </a>
</div>

<!-- HERO BANNER WITH REFERRAL LINK -->
<div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 mb-4 text-white" style="background: linear-gradient(135deg, #1E3A8A 0%, #0F766E 100%);">
    <div class="row align-items-center g-4">
        <div class="col-lg-8">
            <span class="badge bg-warning text-dark fw-bold rounded-pill px-3 py-2 mb-3">
                ₦1,000 CASH BONUS PER INVITE
            </span>
            <h1 class="display-6 fw-bold mb-2">Invite Fellow Secondary Students</h1>
            <p class="text-white-75 mb-4" style="max-width: 600px;">
                Share your unique invite link with friends, classmates, and study groups preparing for WAEC, NECO or JAMB. When they enroll, you instantly earn a <strong>₦1,000 cash reward</strong> directly in your wallet!
            </p>

            <div class="bg-white bg-opacity-10 p-3 rounded-4 border border-white border-opacity-25" style="max-width: 620px;">
                <label class="form-label text-white-50 small fw-bold mb-1">Your Exclusive Invite Link:</label>
                <div class="input-group">
                    <input type="text" id="secRefLink" class="form-control bg-white text-dark py-2 py-md-3 font-monospace" value="<?= e($refLink) ?>" readonly>
                    <button type="button" class="btn btn-warning px-4 fw-bold" onclick="copySecRefLink()">
                        <i class="bi bi-copy me-1"></i> Copy Link
                    </button>
                </div>
            </div>
        </div>

        <div class="col-lg-4 text-center">
            <div class="bg-white p-4 rounded-4 text-dark shadow">
                <div class="text-muted small fw-bold mb-1">Available for Withdrawal</div>
                <h2 class="display-6 fw-bold text-success mb-2">
                    ₦<?= number_format((float)($wallet['available_balance'] ?? 0), 2) ?>
                </h2>
                <div class="d-grid gap-2">
                    <a href="https://api.whatsapp.com/send?text=<?= urlencode("Hey! I'm preparing for WAEC, NECO & JAMB on StudyMe with AI tutoring and past questions. Sign up with my link to start studying: " . $refLink) ?>" target="_blank" class="btn btn-success rounded-pill fw-bold">
                        <i class="bi bi-whatsapp me-1"></i> Share on WhatsApp
                    </a>
                    <a href="<?= url('student/secondary-wallet.php') ?>" class="btn btn-outline-primary rounded-pill fw-bold">
                        <i class="bi bi-bank me-1"></i> Request Bank Payout
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- REFERRAL STATS METRICS -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm rounded-4 p-4 h-100 bg-white border">
            <small class="text-muted fw-bold">Total Referred</small>
            <h3 class="fw-bold text-dark mb-0"><?= (int)$refStats['total_referrals'] ?></h3>
            <span class="text-muted small">Registered Students</span>
        </div>
    </div>

    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm rounded-4 p-4 h-100 bg-white border">
            <small class="text-muted fw-bold">Successful Referrals</small>
            <h3 class="fw-bold text-success mb-0"><?= (int)$refStats['completed_referrals'] ?></h3>
            <span class="text-success small fw-semibold"><i class="bi bi-check-circle-fill"></i> Bonus Credited</span>
        </div>
    </div>

    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm rounded-4 p-4 h-100 bg-white border">
            <small class="text-muted fw-bold">Pending Enrolments</small>
            <h3 class="fw-bold text-warning mb-0"><?= (int)$refStats['pending_referrals'] ?></h3>
            <span class="text-muted small">Awaiting Payment</span>
        </div>
    </div>

    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm rounded-4 p-4 h-100 bg-white border">
            <small class="text-muted fw-bold">Total Earned</small>
            <h3 class="fw-bold text-primary mb-0">₦<?= number_format((float)$refStats['total_bonus_earned'], 2) ?></h3>
            <span class="text-muted small">Lifetime Commission</span>
        </div>
    </div>
</div>

<!-- HOW IT WORKS & ANTI-FRAUD RULES -->
<div class="card border-0 shadow-sm rounded-4 p-4 mb-4 bg-white border">
    <h5 class="fw-bold text-dark mb-3"><i class="bi bi-shield-check text-success me-2"></i> How StudyMe Secondary Referrals Work</h5>
    <div class="row g-3">
        <div class="col-md-4">
            <div class="p-3 bg-light rounded-3 border h-100">
                <div class="badge bg-primary rounded-circle mb-2" style="width: 28px; height: 28px; display: inline-flex; align-items: center; justify-content: center;">1</div>
                <h6 class="fw-bold text-dark">Share Your Link</h6>
                <p class="text-muted small mb-0">Copy your referral link and share it on WhatsApp study groups or social media.</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="p-3 bg-light rounded-3 border h-100">
                <div class="badge bg-primary rounded-circle mb-2" style="width: 28px; height: 28px; display: inline-flex; align-items: center; justify-content: center;">2</div>
                <h6 class="fw-bold text-dark">Friend Signs Up &amp; Enrolls</h6>
                <p class="text-muted small mb-0">Your friend creates their secondary school account and joins our subject prep bundle.</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="p-3 bg-light rounded-3 border h-100">
                <div class="badge bg-primary rounded-circle mb-2" style="width: 28px; height: 28px; display: inline-flex; align-items: center; justify-content: center;">3</div>
                <h6 class="fw-bold text-dark">Get ₦1,000 Credited</h6>
                <p class="text-muted small mb-0">Your ₦1,000 bonus is credited automatically to your wallet balance for withdrawal to your Nigerian bank account.</p>
            </div>
        </div>
    </div>
</div>

<!-- REFERRAL HISTORY TABLE -->
<div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-5 bg-white border">
    <div class="card-header bg-white p-3 p-md-4 border-bottom">
        <h5 class="fw-bold text-dark mb-0">
            <i class="bi bi-clock-history text-primary me-2"></i> Referral Activity Ledger
        </h5>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
            <thead class="bg-light">
                <tr>
                    <th class="ps-4">Referred Student</th>
                    <th>Date Registered</th>
                    <th>Status</th>
                    <th>Bonus Earned</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($referralHistory)): ?>
                    <?php foreach ($referralHistory as $rf): ?>
                    <tr>
                        <td class="ps-4">
                            <div class="fw-bold text-dark"><?= e($rf['first_name'] . ' ' . substr($rf['last_name'], 0, 1) . '.') ?></div>
                            <small class="text-muted"><?= e($rf['email']) ?></small>
                        </td>
                        <td class="text-muted"><?= date('M d, Y h:i A', strtotime($rf['created_at'])) ?></td>
                        <td>
                            <?php if ($rf['status'] === 'completed'): ?>
                                <span class="badge bg-success rounded-pill px-3 py-1">Completed</span>
                            <?php elseif ($rf['status'] === 'pending'): ?>
                                <span class="badge bg-warning text-dark rounded-pill px-3 py-1">Pending Enrollment</span>
                            <?php else: ?>
                                <span class="badge bg-secondary rounded-pill px-3 py-1"><?= ucfirst(e($rf['status'])) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="fw-bold <?= (float)$rf['bonus_amount'] > 0 ? 'text-success' : 'text-muted' ?>">
                            <?= (float)$rf['bonus_amount'] > 0 ? '+₦' . number_format((float)$rf['bonus_amount'], 2) : '₦0.00' ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" class="text-center py-5 text-muted">
                            <i class="bi bi-people fs-1 d-block mb-2 text-muted"></i>
                            You haven't referred any students yet. Share your referral link above to start earning ₦1,000 per student!
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function copySecRefLink() {
    const input = document.getElementById("secRefLink");
    if (!input) return;
    input.select();
    input.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(input.value).then(() => {
        if (typeof StudyMeFeedback !== 'undefined') {
            StudyMeFeedback.success("Referral link copied to clipboard!");
        } else {
            alert("Referral link copied to clipboard!");
        }
    });
}
</script>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
