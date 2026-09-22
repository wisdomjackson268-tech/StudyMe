<?php

require_once dirname(__DIR__) . '/config/main.php';
require_once BASE_PATH . '/includes/functions/referrals.php';

secure_page(ROLE_ADMIN);

$pdo = getDBConnection();
$message = '';
$messageType = 'success';

if (is_post() && isset($_POST['action']) && $_POST['action'] === 'update_rates') {
    $rateTeacher    = (float)($_POST['bonus_rate_teacher'] ?? 1000);
    $rateUniversity = (float)($_POST['bonus_rate_university'] ?? 1000);
    $rateSecondary  = (float)($_POST['bonus_rate_secondary'] ?? 500);
    $rateTechnology = (float)($_POST['bonus_rate_technology'] ?? 1500);

    $stmtSet = $pdo->prepare("INSERT INTO settings (setting_key, setting_value, setting_type) VALUES (?, ?, 'text') ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    $stmtSet->execute(['bonus_rate_teacher', number_format($rateTeacher, 2, '.', '')]);
    $stmtSet->execute(['bonus_rate_university', number_format($rateUniversity, 2, '.', '')]);
    $stmtSet->execute(['bonus_rate_secondary', number_format($rateSecondary, 2, '.', '')]);
    $stmtSet->execute(['bonus_rate_technology', number_format($rateTechnology, 2, '.', '')]);

    set_flash('success', 'Referral bonus rates updated successfully!');
    redirect('admin/referrals.php');
}

if (is_post() && isset($_POST['action']) && $_POST['action'] === 'process_withdrawal') {
    $wId    = (int)($_POST['withdrawal_id'] ?? 0);
    $status = $_POST['status'] === 'completed' ? 'completed' : 'rejected';
    $notes  = trim($_POST['admin_notes'] ?? '');

    $stmtW = $pdo->prepare("SELECT * FROM withdrawals WHERE id = ? LIMIT 1");
    $stmtW->execute([$wId]);
    $wRow = $stmtW->fetch(PDO::FETCH_ASSOC);

    if ($wRow && $wRow['status'] === 'pending') {
        $pdo->beginTransaction();
        try {
            $stmtUpdW = $pdo->prepare("UPDATE withdrawals SET status = ?, admin_notes = ?, processed_at = NOW() WHERE id = ?");
            $stmtUpdW->execute([$status, $notes, $wId]);

            if ($status === 'rejected') {
                $stmtRefund = $pdo->prepare("
                    UPDATE wallets
                    SET available_balance = available_balance + ?,
                        total_withdrawn = GREATEST(0, total_withdrawn - ?)
                    WHERE user_id = ?
                ");
                $stmtRefund->execute([$wRow['amount'], $wRow['amount'], $wRow['user_id']]);
            }

            $stmtNotif = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, created_at) VALUES (?, ?, ?, 'withdrawal', NOW())");
            $notifTitle = $status === 'completed' ? 'Withdrawal Completed! 💸' : 'Withdrawal Request Rejected';
            $notifMsg   = $status === 'completed'
                ? 'Your withdrawal of ₦' . number_format($wRow['amount'], 2) . ' to ' . $wRow['bank_name'] . ' (' . $wRow['account_number'] . ') has been processed.'
                : 'Your withdrawal request of ₦' . number_format($wRow['amount'], 2) . ' was rejected. Funds have been refunded to your wallet.';
            $stmtNotif->execute([$wRow['user_id'], $notifTitle, $notifMsg]);

            $pdo->commit();
            set_flash('success', 'Withdrawal #' . $wId . ' marked as ' . strtoupper($status));
            redirect('admin/referrals.php');
        } catch (Exception $e) {
            $pdo->rollBack();
            set_flash('error', 'Failed to process withdrawal: ' . $e->getMessage());
        }
    }
}

$currentRates = get_referral_bonus_rates();

$teacherCommissions = (float)$pdo->query("
    SELECT COALESCE(SUM(bonus_amount), 0) FROM referrals WHERE applied_rule = 'teacher_commission' AND status = 'completed'
")->fetchColumn();

$uniBonuses = (float)$pdo->query("
    SELECT COALESCE(SUM(bonus_amount), 0) FROM referrals WHERE applied_rule = 'university_student' AND status = 'completed'
")->fetchColumn();

$secBonuses = (float)$pdo->query("
    SELECT COALESCE(SUM(bonus_amount), 0) FROM referrals WHERE applied_rule = 'secondary_student' AND status = 'completed'
")->fetchColumn();

$techBonuses = (float)$pdo->query("
    SELECT COALESCE(SUM(bonus_amount), 0) FROM referrals WHERE applied_rule = 'technology_student' AND status = 'completed'
")->fetchColumn();

$totalIncoming = (float)$pdo->query("
    SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'successful'
")->fetchColumn();

$totalCompletedWithdrawals = (float)$pdo->query("
    SELECT COALESCE(SUM(amount), 0) FROM withdrawals WHERE status = 'completed'
")->fetchColumn();

$totalPendingWithdrawals = (float)$pdo->query("
    SELECT COALESCE(SUM(amount), 0) FROM withdrawals WHERE status = 'pending'
")->fetchColumn();

$totalBonusesPaid = $teacherCommissions + $uniBonuses + $secBonuses + $techBonuses;

$filterCategory = trim($_GET['category'] ?? 'all');
$whereClause = "1=1";
$params = [];

if ($filterCategory === 'teacher') {
    $whereClause .= " AND r.referrer_role = 'teacher'";
} elseif ($filterCategory === 'technology') {
    $whereClause .= " AND (r.course_category = 'technology' OR r.referrer_category = 'technology' OR r.applied_rule = 'technology_student')";
} elseif ($filterCategory === 'university') {
    $whereClause .= " AND (r.course_category = 'university' OR r.referrer_category = 'university' OR r.applied_rule = 'university_student')";
} elseif ($filterCategory === 'secondary') {
    $whereClause .= " AND (r.course_category LIKE '%secondary%' OR r.referrer_category LIKE '%secondary%' OR r.applied_rule = 'secondary_student')";
}

$stmtAllRefs = $pdo->prepare("
    SELECT r.*,
           CONCAT(u1.first_name, ' ', u1.last_name) AS referrer_name,
           u1.email AS referrer_email,
           CONCAT(u2.first_name, ' ', u2.last_name) AS referred_name,
           u2.email AS referred_email,
           c.title AS course_title,
           p.status AS payment_status
    FROM referrals r
    JOIN users u1 ON r.referrer_id = u1.id
    JOIN users u2 ON r.referred_user_id = u2.id
    LEFT JOIN courses c ON r.course_id = c.id
    LEFT JOIN payments p ON r.payment_id = p.id
    WHERE $whereClause
    ORDER BY r.created_at DESC
    LIMIT 100
");
$stmtAllRefs->execute($params);
$referralRecords = $stmtAllRefs->fetchAll(PDO::FETCH_ASSOC);

$stmtWithdrawals = $pdo->query("
    SELECT w.*, CONCAT(u.first_name, ' ', u.last_name) AS user_name, u.email, u.role
    FROM withdrawals w
    JOIN users u ON w.user_id = u.id
    ORDER BY FIELD(w.status, 'pending', 'completed', 'rejected'), w.created_at DESC
    LIMIT 50
")->fetchAll(PDO::FETCH_ASSOC);

include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<div class="container-fluid py-4">

    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
        <div>
            <h1 class="h3 fw-bold mb-1">Referral System &amp; Financial Overview</h1>
            <p class="text-muted small mb-0">Monitor referral bonuses, category rules, teacher commissions, incoming payments, and student withdrawals.</p>
        </div>
        <button type="button" class="btn btn-outline-primary rounded-pill fw-bold btn-sm" data-bs-toggle="modal" data-bs-target="#ratesModal">
            <i class="bi bi-sliders me-1"></i> Edit Bonus Rates
        </button>
    </div>

    <div class="row g-3 mb-5">
        <div class="col-sm-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border-start border-4 border-primary">
                <div class="text-muted small fw-bold text-uppercase mb-1">Teacher Commissions</div>
                <div class="fs-4 fw-bold text-primary">₦<?= number_format($teacherCommissions, 2) ?></div>
                <small class="text-muted" style="font-size:0.75rem;">Rate: ₦<?= number_format($currentRates['teacher'], 0) ?> / referral</small>
            </div>
        </div>

        <div class="col-sm-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border-start border-4 border-info">
                <div class="text-muted small fw-bold text-uppercase mb-1">University Bonuses</div>
                <div class="fs-4 fw-bold text-info">₦<?= number_format($uniBonuses, 2) ?></div>
                <small class="text-muted" style="font-size:0.75rem;">Rate: ₦<?= number_format($currentRates['university'], 0) ?> / referral</small>
            </div>
        </div>

        <div class="col-sm-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border-start border-4 border-warning">
                <div class="text-muted small fw-bold text-uppercase mb-1">Secondary Bonuses</div>
                <div class="fs-4 fw-bold text-warning">₦<?= number_format($secBonuses, 2) ?></div>
                <small class="text-muted" style="font-size:0.75rem;">Rate: ₦<?= number_format($currentRates['secondary'], 0) ?> / referral</small>
            </div>
        </div>

        <div class="col-sm-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border-start border-4 border-success">
                <div class="text-muted small fw-bold text-uppercase mb-1">Technology Bonuses</div>
                <div class="fs-4 fw-bold text-success">₦<?= number_format($techBonuses, 2) ?></div>
                <small class="text-muted" style="font-size:0.75rem;">Rate: ₦<?= number_format($currentRates['technology'], 0) ?> / referral</small>
            </div>
        </div>

        <div class="col-sm-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-dark text-white">
                <div class="text-white-50 small fw-bold text-uppercase mb-1">Total Incoming Payments</div>
                <div class="fs-4 fw-bold text-success">₦<?= number_format($totalIncoming, 2) ?></div>
                <small class="text-white-50" style="font-size:0.75rem;">Platform gross revenue</small>
            </div>
        </div>

        <div class="col-sm-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
                <div class="text-muted small fw-bold text-uppercase mb-1">Completed Withdrawals</div>
                <div class="fs-4 fw-bold text-dark">₦<?= number_format($totalCompletedWithdrawals, 2) ?></div>
                <small class="text-muted" style="font-size:0.75rem;">Disbursed to users</small>
            </div>
        </div>

        <div class="col-sm-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
                <div class="text-muted small fw-bold text-uppercase mb-1">Pending Withdrawals</div>
                <div class="fs-4 fw-bold text-warning">₦<?= number_format($totalPendingWithdrawals, 2) ?></div>
                <small class="text-muted" style="font-size:0.75rem;">Awaiting admin payout</small>
            </div>
        </div>

        <div class="col-sm-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
                <div class="text-muted small fw-bold text-uppercase mb-1">Total Referral Payouts</div>
                <div class="fs-4 fw-bold text-primary">₦<?= number_format($totalBonusesPaid, 2) ?></div>
                <small class="text-muted" style="font-size:0.75rem;">Sum of all awarded bonuses</small>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 mb-5 bg-white overflow-hidden">
        <div class="card-header bg-white py-3 px-4 border-0 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <h5 class="fw-bold mb-0">Referral Activity &amp; Transaction Audit</h5>

            <div class="btn-group btn-group-sm" role="group" aria-label="Category Filters">
                <a href="<?= url('admin/referrals.php?category=all') ?>" class="btn <?= $filterCategory==='all'?'btn-primary':'btn-outline-secondary' ?>">All</a>
                <a href="<?= url('admin/referrals.php?category=technology') ?>" class="btn <?= $filterCategory==='technology'?'btn-primary':'btn-outline-secondary' ?>">Technology</a>
                <a href="<?= url('admin/referrals.php?category=university') ?>" class="btn <?= $filterCategory==='university'?'btn-primary':'btn-outline-secondary' ?>">University</a>
                <a href="<?= url('admin/referrals.php?category=secondary') ?>" class="btn <?= $filterCategory==='secondary'?'btn-primary':'btn-outline-secondary' ?>">Secondary</a>
                <a href="<?= url('admin/referrals.php?category=teacher') ?>" class="btn <?= $filterCategory==='teacher'?'btn-primary':'btn-outline-secondary' ?>">Teacher</a>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small text-uppercase text-muted">
                    <tr>
                        <th class="ps-4">Referrer</th>
                        <th>Role</th>
                        <th>Referred Student</th>
                        <th>Referred Course</th>
                        <th>Category</th>
                        <th>Payment</th>
                        <th>Bonus</th>
                        <th>Applied Rule</th>
                        <th>Status</th>
                        <th class="pe-4">Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($referralRecords)): ?>
                        <?php foreach ($referralRecords as $ref): ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-bold text-dark"><?= e($ref['referrer_name']) ?></div>
                                    <div class="small text-muted font-monospace"><?= e($ref['referrer_email']) ?></div>
                                </td>
                                <td>
                                    <span class="badge bg-secondary bg-opacity-10 text-secondary rounded-pill px-2 py-1 small text-capitalize">
                                        <?= e($ref['referrer_role']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark"><?= e($ref['referred_name']) ?></div>
                                    <div class="small text-muted font-monospace"><?= e($ref['referred_email']) ?></div>
                                </td>
                                <td><?= e($ref['course_title'] ?: 'Pending Enrollment') ?></td>
                                <td>
                                    <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-2 py-1 small">
                                        <?= e($ref['course_category'] ?: $ref['referrer_category'] ?: 'N/A') ?>
                                    </span>
                                </td>
                                <td class="fw-bold">₦<?= number_format((float)$ref['payment_amount'], 2) ?></td>
                                <td class="fw-bold text-success">₦<?= number_format((float)$ref['bonus_amount'], 2) ?></td>
                                <td>
                                    <span class="badge bg-light text-dark border font-monospace small">
                                        <?= e($ref['applied_rule']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($ref['status'] === 'completed'): ?>
                                        <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 py-1">Completed</span>
                                    <?php elseif ($ref['status'] === 'pending'): ?>
                                        <span class="badge bg-warning bg-opacity-10 text-warning rounded-pill px-3 py-1">Pending</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger bg-opacity-10 text-danger rounded-pill px-3 py-1">Cancelled</span>
                                    <?php endif; ?>
                                </td>
                                <td class="pe-4 small text-muted"><?= date('M d, Y · H:i', strtotime($ref['created_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="10" class="text-center py-5 text-muted">No referral records found for this category filter.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden mb-4">
        <div class="card-header bg-white py-3 px-4 border-0">
            <h5 class="fw-bold mb-0">Withdrawal Requests &amp; Payout Approvals</h5>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small text-uppercase text-muted">
                    <tr>
                        <th class="ps-4">User</th>
                        <th>Role</th>
                        <th>Amount</th>
                        <th>Bank Information</th>
                        <th>Account Name</th>
                        <th>Request Date</th>
                        <th>Status</th>
                        <th class="pe-4 text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($stmtWithdrawals)): ?>
                        <?php foreach ($stmtWithdrawals as $w): ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-bold text-dark"><?= e($w['user_name']) ?></div>
                                    <div class="small text-muted font-monospace"><?= e($w['email']) ?></div>
                                </td>
                                <td>
                                    <span class="badge bg-secondary bg-opacity-10 text-secondary rounded-pill px-2 py-1 small text-capitalize">
                                        <?= e($w['role']) ?>
                                    </span>
                                </td>
                                <td class="fw-bold text-success fs-6">₦<?= number_format((float)$w['amount'], 2) ?></td>
                                <td>
                                    <div class="fw-semibold"><?= e($w['bank_name']) ?></div>
                                    <div class="font-monospace text-muted small"><?= e($w['account_number']) ?></div>
                                </td>
                                <td><?= e($w['account_name']) ?></td>
                                <td class="small text-muted"><?= date('M d, Y · H:i', strtotime($w['created_at'])) ?></td>
                                <td>
                                    <?php if ($w['status'] === 'completed'): ?>
                                        <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 py-1">Completed</span>
                                    <?php elseif ($w['status'] === 'pending'): ?>
                                        <span class="badge bg-warning text-dark rounded-pill px-3 py-1">Pending Action</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger bg-opacity-10 text-danger rounded-pill px-3 py-1">Rejected</span>
                                    <?php endif; ?>
                                </td>
                                <td class="pe-4 text-end">
                                    <?php if ($w['status'] === 'pending'): ?>
                                        <button type="button" class="btn btn-sm btn-success rounded-pill px-3 fw-bold me-1"
                                                onclick="openWithdrawalModal(<?= $w['id'] ?>, 'completed', '<?= e($w['user_name']) ?>', '<?= number_format((float)$w['amount'], 2) ?>')">
                                            Approve Payout
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-danger rounded-pill px-3 fw-semibold"
                                                onclick="openWithdrawalModal(<?= $w['id'] ?>, 'rejected', '<?= e($w['user_name']) ?>', '<?= number_format((float)$w['amount'], 2) ?>')">
                                            Reject
                                        </button>
                                    <?php else: ?>
                                        <span class="text-muted small">Processed</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center py-4 text-muted small">No withdrawal requests recorded.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<div class="modal fade" id="ratesModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow-lg">
            <div class="modal-header bg-dark text-white border-0">
                <h5 class="modal-title fw-bold text-white"><i class="bi bi-sliders me-2"></i> Configure Referral Bonus Rates</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="<?= url('admin/referrals.php') ?>" method="POST">
                <input type="hidden" name="action" value="update_rates">
                <div class="modal-body p-4">
                    <p class="text-muted small mb-4">Set server-side bonus values in NGN. Changes apply automatically to all future verified payment referrals.</p>

                    <div class="mb-3">
                        <label for="bonus_rate_technology" class="form-label fw-bold small text-success">Technology Student Referral Bonus (₦)</label>
                        <input type="number" step="0.01" name="bonus_rate_technology" id="bonus_rate_technology" class="form-control py-2" value="<?= number_format($currentRates['technology'], 2, '.', '') ?>" required>
                        <div class="form-text small">Default: ₦1,500.00 for verified ₦10,000 Technology course payments.</div>
                    </div>

                    <div class="mb-3">
                        <label for="bonus_rate_university" class="form-label fw-bold small text-info">University Student Referral Bonus (₦)</label>
                        <input type="number" step="0.01" name="bonus_rate_university" id="bonus_rate_university" class="form-control py-2" value="<?= number_format($currentRates['university'], 2, '.', '') ?>" required>
                        <div class="form-text small">Default: ₦1,000.00 for University course referrals.</div>
                    </div>

                    <div class="mb-3">
                        <label for="bonus_rate_secondary" class="form-label fw-bold small text-warning">Secondary School Student Referral Bonus (₦)</label>
                        <input type="number" step="0.01" name="bonus_rate_secondary" id="bonus_rate_secondary" class="form-control py-2" value="<?= number_format($currentRates['secondary'], 2, '.', '') ?>" required>
                        <div class="form-text small">Default: ₦500.00 for Secondary School referrals.</div>
                    </div>

                    <div class="mb-3">
                        <label for="bonus_rate_teacher" class="form-label fw-bold small text-primary">Teacher Referral Commission (₦)</label>
                        <input type="number" step="0.01" name="bonus_rate_teacher" id="bonus_rate_teacher" class="form-control py-2" value="<?= number_format($currentRates['teacher'], 2, '.', '') ?>" required>
                        <div class="form-text small">Default: ₦1,000.00 teacher referral commission.</div>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold">Save Rates</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="withdrawalActionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow-lg">
            <div class="modal-header bg-dark text-white border-0">
                <h5 class="modal-title fw-bold text-white" id="wModalTitle">Process Withdrawal</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="<?= url('admin/referrals.php') ?>" method="POST">
                <input type="hidden" name="action" value="process_withdrawal">
                <input type="hidden" name="withdrawal_id" id="wModalId" value="0">
                <input type="hidden" name="status" id="wModalStatus" value="completed">

                <div class="modal-body p-4">
                    <p class="mb-3" id="wModalDesc">Are you sure you want to approve this payout?</p>
                    <div class="mb-3">
                        <label for="admin_notes" class="form-label fw-bold small">Admin Note / Transaction Ref (Optional)</label>
                        <textarea name="admin_notes" id="admin_notes" rows="3" class="form-control" placeholder="e.g. Bank Transfer Ref: NIP123456789..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success rounded-pill px-4 fw-bold" id="wModalBtn">Confirm Action</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openWithdrawalModal(id, status, userName, amount) {
    document.getElementById('wModalId').value = id;
    document.getElementById('wModalStatus').value = status;
    const title = document.getElementById('wModalTitle');
    const desc = document.getElementById('wModalDesc');
    const btn = document.getElementById('wModalBtn');

    if (status === 'completed') {
        title.textContent = 'Approve Payout: #' + id;
        desc.innerHTML = 'Approve payout of <strong>₦' + amount + '</strong> for <strong>' + userName + '</strong>?';
        btn.textContent = 'Confirm Payout Approval';
        btn.className = 'btn btn-success rounded-pill px-4 fw-bold';
    } else {
        title.textContent = 'Reject Withdrawal Request: #' + id;
        desc.innerHTML = 'Reject withdrawal of <strong>₦' + amount + '</strong> for <strong>' + userName + '</strong>? Funds will be refunded back to the user’s available wallet.';
        btn.textContent = 'Confirm Rejection & Refund';
        btn.className = 'btn btn-danger rounded-pill px-4 fw-bold';
    }

    const modal = new bootstrap.Modal(document.getElementById('withdrawalActionModal'));
    modal.show();
}
</script>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
