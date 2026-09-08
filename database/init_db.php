<?php
/**
 * StudyMe AI Platform - Database Initialization & Seeding Script
 * 
 * Automatically runs schema.sql and seed.sql against the configured MySQL database.
 * Run via browser at http://localhost/StudyMe/database/init_db.php or CLI.
 */

require_once dirname(__DIR__) . '/config/main.php';

header('Content-Type: text/html; charset=utf-8');

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
        .setup-card { background: #0F172A; border: 1px solid rgba(255,255,255,0.12); border-radius: 18px; max-width: 680px; width: 100%; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5); }
        .log-box { background: #070B15; border: 1px solid rgba(255,255,255,0.08); border-radius: 12px; max-height: 320px; overflow-y: auto; font-family: monospace; font-size: 0.85rem; }
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
            <p class="text-white-50 mb-0 small">Schema Migration &amp; Comprehensive Seed Execution</p>
        </div>
    </div>

    <div class="log-box p-3 mb-4">
        <?php
        try {
            $pdo = getDBConnection();
            echo "<div class='text-success mb-2'><i class='bi bi-check-circle-fill me-1'></i> Connected to MySQL database [studyme] successfully.</div>";

            // 1. Run Schema
            $schemaFile = __DIR__ . '/schema.sql';
            if (file_exists($schemaFile)) {
                $schemaSql = file_get_contents($schemaFile);
                $pdo->exec($schemaSql);
                echo "<div class='text-info mb-2'><i class='bi bi-check-circle-fill me-1'></i> Schema executed: All 26 core tables initialized.</div>";
            }

            // 2. Run Seed
            $seedFile = __DIR__ . '/seed.sql';
            if (file_exists($seedFile)) {
                $seedSql = file_get_contents($seedFile);
                $pdo->exec($seedSql);
                echo "<div class='text-info mb-2'><i class='bi bi-check-circle-fill me-1'></i> Seed data loaded: Pricing plans (TECH ₦10k, SECONDARY ₦2k, UNIVERSITY ₦4k, TEACHER ₦5k), sample courses, lessons, quizzes, certificates, and demo accounts.</div>";
            }

            echo "<div class='text-warning mt-3 pt-2 border-top border-secondary border-opacity-25'><strong>Demo Credentials (Password: Password123!):</strong></div>";
            echo "<div class='text-white-50'>• Admin: <code>admin@studyme.ng</code></div>";
            echo "<div class='text-white-50'>• Teacher: <code>teacher@studyme.ng</code></div>";
            echo "<div class='text-white-50'>• Student: <code>student@studyme.ng</code></div>";

        } catch (Exception $e) {
            echo "<div class='text-danger'><i class='bi bi-exclamation-triangle-fill me-1'></i> Error during database setup: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
        ?>
    </div>

    <div class="d-flex gap-3 justify-content-end">
        <a href="<?= url('index.php') ?>" class="btn btn-outline-light rounded-pill px-4">Home</a>
        <a href="<?= url('auth/login.php') ?>" class="btn btn-primary rounded-pill px-4 fw-bold">Proceed to Login <i class="bi bi-arrow-right ms-1"></i></a>
    </div>
</div>

</body>
</html>
