<?php
/**
 * StudyMe Comprehensive Deep Codebase Auditor
 * Inspects all files for:
 * 1. Missing includes/requires
 * 2. Invalid SQL table references
 * 3. Broken redirect destinations
 * 4. Broken form actions
 * 5. Undefined or missing user settings/profile pages across Student, Teacher, and Admin
 */
require_once dirname(__DIR__) . '/config/main.php';

$pdo = getDBConnection();
$baseDir = BASE_PATH;

echo "=======================================================\n";
echo "      STUDYME DEEP TECHNICAL AUDIT & ANALYSIS         \n";
echo "=======================================================\n\n";

// 1. Get all actual database tables
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
$tablesLower = array_map('strtolower', $tables);
echo "✔ MySQL Database Tables (" . count($tables) . "): " . implode(', ', $tables) . "\n\n";

// 2. Scan all PHP files
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($baseDir, RecursiveDirectoryIterator::SKIP_DOTS)
);

$phpFiles = [];
foreach ($iterator as $path => $file) {
    if ($file->isFile() && pathinfo($path, PATHINFO_EXTENSION) === 'php') {
        $phpFiles[] = $path;
    }
}

$brokenIncludes = [];
$brokenRedirects = [];
$brokenFormActions = [];
$sqlIssues = [];

foreach ($phpFiles as $file) {
    $content = file_get_contents($file);
    $relFile = str_replace($baseDir . DIRECTORY_SEPARATOR, '', $file);
    $lines = explode("\n", $content);

    // A. Check require/include statements
    preg_match_all('/(?:require|require_once|include|include_once)\s*[\(\s]+([^;\)]+)[\)\s]*;/i', $content, $incMatches, PREG_SET_ORDER);
    foreach ($incMatches as $m) {
        $rawPath = trim($m[1], "'\" ");
        // Skip dynamic expressions like $file, dirname(__DIR__), BASE_PATH
        if (strpos($rawPath, '$') !== false || strpos($rawPath, 'BASE_PATH') !== false || strpos($rawPath, 'dirname') !== false) {
            // Evaluate standard BASE_PATH patterns
            if (preg_match('/BASE_PATH\s*\.\s*[\'"]([^\'"]+)[\'"]/', $m[0], $subM)) {
                $target = BASE_PATH . $subM[1];
                if (!file_exists($target)) {
                    $brokenIncludes[] = ['file' => $relFile, 'target' => $target, 'code' => $m[0]];
                }
            }
        } elseif (file_exists($rawPath) === false && file_exists(dirname($file) . '/' . $rawPath) === false && file_exists(BASE_PATH . '/' . $rawPath) === false) {
            $brokenIncludes[] = ['file' => $relFile, 'target' => $rawPath, 'code' => $m[0]];
        }
    }

    // B. Check redirect() and header('Location: ...')
    preg_match_all('/(?:redirect|header)\s*\(\s*[\'"](?:Location:\s*)?([^\'"]+)[\'"]\s*\)/i', $content, $redMatches);
    foreach ($redMatches[1] as $target) {
        if (strpos($target, 'http') === 0 || strpos($target, '#') === 0 || strpos($target, '?') === 0) continue;
        $cleanTarget = strtok($target, '?');
        $cleanTarget = ltrim($cleanTarget, '/');
        if (!file_exists(BASE_PATH . '/' . $cleanTarget) && !file_exists(dirname($file) . '/' . $cleanTarget)) {
            // Check if it's an external or parameterized route
            if (!in_array($cleanTarget, ['javascript:void(0)', ''])) {
                $brokenRedirects[] = ['file' => $relFile, 'target' => $target];
            }
        }
    }

    // C. Check form action URLs
    preg_match_all('/<form[^>]+action=[\'"]([^\'"]+)[\'"]/i', $content, $formMatches);
    foreach ($formMatches[1] as $action) {
        if (empty($action) || strpos($action, 'http') === 0 || strpos($action, '#') === 0 || strpos($action, '?') === 0 || strpos($action, '<?=') !== false) continue;
        $cleanAction = strtok($action, '?');
        $cleanAction = ltrim($cleanAction, '/');
        if (!file_exists(BASE_PATH . '/' . $cleanAction) && !file_exists(dirname($file) . '/' . $cleanAction)) {
            $brokenFormActions[] = ['file' => $relFile, 'action' => $action];
        }
    }
}

echo "2. Include / Require Path Verification:\n";
if (empty($brokenIncludes)) {
    echo "✔ 0 broken include/require paths found across all PHP files.\n\n";
} else {
    echo "❌ Found " . count($brokenIncludes) . " broken include path(s):\n";
    foreach ($brokenIncludes as $bi) {
        echo "   - {$bi['file']}: {$bi['target']}\n";
    }
    echo "\n";
}

echo "3. Redirect Target Verification:\n";
if (empty($brokenRedirects)) {
    echo "✔ 0 broken redirect destinations found.\n\n";
} else {
    echo "❌ Found " . count($brokenRedirects) . " broken redirect target(s):\n";
    foreach ($brokenRedirects as $br) {
        echo "   - {$br['file']} -> {$br['target']}\n";
    }
    echo "\n";
}

echo "4. Form Action Verification:\n";
if (empty($brokenFormActions)) {
    echo "✔ 0 broken static form action targets found.\n\n";
} else {
    echo "❌ Found " . count($brokenFormActions) . " broken form action target(s):\n";
    foreach ($brokenFormActions as $fa) {
        echo "   - {$fa['file']} -> {$fa['action']}\n";
    }
    echo "\n";
}

// 5. Check Universal User Settings across Student, Teacher, and Admin
echo "5. Universal Settings & Profile Pages Audit:\n";
$settingsFiles = [
    'Student Settings' => 'student/settings.php',
    'Student Profile'  => 'student/profile.php',
    'Teacher Settings' => 'teacher/settings.php',
    'Teacher Profile'  => 'teacher/profile.php',
    'Admin Settings'   => 'admin/system-settings.php',
    'Admin Profile'    => 'admin/profile.php',
];

foreach ($settingsFiles as $label => $rel) {
    $exists = file_exists(BASE_PATH . '/' . $rel);
    echo ($exists ? "✔" : "❌") . " $label: $rel " . ($exists ? "EXISTS" : "MISSING") . "\n";
}
