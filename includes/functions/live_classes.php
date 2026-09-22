<?php

require_once dirname(__DIR__, 2) . '/config/database.php';
require_once __DIR__ . '/activity.php';

function ensure_live_classes_schema() {
    static $checked = false;
    if ($checked) return;
    $pdo = getDBConnection();
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `live_classes` (
                `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `course_id` BIGINT UNSIGNED NOT NULL,
                `teacher_id` BIGINT UNSIGNED NOT NULL,
                `title` VARCHAR(255) NOT NULL,
                `description` TEXT NULL,
                `meeting_url` VARCHAR(500) NOT NULL,
                `scheduled_at` DATETIME NOT NULL,
                `duration_minutes` INT UNSIGNED DEFAULT 60,
                `status` ENUM('scheduled', 'live', 'ended', 'cancelled') DEFAULT 'scheduled',
                `recording_url` VARCHAR(500) NULL DEFAULT NULL,
                `attendance_opened_at` DATETIME NULL DEFAULT NULL,
                `attendance_closed_at` DATETIME NULL DEFAULT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_lc_course` (`course_id`),
                INDEX `idx_lc_teacher` (`teacher_id`),
                INDEX `idx_lc_status` (`status`),
                INDEX `idx_lc_scheduled` (`scheduled_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `live_class_attendance` (
                `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `live_class_id` BIGINT UNSIGNED NOT NULL,
                `student_id` BIGINT UNSIGNED NOT NULL,
                `user_id` BIGINT UNSIGNED NOT NULL,
                `course_id` BIGINT UNSIGNED NOT NULL,
                `attended_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `ip_address` VARCHAR(45) NULL DEFAULT NULL,
                `status` ENUM('present', 'late') DEFAULT 'present',
                UNIQUE KEY `unique_student_live_class` (`live_class_id`, `student_id`),
                INDEX `idx_lca_class` (`live_class_id`),
                INDEX `idx_lca_student` (`student_id`),
                INDEX `idx_lca_user` (`user_id`),
                INDEX `idx_lca_course` (`course_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        $liveClassColumns = $pdo->query("SHOW COLUMNS FROM `live_classes`")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('attendance_opened_at', $liveClassColumns, true)) {
            $pdo->exec("ALTER TABLE `live_classes` ADD COLUMN `attendance_opened_at` DATETIME NULL DEFAULT NULL AFTER `status`");
        }
        if (!in_array('attendance_closed_at', $liveClassColumns, true)) {
            $pdo->exec("ALTER TABLE `live_classes` ADD COLUMN `attendance_closed_at` DATETIME NULL DEFAULT NULL AFTER `attendance_opened_at`");
        }
        $checked = true;
    } catch (Exception $e) {
        error_log("Error in ensure_live_classes_schema: " . $e->getMessage());
    }
}

ensure_live_classes_schema();

