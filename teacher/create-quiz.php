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

$stmt = $pdo->prepare("SELECT id, title FROM courses WHERE teacher_id = ? OR id = (SELECT assigned_course_id FROM teachers WHERE id = ?) ORDER BY title ASC");
$stmt->execute([$tid, $tid]);
$myCourses = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($myCourses)) {
    set_flash('error', 'You must create a course before creating quizzes.');
    redirect('teacher/create-course.php');
}

if (is_post()) {
    $courseId     = (int)($_POST['course_id'] ?? 0);
    $title        = trim($_POST['title'] ?? '');
    $description  = trim($_POST['description'] ?? '');
    $timeLimit    = !empty($_POST['time_limit_minutes']) ? (int)$_POST['time_limit_minutes'] : null;
    $passingScore = (float)($_POST['passing_score'] ?? 50.00);
    $attempts     = (int)($_POST['attempts_allowed'] ?? 1);
    $status       = $_POST['status'] ?? 'published';

    $stmtCheck = $pdo->prepare("SELECT id FROM courses WHERE id = ? AND teacher_id = ? LIMIT 1");
    $stmtCheck->execute([$courseId, $tid]);
    if (!$stmtCheck->fetch()) {
        set_flash('error', 'ACCESS DENIED: Invalid course selected or course does not belong to you.');
    } elseif (empty($title)) {
        set_flash('error', 'Quiz title is required.');
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO quizzes (course_id, title, description, time_limit_minutes, passing_score, attempts_allowed, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$courseId, $title, $description, $timeLimit, $passingScore, $attempts, $status]);
            $quizId = $pdo->lastInsertId();

            set_flash('success', 'Quiz created! Now add questions to your assessment.');
            redirect('teacher/questions.php?quiz_id=' . $quizId);
        } catch (Exception $e) {
            set_flash('error', 'Database error: ' . $e->getMessage());
        }
    }
}

include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <p class="text-muted mb-1 fw-semibold text-uppercase small"><i class="bi bi-plus-circle text-success me-1"></i> Instructor Suite</p>
        <h2 class="fw-bold mb-0">Create New Quiz</h2>
    </div>
    <a href="<?= url('teacher/quizzes.php') ?>" class="btn btn-outline-secondary rounded-pill px-4">
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
        </div>

        <div class="mb-3">
            <label class="form-label fw-bold">Quiz Title <span class="text-danger">*</span></label>
            <input type="text" name="title" class="form-control rounded-3" placeholder="e.g. Midterm Evaluation: Fundamentals" required>
        </div>

        <div class="mb-3">
            <label class="form-label fw-bold">Description &amp; Instructions</label>
            <textarea name="description" class="form-control rounded-3" rows="3" placeholder="Instructions for students taking this quiz..."></textarea>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-4">
                <label class="form-label fw-bold">Time Limit (Minutes)</label>
                <input type="number" name="time_limit_minutes" class="form-control rounded-3" placeholder="e.g. 30" min="1">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold">Passing Score (%)</label>
                <input type="number" name="passing_score" class="form-control rounded-3" value="50" min="1" max="100">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold">Allowed Attempts</label>
                <input type="number" name="attempts_allowed" class="form-control rounded-3" value="1" min="1">
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
            <i class="bi bi-arrow-right-circle me-1"></i> Save &amp; Add Questions
        </button>
    </form>
</div>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
