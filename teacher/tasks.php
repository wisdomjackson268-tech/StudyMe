<?php
/**
 * StudyMe AI Platform — Teacher Tasks & Assignments Management
 */
require_once dirname(__DIR__) . '/config/main.php';

secure_page(ROLE_TEACHER);
if (current_user_role() !== ROLE_ADMIN) {
    require_active_subscription();
}

$user = current_user();
$pdo  = getDBConnection();
$uid  = $user['id'];
$courseFilter = (int)($_GET['course_id'] ?? 0);

// Get teacher ID
$stmt = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ? LIMIT 1");
$stmt->execute([$uid]);
$teacher = $stmt->fetch(PDO::FETCH_ASSOC);
$tid = $teacher ? (int)$teacher['id'] : 0;

$tasks = [];
if ($tid) {
    $params = [$tid];
    $sql = "
        SELECT a.*, c.title AS course_title,
               (SELECT COUNT(*) FROM assignment_submissions sub WHERE sub.assignment_id = a.id) AS submission_count
        FROM assignments a
        JOIN courses c ON a.course_id = c.id
        WHERE c.teacher_id = ?
    ";
    if ($courseFilter > 0) {
        $sql .= " AND a.course_id = ?";
        $params[] = $courseFilter;
    }
    $sql .= " ORDER BY a.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
    <div>
        <p class="text-muted mb-1 fw-semibold text-uppercase small"><i class="bi bi-list-task text-primary me-1"></i> Instructor Suite</p>
        <h2 class="fw-bold mb-0">Course Tasks &amp; Assignments</h2>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url('teacher/create-task.php' . ($courseFilter ? '?course_id=' . $courseFilter : '')) ?>" class="btn btn-primary rounded-pill px-4 fw-bold" data-feedback="click">
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
                        <th>Max Points</th>
                        <th>Due Date</th>
                        <th>Submissions</th>
                        <th>Status</th>
                        <th class="text-end pe-4">Actions</th>
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
                            <td class="fw-bold text-main"><?= number_format($t['max_score'], 0) ?> pts</td>
                            <td>
                                <?php if (!empty($t['due_date'])): ?>
                                    <span class="small text-muted"><i class="bi bi-calendar-event me-1"></i><?= date('M d, Y g:i A', strtotime($t['due_date'])) ?></span>
                                <?php else: ?>
                                    <span class="badge bg-light text-muted border">No due date</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-info bg-opacity-10 text-info rounded-pill px-3 py-1 fw-bold">
                                    <i class="bi bi-file-earmark-check me-1"></i><?= (int)$t['submission_count'] ?> submissions
                                </span>
                            </td>
                            <td>
                                <span class="badge <?= $t['status'] === 'published' ? 'bg-success' : 'bg-secondary' ?> rounded-pill">
                                    <?= ucfirst($t['status']) ?>
                                </span>
                            </td>
                            <td class="text-end pe-4">
                                <a href="<?= url('teacher/edit-task.php?id=' . $t['id']) ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                                    <i class="bi bi-pencil-square me-1"></i> Edit
                                </a>
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
        <h4 class="fw-bold">No Tasks Created Yet</h4>
        <p class="text-muted max-w-md mx-auto mb-4">Create tasks and assignments for your students to submit work and test their knowledge.</p>
        <a href="<?= url('teacher/create-task.php' . ($courseFilter ? '?course_id=' . $courseFilter : '')) ?>" class="btn btn-primary rounded-pill px-4 py-2 fw-bold align-self-center">Create First Task</a>
    </div>
<?php endif; ?>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
