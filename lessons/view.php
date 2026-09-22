<?php

require_once dirname(__DIR__) . '/config/main.php';

$lessonId = (int)($_GET['id'] ?? 0);
if ($lessonId > 0) {
    if (is_logged_in()) {
        redirect('student/lesson.php?id=' . $lessonId);
    } else {
        set_flash('info', 'Please sign in to access course lessons and AI study assistance.');
        redirect('auth/login.php');
    }
} else {
    redirect('courses/index.php');
}
