<?php
/**
 * StudyMe AI Platform — Quizzes Index (Routes to student/quizzes or login)
 */
require_once dirname(__DIR__) . '/config/main.php';

if (is_logged_in()) {
    redirect('student/quizzes.php');
} else {
    set_flash('info', 'Please log in to access course quizzes and evaluations.');
    redirect('auth/login.php');
}
