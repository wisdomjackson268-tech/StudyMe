<?php

require_once dirname(__DIR__) . '/config/main.php';

function extractIncludeStatements(string $source): array {
    $tokens = token_get_all($source);
    $statements = [];
    $count = count($tokens);

    for ($i = 0; $i < $count; $i++) {
        $token = $tokens[$i];
        if (!is_array($token)) {
            continue;
        }

        $tokenId = $token[0];
        if (!in_array($tokenId, [T_REQUIRE, T_REQUIRE_ONCE, T_INCLUDE, T_INCLUDE_ONCE], true)) {
            continue;
        }

        $statement = $token[1];
        $j = $i + 1;
        while ($j < $count) {
            $next = $tokens[$j];
            if (is_array($next)) {
                $statement .= $next[1];
            } else {
                $statement .= $next;
            }

            if ($next === ';') {
                $j++;
                break;
            }
            $j++;
        }

        $statements[] = $statement;
        $i = $j - 1;
    }

    return $statements;
}

function extractRedirectCalls(string $source): array {
    $calls = [];
    $tokens = token_get_all($source);
    $count = count($tokens);

    for ($i = 0; $i < $count; $i++) {
        if (!is_array($tokens[$i])) {
            continue;
        }

        $text = strtolower($tokens[$i][1]);
        if (!in_array($text, ['header', 'redirect'], true)) {
            continue;
        }

        $call = $tokens[$i][1];
        $depth = 0;
        $j = $i + 1;
        while ($j < $count) {
            $next = $tokens[$j];
            if (is_array($next)) {
                $call .= $next[1];
            } else {
                $call .= $next;
                if ($next === '(') {
                    $depth++;
                } elseif ($next === ')') {
                    $depth--;
                    if ($depth === 0) {
                        $j++;
                        break;
                    }
                }
            }
            $j++;
        }

        $calls[] = $call;
        $i = $j - 1;
    }

    return $calls;
}

function resolveIncludeTargets(string $statement, string $baseDir): array {
    $targets = [];
    $normalized = preg_replace('/\s+/', ' ', $statement);

    $resolvedBase = $baseDir;
    if (stripos($normalized, 'BASE_PATH') !== false) {
        $resolvedBase = BASE_PATH;
    } elseif (preg_match('/dirname\s*\(\s*__DIR__\s*\)/i', $normalized)) {
        $resolvedBase = dirname($baseDir);
    } elseif (stripos($normalized, '__DIR__') !== false) {
        $resolvedBase = $baseDir;
    }

    preg_match_all('/(?:__DIR__|BASE_PATH|dirname\s*\(\s*__DIR__\s*\))\s*\.\s*[\'\"]([^\'\"]+)[\'\"]/i', $normalized, $dirMatches);
    foreach ($dirMatches[1] as $path) {
        $targets[] = rtrim($resolvedBase, DIRECTORY_SEPARATOR) . '/' . ltrim($path, '/');
    }

    preg_match_all('/(?:require|include)(?:_once)?\s*(?:\(\s*)?[\'\"]([^\'\"]+)[\'\"]/i', $normalized, $literalMatches);
    foreach ($literalMatches[1] as $path) {
        if ($path !== '') {
            $targets[] = rtrim($baseDir, DIRECTORY_SEPARATOR) . '/' . ltrim($path, '/');
        }
    }

    return array_values(array_unique(array_filter($targets, static fn($value) => $value !== '')));
}

$pdo = getDBConnection();
$baseDir = BASE_PATH;

echo "=======================================================\n";
echo "      STUDYME DEEP TECHNICAL AUDIT & ANALYSIS         \n";
echo "=======================================================\n\n";

$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
echo "✔ MySQL Database Tables (" . count($tables) . "): " . implode(', ', $tables) . "\n\n";

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

foreach ($phpFiles as $file) {
    $content = file_get_contents($file);
    if ($content === false) {
        continue;
    }

    $relFile = str_replace($baseDir . DIRECTORY_SEPARATOR, '', $file);

    foreach (extractIncludeStatements($content) as $statement) {
        foreach (resolveIncludeTargets($statement, dirname($file)) as $target) {
            if (file_exists($target)) {
                continue;
            }
            $brokenIncludes[] = ['file' => $relFile, 'target' => $target, 'code' => $statement];
        }
    }

    foreach (extractRedirectCalls($content) as $call) {
        $target = null;

        if (preg_match('/header\s*\(\s*[\'\"]\s*Location\s*:\s*([\'\"]?)([^\'\")]+)\1/i', $call, $headerMatch)) {
            $target = $headerMatch[2];
        } elseif (preg_match('/redirect\s*\(\s*[\'\"]([^\'\"]+)[\'\"]/i', $call, $redirectMatch)) {
            $target = $redirectMatch[1];
        }

        if ($target === null || $target === '' || strpos($target, '$') !== false || strpos($target, '.') !== false && preg_match('/[\$\w]+\s*\./', $target)) {
            continue;
        }

        if (strpos($target, 'http') === 0 || strpos($target, '#') === 0 || strpos($target, '?') === 0) {
            continue;
        }

        $cleanTarget = strtok($target, '?');
        $cleanTarget = ltrim($cleanTarget, '/');
        if (!file_exists(BASE_PATH . '/' . $cleanTarget) && !file_exists(dirname($file) . '/' . $cleanTarget) && !in_array($cleanTarget, ['javascript:void(0)', ''])) {
            $brokenRedirects[] = ['file' => $relFile, 'target' => $target];
        }
    }

    preg_match_all('/<form[^>]+action=[\'\"]([^\'\"]+)[\'\"]/i', $content, $formMatches);
    foreach ($formMatches[1] as $action) {
        if (empty($action) || strpos($action, 'http') === 0 || strpos($action, '#') === 0 || strpos($action, '?') === 0 || strpos($action, '<?=') !== false) {
            continue;
        }

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
