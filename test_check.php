<?php
require_once __DIR__ . '/config/main.php';
$pdo = getDBConnection();
$stmt = $pdo->query("SELECT id, message_type, media_url, duration_seconds, created_at FROM live_class_messages ORDER BY id DESC LIMIT 5");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
