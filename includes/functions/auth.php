<?php
/**
 * StudyMe AI-Powered Learning Platform - Authentication & Session Helper Functions
 */

/**
 * Initialize a secure PHP session.
 *
 * @return void
 */
function init_session() {
    if (session_status() === PHP_SESSION_NONE) {
        // Enforce session security defaults
        ini_set('session.use_strict_mode', 1);
        ini_set('session.cookie_httponly', 1);
        
        // Configure cookie parameters
        $cookieParams = session_get_cookie_params();

        // Ensure cookie path and domain align with configured APP_URL when available.
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
}

/**
 * Check if a user is currently logged in.
 *
 * @return bool
 */
function is_logged_in() {
    init_session();
    $key = defined('SESSION_USER_ID') ? SESSION_USER_ID : 'user_id';
    return !empty($_SESSION[$key]);
}

/**
 * Retrieve current logged-in user session array or field.
 *
 * @param string|null $field
 * @return mixed
 */
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

/**
 * Get current user role.
 *
 * @return string|null
 */
function current_user_role() {
    init_session();
    $key = defined('SESSION_USER_ROLE') ? SESSION_USER_ROLE : 'user_role';
    return $_SESSION[$key] ?? current_user('role');
}

/**
 * Check if the currently logged-in user possesses specific role(s).
 *
 * @param string|array $roles
 * @return bool
 */
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

/**
 * Enforce authentication for protected pages.
 * Redirects to auth/login.php if unauthenticated.
 *
 * @return void
 */
function require_login() {
    if (!is_logged_in()) {
        set_flash('error', 'Please log in first to access your dashboard.');
        redirect('auth/login.php');
    }
}

/**
 * Enforce specific role requirement for protected pages.
 *
 * @param string|array $roles
 * @return void
 */
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
