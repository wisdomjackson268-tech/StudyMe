<?php

require_once dirname(__DIR__) . '/config/main.php';
require_once BASE_PATH . '/includes/functions/enrollments.php';
require_once BASE_PATH . '/includes/functions/activity.php';

secure_page(ROLE_STUDENT);

$user   = current_user();
$userId = (int)$user['id'];
$pdo    = getDBConnection();

log_user_activity($userId, 'viewed_daily_activity', 'Student viewed Secondary Daily Activity Log');

$streak    = calculate_user_learning_streak($userId);
$todayStats = get_user_today_stats($userId);
$heatmap   = get_user_activity_heatmap($userId, 28);

// Fetch all activity logs for this student
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 25;
$offset = ($page - 1) * $limit;

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM activity_logs WHERE user_id = ?");
$countStmt->execute([$userId]);
$totalLogs = (int)$countStmt->fetchColumn();
$totalPages = ceil($totalLogs / $limit);

$stmt = $pdo->prepare("
    SELECT * FROM activity_logs 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT $limit OFFSET $offset
");
$stmt->execute([$userId]);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

function get_sec_activity_icon($action) {
    $act = strtolower($action);
    if (strpos($act, 'login') !== false || strpos($act, 'auth') !== false) return ['icon' => 'bi-shield-lock-fill', 'color' => 'text-primary', 'bg' => 'bg-primary'];
    if (strpos($act, 'subject') !== false) return ['icon' => 'bi-journals', 'color' => 'text-info', 'bg' => 'bg-info'];
    if (strpos($act, 'material') !== false || strpos($act, 'note') !== false) return ['icon' => 'bi-file-earmark-text-fill', 'color' => 'text-warning', 'bg' => 'bg-warning'];
    if (strpos($act, 'practice') !== false || strpos($act, 'cbt') !== false || strpos($act, 'exam') !== false) return ['icon' => 'bi-cpu-fill', 'color' => 'text-success', 'bg' => 'bg-success'];
    if (strpos($act, 'ai') !== false || strpos($act, 'tutor') !== false) return ['icon' => 'bi-robot', 'color' => 'text-warning', 'bg' => 'bg-warning'];
    if (strpos($act, 'referral') !== false) return ['icon' => 'bi-gift-fill', 'color' => 'text-success', 'bg' => 'bg-success'];
    return ['icon' => 'bi-activity', 'color' => 'text-primary', 'bg' => 'bg-primary'];
}

$pageTitle = 'Secondary Daily Activity & Telemetry | StudyMe';
include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <a href="<?= url('student/secondary-dashboard.php') ?>" class="text-decoration-none text-muted small">
                <i class="bi bi-arrow-left me-1"></i> Dashboard
            </a>
            <span class="text-muted small">&bull;</span>
            <span class="text-muted small">Telemetry Engine</span>
        </div>
        <h2 class="fw-bold mb-0 text-dark">
            <i class="bi bi-activity text-primary me-2"></i> Daily Learning Activity
        </h2>
    </div>

    <div class="d-flex align-items-center gap-2 bg-warning bg-opacity-10 border border-warning border-opacity-25 rounded-pill px-4 py-2">
        <span class="fs-4">🔥</span>
        <div>
            <div class="fw-bold text-dark mb-0"><?= (int)$streak['streak_days'] ?> Day Study Streak</div>
            <small class="text-muted">Target: Daily 20-question practice</small>
        </div>
    </div>
</div>

<!-- ACTIVITY HEATMAP SUMMARY -->
<div class="card border-0 shadow-sm rounded-4 p-4 mb-4 bg-white border">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h5 class="fw-bold text-dark mb-0">
            <i class="bi bi-calendar3 text-primary me-2"></i> 28-Day Study Consistency Grid
        </h5>
        <span class="text-muted small"><?= (int)($todayStats['activities_count'] ?? 0) ?> activities recorded today</span>
    </div>

    <div class="d-flex flex-wrap gap-2">
        <?php foreach ($heatmap as $day): ?>
        <div class="text-center" style="min-width: 32px;" title="<?= $day['date'] ?>: <?= $day['count'] ?> actions">
            <div style="width: 32px; height: 32px; border-radius: 6px; background: <?= $day['count'] > 5 ? '#059669' : ($day['count'] > 0 ? '#34D399' : '#F1F5F9') ?>;" class="border"></div>
            <small class="text-muted" style="font-size: 0.65rem;"><?= date('j', strtotime($day['date'])) ?></small>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- ACTIVITY TIMELINE LOG -->
<div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-5 bg-white border">
    <div class="card-header bg-white p-3 p-md-4 border-bottom">
        <h5 class="fw-bold text-dark mb-0">
            <i class="bi bi-list-stars text-primary me-2"></i> Verified Learning Activity Timeline (<?= number_format($totalLogs) ?>)
        </h5>
    </div>

    <div class="card-body p-4">
        <?php if (!empty($logs)): ?>
        <div class="timeline position-relative ps-4" style="border-left: 2px solid #E2E8F0;">
            <?php foreach ($logs as $l): 
                $style = get_sec_activity_icon($l['action']);
            ?>
            <div class="position-relative mb-4">
                <div class="position-absolute" style="left: -33px; top: 0; width: 22px; height: 22px; border-radius: 50%; background: #ffffff; border: 2px solid <?= strpos($style['color'], 'primary') !== false ? '#2563EB' : (strpos($style['color'], 'success') !== false ? '#10B981' : '#F59E0B') ?>; display: flex; align-items: center; justify-content: center;">
                    <i class="bi <?= $style['icon'] ?>" style="font-size: 0.65rem;"></i>
                </div>
                <div class="bg-light p-3 rounded-3 border">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <span class="badge <?= $style['bg'] ?> text-white rounded-pill px-2 py-1 font-monospace" style="font-size: 0.7rem;">
                            <?= strtoupper(e($l['action'])) ?>
                        </span>
                        <small class="text-muted"><i class="bi bi-clock me-1"></i> <?= date('M d, Y h:i A', strtotime($l['created_at'])) ?></small>
                    </div>
                    <div class="fw-semibold text-dark"><?= e($l['description'] ?: ucfirst($l['action'])) ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- PAGINATION -->
        <?php if ($totalPages > 1): ?>
        <div class="d-flex justify-content-center mt-4">
            <nav>
                <ul class="pagination pagination-rounded">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $page - 1 ?>">Previous</a>
                    </li>
                    <?php for ($i = 1; $i <= min(10, $totalPages); $i++): ?>
                    <li class="page-item <?= $page === $i ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                    </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $page + 1 ?>">Next</a>
                    </li>
                </ul>
            </nav>
        </div>
        <?php endif; ?>

        <?php else: ?>
        <div class="text-center py-5 text-muted">
            No activity recorded today yet.
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
