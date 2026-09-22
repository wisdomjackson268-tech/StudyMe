<?php

require_once dirname(__DIR__) . '/config/main.php';

secure_page(ROLE_TEACHER);

$user   = current_user();
$userId = $user['id'];
$pdo    = getDBConnection();

$stmt = $pdo->prepare("
    SELECT a.*, c.title AS course_title
    FROM activity_logs a
    LEFT JOIN courses c ON a.course_id = c.id
    WHERE a.user_id = ?
    ORDER BY a.created_at DESC
    LIMIT 30
");
$stmt->execute([$userId]);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <p class="text-muted mb-1 fw-semibold text-uppercase small"><i class="bi bi-activity text-primary me-1"></i> Instructor Suite</p>
        <h2 class="fw-bold mb-0">Instructor Activity Stream</h2>
    </div>
</div>

<?php if (!empty($logs)): ?>
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">Action</th>
                        <th>Description</th>
                        <th>Related Course</th>
                        <th class="text-end pe-4">Date &amp; Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $l): ?>
                        <tr>
                            <td class="ps-4 fw-bold text-main">
                                <i class="bi bi-check-circle-fill text-success me-2"></i><?= e($l['action']) ?>
                            </td>
                            <td class="text-muted small"><?= e($l['description']) ?></td>
                            <td>
                                <?php if (!empty($l['course_title'])): ?>
                                    <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 py-1"><?= e($l['course_title']) ?></span>
                                <?php else: ?>
                                    <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end pe-4 small text-muted font-monospace">
                                <?= date('M d, Y g:i A', strtotime($l['created_at'])) ?>
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
        <h5 class="fw-bold">No Instructor Activity Recorded</h5>
        <p class="text-muted small mb-0">Actions like creating courses, adding lessons, or publishing quizzes will be tracked here.</p>
    </div>
<?php endif; ?>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
