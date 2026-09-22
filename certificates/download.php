<?php

require_once dirname(__DIR__) . '/config/main.php';

$certNumber = trim($_GET['cert'] ?? '');
if (empty($certNumber)) {
    redirect('certificates/verify.php');
}

redirect('certificates/view.php?cert=' . urlencode($certNumber));
