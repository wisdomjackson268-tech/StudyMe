<?php

require_once dirname(__DIR__) . '/config/main.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

$userName = '';
if (is_logged_in()) {
    $u = current_user();
    $userName = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''));
}

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

session_destroy();

session_start();
session_regenerate_id(true);

$_SESSION['logout_confirmed'] = true;
$_SESSION['logout_user_name'] = $userName;

header('Location: ' . rtrim(defined('APP_URL') ? APP_URL : 'http://localhost/StudyMe', '/') . '/auth/logged-out.php');
exit;
