<?php
/**
 * StudyMe AI Platform — Admin Assignment Submissions Management
 */
require_once dirname(__DIR__) . '/config/main.php';

secure_page(ROLE_ADMIN);

$pdo = getDBConnection();
$submissions = $pdo->query("
    SELECT sub.*, a.title AS assignment_title, c.title AS course_title,
           u.first_name, u.last_name, u.email
    FROM assignment_submissions sub
    JOIN assignments a ON sub.assignment_id = a.id
    JOIN courses c ON a.course_id = c.id
    JOIN students s ON sub.student_id = s.id
    JOIN users u ON s.user_id = u.id
    ORDER BY sub.submitted_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
    <div>
        <p class="text-muted mb-1 fw-semibold text-uppercase small"><i class="bi bi-file-earmark-check-fill text-primary me-1"></i> Admin Command Center</p>
        <h2 class="fw-bold mb-0">Student Task Submissions</h2>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4 overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
            <thead class="bg-light">
                <tr>
                    <th class="ps-4">Student</th>
                    <th>Task / Assignment</th>
                    <th>Course</th>
                    <th>Score</th>
                    <th>Status</th>
                    <th>Date Submitted</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($submissions as $sub): ?>
                <tr>
                    <td class="ps-4">
                        <div class="fw-bold text-main"><?= e($sub['first_name'] . ' ' . $sub['last_name']) ?></div>
                        <div class="text-muted" style="font-size:0.72rem;"><?= e($sub['email']) ?></div>
                    </td>
                    <td class="fw-semibold"><?= e($sub['assignment_title']) ?></td>
                    <td><span class="badge bg-light text-muted border"><?= e($sub['course_title']) ?></span></td>
                    <td class="fw-bold"><?= $sub['score'] !== null ? number_format($sub['score'], 1) : '<span class="text-muted">Not graded</span>' ?></td>
                    <td><span class="badge rounded-pill <?= $sub['status'] === 'graded' ? 'bg-success' : 'bg-warning text-dark' ?>"><?= ucfirst(e($sub['status'])) ?></span></td>
                    <td class="text-muted"><?= date('d M Y', strtotime($sub['submitted_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($submissions)): ?>
                <tr>
                    <td colspan="6" class="text-center py-5 text-muted">No task submissions received yet.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
