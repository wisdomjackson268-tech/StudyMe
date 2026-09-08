<?php
/**
 * StudyMe AI Platform — User/Profile Helper Functions
 */

/**
 * Get a user by ID.
 */
function get_user_by_id($userId) {
    $pdo = getDBConnection();
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Exception $e) {
        error_log("get_user_by_id error: " . $e->getMessage());
        return null;
    }
}

/**
 * Get user by email.
 */
function get_user_by_email($email) {
    $pdo = getDBConnection();
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Exception $e) {
        error_log("get_user_by_email error: " . $e->getMessage());
        return null;
    }
}

/**
 * Update user profile fields.
 */
function update_user_profile($userId, array $data) {
    $pdo = getDBConnection();
    try {
        $allowed = ['first_name','last_name','phone','avatar'];
        $sets = [];
        $values = [];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $sets[] = "$field = ?";
                $values[] = $data[$field];
            }
        }
        if (empty($sets)) return false;
        $values[] = $userId;
        $stmt = $pdo->prepare("UPDATE users SET " . implode(', ', $sets) . " WHERE id = ?");
        return $stmt->execute($values);
    } catch (Exception $e) {
        error_log("update_user_profile error: " . $e->getMessage());
        return false;
    }
}

/**
 * Update user password.
 */
function update_user_password($userId, $newPassword) {
    $pdo = getDBConnection();
    try {
        $hash = password_hash($newPassword, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        return $stmt->execute([$hash, $userId]);
    } catch (Exception $e) {
        error_log("update_user_password error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get all users (admin).
 */
function get_all_users($role = null, $status = null, $search = '', $limit = 50, $offset = 0) {
    $pdo = getDBConnection();
    try {
        $where = ["1=1"];
        $params = [];
        if ($role) { $where[] = "role = ?"; $params[] = $role; }
        if ($status) { $where[] = "status = ?"; $params[] = $status; }
        if ($search) { $where[] = "(first_name LIKE ? OR last_name LIKE ? OR email LIKE ?)"; $s = "%$search%"; $params[] = $s; $params[] = $s; $params[] = $s; }
        $sql = "SELECT * FROM users WHERE " . implode(' AND ', $where) . " ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params[] = (int)$limit;
        $params[] = (int)$offset;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("get_all_users error: " . $e->getMessage());
        return [];
    }
}

/**
 * Count users.
 */
function count_users($role = null, $status = null, $search = '') {
    $pdo = getDBConnection();
    try {
        $where = ["1=1"];
        $params = [];
        if ($role) { $where[] = "role = ?"; $params[] = $role; }
        if ($status) { $where[] = "status = ?"; $params[] = $status; }
        if ($search) { $where[] = "(first_name LIKE ? OR last_name LIKE ? OR email LIKE ?)"; $s = "%$search%"; $params[] = $s; $params[] = $s; $params[] = $s; }
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE " . implode(' AND ', $where));
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * Update user status (admin).
 */
function update_user_status($userId, $status) {
    $pdo = getDBConnection();
    try {
        $allowed = ['active','inactive','suspended','pending'];
        if (!in_array($status, $allowed)) return false;
        $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
        return $stmt->execute([$status, $userId]);
    } catch (Exception $e) {
        error_log("update_user_status error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get student profile by user_id.
 */
function get_student_by_user_id($userId) {
    $pdo = getDBConnection();
    try {
        $stmt = $pdo->prepare("SELECT s.*, u.first_name, u.last_name, u.email, u.phone, u.avatar FROM students s JOIN users u ON u.id = s.user_id WHERE s.user_id = ? LIMIT 1");
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Get teacher profile by user_id.
 */
function get_teacher_by_user_id($userId) {
    $pdo = getDBConnection();
    try {
        $stmt = $pdo->prepare("SELECT t.*, u.first_name, u.last_name, u.email, u.phone, u.avatar FROM teachers t JOIN users u ON u.id = t.user_id WHERE t.user_id = ? LIMIT 1");
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Exception $e) {
        return null;
    }
}
