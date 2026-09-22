<?php

require_once dirname(__DIR__) . '/config/main.php';

secure_page(ROLE_ADMIN);

$pdo = getDBConnection();

$stmt = $pdo->prepare("
    SELECT c.id, c.title, CONCAT(u.first_name, ' ', u.last_name) AS teacher_name
    FROM courses c
    JOIN teachers t ON c.teacher_id = t.id
    JOIN users u ON t.user_id = u.id
    ORDER BY c.title ASC
");
$stmt->execute();
$allCourses = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (is_post()) {
    $courseId    = (int)($_POST['course_id'] ?? 0);
    $title       = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $dueDate     = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
    $maxScore    = (float)($_POST['max_score'] ?? 100.00);
    $status      = $_POST['status'] ?? 'published';

    if (empty($courseId) || empty($title)) {
        set_flash('error', 'Please select a course and enter a task title.');
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO assignments (course_id, title, description, due_date, max_score, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$courseId, $title, $description, $dueDate, $maxScore, $status]);

            set_flash('success', 'Task created successfully!');
            redirect('admin/tasks.php');
        } catch (Exception $e) {
            set_flash('error', 'Failed to create task: ' . $e->getMessage());
        }
    }
}

include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <p class="text-muted mb-1 fw-semibold text-uppercase small"><i class="bi bi-plus-circle text-primary me-1"></i> Admin Command Center</p>
        <h2 class="fw-bold mb-0">Create Task (Admin)</h2>
    </div>
    <a href="<?= url('admin/tasks.php') ?>" class="btn btn-outline-secondary rounded-pill px-4">
        <i class="bi bi-arrow-left me-1"></i> Cancel
    </a>
</div>

<div class="card border-0 shadow-sm rounded-4 p-4 max-w-2xl">
    <form method="POST">
        <div class="mb-3">
            <label class="form-label fw-bold">Select Target Course &amp; Owner <span class="text-danger">*</span></label>
            <select name="course_id" class="form-select rounded-3" required>
                <option value="">-- Select Course (Course Owner) --</option>
                <?php foreach ($allCourses as $c): ?>
                    <option value="<?= $c['id'] ?>">
                        <?= e($c['title']) ?> &mdash; Owner: <?= e($c['teacher_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label fw-bold">Task Title <span class="text-danger">*</span></label>
            <input type="text" name="title" class="form-control rounded-3" placeholder="e.g. System Design Project Task" required>
        </div>

        <div class="mb-3">
            <label class="form-label fw-bold">Instructions &amp; Description</label>
            <textarea name="description" class="form-control rounded-3" rows="4" placeholder="Detailed assignment prompt..."></textarea>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <label class="form-label fw-bold">Maximum Score</label>
                <input type="number" name="max_score" class="form-control rounded-3" value="100" min="1">
            </div>
            <div class="col-md-6">
                <label class="form-label fw-bold">Due Date (Optional)</label>
                <input type="datetime-local" name="due_date" class="form-control rounded-3">
            </div>
        </div>

        <div class="mb-4">
            <label class="form-label fw-bold">Status</label>
            <select name="status" class="form-select rounded-3">
                <option value="published">Published</option>
                <option value="draft">Draft</option>
            </select>
        </div>

        <button type="submit" class="btn btn-primary rounded-pill px-5 fw-bold py-2">
            <i class="bi bi-check-circle me-1"></i> Create Task
        </button>
    </form>
</div>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
