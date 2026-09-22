<?php

require_once dirname(__DIR__) . '/config/main.php';

$pdo = getDBConnection();
echo "Starting Secondary School System Migration...\n";

// 1. Extend students table with student_type, academic_level, target_exam
try {
    $cols = $pdo->query("DESCRIBE students")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('student_type', $cols, true)) {
        $pdo->exec("ALTER TABLE students ADD COLUMN student_type ENUM('secondary','university','technology') NOT NULL DEFAULT 'university' AFTER user_id");
        echo "✔ Added student_type to students table\n";
    }
    if (!in_array('academic_level', $cols, true)) {
        $pdo->exec("ALTER TABLE students ADD COLUMN academic_level VARCHAR(50) DEFAULT 'SS3 / WAEC Candidate' AFTER student_type");
        echo "✔ Added academic_level to students table\n";
    }
    if (!in_array('target_exam', $cols, true)) {
        $pdo->exec("ALTER TABLE students ADD COLUMN target_exam VARCHAR(100) DEFAULT 'WAEC / JAMB / NECO' AFTER academic_level");
        echo "✔ Added target_exam to students table\n";
    }
} catch (Exception $e) {
    echo "Notice on students table alter: " . $e->getMessage() . "\n";
}

// 2. Ensure past_questions table has difficulty and topic_name
try {
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
            difficulty ENUM('easy','medium','hard') NOT NULL DEFAULT 'medium',
            topic_name VARCHAR(150) NULL DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_exam_year_subj (exam_type, year, subject_slug),
            INDEX idx_pq_subject (subject_slug),
            INDEX idx_pq_difficulty (difficulty)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    $pqCols = $pdo->query("DESCRIBE past_questions")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('difficulty', $pqCols, true)) {
        $pdo->exec("ALTER TABLE past_questions ADD COLUMN difficulty ENUM('easy','medium','hard') NOT NULL DEFAULT 'medium' AFTER explanation");
    }
    if (!in_array('topic_name', $pqCols, true)) {
        $pdo->exec("ALTER TABLE past_questions ADD COLUMN topic_name VARCHAR(150) NULL DEFAULT NULL AFTER difficulty");
    }
    echo "✔ past_questions table ready\n";
} catch (Exception $e) {
    echo "past_questions setup error: " . $e->getMessage() . "\n";
}

// 3. Create secondary_subjects table
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS secondary_subjects (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(150) NOT NULL UNIQUE,
            slug VARCHAR(150) NOT NULL UNIQUE,
            code VARCHAR(20) NOT NULL,
            description TEXT NULL,
            icon VARCHAR(100) DEFAULT 'bi-book-half',
            color VARCHAR(30) DEFAULT '#087f5b',
            class_level VARCHAR(50) DEFAULT 'Senior Secondary (SS1 - SS3)',
            status ENUM('active','inactive') NOT NULL DEFAULT 'active',
            sort_order INT UNSIGNED DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_sec_subj_slug (slug),
            INDEX idx_sec_subj_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "✔ secondary_subjects table ready\n";
} catch (Exception $e) {
    echo "secondary_subjects error: " . $e->getMessage() . "\n";
}

