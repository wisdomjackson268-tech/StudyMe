<?php
/**
 * StudyMe AI Platform — Past Questions Explorer (WAEC, NECO, JAMB)
 * Provides interactive past questions by Exam -> Year -> Subject with step-by-step solutions.
 */
require_once dirname(__DIR__) . '/config/main.php';

$pdo = getDBConnection();

$examType    = strtolower(trim($_GET['exam'] ?? 'waec'));
$year        = (int)($_GET['year'] ?? 2024);
$subjectSlug = strtolower(trim($_GET['subject'] ?? ''));

// Validate exam type
if (!in_array($examType, ['waec', 'neco', 'jamb'])) {
    $examType = 'waec';
}

// Fetch available years for this exam
$stmtYears = $pdo->prepare("SELECT DISTINCT year FROM past_questions WHERE exam_type = ? ORDER BY year DESC");
$stmtYears->execute([$examType]);
$availableYears = $stmtYears->fetchAll(PDO::FETCH_COLUMN) ?: [2024, 2023, 2022];

// Fetch available subjects for this exam & year
$stmtSubj = $pdo->prepare("SELECT DISTINCT subject_name, subject_slug FROM past_questions WHERE exam_type = ? AND year = ? ORDER BY subject_name ASC");
$stmtSubj->execute([$examType, $year]);
$availableSubjects = $stmtSubj->fetchAll(PDO::FETCH_ASSOC);

if (empty($subjectSlug) && !empty($availableSubjects)) {
    $subjectSlug = $availableSubjects[0]['subject_slug'];
}

