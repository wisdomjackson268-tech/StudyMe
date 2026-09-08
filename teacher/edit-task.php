<?php
/**
 * StudyMe AI Platform — Teacher Edit Task Form
 */
require_once dirname(__DIR__) . '/config/main.php';

secure_page(ROLE_TEACHER);
if (current_user_role() !== ROLE_ADMIN) {
    require_active_subscription();
}

$user = current_user();
$pdo  = getDBConnection();
$uid  = $user['id'];
$tid_task = (int)($_GET['id'] ?? 0);

// Resolve teacher ID
$stmt = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ? LIMIT 1");
$stmt->execute([$uid]);
$teacher = $stmt->fetch(PDO::FETCH_ASSOC);
$tid = $teacher ? (int)$teacher['id'] : 0;

// Fetch task
$stmt = $pdo->prepare("
    SELECT a.*, c.teacher_id 
    FROM assignments a 
    JOIN courses c ON a.course_id = c.id 
    WHERE a.id = ? LIMIT 1
");
$stmt->execute([$tid_task]);
$task = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$task) {
    set_flash('error', 'Task not found.');
    redirect('teacher/tasks.php');
}

// Ownership check
if ($task['teacher_id'] != $tid && current_user_role() !== ROLE_ADMIN) {
    set_flash('error', 'ACCESS DENIED: You cannot edit a task belonging to another instructor.');
    redirect('teacher/tasks.php');
}

if (is_post()) {
    $title       = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $dueDate     = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
    $maxScore    = (float)($_POST['max_score'] ?? 100.00);
    $status      = $_POST['status'] ?? 'published';

    if (empty($title)) {
        set_flash('error', 'Title is required.');
    } else {
        try {
            $stmt = $pdo->prepare("
                UPDATE assignments 
                SET title = ?, description = ?, due_date = ?, max_score = ?, status = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$title, $description, $dueDate, $maxScore, $status, $tid_task]);

            set_flash('success', 'Task updated successfully!');
            redirect('teacher/tasks.php?course_id=' . $task['course_id']);
        } catch (Exception $e) {
            set_flash('error', 'Update error: ' . $e->getMessage());
        }
    }
}

include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <p class="text-muted mb-1 fw-semibold text-uppercase small"><i class="bi bi-pencil-square text-primary me-1"></i> Instructor Suite</p>
        <h2 class="fw-bold mb-0">Edit Task: <?= e($task['title']) ?></h2>
    </div>
    <a href="<?= url('teacher/tasks.php') ?>" class="btn btn-outline-secondary rounded-pill px-4">
        <i class="bi bi-arrow-left me-1"></i> Back to Tasks
    </a>
</div>

<div class="card border-0 shadow-sm rounded-4 p-4 max-w-2xl">
    <form method="POST">
        <div class="mb-3">
            <label class="form-label fw-bold">Task Title <span class="text-danger">*</span></label>
            <input type="text" name="title" class="form-control rounded-3" value="<?= e($task['title']) ?>" required>
        </div>

        <div class="mb-3">
            <label class="form-label fw-bold">Instructions &amp; Description</label>
            <textarea name="description" class="form-control rounded-3" rows="5"><?= e($task['description']) ?></textarea>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <label class="form-label fw-bold">Maximum Points</label>
                <input type="number" name="max_score" class="form-control rounded-3" value="<?= (int)$task['max_score'] ?>" min="1">
            </div>
            <div class="col-md-6">
                <label class="form-label fw-bold">Due Date</label>
                <input type="datetime-local" name="due_date" class="form-control rounded-3" value="<?= $task['due_date'] ? date('Y-m-d\TH:i', strtotime($task['due_date'])) : '' ?>">
            </div>
        </div>

        <div class="mb-4">
            <label class="form-label fw-bold">Status</label>
            <select name="status" class="form-select rounded-3">
                <option value="published" <?= $task['status'] === 'published' ? 'selected' : '' ?>>Published</option>
                <option value="draft" <?= $task['status'] === 'draft' ? 'selected' : '' ?>>Draft</option>
            </select>
        </div>

        <button type="submit" class="btn btn-primary rounded-pill px-5 fw-bold py-2">
            <i class="bi bi-save me-1"></i> Update Task
        </button>
    </form>
</div>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
