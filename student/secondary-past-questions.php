<?php

require_once dirname(__DIR__) . '/config/main.php';
require_once BASE_PATH . '/includes/functions/enrollments.php';
require_once BASE_PATH . '/includes/functions/activity.php';

secure_page(ROLE_STUDENT);

$user   = current_user();
$userId = (int)$user['id'];
$pdo    = getDBConnection();

log_user_activity($userId, 'viewed_past_questions_vault', 'Student accessed Secondary Past Questions Hub');

// Filters
$examFilter       = strtolower(trim($_GET['exam'] ?? ''));
if ($examFilter === 'jamb') $examFilter = 'utme';
$subjectFilter    = strtolower(trim($_GET['subject'] ?? ''));
$yearFilter       = !empty($_GET['year']) ? (int)$_GET['year'] : null;
$topicFilter      = trim($_GET['topic'] ?? '');
$difficultyFilter = strtolower(trim($_GET['difficulty'] ?? ''));
$search           = trim($_GET['search'] ?? '');
$jumpQuestionNum  = !empty($_GET['qnum']) ? (int)$_GET['qnum'] : null;

// Build query
$where = ["1=1"];
$params = [];

if (!empty($examFilter) && in_array($examFilter, ['waec', 'neco', 'utme', 'jamb', 'general'], true)) {
    if ($examFilter === 'utme' || $examFilter === 'jamb') {
        $where[] = "(pq.exam_type = 'utme' OR pq.exam_type = 'jamb')";
    } else {
        $where[] = "pq.exam_type = ?";
        $params[] = $examFilter;
    }
}

if (!empty($subjectFilter)) {
    $where[] = "(pq.subject_slug = ? OR pq.subject_name LIKE ?)";
    $params[] = $subjectFilter;
    $params[] = "%{$subjectFilter}%";
}

if (!empty($yearFilter) && $yearFilter >= 1990) {
    $where[] = "pq.year = ?";
    $params[] = $yearFilter;
}

if (!empty($topicFilter)) {
    $where[] = "pq.topic_name LIKE ?";
    $params[] = "%{$topicFilter}%";
}

if (!empty($difficultyFilter) && in_array($difficultyFilter, ['easy', 'medium', 'hard'], true)) {
    $where[] = "pq.difficulty = ?";
    $params[] = $difficultyFilter;
}

