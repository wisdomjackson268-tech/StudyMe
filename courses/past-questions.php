<?php

require_once dirname(__DIR__) . '/config/main.php';

$practiceUser = current_user();
$practiceStudentId = 0;
if (current_user_role() === ROLE_STUDENT && !empty($practiceUser['id'])) {
    $practiceStudentStmt = getDBConnection()->prepare('SELECT s.id FROM students s WHERE s.user_id = ? LIMIT 1');
    $practiceStudentStmt->execute([(int)$practiceUser['id']]);
    $practiceStudentId = (int)$practiceStudentStmt->fetchColumn();
}

if (is_post() && isset($_POST['practice_action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $practicePdo = getDBConnection();
    $action = trim($_POST['practice_action']);
    $attemptId = (int)($_POST['attempt_id'] ?? 0);
    $questionId = (int)($_POST['question_id'] ?? 0);

    if (!$practiceStudentId) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Please sign in as a Secondary School student to save practice progress.']);
        exit;
    }

    try {
        if ($action === 'start') {
            $stmt = $practicePdo->prepare('INSERT INTO secondary_practice_attempts (student_id, exam_type, subject_slug, year, total_questions) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$practiceStudentId, trim($_POST['exam_type'] ?? 'general'), trim($_POST['subject_slug'] ?? ''), (int)($_POST['year'] ?? 0) ?: null, (int)($_POST['total_questions'] ?? 0)]);
            echo json_encode(['success' => true, 'attempt_id' => (int)$practicePdo->lastInsertId()]);
            exit;
        }

        $ownership = $practicePdo->prepare('SELECT id FROM secondary_practice_attempts WHERE id = ? AND student_id = ? LIMIT 1');
        $ownership->execute([$attemptId, $practiceStudentId]);
        if (!$ownership->fetchColumn()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Practice attempt not found.']);
            exit;
        }

        if ($action === 'answer' && $questionId > 0) {
            $selected = strtolower(substr(trim($_POST['selected_option'] ?? ''), 0, 1));
            $correctStmt = $practicePdo->prepare('SELECT LOWER(correct_option) FROM past_questions WHERE id = ? LIMIT 1');
            $correctStmt->execute([$questionId]);
            $correct = strtolower((string)$correctStmt->fetchColumn());
            if (!$correct || !in_array($selected, ['a', 'b', 'c', 'd'], true)) {
                echo json_encode(['success' => false, 'message' => 'Invalid answer.']);
                exit;
            }
            $isCorrect = $selected === $correct ? 1 : 0;
            $stmt = $practicePdo->prepare('INSERT INTO secondary_practice_answers (attempt_id, question_id, selected_option, is_correct) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE selected_option = VALUES(selected_option), is_correct = VALUES(is_correct), answered_at = CURRENT_TIMESTAMP');
            $stmt->execute([$attemptId, $questionId, $selected, $isCorrect]);
            $recount = $practicePdo->prepare('UPDATE secondary_practice_attempts a SET answered_questions = (SELECT COUNT(*) FROM secondary_practice_answers WHERE attempt_id = a.id), correct_answers = (SELECT COALESCE(SUM(is_correct), 0) FROM secondary_practice_answers WHERE attempt_id = a.id) WHERE a.id = ?');
            $recount->execute([$attemptId]);
            echo json_encode(['success' => true, 'correct' => (bool)$isCorrect]);
            exit;
        }

        if ($action === 'complete') {
            $stmt = $practicePdo->prepare('UPDATE secondary_practice_attempts SET completed_at = CURRENT_TIMESTAMP WHERE id = ? AND student_id = ?');
            $stmt->execute([$attemptId, $practiceStudentId]);
            log_user_activity((int)$practiceUser['id'], 'secondary_practice_completed', 'Completed a Secondary School practice drill');
            echo json_encode(['success' => true]);
            exit;
        }

        if ($action === 'bookmark' && $questionId > 0) {
            $stmt = $practicePdo->prepare('INSERT IGNORE INTO secondary_question_bookmarks (student_id, question_id) VALUES (?, ?)');
            $stmt->execute([$practiceStudentId, $questionId]);
            echo json_encode(['success' => true, 'bookmarked' => true]);
            exit;
        }

        if ($action === 'unbookmark' && $questionId > 0) {
            $stmt = $practicePdo->prepare('DELETE FROM secondary_question_bookmarks WHERE student_id = ? AND question_id = ?');
            $stmt->execute([$practiceStudentId, $questionId]);
            echo json_encode(['success' => true, 'bookmarked' => false]);
            exit;
        }
    } catch (Exception $e) {
        error_log('Secondary practice request error: ' . $e->getMessage());
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Unable to save practice progress.']);
    exit;
}

$supportedExams = get_aloc_supported_exams();
$supportedSubjects = get_aloc_supported_subjects();
$supportedYears = get_aloc_supported_years();

$examType    = normalize_aloc_exam_type($_GET['exam'] ?? 'utme') ?: 'utme';
$subjectSlug = strtolower(trim($_GET['subject'] ?? 'mathematics'));
$year        = !empty($_GET['year']) ? (int)$_GET['year'] : 2024;
$page        = max(1, (int)($_GET['page'] ?? 1));
$count       = 15;

if (!isset($supportedSubjects[$subjectSlug])) {
    $subjectSlug = 'mathematics';
}

$resultData = fetch_aloc_questions($subjectSlug, $examType, $year, $count, $page);
$questions  = $resultData['questions'] ?? [];
$source     = $resultData['source'] ?? 'none';
$notice     = $resultData['notice'] ?? '';
$errorMsg   = $resultData['error'] ?? null;

$activeExam = $supportedExams[$examType] ?? $supportedExams['utme'];
$activeSubject = $supportedSubjects[$subjectSlug] ?? $supportedSubjects['mathematics'];

$pageTitle = "{$activeExam['name']} {$year} {$activeSubject['name']} Past Questions — StudyMe";
include BASE_PATH . '/includes/layouts/header.php';
?>

<div class="py-5 bg-light-subtle" style="min-height: calc(100vh - 120px);">
    <div class="container py-4">

        <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb small">
                <li class="breadcrumb-item"><a href="<?= url('index.php') ?>" class="text-decoration-none">Home</a></li>
                <li class="breadcrumb-item"><a href="<?= url('courses/index.php') ?>" class="text-decoration-none">Courses</a></li>
                <li class="breadcrumb-item"><a href="<?= url('courses/secondary.php') ?>" class="text-decoration-none">Secondary School</a></li>
                <li class="breadcrumb-item active" aria-current="page"><?= e($activeExam['name']) ?> Questions</li>
            </ol>
        </nav>

        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
            <div>
                <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
                    <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 py-1 fw-bold text-uppercase small">
                        <i class="bi bi-mortarboard me-1"></i> ALOC Station &amp; Verified Vault
                    </span>
                    <?php if ($source === 'aloc_live'): ?>
                        <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 py-1 fw-bold small border border-success border-opacity-25">
                            <i class="bi bi-broadcast me-1"></i> ALOC Station Live
                        </span>
                    <?php else: ?>
                        <span class="badge bg-info bg-opacity-10 text-info rounded-pill px-3 py-1 fw-bold small border border-info border-opacity-25">
                            <i class="bi bi-shield-check me-1"></i> StudyMe Verified Vault
                        </span>
                    <?php endif; ?>
                </div>
                <h1 class="display-6 fw-bold mb-1"><?= e($activeExam['name']) ?> Past Questions &amp; Practice</h1>
                <p class="text-muted mb-0">Master official WAEC, NECO, and JAMB questions with instant CBT scoring, AI tutoring, and detailed solutions.</p>
            </div>

            <div class="d-flex gap-2 flex-wrap">
                <a href="<?= url('courses/secondary.php') ?>" class="btn btn-outline-secondary rounded-pill px-4 fw-semibold btn-sm">
                    <i class="bi bi-arrow-left me-1"></i> Secondary Hub
                </a>
                <button type="button" class="btn btn-warning rounded-pill px-4 fw-bold btn-sm shadow-sm" data-bs-toggle="offcanvas" data-bs-target="#aiAssistantDrawer">
                    <i class="bi bi-robot me-1"></i> Open AI Tutor
                </button>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-4 p-4 mb-4" style="background: var(--bg-card, #ffffff); border: 1px solid var(--border-color, #e2e8f0) !important;">

            <div class="mb-4">
                <label class="form-label small fw-bold text-muted text-uppercase d-block mb-2">
                    <i class="bi bi-check2-square text-primary me-1"></i> Step 1: Select Examination Body
                </label>
                <div class="row g-2">
                    <?php foreach ($supportedExams as $key => $exam): ?>
                        <div class="col-6 col-md-3">
                            <a href="<?= url('courses/past-questions.php?exam=' . $key . '&subject=' . $subjectSlug . '&year=' . $year) ?>"
                               class="btn w-100 p-3 rounded-3 text-start transition-all <?= $examType === $key ? 'btn-primary shadow-sm' : 'btn-outline-secondary border' ?>">
                                <div class="d-flex align-items-center justify-content-between mb-1">
                                    <i class="bi <?= $exam['icon'] ?> fs-5"></i>
                                    <?php if ($examType === $key): ?>
                                        <i class="bi bi-check-circle-fill small"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="fw-bold fs-6"><?= e($exam['name']) ?></div>
                                <div class="small opacity-75 text-truncate" style="font-size: 0.75rem;"><?= e($exam['full_name']) ?></div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="row g-3">

                <div class="col-md-7">
                    <label class="form-label small fw-bold text-muted text-uppercase mb-1">
                        <i class="bi bi-journal-bookmark text-primary me-1"></i> Step 2: Choose Subject (<?= count($supportedSubjects) ?> Available)
                    </label>
                    <select class="form-select form-select-lg rounded-3 fs-6"
                            id="subjectSelector"
                            onchange="location.href='<?= url('courses/past-questions.php?exam=' . $examType . '&year=' . $year . '&subject=') ?>' + this.value;">
                        <?php
                        $categories = ['general' => 'General Subjects', 'science' => 'Sciences & Tech', 'commercial' => 'Commercial & Business', 'arts' => 'Arts & Humanities'];
                        foreach ($categories as $catKey => $catLabel):
                        ?>
                            <optgroup label="<?= $catLabel ?>">
                                <?php foreach ($supportedSubjects as $slug => $sb): ?>
                                    <?php if ($sb['category'] === $catKey): ?>
                                        <option value="<?= $slug ?>" <?= $subjectSlug === $slug ? 'selected' : '' ?>>
                                            <?= e($sb['name']) ?>
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-5">
                    <label class="form-label small fw-bold text-muted text-uppercase mb-1">
                        <i class="bi bi-calendar-event text-primary me-1"></i> Step 3: Exam Series Year
                    </label>
                    <select class="form-select form-select-lg rounded-3 fs-6"
                            id="yearSelector"
                            onchange="location.href='<?= url('courses/past-questions.php?exam=' . $examType . '&subject=' . $subjectSlug . '&year=') ?>' + this.value;">
                        <?php foreach ($supportedYears as $yr): ?>
                            <option value="<?= $yr ?>" <?= $year === $yr ? 'selected' : '' ?>>
                                <?= $yr ?> Examination Series
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

        </div>

        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4 p-3 bg-white rounded-4 shadow-sm border">
            <div class="d-flex align-items-center gap-3">
                <div class="p-2 bg-primary bg-opacity-10 text-primary rounded-circle">
                    <i class="bi <?= $activeSubject['icon'] ?> fs-4"></i>
                </div>
                <div>
                    <h5 class="fw-bold mb-0 text-main">
                        <?= e($activeExam['name']) ?> <?= $year ?> &bull; <?= e($activeSubject['name']) ?>
                    </h5>
                    <small class="text-muted">Interactive CBT Drill &bull; Click options to test yourself</small>
                </div>
            </div>

            <div class="d-flex align-items-center gap-3">
                <div class="text-end">
                    <div class="small text-muted text-uppercase fw-bold" style="font-size: 0.75rem;">Your CBT Score</div>
                    <div class="fw-bold fs-5 text-primary" id="cbtScoreDisplay">0 / <?= count($questions) ?></div>
                </div>
                <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-3" onclick="resetPracticeDrill()">
                    <i class="bi bi-arrow-counterclockwise me-1"></i> Reset Drill
                </button>
            </div>
        </div>

        <?php if (!empty($notice) && $source === 'local_vault'): ?>
            <div class="alert alert-info rounded-4 mb-4 border-0 shadow-sm small d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div>
                    <i class="bi bi-info-circle-fill text-info me-2"></i>
                    <strong>StudyMe Vault Active:</strong> Questions for this series are being served from our verified local curriculum database.
                </div>
                <span class="badge bg-light text-dark border">100% Specialist Verified</span>
            </div>
        <?php endif; ?>

        <?php if (!empty($questions)): ?>
            <div class="d-flex flex-column gap-4 mb-5" id="questionsContainer">
                <?php foreach ($questions as $index => $q):
                    $qNum = $q['question_number'] ?: ($index + 1);
                    $qId = 'q_' . ($q['id'] ?: $qNum);
                    $correctOpt = strtolower($q['correct_option']);
                ?>
                <div class="card border-0 shadow-sm rounded-4 p-4 question-card"
                     id="<?= $qId ?>"
                     data-question-id="<?= (int)($q['id'] ?? 0) ?>"
                     data-correct="<?= e($correctOpt) ?>"
                     data-question-text="<?= e($q['question_text']) ?>"
                     style="background: var(--bg-card, #ffffff); border: 1px solid var(--border-color, #e2e8f0) !important;">

                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="badge bg-primary text-white rounded-pill px-3 py-1 fw-bold">
                            Question <?= $qNum ?>
                        </span>
                        <div class="d-flex align-items-center gap-2">
                            <span class="text-muted small fw-semibold">
                                <?= strtoupper($q['exam_type'] ?? $examType) ?> <?= e($q['exam_year'] ?? $year) ?>
                            </span>
                            <span class="badge bg-light text-muted border rounded-pill small">Multiple Choice</span>
                        </div>
                    </div>

                    <div class="fw-semibold text-main fs-6 mb-3 lh-base question-body">
                        <?= nl2br(e($q['question_text'])) ?>
                    </div>

                    <?php if (!empty($q['image'])): ?>
                        <div class="mb-3 text-center p-2 bg-light rounded-3 border">
                            <img src="<?= e($q['image']) ?>" alt="Question Diagram" class="img-fluid rounded" style="max-height: 260px;">
                        </div>
                    <?php endif; ?>

                    <div class="row g-2 mb-3 options-grid">
                        <?php
                        $optionsList = [
                            'a' => $q['option_a'],
                            'b' => $q['option_b'],
                            'c' => $q['option_c'],
                            'd' => $q['option_d'],
                        ];
                        foreach ($optionsList as $optKey => $optVal):
                            if (empty($optVal)) continue;
                        ?>
                            <div class="col-md-6">
                                <button type="button"
                                        class="btn btn-light w-100 text-start p-3 rounded-3 border d-flex align-items-center gap-3 option-btn transition-all"
                                        data-option="<?= $optKey ?>"
                                        onclick="handleOptionClick('<?= $qId ?>', '<?= $optKey ?>')">
                                    <span class="badge bg-secondary text-white rounded-circle d-flex align-items-center justify-content-center option-badge" style="width: 28px; height: 28px; font-size: 0.85rem;">
                                        <?= strtoupper($optKey) ?>
                                    </span>
                                    <span class="option-text text-main small flex-grow-1"><?= e($optVal) ?></span>
                                    <span class="option-feedback-icon ms-auto"></span>
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="pt-3 border-top border-subtle d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div class="d-flex gap-2 flex-wrap">
                            <button class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-bold"
                                    type="button"
                                    data-bs-toggle="collapse"
                                    data-bs-target="#sol_<?= $qId ?>"
                                    aria-expanded="false">
                                <i class="bi bi-lightbulb-fill me-1 text-warning"></i> View Solution
                            </button>
                            <?php if ($practiceStudentId && !empty($q['id'])): ?>
                                <button class="btn btn-sm btn-outline-secondary rounded-pill px-3 fw-bold bookmark-btn" type="button" onclick="toggleQuestionBookmark(this, <?= (int)$q['id'] ?>)">
                                    <i class="bi bi-bookmark me-1"></i> Save
                                </button>
                            <?php endif; ?>

                            <button class="btn btn-sm btn-outline-warning text-dark rounded-pill px-3 fw-bold"
                                    type="button"
                                    onclick="askAiAboutQuestion('<?= addslashes(e($q['question_text'])) ?>', '<?= addslashes(e($activeSubject['name'])) ?>', '<?= addslashes(e($activeExam['name'])) ?>')">
                                <i class="bi bi-robot me-1 text-primary"></i> Ask AI Tutor
                            </button>
                        </div>
                        <span class="text-muted small feedback-status-label">Click an option to test your answer</span>
                    </div>

                    <div class="collapse mt-3" id="sol_<?= $qId ?>">
                        <div class="p-3 rounded-3 bg-success bg-opacity-10 border border-success border-opacity-25">
                            <div class="fw-bold text-success mb-1 d-flex align-items-center gap-1">
                                <i class="bi bi-check-circle-fill"></i>
                                <span>Correct Answer: Option <?= strtoupper($correctOpt) ?></span>
                            </div>
                            <?php if (!empty($q['explanation'])): ?>
                                <div class="small text-dark mt-2 lh-base">
                                    <strong>Step-by-Step Explanation:</strong><br>
                                    <?= nl2br(e($q['explanation'])) ?>
                                </div>
                            <?php else: ?>
                                <div class="small text-muted mt-1">
                                    Option <?= strtoupper($correctOpt) ?> is the officially accredited correct answer key for this examination series.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>
                <?php endforeach; ?>
            </div>

            <div class="card border-0 shadow-sm rounded-4 p-4 text-center bg-white border">
                <h5 class="fw-bold mb-2">Ready to Try Another Subject or Series?</h5>
                <p class="text-muted small mb-3">Explore comprehensive questions from other years or switch to WAEC, NECO, and Post-UTME.</p>
                <div class="d-flex justify-content-center gap-2 flex-wrap">
                    <a href="<?= url('courses/past-questions.php?exam=waec&subject=mathematics&year=2024') ?>" class="btn btn-outline-primary rounded-pill px-4 fw-bold btn-sm">
                        WAEC 2024 Mathematics
                    </a>
                    <a href="<?= url('courses/past-questions.php?exam=utme&subject=english&year=2024') ?>" class="btn btn-outline-success rounded-pill px-4 fw-bold btn-sm">
                        JAMB 2024 English
                    </a>
                    <a href="<?= url('courses/past-questions.php?exam=neco&subject=biology&year=2024') ?>" class="btn btn-outline-warning text-dark rounded-pill px-4 fw-bold btn-sm">
                        NECO 2024 Biology
                    </a>
                </div>
            </div>

        <?php else: ?>

            <div class="card border-0 shadow-sm rounded-4 p-5 text-center my-4 bg-white">
                <div class="p-3 bg-warning bg-opacity-10 text-warning rounded-circle d-inline-flex mx-auto mb-3" style="width: 72px; height: 72px; align-items: center; justify-content: center;">
                    <i class="bi bi-folder-x fs-1"></i>
                </div>
                <h4 class="fw-bold mb-1">No Questions Found For This Filter</h4>
                <p class="text-muted mb-4 max-w-md mx-auto">
                    Questions for <?= e($activeExam['name']) ?> <?= $year ?> <?= e($activeSubject['name']) ?> are currently being synchronized into the vault.
                </p>

                <div class="d-flex justify-content-center gap-2 flex-wrap">
                    <a href="<?= url('courses/past-questions.php?exam=waec&subject=mathematics&year=2024') ?>" class="btn btn-primary rounded-pill px-4 fw-bold">
                        Try WAEC Mathematics (2024)
                    </a>
                    <a href="<?= url('courses/past-questions.php?exam=utme&subject=english&year=2024') ?>" class="btn btn-outline-secondary rounded-pill px-4 fw-bold">
                        Try JAMB English
                    </a>
                </div>
            </div>
        <?php endif; ?>

    </div>
</div>

<script>
let totalAnswered = 0;
let totalCorrect = 0;
const answeredQuestions = new Set();
let practiceAttemptId = 0;

async function savePracticeRequest(payload) {
    const response = await fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
        body: new URLSearchParams(payload)
    });
    return response.json();
}

async function startPracticeAttempt() {
    const firstCard = document.querySelector('.question-card');
    if (!firstCard) return;
    try {
        const result = await savePracticeRequest({
            practice_action: 'start',
            exam_type: <?= json_encode($examType) ?>,
            subject_slug: <?= json_encode($subjectSlug) ?>,
            year: <?= (int)$year ?>,
            total_questions: document.querySelectorAll('.question-card').length
        });
        if (result.success) practiceAttemptId = Number(result.attempt_id || 0);
    } catch (error) {
        practiceAttemptId = 0;
    }
}

function handleOptionClick(cardId, selectedOption) {
    const card = document.getElementById(cardId);
    if (!card) return;

    const correctOption = card.dataset.correct.toLowerCase();
    const buttons = card.querySelectorAll(".option-btn");
    const statusLabel = card.querySelector(".feedback-status-label");
    const isFirstAttempt = !answeredQuestions.has(cardId);

    // Disable multiple attempts on same question or mark status
    buttons.forEach(btn => {
        const opt = btn.dataset.option.toLowerCase();
        btn.classList.remove("btn-light", "btn-success", "btn-danger", "border-success", "border-danger");

        if (opt === correctOption) {
            btn.classList.add("btn-success", "text-white");
            btn.querySelector(".option-badge").className = "badge bg-white text-success rounded-circle d-flex align-items-center justify-content-center option-badge";
            btn.querySelector(".option-feedback-icon").innerHTML = '<i class="bi bi-check-circle-fill fs-5"></i>';
        } else if (opt === selectedOption && selectedOption !== correctOption) {
            btn.classList.add("btn-danger", "text-white");
            btn.querySelector(".option-badge").className = "badge bg-white text-danger rounded-circle d-flex align-items-center justify-content-center option-badge";
            btn.querySelector(".option-feedback-icon").innerHTML = '<i class="bi bi-x-circle-fill fs-5"></i>';
        } else {
            btn.classList.add("btn-light", "opacity-50");
        }
    });

    if (isFirstAttempt) {
        answeredQuestions.add(cardId);
        totalAnswered++;
        if (selectedOption === correctOption) {
            totalCorrect++;
            if (statusLabel) {
                statusLabel.innerHTML = '<span class="text-success fw-bold"><i class="bi bi-check-circle-fill me-1"></i> Correct!</span>';
            }
            if (typeof StudyMeFeedback !== 'undefined') {
                StudyMeFeedback.success();
            }
        } else {
            if (statusLabel) {
                statusLabel.innerHTML = '<span class="text-danger fw-bold"><i class="bi bi-x-circle-fill me-1"></i> Incorrect</span>';
            }
            if (typeof StudyMeFeedback !== 'undefined') {
                StudyMeFeedback.error();
            }
        }
        updateScoreDisplay();
        const questionId = Number(card.dataset.questionId || 0);
        if (practiceAttemptId && questionId) {
            savePracticeRequest({ practice_action: 'answer', attempt_id: practiceAttemptId, question_id: questionId, selected_option: selectedOption }).catch(() => {});
        }
    }
}

function updateScoreDisplay() {
    const totalQuestions = document.querySelectorAll(".question-card").length;
    const scoreDisplay = document.getElementById("cbtScoreDisplay");
    if (scoreDisplay) {
        scoreDisplay.textContent = `${totalCorrect} / ${totalQuestions} (${totalAnswered} answered)`;
    }
}

function resetPracticeDrill() {
    answeredQuestions.clear();
    totalAnswered = 0;
    totalCorrect = 0;
    updateScoreDisplay();

    const cards = document.querySelectorAll(".question-card");
    cards.forEach(card => {
        const buttons = card.querySelectorAll(".option-btn");
        buttons.forEach(btn => {
            btn.className = "btn btn-light w-100 text-start p-3 rounded-3 border d-flex align-items-center gap-3 option-btn transition-all";
            const badge = btn.querySelector(".option-badge");
            if (badge) badge.className = "badge bg-secondary text-white rounded-circle d-flex align-items-center justify-content-center option-badge";
            const icon = btn.querySelector(".option-feedback-icon");
            if (icon) icon.innerHTML = '';
        });
        const statusLabel = card.querySelector(".feedback-status-label");
        if (statusLabel) {
            statusLabel.textContent = "Click an option to test your answer";
        }
    });
}

async function toggleQuestionBookmark(button, questionId) {
    const saved = button.dataset.saved === '1';
    try {
        const result = await savePracticeRequest({ practice_action: saved ? 'unbookmark' : 'bookmark', question_id: questionId });
        if (!result.success) return;
        button.dataset.saved = saved ? '0' : '1';
        button.classList.toggle('btn-secondary', !saved);
        button.classList.toggle('btn-outline-secondary', saved);
        button.innerHTML = saved ? '<i class="bi bi-bookmark me-1"></i> Save' : '<i class="bi bi-bookmark-fill me-1"></i> Saved';
    } catch (error) {}
}

function askAiAboutQuestion(questionText, subject, exam) {
    const prompt = `Please explain this ${exam} ${subject} past question step-by-step and show me how to solve it:\n\n"${questionText}"`;
    const aiInput = document.getElementById("aiQueryInput") || document.getElementById("pageAiInput");
    const aiDrawer = document.getElementById("aiAssistantDrawer");

    if (aiInput) {
        aiInput.value = prompt;
    }

    if (aiDrawer && typeof bootstrap !== 'undefined') {
        const bsOffcanvas = bootstrap.Offcanvas.getOrCreateInstance(aiDrawer);
        bsOffcanvas.show();
        setTimeout(() => {
            const aiForm = document.getElementById("aiQueryForm");
            if (aiForm) {
                aiForm.dispatchEvent(new Event("submit"));
            }
        }, 300);
    }
}

startPracticeAttempt();
</script>

<?php include BASE_PATH . '/includes/layouts/footer.php'; ?>
