<?php

require_once dirname(__DIR__) . '/config/main.php';

$pdo = getDBConnection();

$columns = $pdo->query("SHOW COLUMNS FROM teachers LIKE 'website'")->fetchAll();
if (!$columns) {
    $pdo->exec("ALTER TABLE teachers ADD COLUMN website VARCHAR(255) NULL AFTER bio");
    echo "Added teachers.website.\n";
} else {
    echo "teachers.website already exists.\n";
}