function get_course_live_classes($courseId) {
    $pdo = getDBConnection();
    try {
        $stmt = $pdo->prepare("
            SELECT lc.*, c.title AS course_title,
                   CONCAT(u.first_name, ' ', u.last_name) AS teacher_name,
                   u.id AS teacher_user_id,
                   u.avatar AS teacher_avatar,
                   t.qualification AS teacher_qualification,
                   (SELECT COUNT(*) FROM live_class_attendance lca WHERE lca.live_class_id = lc.id) AS total_attendance
            FROM live_classes lc
            JOIN courses c ON lc.course_id = c.id
            JOIN teachers t ON lc.teacher_id = t.id
            JOIN users u ON t.user_id = u.id
            WHERE lc.course_id = ?
            ORDER BY lc.scheduled_at DESC
        ");
        $stmt->execute([(int)$courseId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error in get_course_live_classes: " . $e->getMessage());
        return [];
    }
}

function get_student_upcoming_live_classes($studentId) {
    $pdo = getDBConnection();
    try {
        $stmtCheck = $pdo->prepare("SELECT id FROM students WHERE id = ? LIMIT 1");
        $stmtCheck->execute([(int)$studentId]);
        $resolvedStudentId = (int)$stmtCheck->fetchColumn();
        if (!$resolvedStudentId) {
            $stmtUser = $pdo->prepare("SELECT id FROM students WHERE user_id = ? LIMIT 1");
            $stmtUser->execute([(int)$studentId]);
            $resolvedStudentId = (int)$stmtUser->fetchColumn();
        }
        if (!$resolvedStudentId) {
            return [];
        }

        $stmt = $pdo->prepare("
                 SELECT lc.*, c.title AS course_title, c.thumbnail AS course_thumbnail,
                     c.academic_level, c.academic_year,
                     CONCAT(u.first_name, ' ', u.last_name) AS teacher_name,
                     u.id AS teacher_user_id,
                   u.avatar AS teacher_avatar,
                   t.qualification AS teacher_qualification,
                   lca.id AS attendance_id,
                   lca.attended_at AS student_attended_at,
                   (CASE WHEN lca.id IS NOT NULL THEN 1 ELSE 0 END) AS is_attended,
                   (SELECT COUNT(*) FROM live_class_attendance WHERE live_class_id = lc.id) AS total_attendees
            FROM live_classes lc
            JOIN courses c ON lc.course_id = c.id
            JOIN enrollments e ON e.course_id = c.id
            JOIN teachers t ON lc.teacher_id = t.id
            JOIN users u ON t.user_id = u.id
            LEFT JOIN live_class_attendance lca ON lca.live_class_id = lc.id AND lca.student_id = ?
            WHERE e.student_id = ? AND e.status = 'active' AND lc.status IN ('scheduled', 'live')
            ORDER BY (lc.status = 'live') DESC, lc.scheduled_at ASC
        ");
        $stmt->execute([$resolvedStudentId, $resolvedStudentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error in get_student_upcoming_live_classes: " . $e->getMessage());
        return [];
    }
}

function get_student_past_live_classes($studentId) {
    $pdo = getDBConnection();
    try {
        $stmtCheck = $pdo->prepare("SELECT id FROM students WHERE id = ? LIMIT 1");
        $stmtCheck->execute([(int)$studentId]);
        $resolvedStudentId = (int)$stmtCheck->fetchColumn();
        if (!$resolvedStudentId) {
            $stmtUser = $pdo->prepare("SELECT id FROM students WHERE user_id = ? LIMIT 1");
            $stmtUser->execute([(int)$studentId]);
            $resolvedStudentId = (int)$stmtUser->fetchColumn();
        }
        if (!$resolvedStudentId) {
            return [];
        }

        $stmt = $pdo->prepare("
            SELECT lc.*, c.title AS course_title, c.thumbnail AS course_thumbnail,
                   c.academic_level, c.academic_year,
                   CONCAT(u.first_name, ' ', u.last_name) AS teacher_name,
                   u.avatar AS teacher_avatar,
                   t.qualification AS teacher_qualification,
                   lca.id AS attendance_id,
                   lca.attended_at AS student_attended_at,
                   (CASE WHEN lca.id IS NOT NULL THEN 1 ELSE 0 END) AS is_attended,
                   (SELECT COUNT(*) FROM live_class_attendance WHERE live_class_id = lc.id) AS total_attendees
            FROM live_classes lc
            JOIN courses c ON lc.course_id = c.id
            JOIN enrollments e ON e.course_id = c.id
            JOIN teachers t ON lc.teacher_id = t.id
            JOIN users u ON t.user_id = u.id
            LEFT JOIN live_class_attendance lca ON lca.live_class_id = lc.id AND lca.student_id = ?
            WHERE e.student_id = ? AND e.status = 'active' AND lc.status = 'ended'
            ORDER BY lc.scheduled_at DESC
        ");
        $stmt->execute([$resolvedStudentId, $resolvedStudentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error in get_student_past_live_classes: " . $e->getMessage());
        return [];
    }
}

function get_teacher_live_classes($teacherId) {
    $pdo = getDBConnection();
    try {
        $stmt = $pdo->prepare("
            SELECT lc.*, c.title AS course_title, c.academic_level,
                   (SELECT COUNT(DISTINCT e.student_id) FROM enrollments e WHERE e.course_id = lc.course_id AND e.status = 'active') AS enrolled_students,
                   (SELECT COUNT(DISTINCT lca.student_id) FROM live_class_attendance lca WHERE lca.live_class_id = lc.id) AS attended_students
            FROM live_classes lc
            JOIN courses c ON lc.course_id = c.id
            WHERE lc.teacher_id = ?
            ORDER BY (lc.status = 'live') DESC, lc.scheduled_at DESC
        ");
        $stmt->execute([(int)$teacherId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error in get_teacher_live_classes: " . $e->getMessage());
        return [];
    }
}

function schedule_live_class($courseId, $teacherId, $title, $description, $meetingUrl, $scheduledAt, $durationMinutes = 60) {
    $pdo = getDBConnection();
    try {
        $stmt = $pdo->prepare("
            INSERT INTO live_classes (course_id, teacher_id, title, description, meeting_url, scheduled_at, duration_minutes, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'scheduled', NOW())
        ");
        $stmt->execute([(int)$courseId, (int)$teacherId, $title, $description, $meetingUrl, $scheduledAt, (int)$durationMinutes]);
        return (int)$pdo->lastInsertId();
    } catch (Exception $e) {
        error_log("Error in schedule_live_class: " . $e->getMessage());
        return 0;
    }
}

function start_live_class_now($courseId, $teacherId, $subject, $topic, $durationMinutes = 60) {
    $pdo = getDBConnection();
    try {
        $title = trim($subject) . ': ' . trim($topic);
        $description = 'Live lesson on ' . trim($topic) . '.';
        $meetingUrl = rtrim(defined('APP_URL') ? APP_URL : 'http://localhost/StudyMe', '/') . '/student/live-classes.php';
        $stmt = $pdo->prepare("INSERT INTO live_classes
            (course_id, teacher_id, title, description, meeting_url, scheduled_at, duration_minutes, status, attendance_opened_at, created_at)
            VALUES (?, ?, ?, ?, ?, NOW(), ?, 'live', NOW(), NOW())");
        $stmt->execute([(int)$courseId, (int)$teacherId, $title, $description, $meetingUrl, (int)$durationMinutes]);
        $classId = (int)$pdo->lastInsertId();

        $notify = $pdo->prepare("SELECT DISTINCT s.user_id
            FROM students s
            JOIN enrollments e ON e.student_id = s.id
            WHERE e.course_id = ? AND e.status = 'active'");
        $notify->execute([(int)$courseId]);
        $notification = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, link, created_at)
            VALUES (?, ?, ?, 'live_class', ?, NOW())");
        $link = 'student/live-classes.php?class_id=' . $classId;
        foreach ($notify->fetchAll(PDO::FETCH_COLUMN) as $studentUserId) {
            $notification->execute([
                (int)$studentUserId,
                'Your teacher is live now',
                $title . ' has started. Join the live conversation now.',
                $link
            ]);
        }
        return $classId;
    } catch (Exception $e) {
        error_log("Error starting live class: " . $e->getMessage());
        return 0;
    }
}

function get_live_class_by_id($classId) {
    $pdo = getDBConnection();
    try {
        $stmt = $pdo->prepare("
            SELECT lc.*, c.title AS course_title, c.academic_level, c.academic_year,
                   CONCAT(u.first_name, ' ', u.last_name) AS teacher_name,
                   u.avatar AS teacher_avatar,
                   (SELECT COUNT(DISTINCT e.student_id) FROM enrollments e WHERE e.course_id = lc.course_id AND e.status = 'active') AS enrolled_students,
                   (SELECT COUNT(DISTINCT lca.student_id) FROM live_class_attendance lca WHERE lca.live_class_id = lc.id) AS attended_students
            FROM live_classes lc
            JOIN courses c ON lc.course_id = c.id
            JOIN teachers t ON lc.teacher_id = t.id
            JOIN users u ON t.user_id = u.id
            WHERE lc.id = ?
            LIMIT 1
        ");
        $stmt->execute([(int)$classId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Exception $e) {
        error_log("Error in get_live_class_by_id: " . $e->getMessage());
        return null;
    }
}

function update_live_class_status($classId, $teacherId, $status, $recordingUrl = null) {
    $pdo = getDBConnection();
    try {
        $sql = "UPDATE live_classes SET status = ?";
        $params = [$status];

        if ($status === 'live') {
            $sql .= ", attendance_opened_at = COALESCE(attendance_opened_at, NOW())";
        } elseif ($status === 'ended' || $status === 'cancelled') {
            $sql .= ", attendance_closed_at = COALESCE(attendance_closed_at, NOW())";
        }

        if ($recordingUrl !== null) {
            $sql .= ", recording_url = ?";
            $params[] = $recordingUrl;
        }
        $sql .= " WHERE id = ? AND teacher_id = ?";
        $params[] = (int)$classId;
        $params[] = (int)$teacherId;

        $stmt = $pdo->prepare($sql);
        return $stmt->execute($params);
    } catch (Exception $e) {
        error_log("Error in update_live_class_status: " . $e->getMessage());
        return false;
    }
}

function delete_live_class($classId, $teacherId) {
    $pdo = getDBConnection();
    try {
        $stmt = $pdo->prepare("DELETE FROM live_classes WHERE id = ? AND teacher_id = ?");
        return $stmt->execute([(int)$classId, (int)$teacherId]);
    } catch (Exception $e) {
        error_log("Error in delete_live_class: " . $e->getMessage());
        return false;
    }
}

function mark_live_class_attendance($classId, $studentId, $userId = null, $ip = null) {
    $pdo = getDBConnection();
    try {
        $stmtStudent = $pdo->prepare("SELECT id, user_id FROM students WHERE id = ? OR user_id = ? LIMIT 1");
        $stmtStudent->execute([(int)$studentId, (int)($userId ?: $studentId)]);
        $studentRow = $stmtStudent->fetch(PDO::FETCH_ASSOC);
        if (!$studentRow) {
            return ['success' => false, 'message' => 'Student record not found.'];
        }
        $resolvedStudentId = (int)$studentRow['id'];
        $resolvedUserId    = (int)$studentRow['user_id'];

        $stmtClass = $pdo->prepare("SELECT id, course_id, title, status FROM live_classes WHERE id = ? LIMIT 1");
        $stmtClass->execute([(int)$classId]);
        $liveClass = $stmtClass->fetch(PDO::FETCH_ASSOC);

        if (!$liveClass) {
            return ['success' => false, 'message' => 'Live class session not found.'];
        }

        if ($liveClass['status'] !== 'live') {
            return [
                'success' => false,
                'message' => 'Attendance is closed. This live lesson session is currently ' . htmlspecialchars($liveClass['status']) . '.'
            ];
        }

        $courseId = (int)$liveClass['course_id'];

        $stmtEnroll = $pdo->prepare("SELECT id FROM enrollments WHERE student_id = ? AND course_id = ? AND status = 'active' LIMIT 1");
        $stmtEnroll->execute([$resolvedStudentId, $courseId]);
        if (!$stmtEnroll->fetchColumn()) {
            return ['success' => false, 'message' => 'You must be actively enrolled in this course to mark attendance.'];
        }

        $stmtCheck = $pdo->prepare("SELECT id, attended_at FROM live_class_attendance WHERE live_class_id = ? AND student_id = ? LIMIT 1");
        $stmtCheck->execute([(int)$classId, $resolvedStudentId]);
        $existing = $stmtCheck->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
            return [
                'success' => true,
                'already_marked' => true,
                'message' => 'Attendance already recorded.',
                'attended_at' => $existing['attended_at']
            ];
        }

        $clientIp = $ip ?: ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
        $stmtInsert = $pdo->prepare("
            INSERT INTO live_class_attendance (live_class_id, student_id, user_id, course_id, attended_at, ip_address, status)
            VALUES (?, ?, ?, ?, NOW(), ?, 'present')
        ");
        $stmtInsert->execute([(int)$classId, $resolvedStudentId, $resolvedUserId, $courseId, $clientIp]);

        log_user_activity($resolvedUserId, 'attendance_marked', "Marked live attendance for: {$liveClass['title']}", $courseId);

        $attendedAt = date('Y-m-d H:i:s');
        return [
            'success' => true,
            'already_marked' => false,
            'message' => 'Attendance successfully marked! Thank you for participating in today\'s live lesson.',
            'attended_at' => $attendedAt
        ];
    } catch (Exception $e) {
        error_log("Error in mark_live_class_attendance: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to record attendance. Please try again.'];
    }
}

function has_student_attended_live_class($classId, $studentId) {
    $pdo = getDBConnection();
    try {
        $stmt = $pdo->prepare("
            SELECT lca.* FROM live_class_attendance lca
            JOIN students s ON lca.student_id = s.id
            WHERE lca.live_class_id = ? AND (s.id = ? OR s.user_id = ?)
            LIMIT 1
        ");
        $stmt->execute([(int)$classId, (int)$studentId, (int)$studentId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Exception $e) {
        error_log("Error in has_student_attended_live_class: " . $e->getMessage());
        return null;
    }
}

function get_live_class_attendance_list($classId) {
    $pdo = getDBConnection();
    try {
        $stmt = $pdo->prepare("
            SELECT lca.*,
                   CONCAT(u.first_name, ' ', u.last_name) AS student_name,
                   u.email AS student_email,
                   u.avatar AS student_avatar,
                   s.student_number
            FROM live_class_attendance lca
            JOIN students s ON lca.student_id = s.id
            JOIN users u ON s.user_id = u.id
            WHERE lca.live_class_id = ?
            ORDER BY lca.attended_at ASC
        ");
        $stmt->execute([(int)$classId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error in get_live_class_attendance_list: " . $e->getMessage());
        return [];
    }
}

function get_live_class_attendance_stats($classId) {
    $pdo = getDBConnection();
    try {
        $stmt = $pdo->prepare("
            SELECT lc.id, lc.title, lc.status, lc.scheduled_at,
                   (SELECT COUNT(DISTINCT e.student_id) FROM enrollments e WHERE e.course_id = lc.course_id AND e.status = 'active') AS total_enrolled,
                   (SELECT COUNT(DISTINCT lca.student_id) FROM live_class_attendance lca WHERE lca.live_class_id = lc.id) AS total_attended
            FROM live_classes lc
            WHERE lc.id = ?
            LIMIT 1
        ");
        $stmt->execute([(int)$classId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        $enrolled = (int)$row['total_enrolled'];
        $attended = (int)$row['total_attended'];
        $rate     = $enrolled > 0 ? round(($attended / $enrolled) * 100, 1) : 0;

        return [
            'class_id'        => (int)$row['id'],
            'title'           => $row['title'],
            'status'          => $row['status'],
            'scheduled_at'    => $row['scheduled_at'],
            'total_enrolled'  => $enrolled,
            'total_attended'  => $attended,
            'attendance_rate' => $rate
        ];
    } catch (Exception $e) {
        error_log("Error in get_live_class_attendance_stats: " . $e->getMessage());
        return null;
    }
}

function get_teacher_attendance_analytics($teacherId) {
    $pdo = getDBConnection();
    try {
        $stmtSessions = $pdo->prepare("
            SELECT COUNT(*) AS total_sessions,
                   COUNT(CASE WHEN status = 'live' THEN 1 END) AS live_sessions,
                   COUNT(CASE WHEN status = 'ended' THEN 1 END) AS ended_sessions
            FROM live_classes
            WHERE teacher_id = ?
        ");
        $stmtSessions->execute([(int)$teacherId]);
        $sessionStats = $stmtSessions->fetch(PDO::FETCH_ASSOC);

        $stmtAttended = $pdo->prepare("
            SELECT COUNT(DISTINCT lca.id) AS total_attendances,
                   COUNT(DISTINCT lca.student_id) AS unique_attending_students
            FROM live_class_attendance lca
            JOIN live_classes lc ON lca.live_class_id = lc.id
            WHERE lc.teacher_id = ?
        ");
        $stmtAttended->execute([(int)$teacherId]);
        $attendanceStats = $stmtAttended->fetch(PDO::FETCH_ASSOC);

        $stmtList = $pdo->prepare("
            SELECT lc.id, lc.title, lc.status, lc.scheduled_at, lc.duration_minutes,
                   c.title AS course_title,
                   (SELECT COUNT(DISTINCT e.student_id) FROM enrollments e WHERE e.course_id = lc.course_id AND e.status = 'active') AS total_enrolled,
                   (SELECT COUNT(DISTINCT lca.student_id) FROM live_class_attendance lca WHERE lca.live_class_id = lc.id) AS total_attended
            FROM live_classes lc
            JOIN courses c ON lc.course_id = c.id
            WHERE lc.teacher_id = ?
            ORDER BY lc.scheduled_at DESC
        ");
        $stmtList->execute([(int)$teacherId]);
        $sessionRows = $stmtList->fetchAll(PDO::FETCH_ASSOC);

        $totalEnrolledSum = 0;
        $totalAttendedSum = 0;
        foreach ($sessionRows as &$row) {
            $e = (int)$row['total_enrolled'];
            $a = (int)$row['total_attended'];
            $row['attendance_rate'] = $e > 0 ? round(($a / $e) * 100, 1) : 0;
            $totalEnrolledSum += $e;
            $totalAttendedSum += $a;
        }

        $overallRate = $totalEnrolledSum > 0 ? round(($totalAttendedSum / $totalEnrolledSum) * 100, 1) : 0;

        return [
            'total_sessions'            => (int)($sessionStats['total_sessions'] ?? 0),
            'live_sessions'             => (int)($sessionStats['live_sessions'] ?? 0),
            'ended_sessions'            => (int)($sessionStats['ended_sessions'] ?? 0),
            'total_attendances'         => (int)($attendanceStats['total_attendances'] ?? 0),
            'unique_attending_students' => (int)($attendanceStats['unique_attending_students'] ?? 0),
            'overall_attendance_rate'   => $overallRate,
            'sessions'                  => $sessionRows
        ];
    } catch (Exception $e) {
        error_log("Error in get_teacher_attendance_analytics: " . $e->getMessage());
        return [
            'total_sessions' => 0,
            'live_sessions' => 0,
            'ended_sessions' => 0,
            'total_attendances' => 0,
            'unique_attending_students' => 0,
            'overall_attendance_rate' => 0,
            'sessions' => []
        ];
    }
}
