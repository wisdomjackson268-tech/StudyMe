<?php

require_once dirname(__DIR__) . '/config/main.php';

secure_page(ROLE_ADMIN);

$pdo      = getDBConnection();
$currentAdminId = (int)(current_user()['id'] ?? 0);
$user     = current_user();
$errors   = [];
$success  = '';

if (is_post()) {
    $action = trim($_POST['action'] ?? '');
    $targetId = (int)($_POST['user_id'] ?? 0);

    if ($targetId === $currentAdminId && in_array($action, ['deactivate', 'delete'])) {
        $errors[] = 'You cannot deactivate or delete your currently logged-in administrator account.';
    } elseif ($targetId > 0) {
        if ($action === 'deactivate') {
            $pdo->prepare("UPDATE users SET status = 'inactive' WHERE id = ?")->execute([$targetId]);
            $success = 'User account deactivated.';
        } elseif ($action === 'reactivate') {
            $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ?")->execute([$targetId]);
            $success = 'User account reactivated.';
        } elseif ($action === 'delete') {

            $stmt = $pdo->prepare("SELECT COUNT(*) FROM payments WHERE user_id = ? AND status='successful'");
            $stmt->execute([$targetId]);
            if ((int)$stmt->fetchColumn() > 0) {

                $pdo->prepare("UPDATE users SET status = 'inactive' WHERE id = ?")->execute([$targetId]);
                $success = 'User has payment history — account deactivated (not deleted) to preserve financial records.';
            } else {
                $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$targetId]);
                $success = 'User permanently deleted.';
            }
        } elseif ($action === 'edit') {
            $firstName = trim($_POST['first_name'] ?? '');
            $lastName  = trim($_POST['last_name']  ?? '');
            $email     = trim($_POST['email']       ?? '');
            $phone     = trim($_POST['phone']       ?? '');
            $role      = in_array($_POST['role'] ?? '', ['admin','teacher','student']) ? $_POST['role'] : 'student';
            $status    = in_array($_POST['status'] ?? '', ['active','inactive','suspended','pending']) ? $_POST['status'] : 'active';

            if ($targetId === $currentAdminId && $role !== 'admin') {
                $errors[] = 'You cannot change your own admin role.';
            } elseif (empty($firstName) || empty($email)) {
                $errors[] = 'First name and email are required.';
            } else {
                $pdo->prepare("UPDATE users SET first_name=?, last_name=?, email=?, phone=?, role=?, status=?, updated_at=NOW() WHERE id=?")
                    ->execute([$firstName, $lastName, $email, $phone, $role, $status, $targetId]);
                $success = 'User updated successfully.';
            }
        }
    }
}

$search     = trim($_GET['search'] ?? '');
$filterRole = trim($_GET['role']   ?? '');
$filterStatus = trim($_GET['status'] ?? '');
$page       = max(1, (int)($_GET['page'] ?? 1));
$perPage    = 50;
$offset     = ($page - 1) * $perPage;

$whereClauses = [];
$params       = [];

if ($search !== '') {
    $whereClauses[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.username LIKE ?)";
    $s = "%$search%";
    $params = array_merge($params, [$s, $s, $s, $s]);
}
if ($filterRole !== '') {
    $whereClauses[] = "u.role = ?";
    $params[] = $filterRole;
}
if ($filterStatus !== '') {
    $whereClauses[] = "u.status = ?";
    $params[] = $filterStatus;
}

$whereSQL = $whereClauses ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM users u $whereSQL");
$countStmt->execute($params);
$totalUsers = (int)$countStmt->fetchColumn();
$totalPages = ceil($totalUsers / $perPage);

