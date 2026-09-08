<?php
/**
 * StudyMe AI Platform — Teacher Edit Quiz Page
 */
require_once dirname(__DIR__) . '/config/main.php';

secure_page(ROLE_TEACHER);
if (current_user_role() !== ROLE_ADMIN) {
    require_active_subscription();
}

$user = current_user();
$pdo  = getDBConnection();
$uid  = $user['id'];
$qid  = (int)($_GET['id'] ?? 0);

// Get teacher ID
$stmt = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ? LIMIT 1");
$stmt->execute([$uid]);
$teacher = $stmt->fetch(PDO::FETCH_ASSOC);
$tid = $teacher ? (int)$teacher['id'] : 0;

// Fetch quiz with ownership check
$stmt = $pdo->prepare("
    SELECT q.*, c.teacher_id 
    FROM quizzes q 
    JOIN courses c ON q.course_id = c.id 
    WHERE q.id = ? LIMIT 1
");
$stmt->execute([$qid]);
$quiz = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$quiz) {
    set_flash('error', 'Quiz not found.');
    redirect('teacher/quizzes.php');
}

// Ownership validation
if ($quiz['teacher_id'] != $tid && current_user_role() !== ROLE_ADMIN) {
    set_flash('error', 'ACCESS DENIED: You cannot edit a quiz belonging to another teacher.');
    redirect('teacher/quizzes.php');
}

if (is_post()) {
    $title        = trim($_POST['title'] ?? '');
    $description  = trim($_POST['description'] ?? '');
    $timeLimit    = !empty($_POST['time_limit_minutes']) ? (int)$_POST['time_limit_minutes'] : null;
    $passingScore = (float)($_POST['passing_score'] ?? 50.00);
    $attempts     = (int)($_POST['attempts_allowed'] ?? 1);
    $status       = $_POST['status'] ?? 'published';

    if (empty($title)) {
        set_flash('error', 'Title is required.');
    } else {
        try {
            $stmt = $pdo->prepare("
                UPDATE quizzes 
                SET title = ?, description = ?, time_limit_minutes = ?, passing_score = ?, attempts_allowed = ?, status = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$title, $description, $timeLimit, $passingScore, $attempts, $status, $qid]);

            set_flash('success', 'Quiz settings updated successfully!');
            redirect('teacher/quizzes.php?course_id=' . $quiz['course_id']);
        } catch (Exception $e) {
            set_flash('error', 'Update error: ' . $e->getMessage());
        }
    }
}

include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <p class="text-muted mb-1 fw-semibold text-uppercase small"><i class="bi bi-gear text-primary me-1"></i> Instructor Suite</p>
        <h2 class="fw-bold mb-0">Edit Quiz Settings: <?= e($quiz['title']) ?></h2>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url('teacher/quizzes.php') ?>" class="btn btn-outline-secondary rounded-pill px-4">
            <i class="bi bi-arrow-left me-1"></i> Back to Quizzes
        </a>
        <a href="<?= url('teacher/questions.php?quiz_id=' . $qid) ?>" class="btn btn-primary rounded-pill px-4 fw-bold">
            <i class="bi bi-list-check me-1"></i> Manage Questions
        </a>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4 p-4 max-w-2xl">
    <form method="POST">
        <div class="mb-3">
            <label class="form-label fw-bold">Quiz Title <span class="text-danger">*</span></label>
            <input type="text" name="title" class="form-control rounded-3" value="<?= e($quiz['title']) ?>" required>
        </div>

        <div class="mb-3">
            <label class="form-label fw-bold">Description &amp; Instructions</label>
            <textarea name="description" class="form-control rounded-3" rows="3"><?= e($quiz['description']) ?></textarea>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-4">
                <label class="form-label fw-bold">Time Limit (Mins)</label>
                <input type="number" name="time_limit_minutes" class="form-control rounded-3" value="<?= (int)$quiz['time_limit_minutes'] ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold">Passing Score (%)</label>
                <input type="number" name="passing_score" class="form-control rounded-3" value="<?= (int)$quiz['passing_score'] ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold">Attempts Allowed</label>
                <input type="number" name="attempts_allowed" class="form-control rounded-3" value="<?= (int)$quiz['attempts_allowed'] ?>">
            </div>
        </div>

        <div class="mb-4">
            <label class="form-label fw-bold">Status</label>
            <select name="status" class="form-select rounded-3">
                <option value="published" <?= $quiz['status'] === 'published' ? 'selected' : '' ?>>Published</option>
                <option value="draft" <?= $quiz['status'] === 'draft' ? 'selected' : '' ?>>Draft</option>
            </select>
        </div>

        <button type="submit" class="btn btn-primary rounded-pill px-5 fw-bold py-2">
            <i class="bi bi-save me-1"></i> Update Quiz Settings
        </button>
    </form>
</div>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
