<?php
/**
 * StudyMe AI Platform — Admin Subjects Management
 */
require_once dirname(__DIR__) . '/config/main.php';

secure_page(ROLE_ADMIN);

$pdo = getDBConnection();
$subjects = $pdo->query("
    SELECT s.*, d.name AS department_name,
           (SELECT COUNT(*) FROM courses c WHERE c.subject_id = s.id) AS course_count
    FROM subjects s
    LEFT JOIN departments d ON s.department_id = d.id
    ORDER BY s.name ASC
")->fetchAll(PDO::FETCH_ASSOC);

include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
    <div>
        <p class="text-muted mb-1 fw-semibold text-uppercase small"><i class="bi bi-book-half text-primary me-1"></i> Admin Command Center</p>
        <h2 class="fw-bold mb-0">Curriculum Subjects</h2>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4 overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
            <thead class="bg-light">
                <tr>
                    <th class="ps-4">Subject</th>
                    <th>Department</th>
                    <th>Courses Linked</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($subjects as $s): ?>
                <tr>
                    <td class="ps-4 fw-bold text-main"><?= e($s['name']) ?></td>
                    <td><?= e($s['department_name'] ?: 'General Academic') ?></td>
                    <td><span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3"><?= (int)$s['course_count'] ?> courses</span></td>
                    <td><span class="badge rounded-pill <?= $s['status'] === 'active' ? 'bg-success' : 'bg-secondary' ?>"><?= ucfirst(e($s['status'])) ?></span></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($subjects)): ?>
                <tr>
                    <td colspan="4" class="text-center py-5 text-muted">No academic subjects configured.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
