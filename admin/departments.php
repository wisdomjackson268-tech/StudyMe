<?php

require_once dirname(__DIR__) . '/config/main.php';

secure_page(ROLE_ADMIN);

$pdo = getDBConnection();
$departments = $pdo->query("
    SELECT d.*,
           (SELECT COUNT(*) FROM subjects s WHERE s.department_id = d.id) AS subject_count
    FROM departments d
    ORDER BY d.name ASC
")->fetchAll(PDO::FETCH_ASSOC);

include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
    <div>
        <p class="text-muted mb-1 fw-semibold text-uppercase small"><i class="bi bi-building text-primary me-1"></i> Admin Command Center</p>
        <h2 class="fw-bold mb-0">Academic Departments</h2>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4 overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
            <thead class="bg-light">
                <tr>
                    <th class="ps-4">Department Name</th>
                    <th>Slug</th>
                    <th>Subjects Count</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($departments as $d): ?>
                <tr>
                    <td class="ps-4 fw-bold text-main"><?= e($d['name']) ?></td>
                    <td><code><?= e($d['slug']) ?></code></td>
                    <td><span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3"><?= (int)$d['subject_count'] ?></span></td>
                    <td><span class="badge rounded-pill <?= $d['status'] === 'active' ? 'bg-success' : 'bg-secondary' ?>"><?= ucfirst(e($d['status'])) ?></span></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($departments)): ?>
                <tr>
                    <td colspan="4" class="text-center py-5 text-muted">No academic departments defined.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