// Fetch questions
$questions = [];
if (!empty($subjectSlug)) {
    $stmtQ = $pdo->prepare("
        SELECT * FROM past_questions
        WHERE exam_type = ? AND year = ? AND subject_slug = ?
        ORDER BY question_number ASC
    ");
    $stmtQ->execute([$examType, $year, $subjectSlug]);
    $questions = $stmtQ->fetchAll(PDO::FETCH_ASSOC);
}

$pageTitle = strtoupper($examType) . " $year Past Questions — StudyMe";
include BASE_PATH . '/includes/layouts/header.php';
?>

<div class="py-5 bg-light-subtle" style="min-height: calc(100vh - 120px);">
    <div class="container py-4">
        
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb small">
                <li class="breadcrumb-item"><a href="<?= url('index.php') ?>" class="text-decoration-none">Home</a></li>
                <li class="breadcrumb-item"><a href="<?= url('courses/index.php') ?>" class="text-decoration-none">Courses</a></li>
                <li class="breadcrumb-item"><a href="<?= url('courses/secondary.php') ?>" class="text-decoration-none">Secondary School</a></li>
                <li class="breadcrumb-item active" aria-current="page">Past Questions</li>
            </ol>
        </nav>

        <!-- Header -->
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
            <div>
                <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 py-1 fw-bold text-uppercase small mb-2">
                    <i class="bi bi-file-earmark-text me-1"></i> Exam Preparation Vault
                </span>
                <h1 class="display-6 fw-bold mb-1">Official Past Questions &amp; Practice</h1>
                <p class="text-muted mb-0">Browse real past examination questions with step-by-step solutions and instant feedback.</p>
            </div>
            <a href="<?= url('courses/secondary.php') ?>" class="btn btn-outline-secondary rounded-pill px-4 fw-semibold btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Back to Secondary Hub
            </a>
        </div>

        <!-- Filter Card: Exam -> Year -> Subject -->
        <div class="card border-0 shadow-sm rounded-4 p-4 mb-4" style="background: var(--bg-card, #ffffff); border: 1px solid var(--border-color, #e2e8f0) !important;">
            <div class="row g-3 align-items-center">
                
                <!-- 1. Exam Selector -->
                <div class="col-md-4">
                    <label class="form-label small fw-bold text-muted text-uppercase mb-1">1. Select Examination</label>
                    <div class="btn-group w-100" role="group">
                        <a href="<?= url('courses/past-questions.php?exam=waec&year=' . $year) ?>" class="btn <?= $examType==='waec'?'btn-primary fw-bold':'btn-outline-secondary' ?>">
                            WAEC (SSCE)
                        </a>
                        <a href="<?= url('courses/past-questions.php?exam=neco&year=' . $year) ?>" class="btn <?= $examType==='neco'?'btn-primary fw-bold':'btn-outline-secondary' ?>">
                            NECO
                        </a>
                        <a href="<?= url('courses/past-questions.php?exam=jamb&year=' . $year) ?>" class="btn <?= $examType==='jamb'?'btn-primary fw-bold':'btn-outline-secondary' ?>">
                            JAMB (UTME)
                        </a>
                    </div>
                </div>

                <!-- 2. Year Selector -->
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-muted text-uppercase mb-1">2. Examination Year</label>
                    <select class="form-select rounded-3" onchange="location.href='<?= url('courses/past-questions.php?exam=' . $examType . '&year=') ?>' + this.value;">
                        <?php foreach ([2025, 2024, 2023, 2022, 2021, 2020] as $yr): ?>
                        <option value="<?= $yr ?>" <?= $year===$yr?'selected':'' ?>><?= $yr ?> Exam Series</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- 3. Subject Selector -->
                <div class="col-md-5">
                    <label class="form-label small fw-bold text-muted text-uppercase mb-1">3. Subject</label>
                    <select class="form-select rounded-3" onchange="location.href='<?= url('courses/past-questions.php?exam=' . $examType . '&year=' . $year . '&subject=') ?>' + this.value;">
                        <?php if (!empty($availableSubjects)): ?>
                            <?php foreach ($availableSubjects as $sb): ?>
                            <option value="<?= e($sb['subject_slug']) ?>" <?= $subjectSlug===$sb['subject_slug']?'selected':'' ?>>
                                <?= e($sb['subject_name']) ?>
                            </option>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <option value="">No questions loaded yet for this selection</option>
                        <?php endif; ?>
                    </select>
                </div>

            </div>
        </div>

        <!-- Questions Feed -->
        <?php if (!empty($questions)): ?>
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h5 class="fw-bold mb-0 text-main">
                    <i class="bi bi-patch-question-fill text-primary me-2"></i>
                    <?= strtoupper($examType) ?> <?= $year ?> &mdash; <?= ucfirst($subjectSlug) ?> (<?= count($questions) ?> Questions)
                </h5>
                <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 py-1 fw-bold">Active Drill Mode</span>
            </div>

            <div class="d-flex flex-column gap-4 mb-5">
                <?php foreach ($questions as $q): ?>
                <div class="card border-0 shadow-sm rounded-4 p-4" style="background: var(--bg-card, #ffffff); border: 1px solid var(--border-color, #e2e8f0) !important;">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <span class="badge bg-primary text-white rounded-pill px-3 py-1 fw-bold">
                            Question <?= (int)$q['question_number'] ?>
                        </span>
                        <span class="text-muted small"><?= strtoupper($examType) ?> <?= $year ?></span>
                    </div>

                    <div class="fw-bold text-main fs-6 mb-4 lh-base">
                        <?= e($q['question_text']) ?>
                    </div>

                    <!-- Options Grid -->
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <div class="p-3 rounded-3 border bg-light d-flex align-items-center gap-2">
                                <span class="badge bg-secondary text-white rounded-circle">A</span>
                                <span class="small text-main"><?= e($q['option_a']) ?></span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 rounded-3 border bg-light d-flex align-items-center gap-2">
                                <span class="badge bg-secondary text-white rounded-circle">B</span>
                                <span class="small text-main"><?= e($q['option_b']) ?></span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 rounded-3 border bg-light d-flex align-items-center gap-2">
                                <span class="badge bg-secondary text-white rounded-circle">C</span>
                                <span class="small text-main"><?= e($q['option_c']) ?></span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 rounded-3 border bg-light d-flex align-items-center gap-2">
                                <span class="badge bg-secondary text-white rounded-circle">D</span>
                                <span class="small text-main"><?= e($q['option_d']) ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Reveal Solution Toggle -->
                    <div class="mt-2 pt-3 border-top border-subtle d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <button class="btn btn-sm btn-outline-primary rounded-pill px-4 fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#sol_<?= $q['id'] ?>" aria-expanded="false">
                            <i class="bi bi-lightbulb-fill me-1 text-warning"></i> View Correct Answer &amp; Solution
                        </button>
                        <span class="text-muted small">Verified by Subject Specialists</span>
                    </div>

                    <!-- Solution Collapse Body -->
                    <div class="collapse mt-3" id="sol_<?= $q['id'] ?>">
                        <div class="p-3 rounded-3 bg-success bg-opacity-10 border border-success border-opacity-25">
                            <div class="fw-bold text-success mb-1">
                                <i class="bi bi-check-circle-fill me-1"></i> Correct Answer: Option <?= e($q['correct_option']) ?>
                            </div>
                            <?php if (!empty($q['explanation'])): ?>
                            <p class="small text-muted mb-0 lh-base">
                                <strong>Explanation:</strong> <?= e($q['explanation']) ?>
                            </p>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>
                <?php endforeach; ?>
            </div>

        <?php else: ?>
            <div class="card border-0 shadow-sm rounded-4 p-5 text-center my-4">
                <i class="bi bi-folder-symlink text-muted fs-1 mb-2"></i>
                <h4 class="fw-bold mb-1">No Past Questions Found For This Filter</h4>
                <p class="text-muted mb-3">Questions for this examination series are currently being compiled into the vault. Try exploring WAEC 2024 Mathematics or English.</p>
                <a href="<?= url('courses/past-questions.php?exam=waec&year=2024&subject=mathematics') ?>" class="btn btn-primary rounded-pill px-4 fw-bold mx-auto">
                    View WAEC 2024 Mathematics
                </a>
            </div>
        <?php endif; ?>

    </div>
</div>

<?php include BASE_PATH . '/includes/layouts/footer.php'; ?>
