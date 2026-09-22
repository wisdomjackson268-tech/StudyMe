<?php
function init_session() {
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }

    if (headers_sent()) {
        return;
    }

    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_httponly', 1);

    $cookieParams = session_get_cookie_params();

    $appPath = '/';
    $appDomain = $cookieParams['domain'] ?? '';
    if (defined('APP_URL')) {
        $parsed = parse_url(APP_URL);
        if (!empty($parsed['path'])) {
            $appPath = rtrim($parsed['path'], '/') ?: '/';
        }
        if (!empty($parsed['host'])) {
            $appDomain = $parsed['host'];
        }
    }

    session_set_cookie_params([
        'lifetime' => $cookieParams['lifetime'],
        'path'     => $appPath ?: '/',
        'domain'   => $appDomain ?: $cookieParams['domain'],
        'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on'),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    session_start();
}

function is_logged_in() {
    init_session();
    $key = defined('SESSION_USER_ID') ? SESSION_USER_ID : 'user_id';
    $userId = $_SESSION[$key] ?? null;
    if (empty($userId)) {
        return false;
    }

    static $validatedUsers = [];
    $uid = (int)$userId;
    if (isset($validatedUsers[$uid])) {
        return $validatedUsers[$uid];
    }

    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$uid]);
        $exists = (bool)$stmt->fetchColumn();
        if (!$exists) {
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(
                    session_name(),
                    '',
                    time() - 86400,
                    $params['path'],
                    $params['domain'],
                    $params['secure'],
                    $params['httponly']
                );
            }
            if (session_status() === PHP_SESSION_ACTIVE) {
                @session_destroy();
            }
            $validatedUsers[$uid] = false;
            return false;
        }
        $validatedUsers[$uid] = true;
        return true;
    } catch (Exception $e) {
        return false;
    }
}

function current_user($field = null) {
    init_session();
    $key = defined('SESSION_USER_DATA') ? SESSION_USER_DATA : 'user';
    $user = $_SESSION[$key] ?? null;

    if ($user === null) {
        return null;
    }

    if ($field !== null) {
        return is_array($user) ? ($user[$field] ?? null) : null;
    }

    return $user;
}

function current_user_role() {
    init_session();
    $key = defined('SESSION_USER_ROLE') ? SESSION_USER_ROLE : 'user_role';
    return $_SESSION[$key] ?? current_user('role');
}

function has_role($roles) {
    if (!is_logged_in()) {
        return false;
    }

    $currentRole = current_user_role();
    if (!$currentRole) {
        return false;
    }

    if (is_array($roles)) {
        return in_array($currentRole, $roles, true);
    }

    return $currentRole === $roles;
}

function require_login() {
    if (!is_logged_in()) {
        set_flash('error', 'Please log in first to access your dashboard.');
        redirect('auth/login.php');
    }
}

function require_role($roles) {
    require_login();

    if (!has_role($roles)) {
        set_flash('error', 'You do not have permission to access that resource.');
        
        $role = current_user_role();
        if ($role === (defined('ROLE_ADMIN') ? ROLE_ADMIN : 'admin')) {
            redirect('admin/dashboard.php');
        } elseif ($role === (defined('ROLE_TEACHER') ? ROLE_TEACHER : 'teacher')) {
            redirect('teacher/dashboard.php');
        } else {
            redirect('student/dashboard.php');
        }
    }
}
