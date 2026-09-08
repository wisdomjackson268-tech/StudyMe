<?php
/**
 * StudyMe AI Platform — Central Secure Logout Handler
 *
 * Handles session destruction, cache invalidation, and logout confirmation
 * for all user roles: student, teacher, admin.
 */
require_once dirname(__DIR__) . '/config/main.php';

// Send no-cache headers immediately so the browser does not cache this response
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

// Store user name for the confirmation page before we wipe the session
$userName = '';
if (is_logged_in()) {
    $u = current_user();
    $userName = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''));
}

// 1. Clear all session variables
$_SESSION = [];

// 2. Destroy the session cookie
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 86400,          // expired far in the past
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// 3. Destroy the server-side session
session_destroy();

// 4. Start a fresh, clean session for the flash message
session_start();
session_regenerate_id(true);

// 5. Store confirmation flash for the logged-out page
$_SESSION['logout_confirmed'] = true;
$_SESSION['logout_user_name'] = $userName;

// 6. Redirect to the dedicated confirmation page
header('Location: ' . rtrim(defined('APP_URL') ? APP_URL : 'http://localhost/StudyMe', '/') . '/auth/logged-out.php');
exit;
