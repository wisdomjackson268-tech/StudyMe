<?php

require_once dirname(__DIR__) . '/config/main.php';

secure_page(ROLE_ADMIN);

$pdo = getDBConnection();

$stmt = $pdo->prepare("
    SELECT a.*, c.title AS course_title,
           CONCAT(u.first_name, ' ', u.last_name) AS teacher_name,
           (SELECT COUNT(*) FROM assignment_submissions sub WHERE sub.assignment_id = a.id) AS submission_count
    FROM assignments a
    JOIN courses c ON a.course_id = c.id
    JOIN teachers t ON c.teacher_id = t.id
    JOIN users u ON t.user_id = u.id
    ORDER BY a.created_at DESC
");
$stmt->execute();
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
    <div>
        <p class="text-muted mb-1 fw-semibold text-uppercase small"><i class="bi bi-list-task text-primary me-1"></i> Admin Command Center</p>
        <h2 class="fw-bold mb-0">All Course Tasks &amp; Assignments</h2>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url('admin/create-task.php') ?>" class="btn btn-primary rounded-pill px-4 fw-bold">
            <i class="bi bi-plus-circle me-1"></i> Create Task
        </a>
    </div>
</div>

<?php if (!empty($tasks)): ?>
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">Task Title</th>
                        <th>Course</th>
                        <th>Course Owner</th>
                        <th>Max Points</th>
                        <th>Due Date</th>
                        <th>Submissions</th>
                        <th class="text-end pe-4">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tasks as $t): ?>
                        <tr>
                            <td class="ps-4">
                                <div class="fw-bold text-main"><?= e($t['title']) ?></div>
                                <small class="text-muted"><?= e(substr($t['description'] ?? '', 0, 70)) ?>...</small>
                            </td>
                            <td><span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 py-1"><?= e($t['course_title']) ?></span></td>
                            <td><span class="fw-semibold small"><i class="bi bi-person-badge text-warning me-1"></i><?= e($t['teacher_name']) ?></span></td>
                            <td class="fw-bold text-main"><?= number_format($t['max_score'], 0) ?> pts</td>
                            <td class="small text-muted"><?= $t['due_date'] ? date('M d, Y', strtotime($t['due_date'])) : 'No due date' ?></td>
                            <td>
                                <span class="badge bg-info bg-opacity-10 text-info rounded-pill px-3 py-1 fw-bold">
                                    <i class="bi bi-file-earmark-check me-1"></i><?= (int)$t['submission_count'] ?> submissions
                                </span>
                            </td>
                            <td class="text-end pe-4">
                                <span class="badge <?= $t['status'] === 'published' ? 'bg-success' : 'bg-secondary' ?> rounded-pill">
                                    <?= ucfirst($t['status']) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php else: ?>
    <div class="card border-0 shadow-sm rounded-4 p-5 text-center my-4">
        <i class="bi bi-list-check text-muted display-3 mb-3"></i>
        <h4 class="fw-bold">No Tasks Created</h4>
        <p class="text-muted max-w-md mx-auto mb-0">No assignments currently exist in the database.</p>
    </div>
<?php endif; ?>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
