<?php

require_once dirname(__DIR__) . '/config/main.php';

secure_page(ROLE_TEACHER);
if (current_user_role() !== ROLE_ADMIN) {
    require_active_subscription();
}

$user = current_user();
$pdo  = getDBConnection();
$uid  = $user['id'];
$preSelectedCourse = (int)($_GET['course_id'] ?? 0);

$stmt = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ? LIMIT 1");
$stmt->execute([$uid]);
$teacher = $stmt->fetch(PDO::FETCH_ASSOC);
$tid = $teacher ? (int)$teacher['id'] : 0;

if (!$tid) {
    set_flash('error', 'Teacher account required.');
    redirect('teacher/dashboard.php');
}

$stmt = $pdo->prepare("SELECT id, title FROM courses WHERE teacher_id = ? ORDER BY title ASC");
$stmt->execute([$tid]);
$myCourses = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($myCourses)) {
    set_flash('error', 'You must create at least one course before creating tasks.');
    redirect('teacher/create-course.php');
}

if (is_post()) {
    $courseId    = (int)($_POST['course_id'] ?? 0);
    $title       = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $dueDate     = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
    $maxScore    = (float)($_POST['max_score'] ?? 100.00);
    $status      = $_POST['status'] ?? 'published';

    $stmtCheck = $pdo->prepare("SELECT id FROM courses WHERE id = ? AND teacher_id = ? LIMIT 1");
    $stmtCheck->execute([$courseId, $tid]);
    if (!$stmtCheck->fetch()) {
        set_flash('error', 'ACCESS DENIED: Invalid course selected or course does not belong to you.');
    } elseif (empty($title)) {
        set_flash('error', 'Task title is required.');
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO assignments (course_id, title, description, due_date, max_score, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$courseId, $title, $description, $dueDate, $maxScore, $status]);

            set_flash('success', 'Task created successfully!');
            redirect('teacher/tasks.php?course_id=' . $courseId);
        } catch (Exception $e) {
            set_flash('error', 'Failed to create task: ' . $e->getMessage());
        }
    }
}

include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <p class="text-muted mb-1 fw-semibold text-uppercase small"><i class="bi bi-plus-circle text-primary me-1"></i> Instructor Suite</p>
        <h2 class="fw-bold mb-0">Create Course Task / Assignment</h2>
    </div>
    <a href="<?= url('teacher/tasks.php') ?>" class="btn btn-outline-secondary rounded-pill px-4">
        <i class="bi bi-arrow-left me-1"></i> Cancel
    </a>
</div>

<div class="card border-0 shadow-sm rounded-4 p-4 max-w-2xl">
    <form method="POST">
        <div class="mb-3">
            <label class="form-label fw-bold">Select Course <span class="text-danger">*</span></label>
            <select name="course_id" class="form-select rounded-3" required>
                <option value="">-- Select Owned Course --</option>
                <?php foreach ($myCourses as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $preSelectedCourse === (int)$c['id'] ? 'selected' : '' ?>>
                        <?= e($c['title']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div class="form-text">You can only create tasks for courses you own.</div>
        </div>

        <div class="mb-3">
            <label class="form-label fw-bold">Task Title <span class="text-danger">*</span></label>
            <input type="text" name="title" class="form-control rounded-3" placeholder="e.g. Build a Responsive Portfolio Project" required>
        </div>

        <div class="mb-3">
            <label class="form-label fw-bold">Instructions &amp; Description</label>
            <textarea name="description" class="form-control rounded-3" rows="5" placeholder="Detail the submission requirements, format, and grading rubric..."></textarea>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <label class="form-label fw-bold">Maximum Points / Score</label>
                <input type="number" name="max_score" class="form-control rounded-3" value="100" min="1" step="1">
            </div>
            <div class="col-md-6">
                <label class="form-label fw-bold">Due Date (Optional)</label>
                <input type="datetime-local" name="due_date" class="form-control rounded-3">
            </div>
        </div>

        <div class="mb-4">
            <label class="form-label fw-bold">Status</label>
            <select name="status" class="form-select rounded-3">
                <option value="published">Published (Students can view &amp; submit)</option>
                <option value="draft">Draft (Hidden from students)</option>
            </select>
        </div>

        <button type="submit" class="btn btn-primary rounded-pill px-5 fw-bold py-2">
            <i class="bi bi-check-circle me-1"></i> Create Task
        </button>
    </form>
</div>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
