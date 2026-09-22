<?php

require_once dirname(__DIR__) . '/config/main.php';

require_login();
$role = current_user_role();

if ($role === ROLE_ADMIN) {
    redirect('admin/notifications.php');
} elseif ($role === ROLE_TEACHER) {
    redirect('teacher/notifications.php');
} else {
    redirect('student/notifications.php');
}
