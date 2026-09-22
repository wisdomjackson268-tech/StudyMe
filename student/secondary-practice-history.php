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

log_user_activity($userId, 'accessed_practice_history', 'Student viewed CBT Practice History');

$examFilter = strtolower(trim($_GET['exam'] ?? ''));
if ($examFilter === 'jamb') $examFilter = 'utme';

$where = ["a.student_id = ?"];
$params = [$studentId];

if (!empty($examFilter) && in_array($examFilter, ['waec', 'neco', 'utme'], true)) {
    if ($examFilter === 'utme') {
        $where[] = "(a.exam_type = 'utme' OR a.exam_type = 'jamb')";
    } else {
        $where[] = "a.exam_type = ?";
        $params[] = $examFilter;
    }
}

// Fetch all practice attempts
$attempts = [];
if ($studentId) {
    $sql = "
        SELECT a.*, COALESCE(s.name, a.subject_slug) AS subject_title, s.color AS subject_color, s.icon AS subject_icon
        FROM secondary_practice_attempts a
        LEFT JOIN secondary_subjects s ON a.subject_slug = s.slug
        WHERE " . implode(' AND ', $where) . "
        ORDER BY a.started_at DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $attempts = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Stats overview
$stats = get_secondary_practice_stats($studentId);

$pageTitle = 'CBT Practice History & Exam Logs | StudyMe';
include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?= url('student/secondary-dashboard.php') ?>" class="text-decoration-none">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= url('student/secondary-past-questions.php') ?>" class="text-decoration-none">Past Questions</a></li>
                <li class="breadcrumb-item active">Practice History</li>
            </ol>
        </nav>
        <h2 class="fw-bold mb-0 text-dark">
            <i class="bi bi-clock-history text-primary me-2"></i>CBT Practice History &amp; Test Logs
        </h2>
        <p class="text-muted mb-0 small">Review past mock attempts, track accuracy trends, and analyze step-by-step verified solutions.</p>
    </div>

    <div class="d-flex gap-2">
        <a href="<?= url('student/secondary-past-questions.php') ?>" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm">
            <i class="bi bi-play-circle-fill me-1"></i> Start New Practice
        </a>
    </div>
</div>

<!-- PERFORMANCE METRICS CARDS -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-circle bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center" style="width:48px; height:48px;">
                    <i class="bi bi-journal-check fs-4"></i>
                </div>
                <div>
                    <h3 class="fw-bold mb-0 text-dark"><?= (int)$stats['total_attempts'] ?></h3>
                    <small class="text-muted">Total Sessions</small>
                </div>
            </div>
        </div>
    </div>

    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-circle bg-success bg-opacity-10 text-success d-flex align-items-center justify-content-center" style="width:48px; height:48px;">
                    <i class="bi bi-bullseye fs-4"></i>
                </div>
                <div>
                    <h3 class="fw-bold mb-0 text-success"><?= (float)$stats['average_accuracy'] ?>%</h3>
                    <small class="text-muted">Average Accuracy</small>
                </div>
            </div>
        </div>
    </div>

    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-circle bg-warning bg-opacity-10 text-warning d-flex align-items-center justify-content-center" style="width:48px; height:48px;">
                    <i class="bi bi-patch-question fs-4"></i>
                </div>
                <div>
                    <h3 class="fw-bold mb-0 text-dark"><?= (int)$stats['total_questions'] ?></h3>
                    <small class="text-muted">Questions Practiced</small>
                </div>
            </div>
        </div>
    </div>

    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-circle bg-info bg-opacity-10 text-info d-flex align-items-center justify-content-center" style="width:48px; height:48px;">
                    <i class="bi bi-hourglass-split fs-4"></i>
                </div>
                <div>
                    <h3 class="fw-bold mb-0 text-dark"><?= (int)$stats['total_time_minutes'] ?>m</h3>
                    <small class="text-muted">Total Practice Time</small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- FILTER TABS & HISTORY TABLE -->
<div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
    <div class="card-header bg-white p-3 p-md-4 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div class="nav nav-pills gap-2">
            <a href="secondary-practice-history.php" class="nav-link rounded-pill px-3 py-1 small <?= empty($examFilter) ? 'active bg-primary' : 'text-muted' ?>">
                All Attempts (<?= (int)$stats['total_attempts'] ?>)
            </a>
            <a href="secondary-practice-history.php?exam=waec" class="nav-link rounded-pill px-3 py-1 small <?= $examFilter === 'waec' ? 'active bg-warning text-dark fw-bold' : 'text-muted' ?>">
                WAEC (<?= (int)$stats['waec_attempts'] ?>)
            </a>
            <a href="secondary-practice-history.php?exam=neco" class="nav-link rounded-pill px-3 py-1 small <?= $examFilter === 'neco' ? 'active bg-success' : 'text-muted' ?>">
                NECO (<?= (int)$stats['neco_attempts'] ?>)
            </a>
            <a href="secondary-practice-history.php?exam=utme" class="nav-link rounded-pill px-3 py-1 small <?= in_array($examFilter, ['utme', 'jamb']) ? 'active bg-info' : 'text-muted' ?>">
                JAMB UTME (<?= (int)$stats['jamb_attempts'] ?>)
            </a>
        </div>

        <span class="text-muted small">Showing <?= count($attempts) ?> records</span>
    </div>

    <?php if (!empty($attempts)): ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">Examination</th>
                        <th>Subject &amp; Topic</th>
                        <th>Questions</th>
                        <th>Score &amp; Accuracy</th>
                        <th>Duration</th>
                        <th>Date Completed</th>
                        <th class="text-end pe-4">Review Solutions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($attempts as $a): 
                        $examBadgeClass = 'bg-primary';
                        if ($a['exam_type'] === 'waec') $examBadgeClass = 'bg-warning text-dark';
                        elseif ($a['exam_type'] === 'neco') $examBadgeClass = 'bg-success';
                        elseif ($a['exam_type'] === 'utme' || $a['exam_type'] === 'jamb') $examBadgeClass = 'bg-info text-dark';
                    ?>
                    <tr>
                        <td class="ps-4">
                            <span class="badge <?= $examBadgeClass ?> rounded-pill px-3 py-1 fw-bold text-uppercase">
                                <?= e($a['exam_type'] === 'utme' ? 'JAMB' : $a['exam_type']) ?>
                            </span>
                            <?php if ($a['year']): ?>
                                <span class="badge bg-light text-dark border rounded-pill px-2 py-0 ms-1 small">
                                    <?= (int)$a['year'] ?>
                                </span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <div class="fw-bold text-dark"><?= e($a['subject_title']) ?></div>
                            <?php if (!empty($a['topic_name'])): ?>
                                <small class="text-muted d-block"><?= e($a['topic_name']) ?></small>
                            <?php endif; ?>
                        </td>

                        <td>
                            <span class="fw-semibold text-dark"><?= (int)$a['total_questions'] ?> Qs</span>
                        </td>

                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <span class="fw-bold"><?= (int)$a['correct_answers'] ?> / <?= (int)$a['total_questions'] ?></span>
                                <span class="badge <?= (float)$a['score_percentage'] >= 70 ? 'bg-success' : ((float)$a['score_percentage'] >= 50 ? 'bg-warning text-dark' : 'bg-danger') ?> rounded-pill px-2 py-0">
                                    <?= (float)$a['score_percentage'] ?>%
                                </span>
                            </div>
                        </td>

                        <td class="small text-muted">
                            <i class="bi bi-stopwatch me-1"></i>
                            <?= floor((int)$a['time_spent_seconds'] / 60) ?>m <?= ((int)$a['time_spent_seconds'] % 60) ?>s
                        </td>

                        <td class="small text-muted">
                            <?= date('M d, Y h:i A', strtotime($a['started_at'])) ?>
                        </td>

                        <td class="text-end pe-4">
                            <a href="<?= url('student/secondary-practice.php?review_attempt=' . (int)$a['id']) ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-bold">
                                <i class="bi bi-card-checklist me-1"></i> Review Answers
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="p-5 text-center text-muted">
            <i class="bi bi-clock fs-1 d-block mb-3 text-secondary opacity-50"></i>
            <h5 class="fw-bold text-dark">No practice sessions found</h5>
            <p class="small text-muted mb-4">You haven't completed any CBT practice sessions under this filter yet.</p>
            <div>
                <a href="<?= url('student/secondary-past-questions.php') ?>" class="btn btn-primary rounded-pill px-4 fw-bold">
                    <i class="bi bi-lightning-charge-fill me-1"></i> Launch a Practice Session Now
                </a>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
