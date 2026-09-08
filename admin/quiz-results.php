<?php
/**
 * StudyMe AI Platform — Admin Quiz Results & Performance Analytics
 */
require_once dirname(__DIR__) . '/config/main.php';

secure_page(ROLE_ADMIN);

$pdo     = getDBConnection();
$search  = trim($_GET['search'] ?? '');
$quizId  = (int)($_GET['quiz_id'] ?? 0);

$whereClauses = [];
$params       = [];

if ($search !== '') {
    $whereClauses[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR q.title LIKE ?)";
    $s = "%$search%";
    $params = array_merge($params, [$s, $s, $s, $s]);
}
if ($quizId > 0) {
    $whereClauses[] = "qa.quiz_id = ?";
    $params[] = $quizId;
}

$whereSQL = $whereClauses ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

$stmt = $pdo->prepare("
    SELECT qa.*, q.title AS quiz_title, q.passing_score,
           c.title AS course_title,
           u.id AS user_id, u.first_name, u.last_name, u.email
    FROM quiz_attempts qa
    JOIN quizzes q ON qa.quiz_id = q.id
    JOIN courses c ON q.course_id = c.id
    JOIN students s ON qa.student_id = s.id
    JOIN users u ON s.user_id = u.id
    $whereSQL
    ORDER BY qa.submitted_at DESC, qa.id DESC
");
$stmt->execute($params);
$attempts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Aggregate Calculations
$totalAttempts = count($attempts);
$totalScores   = array_column($attempts, 'percentage');
$avgScore      = $totalAttempts > 0 ? array_sum($totalScores) / $totalAttempts : 0.0;
$highestScore  = $totalAttempts > 0 ? max($totalScores) : 0.0;
$lowestScore   = $totalAttempts > 0 ? min($totalScores) : 0.0;
$passedCount   = count(array_filter($attempts, fn($a) => (bool)$a['passed']));
$failedCount   = $totalAttempts - $passedCount;
$passRate      = $totalAttempts > 0 ? ($passedCount / $totalAttempts) * 100 : 0.0;
$failRate      = $totalAttempts > 0 ? ($failedCount / $totalAttempts) * 100 : 0.0;

// Quizzes for filter
$quizzes = $pdo->query("SELECT id, title FROM quizzes ORDER BY title ASC")->fetchAll(PDO::FETCH_ASSOC);

include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
    <div>
        <p class="text-muted mb-1 fw-semibold text-uppercase small"><i class="bi bi-patch-check-fill text-success me-1"></i> Admin Command Center</p>
        <h2 class="fw-bold mb-0">Quiz Submissions & Results</h2>
    </div>
    <a href="<?= url('admin/quizzes.php') ?>" class="btn btn-outline-secondary rounded-pill px-4">
        <i class="bi bi-arrow-left me-1"></i> Manage Quizzes
    </a>
</div>

<!-- Real Database Aggregate Statistics (Part 28) -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-2">
        <div class="stat-card">
            <div class="stat-icon blue"><i class="bi bi-pen-fill"></i></div>
            <div><div class="stat-value"><?= $totalAttempts ?></div><p class="stat-label">Total Attempts</p></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-2">
        <div class="stat-card">
            <div class="stat-icon green"><i class="bi bi-graph-up"></i></div>
            <div><div class="stat-value"><?= number_format($avgScore, 1) ?>%</div><p class="stat-label">Average Score</p></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-2">
        <div class="stat-card">
            <div class="stat-icon gold"><i class="bi bi-trophy-fill"></i></div>
            <div><div class="stat-value"><?= number_format($highestScore, 1) ?>%</div><p class="stat-label">Highest Score</p></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-2">
        <div class="stat-card">
            <div class="stat-icon purple"><i class="bi bi-bar-chart-fill"></i></div>
            <div><div class="stat-value"><?= number_format($lowestScore, 1) ?>%</div><p class="stat-label">Lowest Score</p></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-2">
        <div class="stat-card">
            <div class="stat-icon green"><i class="bi bi-check2-all"></i></div>
            <div><div class="stat-value"><?= number_format($passRate, 1) ?>%</div><p class="stat-label">Pass Rate</p></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-2">
        <div class="stat-card">
            <div class="stat-icon red" style="background:rgba(239,68,68,0.1);"><i class="bi bi-x-circle-fill" style="color:#ef4444;"></i></div>
            <div><div class="stat-value"><?= number_format($failRate, 1) ?>%</div><p class="stat-label">Fail Rate</p></div>
        </div>
    </div>
</div>

<!-- Search & Filter -->
<div class="card border-0 shadow-sm rounded-4 p-3 mb-4">
    <form method="GET" class="row g-2 align-items-end">
        <div class="col-md-6">
            <input type="text" name="search" class="form-control rounded-3" placeholder="Search student name, email, or quiz..." value="<?= e($search) ?>">
        </div>
        <div class="col-md-4">
            <select name="quiz_id" class="form-select rounded-3">
                <option value="">All Quizzes</option>
                <?php foreach ($quizzes as $qz): ?>
                <option value="<?= $qz['id'] ?>" <?= $quizId === (int)$qz['id'] ? 'selected' : '' ?>><?= e($qz['title']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-primary rounded-pill w-100"><i class="bi bi-search me-1"></i> Filter</button>
        </div>
    </form>
</div>

<!-- Results Table -->
<div class="card border-0 shadow-sm rounded-4 overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
            <thead class="bg-light" style="font-size:0.78rem; text-transform:uppercase; letter-spacing:0.04em;">
                <tr>
                    <th class="ps-4">Student</th>
                    <th>Quiz Title</th>
                    <th>Course</th>
                    <th>Score</th>
                    <th>Percentage</th>
                    <th>Result</th>
                    <th>Date Attempted</th>
                    <th class="text-end pe-4">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($attempts as $att): ?>
                <tr>
                    <td class="ps-4">
                        <div class="d-flex align-items-center gap-2">
                            <div class="rounded-circle bg-primary bg-opacity-10 text-primary fw-bold d-flex align-items-center justify-content-center" style="width:34px;height:34px;">
                                <?= strtoupper(substr($att['first_name'], 0, 1)) ?>
                            </div>
                            <div>
                                <div class="fw-bold text-main"><?= e($att['first_name'] . ' ' . $att['last_name']) ?></div>
                                <div class="text-muted" style="font-size:0.72rem;"><?= e($att['email']) ?></div>
                            </div>
                        </div>
                    </td>
                    <td class="fw-semibold text-main"><?= e($att['quiz_title']) ?></td>
                    <td><span class="badge bg-light text-muted border"><?= e(substr($att['course_title'], 0, 24)) ?>...</span></td>
                    <td class="fw-bold"><?= number_format($att['score'], 1) ?></td>
                    <td class="fw-bold <?= $att['passed'] ? 'text-success' : 'text-danger' ?>">
                        <?= number_format($att['percentage'], 1) ?>%
                    </td>
                    <td>
                        <span class="badge rounded-pill <?= $att['passed'] ? 'bg-success' : 'bg-danger' ?> px-2 py-1">
                            <?= $att['passed'] ? 'Passed' : 'Failed' ?>
                        </span>
                    </td>
                    <td class="text-muted"><?= date('d M Y, H:i', strtotime($att['submitted_at'] ?: $att['started_at'])) ?></td>
                    <td class="text-end pe-4">
                        <a href="<?= url('quizzes/results.php?attempt_id=' . $att['id']) ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3" target="_blank">
                            <i class="bi bi-eye me-1"></i> Review
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($attempts)): ?>
                <tr>
                    <td colspan="8" class="text-center py-5 text-muted">No quiz attempt submissions found.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
