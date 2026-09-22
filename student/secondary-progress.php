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

log_user_activity($userId, 'viewed_secondary_progress', 'Student reviewed Secondary Progress Analytics');

$stats = get_secondary_practice_stats($studentId);
$history = get_secondary_practice_history($studentId, 25);

// Subject accuracy breakdown
$subjectBreakdown = [];
if ($studentId) {
    try {
        $stmtSub = $pdo->prepare("
            SELECT a.subject_slug, s.name AS subject_name, s.color AS subject_color, s.icon AS subject_icon,
                   COUNT(a.id) AS total_attempts,
                   SUM(a.total_questions) AS total_questions,
                   SUM(a.correct_answers) AS total_correct,
                   ROUND(AVG(a.score_percentage), 1) AS avg_percentage
            FROM secondary_practice_attempts a
            LEFT JOIN secondary_subjects s ON a.subject_slug = s.slug
            WHERE a.student_id = ? AND a.completed_at IS NOT NULL
            GROUP BY a.subject_slug, s.name, s.color, s.icon
            ORDER BY avg_percentage DESC
        ");
        $stmtSub->execute([$studentId]);
        $subjectBreakdown = $stmtSub->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
}

$streak = calculate_user_learning_streak($userId);

$pageTitle = 'Secondary Learning Progress & Analytics | StudyMe';
include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <a href="<?= url('student/secondary-dashboard.php') ?>" class="text-decoration-none text-muted small">
                <i class="bi bi-arrow-left me-1"></i> Dashboard
            </a>
            <span class="text-muted small">&bull;</span>
            <span class="text-muted small">Telemetry</span>
        </div>
        <h2 class="fw-bold mb-0 text-dark">
            <i class="bi bi-graph-up-arrow text-primary me-2"></i> My Progress &amp; Analytics
        </h2>
    </div>

    <a href="<?= url('student/secondary-practice.php') ?>" class="btn btn-primary rounded-pill px-4 fw-bold">
        <i class="bi bi-play-circle-fill me-1"></i> Start New CBT Drill
    </a>
</div>

<!-- KEY METRIC CARDS -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm rounded-4 p-4 h-100 bg-white border">
            <small class="text-muted fw-bold">Questions Attempted</small>
            <h3 class="fw-bold text-dark mb-1"><?= number_format($stats['total_questions']) ?></h3>
            <span class="text-success small fw-semibold"><i class="bi bi-check-lg"></i> <?= $stats['correct_answers'] ?> Correct</span>
        </div>
    </div>

    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm rounded-4 p-4 h-100 bg-white border">
            <small class="text-muted fw-bold">Overall Accuracy</small>
            <h3 class="fw-bold text-primary mb-1"><?= $stats['average_accuracy'] ?>%</h3>
            <span class="text-muted small"><?= $stats['total_attempts'] ?> Tests Completed</span>
        </div>
    </div>

    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm rounded-4 p-4 h-100 bg-white border">
            <small class="text-muted fw-bold">Practice Time</small>
            <h3 class="fw-bold text-dark mb-1"><?= $stats['total_time_minutes'] ?> mins</h3>
            <span class="text-muted small">CBT Drill Time</span>
        </div>
    </div>

    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm rounded-4 p-4 h-100 bg-white border">
            <small class="text-muted fw-bold">Daily Streak</small>
            <h3 class="fw-bold text-warning mb-1">🔥 <?= (int)$streak['streak_days'] ?> Days</h3>
            <span class="text-muted small">Continuous Learning</span>
        </div>
    </div>
</div>

<div class="row g-4 mb-5">

    <!-- SUBJECT PERFORMANCE BREAKDOWN -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm rounded-4 p-4 h-100 bg-white border">
            <h5 class="fw-bold text-dark mb-3">
                <i class="bi bi-bar-chart-fill text-primary me-2"></i> Subject Mastery &amp; Accuracy
            </h5>

            <?php if (!empty($subjectBreakdown)): ?>
                <div class="d-flex flex-column gap-3">
                    <?php foreach ($subjectBreakdown as $sb): ?>
                    <div class="p-3 bg-light rounded-3 border">
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <span class="fw-bold text-dark"><?= e($sb['subject_name'] ?? ucfirst($sb['subject_slug'])) ?></span>
                            <span class="badge <?= (float)$sb['avg_percentage'] >= 70 ? 'bg-success' : ((float)$sb['avg_percentage'] >= 50 ? 'bg-warning text-dark' : 'bg-danger') ?> rounded-pill px-3 py-1 fw-bold">
                                <?= $sb['avg_percentage'] ?>%
                            </span>
                        </div>
                        <div class="progress mb-2" style="height: 8px;">
                            <div class="progress-bar <?= (float)$sb['avg_percentage'] >= 70 ? 'bg-success' : ((float)$sb['avg_percentage'] >= 50 ? 'bg-warning' : 'bg-danger') ?>" style="width: <?= min(100, (float)$sb['avg_percentage']) ?>%;"></div>
                        </div>
                        <div class="d-flex justify-content-between text-muted" style="font-size: 0.75rem;">
                            <span><?= $sb['total_correct'] ?> of <?= $sb['total_questions'] ?> correct</span>
                            <span><?= $sb['total_attempts'] ?> attempts</span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-pie-chart fs-1 d-block mb-2 text-muted"></i>
                    No subject drills completed yet. Start a CBT practice to track your subject mastery!
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- EXAM DISTRIBUTION PIE METRICS -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm rounded-4 p-4 h-100 bg-white border">
            <h5 class="fw-bold text-dark mb-3">
                <i class="bi bi-pie-chart-fill text-success me-2"></i> Exam Category Drills
            </h5>

            <div class="row g-3 text-center mb-4">
                <div class="col-4">
                    <div class="p-3 bg-warning bg-opacity-10 rounded-4 border border-warning border-opacity-25">
                        <span class="badge bg-warning text-dark rounded-pill px-2 py-1 small mb-1">WAEC</span>
                        <h4 class="fw-bold mb-0 text-dark"><?= $stats['waec_attempts'] ?></h4>
                        <small class="text-muted">Attempts</small>
                    </div>
                </div>
                <div class="col-4">
                    <div class="p-3 bg-info bg-opacity-10 rounded-4 border border-info border-opacity-25">
                        <span class="badge bg-info text-dark rounded-pill px-2 py-1 small mb-1">NECO</span>
                        <h4 class="fw-bold mb-0 text-dark"><?= $stats['neco_attempts'] ?></h4>
                        <small class="text-muted">Attempts</small>
                    </div>
                </div>
                <div class="col-4">
                    <div class="p-3 bg-success bg-opacity-10 rounded-4 border border-success border-opacity-25">
                        <span class="badge bg-success text-white rounded-pill px-2 py-1 small mb-1">JAMB</span>
                        <h4 class="fw-bold mb-0 text-dark"><?= $stats['jamb_attempts'] ?></h4>
                        <small class="text-muted">Attempts</small>
                    </div>
                </div>
            </div>

            <div class="p-3 bg-light rounded-3 border">
                <h6 class="fw-bold text-dark mb-1"><i class="bi bi-lightbulb-fill text-warning me-1"></i> Exam Strategy Recommendation:</h6>
                <p class="text-muted small mb-0">
                    Aim for a minimum of <strong>75% accuracy</strong> across your 4 target UTME subjects and 5 core WAEC subjects to guarantee distinction grades.
                </p>
            </div>
        </div>
    </div>

</div>

<!-- COMPREHENSIVE PRACTICE HISTORY LEDGER -->
<div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-5 bg-white border">
    <div class="card-header bg-white p-3 p-md-4 border-bottom">
        <h5 class="fw-bold text-dark mb-0">
            <i class="bi bi-clock-history text-primary me-2"></i> Historical Practice Ledger
        </h5>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
            <thead class="bg-light">
                <tr>
                    <th class="ps-4">Exam &amp; Subject</th>
                    <th>Date &amp; Time</th>
                    <th>Mode</th>
                    <th>Questions</th>
                    <th>Accuracy</th>
                    <th class="text-end pe-4">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($history)): ?>
                    <?php foreach ($history as $h): ?>
                    <tr>
                        <td class="ps-4">
                            <span class="badge bg-dark font-monospace me-1"><?= strtoupper(e($h['exam_type'])) ?></span>
                            <span class="fw-bold text-dark"><?= e($h['subject_name'] ?? ucfirst($h['subject_slug'])) ?></span>
                        </td>
                        <td class="text-muted"><?= date('M d, Y h:i A', strtotime($h['started_at'])) ?></td>
                        <td><?= e($h['mode'] === 'cbt_timed' ? 'Timed CBT' : 'Practice Drill') ?></td>
                        <td>
                            <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 py-1">
                                <?= $h['correct_answers'] ?> / <?= $h['total_questions'] ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge <?= (float)$h['score_percentage'] >= 70 ? 'bg-success' : ((float)$h['score_percentage'] >= 50 ? 'bg-warning text-dark' : 'bg-danger') ?> rounded-pill px-3 py-1 fw-bold">
                                <?= round((float)$h['score_percentage']) ?>%
                            </span>
                        </td>
                        <td class="text-end pe-4">
                            <a href="<?= url('student/secondary-practice.php?exam=' . urlencode($h['exam_type']) . '&subject=' . urlencode($h['subject_slug'])) ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                                Retake
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="text-center py-5 text-muted">
                            No practice history recorded yet.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
