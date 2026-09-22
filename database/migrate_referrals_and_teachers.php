<?php

require_once dirname(__DIR__) . '/config/main.php';

$pdo = getDBConnection();
echo "Starting Referral & Teacher Schema Migration...\n";

$userCols = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
if (!in_array('referral_code', $userCols)) {
    $pdo->exec("ALTER TABLE users ADD COLUMN referral_code VARCHAR(50) NULL UNIQUE AFTER email");
    echo "✔ Added referral_code to users table\n";
}

$teacherCols = $pdo->query("SHOW COLUMNS FROM teachers")->fetchAll(PDO::FETCH_COLUMN);
if (!in_array('assigned_course_id', $teacherCols)) {
    $pdo->exec("ALTER TABLE teachers ADD COLUMN assigned_course_id BIGINT UNSIGNED NULL AFTER user_id");
    echo "✔ Added assigned_course_id to teachers table\n";
}
if (!in_array('assigned_category_id', $teacherCols)) {
    $pdo->exec("ALTER TABLE teachers ADD COLUMN assigned_category_id BIGINT UNSIGNED NULL AFTER assigned_course_id");
    echo "✔ Added assigned_category_id to teachers table\n";
}

$pdo->exec("
CREATE TABLE IF NOT EXISTS referrals (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    referrer_id BIGINT UNSIGNED NOT NULL,
    referrer_role ENUM('student','teacher','admin') NOT NULL DEFAULT 'student',
    referrer_category VARCHAR(100) NULL,
    referred_user_id BIGINT UNSIGNED NOT NULL,
    referral_code VARCHAR(50) NOT NULL,
    course_id BIGINT UNSIGNED NULL,
    course_category VARCHAR(100) NULL,
    payment_id BIGINT UNSIGNED NULL,
    payment_amount DECIMAL(12,2) DEFAULT 0.00,
    bonus_amount DECIMAL(12,2) DEFAULT 0.00,
    applied_rule VARCHAR(100) NOT NULL DEFAULT 'pending',
    status ENUM('pending','completed','cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (referrer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (referred_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE SET NULL,
    FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");
echo "✔ Verified referrals table\n";

$pdo->exec("
CREATE TABLE IF NOT EXISTS wallets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL UNIQUE,
    available_balance DECIMAL(12,2) DEFAULT 0.00,
    pending_balance DECIMAL(12,2) DEFAULT 0.00,
    total_earned DECIMAL(12,2) DEFAULT 0.00,
    total_withdrawn DECIMAL(12,2) DEFAULT 0.00,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");
echo "✔ Verified wallets table\n";

$pdo->exec("
CREATE TABLE IF NOT EXISTS withdrawals (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    bank_name VARCHAR(150) NOT NULL,
    account_number VARCHAR(50) NOT NULL,
    account_name VARCHAR(150) NOT NULL,
    status ENUM('pending','completed','rejected') DEFAULT 'pending',
    admin_notes TEXT NULL,
    processed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");
echo "✔ Verified withdrawals table\n";

$defaultBonusSettings = [
    'bonus_rate_teacher'    => '1000.00',
    'bonus_rate_university' => '1000.00',
    'bonus_rate_secondary'  => '1000.00',
    'bonus_rate_technology' => '1500.00',
];

$stmtSet = $pdo->prepare("INSERT INTO settings (setting_key, setting_value, setting_type) VALUES (?, ?, 'text') ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
foreach ($defaultBonusSettings as $key => $val) {
    $stmtSet->execute([$key, $val]);
}
echo "✔ Seeded configurable bonus rate settings\n";

$usersWithoutCode = $pdo->query("SELECT id, role, first_name FROM users WHERE referral_code IS NULL OR referral_code = ''")->fetchAll(PDO::FETCH_ASSOC);
$stmtUpdateCode = $pdo->prepare("UPDATE users SET referral_code = ? WHERE id = ?");

foreach ($usersWithoutCode as $u) {
    $prefix = strtoupper(substr($u['role'], 0, 3));
    $code = $prefix . '-' . strtoupper(substr(md5($u['id'] . $u['first_name'] . 'STUDYME'), 0, 6));
    $stmtUpdateCode->execute([$code, $u['id']]);

    $pdo->prepare("INSERT IGNORE INTO wallets (user_id, available_balance, pending_balance, total_earned, total_withdrawn) VALUES (?, 0, 0, 0, 0)")
        ->execute([$u['id']]);
}
echo "✔ Generated referral codes & wallets for " . count($usersWithoutCode) . " existing users\n";

echo "Referral & Teacher Schema Migration completed successfully!\n";
