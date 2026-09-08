<?php
/**
 * StudyMe AI Platform — Admin Teachers Management & Applications
 */
require_once dirname(__DIR__) . '/config/main.php';

secure_page(ROLE_ADMIN);

$pdo     = getDBConnection();
$search  = trim($_GET['search'] ?? '');
$success = '';

// Handle Application Approval/Rejection
if (is_post()) {
    $action = trim($_POST['action'] ?? '');
    $appId  = (int)($_POST['application_id'] ?? 0);

    if ($action === 'approve_app' && $appId > 0) {
        $stmt = $pdo->prepare("SELECT user_id FROM teacher_applications WHERE id = ? LIMIT 1");
        $stmt->execute([$appId]);
        $app = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($app) {
            $pdo->prepare("UPDATE users SET role = 'teacher' WHERE id = ?")->execute([$app['user_id']]);
            $pdo->prepare("UPDATE teacher_applications SET status = 'approved', reviewed_by = ?, reviewed_at = NOW() WHERE id = ?")->execute([current_user('id'), $appId]);
            // Ensure teacher record exists
            $tNum = 'TCH-' . str_pad($app['user_id'], 3, '0', STR_PAD_LEFT);
            $pdo->prepare("INSERT IGNORE INTO teachers (user_id, teacher_number, status) VALUES (?, ?, 'active')")->execute([$app['user_id'], $tNum]);
            $success = 'Teacher application approved and role upgraded.';
        }
    } elseif ($action === 'reject_app' && $appId > 0) {
        $pdo->prepare("UPDATE teacher_applications SET status = 'rejected', reviewed_by = ?, reviewed_at = NOW() WHERE id = ?")->execute([current_user('id'), $appId]);
        $success = 'Teacher application rejected.';
    }
}

// Fetch all teachers
$teachers = $pdo->query("
    SELECT t.*, u.first_name, u.last_name, u.email, u.phone, u.status AS user_status, u.created_at AS user_created_at,
           (SELECT COUNT(*) FROM courses c WHERE c.teacher_id = t.id) AS course_count,
           (SELECT COUNT(DISTINCT e.student_id) FROM enrollments e JOIN courses c ON e.course_id = c.id WHERE c.teacher_id = t.id) AS student_count
    FROM teachers t
    JOIN users u ON t.user_id = u.id
    ORDER BY t.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Fetch Pending Applications
$pendingApps = $pdo->query("
    SELECT ta.*, u.first_name, u.last_name, u.email
    FROM teacher_applications ta
    JOIN users u ON ta.user_id = u.id
    WHERE ta.status = 'pending'
    ORDER BY ta.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
    <div>
        <p class="text-muted mb-1 fw-semibold text-uppercase small"><i class="bi bi-person-badge-fill text-primary me-1"></i> Admin Command Center</p>
        <h2 class="fw-bold mb-0">Instructors & Teacher Applications</h2>
    </div>
    <div class="text-muted small">Total: <strong><?= count($teachers) ?></strong> instructors</div>
</div>

<?php if ($success): ?>
<div class="alert alert-success rounded-3 mb-4"><i class="bi bi-check-circle me-1"></i> <?= e($success) ?></div>
<?php endif; ?>

<!-- Pending Applications -->
<?php if (!empty($pendingApps)): ?>
<div class="card border-0 shadow-sm rounded-4 mb-4 bg-warning bg-opacity-10 border border-warning border-opacity-25">
    <div class="card-header bg-transparent border-0 p-4 pb-0">
        <h5 class="fw-bold text-warning-emphasis mb-0"><i class="bi bi-bell-fill me-2"></i>Pending Teacher Applications (<?= count($pendingApps) ?>)</h5>
    </div>
    <div class="p-4 pt-3">
        <div class="row g-3">
            <?php foreach ($pendingApps as $app): ?>
            <div class="col-md-6">
                <div class="card border-0 shadow-sm rounded-3 p-3 bg-white">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <div class="fw-bold text-main"><?= e($app['first_name'] . ' ' . $app['last_name']) ?></div>
                            <div class="text-muted small"><?= e($app['email']) ?></div>
                        </div>
                        <span class="badge bg-warning text-dark rounded-pill">Pending Review</span>
                    </div>
                    <p class="text-muted small mb-3"><strong>Specialization:</strong> <?= e($app['specialization'] ?: 'General Education') ?></p>
                    <div class="d-flex gap-2">
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="action" value="approve_app">
                            <input type="hidden" name="application_id" value="<?= $app['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-success rounded-pill px-3"><i class="bi bi-check-lg me-1"></i> Approve</button>
                        </form>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="action" value="reject_app">
                            <input type="hidden" name="application_id" value="<?= $app['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-3"><i class="bi bi-x-lg me-1"></i> Reject</button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Teachers Table -->
<div class="card border-0 shadow-sm rounded-4 overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
            <thead class="bg-light" style="font-size:0.78rem; text-transform:uppercase; letter-spacing:0.04em;">
                <tr>
                    <th class="ps-4">Instructor</th>
                    <th>Teacher ID</th>
                    <th>Specialization</th>
                    <th>Courses</th>
                    <th>Total Students</th>
                    <th>Rating</th>
                    <th>Status</th>
                    <th class="text-end pe-4">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($teachers as $t): ?>
                <tr>
                    <td class="ps-4">
                        <div class="d-flex align-items-center gap-3">
                            <div class="rounded-circle bg-warning bg-opacity-15 text-warning fw-bold d-flex align-items-center justify-content-center" style="width:38px;height:38px;">
                                <?= strtoupper(substr($t['first_name'], 0, 1)) ?>
                            </div>
                            <div>
                                <div class="fw-bold text-main"><?= e($t['first_name'] . ' ' . $t['last_name']) ?></div>
                                <div class="text-muted" style="font-size:0.75rem;"><?= e($t['email']) ?></div>
                            </div>
                        </div>
                    </td>
                    <td><code><?= e($t['teacher_number']) ?></code></td>
                    <td><?= e($t['specialization'] ?: 'Instructor') ?></td>
                    <td><span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-2"><?= (int)$t['course_count'] ?></span></td>
                    <td><span class="badge bg-light text-muted border"><?= (int)$t['student_count'] ?></span></td>
                    <td class="text-warning fw-bold"><i class="bi bi-star-fill me-1"></i><?= number_format((float)$t['rating'], 2) ?></td>
                    <td>
                        <span class="badge rounded-pill <?= $t['user_status'] === 'active' ? 'bg-success' : 'bg-secondary' ?>">
                            <?= ucfirst(e($t['user_status'])) ?>
                        </span>
                    </td>
                    <td class="text-end pe-4">
                        <a href="<?= url('admin/users.php?edit_id=' . $t['user_id']) ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                            <i class="bi bi-pencil me-1"></i> Edit Profile
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
