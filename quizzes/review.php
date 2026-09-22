<?php

require_once dirname(__DIR__) . '/config/main.php';

$attemptId = (int)($_GET['attempt_id'] ?? 0);
if ($attemptId > 0) {
    redirect('quizzes/results.php?attempt_id=' . $attemptId);
} else {
    redirect('student/quizzes.php');
}
