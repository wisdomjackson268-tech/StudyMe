<?php
/**
 * StudyMe AI Platform — Admin Quiz Management & Moderation
 */
require_once dirname(__DIR__) . '/config/main.php';

secure_page(ROLE_ADMIN);

$pdo     = getDBConnection();
$success = '';
$errors  = [];

// Handle Quiz Status / Delete / Create
if (is_post()) {
    $action = trim($_POST['action'] ?? '');
    $quizId = (int)($_POST['quiz_id'] ?? 0);

    if ($action === 'toggle_status' && $quizId > 0) {
        $newStatus = trim($_POST['new_status'] ?? 'published');
        $pdo->prepare("UPDATE quizzes SET status = ? WHERE id = ?")->execute([$newStatus, $quizId]);
        $success = 'Quiz status updated.';
    } elseif ($action === 'delete' && $quizId > 0) {
        $pdo->prepare("DELETE FROM quizzes WHERE id = ?")->execute([$quizId]);
        $success = 'Quiz deleted successfully.';
    } elseif ($action === 'create_quiz') {
        $courseId = (int)($_POST['course_id'] ?? 0);
        $title    = trim($_POST['title'] ?? '');
        $desc     = trim($_POST['description'] ?? '');
        $pass     = (float)($_POST['passing_score'] ?? 50.0);
        $limit    = (int)($_POST['time_limit_minutes'] ?? 15);

        if (empty($title) || $courseId <= 0) {
            $errors[] = 'Quiz title and course selection are required.';
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO quizzes (course_id, title, description, time_limit_minutes, passing_score, status, created_at)
                VALUES (?, ?, ?, ?, ?, 'published', NOW())
            ");
            $stmt->execute([$courseId, $title, $desc, $limit, $pass]);
            $newQuizId = $pdo->lastInsertId();
            $success = 'Quiz created! Now you can add questions.';
            redirect('admin/questions.php?quiz_id=' . $newQuizId);
        }
    }
}

