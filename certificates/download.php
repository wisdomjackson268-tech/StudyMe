<?php
/**
 * StudyMe AI Platform — Certificate Download Gateway
 */
require_once dirname(__DIR__) . '/config/main.php';

$certNumber = trim($_GET['cert'] ?? '');
if (empty($certNumber)) {
    redirect('certificates/verify.php');
}

// Redirect to printable view
redirect('certificates/view.php?cert=' . urlencode($certNumber));
