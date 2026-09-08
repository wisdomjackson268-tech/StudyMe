<?php
/**
 * StudyMe AI Platform — Announcement System Database Migration
 */
require_once dirname(__DIR__) . '/config/main.php';

$pdo = getDBConnection();
echo "Starting Announcement System Migration...\n";

// 1. Upgrade announcements table columns
$annCols = $pdo->query("SHOW COLUMNS FROM announcements")->fetchAll(PDO::FETCH_COLUMN);

if (!in_array('creator_role', $annCols)) {
    $pdo->exec("ALTER TABLE announcements ADD COLUMN creator_role ENUM('admin','teacher') NOT NULL DEFAULT 'admin' AFTER created_by");
    echo "✔ Added creator_role to announcements\n";
}

if (!in_array('course_id', $annCols)) {
    $pdo->exec("ALTER TABLE announcements ADD COLUMN course_id BIGINT UNSIGNED NULL AFTER creator_role");
    echo "✔ Added course_id to announcements\n";
}

if (!in_array('target_type', $annCols)) {
    $pdo->exec("ALTER TABLE announcements ADD COLUMN target_type ENUM('all','students','teachers','technology','university','secondary','course','user') NOT NULL DEFAULT 'all' AFTER course_id");
    echo "✔ Added target_type to announcements\n";
}

if (!in_array('target_user_id', $annCols)) {
    $pdo->exec("ALTER TABLE announcements ADD COLUMN target_user_id BIGINT UNSIGNED NULL AFTER target_type");
    echo "✔ Added target_user_id to announcements\n";
}

if (!in_array('attachment', $annCols)) {
    $pdo->exec("ALTER TABLE announcements ADD COLUMN attachment VARCHAR(255) NULL AFTER target_user_id");
    echo "✔ Added attachment to announcements\n";
}

// Modify status column to include archived
try {
    $pdo->exec("ALTER TABLE announcements MODIFY COLUMN status ENUM('draft','published','archived') DEFAULT 'published'");
    echo "✔ Updated status column on announcements\n";
} catch (Exception $e) {}

// 2. Create announcement_reads table for individual user read tracking
$pdo->exec("
CREATE TABLE IF NOT EXISTS announcement_reads (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    announcement_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_announcement (announcement_id, user_id),
    FOREIGN KEY (announcement_id) REFERENCES announcements(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");
echo "✔ Verified announcement_reads table\n";

// 3. Create storage/uploads/announcements directory if missing
$uploadDir = BASE_PATH . '/uploads/announcements';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
    echo "✔ Created announcements upload directory: uploads/announcements\n";
}

echo "Announcement System Migration completed successfully!\n";
