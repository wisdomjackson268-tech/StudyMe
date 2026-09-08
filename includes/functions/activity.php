<?php
/**
 * StudyMe AI Platform — Rich Daily Activity Tracking & Telemetry Engine
 */
require_once dirname(__DIR__, 2) . '/config/database.php';

/**
 * Log a user activity event to MySQL.
 */
function log_user_activity($userId, $action, $description, $courseId = null, $lessonId = null, $quizId = null, $taskId = null) {
    if (!$userId) return false;
    $pdo = getDBConnection();
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        $stmt = $pdo->prepare("
            INSERT INTO activity_logs (user_id, course_id, lesson_id, quiz_id, task_id, action, description, ip_address, user_agent, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        return $stmt->execute([$userId, $courseId, $lessonId, $quizId, $taskId, $action, $description, $ip, $agent]);
    } catch (Exception $e) {
        error_log("Error in log_user_activity: " . $e->getMessage());
        return false;
    }
}

/**
 * Get daily activity history for a user on a given date (defaults to Today).
 */
function get_user_daily_activity($userId, $date = null) {
    $pdo = getDBConnection();
    if (!$date) $date = date('Y-m-d');
    try {
        $stmt = $pdo->prepare("
            SELECT a.*, c.title AS course_title
            FROM activity_logs a
            LEFT JOIN courses c ON a.course_id = c.id
            WHERE a.user_id = ? AND DATE(a.created_at) = ?
            ORDER BY a.created_at DESC
        ");
        $stmt->execute([$userId, $date]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error in get_user_daily_activity: " . $e->getMessage());
        return [];
    }
}

/**
 * Calculate dynamic learning streak (consecutive days with activity).
 */
function calculate_user_learning_streak($userId) {
    $pdo = getDBConnection();
    try {
        $stmt = $pdo->prepare("
            SELECT DISTINCT DATE(created_at) AS activity_date
            FROM activity_logs
            WHERE user_id = ?
            ORDER BY activity_date DESC
            LIMIT 60
        ");
        $stmt->execute([$userId]);
        $dates = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($dates)) return 0;

        $today     = new DateTime(date('Y-m-d'));
        $yesterday = (new DateTime(date('Y-m-d')))->modify('-1 day');

        $mostRecent = new DateTime($dates[0]);
        if ($mostRecent < $yesterday) {
            return 0; // Streak broken if no activity today or yesterday
        }

        $streak = 0;
        $checkDate = clone $mostRecent;

        foreach ($dates as $dStr) {
            $curr = new DateTime($dStr);
            $diff = $checkDate->diff($curr)->days;
            if ($diff == 0 || $diff == 1) {
                $streak++;
                $checkDate = $curr;
            } else {
                break;
            }
        }
        return max(1, $streak);
    } catch (Exception $e) {
        return 1;
    }
}

/**
 * Get today's activity telemetry breakdown.
 */
function get_user_today_stats($userId) {
    $pdo = getDBConnection();
    $today = date('Y-m-d');
    $stats = [
        'total' => 0,
        'lessons' => 0,
        'videos' => 0,
        'quizzes' => 0,
        'tasks' => 0,
        'ai_sessions' => 0
    ];

    try {
        $stmt = $pdo->prepare("
            SELECT action, COUNT(*) AS cnt
            FROM activity_logs
            WHERE user_id = ? AND DATE(created_at) = ?
            GROUP BY action
        ");
        $stmt->execute([$userId, $today]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $r) {
            $cnt = (int)$r['cnt'];
            $stats['total'] += $cnt;
            $act = strtolower($r['action']);

            if (strpos($act, 'lesson') !== false) $stats['lessons'] += $cnt;
            if (strpos($act, 'video') !== false) $stats['videos'] += $cnt;
            if (strpos($act, 'quiz') !== false) $stats['quizzes'] += $cnt;
            if (strpos($act, 'task') !== false) $stats['tasks'] += $cnt;
            if (strpos($act, 'ai') !== false) $stats['ai_sessions'] += $cnt;
        }

        return $stats;
    } catch (Exception $e) {
        return $stats;
    }
}

/**
 * Get activity heatmap data for calendar grid (past 28 days).
 */
function get_user_activity_heatmap($userId, $days = 28) {
    $pdo = getDBConnection();
    $result = [];
    $startDate = date('Y-m-d', strtotime("-$days days"));

    try {
        $stmt = $pdo->prepare("
            SELECT DATE(created_at) AS date_str, COUNT(*) AS count
            FROM activity_logs
            WHERE user_id = ? AND created_at >= ?
            GROUP BY DATE(created_at)
        ");
        $stmt->execute([$userId, $startDate]);
        $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        for ($i = $days; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-$i days"));
            $cnt = isset($rows[$d]) ? (int)$rows[$d] : 0;
            $result[] = [
                'date' => $d,
                'day_name' => date('D', strtotime($d)),
                'count' => $cnt,
                'intensity' => $cnt == 0 ? 0 : ($cnt < 3 ? 1 : ($cnt < 7 ? 2 : 3))
            ];
        }
        return $result;
    } catch (Exception $e) {
        return [];
    }
}
