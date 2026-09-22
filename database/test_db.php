<?php

require_once dirname(__DIR__) . '/config/main.php';

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>\n<html lang=\"en\">\n<head>\n<meta charset=\"UTF-8\">\n<title>StudyMe - Database Connection Test</title>\n";
echo "<style>body { font-family: system-ui, -apple-system, sans-serif; background: #0f172a; color: #f8fafc; padding: 2rem; } .card { max-width: 600px; margin: 0 auto; background: #1e293b; padding: 2rem; border-radius: 12px; border: 1px solid #334155; } .success { color: #4ade80; font-weight: bold; font-size: 1.25rem; } .failed { color: #f87171; font-weight: bold; font-size: 1.25rem; } ul { list-style: none; padding: 0; } li { padding: 0.5rem 0; border-bottom: 1px solid #334155; } code { background: #0f172a; padding: 0.2rem 0.5rem; border-radius: 4px; color: #38bdf8; }</style>\n";
echo "</head>\n<body>\n<div class=\"card\">\n";
echo "<h2>StudyMe LMS - Database Connection Test</h2>\n";
echo "<p>Testing chain: <code>PHP</code> &rarr; <code>PDO</code> &rarr; <code>MySQL</code> &rarr; <code>studyme</code></p>\n";

try {

    $db = getDBConnection();

    $stmt = $db->query("SELECT 1 AS test_val");
    $result = $stmt->fetch();

    if ($result && isset($result['test_val']) && (int)$result['test_val'] === 1) {
        echo "<p class=\"success\">&check; Database connection successful</p>\n";
        echo "<ul>\n";
        echo "<li><strong>Database Name:</strong> studyme</li>\n";
        echo "<li><strong>PDO Driver:</strong> " . e($db->getAttribute(PDO::ATTR_DRIVER_NAME)) . "</li>\n";
        echo "<li><strong>Server Version:</strong> " . e($db->getAttribute(PDO::ATTR_SERVER_VERSION)) . "</li>\n";
        echo "<li><strong>Connection Status:</strong> Active &amp; Ready</li>\n";
        echo "</ul>\n";
    } else {
        echo "<p class=\"failed\">&cross; Database connection failed</p>\n";
        echo "<p>Query executed but did not return expected value.</p>\n";
    }

} catch (Exception $e) {
    echo "<p class=\"failed\">&cross; Database connection failed</p>\n";
    echo "<p><strong>Error Message:</strong> " . e($e->getMessage()) . "</p>\n";
}

echo "<hr style=\"border-color: #334155; margin-top: 1.5rem;\">\n";
echo "<p style=\"font-size: 0.875rem; color: #94a3b8;\">Note: This is a development diagnostic tool. Do not expose in production.</p>\n";
echo "</div>\n</body>\n</html>";
