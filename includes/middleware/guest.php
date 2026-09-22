<?php

require_once dirname(__DIR__, 2) . '/config/main.php';

if (is_logged_in()) {
    $role = current_user_role();
    $dash = $role === 'admin' ? 'admin/dashboard.php' : ($role === 'teacher' ? 'teacher/dashboard.php' : 'student/dashboard.php');
    redirect($dash);
}
