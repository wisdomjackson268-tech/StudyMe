<?php

/**
 * StudyMe AI-Powered Learning Platform - Database Connection Setup
 * Uses PDO with utf8mb4 charset and secure attributes.
 */

function getDBConnection() {
    static $pdo = null;

    if ($pdo === null) {
        $host = "127.0.0.1";
        $dbname = "studyme";
        $username = "root";
        $password = "";
        $charset = "utf8mb4";

        $dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, $username, $password, $options);
        } catch (PDOException $e) {
            error_log("StudyMe DB Connection Error: " . $e->getMessage());

            if (defined('APP_ENV') && APP_ENV === 'development') {
                die("Database Connection Failed: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
            } else {
                die("Database connection error. Please contact administrator.");
            }
        }
    }

    return $pdo;
}

// Global $pdo instance available across application scripts
$pdo = getDBConnection();