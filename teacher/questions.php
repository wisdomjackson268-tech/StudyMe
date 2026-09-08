<?php
/**
 * StudyMe AI Platform — Teacher Interactive Quiz Question Manager
 */
require_once dirname(__DIR__) . '/config/main.php';

secure_page(ROLE_TEACHER);
if (current_user_role() !== ROLE_ADMIN) {
    require_active_subscription();
}

$user = current_user();
$pdo  = getDBConnection();
$uid  = $user['id'];
$qid  = (int)($_GET['quiz_id'] ?? 0);

// Get teacher ID
$stmt = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ? LIMIT 1");
$stmt->execute([$uid]);
$teacher = $stmt->fetch(PDO::FETCH_ASSOC);
$tid = $teacher ? (int)$teacher['id'] : 0;

// Fetch quiz record with course ownership validation
$stmt = $pdo->prepare("
    SELECT q.*, c.title AS course_title, c.teacher_id
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

if ($quiz['teacher_id'] != $tid && current_user_role() !== ROLE_ADMIN) {
    set_flash('error', 'ACCESS DENIED: You cannot edit questions for another teacher\'s quiz.');
    redirect('teacher/quizzes.php');
}

// Delete question action
if (isset($_GET['action']) && $_GET['action'] === 'delete' && !empty($_GET['question_id'])) {
    $questionId = (int)$_GET['question_id'];
    $stmtDel = $pdo->prepare("DELETE FROM questions WHERE id = ? AND quiz_id = ?");
    $stmtDel->execute([$questionId, $qid]);
    set_flash('success', 'Question deleted successfully.');
    redirect('teacher/questions.php?quiz_id=' . $qid);
}

// Add new question action
if (is_post() && isset($_POST['add_question'])) {
    $questionText = trim($_POST['question'] ?? '');
    $type         = $_POST['question_type'] ?? 'single_choice';
    $points       = (float)($_POST['points'] ?? 1.00);

    if (empty($questionText)) {
        set_flash('error', 'Question prompt is required.');
    } else {
        try {
            $pdo->beginTransaction();

            $stmtQ = $pdo->prepare("INSERT INTO questions (quiz_id, question, question_type, points, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmtQ->execute([$qid, $questionText, $type, $points]);
            $questionId = $pdo->lastInsertId();

            if ($type === 'true_false') {
                $correctVal = $_POST['tf_correct'] ?? 'True';
                $stmtOpt = $pdo->prepare("INSERT INTO question_options (question_id, option_text, is_correct, sort_order) VALUES (?, ?, ?, ?)");
                $stmtOpt->execute([$questionId, 'True', ($correctVal === 'True' ? 1 : 0), 1]);
                $stmtOpt->execute([$questionId, 'False', ($correctVal === 'False' ? 1 : 0), 2]);
            } else {
                // Multiple Choice options
                $options = $_POST['options'] ?? [];
                $correctIndex = (int)($_POST['correct_option'] ?? 0);

                $stmtOpt = $pdo->prepare("INSERT INTO question_options (question_id, option_text, is_correct, sort_order) VALUES (?, ?, ?, ?)");
                foreach ($options as $idx => $optText) {
                    $optText = trim($optText);
                    if ($optText !== '') {
                        $isCorrect = ($idx === $correctIndex) ? 1 : 0;
                        $stmtOpt->execute([$questionId, $optText, $isCorrect, $idx + 1]);
                    }
                }
            }

            $pdo->commit();
            set_flash('success', 'Question added successfully!');
            redirect('teacher/questions.php?quiz_id=' . $qid);
        } catch (Exception $e) {
            $pdo->rollBack();
            set_flash('error', 'Failed to save question: ' . $e->getMessage());
        }
    }
}

// Fetch all existing questions with options
$stmtQuestions = $pdo->prepare("SELECT * FROM questions WHERE quiz_id = ? ORDER BY sort_order ASC, id ASC");
$stmtQuestions->execute([$qid]);
$questionsList = $stmtQuestions->fetchAll(PDO::FETCH_ASSOC);

foreach ($questionsList as &$qItem) {
    $stmtOpt = $pdo->prepare("SELECT * FROM question_options WHERE question_id = ? ORDER BY sort_order ASC, id ASC");
    $stmtOpt->execute([$qItem['id']]);
    $qItem['options'] = $stmtOpt->fetchAll(PDO::FETCH_ASSOC);
}
unset($qItem);

include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
    <div>
        <p class="text-muted mb-1 fw-semibold text-uppercase small">
            <i class="bi bi-patch-question-fill text-success me-1"></i> Quiz Question Builder
        </p>
        <h2 class="fw-bold mb-0"><?= e($quiz['title']) ?></h2>
        <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill mt-1"><?= e($quiz['course_title']) ?></span>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url('teacher/quizzes.php?course_id=' . $quiz['course_id']) ?>" class="btn btn-outline-secondary rounded-pill px-4">
            <i class="bi bi-arrow-left me-1"></i> Back to Quizzes
        </a>
    </div>
</div>

<div class="row g-4">
    <!-- Question Builder Form -->
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm rounded-4 p-4 sticky-top" style="top: 90px;">
            <h5 class="fw-bold mb-3"><i class="bi bi-plus-circle text-primary me-2"></i>Add Question</h5>
            <form method="POST">
                <input type="hidden" name="add_question" value="1">

                <div class="mb-3">
                    <label class="form-label fw-bold">Question Prompt <span class="text-danger">*</span></label>
                    <textarea name="question" class="form-control rounded-3" rows="3" placeholder="e.g. What is the derivative of x^2?" required></textarea>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-7">
                        <label class="form-label fw-bold">Question Type</label>
                        <select name="question_type" id="qTypeSelect" class="form-select rounded-3">
                            <option value="single_choice">Multiple Choice (Single Answer)</option>
                            <option value="true_false">True / False</option>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label fw-bold">Points</label>
                        <input type="number" name="points" class="form-control rounded-3" value="1.0" step="0.5" min="0.5">
                    </div>
                </div>

                <!-- True / False Options Box -->
                <div id="tfBox" class="mb-4 d-none p-3 bg-light rounded-3 border">
                    <label class="form-label fw-bold mb-2">Select Correct Answer:</label>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="tf_correct" id="tfTrue" value="True" checked>
                        <label class="form-check-label fw-semibold text-success" for="tfTrue">True</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="tf_correct" id="tfFalse" value="False">
                        <label class="form-check-label fw-semibold text-danger" for="tfFalse">False</label>
                    </div>
                </div>

                <!-- Multiple Choice Options Box -->
                <div id="mcBox" class="mb-4 p-3 bg-light rounded-3 border">
                    <label class="form-label fw-bold mb-2">Options (Mark radio for correct answer):</label>
                    <?php for ($i = 0; $i < 4; $i++): ?>
                        <div class="input-group mb-2">
                            <div class="input-group-text bg-white">
                                <input class="form-check-input mt-0" type="radio" name="correct_option" value="<?= $i ?>" <?= $i === 0 ? 'checked' : '' ?> aria-label="Correct answer radio">
                            </div>
                            <input type="text" name="options[]" class="form-control" placeholder="Option <?= chr(65 + $i) ?>" <?= $i < 2 ? 'required' : '' ?>>
                        </div>
                    <?php endfor; ?>
                </div>

                <button type="submit" class="btn btn-primary rounded-pill w-100 fw-bold py-2">
                    <i class="bi bi-plus-lg me-1"></i> Add Question
                </button>
            </form>
        </div>
    </div>

    <!-- Existing Questions List -->
    <div class="col-lg-7">
        <h5 class="fw-bold mb-3">Quiz Questions (<?= count($questionsList) ?>)</h5>
        
        <?php if (!empty($questionsList)): ?>
            <div class="d-flex flex-column gap-3">
                <?php foreach ($questionsList as $qIdx => $qItem): ?>
                    <div class="card border-0 shadow-sm rounded-4 p-4 position-relative">
                        <div class="d-flex align-items-start justify-content-between gap-3 mb-2">
                            <div>
                                <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 py-1 me-2">Q<?= $qIdx + 1 ?></span>
                                <span class="badge bg-secondary bg-opacity-10 text-muted rounded-pill px-2"><?= (float)$qItem['points'] ?> pts</span>
                                <h5 class="fw-bold text-main mt-2 mb-1"><?= e($qItem['question']) ?></h5>
                            </div>
                            <a href="<?= url('teacher/questions.php?quiz_id=' . $qid . '&action=delete&question_id=' . $qItem['id']) ?>" 
                               class="btn btn-sm btn-outline-danger rounded-circle p-2"
                               onclick="return confirm('Delete this question?');"
                               title="Delete Question">
                                <i class="bi bi-trash"></i>
                            </a>
                        </div>

                        <!-- Options Display -->
                        <div class="mt-3 pt-3 border-top">
                            <div class="row g-2">
                                <?php foreach ($qItem['options'] as $opt): ?>
                                    <div class="col-12">
                                        <div class="p-2 px-3 rounded-3 border <?= $opt['is_correct'] ? 'bg-success bg-opacity-10 border-success text-success fw-bold' : 'bg-light text-muted' ?> d-flex align-items-center justify-content-between">
                                            <span><?= e($opt['option_text']) ?></span>
                                            <?php if ($opt['is_correct']): ?>
                                                <span class="badge bg-success"><i class="bi bi-check-lg me-1"></i>Correct</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="card border-0 shadow-sm rounded-4 p-5 text-center">
                <i class="bi bi-patch-question text-muted display-4 mb-3"></i>
                <h5 class="fw-bold mb-1">No Questions Added Yet</h5>
                <p class="text-muted small mb-0">Use the form on the left to add your first question.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const qTypeSelect = document.getElementById('qTypeSelect');
    const tfBox = document.getElementById('tfBox');
    const mcBox = document.getElementById('mcBox');

    if (qTypeSelect) {
        qTypeSelect.addEventListener('change', () => {
            if (qTypeSelect.value === 'true_false') {
                tfBox.classList.remove('d-none');
                mcBox.classList.add('d-none');
            } else {
                tfBox.classList.add('d-none');
                mcBox.classList.remove('d-none');
            }
        });
    }
});
</script>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
