<?php

require_once dirname(__DIR__) . '/config/main.php';

secure_page(ROLE_ADMIN);

$pdo = getDBConnection();
$assignments = $pdo->query("
    SELECT a.*, c.title AS course_title,
           (SELECT COUNT(*) FROM assignment_submissions sub WHERE sub.assignment_id = a.id) AS submission_count
    FROM assignments a
    JOIN courses c ON a.course_id = c.id
    ORDER BY a.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
    <div>
        <p class="text-muted mb-1 fw-semibold text-uppercase small"><i class="bi bi-clipboard-check-fill text-primary me-1"></i> Admin Command Center</p>
        <h2 class="fw-bold mb-0">Course Tasks & Assignments</h2>
    </div>
    <a href="<?= url('admin/create-task.php') ?>" class="btn btn-primary rounded-pill px-4 fw-bold">
        <i class="bi bi-plus-circle me-1"></i> Create Task
    </a>
</div>

<div class="card border-0 shadow-sm rounded-4 overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
            <thead class="bg-light">
                <tr>
                    <th class="ps-4">Task Title</th>
                    <th>Course</th>
                    <th>Max Score</th>
                    <th>Submissions</th>
                    <th>Status</th>
                    <th>Due Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($assignments as $a): ?>
                <tr>
                    <td class="ps-4 fw-bold text-main"><?= e($a['title']) ?></td>
                    <td><?= e($a['course_title']) ?></td>
                    <td><?= (float)$a['max_score'] ?> pts</td>
                    <td><span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3"><?= (int)$a['submission_count'] ?></span></td>
                    <td><span class="badge rounded-pill <?= $a['status'] === 'published' ? 'bg-success' : 'bg-secondary' ?>"><?= ucfirst(e($a['status'])) ?></span></td>
                    <td class="text-muted"><?= !empty($a['due_date']) ? date('d M Y', strtotime($a['due_date'])) : 'Open' ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($assignments)): ?>
                <tr>
                    <td colspan="6" class="text-center py-5 text-muted">No course tasks created yet. Click "Create Task" to assign practical work.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
