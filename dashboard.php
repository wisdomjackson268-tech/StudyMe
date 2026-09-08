<?php
/**
 * StudyMe AI Platform — Central Dashboard Gateway Router
 * 
 * Securely routes requests to the appropriate role-based dashboard:
 * - Unauthenticated (Guest): Redirects to login with required prompt
 * - Student: Routes to student/dashboard.php
 * - Teacher: Routes to teacher/dashboard.php
 * - Admin: Routes to admin/dashboard.php
 * 
 * Never trusts client/browser-supplied role values.
 */
require_once __DIR__ . '/config/main.php';

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
    redirect('student/dashboard.php');
}
