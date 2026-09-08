<?php
/**
 * StudyMe Comprehensive Project Lint & Syntax Audit
 */
$baseDir = dirname(__DIR__);
$phpCli = 'C:\\xampp\\php\\php.exe';

echo "=======================================================\n";
echo "       STUDYME COMPREHENSIVE CODEBASE AUDIT           \n";
echo "=======================================================\n\n";

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($baseDir, RecursiveDirectoryIterator::SKIP_DOTS)
);

$phpFiles = [];
$allFiles = [];
foreach ($iterator as $path => $file) {
    if ($file->isFile()) {
        $allFiles[] = $path;
        if (pathinfo($path, PATHINFO_EXTENSION) === 'php') {
            $phpFiles[] = $path;
        }
    }
}

echo "Total files found: " . count($allFiles) . "\n";
echo "Total PHP files found: " . count($phpFiles) . "\n\n";

echo "1. Performing PHP Syntax Linting across all PHP files...\n";
$syntaxErrors = [];
foreach ($phpFiles as $file) {
    $cmd = escapeshellcmd($phpCli) . " -l " . escapeshellarg($file) . " 2>&1";
    $output = shell_exec($cmd);
    if (strpos($output, 'No syntax errors detected') === false) {
        $syntaxErrors[] = ['file' => $file, 'error' => trim($output)];
    }
}

if (empty($syntaxErrors)) {
    echo "✔ All " . count($phpFiles) . " PHP files passed syntax check with 0 syntax errors!\n\n";
} else {
    echo "❌ Found " . count($syntaxErrors) . " syntax error(s):\n";
    foreach ($syntaxErrors as $err) {
        echo "  - " . $err['file'] . "\n    " . $err['error'] . "\n";
    }
    echo "\n";
}
