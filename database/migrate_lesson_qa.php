<?php
/**
 * Migration: Create lesson_questions and lesson_question_replies tables
 */
require_once dirname(__DIR__) . '/config/main.php';

$pdo = getDBConnection();

echo "Running Lesson Q&A migration...\n";

$sql = "
CREATE TABLE IF NOT EXISTS lesson_questions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    lesson_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(255) NULL,
    question TEXT NOT NULL,
    status ENUM('open', 'answered', 'closed') DEFAULT 'open',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_lesson (lesson_id),
    INDEX idx_user (user_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lesson_question_replies (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    question_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    reply TEXT NOT NULL,
    is_instructor_reply BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (question_id) REFERENCES lesson_questions(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_question (question_id),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

$pdo->exec($sql);

echo "✔ Successfully created lesson_questions and lesson_question_replies tables.\n";
