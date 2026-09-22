<?php

require_once dirname(__DIR__) . '/config/main.php';
require_once BASE_PATH . '/includes/functions/enrollments.php';
require_once BASE_PATH . '/includes/functions/activity.php';

secure_page(ROLE_STUDENT);

$user   = current_user();
$userId = (int)$user['id'];
$pdo    = getDBConnection();

$studentStmt = $pdo->prepare("SELECT id FROM students WHERE user_id = ? LIMIT 1");
$studentStmt->execute([$userId]);
$studentId = (int)$studentStmt->fetchColumn();

log_user_activity($userId, 'accessed_neco_hub', 'Student accessed NECO SSCE Preparation Portal');

// Fetch NECO Subjects with real question counts
$necoSubjects = get_secondary_exam_subjects('neco');
$allSubjects = get_secondary_subjects('active');

$subjectMap = [];
foreach ($necoSubjects as $ns) {
    $subjectMap[$ns['subject_slug']] = $ns;
}

$displaySubjects = [];
foreach ($allSubjects as $as) {
    if (isset($subjectMap[$as['slug']])) {
        $displaySubjects[] = $subjectMap[$as['slug']];
    } else {
        $displaySubjects[] = [
            'subject_slug' => $as['slug'],
            'subject_name' => $as['name'],
            'icon' => $as['icon'] ?: 'bi-book-half',
            'color' => $as['color'] ?: '#087f5b',
            'question_count' => (int)$as['question_count'],
            'min_year' => 2020,
            'max_year' => 2024
        ];
    }
}