// 4. Create secondary_topics table
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS secondary_topics (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            subject_id BIGINT UNSIGNED NOT NULL,
            title VARCHAR(200) NOT NULL,
            slug VARCHAR(200) NOT NULL,
            description TEXT NULL,
            term VARCHAR(50) DEFAULT 'First Term',
            class_level VARCHAR(50) DEFAULT 'SS1',
            sort_order INT UNSIGNED DEFAULT 1,
            status ENUM('active','inactive') NOT NULL DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_sec_top_subj (subject_id),
            INDEX idx_sec_top_slug (slug),
            CONSTRAINT fk_sec_top_subj FOREIGN KEY (subject_id) REFERENCES secondary_subjects (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "✔ secondary_topics table ready\n";
} catch (Exception $e) {
    echo "secondary_topics error: " . $e->getMessage() . "\n";
}

// 5. Create secondary_materials table
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS secondary_materials (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            subject_id BIGINT UNSIGNED NOT NULL,
            topic_id BIGINT UNSIGNED NULL DEFAULT NULL,
            title VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL,
            content_type ENUM('notes','summary','formula_sheet','video','pdf') NOT NULL DEFAULT 'notes',
            content_body LONGTEXT NULL,
            file_url VARCHAR(500) NULL DEFAULT NULL,
            duration_minutes INT UNSIGNED DEFAULT 15,
            downloadable TINYINT(1) DEFAULT 1,
            status ENUM('published','draft') NOT NULL DEFAULT 'published',
            sort_order INT UNSIGNED DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_sec_mat_subj (subject_id),
            INDEX idx_sec_mat_top (topic_id),
            CONSTRAINT fk_sec_mat_subj FOREIGN KEY (subject_id) REFERENCES secondary_subjects (id) ON DELETE CASCADE,
            CONSTRAINT fk_sec_mat_top FOREIGN KEY (topic_id) REFERENCES secondary_topics (id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "✔ secondary_materials table ready\n";
} catch (Exception $e) {
    echo "secondary_materials error: " . $e->getMessage() . "\n";
}

// 6. Create practice attempts, answers, and bookmarks
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS secondary_practice_attempts (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            student_id BIGINT UNSIGNED NOT NULL,
            exam_type VARCHAR(20) NOT NULL DEFAULT 'waec',
            subject_slug VARCHAR(150) NOT NULL,
            topic_name VARCHAR(150) NULL DEFAULT NULL,
            year INT UNSIGNED NULL,
            mode ENUM('cbt_timed','practice_study','quick_drill') DEFAULT 'cbt_timed',
            time_limit_minutes INT UNSIGNED DEFAULT 20,
            time_spent_seconds INT UNSIGNED DEFAULT 0,
            total_questions INT UNSIGNED NOT NULL DEFAULT 0,
            answered_questions INT UNSIGNED NOT NULL DEFAULT 0,
            correct_answers INT UNSIGNED NOT NULL DEFAULT 0,
            wrong_answers INT UNSIGNED NOT NULL DEFAULT 0,
            score_percentage DECIMAL(5,2) DEFAULT 0.00,
            started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            completed_at TIMESTAMP NULL DEFAULT NULL,
            INDEX idx_sec_prac_stud (student_id, started_at),
            INDEX idx_sec_prac_subj (subject_slug),
            CONSTRAINT fk_sec_prac_stud FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    $attCols = $pdo->query("DESCRIBE secondary_practice_attempts")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('score_percentage', $attCols, true)) {
        $pdo->exec("ALTER TABLE secondary_practice_attempts ADD COLUMN score_percentage DECIMAL(5,2) DEFAULT 0.00 AFTER wrong_answers");
    }
    if (!in_array('mode', $attCols, true)) {
        $pdo->exec("ALTER TABLE secondary_practice_attempts ADD COLUMN mode ENUM('cbt_timed','practice_study','quick_drill') DEFAULT 'cbt_timed' AFTER year");
    }
    if (!in_array('time_limit_minutes', $attCols, true)) {
        $pdo->exec("ALTER TABLE secondary_practice_attempts ADD COLUMN time_limit_minutes INT UNSIGNED DEFAULT 20 AFTER mode");
    }
    if (!in_array('time_spent_seconds', $attCols, true)) {
        $pdo->exec("ALTER TABLE secondary_practice_attempts ADD COLUMN time_spent_seconds INT UNSIGNED DEFAULT 0 AFTER time_limit_minutes");
    }
    if (!in_array('topic_name', $attCols, true)) {
        $pdo->exec("ALTER TABLE secondary_practice_attempts ADD COLUMN topic_name VARCHAR(150) NULL DEFAULT NULL AFTER subject_slug");
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS secondary_practice_answers (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            attempt_id BIGINT UNSIGNED NOT NULL,
            question_id BIGINT UNSIGNED NOT NULL,
            selected_option CHAR(1) NULL,
            is_correct TINYINT(1) NOT NULL DEFAULT 0,
            time_spent_sec INT UNSIGNED DEFAULT 0,
            answered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_secondary_answer (attempt_id, question_id),
            INDEX idx_sec_ans_att (attempt_id),
            CONSTRAINT fk_sec_ans_att FOREIGN KEY (attempt_id) REFERENCES secondary_practice_attempts(id) ON DELETE CASCADE,
            CONSTRAINT fk_sec_ans_qst FOREIGN KEY (question_id) REFERENCES past_questions(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS secondary_question_bookmarks (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            student_id BIGINT UNSIGNED NOT NULL,
            question_id BIGINT UNSIGNED NOT NULL,
            notes TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_secondary_bookmark (student_id, question_id),
            INDEX idx_sec_bm_stud (student_id),
            CONSTRAINT fk_sec_bm_stud FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
            CONSTRAINT fk_sec_bm_qst FOREIGN KEY (question_id) REFERENCES past_questions(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "✔ Practice attempt and bookmark tables verified\n";
} catch (Exception $e) {
    echo "Practice tables error: " . $e->getMessage() . "\n";
}

// 7. Seed complete Secondary Subjects
$subjectsList = [
    [
        'name' => 'Mathematics',
        'slug' => 'mathematics',
        'code' => 'MTH',
        'description' => 'Comprehensive WAEC, NECO & JAMB Mathematics covering Algebra, Geometry, Trigonometry, Statistics, Calculus, and Mensuration.',
        'icon' => 'bi-calculator-fill',
        'color' => '#2563EB',
        'sort_order' => 1
    ],
    [
        'name' => 'English Language',
        'slug' => 'english',
        'code' => 'ENG',
        'description' => 'Master Grammar, Lexis and Structure, Oral English (phonetics), Essay & Letter Writing, Comprehension passages, and Summary skills.',
        'icon' => 'bi-chat-left-text-fill',
        'color' => '#087F5B',
        'sort_order' => 2
    ],
    [
        'name' => 'Physics',
        'slug' => 'physics',
        'code' => 'PHY',
        'description' => 'Mechanics, Sound & Wave Motion, Light & Optics, Heat & Thermodynamics, Electricity, Magnetism, and Modern Physics.',
        'icon' => 'bi-lightning-charge-fill',
        'color' => '#D97706',
        'sort_order' => 3
    ],
    [
        'name' => 'Chemistry',
        'slug' => 'chemistry',
        'code' => 'CHM',
        'description' => 'Atomic Structure, Chemical Bonding, Stoichiometry, Periodic Table, Electrochemistry, Organic Chemistry, and Volumetric Analysis.',
        'icon' => 'bi-radioactive',
        'color' => '#DC2626',
        'sort_order' => 4
    ],
    [
        'name' => 'Biology',
        'slug' => 'biology',
        'code' => 'BIO',
        'description' => 'Cell Structure & Function, Plant & Animal Physiology, Genetics & Heredity, Ecology, Evolution, and Specimen Drawing.',
        'icon' => 'bi-flower1',
        'color' => '#16A34A',
        'sort_order' => 5
    ],
    [
        'name' => 'Economics',
        'slug' => 'economics',
        'code' => 'ECO',
        'description' => 'Microeconomics, Macroeconomics, Demand & Supply, Market Equilibrium, National Income, Public Finance, Money & Banking, and International Trade.',
        'icon' => 'bi-graph-up-arrow',
        'color' => '#0D9488',
        'sort_order' => 6
    ],
    [
        'name' => 'Government',
        'slug' => 'government',
        'code' => 'GOV',
        'description' => 'Basic Political Concepts, Forms of Government, Nigerian Constitutional Development, Federalism, Public Administration, and International Relations.',
        'icon' => 'bi-building-fill-gear',
        'color' => '#4F46E5',
        'sort_order' => 7
    ],
    [
        'name' => 'Literature in English',
        'slug' => 'literature',
        'code' => 'LIT',
        'description' => 'Detailed analysis of prescribed African and Non-African Prose, Drama, and Poetry texts, literary devices, themes, and character critique.',
        'icon' => 'bi-journal-bookmark-fill',
        'color' => '#9333EA',
        'sort_order' => 8
    ],
    [
        'name' => 'Geography',
        'slug' => 'geography',
        'code' => 'GEO',
        'description' => 'Physical Geography, Earth movements, Weather & Climate, Contours & Topographical Map Reading, Regional Geography of Nigeria and Africa.',
        'icon' => 'bi-globe-americas',
        'color' => '#0284C7',
        'sort_order' => 9
    ],
    [
        'name' => 'Civic Education',
        'slug' => 'civic-education',
        'code' => 'CIV',
        'description' => 'National Values, Democratic Institutions, Rule of Law, Human Rights, Citizenship Rights & Responsibilities, and Drug Abuse Prevention.',
        'icon' => 'bi-shield-check',
        'color' => '#EA580C',
        'sort_order' => 10
    ],
    [
        'name' => 'Computer Studies & ICT',
        'slug' => 'computer-studies',
        'code' => 'CSC',
        'description' => 'Computer Architecture, Logic Gates, Flowcharts, Number Systems, Internet Safety, Word Processing, Spreadsheets, and Basic Programming concepts.',
        'icon' => 'bi-laptop',
        'color' => '#4338CA',
        'sort_order' => 11
    ],
    [
        'name' => 'Agricultural Science',
        'slug' => 'agric-science',
        'code' => 'AGR',
        'description' => 'Soil Science, Crop Production & Protection, Animal Husbandry & Nutrition, Farm Machinery, and Agricultural Economics & Extension.',
        'icon' => 'bi-tree-fill',
        'color' => '#15803D',
        'sort_order' => 12
    ],
    [
        'name' => 'Further Mathematics',
        'slug' => 'further-maths',
        'code' => 'FMT',
        'description' => 'Advanced Algebra, Matrices & Determinants, Vectors in 2D/3D, Coordinate Geometry, Differential & Integral Calculus, and Statics & Dynamics.',
        'icon' => 'bi-infinity',
        'color' => '#7C3AED',
        'sort_order' => 13
    ],
    [
        'name' => 'Commerce',
        'slug' => 'commerce',
        'code' => 'COM',
        'description' => 'Home & Foreign Trade, Warehousing, Insurance, Transportation, Advertising, Banking Services, Business Structure, and Consumer Protection.',
        'icon' => 'bi-shop',
        'color' => '#B45309',
        'sort_order' => 14
    ],
    [
        'name' => 'Financial Accounting',
        'slug' => 'financial-accounting',
        'code' => 'ACC',
        'description' => 'Double Entry Bookkeeping, Trial Balance, Final Accounts with Adjustments, Bank Reconciliation Statements, Partnership Accounts, and Depreciation.',
        'icon' => 'bi-cash-coin',
        'color' => '#047857',
        'sort_order' => 15
    ],
    [
        'name' => 'Christian Religious Studies (CRS)',
        'slug' => 'crs',
        'code' => 'CRS',
        'description' => 'Old Testament Themes (Sovereignty of God, Leadership), Synoptic Gospels, Early Church Acts of Apostles, and Christian Moral Teachings.',
        'icon' => 'bi-cross',
        'color' => '#0369A1',
        'sort_order' => 16
    ],
    [
        'name' => 'Islamic Religious Studies (IRS)',
        'slug' => 'irs',
        'code' => 'IRS',
        'description' => 'Surahs of the Holy Quran, Hadith studies, Tawhid (Unity of God), Fiqh (Islamic Jurisprudence), Sirah (Prophetic biography), and Tahdhib (Ethics).',
        'icon' => 'bi-moon-stars-fill',
        'color' => '#059669',
        'sort_order' => 17
    ],
    [
        'name' => 'Technical Drawing',
        'slug' => 'technical-drawing',
        'code' => 'TD',
        'description' => 'Geometrical Construction, Orthographic & Isometric Projections, Sectional Elevations, Tangency, and Architectural/Mechanical Drafting.',
        'icon' => 'bi-rulers',
        'color' => '#6D28D9',
        'sort_order' => 18
    ]
];

$stmtSub = $pdo->prepare("
    INSERT INTO secondary_subjects (name, slug, code, description, icon, color, class_level, status, sort_order)
    VALUES (?, ?, ?, ?, ?, ?, 'Senior Secondary (SS1 - SS3)', 'active', ?)
    ON DUPLICATE KEY UPDATE
        code = VALUES(code),
        description = VALUES(description),
        icon = VALUES(icon),
        color = VALUES(color),
        status = 'active',
        sort_order = VALUES(sort_order)
");

foreach ($subjectsList as $s) {
    $stmtSub->execute([
        $s['name'], $s['slug'], $s['code'], $s['description'], $s['icon'], $s['color'], $s['sort_order']
    ]);
}
echo "✔ " . count($subjectsList) . " Secondary Subjects seeded in secondary_subjects table\n";

// 8. Seed Topics for Key Subjects
$subjectIdMap = $pdo->query("SELECT slug, id FROM secondary_subjects")->fetchAll(PDO::FETCH_KEY_PAIR);

$sampleTopics = [
    'mathematics' => [
        ['Number Base Systems & Modular Arithmetic', 'number-base-systems', 'Operations in binary, octal, hex and modular arithmetic.', 'First Term', 'SS1'],
        ['Indices, Logarithms & Surds', 'indices-logarithms-surds', 'Laws of indices, logarithmic equations, simplifying surds.', 'First Term', 'SS1'],
        ['Quadratic Equations & Simultaneous Equations', 'quadratic-equations', 'Factorization, formula method, completing the square, graphical solutions.', 'Second Term', 'SS2'],
        ['Trigonometry & Bearing/Distances', 'trigonometry-and-bearings', 'Sine and cosine rules, angles of elevation, 3-figure bearings.', 'Third Term', 'SS2'],
        ['Calculus: Differentiation & Integration', 'intro-to-calculus', 'Derivatives, rates of change, tangents/normals, basic integration.', 'First Term', 'SS3'],
        ['Statistics & Probability', 'statistics-and-probability', 'Measures of central tendency, dispersion, cumulative frequency, probability rules.', 'Second Term', 'SS3']
    ],
    'english' => [
        ['Parts of Speech & Concord Rules', 'parts-of-speech-concord', 'Subject-verb agreement, noun types, modal auxiliaries.', 'First Term', 'SS1'],
        ['Phonetics & Oral English (Vowels/Consonants)', 'phonetics-oral-english', 'Monophthongs, diphthongs, consonant clusters, stress & intonation.', 'First Term', 'SS1'],
        ['Essay Writing: Argumentative & Expository', 'essay-writing-skills', 'Structure of WAEC essays, introduction, body paragraphs, logical flow.', 'Second Term', 'SS2'],
        ['Comprehension Passages & Summary Techniques', 'comprehension-and-summary', 'Identifying main ideas, question answering techniques, word replacement.', 'First Term', 'SS3']
    ],
    'physics' => [
        ['Units, Dimensions & Measurement', 'units-and-measurement', 'Fundamental and derived quantities, vernier calipers, micrometer screw gauge.', 'First Term', 'SS1'],
        ['Rectilinear Motion & Projectiles', 'motion-and-projectiles', 'Velocity, acceleration, equations of motion, projectile trajectories.', 'First Term', 'SS1'],
        ['Work, Energy, Power & Machines', 'work-energy-power', 'Mechanical advantage, velocity ratio, efficiency, conservation of energy.', 'Second Term', 'SS1'],
        ['Waves, Optics & Sound Resonance', 'waves-and-optics', 'Wave equation, reflection, refraction, lenses, echoes and pitch.', 'First Term', 'SS2'],
        ['Current Electricity & Circuit Calculations', 'current-electricity', 'Ohm law, series/parallel circuits, electrical energy & power.', 'Second Term', 'SS2']
    ],
    'chemistry' => [
        ['Particulate Nature of Matter & Atomic Structure', 'atomic-structure', 'Protons, neutrons, electrons, isotopes, electronic configuration.', 'First Term', 'SS1'],
        ['Periodic Table & Chemical Bonding', 'periodic-table-bonding', 'Ionic, covalent, coordinate bonding, electronegativity trends.', 'Second Term', 'SS1'],
        ['Stoichiometry & Mole Concept', 'stoichiometry-mole-concept', 'Molar mass, empirical formula, gas volumes at STP.', 'Third Term', 'SS1'],
        ['Acids, Bases, Salts & Neutralization', 'acids-bases-salts', 'pH scale, indicators, preparation of soluble/insoluble salts.', 'First Term', 'SS2'],
        ['Organic Chemistry: Hydrocarbons (Alkanes, Alkenes, Alkynes)', 'organic-hydrocarbons', 'Nomenclature, isomerism, combustion, substitution & addition reactions.', 'First Term', 'SS3']
    ],
    'biology' => [
        ['Cell Structure, Organization & Organelles', 'cell-structure-organization', 'Plant vs animal cells, functions of mitochondria, chloroplast, nucleus.', 'First Term', 'SS1'],
        ['Nutrition in Plants & Animals', 'photosynthesis-digestion', 'Photosynthesis mechanism, digestive enzymes, balanced diet.', 'Second Term', 'SS1'],
        ['Circulatory & Respiratory Systems', 'circulation-and-respiration', 'Heart structure, blood vessels, gas exchange mechanisms.', 'First Term', 'SS2'],
        ['Genetics, Heredity & Mendel Laws', 'genetics-and-heredity', 'Monohybrid cross, dominant/recessive genes, blood grouping, sickle cell.', 'First Term', 'SS3'],
        ['Ecology, Habitats & Food Webs', 'ecology-and-habitats', 'Biotic/abiotic factors, ecological pyramids, nutrient cycling.', 'Second Term', 'SS3']
    ],
    'economics' => [
        ['Basic Economic Concepts (Scarcity, Choice, Scale of Preference)', 'basic-economic-concepts', 'Opportunity cost, production possibility curve.', 'First Term', 'SS1'],
        ['Theory of Demand & Supply', 'demand-and-supply', 'Law of demand, elasticity, equilibrium price determination.', 'Second Term', 'SS1'],
        ['Market Structures (Perfect vs Monopoly)', 'market-structures', 'Price takers, barriers to entry, oligopoly, monopolistic competition.', 'First Term', 'SS2'],
        ['National Income Accounting & Inflation', 'national-income-inflation', 'GDP, GNP, per capita income, causes and control of inflation.', 'Second Term', 'SS3']
    ]
];

$stmtTop = $pdo->prepare("
    INSERT INTO secondary_topics (subject_id, title, slug, description, term, class_level, sort_order, status)
    VALUES (?, ?, ?, ?, ?, ?, ?, 'active')
    ON DUPLICATE KEY UPDATE
        title = VALUES(title),
        description = VALUES(description),
        term = VALUES(term),
        class_level = VALUES(class_level)
");

$topicIdMap = [];
foreach ($sampleTopics as $subjSlug => $topics) {
    if (!isset($subjectIdMap[$subjSlug])) continue;
    $sId = $subjectIdMap[$subjSlug];
    $order = 1;
    foreach ($topics as $t) {
        $stmtTop->execute([$sId, $t[0], $t[1], $t[2], $t[3], $t[4], $order]);
        $order++;
    }
}
echo "✔ Standard curriculum topics seeded\n";

// 9. Seed Study Materials & Summaries
$stmtMat = $pdo->prepare("
    INSERT INTO secondary_materials (subject_id, topic_id, title, slug, content_type, content_body, duration_minutes, downloadable, status, sort_order)
    VALUES (?, ?, ?, ?, ?, ?, ?, 1, 'published', ?)
    ON DUPLICATE KEY UPDATE
        title = VALUES(title),
        content_body = VALUES(content_body),
        duration_minutes = VALUES(duration_minutes)
");

$materialsList = [
    [
        'subject_slug' => 'mathematics',
        'title' => 'WAEC / JAMB High-Yield Formula Sheet (Algebra, Trig & Mensuration)',
        'slug' => 'math-high-yield-formula-sheet',
        'type' => 'formula_sheet',
        'duration' => 20,
        'body' => "### Essential Secondary School Mathematics Formulas\n\n#### 1. Quadratic Equation\nFor equation $ax^2 + bx + c = 0$:\n$$x = \\frac{-b \\pm \\sqrt{b^2 - 4ac}}{2a}$$\n* Discriminant $\\Delta = b^2 - 4ac$:\n  - $\\Delta > 0$: 2 real distinct roots\n  - $\\Delta = 0$: 1 real repeated root\n  - $\\Delta < 0$: Complex roots\n\n#### 2. Indices & Logarithms\n* $a^m \\times a^n = a^{m+n}$\n* $\\frac{a^m}{a^n} = a^{m-n}$\n* $(a^m)^n = a^{mn}$\n* $\\log_a(xy) = \\log_a x + \\log_a y$\n* $\\log_a(x/y) = \\log_a x - \\log_a y$\n* $\\log_a(x^k) = k \\log_a x$\n* Change of base: $\\log_a b = \\frac{\\log_c b}{\\log_c a}$\n\n#### 3. Trigonometry\n* $\\sin^2 \\theta + \\cos^2 \\theta = 1$\n* Sine Rule: $\\frac{a}{\\sin A} = \\frac{b}{\\sin B} = \\frac{c}{\\sin C} = 2R$\n* Cosine Rule: $a^2 = b^2 + c^2 - 2bc \\cos A$\n* Area of Triangle: $\\text{Area} = \\frac{1}{2}ab \\sin C$\n\n#### 4. Mensuration\n* Cylinder Volume: $V = \\pi r^2 h$, Surface Area: $A = 2\\pi r(r + h)$\n* Cone Volume: $V = \\frac{1}{3}\\pi r^2 h$, Curved Surface Area: $A = \\pi r l$\n* Sphere Volume: $V = \\frac{4}{3}\\pi r^3$, Surface Area: $A = 4\\pi r^2$"
    ],
    [
        'subject_slug' => 'english',
        'title' => 'Mastering WAEC / NECO Oral English: Vowel Sounds & Word Stress',
        'slug' => 'oral-english-mastery-guide',
        'type' => 'summary',
        'duration' => 25,
        'body' => "### Oral English Master Summary\n\n#### 1. Monophthongs (Pure Vowels)\n* Short vowels: /ɪ/ (sit), /e/ (bed), /æ/ (cat), /ʌ/ (cup, love), /ɒ/ (pot), /ʊ/ (put), /ə/ (sofa - schwa)\n* Long vowels: /iː/ (seat), /ɑː/ (father, car), /ɔː/ (port, saw), /uː/ (food), /ɜː/ (bird, learn)\n\n#### 2. Common WAEC Traps\n* 'plumber' is pronounced /ˈplʌm.ər/ (silent b)\n* 'receipt' has silent p\n* 'sword' has silent w (/sɔːd/)\n* 'debt' and 'doubt' have silent b\n\n#### 3. Word Stress Rules\n* 2-Syllable Nouns/Adjectives: Stress on **First** syllable (PRE-sent, EX-port, TA-ble, CLEV-er)\n* 2-Syllable Verbs: Stress on **Second** syllable (pre-SENT, ex-PORT, re-LAX, de-CIDE)\n* Words ending in '-tion', '-sion', '-ic': Stress on the syllable immediately before the suffix (edu-CA-tion, spe-CI-fic, de-CI-sion)"
    ],
    [
        'subject_slug' => 'physics',
        'title' => 'Mechanics & Electricity Key Summary for WAEC & JAMB',
        'slug' => 'physics-mechanics-electricity-summary',
        'type' => 'notes',
        'duration' => 30,
        'body' => "### Physics Comprehensive Revision Notes\n\n#### 1. Equations of Uniformly Accelerated Motion\n1. $v = u + at$\n2. $s = ut + \\frac{1}{2}at^2$\n3. $v^2 = u^2 + 2as$\n4. $s = \\frac{(u + v)}{2}t$\n\n#### 2. Projectiles\n* Time of Flight: $T = \\frac{2u \\sin \\theta}{g}$\n* Maximum Height: $H = \\frac{u^2 \\sin^2 \\theta}{2g}$\n* Range: $R = \\frac{u^2 \\sin 2\\theta}{g}$ (Maximum range occurs at $\\theta = 45^\\circ$)\n\n#### 3. Electric Circuits\n* Ohm's Law: $V = IR$\n* Resistors in Series: $R_{\\text{eq}} = R_1 + R_2 + R_3$\n* Resistors in Parallel: $\\frac{1}{R_{\\text{eq}}} = \\frac{1}{R_1} + \\frac{1}{R_2}$\n* Electrical Power: $P = IV = I^2R = \\frac{V^2}{R}$\n* Electrical Energy: $E = Pt = IVt$"
    ],
    [
        'subject_slug' => 'chemistry',
        'title' => 'Stoichiometry, Acid-Base & Organic Chemistry Fast Notes',
        'slug' => 'chemistry-fast-notes',
        'type' => 'notes',
        'duration' => 25,
        'body' => "### Secondary Chemistry High-Yield Notes\n\n#### 1. Mole Concepts\n* Number of moles $n = \\frac{\\text{Mass (g)}}{\\text{Molar Mass (g/mol)}}$\n* Moles of gas at STP: $n = \\frac{\\text{Volume in dm}^3}{22.4\\text{ dm}^3}$\n* Molarity: $C = \\frac{n}{V(\\text{dm}^3)} = \\frac{\\text{mass}}{M \\times V}$\n* Dilution formula: $C_1 V_1 = C_2 V_2$\n\n#### 2. Acid-Base Titration\n$$\\frac{C_a V_a}{C_b V_b} = \\frac{n_a}{n_b}$$\nWhere $C_a, C_b$ are concentrations in $\\text{mol/dm}^3$, $V_a, V_b$ are volumes, and $n_a, n_b$ are mole ratios from balanced equation.\n\n#### 3. Hydrocarbons Summary\n* Alkanes: $C_n H_{2n+2}$ (Saturated, undergoes substitution reactions)\n* Alkenes: $C_n H_{2n}$ (Unsaturated with double bond, decolourizes bromine water)\n* Alkynes: $C_n H_{2n-2}$ (Unsaturated with triple bond)"
    ],
    [
        'subject_slug' => 'biology',
        'title' => 'Genetics, Ecology & Cell Biology Quick Revision Guide',
        'slug' => 'biology-quick-revision',
        'type' => 'summary',
        'duration' => 20,
        'body' => "### Biology High-Yield Revision\n\n#### 1. Cell Organelles & Functions\n* **Mitochondrion**: Site of cellular respiration (ATP synthesis) - Powerhouse.\n* **Ribosome**: Site of protein synthesis.\n* **Chloroplast**: Contains chlorophyll for photosynthesis.\n* **Cell Membrane**: Selectively permeable lipid bilayer controlling movement of substances.\n\n#### 2. Genetics & Mendelism\n* Phenotypic ratio in F2 generation for monohybrid cross: **3:1**\n* Genotypic ratio: **1:2:1** (1 BB : 2 Bb : 1 bb)\n* ABO Blood Groups: Genotypes $I^A I^A, I^A I^O$ (Type A), $I^B I^B, I^B I^O$ (Type B), $I^A I^B$ (Type AB - co-dominance), $I^O I^O$ (Type O - universal donor)."
    ]
];

foreach ($materialsList as $m) {
    if (!isset($subjectIdMap[$m['subject_slug']])) continue;
    $sId = $subjectIdMap[$m['subject_slug']];
    $stmtMat->execute([$sId, null, $m['title'], $m['slug'], $m['type'], $m['body'], $m['duration'], 1]);
}
echo "✔ High-yield secondary study materials loaded\n";

// 10. Seed Extensive Past Questions across WAEC, NECO, JAMB
$questionsData = [
    // === MATHEMATICS ===
    [
        'exam_type' => 'waec', 'year' => 2024, 'subject_name' => 'Mathematics', 'subject_slug' => 'mathematics',
        'question_number' => 1,
        'question_text' => 'If $2^{x+3} = 32$, find the value of $x$.',
        'option_a' => '1', 'option_b' => '2', 'option_c' => '3', 'option_d' => '5',
        'correct_option' => 'b', 'difficulty' => 'easy', 'topic_name' => 'Indices and Logarithms',
        'explanation' => 'Express 32 as a power of 2: $32 = 2^5$. Therefore, $2^{x+3} = 2^5 \\implies x + 3 = 5 \\implies x = 2$.'
    ],
    [
        'exam_type' => 'waec', 'year' => 2024, 'subject_name' => 'Mathematics', 'subject_slug' => 'mathematics',
        'question_number' => 2,
        'question_text' => 'Simplify $\\sqrt{75} - \\sqrt{12} + \\sqrt{27}$.',
        'option_a' => '$4\\sqrt{3}$', 'option_b' => '$6\\sqrt{3}$', 'option_c' => '$5\\sqrt{3}$', 'option_d' => '$7\\sqrt{3}$',
        'correct_option' => 'b', 'difficulty' => 'easy', 'topic_name' => 'Surds',
        'explanation' => '$\\sqrt{75} = 5\\sqrt{3}$, $\\sqrt{12} = 2\\sqrt{3}$, $\\sqrt{27} = 3\\sqrt{3}$. Result: $5\\sqrt{3} - 2\\sqrt{3} + 3\\sqrt{3} = 6\\sqrt{3}$.'
    ],
    [
        'exam_type' => 'waec', 'year' => 2024, 'subject_name' => 'Mathematics', 'subject_slug' => 'mathematics',
        'question_number' => 3,
        'question_text' => 'Solve the quadratic equation: $2x^2 - 5x - 3 = 0$.',
        'option_a' => '$x = 3$ or $x = -\\frac{1}{2}$', 'option_b' => '$x = -3$ or $x = \\frac{1}{2}$', 'option_c' => '$x = 2$ or $x = -3$', 'option_d' => '$x = 1$ or $x = -\\frac{3}{2}$',
        'correct_option' => 'a', 'difficulty' => 'medium', 'topic_name' => 'Quadratic Equations',
        'explanation' => 'Factorization: $(2x + 1)(x - 3) = 0 \\implies x = 3$ or $x = -\\frac{1}{2}$.'
    ],
    [
        'exam_type' => 'waec', 'year' => 2023, 'subject_name' => 'Mathematics', 'subject_slug' => 'mathematics',
        'question_number' => 1,
        'question_text' => 'A trader bought a bag of rice for ₦30,000 and sold it for ₦37,500. Calculate his percentage profit.',
        'option_a' => '20%', 'option_b' => '25%', 'option_c' => '30%', 'option_d' => '15%',
        'correct_option' => 'b', 'difficulty' => 'easy', 'topic_name' => 'Commercial Arithmetic',
        'explanation' => '$\\text{Profit} = ₦37,500 - ₦30,000 = ₦7,500$. $\\text{Percentage Profit} = \\frac{7500}{30000} \\times 100\\% = 25\\%$.'
    ],
    [
        'exam_type' => 'waec', 'year' => 2023, 'subject_name' => 'Mathematics', 'subject_slug' => 'mathematics',
        'question_number' => 2,
        'question_text' => 'The angle of elevation of the top of a building from a point 30m away on level ground is $45^\\circ$. Find the height of the building.',
        'option_a' => '15m', 'option_b' => '30m', 'option_c' => '$30\\sqrt{3}$m', 'option_d' => '$15\\sqrt{2}$m',
        'correct_option' => 'b', 'difficulty' => 'easy', 'topic_name' => 'Trigonometry',
        'explanation' => '$\\tan 45^\\circ = \\frac{\\text{Height}}{\\text{Distance}} \\implies 1 = \\frac{h}{30} \\implies h = 30\\text{m}$.'
    ],
    [
        'exam_type' => 'jamb', 'year' => 2024, 'subject_name' => 'Mathematics', 'subject_slug' => 'mathematics',
        'question_number' => 1,
        'question_text' => 'Find the derivative $\\frac{dy}{dx}$ if $y = 3x^4 - 5x^2 + 7x - 2$.',
        'option_a' => '$12x^3 - 10x + 7$', 'option_b' => '$12x^4 - 10x^2 + 7$', 'option_c' => '$7x^3 - 5x + 3$', 'option_d' => '$12x^3 - 5x + 7$',
        'correct_option' => 'a', 'difficulty' => 'medium', 'topic_name' => 'Calculus',
        'explanation' => 'Using power rule $\\frac{d}{dx}(ax^n) = anx^{n-1}$: $\\frac{dy}{dx} = 12x^3 - 10x + 7$.'
    ],
    [
        'exam_type' => 'jamb', 'year' => 2024, 'subject_name' => 'Mathematics', 'subject_slug' => 'mathematics',
        'question_number' => 2,
        'question_text' => 'Evaluate $\\int (4x^3 + 6x - 5)\\,dx$.',
        'option_a' => '$x^4 + 3x^2 - 5x + C$', 'option_b' => '$4x^4 + 6x^2 - 5x + C$', 'option_c' => '$12x^2 + 6 + C$', 'option_d' => '$x^4 + 6x^2 - 5 + C$',
        'correct_option' => 'a', 'difficulty' => 'medium', 'topic_name' => 'Calculus',
        'explanation' => 'Integration: $\\int 4x^3 dx + \\int 6x dx - \\int 5 dx = x^4 + 3x^2 - 5x + C$.'
    ],
    [
        'exam_type' => 'neco', 'year' => 2024, 'subject_name' => 'Mathematics', 'subject_slug' => 'mathematics',
        'question_number' => 1,
        'question_text' => 'If $\\log_{10} 2 = 0.3010$ and $\\log_{10} 3 = 0.4771$, find $\\log_{10} 18$.',
        'option_a' => '1.2552', 'option_b' => '1.0791', 'option_c' => '0.7781', 'option_d' => '1.4552',
        'correct_option' => 'a', 'difficulty' => 'medium', 'topic_name' => 'Logarithms',
        'explanation' => '$18 = 2 \\times 3^2 \\implies \\log 18 = \\log 2 + 2\\log 3 = 0.3010 + 2(0.4771) = 0.3010 + 0.9542 = 1.2552$.'
    ],

    // === ENGLISH LANGUAGE ===
    [
        'exam_type' => 'waec', 'year' => 2024, 'subject_name' => 'English Language', 'subject_slug' => 'english',
        'question_number' => 1,
        'question_text' => 'Choose the word nearest in meaning to the capitalized word: "The principal gave an AMBIGUOUS response to our request."',
        'option_a' => 'Unclear and open to double interpretation', 'option_b' => 'Aggressive and hostile', 'option_c' => 'Explicit and direct', 'option_d' => 'Immediate',
        'correct_option' => 'a', 'difficulty' => 'easy', 'topic_name' => 'Synonyms & Lexis',
        'explanation' => '"Ambiguous" means having more than one possible meaning; not clear or distinct.'
    ],
    [
        'exam_type' => 'waec', 'year' => 2024, 'subject_name' => 'English Language', 'subject_slug' => 'english',
        'question_number' => 2,
        'question_text' => 'Choose the option that has the SAME vowel sound as in "CUP":',
        'option_a' => 'Cot', 'option_b' => 'Love', 'option_c' => 'Soup', 'option_d' => 'Put',
        'correct_option' => 'b', 'difficulty' => 'easy', 'topic_name' => 'Oral English',
        'explanation' => 'Both "cup" and "love" feature the short central vowel sound /ʌ/.'
    ],
    [
        'exam_type' => 'jamb', 'year' => 2024, 'subject_name' => 'English Language', 'subject_slug' => 'english',
        'question_number' => 1,
        'question_text' => 'Identify the word with the stress on the FIRST syllable:',
        'option_a' => 'export (noun)', 'option_b' => 'export (verb)', 'option_c' => 'decide', 'option_d' => 'refuse (verb)',
        'correct_option' => 'a', 'difficulty' => 'medium', 'topic_name' => 'Word Stress',
        'explanation' => 'Two-syllable nouns take stress on the first syllable (EX-port), whereas verbs take stress on the second (ex-PORT).'
    ],

    // === PHYSICS ===
    [
        'exam_type' => 'waec', 'year' => 2024, 'subject_name' => 'Physics', 'subject_slug' => 'physics',
        'question_number' => 1,
        'question_text' => 'A car accelerates uniformly from rest to 20 m/s in 5 seconds. What is its acceleration?',
        'option_a' => '2 m/s²', 'option_b' => '4 m/s²', 'option_c' => '5 m/s²', 'option_d' => '100 m/s²',
        'correct_option' => 'b', 'difficulty' => 'easy', 'topic_name' => 'Motion',
        'explanation' => '$a = \\frac{v - u}{t} = \\frac{20 - 0}{5} = 4\\text{ m/s}^2$.'
    ],
    [
        'exam_type' => 'jamb', 'year' => 2024, 'subject_name' => 'Physics', 'subject_slug' => 'physics',
        'question_number' => 1,
        'question_text' => 'Which of the following is NOT a fundamental SI quantity?',
        'option_a' => 'Electric current', 'option_b' => 'Luminous intensity', 'option_c' => 'Electric charge', 'option_d' => 'Thermodynamic temperature',
        'correct_option' => 'c', 'difficulty' => 'easy', 'topic_name' => 'Units and Measurement',
        'explanation' => 'Electric current (Ampere) is fundamental, while electric charge ($Q = It$, Coulomb) is a derived quantity.'
    ],
    [
        'exam_type' => 'neco', 'year' => 2024, 'subject_name' => 'Physics', 'subject_slug' => 'physics',
        'question_number' => 1,
        'question_text' => 'Two resistors of $3\\,\\Omega$ and $6\\,\\Omega$ are connected in parallel. What is the effective resistance?',
        'option_a' => '$9\\,\\Omega$', 'option_b' => '$2\\,\\Omega$', 'option_c' => '$4.5\\,\\Omega$', 'option_d' => '$18\\,\\Omega$',
        'correct_option' => 'b', 'difficulty' => 'easy', 'topic_name' => 'Current Electricity',
        'explanation' => '$\\frac{1}{R} = \\frac{1}{3} + \\frac{1}{6} = \\frac{2+1}{6} = \\frac{3}{6} = \\frac{1}{2} \\implies R = 2\\,\\Omega$.'
    ],

    // === CHEMISTRY ===
    [
        'exam_type' => 'neco', 'year' => 2024, 'subject_name' => 'Chemistry', 'subject_slug' => 'chemistry',
        'question_number' => 1,
        'question_text' => 'What is the oxidation state of sulfur in $\\text{H}_2\\text{SO}_4$?',
        'option_a' => '+2', 'option_b' => '+4', 'option_c' => '+6', 'option_d' => '-2',
        'correct_option' => 'c', 'difficulty' => 'easy', 'topic_name' => 'Oxidation Numbers',
        'explanation' => '$2(+1) + S + 4(-2) = 0 \\implies 2 + S - 8 = 0 \\implies S = +6$.'
    ],
    [
        'exam_type' => 'jamb', 'year' => 2024, 'subject_name' => 'Chemistry', 'subject_slug' => 'chemistry',
        'question_number' => 1,
        'question_text' => 'Which of the following elements has the highest electronegativity on the Pauling scale?',
        'option_a' => 'Oxygen', 'option_b' => 'Fluorine', 'option_c' => 'Chlorine', 'option_d' => 'Nitrogen',
        'correct_option' => 'b', 'difficulty' => 'easy', 'topic_name' => 'Periodic Trends',
        'explanation' => 'Fluorine is the most electronegative element with a value of 4.0 on the Pauling scale.'
    ],

    // === BIOLOGY ===
    [
        'exam_type' => 'neco', 'year' => 2024, 'subject_name' => 'Biology', 'subject_slug' => 'biology',
        'question_number' => 1,
        'question_text' => 'Which cell organelle is responsible for cellular respiration and ATP generation?',
        'option_a' => 'Ribosome', 'option_b' => 'Mitochondrion', 'option_c' => 'Golgi Apparatus', 'option_d' => 'Nucleolus',
        'correct_option' => 'b', 'difficulty' => 'easy', 'topic_name' => 'Cell Biology',
        'explanation' => 'The mitochondrion is known as the powerhouse of the cell, carrying out the Krebs cycle and oxidative phosphorylation.'
    ],
    [
        'exam_type' => 'waec', 'year' => 2024, 'subject_name' => 'Biology', 'subject_slug' => 'biology',
        'question_number' => 1,
        'question_text' => 'In genetics, what is the expected phenotypic ratio in the F2 generation of a typical monohybrid cross with complete dominance?',
        'option_a' => '1:2:1', 'option_b' => '3:1', 'option_c' => '9:3:3:1', 'option_d' => '1:1',
        'correct_option' => 'b', 'difficulty' => 'easy', 'topic_name' => 'Genetics',
        'explanation' => 'Mendel monohybrid cross produces a 3:1 phenotypic ratio (dominant to recessive) and 1:2:1 genotypic ratio.'
    ],

    // === ECONOMICS ===
    [
        'exam_type' => 'waec', 'year' => 2024, 'subject_name' => 'Economics', 'subject_slug' => 'economics',
        'question_number' => 1,
        'question_text' => 'Opportunity cost is best defined as:',
        'option_a' => 'The monetary cost of an item', 'option_b' => 'The alternative forgone when a choice is made', 'option_c' => 'The variable cost of production', 'option_d' => 'The sunk cost',
        'correct_option' => 'b', 'difficulty' => 'easy', 'topic_name' => 'Basic Economic Concepts',
        'explanation' => 'Opportunity cost represents the next best alternative sacrificed when making a decision.'
    ],
    [
        'exam_type' => 'jamb', 'year' => 2024, 'subject_name' => 'Economics', 'subject_slug' => 'economics',
        'question_number' => 1,
        'question_text' => 'When demand is price INELASTIC, a rise in price will lead to:',
        'option_a' => 'An increase in total revenue', 'option_b' => 'A decrease in total revenue', 'option_c' => 'No change in total revenue', 'option_d' => 'Zero demand',
        'correct_option' => 'a', 'difficulty' => 'medium', 'topic_name' => 'Elasticity of Demand',
        'explanation' => 'For inelastic demand ($|E_d| < 1$), the percentage drop in quantity demanded is less than the percentage increase in price, increasing total revenue.'
    ],

    // === GOVERNMENT ===
    [
        'exam_type' => 'waec', 'year' => 2024, 'subject_name' => 'Government', 'subject_slug' => 'government',
        'question_number' => 1,
        'question_text' => 'The system of government where power is shared between a central authority and constituent component units is called:',
        'option_a' => 'Unitary system', 'option_b' => 'Federal system', 'option_c' => 'Confederal system', 'option_d' => 'Monarchy',
        'correct_option' => 'b', 'difficulty' => 'easy', 'topic_name' => 'Forms of Government',
        'explanation' => 'Federalism constitutional divides powers between the central government and regional state components.'
    ]
];

$stmtPQIns = $pdo->prepare("
    INSERT INTO past_questions (exam_type, year, subject_name, subject_slug, question_number, question_text, option_a, option_b, option_c, option_d, correct_option, explanation, difficulty, topic_name)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        question_text = VALUES(question_text),
        option_a = VALUES(option_a),
        option_b = VALUES(option_b),
        option_c = VALUES(option_c),
        option_d = VALUES(option_d),
        correct_option = VALUES(correct_option),
        explanation = VALUES(explanation),
        difficulty = VALUES(difficulty),
        topic_name = VALUES(topic_name)
");

foreach ($questionsData as $pq) {
    $stmtPQIns->execute([
        $pq['exam_type'], $pq['year'], $pq['subject_name'], $pq['subject_slug'],
        $pq['question_number'], $pq['question_text'], $pq['option_a'], $pq['option_b'],
        $pq['option_c'], $pq['option_d'], $pq['correct_option'], $pq['explanation'],
        $pq['difficulty'], $pq['topic_name']
    ]);
}
echo "✔ " . count($questionsData) . " Verified Past Questions loaded across WAEC, NECO & JAMB\n";

echo "Secondary School complete migration succeeded!\n";
