<?php
/**
 * StudyMe AI Platform — Teacher Quiz Manager
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

$quizzes = [];
if ($tid) {
    $params = [$tid];
    $sql = "
        SELECT q.*, c.title AS course_title,
               (SELECT COUNT(*) FROM questions qst WHERE qst.quiz_id = q.id) AS question_count,
               (SELECT COUNT(*) FROM quiz_attempts qa WHERE qa.quiz_id = q.id) AS attempt_count
        FROM quizzes q
        JOIN courses c ON q.course_id = c.id
        WHERE c.teacher_id = ?
    ";
    if ($courseFilter > 0) {
        $sql .= " AND q.course_id = ?";
        $params[] = $courseFilter;
    }
    $sql .= " ORDER BY q.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $quizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
    <div>
        <p class="text-muted mb-1 fw-semibold text-uppercase small"><i class="bi bi-patch-question-fill text-success me-1"></i> Instructor Suite</p>
        <h2 class="fw-bold mb-0">Quizzes &amp; Assessments</h2>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url('teacher/create-quiz.php' . ($courseFilter ? '?course_id=' . $courseFilter : '')) ?>" class="btn btn-primary rounded-pill px-4 fw-bold" data-feedback="click">
            <i class="bi bi-plus-circle me-1"></i> Create New Quiz
        </a>
    </div>
</div>

<?php if (!empty($quizzes)): ?>
    <div class="row g-4">
        <?php foreach ($quizzes as $q): ?>
            <div class="col-md-6 col-xl-4">
                <div class="card border-0 shadow-sm rounded-4 p-4 h-100 d-flex flex-column">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 py-1 small"><?= e($q['course_title']) ?></span>
                        <span class="badge <?= $q['status'] === 'published' ? 'bg-success' : 'bg-secondary' ?> rounded-pill">
                            <?= ucfirst($q['status']) ?>
                        </span>
                    </div>

                    <h5 class="fw-bold mb-2 mt-1"><?= e($q['title']) ?></h5>
                    <p class="text-muted small mb-3 flex-grow-1"><?= e(substr($q['description'] ?? '', 0, 90)) ?>...</p>

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

                    <!-- Actions -->
                    <div class="d-flex flex-wrap gap-2 pt-2 border-top mt-auto">
                        <a href="<?= url('teacher/questions.php?quiz_id=' . $q['id']) ?>" class="btn btn-sm btn-primary rounded-pill flex-grow-1 fw-bold">
                            <i class="bi bi-list-check me-1"></i> Questions (<?= (int)$q['question_count'] ?>)
                        </a>
                        <a href="<?= url('teacher/edit-quiz.php?id=' . $q['id']) ?>" class="btn btn-sm btn-outline-secondary rounded-pill">
                            <i class="bi bi-gear me-1"></i> Settings
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="card border-0 shadow-sm rounded-4 p-5 text-center my-4">
        <i class="bi bi-patch-question text-muted display-3 mb-3"></i>
        <h4 class="fw-bold">No Quizzes Created Yet</h4>
        <p class="text-muted max-w-md mx-auto mb-4">Create quizzes and test questions to evaluate your students' understanding.</p>
        <a href="<?= url('teacher/create-quiz.php' . ($courseFilter ? '?course_id=' . $courseFilter : '')) ?>" class="btn btn-primary rounded-pill px-4 py-2 fw-bold align-self-center">Create First Quiz</a>
    </div>
<?php endif; ?>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
