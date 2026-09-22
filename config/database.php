<?php

function getDBConnection() {
    static $pdo = null;

    if ($pdo === null) {
        $host     = function_exists('env') ? env('DB_HOST', '127.0.0.1') : (getenv('DB_HOST') ?: '127.0.0.1');
        $port     = function_exists('env') ? env('DB_PORT', '3306') : (getenv('DB_PORT') ?: '3306');
        $dbname   = function_exists('env') ? env('DB_NAME', 'studyme') : (getenv('DB_NAME') ?: 'studyme');
        $username = function_exists('env') ? env('DB_USER', 'root') : (getenv('DB_USER') ?: 'root');
        $password = function_exists('env') ? env('DB_PASS', '') : (getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
        $charset  = 'utf8mb4';

        $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=$charset";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, $username, $password, $options);
        } catch (PDOException $e) {
            if ($e->getCode() === 1049 || strpos($e->getMessage(), 'Unknown database') !== false) {
                try {
                    $initDsn = "mysql:host=$host;port=$port;charset=$charset";
                    $initPdo = new PDO($initDsn, $username, $password, $options);
                    $initPdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                    $pdo = new PDO($dsn, $username, $password, $options);
                } catch (PDOException $ex) {
                    error_log("StudyMe DB Auto-Create Error: " . $ex->getMessage());
                }
            }

            if ($pdo === null) {
                error_log("StudyMe DB Connection Error: " . $e->getMessage());

                if (defined('APP_ENV') && APP_ENV === 'development') {
                    die("Database Connection Failed: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "<br><br>Tip: Import <code>database/studyme.sql</code> in phpMyAdmin or visit <a href='database/init_db.php'>database/init_db.php</a> to initialize the database.");
                } else {
                    die("Database connection error. Please contact administrator.");
                }
            }
        }
    }

    return $pdo;
}

$pdo = getDBConnection();