<?php
/**
 * StudyMe — Resources Table Migration
 * Run: php -f database/migrate_resources.php
 */
require_once dirname(__DIR__) . '/config/database.php';

$pdo = getDBConnection();

$sql = "
CREATE TABLE IF NOT EXISTS resources (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    teacher_id BIGINT UNSIGNED NOT NULL,
    course_id BIGINT UNSIGNED NULL,
    lesson_id BIGINT UNSIGNED NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    resource_type ENUM('document','pdf','video','image','other') DEFAULT 'document',
    original_filename VARCHAR(500) NOT NULL,
    stored_filename VARCHAR(500) NOT NULL,
    mime_type VARCHAR(150),
    file_size BIGINT UNSIGNED DEFAULT 0,
    file_path VARCHAR(500) NOT NULL,
    conversion_status ENUM('none','pending','completed','failed') DEFAULT 'none',
    thumbnail_path VARCHAR(500) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE SET NULL,
    FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

try {
    $pdo->exec($sql);
    echo "✓ resources table created or already exists.\n";

    // Create upload directories
    $dirs = [
        dirname(__DIR__) . '/uploads',
        dirname(__DIR__) . '/uploads/documents',
        dirname(__DIR__) . '/uploads/videos',
        dirname(__DIR__) . '/uploads/images',
        dirname(__DIR__) . '/uploads/thumbnails',
    ];
    foreach ($dirs as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
            // Create .htaccess to prevent PHP execution
            file_put_contents($dir . '/.htaccess', "Options -Indexes\n<FilesMatch \"\\.php$\">\n  Deny from all\n</FilesMatch>\n");
            echo "✓ Created directory: $dir\n";
        } else {
            echo "✓ Directory exists: $dir\n";
        }
    }
    echo "\n✓ Migration completed successfully.\n";
} catch (PDOException $e) {
    echo "✗ Migration failed: " . $e->getMessage() . "\n";
}
