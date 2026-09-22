<?php

require_once dirname(__DIR__) . '/config/main.php';

secure_page(ROLE_TEACHER);
$user = current_user();
$pdo  = getDBConnection();
$uid  = $user['id'];

$stmt = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ? LIMIT 1");
$stmt->execute([$uid]);
$teacher = $stmt->fetch(PDO::FETCH_ASSOC);
$tid = $teacher ? (int)$teacher['id'] : 0;

$submissions = [];
if ($tid) {
    $stmt = $pdo->prepare("
        SELECT sub.*, a.title AS assignment_title, c.title AS course_title,
               CONCAT(u.first_name, ' ', u.last_name) AS student_name, u.email AS student_email
        FROM assignment_submissions sub
        JOIN assignments a ON sub.assignment_id = a.id
        JOIN courses c ON a.course_id = c.id
        JOIN students s ON sub.student_id = s.id
        JOIN users u ON s.user_id = u.id
        WHERE c.teacher_id = ?
        ORDER BY sub.submitted_at DESC
    ");
    $stmt->execute([$tid]);
    $submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <p class="text-muted mb-1 fw-semibold text-uppercase small"><i class="bi bi-file-earmark-check-fill text-primary me-1"></i> Instructor Suite</p>
        <h2 class="fw-bold mb-0">Student Task Submissions</h2>
    </div>
</div>

<?php if (!empty($submissions)): ?>
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">Student</th>
                        <th>Task &amp; Course</th>
                        <th>Submitted At</th>
                        <th>Score</th>
                        <th class="text-end pe-4">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($submissions as $sub): ?>
                        <tr>
                            <td class="ps-4">
                                <div class="fw-bold text-main"><?= e($sub['student_name']) ?></div>
                                <small class="text-muted"><?= e($sub['student_email']) ?></small>
                            </td>
                            <td>
                                <div class="fw-bold text-main small"><?= e($sub['assignment_title']) ?></div>
                                <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-2 py-1 small"><?= e($sub['course_title']) ?></span>
                            </td>
                            <td class="small text-muted"><?= date('M d, Y g:i A', strtotime($sub['submitted_at'])) ?></td>
                            <td class="fw-bold text-main"><?= $sub['score'] !== null ? number_format($sub['score'], 0) . ' pts' : 'Ungraded' ?></td>
                            <td class="text-end pe-4">
                                <span class="badge <?= $sub['status'] === 'graded' ? 'bg-success' : 'bg-warning text-dark' ?> rounded-pill">
                                    <?= ucfirst($sub['status']) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php else: ?>
    <div class="card border-0 shadow-sm rounded-4 p-5 text-center">
        <i class="bi bi-file-earmark-text text-muted display-4 mb-3"></i>
        <h5 class="fw-bold">No Submissions Pending</h5>
        <p class="text-muted small mb-0">When students submit solutions to your tasks, they will be listed here for grading.</p>
    </div>
<?php endif; ?>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
