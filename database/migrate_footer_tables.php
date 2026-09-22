<?php

require_once dirname(__DIR__) . '/config/database.php';

$pdo = getDBConnection();

try {

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS contact_messages (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(150) NOT NULL,
            email VARCHAR(191) NOT NULL,
            subject VARCHAR(255) NOT NULL,
            message LONGTEXT NOT NULL,
            status ENUM('unread','read','replied') DEFAULT 'unread',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "✓ contact_messages table ready.\n";

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS blog_categories (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(150) NOT NULL UNIQUE,
            slug VARCHAR(180) NOT NULL UNIQUE,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS blog_posts (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            author_id BIGINT UNSIGNED NOT NULL,
            category_id BIGINT UNSIGNED NULL,
            title VARCHAR(255) NOT NULL,
            slug VARCHAR(280) NOT NULL UNIQUE,
            excerpt TEXT,
            content LONGTEXT NOT NULL,
            featured_image VARCHAR(255),
            status ENUM('draft','published') DEFAULT 'draft',
            published_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (category_id) REFERENCES blog_categories(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "✓ blog tables ready.\n";

    $catCount = (int)$pdo->query("SELECT COUNT(*) FROM blog_categories")->fetchColumn();
    if ($catCount === 0) {
        $pdo->exec("
            INSERT INTO blog_categories (name, slug, description) VALUES
            ('AI Education', 'ai-education', 'Insights into artificial intelligence in modern personalized learning'),
            ('Study Techniques', 'study-techniques', 'Evidence-based cognitive study strategies and time management'),
            ('Tech & Career', 'tech-career', 'Software engineering, data science, and career advancement guides'),
            ('Platform Updates', 'platform-updates', 'New features, release notes, and community announcements')
        ");
        echo "✓ blog categories seeded.\n";
    }

    $postCount = (int)$pdo->query("SELECT COUNT(*) FROM blog_posts")->fetchColumn();
    if ($postCount === 0) {
        $adminId = (int)$pdo->query("SELECT id FROM users WHERE role = 'admin' LIMIT 1")->fetchColumn();
        if (!$adminId) {
            $adminId = (int)$pdo->query("SELECT id FROM users LIMIT 1")->fetchColumn() ?: 1;
        }

        $cats = $pdo->query("SELECT id, slug FROM blog_categories")->fetchAll(PDO::FETCH_KEY_PAIR);
        $aiCat = $cats['ai-education'] ?? 1;
        $studyCat = $cats['study-techniques'] ?? 1;
        $techCat = $cats['tech-career'] ?? 1;

        $stmt = $pdo->prepare("
            INSERT INTO blog_posts (author_id, category_id, title, slug, excerpt, content, featured_image, status, published_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'published', NOW())
        ");

        $stmt->execute([
            $adminId,
            $aiCat,
            'How AI Tutors Transform Mastery Learning',
            'how-ai-tutors-transform-mastery-learning',
            'Explore how personalized, round-the-clock AI guidance helps students overcome difficult programming concepts and retain knowledge 2x faster.',
            '<p>Mastery learning is an educational philosophy where students must achieve a level of prerequisite knowledge before moving forward to subsequent information. Historically, 1-on-1 human tutoring was the only reliable way to implement true mastery learning at scale.</p><p>With <strong>StudyMe AI Tutors</strong>, students receive real-time, context-aware breakdowns of complex concepts. The AI does not simply give away answers; rather, it prompts the learner with guided Socratic questioning, breaks code into logical steps, and generates targeted drill questions until the fundamental concept is mastered.</p><h3>Key Benefits</h3><ul><li>24/7 Availability with zero judgment</li><li>Instant feedback on homework problems</li><li>Custom analogies tailored to your background</li></ul>',
            'https://images.unsplash.com/photo-1618005182384-a83a8bd57fbe?w=800&q=80'
        ]);

        $stmt->execute([
            $adminId,
            $studyCat,
            'Spaced Repetition & Active Recall: The Ultimate Study Stack',
            'spaced-repetition-active-recall-study-stack',
            'Discover the scientifically backed cognitive techniques that make exam preparation effortless and long-term memory retention durable.',
            '<p>Rereading lecture notes and highlighting textbooks are among the least effective study strategies according to cognitive science. Instead, the combination of <strong>Active Recall</strong> (testing yourself on concepts without looking) and <strong>Spaced Repetition</strong> (reviewing material at increasing intervals) delivers superior memory retention.</p><p>On StudyMe, every course module incorporates interactive quizzes and quick conceptual check-ins that trigger active retrieval pathways in the brain.</p>',
            'https://images.unsplash.com/photo-1434030216411-0b793f4b4173?w=800&q=80'
        ]);

        $stmt->execute([
            $adminId,
            $techCat,
            'From Beginner to Full-Stack Developer in 2026',
            'from-beginner-to-full-stack-developer-2026',
            'A practical, step-by-step roadmap to mastering frontend, backend, databases, and AI integration for high-growth tech careers.',
            '<p>The software engineering landscape is rapidly evolving. Modern developers need a strong foundation in core programming principles, modern frameworks, database architecture, and AI-assisted workflows.</p><p>StudyMe courses are designed by industry professionals with hands-on projects, real-world source code repositories, and verifiable certificates.</p>',
            'https://images.unsplash.com/photo-1498050108023-c5249f4df085?w=800&q=80'
        ]);

        echo "✓ blog posts seeded.\n";
    }

    echo "✓ All footer migrations completed successfully.\n";
} catch (Exception $e) {
    echo "✗ Migration failed: " . $e->getMessage() . "\n";
}
