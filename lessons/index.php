<?php

require_once dirname(__DIR__) . '/config/main.php';

if (is_logged_in()) {
    $role = current_user_role();
    if ($role === ROLE_TEACHER) {
        redirect('teacher/lessons.php');
    } elseif ($role === ROLE_ADMIN) {
        redirect('admin/lessons.php');
    } else {
        redirect('student/my-courses.php');
    }
} else {
    redirect('courses/index.php');
}
