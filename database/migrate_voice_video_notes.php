<?php
/**
 * Migration script: Create voice_video_notes table
 */
require_once __DIR__ . '/../config/database.php';

try {
    $db = getDBConnection();
    echo "Connected to database successfully.\n";

    $sql = "CREATE TABLE IF NOT EXISTS `voice_video_notes` (
        `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `teacher_id` BIGINT UNSIGNED NOT NULL,
        `course_id` BIGINT UNSIGNED NOT NULL,
        `lesson_id` BIGINT UNSIGNED NULL DEFAULT NULL,
        `title` VARCHAR(255) NOT NULL,
        `description` TEXT NULL,
        `media_type` ENUM('voice', 'video') NOT NULL,
        `file_path` VARCHAR(500) NOT NULL,
        `file_size` BIGINT UNSIGNED NOT NULL DEFAULT 0,
        `mime_type` VARCHAR(100) NOT NULL,
        `duration_seconds` INT UNSIGNED NOT NULL DEFAULT 0,
        `status` ENUM('draft', 'published', 'archived') NOT NULL DEFAULT 'published',
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX `idx_vvn_teacher` (`teacher_id`),
        INDEX `idx_vvn_course` (`course_id`),
        INDEX `idx_vvn_lesson` (`lesson_id`),
        INDEX `idx_vvn_status` (`status`),
        INDEX `idx_vvn_media_type` (`media_type`),
        CONSTRAINT `fk_vvn_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`) ON DELETE CASCADE,
        CONSTRAINT `fk_vvn_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
        CONSTRAINT `fk_vvn_lesson` FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    $db->exec($sql);
    echo "Table `voice_video_notes` created or already exists.\n";

} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
