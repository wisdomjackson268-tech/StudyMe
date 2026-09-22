<?php

require_once dirname(__DIR__) . '/config/main.php';

$isCli = (php_sapi_name() === 'cli');

if (!$isCli) {
    header('Content-Type: text/html; charset=utf-8');
}

$logs = [];
$status = 'pending';

try {
    $host     = function_exists('env') ? env('DB_HOST', '127.0.0.1') : (getenv('DB_HOST') ?: '127.0.0.1');
    $port     = function_exists('env') ? env('DB_PORT', '3306') : (getenv('DB_PORT') ?: '3306');
    $dbname   = function_exists('env') ? env('DB_NAME', 'studyme') : (getenv('DB_NAME') ?: 'studyme');
    $username = function_exists('env') ? env('DB_USER', 'root') : (getenv('DB_USER') ?: 'root');
    $password = function_exists('env') ? env('DB_PASS', '') : (getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
    $charset  = 'utf8mb4';

    $serverDsn = "mysql:host=$host;port=$port;charset=$charset";
    $serverPdo = new PDO($serverDsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    $serverPdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $logs[] = ["success", "MySQL Server connection established. Database [$dbname] verified/created."];

    $pdo = getDBConnection();
    $logs[] = ["success", "Connected to MySQL database [$dbname] with PDO utf8mb4."];

    $schemaFile = __DIR__ . '/schema.sql';
    if (file_exists($schemaFile)) {
        $schemaSql = file_get_contents($schemaFile);
        $pdo->exec($schemaSql);
        $logs[] = ["info", "Schema executed successfully: All 42 core tables created with primary keys, indexes, and foreign keys."];
    } else {
        throw new Exception("schema.sql file not found in " . __DIR__);
    }

    $seedFile = __DIR__ . '/seed.sql';
    if (file_exists($seedFile)) {
        $seedSql = file_get_contents($seedFile);
        $pdo->exec($seedSql);
        $logs[] = ["info", "Seed data loaded successfully: Official pricing (Tech ₦10,000, Secondary ₦3,000, University ₦5,000, Teacher ₦4,000), 11 academic faculties, 20 Tech courses, 19 Secondary subjects, 45 University modules, lessons, quizzes, assignments, past questions, and demo accounts."];
    } else {
        throw new Exception("seed.sql file not found in " . __DIR__);
    }

    $status = 'success';
} catch (Exception $e) {
    $status = 'error';
    $logs[] = ["danger", "Error during database setup: " . $e->getMessage()];
}

if ($isCli) {
    echo "\n=== StudyMe Database Initialization ===\n";
    foreach ($logs as $l) {
        $tag = strtoupper($l[0]);
        echo "[$tag] {$l[1]}\n";
    }
    if ($status === 'success') {
        echo "\nDemo Credentials (Password: Password123!):\n";
        echo " - Admin:   admin@studyme.ng\n";
        echo " - Teacher: teacher@studyme.ng\n";
        echo " - Student: student@studyme.ng\n";
        echo "\nDatabase migration completed successfully!\n\n";
    }
    exit($status === 'success' ? 0 : 1);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudyMe - Database Migration &amp; Seed</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <style>
        body { background: #0B1120; color: #F8FAFC; min-height: 100vh; display: flex; align-items: center; justify-content: center; font-family: system-ui, -apple-system, sans-serif; }
        .setup-card { background: #0F172A; border: 1px solid rgba(255,255,255,0.12); border-radius: 18px; max-width: 720px; width: 100%; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5); }
        .log-box { background: #070B15; border: 1px solid rgba(255,255,255,0.08); border-radius: 12px; max-height: 380px; overflow-y: auto; font-family: monospace; font-size: 0.85rem; }
    </style>
</head>
<body class="p-3">

<div class="setup-card p-4 p-md-5">
    <div class="d-flex align-items-center gap-3 mb-4">
        <div class="p-3 bg-primary bg-opacity-20 text-primary rounded-circle fs-3">
            <i class="bi bi-database-fill-gear"></i>
        </div>
        <div>
            <h3 class="fw-bold mb-1">StudyMe Database Setup</h3>
            <p class="text-white-50 mb-0 small">Automated MySQL Schema &amp; Comprehensive Seed Execution</p>
        </div>
    </div>

    <div class="log-box p-3 mb-4">
        <?php foreach ($logs as $l): ?>
            <div class="text-<?= $l[0] ?> mb-2">
                <i class="bi bi-<?= $l[0] === 'success' ? 'check-circle-fill' : ($l[0] === 'info' ? 'info-circle-fill' : 'exclamation-triangle-fill') ?> me-1"></i>
                <?= htmlspecialchars($l[1], ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endforeach; ?>

        <?php if ($status === 'success'): ?>
            <div class="text-warning mt-3 pt-2 border-top border-secondary border-opacity-25">
                <strong><i class="bi bi-shield-lock-fill me-1"></i> Default Demo Credentials (Password: <code>Password123!</code>):</strong>
            </div>
            <div class="text-white-50 mt-1">• <strong>Admin:</strong> <code>admin@studyme.ng</code></div>
            <div class="text-white-50">• <strong>Teacher:</strong> <code>teacher@studyme.ng</code> (Dr. Sarah Jenkins)</div>
            <div class="text-white-50">• <strong>Teacher:</strong> <code>alex@studyme.ng</code> (Alex Rivera)</div>
            <div class="text-white-50">• <strong>Student:</strong> <code>student@studyme.ng</code> (David Okonkwo)</div>
            <div class="text-white-50">• <strong>Student:</strong> <code>amina@studyme.ng</code> (Amina Bello)</div>
        <?php endif; ?>
    </div>

    <div class="d-flex gap-3 justify-content-end">
        <a href="<?= url('index.php') ?>" class="btn btn-outline-light rounded-pill px-4">Home</a>
        <a href="<?= url('auth/login.php') ?>" class="btn btn-primary rounded-pill px-4 fw-bold">Proceed to Login <i class="bi bi-arrow-right ms-1"></i></a>
    </div>
</div>

</body>
</html>
