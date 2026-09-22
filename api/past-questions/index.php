<?php

require_once dirname(__DIR__, 2) . '/config/main.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$subject  = strtolower(trim($_GET['subject'] ?? ($_POST['subject'] ?? 'mathematics')));
$examType = strtolower(trim($_GET['exam'] ?? ($_GET['type'] ?? ($_POST['exam'] ?? 'utme'))));
$year     = !empty($_GET['year']) ? (int)$_GET['year'] : (!empty($_POST['year']) ? (int)$_POST['year'] : null);
$count    = !empty($_GET['count']) ? (int)$_GET['count'] : (!empty($_POST['count']) ? (int)$_POST['count'] : 10);
$page     = !empty($_GET['page']) ? (int)$_GET['page'] : (!empty($_POST['page']) ? (int)$_POST['page'] : 1);

$result = fetch_aloc_questions($subject, $examType, $year, $count, $page);

if ($result['success']) {
    http_response_code(200);
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
} else {

    http_response_code(200);
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}
