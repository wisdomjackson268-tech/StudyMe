<?php

require_once dirname(__DIR__) . '/config/main.php';
require_once BASE_PATH . '/includes/functions/activity.php';

secure_page(ROLE_ADMIN);
$user   = current_user();
$pdo    = getDBConnection();
$userId = (int)$user['id'];

log_user_activity($userId, 'admin_notifications_view', 'Admin viewed notifications page');

$newUsers = [];
try {
    $newUsers = $pdo->query("
        SELECT id, first_name, last_name, email, role, status, created_at
        FROM users
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ORDER BY created_at DESC LIMIT 20
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { error_log($e->getMessage()); }

$pendingTeachers = [];
try {
    $pendingTeachers = $pdo->query("
        SELECT u.id, u.first_name, u.last_name, u.email, u.status, u.created_at
        FROM users u
        WHERE u.role = 'teacher' AND u.status IN ('pending','inactive')
        ORDER BY u.created_at DESC LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { error_log($e->getMessage()); }

$recentActivity = [];
try {
    $recentActivity = $pdo->query("
        SELECT a.*, CONCAT(u.first_name,' ',u.last_name) AS user_name, u.role, u.email
        FROM activity_logs a
        JOIN users u ON a.user_id = u.id
        ORDER BY a.created_at DESC LIMIT 50
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { error_log($e->getMessage()); }

$stats = ['total_users'=>0,'students'=>0,'teachers'=>0,'active_today'=>0];
try {
    $row = $pdo->query("SELECT
        COUNT(*) AS total_users,
        SUM(role='student') AS students,
        SUM(role='teacher') AS teachers,
        SUM(DATE(last_login_at) = CURDATE()) AS active_today
        FROM users")->fetch(PDO::FETCH_ASSOC);
    if ($row) $stats = $row;
} catch (Exception $e) {}

if (isset($_POST['mark_read'])) {
    set_flash('success', 'All notifications marked as read.');
    redirect('admin/notifications.php');
}

include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div>
        <p class="text-muted mb-1 fw-semibold text-uppercase small"><i class="bi bi-bell-fill text-primary me-1"></i> Admin Command Center</p>
        <h2 class="fw-bold mb-0">Notifications &amp; System Alerts</h2>
    </div>
    <form method="POST">
        <button type="submit" name="mark_read" class="btn btn-outline-secondary rounded-pill px-4 fw-semibold shadow-sm">
            <i class="bi bi-check2-all me-1"></i> Mark All Read
        </button>
    </form>
</div>

<?php foreach (get_flash() as $type => $msgs): foreach ($msgs as $msg): ?>
    <div class="alert alert-<?= $type === 'error' ? 'danger' : $type ?> rounded-3 mb-4"><?= e($msg) ?></div>
<?php endforeach; endforeach; ?>

<div class="row g-3 mb-5">
    <?php $statCards = [
        ['icon'=>'bi-people-fill','color'=>'primary','label'=>'Total Users','value'=> (int)$stats['total_users']],
        ['icon'=>'bi-mortarboard-fill','color'=>'success','label'=>'Students','value'=> (int)$stats['students']],
        ['icon'=>'bi-person-workspace','color'=>'info','label'=>'Teachers','value'=> (int)$stats['teachers']],
        ['icon'=>'bi-activity','color'=>'warning','label'=>'Active Today','value'=> (int)$stats['active_today']],
    ];
    foreach ($statCards as $sc): ?>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm rounded-4 p-4 h-100">
            <div class="d-flex align-items-center gap-3">
                <div class="p-2 rounded-3 bg-<?= $sc['color'] ?> bg-opacity-10">
                    <i class="<?= $sc['icon'] ?> text-<?= $sc['color'] ?> fs-4"></i>
                </div>
                <div>
                    <div class="fs-3 fw-black"><?= number_format((int)$sc['value']) ?></div>
                    <div class="text-muted small fw-semibold"><?= $sc['label'] ?></div>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="row g-4">

    <div class="col-lg-6">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-header bg-transparent border-0 pt-4 pb-2 px-4 d-flex align-items-center gap-2">
                <i class="bi bi-person-plus-fill text-success"></i>
                <h6 class="fw-bold mb-0">New Registrations <span class="badge bg-success ms-1"><?= count($newUsers) ?></span></h6>
                <a href="<?= url('admin/users.php') ?>" class="ms-auto small text-decoration-none text-primary fw-semibold">View All</a>
            </div>
            <div class="card-body px-4 pb-4">
                <?php if (empty($newUsers)): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="bi bi-person-x display-5 d-block mb-2 opacity-30"></i>
                        No new registrations in the last 30 days.
                    </div>
                <?php else: ?>
                <div class="d-flex flex-column gap-3">
                    <?php foreach ($newUsers as $nu):
                        $roleColor = match($nu['role']) { 'admin'=>'danger', 'teacher'=>'info', default=>'success' };
                        $statusColor = match($nu['status'] ?? 'active') { 'active'=>'success', 'pending'=>'warning', 'suspended'=>'danger', default=>'secondary' };
                    ?>
                    <div class="d-flex align-items-center gap-3 p-3 rounded-3 bg-light">
                        <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold flex-shrink-0"
                             style="width:38px;height:38px;font-size:.8rem;background:linear-gradient(135deg,#6C2BFF,#4f46e5);">
                            <?= strtoupper(substr($nu['first_name'], 0, 1)) ?>
                        </div>
                        <div class="flex-grow-1 overflow-hidden">
                            <div class="fw-semibold small text-truncate"><?= e($nu['first_name'] . ' ' . $nu['last_name']) ?></div>
                            <div class="text-muted" style="font-size:.72rem;" class="text-truncate"><?= e($nu['email']) ?></div>
                        </div>
                        <div class="text-end flex-shrink-0">
                            <span class="badge bg-<?= $roleColor ?> rounded-pill mb-1 d-block"><?= ucfirst($nu['role']) ?></span>
                            <span class="badge bg-<?= $statusColor ?> bg-opacity-15 text-<?= $statusColor ?> rounded-pill" style="font-size:.65rem;"><?= ucfirst($nu['status'] ?? 'active') ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-header bg-transparent border-0 pt-4 pb-2 px-4 d-flex align-items-center gap-2">
                <i class="bi bi-person-badge-fill text-warning"></i>
                <h6 class="fw-bold mb-0">Pending Teachers <span class="badge bg-warning text-dark ms-1"><?= count($pendingTeachers) ?></span></h6>
                <a href="<?= url('admin/teachers.php') ?>" class="ms-auto small text-decoration-none text-primary fw-semibold">Manage</a>
            </div>
            <div class="card-body px-4 pb-4">
                <?php if (empty($pendingTeachers)): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="bi bi-check2-circle display-5 d-block mb-2 opacity-30"></i>
                        <span class="small">No pending teacher applications.</span>
                    </div>
                <?php else: ?>
                <div class="d-flex flex-column gap-3">
                    <?php foreach ($pendingTeachers as $pt): ?>
                    <div class="d-flex align-items-center gap-3 p-3 rounded-3" style="background:#fffbeb;border:1px solid #fde68a;">
                        <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold flex-shrink-0"
                             style="width:38px;height:38px;font-size:.8rem;background:linear-gradient(135deg,#f59e0b,#d97706);">
                            <?= strtoupper(substr($pt['first_name'], 0, 1)) ?>
                        </div>
                        <div class="flex-grow-1 overflow-hidden">
                            <div class="fw-semibold small text-truncate"><?= e($pt['first_name'] . ' ' . $pt['last_name']) ?></div>
                            <div class="text-muted" style="font-size:.72rem;"><?= e($pt['email']) ?></div>
                            <div class="text-muted" style="font-size:.7rem;"><?= date('M j, Y', strtotime($pt['created_at'])) ?></div>
                        </div>
                        <div>
                            <a href="<?= url('admin/teachers.php?action=approve&id=' . $pt['id']) ?>"
                               class="btn btn-sm btn-success rounded-pill px-2 py-1 fw-bold" style="font-size:.7rem;">
                                <i class="bi bi-check-lg"></i> Approve
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-header bg-transparent border-0 pt-4 pb-2 px-4 d-flex align-items-center gap-2">
                <i class="bi bi-activity text-primary"></i>
                <h6 class="fw-bold mb-0">Live Activity Feed <span class="badge bg-primary ms-1"><?= count($recentActivity) ?></span></h6>
                <a href="<?= url('admin/activity.php') ?>" class="ms-auto small text-decoration-none text-primary fw-semibold">Full Log</a>
            </div>
            <div class="card-body px-4 pb-4">
                <?php if (empty($recentActivity)): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="bi bi-clock-history display-5 d-block mb-2 opacity-30"></i>
                        <span class="small">No activity recorded yet.</span>
                    </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle small mb-0">
                        <thead class="text-muted">
                            <tr>
                                <th class="fw-semibold border-0">User</th>
                                <th class="fw-semibold border-0">Role</th>
                                <th class="fw-semibold border-0">Action</th>
                                <th class="fw-semibold border-0">Description</th>
                                <th class="fw-semibold border-0">Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentActivity as $act):
                                $roleColor = match($act['role'] ?? '') { 'admin'=>'danger', 'teacher'=>'info', default=>'success' };
                                $actionColor = match(true) {
                                    str_contains($act['action'], 'login') => 'primary',
                                    str_contains($act['action'], 'register') => 'success',
                                    str_contains($act['action'], 'quiz') => 'warning',
                                    str_contains($act['action'], 'lesson') => 'info',
                                    default => 'secondary'
                                };
                            ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold flex-shrink-0"
                                             style="width:28px;height:28px;font-size:.62rem;background:linear-gradient(135deg,#6C2BFF,#4f46e5);">
                                            <?= strtoupper(substr($act['user_name'] ?? 'U', 0, 1)) ?>
                                        </div>
                                        <span class="fw-semibold text-truncate" style="max-width:120px;"><?= e($act['user_name'] ?? 'Unknown') ?></span>
                                    </div>
                                </td>
                                <td><span class="badge bg-<?= $roleColor ?> rounded-pill"><?= ucfirst($act['role'] ?? 'user') ?></span></td>
                                <td><span class="badge bg-<?= $actionColor ?> bg-opacity-15 text-<?= $actionColor ?> rounded-pill"><?= e($act['action']) ?></span></td>
                                <td class="text-muted text-truncate" style="max-width:200px;"><?= e($act['description']) ?></td>
                                <td class="text-muted text-nowrap"><?= date('M j, g:ia', strtotime($act['created_at'])) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
