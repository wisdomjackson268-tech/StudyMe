<?php
/**
 * StudyMe — Migration for Rich Activity Logs & Official Pricing Settings
 */
require_once dirname(__DIR__) . '/config/database.php';

$pdo = getDBConnection();

try {
    // 1. Create or update activity_logs table with detailed fields
    $sqlActivity = "
    CREATE TABLE IF NOT EXISTS activity_logs (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT UNSIGNED NOT NULL,
        course_id BIGINT UNSIGNED NULL,
        lesson_id BIGINT UNSIGNED NULL,
        quiz_id BIGINT UNSIGNED NULL,
        task_id BIGINT UNSIGNED NULL,
        action VARCHAR(100) NOT NULL,
        description TEXT,
        ip_address VARCHAR(45) NULL,
        user_agent TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_date (user_id, created_at),
        INDEX idx_user_action (user_id, action),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    $pdo->exec($sqlActivity);
    echo "✓ activity_logs table initialized.\n";

    // 2. Ensure settings table exists and seed default official prices
    $sqlSettings = "
    CREATE TABLE IF NOT EXISTS settings (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(191) NOT NULL UNIQUE,
        setting_value LONGTEXT,
        setting_type VARCHAR(50) DEFAULT 'text',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    $pdo->exec($sqlSettings);
    echo "✓ settings table initialized.\n";

    // Default prices
    $defaultPrices = [
        'price_tech'       => '10000.00',
        'price_university' => '4000.00',
        'price_secondary'  => '3000.00',
        'price_teacher'    => '5000.00',
    ];

    $stmtPrice = $pdo->prepare("
        INSERT INTO settings (setting_key, setting_value, setting_type)
        VALUES (?, ?, 'text')
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
    ");

    foreach ($defaultPrices as $key => $val) {
        $stmtPrice->execute([$key, $val]);
    }
    echo "✓ Official pricing settings initialized in database.\n";

} catch (PDOException $e) {
    echo "✗ Migration error: " . $e->getMessage() . "\n";
}