$stmt = $pdo->prepare("
    SELECT u.id, u.first_name, u.last_name, u.username, u.email, u.phone,
           u.role, u.status, u.avatar, u.created_at, u.last_login_at,
           (SELECT c.title FROM enrollments e JOIN courses c ON e.course_id = c.id
            JOIN students s ON e.student_id = s.id WHERE s.user_id = u.id AND e.status='active' LIMIT 1) AS active_course
    FROM users u
    $whereSQL
    ORDER BY u.created_at DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$editUser = null;
if (!empty($_GET['edit_id'])) {
    $stmt2 = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
    $stmt2->execute([(int)$_GET['edit_id']]);
    $editUser = $stmt2->fetch(PDO::FETCH_ASSOC);
}

include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
    <div>
        <p class="text-muted mb-1 fw-semibold text-uppercase small"><i class="bi bi-people-fill text-primary me-1"></i> Admin Command Center</p>
        <h2 class="fw-bold mb-0">Users & Roles Management</h2>
    </div>
    <div class="text-muted small">Total: <strong><?= number_format($totalUsers) ?></strong> users</div>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger rounded-3 mb-4">
    <?php foreach ($errors as $e): ?><div><i class="bi bi-exclamation-triangle me-1"></i> <?= e($e) ?></div><?php endforeach; ?>
</div>
<?php endif; ?>
<?php if ($success): ?>
<div class="alert alert-success rounded-3 mb-4"><i class="bi bi-check-circle me-1"></i> <?= e($success) ?></div>
<?php endif; ?>

<div class="card border-0 shadow-sm rounded-4 p-3 mb-4">
    <form method="GET" class="row g-2 align-items-end">
        <div class="col-md-4">
            <input type="text" name="search" class="form-control rounded-3" placeholder="Search name, email, username…" value="<?= e($search) ?>">
        </div>
        <div class="col-md-2">
            <select name="role" class="form-select rounded-3">
                <option value="">All Roles</option>
                <option value="student"  <?= $filterRole==='student'  ? 'selected':'' ?>>Students</option>
                <option value="teacher"  <?= $filterRole==='teacher'  ? 'selected':'' ?>>Teachers</option>
                <option value="admin"    <?= $filterRole==='admin'    ? 'selected':'' ?>>Admins</option>
            </select>
        </div>
        <div class="col-md-2">
            <select name="status" class="form-select rounded-3">
                <option value="">All Statuses</option>
                <option value="active"   <?= $filterStatus==='active'   ? 'selected':'' ?>>Active</option>
                <option value="inactive" <?= $filterStatus==='inactive' ? 'selected':'' ?>>Inactive</option>
                <option value="suspended"<?= $filterStatus==='suspended'? 'selected':'' ?>>Suspended</option>
                <option value="pending"  <?= $filterStatus==='pending'  ? 'selected':'' ?>>Pending</option>
            </select>
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-primary rounded-pill px-4 w-100">
                <i class="bi bi-search me-1"></i> Filter
            </button>
        </div>
        <div class="col-md-2">
            <a href="<?= url('admin/users.php') ?>" class="btn btn-outline-secondary rounded-pill px-4 w-100">
                <i class="bi bi-x me-1"></i> Clear
            </a>
        </div>
    </form>
</div>

<div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="bg-light" style="font-size:0.8rem; text-transform:uppercase; letter-spacing:0.04em;">
                <tr>
                    <th class="ps-4">User</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Active Course</th>
                    <th>Last Login</th>
                    <th>Joined</th>
                    <th class="text-end pe-4">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td class="ps-4">
                        <div class="d-flex align-items-center gap-3">
                            <?php if (!empty($u['avatar'])): ?>
                                <img src="<?= e($u['avatar']) ?>" class="rounded-circle border" style="width:38px;height:38px;object-fit:cover;" alt="">
                            <?php else: ?>
                                <div class="rounded-circle bg-primary bg-opacity-10 text-primary fw-bold d-flex align-items-center justify-content-center" style="width:38px;height:38px;font-size:0.9rem;">
                                    <?= strtoupper(substr($u['first_name'],0,1)) ?>
                                </div>
                            <?php endif; ?>
                            <div>
                                <div class="fw-bold text-main small"><?= e($u['first_name'].' '.$u['last_name']) ?></div>
                                <div class="text-muted" style="font-size:0.75rem;"><?= e($u['email']) ?></div>
                                <?php if (!empty($u['username'])): ?>
                                <div class="text-muted" style="font-size:0.72rem;">@<?= e($u['username']) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="badge rounded-pill px-3 py-1
                            <?= $u['role']==='admin' ? 'bg-danger' : ($u['role']==='teacher' ? 'bg-warning text-dark' : 'bg-primary') ?>">
                            <?= ucfirst(e($u['role'])) ?>
                        </span>
                    </td>
                    <td>
                        <span class="badge rounded-pill px-2 py-1
                            <?= $u['status']==='active' ? 'bg-success' : ($u['status']==='inactive' ? 'bg-secondary' : 'bg-warning text-dark') ?>">
                            <?= ucfirst(e($u['status'])) ?>
                        </span>
                    </td>
                    <td class="small text-muted">
                        <?= !empty($u['active_course']) ? '<i class="bi bi-play-circle-fill text-primary me-1"></i>'.e(substr($u['active_course'],0,28)).'…' : '<span class="text-muted">—</span>' ?>
                    </td>
                    <td class="small text-muted">
                        <?= !empty($u['last_login_at']) ? date('d M Y', strtotime($u['last_login_at'])) : 'Never' ?>
                    </td>
                    <td class="small text-muted"><?= date('d M Y', strtotime($u['created_at'])) ?></td>
                    <td class="text-end pe-4">
                        <div class="d-flex justify-content-end gap-1">

                            <a href="<?= url('admin/users.php?edit_id='.$u['id']) ?>"
                               class="btn btn-sm btn-outline-primary rounded-pill px-2" title="Edit">
                                <i class="bi bi-pencil-square"></i>
                            </a>

                            <?php if ($u['id'] !== $currentAdminId): ?>
                                <?php if ($u['status'] === 'active'): ?>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="deactivate">
                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-warning rounded-pill px-2"
                                            title="Deactivate"
                                            onclick="return confirm('Deactivate this account?\n\nAll data (enrollments, progress, payments) will be preserved.')">
                                        <i class="bi bi-pause-circle"></i>
                                    </button>
                                </form>
                                <?php else: ?>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="reactivate">
                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-success rounded-pill px-2" title="Reactivate">
                                        <i class="bi bi-play-circle"></i>
                                    </button>
                                </form>
                                <?php endif; ?>

                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-2"
                                            title="Delete"
                                            onclick="return confirm('⚠️ DELETE USER?\n\nThis cannot be undone.\n\nIf the user has payment records, the account will be deactivated instead of deleted to protect financial history.')">
                                        <i class="bi bi-trash3"></i>
                                    </button>
                                </form>
                            <?php else: ?>
                                <span class="badge bg-info bg-opacity-15 text-info border border-info border-opacity-25 rounded-pill px-2" title="This is your account">You</span>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($users)): ?>
                <tr><td colspan="7" class="text-center py-5 text-muted">No users match your search.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($totalPages > 1): ?>