$stmt = $pdo->prepare("
    SELECT q.*, c.title AS course_title,
           CONCAT(u.first_name, ' ', u.last_name) AS teacher_name,
           (SELECT COUNT(*) FROM questions qst WHERE qst.quiz_id = q.id) AS question_count,
           (SELECT COUNT(*) FROM quiz_attempts qa WHERE qa.quiz_id = q.id) AS attempt_count
    FROM quizzes q
    JOIN courses c ON q.course_id = c.id
    JOIN teachers t ON c.teacher_id = t.id
    JOIN users u ON t.user_id = u.id
    ORDER BY q.created_at DESC
");
$stmt->execute();
$quizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Courses for create modal
$courses = $pdo->query("SELECT id, title FROM courses WHERE status = 'published' ORDER BY title ASC")->fetchAll(PDO::FETCH_ASSOC);

include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
    <div>
        <p class="text-muted mb-1 fw-semibold text-uppercase small"><i class="bi bi-patch-question-fill text-success me-1"></i> Admin Command Center</p>
        <h2 class="fw-bold mb-0">Platform Quiz Moderation</h2>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url('admin/quiz-results.php') ?>" class="btn btn-outline-primary rounded-pill px-4">
            <i class="bi bi-bar-chart-fill me-1"></i> View Results & Analytics
        </a>
        <button class="btn btn-primary rounded-pill px-4 fw-bold" data-bs-toggle="modal" data-bs-target="#createQuizModal">
            <i class="bi bi-plus-circle me-1"></i> Create Quiz
        </button>
    </div>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger rounded-3 mb-4"><?php foreach ($errors as $e): ?><div><?= e($e) ?></div><?php endforeach; ?></div>
<?php endif; ?>
<?php if ($success): ?>
<div class="alert alert-success rounded-3 mb-4"><i class="bi bi-check-circle me-1"></i> <?= e($success) ?></div>
<?php endif; ?>

<?php if (!empty($quizzes)): ?>
    <div class="row g-4">
        <?php foreach ($quizzes as $q): ?>
            <div class="col-md-6 col-xl-4">
                <div class="card border-0 shadow-sm rounded-4 p-4 h-100 d-flex flex-column hover-lift transition">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 py-1 small"><?= e(substr($q['course_title'], 0, 26)) ?>...</span>
                        <span class="badge <?= $q['status'] === 'published' ? 'bg-success' : 'bg-secondary' ?> rounded-pill">
                            <?= ucfirst($q['status']) ?>
                        </span>
                    </div>

                    <h5 class="fw-bold mb-1 mt-1"><?= e($q['title']) ?></h5>
                    <small class="text-muted mb-2"><i class="bi bi-person-badge text-warning me-1"></i>Owner: <?= e($q['teacher_name']) ?></small>
                    <p class="text-muted small mb-3 flex-grow-1"><?= e(substr($q['description'] ?? 'Evaluation quiz for course concepts.', 0, 90)) ?>...</p>

                    <!-- Stats Bar -->
                    <div class="row g-2 text-center p-3 bg-light rounded-3 mb-3 border border-subtle small">
                        <div class="col-4">
                            <div class="fw-bold text-main"><?= (int)$q['question_count'] ?></div>
                            <div class="text-muted" style="font-size:0.72rem;">Questions</div>
                        </div>
                        <div class="col-4">
                            <div class="fw-bold text-main"><?= (int)$q['attempt_count'] ?></div>
                            <div class="text-muted" style="font-size:0.72rem;">Attempts</div>
                        </div>
                        <div class="col-4">
                            <div class="fw-bold text-success"><?= number_format($q['passing_score'], 0) ?>%</div>
                            <div class="text-muted" style="font-size:0.72rem;">Pass Score</div>
                        </div>
                    </div>

                    <div class="d-flex gap-2 justify-content-between pt-2 border-top">
                        <a href="<?= url('admin/questions.php?quiz_id=' . $q['id']) ?>" class="btn btn-sm btn-primary rounded-pill px-3">
                            <i class="bi bi-pencil-square me-1"></i> Questions (<?= (int)$q['question_count'] ?>)
                        </a>
                        <div class="d-flex gap-1">
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="action" value="toggle_status">
                                <input type="hidden" name="quiz_id" value="<?= $q['id'] ?>">
                                <input type="hidden" name="new_status" value="<?= $q['status'] === 'published' ? 'draft' : 'published' ?>">
                                <button type="submit" class="btn btn-sm btn-outline-<?= $q['status'] === 'published' ? 'warning' : 'success' ?> rounded-pill px-2" title="Toggle Publish">
                                    <i class="bi bi-<?= $q['status'] === 'published' ? 'pause' : 'play' ?>"></i>
                                </button>
                            </form>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Delete this quiz?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="quiz_id" value="<?= $q['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-2" title="Delete Quiz">
                                    <i class="bi bi-trash3"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="card border-0 shadow-sm rounded-4 p-5 text-center my-4">
        <i class="bi bi-patch-question text-muted display-3 mb-3"></i>
        <h4 class="fw-bold">No Quizzes Found</h4>
        <p class="text-muted max-w-md mx-auto mb-3">Create your first quiz to evaluate learner progress.</p>
        <button class="btn btn-primary rounded-pill px-4 mx-auto" data-bs-toggle="modal" data-bs-target="#createQuizModal">
            <i class="bi bi-plus-circle me-1"></i> Create First Quiz
        </button>
    </div>
<?php endif; ?>

<!-- Create Quiz Modal -->
<div class="modal fade" id="createQuizModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Create New Quiz</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-3">
                <form method="POST">
                    <input type="hidden" name="action" value="create_quiz">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Course <span class="text-danger">*</span></label>
                        <select name="course_id" class="form-select rounded-3" required>
                            <option value="">Select Course</option>
                            <?php foreach ($courses as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= e($c['title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Quiz Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control rounded-3" placeholder="e.g. Module 1 Knowledge Assessment" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Description</label>
                        <textarea name="description" class="form-control rounded-3" rows="2" placeholder="Brief overview of quiz topics..."></textarea>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold">Passing Score (%)</label>
                            <input type="number" name="passing_score" class="form-control rounded-3" value="60" min="1" max="100" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">Time Limit (mins)</label>
                            <input type="number" name="time_limit_minutes" class="form-control rounded-3" value="15" min="1" required>
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold">Create & Add Questions</button>
                        <button type="button" class="btn btn-outline-secondary rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