if (!empty($search)) {
    $where[] = "(pq.question_text LIKE ? OR pq.explanation LIKE ? OR pq.topic_name LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

// Global Exam counts for stats banner
$examCounts = get_secondary_exam_counts();

// Count matching total
$countSql = "SELECT COUNT(*) FROM past_questions pq WHERE " . implode(' AND ', $where);
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalQuestions = (int)$countStmt->fetchColumn();

// Pagination
$limit = 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$totalPages = max(1, ceil($totalQuestions / $limit));

if ($jumpQuestionNum && $jumpQuestionNum > 0 && $jumpQuestionNum <= $totalQuestions) {
    $page = ceil($jumpQuestionNum / $limit);
}
$offset = ($page - 1) * $limit;

// Fetch Questions
$sql = "
    SELECT pq.*, s.icon AS subject_icon, s.color AS subject_color
    FROM past_questions pq
    LEFT JOIN secondary_subjects s ON pq.subject_slug = s.slug
    WHERE " . implode(' AND ', $where) . "
    ORDER BY pq.year DESC, pq.subject_name ASC, pq.question_number ASC, pq.id ASC
    LIMIT $limit OFFSET $offset
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$questionVault = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Available Subjects with question count for filter dropdown
$availableSubjects = get_secondary_exam_subjects($examFilter ?: null);
$allSubjectsList = get_secondary_subjects('active');

// Available Years for filter dropdown
$availableYears = get_secondary_subject_years($examFilter ?: null, $subjectFilter ?: null);

// Available Topics for the selected subject
$availableTopics = [];
if (!empty($subjectFilter)) {
    $subjRow = get_secondary_subject_by_slug($subjectFilter);
    if ($subjRow) {
        $availableTopics = get_secondary_topics($subjRow['id']);
    }
}

$pageTitle = 'Past Questions Hub | WAEC, NECO & JAMB | StudyMe';
include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<!-- MathJax for crystal clear mathematical equations and chemical formulas -->
<script>
window.MathJax = {
  tex: {
    inlineMath: [['$', '$'], ['\\(', '\\)']],
    displayMath: [['$$', '$$'], ['\\[', '\\]']]
  },
  svg: { fontCache: 'global' }
};
</script>
<script id="MathJax-script" async src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-mml-chtml.js"></script>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?= url('student/secondary-dashboard.php') ?>" class="text-decoration-none">Dashboard</a></li>
                <li class="breadcrumb-item active" aria-current="page">Past Questions Hub</li>
            </ol>
        </nav>
        <h2 class="fw-bold mb-0 text-dark">
            <i class="bi bi-patch-question-fill text-primary me-2"></i>Past Questions &amp; CBT Examination Hub
        </h2>
        <p class="text-muted mb-0 small">Browse, read solutions, and launch dynamic practice tests across WAEC, NECO, and JAMB.</p>
    </div>

    <!-- Quick Action Launchers -->
    <div class="d-flex gap-2 flex-wrap">
        <button type="button" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#quickCbtModal">
            <i class="bi bi-play-circle-fill me-1"></i> Launch CBT Practice
        </button>
        <a href="<?= url('student/secondary-practice-history.php') ?>" class="btn btn-outline-secondary rounded-pill px-3 fw-semibold">
            <i class="bi bi-clock-history me-1"></i> Practice History
        </a>
    </div>
</div>

<!-- 1. EXAM SWITCHER & SUMMARY CARDS -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <a href="<?= url('student/secondary-past-questions.php') ?>" class="card border-0 shadow-sm rounded-4 text-decoration-none h-100 transition-hover <?= empty($examFilter) ? 'border border-2 border-primary bg-primary bg-opacity-10' : 'bg-white' ?>">
            <div class="card-body p-3 text-center">
                <div class="rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center mb-2" style="width: 44px; height: 44px;">
                    <i class="bi bi-layers-fill fs-5"></i>
                </div>
                <h6 class="fw-bold mb-1 text-dark">All Past Questions</h6>
                <span class="badge bg-primary rounded-pill px-3 py-1"><?= number_format((int)$examCounts['total']) ?> Questions</span>
            </div>
        </a>
    </div>

    <div class="col-6 col-md-3">
        <a href="<?= url('student/secondary-past-questions.php?exam=waec') ?>" class="card border-0 shadow-sm rounded-4 text-decoration-none h-100 transition-hover <?= $examFilter === 'waec' ? 'border border-2 border-warning bg-warning bg-opacity-10' : 'bg-white' ?>">
            <div class="card-body p-3 text-center">
                <div class="rounded-circle bg-warning text-dark d-inline-flex align-items-center justify-content-center mb-2" style="width: 44px; height: 44px;">
                    <i class="bi bi-award-fill fs-5"></i>
                </div>
                <h6 class="fw-bold mb-1 text-dark">WAEC SSCE</h6>
                <span class="badge bg-warning text-dark rounded-pill px-3 py-1"><?= number_format((int)$examCounts['waec_count']) ?> Verified</span>
            </div>
        </a>
    </div>

    <div class="col-6 col-md-3">
        <a href="<?= url('student/secondary-past-questions.php?exam=neco') ?>" class="card border-0 shadow-sm rounded-4 text-decoration-none h-100 transition-hover <?= $examFilter === 'neco' ? 'border border-2 border-success bg-success bg-opacity-10' : 'bg-white' ?>">
            <div class="card-body p-3 text-center">
                <div class="rounded-circle bg-success text-white d-inline-flex align-items-center justify-content-center mb-2" style="width: 44px; height: 44px;">
                    <i class="bi bi-journal-bookmark-fill fs-5"></i>
                </div>
                <h6 class="fw-bold mb-1 text-dark">NECO SSCE</h6>
                <span class="badge bg-success rounded-pill px-3 py-1"><?= number_format((int)$examCounts['neco_count']) ?> Verified</span>
            </div>
        </a>
    </div>

    <div class="col-6 col-md-3">
        <a href="<?= url('student/secondary-past-questions.php?exam=utme') ?>" class="card border-0 shadow-sm rounded-4 text-decoration-none h-100 transition-hover <?= in_array($examFilter, ['utme', 'jamb']) ? 'border border-2 border-info bg-info bg-opacity-10' : 'bg-white' ?>">
            <div class="card-body p-3 text-center">
                <div class="rounded-circle bg-info text-white d-inline-flex align-items-center justify-content-center mb-2" style="width: 44px; height: 44px;">
                    <i class="bi bi-lightning-charge-fill fs-5"></i>
                </div>
                <h6 class="fw-bold mb-1 text-dark">JAMB UTME</h6>
                <span class="badge bg-info rounded-pill px-3 py-1"><?= number_format((int)$examCounts['jamb_count']) ?> Verified</span>
            </div>
        </a>
    </div>
</div>

<!-- 2. FILTER & SEARCH COMMAND BAR -->
<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-body p-3 p-md-4">
        <form method="GET" action="<?= url('student/secondary-past-questions.php') ?>" class="row g-2 align-items-end">
            <!-- Exam -->
            <div class="col-6 col-md-2">
                <label class="form-label small fw-bold text-muted mb-1">Exam Body</label>
                <select name="exam" class="form-select rounded-3">
                    <option value="">All Exams</option>
                    <option value="waec" <?= $examFilter === 'waec' ? 'selected' : '' ?>>WAEC</option>
                    <option value="neco" <?= $examFilter === 'neco' ? 'selected' : '' ?>>NECO</option>
                    <option value="utme" <?= in_array($examFilter, ['utme', 'jamb']) ? 'selected' : '' ?>>JAMB UTME</option>
                </select>
            </div>

            <!-- Subject -->
            <div class="col-6 col-md-3">
                <label class="form-label small fw-bold text-muted mb-1">Subject</label>
                <select name="subject" class="form-select rounded-3">
                    <option value="">All Subjects</option>
                    <?php foreach ($allSubjectsList as $as): ?>
                        <option value="<?= e($as['slug']) ?>" <?= $subjectFilter === $as['slug'] ? 'selected' : '' ?>>
                            <?= e($as['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Year -->
            <div class="col-6 col-md-2">
                <label class="form-label small fw-bold text-muted mb-1">Year</label>
                <select name="year" class="form-select rounded-3">
                    <option value="">All Years</option>
                    <?php foreach ($availableYears as $yr): ?>
                        <option value="<?= (int)$yr['year'] ?>" <?= $yearFilter === (int)$yr['year'] ? 'selected' : '' ?>>
                            <?= (int)$yr['year'] ?> (<?= (int)$yr['count'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Keyword Search -->
            <div class="col-6 col-md-3">
                <label class="form-label small fw-bold text-muted mb-1">Keyword Search</label>
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" name="search" class="form-control border-start-0 rounded-end-3" placeholder="Keywords, formulas..." value="<?= e($search) ?>">
                </div>
            </div>

            <!-- Submit Buttons -->
            <div class="col-12 col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary rounded-3 w-100 fw-bold">
                    <i class="bi bi-funnel me-1"></i> Filter
                </button>
                <?php if (!empty($examFilter) || !empty($subjectFilter) || !empty($yearFilter) || !empty($search) || !empty($topicFilter)): ?>
                    <a href="<?= url('student/secondary-past-questions.php') ?>" class="btn btn-outline-secondary rounded-3" title="Clear Filters">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- 3. QUESTION BROWSER (READ MODE) & RESULTS LISTING -->
<div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
    <div class="card-header bg-white p-3 p-md-4 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
            <h5 class="fw-bold mb-0 text-dark">
                <i class="bi bi-journal-text text-primary me-2"></i>Questions Found (<?= number_format($totalQuestions) ?>)
            </h5>
            <small class="text-muted">Browse &amp; read step-by-step solutions without starting a timed quiz</small>
        </div>

        <!-- Jump to Question Number Form -->
        <div class="d-flex align-items-center gap-2">
            <?php if ($totalQuestions > 0): ?>
            <form method="GET" class="d-flex align-items-center gap-2">
                <?php if ($examFilter): ?><input type="hidden" name="exam" value="<?= e($examFilter) ?>"><?php endif; ?>
                <?php if ($subjectFilter): ?><input type="hidden" name="subject" value="<?= e($subjectFilter) ?>"><?php endif; ?>
                <?php if ($yearFilter): ?><input type="hidden" name="year" value="<?= (int)$yearFilter ?>"><?php endif; ?>
                <span class="small text-muted d-none d-sm-inline">Jump to #:</span>
                <input type="number" name="qnum" min="1" max="<?= $totalQuestions ?>" class="form-control form-control-sm text-center rounded-pill" style="width: 70px;" placeholder="1" value="<?= $offset + 1 ?>">
                <button type="submit" class="btn btn-sm btn-outline-primary rounded-pill px-3">Go</button>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <div class="card-body p-3 p-md-4">
        <?php if (!empty($questionVault)): ?>
            <div class="d-flex flex-column gap-4">
                <?php foreach ($questionVault as $idx => $q): 
                    $currQNum = $offset + $idx + 1;
                ?>
                <div class="card border rounded-4 p-3 p-md-4 bg-light bg-opacity-50 shadow-none position-relative" id="question_card_<?= $q['id'] ?>">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <span class="badge bg-dark rounded-pill px-3 py-1 font-monospace">
                                Question #<?= $currQNum ?>
                            </span>
                            <span class="badge bg-warning text-dark rounded-pill px-3 py-1 fw-bold text-uppercase">
                                <?= e($q['exam_type'] === 'utme' ? 'JAMB' : strtoupper($q['exam_type'])) ?> <?= (int)$q['year'] ?>
                            </span>
                            <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 py-1 fw-bold">
                                <?= e($q['subject_name'] ?? ucfirst($q['subject_slug'])) ?>
                            </span>
                            <?php if (!empty($q['topic_name'])): ?>
                                <span class="badge bg-white text-muted border rounded-pill px-2 py-1 small">
                                    <i class="bi bi-tag-fill me-1 text-secondary"></i><?= e($q['topic_name']) ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-sm btn-outline-success rounded-pill px-3" onclick="toggleSolution('sol_<?= $q['id'] ?>')">
                                <i class="bi bi-lightbulb me-1"></i> Reveal Solution
                            </button>
                            <a href="<?= url('student/secondary-ai-tutor.php?preset=' . urlencode("Explain this {$q['exam_type']} {$q['year']} {$q['subject_name']} question in detail with step-by-step reasoning:\n\n" . $q['question_text'])) ?>" class="btn btn-sm btn-outline-warning text-dark rounded-pill px-3" title="Ask Socratic AI Tutor">
                                <i class="bi bi-robot me-1"></i> AI Tutor
                            </a>
                        </div>
                    </div>

                    <!-- Question Text -->
                    <div class="fs-5 fw-semibold text-dark mb-3 lh-base pe-2">
                        <?= $q['question_text'] ?>
                    </div>

                    <!-- Options Grid -->
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <div class="p-3 rounded-3 border bg-white d-flex align-items-center gap-2">
                                <span class="badge bg-light text-dark border px-2 py-1 fw-bold">A</span>
                                <div><?= $q['option_a'] ?></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 rounded-3 border bg-white d-flex align-items-center gap-2">
                                <span class="badge bg-light text-dark border px-2 py-1 fw-bold">B</span>
                                <div><?= $q['option_b'] ?></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 rounded-3 border bg-white d-flex align-items-center gap-2">
                                <span class="badge bg-light text-dark border px-2 py-1 fw-bold">C</span>
                                <div><?= $q['option_c'] ?></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 rounded-3 border bg-white d-flex align-items-center gap-2">
                                <span class="badge bg-light text-dark border px-2 py-1 fw-bold">D</span>
                                <div><?= $q['option_d'] ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- Hidden Solution Accordion -->
                    <div id="sol_<?= $q['id'] ?>" class="p-3 bg-white rounded-3 border border-success border-opacity-50 d-none mt-2">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="badge bg-success rounded-pill px-3 py-1 font-monospace fw-bold">
                                <i class="bi bi-check-circle-fill me-1"></i> CORRECT OPTION: <?= strtoupper(e($q['correct_option'])) ?>
                            </span>
                            <?php if (!empty($q['difficulty'])): ?>
                                <span class="badge bg-secondary bg-opacity-10 text-dark rounded-pill px-2 py-1 text-capitalize small">
                                    Difficulty: <?= e($q['difficulty']) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="text-dark small lh-lg">
                            <strong>Step-by-Step Solution &amp; Explanation:</strong>
                            <div class="mt-1 p-2 bg-light rounded-2 border">
                                <?= $q['explanation'] ?: 'Standard answer verified by StudyMe academic examiners.' ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- PAGINATION -->
            <?php if ($totalPages > 1): ?>
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mt-4 pt-3 border-top">
                <span class="small text-muted">
                    Page <?= $page ?> of <?= $totalPages ?> (Total: <?= number_format($totalQuestions) ?> questions)
                </span>
                <nav>
                    <ul class="pagination pagination-rounded mb-0">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">Previous</a>
                        </li>
                        <?php 
                        $startP = max(1, $page - 2);
                        $endP   = min($totalPages, $page + 2);
                        for ($i = $startP; $i <= $endP; $i++): 
                        ?>
                        <li class="page-item <?= $page === $i ? 'active' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                        </li>
                        <?php endfor; ?>
                        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">Next</a>
                        </li>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>

        <?php else: ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-journal-x fs-1 d-block mb-3 text-secondary opacity-50"></i>
                <h5 class="fw-bold text-dark">No questions found matching your filter</h5>
                <p class="small text-muted mb-4">Try selecting a different subject or clearing your search keywords.</p>
                <div>
                    <a href="<?= url('student/secondary-past-questions.php') ?>" class="btn btn-primary rounded-pill px-4 fw-bold">
                        <i class="bi bi-arrow-counterclockwise me-1"></i> Reset All Filters
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- 4. QUICK CBT PRACTICE LAUNCHER MODAL -->
<div class="modal fade" id="quickCbtModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-play-circle-fill text-primary me-2"></i>Configure CBT Practice Session</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="GET" action="<?= url('student/secondary-practice.php') ?>">
                <div class="modal-body py-3">
                    <p class="small text-muted mb-3">Choose your exam parameters and desired question volume to begin a full test simulation.</p>

                    <div class="mb-3">
                        <label class="form-label fw-bold small">Target Examination</label>
                        <select name="exam" class="form-select rounded-3">
                            <option value="waec" <?= $examFilter === 'waec' ? 'selected' : '' ?>>WAEC SSCE Exam</option>
                            <option value="neco" <?= $examFilter === 'neco' ? 'selected' : '' ?>>NECO SSCE Exam</option>
                            <option value="utme" <?= in_array($examFilter, ['utme', 'jamb']) ? 'selected' : '' ?>>JAMB UTME Mock</option>
                            <option value="all">Mixed Examination Drill</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small">Subject</label>
                        <select name="subject" class="form-select rounded-3">
                            <?php foreach ($allSubjectsList as $as): ?>
                                <option value="<?= e($as['slug']) ?>" <?= $subjectFilter === $as['slug'] ? 'selected' : '' ?>>
                                    <?= e($as['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-bold small">Target Year</label>
                            <select name="year" class="form-select rounded-3">
                                <option value="">All Available Years</option>
                                <option value="2024">2024</option>
                                <option value="2023">2023</option>
                                <option value="2022">2022</option>
                                <option value="2021">2021</option>
                                <option value="2020">2020</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold small">Question Volume</label>
                            <select name="count" class="form-select rounded-3">
                                <option value="10">10 Questions (Quick)</option>
                                <option value="20" selected>20 Questions (Standard)</option>
                                <option value="30">30 Questions (Full Test)</option>
                                <option value="40">40 Questions (JAMB Format)</option>
                                <option value="50">50 Questions (WAEC Format)</option>
                                <option value="all">All Available Questions</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small">Practice Mode</label>
                        <select name="mode" class="form-select rounded-3">
                            <option value="cbt_timed">Timed Exam Simulation (With live countdown)</option>
                            <option value="practice_study">Study Drill Mode (Instant solution review)</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm">
                        <i class="bi bi-lightning-charge-fill me-1"></i> Start Practice Session
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function toggleSolution(elementId) {
    const el = document.getElementById(elementId);
    if (!el) return;
    el.classList.toggle('d-none');
    if (window.MathJax && !el.classList.contains('d-none')) {
        MathJax.typesetPromise([el]).catch(function (err) {
            console.warn('MathJax rendering error:', err);
        });
    }
}
</script>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