<nav>
    <ul class="pagination justify-content-center gap-1">
        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
        <li class="page-item <?= $p === $page ? 'active' : '' ?>">
            <a class="page-link rounded-3" href="?page=<?= $p ?>&search=<?= urlencode($search) ?>&role=<?= urlencode($filterRole) ?>&status=<?= urlencode($filterStatus) ?>">
                <?= $p ?>
            </a>
        </li>
        <?php endfor; ?>
    </ul>
</nav>
<?php endif; ?>

<?php if ($editUser): ?>
<div class="modal fade show d-block" tabindex="-1" style="background:rgba(0,0,0,0.5);">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Edit User — <?= e($editUser['first_name'].' '.$editUser['last_name']) ?></h5>
                <a href="<?= url('admin/users.php') ?>" class="btn-close"></a>
            </div>
            <div class="modal-body pt-3">
                <form method="POST">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="user_id" value="<?= $editUser['id'] ?>">
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label class="form-label small fw-bold">First Name</label>
                            <input type="text" name="first_name" class="form-control rounded-3" value="<?= e($editUser['first_name']) ?>" required>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label small fw-bold">Last Name</label>
                            <input type="text" name="last_name" class="form-control rounded-3" value="<?= e($editUser['last_name']) ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold">Email</label>
                            <input type="email" name="email" class="form-control rounded-3" value="<?= e($editUser['email']) ?>" required>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label small fw-bold">Phone</label>
                            <input type="tel" name="phone" class="form-control rounded-3" value="<?= e($editUser['phone']) ?>">
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label small fw-bold">Role</label>
                            <select name="role" class="form-select rounded-3" <?= $editUser['id'] === $currentAdminId ? 'disabled' : '' ?>>
                                <option value="student" <?= $editUser['role']==='student'?'selected':'' ?>>Student</option>
                                <option value="teacher" <?= $editUser['role']==='teacher'?'selected':'' ?>>Teacher</option>
                                <option value="admin"   <?= $editUser['role']==='admin'  ?'selected':'' ?>>Admin</option>
                            </select>
                            <?php if ($editUser['id'] === $currentAdminId): ?>
                                <input type="hidden" name="role" value="admin">
                                <small class="text-muted">Cannot change your own role</small>
                            <?php endif; ?>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label small fw-bold">Status</label>
                            <select name="status" class="form-select rounded-3">
                                <option value="active"    <?= $editUser['status']==='active'   ?'selected':'' ?>>Active</option>
                                <option value="inactive"  <?= $editUser['status']==='inactive' ?'selected':'' ?>>Inactive</option>
                                <option value="suspended" <?= $editUser['status']==='suspended'?'selected':'' ?>>Suspended</option>
                                <option value="pending"   <?= $editUser['status']==='pending'  ?'selected':'' ?>>Pending</option>
                            </select>
                        </div>
                    </div>
                    <div class="mt-3 p-3 bg-warning bg-opacity-10 border border-warning border-opacity-25 rounded-3 small text-warning-emphasis">
                        <i class="bi bi-shield-lock me-1"></i> Password is never displayed or editable here. It remains securely hashed.
                    </div>
                    <div class="d-flex gap-2 mt-4">
                        <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold">
                            <i class="bi bi-check-lg me-1"></i> Save Changes
                        </button>
                        <a href="<?= url('admin/users.php') ?>" class="btn btn-outline-secondary rounded-pill px-4">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
