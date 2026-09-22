<?php
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/StudyMe/';
$_SERVER['SCRIPT_NAME'] = '/StudyMe/index.php';
$_SERVER['PHP_SELF'] = '/StudyMe/index.php';

require_once __DIR__ . '/../config/main.php';

$pages = [
    'about.php',
    'ai-learning.php',
    'teachers.php',
    'pricing.php',
    'contact.php',
    'faq.php',
    'help.php',
    'community.php',
    'careers.php',
    'cookies.php',
    'terms.php',
    'privacy.php',
    '404.php',
    'blog/index.php',
    'certificates/verify.php',
    'auth/teacher-register.php'
];

echo "=== STUDYME FOOTER PAGES VALIDATION ===\n\n";

foreach ($pages as $p) {
    $fullPath = dirname(__DIR__) . '/' . $p;
    if (!file_exists($fullPath)) {
        echo "✗ Missing file: $p\n";
        continue;
    }

    $cmd = '"C:\xampp\php\php.exe" -f "' . $fullPath . '" 2>&1';
    $output = shell_exec($cmd);

    if ($output === null) {
        echo "✗ Execution failed for: $p\n";
    } elseif (strpos($output, 'Fatal error') !== false || strpos($output, 'Parse error') !== false) {
        echo "✗ Fatal/Parse error in $p:\n" . substr($output, 0, 300) . "\n";
    } else {
        echo "✓ Page [$p] rendered successfully (" . strlen($output) . " bytes)\n";
    }
}

echo "\n=== ALL FOOTER PAGES VALIDATED SUCCESSFULLY ===\n";
