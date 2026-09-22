<?php

require_once __DIR__ . '/config/main.php';
require_once BASE_PATH . '/includes/functions/enrollments.php';

if (!is_logged_in()) {
    set_flash('error', 'Please log in first to access your dashboard.');
    redirect('auth/login.php');
}

$role = current_user_role();

if ($role === (defined('ROLE_ADMIN') ? ROLE_ADMIN : 'admin')) {
    redirect('admin/dashboard.php');
} elseif ($role === (defined('ROLE_TEACHER') ? ROLE_TEACHER : 'teacher')) {
    redirect('teacher/dashboard.php');
} else {
    $uid = (int)current_user('id');
    if (is_secondary_student($uid)) {
        redirect('student/secondary-dashboard.php');
    } else {
        redirect('student/dashboard.php');
    }
}

