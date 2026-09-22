<?php

require_once dirname(__DIR__) . '/config/main.php';

secure_page(ROLE_ADMIN);

$pdo     = getDBConnection();
$quizId  = (int)($_GET['quiz_id'] ?? 0);
$errors  = [];
$success = '';

if (is_post()) {
    $action = trim($_POST['action'] ?? '');

    if ($action === 'create_question') {
        $qQuizId  = (int)($_POST['quiz_id'] ?? 0);
        $question = trim($_POST['question'] ?? '');
        $type     = in_array($_POST['question_type'] ?? '', ['single_choice', 'multiple_choice', 'true_false']) ? $_POST['question_type'] : 'single_choice';
        $points   = (float)($_POST['points'] ?? 1.0);
        $options  = $_POST['options'] ?? [];
        $correct  = (int)($_POST['correct_option'] ?? 0);

        if (empty($question)) {
            $errors[] = 'Question text is required.';
        } else {
            $stmt = $pdo->prepare("INSERT INTO questions (quiz_id, question, question_type, points, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->execute([$qQuizId, $question, $type, $points]);
            $questionId = $pdo->lastInsertId();

            if ($type === 'true_false') {
                $tfVal = trim($_POST['tf_correct'] ?? 'True');
                $pdo->prepare("INSERT INTO question_options (question_id, option_text, is_correct, sort_order) VALUES (?, 'True', ?, 1)")->execute([$questionId, $tfVal === 'True' ? 1 : 0]);
                $pdo->prepare("INSERT INTO question_options (question_id, option_text, is_correct, sort_order) VALUES (?, 'False', ?, 2)")->execute([$questionId, $tfVal === 'False' ? 1 : 0]);
            } else {
                foreach ($options as $idx => $optText) {
                    $optText = trim($optText);
                    if (!empty($optText)) {
                        $isCorr = ($idx === $correct) ? 1 : 0;
                        $pdo->prepare("INSERT INTO question_options (question_id, option_text, is_correct, sort_order) VALUES (?, ?, ?, ?)")->execute([$questionId, $optText, $isCorr, $idx + 1]);
                    }
                }
            }
            $success = 'Question added successfully!';
        }
    } elseif ($action === 'delete_question') {
        $qId = (int)($_POST['question_id'] ?? 0);
        $pdo->prepare("DELETE FROM questions WHERE id = ?")->execute([$qId]);
        $success = 'Question deleted.';
    }
}

$quiz = null;
if ($quizId > 0) {
    $stmt = $pdo->prepare("
        SELECT q.*, c.title AS course_title
        FROM quizzes q
        JOIN courses c ON q.course_id = c.id
        WHERE q.id = ? LIMIT 1
    ");
    $stmt->execute([$quizId]);
    $quiz = $stmt->fetch(PDO::FETCH_ASSOC);
}

$quizzes = $pdo->query("SELECT q.id, q.title, c.title AS course_title FROM quizzes q JOIN courses c ON q.course_id = c.id ORDER BY q.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

if (!$quiz && !empty($quizzes)) {
    $quizId = (int)$quizzes[0]['id'];
    $quiz   = $quizzes[0];
}

$questions = [];
if ($quizId > 0) {
    $stmt = $pdo->prepare("
        SELECT q.*,
               (SELECT COUNT(*) FROM question_options qo WHERE qo.question_id = q.id) AS option_count
        FROM questions q
        WHERE q.quiz_id = ?
        ORDER BY q.sort_order ASC, q.id ASC
    ");
    $stmt->execute([$quizId]);
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($questions as &$qst) {
        $stmtOpt = $pdo->prepare("SELECT * FROM question_options WHERE question_id = ? ORDER BY sort_order ASC");
        $stmtOpt->execute([$qst['id']]);
        $qst['options'] = $stmtOpt->fetchAll(PDO::FETCH_ASSOC);
    }
    unset($qst);
}

include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
    <div>
        <p class="text-muted mb-1 fw-semibold text-uppercase small"><i class="bi bi-patch-question-fill text-primary me-1"></i> Admin Command Center</p>
        <h2 class="fw-bold mb-0">Quiz Questions Manager</h2>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url('admin/quizzes.php') ?>" class="btn btn-outline-secondary rounded-pill px-4">
            <i class="bi bi-arrow-left me-1"></i> Back to Quizzes
        </a>
        <?php if ($quiz): ?>
        <button class="btn btn-primary rounded-pill px-4 fw-bold" data-bs-toggle="modal" data-bs-target="#addQuestionModal">
            <i class="bi bi-plus-circle me-1"></i> Add Question
        </button>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger rounded-3 mb-4"><?php foreach ($errors as $e): ?><div><?= e($e) ?></div><?php endforeach; ?></div>
<?php endif; ?>
<?php if ($success): ?>
<div class="alert alert-success rounded-3 mb-4"><i class="bi bi-check-circle me-1"></i> <?= e($success) ?></div>
<?php endif; ?>

<div class="card border-0 shadow-sm rounded-4 p-3 mb-4">
    <form method="GET" class="row g-2 align-items-center">
        <div class="col-auto">
            <label class="fw-bold small text-muted">Select Quiz:</label>
        </div>
        <div class="col-md-6">
            <select name="quiz_id" class="form-select rounded-3" onchange="this.form.submit()">
                <?php foreach ($quizzes as $qz): ?>
                <option value="<?= $qz['id'] ?>" <?= $quizId === (int)$qz['id'] ? 'selected' : '' ?>>
                    <?= e($qz['title']) ?> (<?= e($qz['course_title']) ?>)
                </option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>
</div>

<?php if ($quiz): ?>
<div class="card border-0 shadow-sm rounded-4 p-4 mb-4 bg-primary bg-opacity-10 border border-primary border-opacity-25">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <span class="badge bg-primary rounded-pill px-3 py-1 mb-2">Active Quiz</span>
            <h4 class="fw-bold mb-1"><?= e($quiz['title']) ?></h4>
            <p class="text-muted small mb-0"><i class="bi bi-book me-1"></i>Course: <?= e($quiz['course_title']) ?> | <?= count($questions) ?> Questions</p>
        </div>
        <button class="btn btn-primary rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#addQuestionModal">
            <i class="bi bi-plus-lg me-1"></i> Add New Question
        </button>
    </div>
</div>

<?php if (!empty($questions)): ?>
<div class="d-flex flex-column gap-3">
    <?php foreach ($questions as $idx => $qst): ?>
    <div class="card border-0 shadow-sm rounded-4 p-4">
        <div class="d-flex justify-content-between align-items-start gap-3">
            <div class="flex-grow-1">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="badge bg-secondary bg-opacity-10 text-secondary rounded-pill px-2">Q<?= $idx + 1 ?></span>
                    <span class="badge bg-info bg-opacity-10 text-info rounded-pill px-2 text-capitalize"><?= str_replace('_', ' ', $qst['question_type']) ?></span>
                    <span class="badge bg-light text-muted border rounded-pill px-2"><?= (float)$qst['points'] ?> pts</span>
                </div>
                <h5 class="fw-bold mb-3"><?= e($qst['question']) ?></h5>

                <div class="row g-2">
                    <?php foreach ($qst['options'] as $opt): ?>
                    <div class="col-md-6">
                        <div class="p-3 rounded-3 border d-flex align-items-center gap-2 <?= $opt['is_correct'] ? 'bg-success bg-opacity-10 border-success text-success fw-bold' : 'bg-light text-muted' ?>">
                            <i class="bi <?= $opt['is_correct'] ? 'bi-check-circle-fill text-success' : 'bi-circle text-muted' ?>"></i>
                            <span><?= e($opt['option_text']) ?></span>
                            <?php if ($opt['is_correct']): ?>
                            <span class="badge bg-success ms-auto small">Correct</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <form method="POST" class="d-inline" onsubmit="return confirm('Delete this question?')">
                <input type="hidden" name="action" value="delete_question">
                <input type="hidden" name="question_id" value="<?= $qst['id'] ?>">
                <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-2" title="Delete Question">
                    <i class="bi bi-trash3"></i>
                </button>
            </form>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php else: ?>
<div class="card border-0 shadow-sm rounded-4 p-5 text-center">
    <i class="bi bi-patch-question text-muted display-3 mb-3"></i>
    <h4 class="fw-bold">No Questions Added Yet</h4>
    <p class="text-muted">Create questions for this quiz to test student learning.</p>
    <button class="btn btn-primary rounded-pill px-4 mx-auto" data-bs-toggle="modal" data-bs-target="#addQuestionModal">
        <i class="bi bi-plus-circle me-1"></i> Add First Question
    </button>
</div>
<?php endif; ?>

<div class="modal fade" id="addQuestionModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Add Question to Quiz</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-3">
                <form method="POST">
                    <input type="hidden" name="action" value="create_question">
                    <input type="hidden" name="quiz_id" value="<?= $quizId ?>">

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Question Text <span class="text-danger">*</span></label>
                        <textarea name="question" class="form-control rounded-3" rows="3" required placeholder="Type the question here..."></textarea>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-sm-6">
                            <label class="form-label small fw-bold">Question Type</label>
                            <select name="question_type" class="form-select rounded-3" id="qTypeSelect" onchange="toggleOptionFields(this.value)">
                                <option value="single_choice">Multiple Choice (Single Answer)</option>
                                <option value="true_false">True / False</option>
                            </select>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label small fw-bold">Points</label>
                            <input type="number" name="points" class="form-control rounded-3" value="1.0" step="0.5" min="0.5" required>
                        </div>
                    </div>

                    <div id="mcqOptions">
                        <label class="form-label small fw-bold mb-2">Options (Select radio for correct answer):</label>
                        <?php for ($i = 0; $i < 4; $i++): ?>
                        <div class="input-group mb-2">
                            <div class="input-group-text bg-light">
                                <input class="form-check-input mt-0" type="radio" name="correct_option" value="<?= $i ?>" <?= $i === 0 ? 'checked' : '' ?> title="Mark as correct">
                            </div>
                            <input type="text" name="options[<?= $i ?>]" class="form-control rounded-end-3" placeholder="Option <?= chr(65 + $i) ?>" <?= $i < 2 ? 'required' : '' ?>>
                        </div>
                        <?php endfor; ?>
                    </div>

                    <div id="tfOptions" style="display:none;">
                        <label class="form-label small fw-bold mb-2">Correct Answer:</label>
                        <div class="d-flex gap-4">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="tf_correct" id="tfTrue" value="True" checked>
                                <label class="form-check-label fw-bold text-success" for="tfTrue">True</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="tf_correct" id="tfFalse" value="False">
                                <label class="form-check-label fw-bold text-danger" for="tfFalse">False</label>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex gap-2 mt-4">
                        <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold">
                            <i class="bi bi-check-lg me-1"></i> Save Question
                        </button>
                        <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function toggleOptionFields(type) {
    if (type === 'true_false') {
        document.getElementById('mcqOptions').style.display = 'none';
        document.getElementById('tfOptions').style.display = 'block';
    } else {
        document.getElementById('mcqOptions').style.display = 'block';
        document.getElementById('tfOptions').style.display = 'none';
    }
}
</script>
<?php endif; ?>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
