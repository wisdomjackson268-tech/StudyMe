<?php

if (!function_exists('get_target_recipient_user_ids')) {
    function get_target_recipient_user_ids($targetType, $courseId = null, $targetUserId = null, $creatorUserId = null) {
        $pdo = getDBConnection();
        $recipientIds = [];

        switch ($targetType) {
            case 'all':
                $stmt = $pdo->query("SELECT id FROM users WHERE status = 'active'");
                $recipientIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
                break;

            case 'students':
                $stmt = $pdo->query("SELECT id FROM users WHERE role = 'student' AND status = 'active'");
                $recipientIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
                break;

            case 'teachers':
                $stmt = $pdo->query("SELECT id FROM users WHERE role = 'teacher' AND status = 'active'");
                $recipientIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
                break;

            case 'technology':
                $stmt = $pdo->query("
                    SELECT DISTINCT s.user_id 
                    FROM students s
                    JOIN enrollments e ON s.id = e.student_id
                    JOIN courses c ON e.course_id = c.id
                    JOIN categories cat ON c.category_id = cat.id
                    WHERE e.status = 'active' AND (cat.slug = 'technology' OR cat.slug = 'tech')
                ");
                $recipientIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
                break;

            case 'university':
                $stmt = $pdo->query("
                    SELECT DISTINCT s.user_id 
                    FROM students s
                    JOIN enrollments e ON s.id = e.student_id
                    JOIN courses c ON e.course_id = c.id
                    JOIN categories cat ON c.category_id = cat.id
                    WHERE e.status = 'active' AND (cat.slug = 'university' OR cat.slug = 'uni')
                ");
                $recipientIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
                break;

            case 'secondary':
                $stmt = $pdo->query("
                    SELECT DISTINCT s.user_id 
                    FROM students s
                    JOIN enrollments e ON s.id = e.student_id
                    JOIN courses c ON e.course_id = c.id
                    JOIN categories cat ON c.category_id = cat.id
                    WHERE e.status = 'active' AND cat.slug LIKE '%secondary%'
                ");
                $recipientIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
                break;

            case 'course':
                if ($courseId > 0) {
                    $stmt = $pdo->prepare("
                        SELECT DISTINCT s.user_id 
                        FROM students s
                        JOIN enrollments e ON s.id = e.student_id
                        WHERE e.course_id = ? AND e.status = 'active'
                    ");
                    $stmt->execute([$courseId]);
                    $recipientIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
                }
                break;

            case 'user':
                if ($targetUserId > 0) {
                    $recipientIds = [$targetUserId];
                }
                break;
        }

        return array_values(array_unique(array_map('intval', $recipientIds)));
    }
}

if (!function_exists('publish_announcement_notifications')) {
    function publish_announcement_notifications($announcementId) {
        $pdo = getDBConnection();
        
        $stmt = $pdo->prepare("SELECT * FROM announcements WHERE id = ? LIMIT 1");
        $stmt->execute([$announcementId]);
        $ann = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$ann || $ann['status'] !== 'published') {
            return 0;
        }

        $recipients = get_target_recipient_user_ids(
            $ann['target_type'],
            $ann['course_id'],
            $ann['target_user_id'],
            $ann['created_by']
        );

        if (empty($recipients)) {
            return 0;
        }

        $stmtNotif = $pdo->prepare("
            INSERT INTO notifications (user_id, title, message, type, link, created_at)
            VALUES (?, ?, ?, 'announcement', ?, NOW())
        ");

        $title = $ann['title'];
        $snippet = mb_strimwidth(strip_tags($ann['content']), 0, 140, '...');
        $link = 'announcements/view.php?id=' . $announcementId;

        $count = 0;
        foreach ($recipients as $uid) {
            $stmtNotif->execute([$uid, $title, $snippet, $link]);
            $count++;
        }

        return $count;
    }
}

if (!function_exists('create_announcement_entry')) {
    function create_announcement_entry($title, $content, $creatorId, $creatorRole, $targetType = 'all', $courseId = null, $priority = 'normal', $status = 'published', $attachmentFile = null, $targetUserId = null) {
        $pdo = getDBConnection();

        if ($creatorRole === 'student') {
            return ['success' => false, 'error' => 'ACCESS DENIED: Students do not have permission to create announcements.'];
        }

        if ($creatorRole === 'teacher') {
            $stmtTch = $pdo->prepare("
                SELECT t.id, t.assigned_course_id 
                FROM teachers t 
                WHERE t.user_id = ? LIMIT 1
            ");
            $stmtTch->execute([$creatorId]);
            $teacher = $stmtTch->fetch(PDO::FETCH_ASSOC);
            $teacherId = $teacher ? (int)$teacher['id'] : 0;

            $stmtCourses = $pdo->prepare("SELECT id FROM courses WHERE teacher_id = ? OR id = ?");
            $stmtCourses->execute([$teacherId, (int)($teacher['assigned_course_id'] ?? 0)]);
            $validCourseIds = $stmtCourses->fetchAll(PDO::FETCH_COLUMN);

            if (empty($validCourseIds) || !in_array((int)$courseId, array_map('intval', $validCourseIds))) {
                return ['success' => false, 'error' => 'REQUEST REJECTED BY SERVER: You can only publish announcements for courses you are authorized to teach.'];
            }

            $targetType = 'course';
        }

        $attachmentPath = null;
        if (!empty($attachmentFile) && isset($attachmentFile['tmp_name']) && is_uploaded_file($attachmentFile['tmp_name'])) {
            $allowedExts = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx', 'txt', 'zip'];
            $fileExt = strtolower(pathinfo($attachmentFile['name'], PATHINFO_EXTENSION));

            if (!in_array($fileExt, $allowedExts)) {
                return ['success' => false, 'error' => 'Invalid attachment format. Allowed formats: PDF, Images (JPG, PNG, WEBP), Documents (DOC, DOCX, TXT, ZIP).'];
            }

            if ($attachmentFile['size'] > 15 * 1024 * 1024) {
                return ['success' => false, 'error' => 'Attachment exceeds maximum allowed size of 15MB.'];
            }

            $uploadDir = BASE_PATH . '/uploads/announcements';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $fileName = 'ann_' . time() . '_' . rand(1000, 9999) . '.' . $fileExt;
            $destPath = $uploadDir . '/' . $fileName;

            if (move_uploaded_file($attachmentFile['tmp_name'], $destPath)) {
                $attachmentPath = 'uploads/announcements/' . $fileName;
            }
        }

        try {
            $publishedAt = ($status === 'published') ? date('Y-m-d H:i:s') : null;
            $stmt = $pdo->prepare("
                INSERT INTO announcements (title, content, created_by, creator_role, course_id, target_type, target_user_id, attachment, priority, status, published_at, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $title,
                $content,
                $creatorId,
                $creatorRole,
                $courseId ?: null,
                $targetType,
                $targetUserId ?: null,
                $attachmentPath,
                $priority,
                $status,
                $publishedAt
            ]);

            $announcementId = (int)$pdo->lastInsertId();

            if ($status === 'published') {
                publish_announcement_notifications($announcementId);
            }

            return ['success' => true, 'id' => $announcementId, 'attachment' => $attachmentPath];
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
        }
    }
}

if (!function_exists('get_user_announcements')) {
    function get_user_announcements($userId, $userRole, $limit = 50, $offset = 0) {
        $pdo = getDBConnection();
        $whereConditions = [];
        $params = [];

        if ($userRole === 'admin') {
            $whereConditions[] = "1=1";
        } elseif ($userRole === 'teacher') {
            $whereConditions[] = "(a.created_by = ? OR a.target_type IN ('all', 'teachers'))";
            $params[] = $userId;
        } else {
            $whereConditions[] = "a.status = 'published' AND (
                a.target_type = 'all'
                OR a.target_type = 'students'
                OR (a.target_type = 'course' AND a.course_id IN (
                    SELECT e.course_id FROM enrollments e JOIN students s ON e.student_id = s.id WHERE s.user_id = ? AND e.status = 'active'
                ))
                OR (a.target_type = 'technology' AND EXISTS (
                    SELECT 1 FROM enrollments e JOIN students s ON e.student_id = s.id JOIN courses c ON e.course_id = c.id JOIN categories cat ON c.category_id = cat.id WHERE s.user_id = ? AND e.status = 'active' AND (cat.slug = 'technology' OR cat.slug = 'tech')
                ))
                OR (a.target_type = 'university' AND EXISTS (
                    SELECT 1 FROM enrollments e JOIN students s ON e.student_id = s.id JOIN courses c ON e.course_id = c.id JOIN categories cat ON c.category_id = cat.id WHERE s.user_id = ? AND e.status = 'active' AND (cat.slug = 'university' OR cat.slug = 'uni')
                ))
                OR (a.target_type = 'secondary' AND EXISTS (
                    SELECT 1 FROM enrollments e JOIN students s ON e.student_id = s.id JOIN courses c ON e.course_id = c.id JOIN categories cat ON c.category_id = cat.id WHERE s.user_id = ? AND e.status = 'active' AND cat.slug LIKE '%secondary%'
                ))
                OR (a.target_type = 'user' AND a.target_user_id = ?)
            )";
            $params[] = $userId;
            $params[] = $userId;
            $params[] = $userId;
            $params[] = $userId;
            $params[] = $userId;
        }

        $whereSql = implode(' AND ', $whereConditions);

        $sql = "
            SELECT a.*,
                   CONCAT(u.first_name, ' ', u.last_name) AS author_name,
                   u.role AS author_role,
                   c.title AS course_title,
                   (CASE WHEN ar.id IS NOT NULL THEN 1 ELSE 0 END) AS is_read,
                   ar.read_at
            FROM announcements a
            LEFT JOIN users u ON a.created_by = u.id
            LEFT JOIN courses c ON a.course_id = c.id
            LEFT JOIN announcement_reads ar ON a.id = ar.announcement_id AND ar.user_id = ?
            WHERE $whereSql
            ORDER BY (a.status = 'published') DESC, a.created_at DESC
            LIMIT ? OFFSET ?
        ";

        array_unshift($params, $userId);
        $params[] = (int)$limit;
        $params[] = (int)$offset;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('count_unread_announcements')) {
    function count_unread_announcements($userId, $userRole) {
        $pdo = getDBConnection();
        $userId = (int)$userId;
        if ($userId <= 0) return 0;

        $where = '';
        $params = [];
        if ($userRole === 'admin') {
            $where = "a.status = 'published' AND NOT EXISTS (SELECT 1 FROM announcement_reads ar WHERE ar.announcement_id = a.id AND ar.user_id = ?)";
            $params = [$userId];
        } elseif ($userRole === 'teacher') {
            $where = "a.status = 'published' AND (a.created_by = ? OR a.target_type IN ('all', 'teachers')) AND NOT EXISTS (SELECT 1 FROM announcement_reads ar WHERE ar.announcement_id = a.id AND ar.user_id = ?)";
            $params = [$userId, $userId];
        } else {
            $where = "a.status = 'published' AND (a.target_type = 'all' OR a.target_type = 'students' OR (a.target_type = 'course' AND a.course_id IN (SELECT e.course_id FROM enrollments e JOIN students s ON e.student_id = s.id WHERE s.user_id = ? AND e.status = 'active')) OR (a.target_type = 'user' AND a.target_user_id = ?)) AND NOT EXISTS (SELECT 1 FROM announcement_reads ar WHERE ar.announcement_id = a.id AND ar.user_id = ?)";
            $params = [$userId, $userId, $userId];
        }
        $sql = "SELECT COUNT(*) as cnt FROM announcements a WHERE $where";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)($row['cnt'] ?? 0);
    }
}

if (!function_exists('mark_announcement_as_read')) {
    function mark_announcement_as_read($announcementId, $userId) {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            INSERT IGNORE INTO announcement_reads (announcement_id, user_id, read_at)
            VALUES (?, ?, NOW())
        ");
        return $stmt->execute([$announcementId, $userId]);
    }
}

if (!function_exists('mark_all_announcements_as_read')) {
    function mark_all_announcements_as_read($userId, $userRole) {
        $pdo = getDBConnection();
        $announcements = get_user_announcements($userId, $userRole, 100);
        
        $stmt = $pdo->prepare("
            INSERT IGNORE INTO announcement_reads (announcement_id, user_id, read_at)
            VALUES (?, ?, NOW())
        ");

        foreach ($announcements as $ann) {
            $stmt->execute([$ann['id'], $userId]);
        }

        return true;
    }
}

if (!function_exists('get_announcement_telemetry')) {
    function get_announcement_telemetry($announcementId) {
        $pdo = getDBConnection();

        $stmt = $pdo->prepare("SELECT * FROM announcements WHERE id = ? LIMIT 1");
        $stmt->execute([$announcementId]);
        $ann = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$ann) {
            return ['recipients_count' => 0, 'read_count' => 0, 'unread_count' => 0];
        }

        $recipients = get_target_recipient_user_ids(
            $ann['target_type'],
            $ann['course_id'],
            $ann['target_user_id'],
            $ann['created_by']
        );
        $totalRecipients = count($recipients);

        $stmtReads = $pdo->prepare("SELECT COUNT(*) FROM announcement_reads WHERE announcement_id = ?");
        $stmtReads->execute([$announcementId]);
        $readCount = (int)$stmtReads->fetchColumn();

        return [
            'recipients_count' => $totalRecipients,
            'read_count'       => $readCount,
            'unread_count'     => max(0, $totalRecipients - $readCount)
        ];
    }
}