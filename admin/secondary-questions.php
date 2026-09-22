<?php

require_once dirname(__DIR__) . '/config/main.php';
require_once BASE_PATH . '/includes/functions/enrollments.php';

secure_page(ROLE_ADMIN);

$pdo = getDBConnection();
$errors = [];
$successMsg = '';

// Handle Question Creation / Update / Deletion
if (is_post()) {
    $action = trim($_POST['action'] ?? '');

    if ($action === 'create_question') {
        $examType      = strtolower(trim($_POST['exam_type'] ?? 'waec'));
        $year          = (int)($_POST['year'] ?? 2024);
        $subjectSlug   = trim($_POST['subject_slug'] ?? 'mathematics');
        $subjectRow    = get_secondary_subject_by_slug($subjectSlug);
        $subjectName   = $subjectRow ? $subjectRow['name'] : ucfirst($subjectSlug);
        $subjectId     = $subjectRow ? (int)$subjectRow['id'] : null;
        $questionNum   = (int)($_POST['question_number'] ?? 1);
        $questionText  = trim($_POST['question_text'] ?? '');
        $optA          = trim($_POST['option_a'] ?? '');
        $optB          = trim($_POST['option_b'] ?? '');
        $optC          = trim($_POST['option_c'] ?? '');
        $optD          = trim($_POST['option_d'] ?? '');
        $correctOption = strtoupper(trim($_POST['correct_option'] ?? 'A'));
        $explanation   = trim($_POST['explanation'] ?? '');
        $difficulty    = strtolower(trim($_POST['difficulty'] ?? 'medium'));
        $topicName     = trim($_POST['topic_name'] ?? '');

        if (empty($questionText) || empty($optA) || empty($optB)) {
            $errors[] = 'Question text and at least options A and B are required.';
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO past_questions 
                        (exam_type, subject_id, year, subject_name, subject_slug, question_number, question_text, option_a, option_b, option_c, option_d, correct_option, explanation, difficulty, topic_name, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $examType, $subjectId, $year, $subjectName, $subjectSlug, $questionNum, $questionText,
                    $optA, $optB, $optC, $optD, $correctOption, $explanation, $difficulty, $topicName
                ]);
                $successMsg = 'Past Question saved to examination bank successfully!';
            } catch (Exception $e) {
                $errors[] = 'Database error: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'edit_question') {
        $qId           = (int)($_POST['question_id'] ?? 0);
        $examType      = strtolower(trim($_POST['exam_type'] ?? 'waec'));
        $year          = (int)($_POST['year'] ?? 2024);
        $subjectSlug   = trim($_POST['subject_slug'] ?? 'mathematics');
        $subjectRow    = get_secondary_subject_by_slug($subjectSlug);
        $subjectName   = $subjectRow ? $subjectRow['name'] : ucfirst($subjectSlug);
        $subjectId     = $subjectRow ? (int)$subjectRow['id'] : null;
        $questionText  = trim($_POST['question_text'] ?? '');
        $optA          = trim($_POST['option_a'] ?? '');
        $optB          = trim($_POST['option_b'] ?? '');
        $optC          = trim($_POST['option_c'] ?? '');
        $optD          = trim($_POST['option_d'] ?? '');
        $correctOption = strtoupper(trim($_POST['correct_option'] ?? 'A'));
        $explanation   = trim($_POST['explanation'] ?? '');
        $difficulty    = strtolower(trim($_POST['difficulty'] ?? 'medium'));
        $topicName     = trim($_POST['topic_name'] ?? '');

        if ($qId > 0 && !empty($questionText)) {
            try {
                $stmt = $pdo->prepare("
                    UPDATE past_questions 
                    SET exam_type = ?, subject_id = ?, year = ?, subject_name = ?, subject_slug = ?,
                        question_text = ?, option_a = ?, option_b = ?, option_c = ?, option_d = ?,
                        correct_option = ?, explanation = ?, difficulty = ?, topic_name = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $examType, $subjectId, $year, $subjectName, $subjectSlug,
                    $questionText, $optA, $optB, $optC, $optD,
                    $correctOption, $explanation, $difficulty, $topicName, $qId
                ]);
                $successMsg = 'Past Question updated successfully!';
            } catch (Exception $e) {
                $errors[] = 'Database error: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'delete_question') {
        $qId = (int)($_POST['question_id'] ?? 0);
        if ($qId > 0) {
            $pdo->prepare("DELETE FROM past_questions WHERE id = ?")->execute([$qId]);
            $successMsg = 'Question removed from database.';
        }
    }
}

// Global Exam counts
$examCounts = get_secondary_exam_counts();

// Filters
$examFilter    = strtolower(trim($_GET['exam'] ?? ''));
$subjectFilter = strtolower(trim($_GET['subject'] ?? ''));
$yearFilter    = !empty($_GET['year']) ? (int)$_GET['year'] : null;
$searchQuery   = trim($_GET['q'] ?? '');

$where = ["1=1"];
$params = [];

if (!empty($examFilter)) {
    if ($examFilter === 'jamb' || $examFilter === 'utme') {
        $where[] = "(exam_type = 'jamb' OR exam_type = 'utme')";
    } else {
        $where[] = "exam_type = ?";
        $params[] = $examFilter;
    }
}
if (!empty($subjectFilter)) {
    $where[] = "subject_slug = ?";
    $params[] = $subjectFilter;
}
if (!empty($yearFilter)) {
    $where[] = "year = ?";
    $params[] = $yearFilter;
}
if (!empty($searchQuery)) {
    $where[] = "(question_text LIKE ? OR explanation LIKE ? OR topic_name LIKE ?)";
    $params[] = "%{$searchQuery}%";
    $params[] = "%{$searchQuery}%";
    $params[] = "%{$searchQuery}%";
}

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM past_questions WHERE " . implode(' AND ', $where));
$countStmt->execute($params);
$totalQuestions = (int)$countStmt->fetchColumn();
$totalPages = ceil($totalQuestions / $limit);

$stmt = $pdo->prepare("SELECT * FROM past_questions WHERE " . implode(' AND ', $where) . " ORDER BY year DESC, subject_name ASC, id DESC LIMIT $limit OFFSET $offset");
$stmt->execute($params);
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

$subjects = get_secondary_subjects('active');

$pageTitle = 'Manage WAEC/NECO/JAMB Questions | Admin';
include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
    <div>
        <p class="text-muted mb-1 fw-semibold text-uppercase small">
            <i class="bi bi-patch-question-fill text-warning me-1"></i> Examination Bank Manager
        </p>
        <h2 class="fw-bold mb-0 text-dark">WAEC / NECO / JAMB Past Questions</h2>
    </div>

    <button type="button" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#newQuestionModal">
        <i class="bi bi-plus-lg me-1"></i> Add Past Question
    </button>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger rounded-4 mb-4">
    <?php foreach ($errors as $e): ?><div><i class="bi bi-exclamation-circle-fill me-1"></i> <?= e($e) ?></div><?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (!empty($successMsg)): ?>
<div class="alert alert-success rounded-4 mb-4">
    <i class="bi bi-check-circle-fill me-1"></i> <?= e($successMsg) ?>
</div>
<?php endif; ?>

<!-- EXAM METRICS CARDS -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-circle bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center" style="width:46px; height:46px;">
                    <i class="bi bi-layers-fill fs-4"></i>
                </div>
                <div>
                    <h4 class="fw-bold mb-0 text-dark"><?= number_format((int)$examCounts['total']) ?></h4>
                    <small class="text-muted">Total Questions</small>
                </div>
            </div>
        </div>
    </div>

    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-circle bg-warning bg-opacity-10 text-warning d-flex align-items-center justify-content-center" style="width:46px; height:46px;">
                    <i class="bi bi-award-fill fs-4"></i>
                </div>
                <div>
                    <h4 class="fw-bold mb-0 text-dark"><?= number_format((int)$examCounts['waec_count']) ?></h4>
                    <small class="text-muted">WAEC Questions</small>
                </div>
            </div>
        </div>
    </div>

    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-circle bg-success bg-opacity-10 text-success d-flex align-items-center justify-content-center" style="width:46px; height:46px;">
                    <i class="bi bi-journal-bookmark-fill fs-4"></i>
                </div>
                <div>
                    <h4 class="fw-bold mb-0 text-dark"><?= number_format((int)$examCounts['neco_count']) ?></h4>
                    <small class="text-muted">NECO Questions</small>
                </div>
            </div>
        </div>
    </div>

    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-circle bg-info bg-opacity-10 text-info d-flex align-items-center justify-content-center" style="width:46px; height:46px;">
                    <i class="bi bi-lightning-charge-fill fs-4"></i>
                </div>
                <div>
                    <h4 class="fw-bold mb-0 text-dark"><?= number_format((int)$examCounts['jamb_count']) ?></h4>
                    <small class="text-muted">JAMB Questions</small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- FILTER BAR -->
<div class="card border-0 shadow-sm rounded-4 p-4 mb-4 bg-white border">
    <form method="GET" action="<?= url('admin/secondary-questions.php') ?>" class="row g-3 align-items-end">
        <div class="col-md-3">
            <label class="form-label small fw-bold text-muted">Exam Type:</label>
            <select name="exam" class="form-select rounded-3">
                <option value="">All Exams</option>
                <option value="waec" <?= $examFilter === 'waec' ? 'selected' : '' ?>>WAEC</option>
                <option value="neco" <?= $examFilter === 'neco' ? 'selected' : '' ?>>NECO</option>
                <option value="jamb" <?= $examFilter === 'jamb' ? 'selected' : '' ?>>JAMB</option>
            </select>
        </div>

        <div class="col-md-3">
            <label class="form-label small fw-bold text-muted">Subject:</label>
            <select name="subject" class="form-select rounded-3">
                <option value="">All Subjects</option>
                <?php foreach ($subjects as $s): ?>
                <option value="<?= e($s['slug']) ?>" <?= $subjectFilter === $s['slug'] ? 'selected' : '' ?>>
                    <?= e($s['name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-2">
            <label class="form-label small fw-bold text-muted">Year:</label>
            <select name="year" class="form-select rounded-3">
                <option value="">All Years</option>
                <option value="2024" <?= $yearFilter === 2024 ? 'selected' : '' ?>>2024</option>
                <option value="2023" <?= $yearFilter === 2023 ? 'selected' : '' ?>>2023</option>
                <option value="2022" <?= $yearFilter === 2022 ? 'selected' : '' ?>>2022</option>
                <option value="2021" <?= $yearFilter === 2021 ? 'selected' : '' ?>>2021</option>
                <option value="2020" <?= $yearFilter === 2020 ? 'selected' : '' ?>>2020</option>
            </select>
        </div>

        <div class="col-md-3">
            <label class="form-label small fw-bold text-muted">Search Keyword:</label>
            <input type="text" name="q" class="form-control rounded-3" placeholder="Search text..." value="<?= e($searchQuery) ?>">
        </div>

        <div class="col-md-1">
            <button type="submit" class="btn btn-primary w-100 rounded-3 fw-bold">Filter</button>
        </div>
    </form>
</div>

<!-- QUESTIONS TABLE -->
<div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-5 bg-white border">
    <div class="card-header bg-white p-3 p-md-4 border-bottom d-flex align-items-center justify-content-between">
        <h5 class="fw-bold text-dark mb-0">Verified Questions Vault (<?= number_format($totalQuestions) ?>)</h5>
        <span class="text-muted small">Showing <?= min($limit, count($questions)) ?> per page</span>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
            <thead class="bg-light">
                <tr>
                    <th class="ps-4">Exam &amp; Subject</th>
                    <th>Year</th>
                    <th style="width: 45%;">Question Snippet</th>
                    <th>Answer</th>
                    <th class="text-end pe-4">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($questions)): ?>
                    <?php foreach ($questions as $q): ?>
                    <tr>
                        <td class="ps-4">
                            <span class="badge bg-dark font-monospace me-1"><?= strtoupper(e($q['exam_type'])) ?></span>
                            <strong class="text-dark"><?= e($q['subject_name']) ?></strong>
                            <?php if (!empty($q['topic_name'])): ?>
                                <br><small class="text-muted"><?= e($q['topic_name']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td class="fw-bold text-muted"><?= (int)$q['year'] ?></td>
                        <td>
                            <div class="fw-semibold text-dark text-truncate" style="max-width: 450px;">
                                <?= e($q['question_text']) ?>
                            </div>
                            <small class="text-muted">A: <?= e(substr($q['option_a'], 0, 25)) ?> | B: <?= e(substr($q['option_b'], 0, 25)) ?></small>
                        </td>
                        <td>
                            <span class="badge bg-success rounded-pill px-3 py-1 font-monospace fw-bold">
                                Option <?= strtoupper(e($q['correct_option'])) ?>
                            </span>
                        </td>
                        <td class="text-end pe-4">
                            <!-- Edit Button -->
                            <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-2 py-1 me-1" data-bs-toggle="modal" data-bs-target="#editQuestionModal<?= $q['id'] ?>">
                                <i class="bi bi-pencil-square"></i>
                            </button>

                            <!-- Delete Button -->
                            <form method="POST" action="<?= url('admin/secondary-questions.php') ?>" class="d-inline" onsubmit="return confirm('Delete this question permanently?');">
                                <input type="hidden" name="action" value="delete_question">
                                <input type="hidden" name="question_id" value="<?= $q['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-2 py-1">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>

                            <!-- Edit Modal for this question -->
                            <div class="modal fade text-start" id="editQuestionModal<?= $q['id'] ?>" tabindex="-1">
                                <div class="modal-dialog modal-lg modal-dialog-centered">
                                    <div class="modal-content rounded-4 border-0 shadow">
                                        <div class="modal-header border-0 pb-0">
                                            <h5 class="modal-title fw-bold">Edit Past Question #<?= $q['id'] ?></h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <form method="POST" action="<?= url('admin/secondary-questions.php') ?>">
                                            <input type="hidden" name="action" value="edit_question">
                                            <input type="hidden" name="question_id" value="<?= $q['id'] ?>">
                                            <div class="modal-body py-3">
                                                <div class="row g-2 mb-3">
                                                    <div class="col-md-4">
                                                        <label class="form-label small fw-bold">Exam Type</label>
                                                        <select name="exam_type" class="form-select rounded-3" required>
                                                            <option value="waec" <?= $q['exam_type'] === 'waec' ? 'selected' : '' ?>>WAEC</option>
                                                            <option value="neco" <?= $q['exam_type'] === 'neco' ? 'selected' : '' ?>>NECO</option>
                                                            <option value="jamb" <?= in_array($q['exam_type'], ['jamb', 'utme']) ? 'selected' : '' ?>>JAMB UTME</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label small fw-bold">Subject</label>
                                                        <select name="subject_slug" class="form-select rounded-3" required>
                                                            <?php foreach ($subjects as $s): ?>
                                                            <option value="<?= e($s['slug']) ?>" <?= $q['subject_slug'] === $s['slug'] ? 'selected' : '' ?>><?= e($s['name']) ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label small fw-bold">Year</label>
                                                        <input type="number" name="year" class="form-control rounded-3" value="<?= (int)$q['year'] ?>" required>
                                                    </div>
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label small fw-bold">Topic Name</label>
                                                    <input type="text" name="topic_name" class="form-control rounded-3" value="<?= e($q['topic_name']) ?>">
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label small fw-bold">Question Text (supports LaTeX $$ and $$)</label>
                                                    <textarea name="question_text" class="form-control rounded-3" rows="3" required><?= e($q['question_text']) ?></textarea>
                                                </div>

                                                <div class="row g-2 mb-3">
                                                    <div class="col-md-6">
                                                        <label class="form-label small fw-bold">Option A</label>
                                                        <input type="text" name="option_a" class="form-control rounded-3" value="<?= e($q['option_a']) ?>" required>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label small fw-bold">Option B</label>
                                                        <input type="text" name="option_b" class="form-control rounded-3" value="<?= e($q['option_b']) ?>" required>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label small fw-bold">Option C</label>
                                                        <input type="text" name="option_c" class="form-control rounded-3" value="<?= e($q['option_c']) ?>">
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label small fw-bold">Option D</label>
                                                        <input type="text" name="option_d" class="form-control rounded-3" value="<?= e($q['option_d']) ?>">
                                                    </div>
                                                </div>

                                                <div class="row g-2 mb-3">
                                                    <div class="col-md-6">
                                                        <label class="form-label small fw-bold">Correct Option</label>
                                                        <select name="correct_option" class="form-select rounded-3">
                                                            <option value="A" <?= strtoupper($q['correct_option']) === 'A' ? 'selected' : '' ?>>A</option>
                                                            <option value="B" <?= strtoupper($q['correct_option']) === 'B' ? 'selected' : '' ?>>B</option>
                                                            <option value="C" <?= strtoupper($q['correct_option']) === 'C' ? 'selected' : '' ?>>C</option>
                                                            <option value="D" <?= strtoupper($q['correct_option']) === 'D' ? 'selected' : '' ?>>D</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label small fw-bold">Difficulty</label>
                                                        <select name="difficulty" class="form-select rounded-3">
                                                            <option value="easy" <?= $q['difficulty'] === 'easy' ? 'selected' : '' ?>>Easy</option>
                                                            <option value="medium" <?= $q['difficulty'] === 'medium' ? 'selected' : '' ?>>Medium</option>
                                                            <option value="hard" <?= $q['difficulty'] === 'hard' ? 'selected' : '' ?>>Hard</option>
                                                        </select>
                                                    </div>
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label small fw-bold">Step-by-Step Explanation</label>
                                                    <textarea name="explanation" class="form-control rounded-3" rows="3"><?= e($q['explanation']) ?></textarea>
                                                </div>
                                            </div>
                                            <div class="modal-footer border-0 pt-0">
                                                <button type="button" class="btn btn-light rounded-pill" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-primary rounded-pill fw-bold px-4">Save Changes</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="text-center py-5 text-muted">No questions found matching your filter.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- PAGINATION -->
    <?php if ($totalPages > 1): ?>
    <div class="d-flex justify-content-center p-3 border-top">
        <ul class="pagination pagination-rounded mb-0">
            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">Previous</a>
            </li>
            <?php for ($i = 1; $i <= min(10, $totalPages); $i++): ?>
            <li class="page-item <?= $page === $i ? 'active' : '' ?>">
                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
            </li>
            <?php endfor; ?>
            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">Next</a>
            </li>
        </ul>
    </div>
    <?php endif; ?>
</div>

<!-- NEW QUESTION MODAL -->
<div class="modal fade" id="newQuestionModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Add Question to Examination Bank</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="<?= url('admin/secondary-questions.php') ?>">
                <input type="hidden" name="action" value="create_question">
                <div class="modal-body py-3">
                    <div class="row g-2 mb-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Exam Type</label>
                            <select name="exam_type" class="form-select rounded-3" required>
                                <option value="waec">WAEC SSCE</option>
                                <option value="neco">NECO SSCE</option>
                                <option value="jamb">JAMB UTME</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Subject</label>
                            <select name="subject_slug" class="form-select rounded-3" required>
                                <?php foreach ($subjects as $s): ?>
                                <option value="<?= e($s['slug']) ?>"><?= e($s['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Year</label>
                            <input type="number" name="year" class="form-control rounded-3" value="2024" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Topic Name (e.g. Quadratic Equations)</label>
                        <input type="text" name="topic_name" class="form-control rounded-3" placeholder="Topic name...">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Question Text (supports LaTeX $$ and $$)</label>
                        <textarea name="question_text" class="form-control rounded-3" rows="3" placeholder="Enter question text..." required></textarea>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Option A</label>
                            <input type="text" name="option_a" class="form-control rounded-3" placeholder="Option A" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Option B</label>
                            <input type="text" name="option_b" class="form-control rounded-3" placeholder="Option B" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Option C</label>
                            <input type="text" name="option_c" class="form-control rounded-3" placeholder="Option C">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Option D</label>
                            <input type="text" name="option_d" class="form-control rounded-3" placeholder="Option D">
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Correct Option</label>
                            <select name="correct_option" class="form-select rounded-3">
                                <option value="A">A</option>
                                <option value="B">B</option>
                                <option value="C">C</option>
                                <option value="D">D</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Difficulty</label>
                            <select name="difficulty" class="form-select rounded-3">
                                <option value="easy">Easy</option>
                                <option value="medium" selected>Medium</option>
                                <option value="hard">Hard</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Step-by-Step Explanation &amp; Solution</label>
                        <textarea name="explanation" class="form-control rounded-3" rows="3" placeholder="Detailed solution..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill fw-bold px-4">Save Question</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
