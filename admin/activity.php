<?php
/**
 * StudyMe AI Platform — Admin Platform-Wide Telemetry & Activity Stream
 */
require_once dirname(__DIR__) . '/config/main.php';

secure_page(ROLE_ADMIN);

$pdo = getDBConnection();

$stmt = $pdo->prepare("
    SELECT a.*, u.first_name, u.last_name, u.email, u.role, c.title AS course_title
    FROM activity_logs a
    JOIN users u ON a.user_id = u.id
    LEFT JOIN courses c ON a.course_id = c.id
    ORDER BY a.created_at DESC
    LIMIT 40
");
$stmt->execute();
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <p class="text-muted mb-1 fw-semibold text-uppercase small"><i class="bi bi-activity text-primary me-1"></i> Admin Command Center</p>
        <h2 class="fw-bold mb-0">Platform Activity Telemetry</h2>
    </div>
</div>

<?php if (!empty($logs)): ?>
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">User</th>
                        <th>Role</th>
                        <th>Action</th>
                        <th>Description</th>
                        <th>Course Context</th>
                        <th class="text-end pe-4">Timestamp</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $l): ?>
                        <tr>
                            <td class="ps-4">
                                <div class="fw-bold text-main small"><?= e($l['first_name'] . ' ' . $l['last_name']) ?></div>
                                <small class="text-muted" style="font-size:0.75rem;"><?= e($l['email']) ?></small>
                            </td>
                            <td>
                                <span class="badge <?= $l['role'] === 'admin' ? 'bg-danger' : ($l['role'] === 'teacher' ? 'bg-warning text-dark' : 'bg-primary') ?> rounded-pill px-2 py-1 small">
                                    <?= ucfirst($l['role']) ?>
                                </span>
                            </td>
                            <td class="fw-bold text-main small"><?= e($l['action']) ?></td>
                            <td class="text-muted small"><?= e($l['description']) ?></td>
                            <td>
                                <?php if (!empty($l['course_title'])): ?>
                                    <span class="badge bg-light text-muted border"><?= e($l['course_title']) ?></span>
                                <?php else: ?>
                                    <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end pe-4 small text-muted font-monospace">
                                <?= date('M d, g:i:s A', strtotime($l['created_at'])) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php else: ?>
    <div class="card border-0 shadow-sm rounded-4 p-5 text-center">
        <i class="bi bi-activity text-muted display-4 mb-3"></i>
        <h5 class="fw-bold">No Platform Telemetry Logs</h5>
        <p class="text-muted small mb-0">User logins, course interactions, and system events will appear here in real-time.</p>
    </div>
<?php endif; ?>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
