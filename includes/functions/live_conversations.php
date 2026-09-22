<?php

require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__) . '/functions/notifications.php';

function ensure_live_conversations_schema(): void {
    static $checked = false;
    if ($checked) {
        return;
    }

    $pdo = getDBConnection();
    $pdo->exec("CREATE TABLE IF NOT EXISTS live_class_messages (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        live_class_id BIGINT UNSIGNED NOT NULL,
        user_id BIGINT UNSIGNED NOT NULL,
        role ENUM('teacher', 'student') NOT NULL,
        message_type ENUM('text', 'voice') NOT NULL DEFAULT 'text',
        message_text TEXT NULL,
        media_url VARCHAR(500) NULL,
        duration_seconds INT UNSIGNED NULL DEFAULT NULL,
        reply_to_message_id BIGINT UNSIGNED NULL,
        mention_user_id BIGINT UNSIGNED NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_lcm_class_created (live_class_id, created_at),
        INDEX idx_lcm_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $columns = $pdo->query("SHOW COLUMNS FROM live_class_messages")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('reply_to_message_id', $columns, true)) {
        $pdo->exec('ALTER TABLE live_class_messages ADD COLUMN reply_to_message_id BIGINT UNSIGNED NULL AFTER media_url');
    }
    if (!in_array('mention_user_id', $columns, true)) {
        $pdo->exec('ALTER TABLE live_class_messages ADD COLUMN mention_user_id BIGINT UNSIGNED NULL AFTER reply_to_message_id');
    }
    if (!in_array('duration_seconds', $columns, true)) {
        $pdo->exec('ALTER TABLE live_class_messages ADD COLUMN duration_seconds INT UNSIGNED NULL DEFAULT NULL AFTER media_url');
    }
    $messageColumns = $pdo->query("SHOW COLUMNS FROM live_class_messages LIKE 'message_type'")->fetch(PDO::FETCH_ASSOC);
    if ($messageColumns && (strpos((string)($messageColumns['Type'] ?? ''), "'image'") === false || strpos((string)($messageColumns['Type'] ?? ''), "'video'") === false)) {
        $pdo->exec("ALTER TABLE live_class_messages MODIFY message_type ENUM('text', 'voice', 'image', 'video') NOT NULL DEFAULT 'text'");
    }
    $pdo->exec("CREATE TABLE IF NOT EXISTS live_class_typing (
        live_class_id BIGINT UNSIGNED NOT NULL,
        user_id BIGINT UNSIGNED NOT NULL,
        role ENUM('teacher', 'student') NOT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (live_class_id, user_id),
        INDEX idx_lct_class_updated (live_class_id, updated_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $checked = true;
}

function set_live_typing(int $classId, int $userId, string $role, bool $isTyping): bool {
    ensure_live_conversations_schema();
    $pdo = getDBConnection();
    if ($isTyping) {
        $stmt = $pdo->prepare("INSERT INTO live_class_typing (live_class_id, user_id, role, updated_at)
            VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE role = VALUES(role), updated_at = NOW()");
        return $stmt->execute([$classId, $userId, $role]);
    }
    $stmt = $pdo->prepare('DELETE FROM live_class_typing WHERE live_class_id = ? AND user_id = ?');
    return $stmt->execute([$classId, $userId]);
}

function get_live_typing_users(int $classId, int $userId): array {
    ensure_live_conversations_schema();
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT t.user_id, t.role, CONCAT(u.first_name, ' ', u.last_name) AS name
        FROM live_class_typing t
        JOIN users u ON u.id = t.user_id
        WHERE t.live_class_id = ? AND t.user_id <> ? AND t.updated_at >= (NOW() - INTERVAL 8 SECOND)
        ORDER BY t.updated_at DESC");
    $stmt->execute([$classId, $userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function get_live_conversation_access(int $classId, int $userId, string $role): ?array {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT lc.id, lc.course_id, lc.teacher_id
        FROM live_classes lc
        WHERE lc.id = ? LIMIT 1");
    $stmt->execute([$classId]);
    $class = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$class) {
        return null;
    }

    if ($role === ROLE_TEACHER) {
        $stmt = $pdo->prepare('SELECT id FROM teachers WHERE id = ? AND user_id = ? LIMIT 1');
        $stmt->execute([(int)$class['teacher_id'], $userId]);
        return $stmt->fetchColumn() ? $class : null;
    }

    if ($role === ROLE_STUDENT) {
        $stmt = $pdo->prepare('SELECT 1 FROM students s JOIN enrollments e ON e.student_id = s.id WHERE s.user_id = ? AND e.course_id = ? AND e.status = \'active\' LIMIT 1');
        $stmt->execute([$userId, (int)$class['course_id']]);
        return $stmt->fetchColumn() ? $class : null;
    }

    return $role === ROLE_ADMIN ? $class : null;
}

function get_live_conversation_messages(int $classId, int $userId, string $role): array {
    ensure_live_conversations_schema();
    if (!get_live_conversation_access($classId, $userId, $role)) {
        return [];
    }

    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT m.id, m.user_id, m.role, m.message_type, m.message_text, m.media_url, m.duration_seconds,
        m.reply_to_message_id, m.mention_user_id, m.created_at,
        CONCAT(u.first_name, ' ', u.last_name) AS sender_name, u.avatar AS sender_avatar,
        CONCAT(mu.first_name, ' ', mu.last_name) AS mention_user_name
        FROM live_class_messages m
        JOIN users u ON u.id = m.user_id
        LEFT JOIN users mu ON mu.id = m.mention_user_id
        WHERE m.live_class_id = ?
        ORDER BY m.created_at ASC, m.id ASC");
    $stmt->execute([$classId]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($messages as &$message) {
        if ($message['role'] === 'teacher' && function_exists('get_teacher_avatar_url')) {
            $message['sender_avatar'] = get_teacher_avatar_url($message['sender_avatar'] ?? null, $message['sender_name'] ?? 'Instructor', (int)$message['user_id']);
        } elseif (function_exists('get_avatar_url')) {
            $message['sender_avatar'] = get_avatar_url($message['sender_avatar'] ?? null, $message['sender_name'] ?? 'User');
        }
    }
    unset($message);
    return $messages;
}

function get_live_class_enrolled_students(int $classId): array {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT lc.course_id FROM live_classes lc WHERE lc.id = ? LIMIT 1");
    $stmt->execute([$classId]);
    $courseId = (int)$stmt->fetchColumn();
    if (!$courseId) {
        return [];
    }

    $stmt = $pdo->prepare("
        SELECT u.id AS user_id, CONCAT(u.first_name, ' ', u.last_name) AS name, u.avatar, s.student_number
        FROM enrollments e
        JOIN students s ON e.student_id = s.id
        JOIN users u ON s.user_id = u.id
        WHERE e.course_id = ? AND e.status = 'active'
        ORDER BY u.first_name ASC, u.last_name ASC
    ");
    $stmt->execute([$courseId]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($students as &$st) {
        if (function_exists('get_avatar_url')) {
            $st['avatar'] = get_avatar_url($st['avatar'] ?? null, $st['name'] ?? 'Student');
        }
    }
    unset($st);
    return $students;
}

function create_live_conversation_message(int $classId, int $userId, string $role, string $type, ?string $text, ?string $mediaUrl, int $replyToMessageId = 0, int $mentionUserId = 0, int $durationSeconds = 0): bool {
    ensure_live_conversations_schema();
    if (!get_live_conversation_access($classId, $userId, $role)) {
        return false;
    }
    if (in_array($type, ['voice', 'image', 'video'], true) && $role !== ROLE_TEACHER) {
        return false;
    }
    if ($type === 'text' && trim((string)$text) === '') {
        return false;
    }
    if (in_array($type, ['voice', 'image', 'video'], true) && !$mediaUrl) {
        return false;
    }

    $pdo = getDBConnection();
    $class = get_live_conversation_access($classId, $userId, $role);
    $replyUserId = 0;
    if ($replyToMessageId > 0) {
        $replyStmt = $pdo->prepare('SELECT user_id FROM live_class_messages WHERE id = ? AND live_class_id = ? LIMIT 1');
        $replyStmt->execute([$replyToMessageId, $classId]);
        $replyUserId = (int)$replyStmt->fetchColumn();
        if (!$replyUserId) {
            $replyToMessageId = 0;
        }
    }

    if ($mentionUserId > 0) {
        if ($role !== ROLE_TEACHER) {
            $mentionUserId = 0;
        } else {
            $mentionStmt = $pdo->prepare('SELECT s.user_id FROM students s JOIN enrollments e ON e.student_id = s.id WHERE s.user_id = ? AND e.course_id = ? AND e.status = \'active\' LIMIT 1');
            $mentionStmt->execute([$mentionUserId, (int)$class['course_id']]);
            if (!$mentionStmt->fetchColumn()) {
                $mentionUserId = 0;
            }
        }
    }

    $stmt = $pdo->prepare('INSERT INTO live_class_messages (live_class_id, user_id, role, message_type, message_text, media_url, duration_seconds, reply_to_message_id, mention_user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $saved = $stmt->execute([$classId, $userId, $role, $type, $text ? trim($text) : null, $mediaUrl, $durationSeconds > 0 ? $durationSeconds : null, $replyToMessageId ?: null, $mentionUserId ?: null]);
    if (!$saved) {
        return false;
    }

    $link = 'student/live-classes.php?class_id=' . $classId;
    $targets = [];
    if ($replyUserId && $replyUserId !== $userId) {
        $targets[$replyUserId] = 'Someone replied to your live-class message.';
    }
    if ($mentionUserId && $mentionUserId !== $userId) {
        $targets[$mentionUserId] = 'Your teacher mentioned you in the live-class conversation.';
    }
    foreach ($targets as $targetUserId => $notificationText) {
        create_notification((int)$targetUserId, 'Live conversation update', $notificationText, 'live_class', $link);
    }
    return true;
}

function edit_live_conversation_message(int $classId, int $messageId, int $userId, string $text): bool {
    ensure_live_conversations_schema();
    if (!get_live_conversation_access($classId, $userId, current_user_role()) || trim($text) === '') {
        return false;
    }
    $stmt = getDBConnection()->prepare("UPDATE live_class_messages
        SET message_text = ?
        WHERE id = ? AND live_class_id = ? AND user_id = ? AND message_type = 'text'");
    return $stmt->execute([trim($text), $messageId, $classId, $userId]) && $stmt->rowCount() > 0;
}

function delete_live_conversation_message(int $classId, int $messageId, int $userId): bool {
    ensure_live_conversations_schema();
    $role = current_user_role();
    if (!get_live_conversation_access($classId, $userId, $role)) {
        return false;
    }
    if ($role === ROLE_TEACHER || $role === ROLE_ADMIN) {
        $stmt = getDBConnection()->prepare('DELETE FROM live_class_messages WHERE id = ? AND live_class_id = ?');
        return $stmt->execute([$messageId, $classId]) && $stmt->rowCount() > 0;
    }
    $stmt = getDBConnection()->prepare('DELETE FROM live_class_messages WHERE id = ? AND live_class_id = ? AND user_id = ?');
    return $stmt->execute([$messageId, $classId, $userId]) && $stmt->rowCount() > 0;
}

ensure_live_conversations_schema();