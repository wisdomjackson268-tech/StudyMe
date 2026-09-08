<?php
/**
 * StudyMe AI Platform — Admin Dashboard
 */
require_once dirname(__DIR__) . '/config/main.php';

secure_page(ROLE_ADMIN);

$user = current_user();
$pdo  = getDBConnection();

// Real stats from DB
$totalUsers    = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalStudents = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='student'")->fetchColumn();
$totalTeachers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='teacher'")->fetchColumn();
$totalCourses  = (int)$pdo->query("SELECT COUNT(*) FROM courses")->fetchColumn();
$totalSubs     = (int)$pdo->query("SELECT COUNT(*) FROM subscriptions WHERE status='active'")->fetchColumn();
$totalRevenue  = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='successful'")->fetchColumn();

// Recent users
$recentUsers = $pdo->query("
    SELECT id, first_name, last_name, email, role, status, created_at
    FROM users ORDER BY created_at DESC LIMIT 6
")->fetchAll(PDO::FETCH_ASSOC);

// Recent payments
$recentPayments = $pdo->query("
    SELECT p.id, p.amount, p.status, p.created_at,
           u.first_name, u.last_name
    FROM payments p JOIN users u ON u.id=p.user_id
    ORDER BY p.created_at DESC LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-5">
    <div class="d-flex align-items-center gap-3">
        <div class="position-relative">
            <?php $dashAdminAvatar = function_exists('get_avatar_url') ? get_avatar_url($user['avatar'] ?? null, $user['first_name'] ?? 'Admin') : ($user['avatar'] ?? ''); ?>
            <a href="<?= url('admin/profile.php') ?>" class="text-decoration-none" title="Click to update admin photo">
                <img src="<?= e($dashAdminAvatar) ?>" 
                     alt="<?= e($user['first_name']) ?>" 
                     class="rounded-circle border border-3 border-primary shadow-sm"
                     style="width: 60px; height: 60px; object-fit: cover;"
                     onerror="this.onerror=null; this.src='https://ui-avatars.com/api/?name=<?= urlencode($user['first_name'] ?? 'Admin') ?>&background=4f46e5&color=ffffff&bold=true';">
                <span class="position-absolute bottom-0 end-0 bg-primary text-white rounded-circle d-flex align-items-center justify-content-center shadow" style="width: 20px; height: 20px; font-size: 10px;">
                    <i class="bi bi-camera-fill"></i>
                </span>
            </a>
        </div>
        <div>
            <p class="text-muted mb-0 fw-semibold" style="font-size:0.85rem;letter-spacing:0.05em;text-transform:uppercase;">
                <i class="bi bi-shield-check-fill text-primary me-1"></i> Admin Command Center
            </p>
            <h1 class="greeting-title mb-0 fs-3">Good day, <?= e($user['first_name']) ?>!</h1>
            <p class="text-muted small mb-0">Platform overview and management controls. <a href="<?= url('admin/profile.php') ?>" class="text-primary text-decoration-none fw-semibold">Edit Photo</a></p>
        </div>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url('admin/profile.php') ?>" class="btn btn-outline-secondary rounded-pill px-3 fw-semibold btn-sm d-flex align-items-center gap-1" title="Update Profile & Photo">
            <i class="bi bi-person-circle"></i> <span>Profile &amp; Photo</span>
        </a>
        <a href="<?= url('admin/users.php') ?>" class="btn btn-outline-primary rounded-pill px-4 fw-bold" data-feedback="click">
            <i class="bi bi-people me-1"></i> Manage Users
        </a>
    </div>
</div>

<!-- Stats Row -->
<div class="row g-4 mb-5">
    <div class="col-sm-6 col-xl-2">
        <div class="stat-card">
            <div class="stat-icon blue"><i class="bi bi-people-fill"></i></div>
            <div><div class="stat-value"><?= number_format($totalUsers) ?></div><p class="stat-label">Total Users</p></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-2">
        <div class="stat-card">
            <div class="stat-icon green"><i class="bi bi-mortarboard-fill"></i></div>
            <div><div class="stat-value"><?= number_format($totalStudents) ?></div><p class="stat-label">Students</p></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-2">
        <div class="stat-card">
            <div class="stat-icon gold"><i class="bi bi-person-badge-fill"></i></div>
            <div><div class="stat-value"><?= number_format($totalTeachers) ?></div><p class="stat-label">Teachers</p></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-2">
        <div class="stat-card">
            <div class="stat-icon purple"><i class="bi bi-collection-play-fill"></i></div>
            <div><div class="stat-value"><?= number_format($totalCourses) ?></div><p class="stat-label">Courses</p></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-2">
        <div class="stat-card">
            <div class="stat-icon blue"><i class="bi bi-award-fill"></i></div>
            <div><div class="stat-value"><?= number_format($totalSubs) ?></div><p class="stat-label">Active Subs</p></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-2">
        <div class="stat-card">
            <div class="stat-icon green"><i class="bi bi-cash-stack"></i></div>
            <div><div class="stat-value">₦<?= number_format($totalRevenue) ?></div><p class="stat-label">Revenue</p></div>
        </div>
    </div>
</div>

<div class="row g-4 mb-5">
    <!-- Recent Users -->
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-header bg-transparent border-0 p-4 d-flex justify-content-between align-items-center">
                <h5 class="fw-bold mb-0">Recent Registrations</h5>
                <a href="<?= url('admin/users.php') ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3">View All</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="text-muted" style="font-size:0.78rem;text-transform:uppercase;letter-spacing:0.05em;">
                        <tr>
                            <th class="ps-4">User</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th class="pe-4">Joined</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentUsers as $u): ?>
                        <tr>
                            <td class="ps-4">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="rounded-circle bg-primary bg-opacity-10 text-primary fw-bold d-flex align-items-center justify-content-center" style="width:34px;height:34px;font-size:0.85rem;">
                                        <?= strtoupper(substr($u['first_name'],0,1)) ?>
                                    </div>
                                    <div>
                                        <div class="fw-semibold"><?= e($u['first_name'].' '.$u['last_name']) ?></div>
                                        <div class="text-muted" style="font-size:0.78rem;"><?= e($u['email']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td><span class="badge bg-secondary bg-opacity-15 text-secondary rounded-pill px-2 py-1"><?= ucfirst(e($u['role'])) ?></span></td>
                            <td>
                                <span class="badge rounded-pill px-2 py-1 <?= $u['status']==='active' ? 'bg-success bg-opacity-15 text-success' : 'bg-warning bg-opacity-15 text-warning' ?>">
                                    <?= ucfirst(e($u['status'])) ?>
                                </span>
                            </td>
                            <td class="pe-4 text-muted"><?= date('d M Y', strtotime($u['created_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Recent Payments -->
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-header bg-transparent border-0 p-4 d-flex justify-content-between align-items-center">
                <h5 class="fw-bold mb-0">Recent Payments</h5>
                <a href="<?= url('admin/payments.php') ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3">View All</a>
            </div>
            <ul class="list-group list-group-flush rounded-4">
                <?php foreach ($recentPayments as $pay): ?>
                <li class="list-group-item border-0 px-4 py-3 d-flex justify-content-between align-items-center">
                    <div>
                        <div class="fw-semibold small"><?= e($pay['first_name'].' '.$pay['last_name']) ?></div>
                        <div class="text-muted" style="font-size:0.78rem;"><?= date('d M Y', strtotime($pay['created_at'])) ?></div>
                    </div>
                    <div class="text-end">
                        <div class="fw-bold">₦<?= number_format($pay['amount']) ?></div>
                        <span class="badge rounded-pill <?= $pay['status']==='successful' ? 'bg-success' : ($pay['status']==='pending' ? 'bg-warning text-dark' : 'bg-danger') ?>"><?= ucfirst($pay['status']) ?></span>
                    </div>
                </li>
                <?php endforeach; ?>
                <?php if (empty($recentPayments)): ?>
                <li class="list-group-item border-0 px-4 py-4 text-center text-muted small">No payments yet.</li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</div>

<!-- Announcements Overview Widget -->
<?php 
$recentAdminAnns = $pdo->query("SELECT a.*, CONCAT(u.first_name,' ',u.last_name) AS author_name FROM announcements a LEFT JOIN users u ON a.created_by = u.id ORDER BY a.created_at DESC LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="card border-0 shadow-sm rounded-4 p-4 mb-5 bg-white">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-megaphone-fill text-warning me-2"></i>Platform Announcements</h5>
        <div>
            <button class="btn btn-primary btn-sm rounded-pill px-3 fw-bold me-2" data-bs-toggle="modal" data-bs-target="#annModal" onclick="window.location.href='<?= url('admin/announcements.php') ?>'">
                <i class="bi bi-plus-circle me-1"></i> Broadcast
            </button>
            <a href="<?= url('admin/announcements.php') ?>" class="small text-decoration-none fw-bold">Manage All &rarr;</a>
        </div>
    </div>

    <?php if (!empty($recentAdminAnns)): ?>
        <div class="list-group list-group-flush">
            <?php foreach ($recentAdminAnns as $ann): ?>
                <?php $stats = get_announcement_telemetry($ann['id']); ?>
                <div class="list-group-item px-0 py-3 border-light d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <div class="fw-bold text-dark mb-1">
                            <a href="<?= url('announcements/view.php?id=' . $ann['id']) ?>" class="text-decoration-none text-dark hover-primary">
                                <?= e($ann['title']) ?>
                            </a>
                            <span class="badge bg-secondary bg-opacity-10 text-secondary rounded-pill ms-1 px-2 text-capitalize">Target: <?= e($ann['target_type']) ?></span>
                        </div>
                        <small class="text-muted">By <?= e($ann['author_name'] ?: 'Admin') ?> · <?= e(mb_strimwidth(strip_tags($ann['content']), 0, 90, '...')) ?></small>
                    </div>
                    <div class="text-end">
                        <div class="small text-success fw-bold"><?= $stats['read_count'] ?> / <?= $stats['recipients_count'] ?> Read</div>
                        <small class="text-muted"><?= date('M d, Y', strtotime($ann['created_at'])) ?></small>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p class="text-muted small mb-0">No platform announcements created yet.</p>
    <?php endif; ?>
</div>

<!-- Admin Quick Actions -->
<h4 class="fw-bold mb-3">Quick Actions</h4>
<div class="row g-3">
    <div class="col-6 col-md-2">
        <a href="<?= url('admin/users.php') ?>" class="card border-0 shadow-sm rounded-4 p-3 text-center text-decoration-none hover-lift d-flex flex-column align-items-center gap-2"><i class="bi bi-people-fill text-primary" style="font-size:1.8rem;"></i><span class="fw-semibold" style="font-size:0.8rem;">Users</span></a>
    </div>
    <div class="col-6 col-md-2">
        <a href="<?= url('admin/courses.php') ?>" class="card border-0 shadow-sm rounded-4 p-3 text-center text-decoration-none hover-lift d-flex flex-column align-items-center gap-2"><i class="bi bi-collection-play-fill text-success" style="font-size:1.8rem;"></i><span class="fw-semibold" style="font-size:0.8rem;">Courses</span></a>
    </div>
    <div class="col-6 col-md-2">
        <a href="<?= url('admin/payments.php') ?>" class="card border-0 shadow-sm rounded-4 p-3 text-center text-decoration-none hover-lift d-flex flex-column align-items-center gap-2"><i class="bi bi-credit-card-fill text-warning" style="font-size:1.8rem;"></i><span class="fw-semibold" style="font-size:0.8rem;">Payments</span></a>
    </div>
    <div class="col-6 col-md-2">
        <a href="<?= url('admin/subscriptions.php') ?>" class="card border-0 shadow-sm rounded-4 p-3 text-center text-decoration-none hover-lift d-flex flex-column align-items-center gap-2"><i class="bi bi-award-fill text-danger" style="font-size:1.8rem;"></i><span class="fw-semibold" style="font-size:0.8rem;">Subscriptions</span></a>
    </div>
    <div class="col-6 col-md-2">
        <a href="<?= url('admin/analytics.php') ?>" class="card border-0 shadow-sm rounded-4 p-3 text-center text-decoration-none hover-lift d-flex flex-column align-items-center gap-2"><i class="bi bi-graph-up-arrow text-info" style="font-size:1.8rem;"></i><span class="fw-semibold" style="font-size:0.8rem;">Analytics</span></a>
    </div>
    <div class="col-6 col-md-2">
        <a href="<?= url('admin/ai-settings.php') ?>" class="card border-0 shadow-sm rounded-4 p-3 text-center text-decoration-none hover-lift d-flex flex-column align-items-center gap-2"><i class="bi bi-robot text-purple" style="font-size:1.8rem;color:#8B5CF6;"></i><span class="fw-semibold" style="font-size:0.8rem;">AI Engine</span></a>
    </div>
</div>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
