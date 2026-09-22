<?php

require_once dirname(__DIR__) . '/config/main.php';
require_once BASE_PATH . '/includes/functions/enrollments.php';

secure_page(ROLE_STUDENT);

$user   = current_user();
$userId = $user['id'];
$pdo    = getDBConnection();

$stmt = $pdo->prepare("SELECT id FROM students WHERE user_id = ? LIMIT 1");
$stmt->execute([$userId]);
$studentRow = $stmt->fetch(PDO::FETCH_ASSOC);
$studentId  = $studentRow ? (int)$studentRow['id'] : 0;

$activeCourse = $studentId ? get_student_active_course($studentId) : null;

$streak    = calculate_user_learning_streak($userId);
$todayStats= get_user_today_stats($userId);
$todayLogs = get_user_daily_activity($userId, date('Y-m-d'));
$heatmap   = get_user_activity_heatmap($userId, 27);

$recentStream = $pdo->prepare("
    SELECT a.*, c.title AS course_title
    FROM activity_logs a
    LEFT JOIN courses c ON a.course_id = c.id
    WHERE a.user_id = ?
    ORDER BY a.created_at DESC
    LIMIT 25
");
$recentStream->execute([$userId]);
$historyLogs = $recentStream->fetchAll(PDO::FETCH_ASSOC);

function get_activity_icon($action) {
    $act = strtolower($action);
    if (strpos($act, 'login') !== false || strpos($act, 'auth') !== false) return '<i class="bi bi-shield-lock-fill text-primary"></i>';
    if (strpos($act, 'course') !== false) return '<i class="bi bi-journal-bookmark-fill text-info"></i>';
    if (strpos($act, 'lesson') !== false) return '<i class="bi bi-book-half text-success"></i>';
    if (strpos($act, 'video') !== false) return '<i class="bi bi-play-circle-fill text-danger"></i>';
    if (strpos($act, 'pdf') !== false || strpos($act, 'doc') !== false) return '<i class="bi bi-file-earmark-pdf-fill text-warning"></i>';
    if (strpos($act, 'quiz') !== false) return '<i class="bi bi-patch-question-fill text-warning"></i>';
    if (strpos($act, 'task') !== false) return '<i class="bi bi-check-circle-fill text-primary"></i>';
    if (strpos($act, 'ai') !== false) return '<i class="bi bi-robot text-purple" style="color:#8B5CF6;"></i>';
    if (strpos($act, 'profile') !== false) return '<i class="bi bi-person-circle text-secondary"></i>';
    return '<i class="bi bi-activity text-primary"></i>';
}

include BASE_PATH . '/includes/layouts/dashboard-header.php';
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
    <div>
        <p class="text-muted mb-1 fw-semibold text-uppercase small">
            <i class="bi bi-activity text-primary me-1"></i> Telemetry &amp; Streak Engine
        </p>
        <h2 class="fw-bold mb-0">My Daily Activity</h2>
    </div>
    <div class="d-flex align-items-center gap-2 bg-warning bg-opacity-10 border border-warning border-opacity-25 rounded-pill px-4 py-2">
        <span class="fs-4">🔥</span>
        <div>
            <div class="fw-bold text-warning-emphasis lh-1"><?= $streak ?> Day Learning Streak</div>
            <small class="text-muted" style="font-size:0.75rem;">Keep active daily to maintain your streak</small>
        </div>
    </div>
</div>

<?php if ($activeCourse): ?>
    <div class="card border-0 shadow-sm rounded-4 p-4 mb-4 bg-gradient-primary text-white position-relative overflow-hidden">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 position-relative z-2">
            <div>
                <span class="badge bg-white text-primary rounded-pill px-3 py-1 fw-bold small mb-2">My Enrolled Course</span>
                <h3 class="fw-bold mb-1"><?= e($activeCourse['course_title']) ?></h3>
                <p class="mb-0 text-white-50 small">Instructor: <?= e($activeCourse['teacher_name']) ?></p>
            </div>
            <a href="<?= url('student/course.php?id=' . $activeCourse['course_id']) ?>" class="btn btn-light rounded-pill px-4 fw-bold text-primary">
                Resume Learning <i class="bi bi-arrow-right ms-1"></i>
            </a>
        </div>
    </div>
<?php endif; ?>

<div class="row g-4 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon blue"><i class="bi bi-book-half"></i></div>
            <div>
                <div class="stat-value"><?= (int)$todayStats['lessons'] ?></div>
                <p class="stat-label">Lessons Viewed Today</p>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon red"><i class="bi bi-play-circle-fill"></i></div>
            <div>
                <div class="stat-value"><?= (int)$todayStats['videos'] ?></div>
                <p class="stat-label">Videos Watched Today</p>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon gold"><i class="bi bi-patch-question-fill"></i></div>
            <div>
                <div class="stat-value"><?= (int)$todayStats['quizzes'] ?></div>
                <p class="stat-label">Quiz Attempts Today</p>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon purple"><i class="bi bi-robot"></i></div>
            <div>
                <div class="stat-value"><?= (int)$todayStats['ai_sessions'] ?></div>
                <p class="stat-label">AI Tutor Queries</p>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">

    <div class="col-lg-7">

        <div class="card border-0 shadow-sm rounded-4 p-4 mb-4">
            <h5 class="fw-bold mb-3"><i class="bi bi-calendar3-week text-primary me-2"></i>Activity Calendar (Past 4 Weeks)</h5>
            <div class="d-flex flex-wrap gap-2 justify-content-between p-3 bg-light rounded-3 border">
                <?php foreach ($heatmap as $day): ?>
                    <div class="text-center" style="width: 32px;" title="<?= $day['date'] ?>: <?= $day['count'] ?> activities">
                        <small class="text-muted d-block" style="font-size:0.65rem;"><?= substr($day['day_name'], 0, 1) ?></small>
                        <div class="rounded-2 my-1" style="height: 28px; width: 100%;
                            background: <?= $day['intensity'] == 0 ? 'rgba(226, 232, 240, 0.8)' : ($day['intensity'] == 1 ? 'rgba(37, 99, 235, 0.35)' : ($day['intensity'] == 2 ? 'rgba(37, 99, 235, 0.7)' : '#2563EB')) ?>;">
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-4 p-4">
            <h5 class="fw-bold mb-3"><i class="bi bi-clock-history text-success me-2"></i>Today's Logged Actions</h5>
            <?php if (!empty($todayLogs)): ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($todayLogs as $log): ?>
                        <div class="list-group-item py-3 px-0 border-0 border-bottom d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center gap-3">
                                <div class="fs-4 flex-shrink-0"><?= get_activity_icon($log['action']) ?></div>
                                <div>
                                    <div class="fw-bold text-main small"><?= e($log['action']) ?></div>
                                    <div class="text-muted small"><?= e($log['description']) ?></div>
                                </div>
                            </div>
                            <span class="small text-muted font-monospace"><?= date('g:i A', strtotime($log['created_at'])) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-4 text-muted">
                    <i class="bi bi-journal-text fs-2 d-block mb-2"></i>
                    <p class="small mb-0">No actions recorded yet today. Open a lesson or ask AI to log your learning activity!</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card border-0 shadow-sm rounded-4 p-4">
            <h5 class="fw-bold mb-3"><i class="bi bi-journal-check text-info me-2"></i>Recent Activity History</h5>
            <?php if (!empty($historyLogs)): ?>
                <div class="d-flex flex-column gap-3">
                    <?php foreach (array_slice($historyLogs, 0, 15) as $hLog): ?>
                        <div class="p-3 bg-light rounded-3 border border-subtle d-flex align-items-start gap-3">
                            <div class="fs-4 flex-shrink-0"><?= get_activity_icon($hLog['action']) ?></div>
                            <div class="flex-grow-1">
                                <div class="d-flex align-items-center justify-content-between">
                                    <span class="fw-bold text-main small"><?= e($hLog['action']) ?></span>
                                    <small class="text-muted font-monospace" style="font-size:0.7rem;"><?= date('M d, g:i A', strtotime($hLog['created_at'])) ?></small>
                                </div>
                                <p class="text-muted small mb-0 lh-sm mt-1"><?= e($hLog['description']) ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="p-4 text-center text-muted small">No past activity recorded yet.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include BASE_PATH . '/includes/layouts/dashboard-footer.php'; ?>