// Fetch past NECO attempts for student
$necoAttempts = [];
if ($studentId) {
    try {
        $stmtAtt = $pdo->prepare("
            SELECT a.*, COALESCE(s.name, a.subject_slug) AS subject_title
            FROM secondary_practice_attempts a
            LEFT JOIN secondary_subjects s ON a.subject_slug = s.slug
            WHERE a.student_id = ? AND a.exam_type = 'neco'
            ORDER BY a.started_at DESC
            LIMIT 6
        ");
        $stmtAtt->execute([$studentId]);
        $necoAttempts = $stmtAtt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
}

$pageTitle = 'NECO SSCE Past Questions & Practice Hub | StudyMe';
include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?= url('student/secondary-dashboard.php') ?>" class="text-decoration-none">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= url('student/secondary-past-questions.php') ?>" class="text-decoration-none">Past Questions</a></li>
                <li class="breadcrumb-item active" aria-current="page">NECO SSCE</li>
            </ol>
        </nav>
        <h2 class="fw-bold mb-0 text-dark">
            <i class="bi bi-journal-bookmark-fill text-success me-2"></i>NECO SSCE Exam Simulator &amp; Past Questions
        </h2>
        <p class="text-muted mb-0 small">Practice National Examinations Council Senior School Certificate Examination papers with full solutions.</p>
    </div>

    <div class="d-flex gap-2">
        <a href="<?= url('student/secondary-past-questions.php?exam=neco') ?>" class="btn btn-outline-success rounded-pill px-3 fw-semibold">
            <i class="bi bi-search me-1"></i> Browse All NECO Questions
        </a>
    </div>
</div>

<!-- NECO HERO BANNER -->
<div class="card border-0 rounded-4 shadow-sm overflow-hidden mb-4" style="background: linear-gradient(135deg, #065f46 0%, #064e3b 100%);">
    <div class="card-body p-4 p-md-5 text-white position-relative">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <span class="badge bg-success bg-opacity-50 border border-light rounded-pill px-3 py-1 mb-2 fw-bold text-uppercase text-white">
                    National Examinations Council Standard
                </span>
                <h3 class="fw-bold text-white mb-2">NECO Senior School Certificate Exam Mock</h3>
                <p class="text-light opacity-75 mb-4">
                    Target top grades in your NECO June/July and Nov/Dec examinations with real previous exam questions and instant feedback.
                </p>
                
                <!-- Quick Practice Config Form -->
                <form method="GET" action="<?= url('student/secondary-practice.php') ?>" class="row g-2 bg-white bg-opacity-10 p-3 rounded-4 backdrop-blur">
                    <input type="hidden" name="exam" value="neco">
                    
                    <div class="col-md-4">
                        <select name="subject" class="form-select rounded-3 bg-white border-0" required>
                            <option value="">-- Choose Subject --</option>
                            <?php foreach ($displaySubjects as $ds): ?>
                                <option value="<?= e($ds['subject_slug']) ?>"><?= e($ds['subject_name']) ?> (<?= (int)$ds['question_count'] ?> Qs)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <select name="year" class="form-select rounded-3 bg-white border-0">
                            <option value="">All Years (2020–2024)</option>
                            <option value="2024">2024 Paper</option>
                            <option value="2023">2023 Paper</option>
                            <option value="2022">2022 Paper</option>
                            <option value="2021">2021 Paper</option>
                            <option value="2020">2020 Paper</option>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <select name="count" class="form-select rounded-3 bg-white border-0">
                            <option value="10">10 Questions</option>
                            <option value="20" selected>20 Questions</option>
                            <option value="30">30 Questions</option>
                            <option value="50">50 Questions (Full Paper)</option>
                            <option value="all">All Available</option>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <button type="submit" class="btn btn-light text-success fw-bold rounded-3 w-100">
                            <i class="bi bi-play-fill me-1"></i> Start CBT
                        </button>
                    </div>
                </form>
            </div>
            
            <div class="col-lg-4 text-center d-none d-lg-block">
                <i class="bi bi-journal-check text-success text-opacity-50" style="font-size: 8rem; color: #a7f3d0 !important;"></i>
            </div>
        </div>
    </div>
</div>

<!-- SUBJECTS DIRECTORY -->
<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-header bg-white p-3 p-md-4 border-bottom d-flex align-items-center justify-content-between">
        <h5 class="fw-bold mb-0 text-dark">
            <i class="bi bi-grid-fill text-success me-2"></i>Select Subject to Browse or Practice
        </h5>
        <span class="badge bg-light text-dark border rounded-pill px-3 py-1"><?= count($displaySubjects) ?> Subjects Available</span>
    </div>
    
    <div class="card-body p-3 p-md-4">
        <div class="row g-3">
            <?php foreach ($displaySubjects as $s): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card border rounded-4 h-100 p-3 bg-light bg-opacity-25 transition-hover">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div class="rounded-3 p-2 text-white d-flex align-items-center justify-content-center" style="width: 44px; height: 44px; background-color: <?= e($s['color'] ?: '#087f5b') ?>;">
                                <i class="bi <?= e($s['icon'] ?: 'bi-book-half') ?> fs-5"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h6 class="fw-bold mb-0 text-dark"><?= e($s['subject_name']) ?></h6>
                                <small class="text-muted"><?= (int)$s['question_count'] ?> Verified Past Questions</small>
                            </div>
                        </div>

                        <div class="d-flex gap-2 mt-auto pt-2 border-top">
                            <a href="<?= url('student/secondary-past-questions.php?exam=neco&subject=' . urlencode($s['subject_slug'])) ?>" class="btn btn-sm btn-outline-secondary rounded-pill flex-grow-1">
                                <i class="bi bi-eye me-1"></i> Read Questions
                            </a>
                            <a href="<?= url('student/secondary-practice.php?exam=neco&subject=' . urlencode($s['subject_slug']) . '&count=20') ?>" class="btn btn-sm btn-success fw-bold rounded-pill px-3">
                                <i class="bi bi-play-fill"></i> Practice
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- RECENT NECO ATTEMPTS -->
<?php if (!empty($necoAttempts)): ?>
<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-header bg-white p-3 p-md-4 border-bottom d-flex align-items-center justify-content-between">
        <h5 class="fw-bold mb-0 text-dark">
            <i class="bi bi-clock-history text-success me-2"></i>Recent NECO Mock Attempts
        </h5>
        <a href="<?= url('student/secondary-practice-history.php') ?>" class="btn btn-sm btn-outline-success rounded-pill px-3">View Full History</a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="bg-light">
                <tr>
                    <th class="ps-4">Subject</th>
                    <th>Questions</th>
                    <th>Score</th>
                    <th>Accuracy</th>
                    <th>Date</th>
                    <th class="text-end pe-4">Review</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($necoAttempts as $att): ?>
                <tr>
                    <td class="ps-4 fw-bold"><?= e($att['subject_title']) ?></td>
                    <td><?= (int)$att['total_questions'] ?> Qs</td>
                    <td class="fw-bold"><?= (int)$att['correct_answers'] ?> / <?= (int)$att['total_questions'] ?></td>
                    <td>
                        <span class="badge <?= (float)$att['score_percentage'] >= 70 ? 'bg-success' : ((float)$att['score_percentage'] >= 50 ? 'bg-warning text-dark' : 'bg-danger') ?> rounded-pill px-3 py-1">
                            <?= (float)$att['score_percentage'] ?>%
                        </span>
                    </td>
                    <td class="small text-muted"><?= date('M d, Y h:i A', strtotime($att['started_at'])) ?></td>
                    <td class="text-end pe-4">
                        <a href="<?= url('student/secondary-practice.php?review_attempt=' . (int)$att['id']) ?>" class="btn btn-sm btn-outline-success rounded-pill px-3">
                            <i class="bi bi-card-checklist me-1"></i> Review
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
