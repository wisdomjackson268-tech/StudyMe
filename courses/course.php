<?php

require_once dirname(__DIR__) . '/config/main.php';

$id   = (int)($_GET['id'] ?? 0);
$slug = trim($_GET['slug'] ?? '');

if ($slug !== '') {
    redirect('courses/details.php?slug=' . urlencode($slug));
} elseif ($id > 0) {
    redirect('courses/details.php?id=' . $id);
} else {
    redirect('courses/index.php');
}
