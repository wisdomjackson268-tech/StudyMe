<?php
/**
 * StudyMe AI Platform — Secondary Subjects & Past Questions Database Migration
 */
require_once dirname(__DIR__) . '/config/main.php';

$pdo = getDBConnection();
echo "Migrating Secondary Subjects & Past Questions Schema...\n";

// 1. Create past_questions table if not exists
$pdo->exec("
    CREATE TABLE IF NOT EXISTS past_questions (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        exam_type ENUM('waec','neco','jamb','general') NOT NULL DEFAULT 'waec',
        year INT UNSIGNED NOT NULL,
        subject_name VARCHAR(150) NOT NULL,
        subject_slug VARCHAR(150) NOT NULL,
        question_number INT UNSIGNED NOT NULL DEFAULT 1,
        question_text LONGTEXT NOT NULL,
        option_a TEXT NOT NULL,
        option_b TEXT NOT NULL,
        option_c TEXT NOT NULL,
        option_d TEXT NOT NULL,
        correct_option CHAR(1) NOT NULL,
        explanation LONGTEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_exam_year_subj (exam_type, year, subject_slug)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");
echo "✔ past_questions table created/verified\n";

// 2. Ensure Secondary Category ID
$stmtCat = $pdo->prepare("SELECT id FROM categories WHERE slug = 'secondary-waec-neco' LIMIT 1");
$stmtCat->execute();
$secCatId = (int)$stmtCat->fetchColumn();
if (!$secCatId) {
    $pdo->prepare("INSERT INTO categories (name, slug, description, image, status) VALUES ('Secondary / WAEC / NECO', 'secondary-waec-neco', 'High school curriculum, WAEC, NECO & JAMB prep.', 'bi-book-half', 'active')")->execute();
    $secCatId = (int)$pdo->lastInsertId();
}

$stmtT = $pdo->query("SELECT id FROM teachers LIMIT 1");
$teacherId = (int)$stmtT->fetchColumn() ?: 1;

// 3. Add complete secondary subjects (Price ₦3,000 each)
$subjects = [
    ['Mathematics (WAEC/NECO/JAMB)', 'secondary-mathematics', 'Algebra, Geometry, Trigonometry, Statistics, Probability, and Calculus with step-by-step past questions.', '3000.00'],
    ['English Language & Oral English', 'secondary-english', 'Grammar, essay writing, summary techniques, comprehension passages, and phonetics.', '3000.00'],
    ['Physics (SSCE / WAEC / NECO)', 'secondary-physics', 'Mechanics, Waves, Sound, Optics, Electricity, Magnetism, and Nuclear Physics.', '3000.00'],
    ['Chemistry (SSCE / WAEC / NECO)', 'secondary-chemistry', 'Periodic table, chemical equations, stoichiometry, organic chemistry, and volumetric analysis.', '3000.00'],
    ['Biology (SSCE / WAEC / NECO)', 'secondary-biology', 'Cell biology, plant & animal physiology, genetics, ecology, and specimen drawing.', '3000.00'],
    ['Economics (WAEC / NECO / JAMB)', 'secondary-economics', 'Demand and supply, market structures, national income, banking, and international trade.', '3000.00'],
    ['Government & Politics', 'secondary-government', 'Political concepts, constitutions, organs of government, Nigerian political development.', '3000.00'],
    ['Literature in English', 'secondary-literature', 'Analysis of prose, drama, poetry, literary devices, characterization, and critique essays.', '3000.00'],
    ['Geography & Map Reading', 'secondary-geography', 'Physical geography, map interpretation, contours, weather systems, and regional geography.', '3000.00'],
    ['Civic Education & Social Studies', 'secondary-civic-education', 'National values, democratic institutions, rule of law, human rights, and citizenship duties.', '3000.00'],
    ['Computer Studies & ICT', 'secondary-computer-studies', 'Computer architecture, logic gates, basic coding, data processing, and network safety.', '3000.00'],
    ['Agricultural Science', 'secondary-agric-science', 'Soil science, crop production, animal husbandry, agricultural economics, and farm tools.', '3000.00'],
    ['Further Mathematics', 'secondary-further-maths', 'Advanced algebra, vectors, matrices, calculus, mechanics, and coordinate geometry.', '3000.00'],
    ['Commerce & Business Methods', 'secondary-commerce', 'Trade, banking, insurance, transportation, advertising, warehousing, and e-commerce.', '3000.00'],
    ['Financial Accounting (SSCE)', 'secondary-financial-accounting', 'Double-entry bookkeeping, trial balance, final accounts, bank reconciliation, and partnership accounts.', '3000.00'],
    ['History of West Africa', 'secondary-history', 'Pre-colonial empires, transatlantic trade, colonial administration, and Nigerian nationalist movements.', '3000.00'],
    ['Christian Religious Studies (CRS)', 'secondary-crs', 'Old and New Testament themes, ethical teachings, gospel messages, and moral guidance.', '3000.00'],
    ['Islamic Religious Studies (IRS)', 'secondary-irs', 'Quranic studies, Hadith analysis, Tawhid, Fiqh, Islamic history, and moral ethics.', '3000.00'],
    ['Technical Drawing & Graphics', 'secondary-technical-drawing', 'Geometric construction, orthographic projection, isometric views, sectional elevations, and architectural drafting.', '3000.00']
];

foreach ($subjects as $sb) {
    $stmt = $pdo->prepare("
        INSERT INTO courses (teacher_id, category_id, title, slug, short_description, description, level, price, duration_minutes, thumbnail, status, featured, certificate_enabled, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, 'beginner', ?, 1800, 'https://images.unsplash.com/photo-1456513080510-7bf3a84b82f8?w=600&q=80', 'published', 1, 1, NOW(), NOW())
        ON DUPLICATE KEY UPDATE 
            category_id = VALUES(category_id),
            title = VALUES(title),
            short_description = VALUES(short_description),
            price = VALUES(price),
            status = 'published'
    ");
    $stmt->execute([$teacherId, $secCatId, $sb[0], $sb[1], $sb[2], $sb[2], $sb[3]]);
}
echo "✔ " . count($subjects) . " Secondary Subjects synchronized in database (₦3,000 each)\n";

// 4. Seed Rich Sample Past Questions for WAEC, NECO, JAMB
$pdo->exec("TRUNCATE TABLE past_questions;");

$pqData = [
    // WAEC 2024 Mathematics
    [
        'exam_type' => 'waec', 'year' => 2024, 'subject_name' => 'Mathematics', 'subject_slug' => 'mathematics',
        'question_number' => 1,
        'question_text' => 'If $2^{x+3} = 32$, find the value of $x$.',
        'option_a' => '1', 'option_b' => '2', 'option_c' => '3', 'option_d' => '5',
        'correct_option' => 'B',
        'explanation' => 'Rewrite 32 as a power of 2: $32 = 2^5$. Therefore, $2^{x+3} = 2^5 \implies x + 3 = 5 \implies x = 2$.'
    ],
    [
        'exam_type' => 'waec', 'year' => 2024, 'subject_name' => 'Mathematics', 'subject_slug' => 'mathematics',
        'question_number' => 2,
        'question_text' => 'Simplify $\sqrt{75} - \sqrt{12} + \sqrt{27}$.',
        'option_a' => '$4\sqrt{3}$', 'option_b' => '$6\sqrt{3}$', 'option_c' => '$5\sqrt{3}$', 'option_d' => '$7\sqrt{3}$',
        'correct_option' => 'B',
        'explanation' => '$\sqrt{75} = 5\sqrt{3}$, $\sqrt{12} = 2\sqrt{3}$, $\sqrt{27} = 3\sqrt{3}$. Result: $5\sqrt{3} - 2\sqrt{3} + 3\sqrt{3} = 6\sqrt{3}$.'
    ],
    [
        'exam_type' => 'waec', 'year' => 2024, 'subject_name' => 'Mathematics', 'subject_slug' => 'mathematics',
        'question_number' => 3,
        'question_text' => 'Solve the quadratic equation: $2x^2 - 5x - 3 = 0$.',
        'option_a' => '$x = 3$ or $x = -\frac{1}{2}$', 'option_b' => '$x = -3$ or $x = \frac{1}{2}$', 'option_c' => '$x = 2$ or $x = -3$', 'option_d' => '$x = 1$ or $x = -\frac{3}{2}$',
        'correct_option' => 'A',
        'explanation' => 'Factoring: $(2x + 1)(x - 3) = 0 \implies x = 3$ or $x = -\frac{1}{2}$.'
    ],

    // WAEC 2024 English Language
    [
        'exam_type' => 'waec', 'year' => 2024, 'subject_name' => 'English Language', 'subject_slug' => 'english',
        'question_number' => 1,
        'question_text' => 'Choose the option nearest in meaning to the italicized word: "The minister gave an *ambiguous* answer to the press."',
        'option_a' => 'Clear', 'option_b' => 'Uncertain or unclear', 'option_c' => 'Detailed', 'option_d' => 'Hostile',
        'correct_option' => 'B',
        'explanation' => '"Ambiguous" means open to more than one interpretation; having a double meaning or unclear.'
    ],
    [
        'exam_type' => 'waec', 'year' => 2024, 'subject_name' => 'English Language', 'subject_slug' => 'english',
        'question_number' => 2,
        'question_text' => 'From the options, select the word with the same vowel sound as in "CUP":',
        'option_a' => 'Cot', 'option_b' => 'Love', 'option_c' => 'Soup', 'option_d' => 'Put',
        'correct_option' => 'B',
        'explanation' => 'Both "cup" and "love" share the short central vowel sound /ʌ/.'
    ],

    // WAEC 2024 Physics
    [
        'exam_type' => 'waec', 'year' => 2024, 'subject_name' => 'Physics', 'subject_slug' => 'physics',
        'question_number' => 1,
        'question_text' => 'A car accelerates uniformly from rest to a speed of 20 m/s in 5 seconds. What is its acceleration?',
        'option_a' => '2 m/s²', 'option_b' => '4 m/s²', 'option_c' => '5 m/s²', 'option_d' => '100 m/s²',
        'correct_option' => 'B',
        'explanation' => '$a = \frac{v - u}{t} = \frac{20 - 0}{5} = 4\text{ m/s}^2$.'
    ],

    // JAMB 2024 Mathematics
    [
        'exam_type' => 'jamb', 'year' => 2024, 'subject_name' => 'Mathematics', 'subject_slug' => 'mathematics',
        'question_number' => 1,
        'question_text' => 'Find the derivative of $y = 3x^4 - 5x^2 + 7x - 2$ with respect to $x$.',
        'option_a' => '$12x^3 - 10x + 7$', 'option_b' => '$12x^4 - 10x^2 + 7$', 'option_c' => '$7x^3 - 5x + 3$', 'option_d' => '$12x^3 - 5x + 7$',
        'correct_option' => 'A',
        'explanation' => 'Using power rule $\frac{d}{dx}(ax^n) = anx^{n-1}$: $\frac{dy}{dx} = 12x^3 - 10x + 7$.'
    ],
    [
        'exam_type' => 'jamb', 'year' => 2024, 'subject_name' => 'Physics', 'subject_slug' => 'physics',
        'question_number' => 1,
        'question_text' => 'Which of the following is NOT a fundamental quantity in the SI system?',
        'option_a' => 'Mass', 'option_b' => 'Electric Current', 'option_c' => 'Electric Charge', 'option_d' => 'Thermodynamic Temperature',
        'correct_option' => 'C',
        'explanation' => 'Electric current (Ampere) is the fundamental quantity, while electric charge (Coulomb = Ampere × second) is a derived quantity.'
    ],

    // NECO 2024 Chemistry
    [
        'exam_type' => 'neco', 'year' => 2024, 'subject_name' => 'Chemistry', 'subject_slug' => 'chemistry',
        'question_number' => 1,
        'question_text' => 'What is the oxidation number of sulfur in $H_2SO_4$?',
        'option_a' => '+2', 'option_b' => '+4', 'option_c' => '+6', 'option_d' => '-2',
        'correct_option' => 'C',
        'explanation' => '$2(+1) + S + 4(-2) = 0 \implies 2 + S - 8 = 0 \implies S = +6$.'
    ],
    [
        'exam_type' => 'neco', 'year' => 2024, 'subject_name' => 'Biology', 'subject_slug' => 'biology',
        'question_number' => 1,
        'question_text' => 'Which cell organelle is responsible for cellular respiration and ATP generation?',
        'option_a' => 'Ribosome', 'option_b' => 'Mitochondrion', 'option_c' => 'Golgi Apparatus', 'option_d' => 'Nucleolus',
        'correct_option' => 'B',
        'explanation' => 'The mitochondrion is known as the powerhouse of the cell where ATP is produced during aerobic respiration.'
    ]
];

$stmtPQ = $pdo->prepare("
    INSERT INTO past_questions (exam_type, year, subject_name, subject_slug, question_number, question_text, option_a, option_b, option_c, option_d, correct_option, explanation)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

foreach ($pqData as $pq) {
    $stmtPQ->execute([
        $pq['exam_type'], $pq['year'], $pq['subject_name'], $pq['subject_slug'],
        $pq['question_number'], $pq['question_text'], $pq['option_a'], $pq['option_b'],
        $pq['option_c'], $pq['option_d'], $pq['correct_option'], $pq['explanation']
    ]);
}
echo "✔ " . count($pqData) . " Sample Past Questions loaded across WAEC, NECO & JAMB\n";
echo "Secondary subjects & Past Questions migration complete!\n";
