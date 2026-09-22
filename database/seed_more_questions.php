<?php

require_once __DIR__ . '/../config/main.php';

$pdo = getDBConnection();

$moreNeco = [
    [
        'subject_slug' => 'english-language',
        'exam_type' => 'NECO',
        'year' => 2023,
        'question_text' => 'Choose the word that is nearest in meaning to the underlined word: The manager made an <u>ephemeral</u> remark during the morning briefing.',
        'option_a' => 'Lengthy',
        'option_b' => 'Short-lived',
        'option_c' => 'Insightful',
        'option_d' => 'Aggressive',
        'correct_option' => 'B',
        'explanation' => '"Ephemeral" refers to something lasting for a very short time. Hence, "short-lived" is the closest in meaning.',
        'difficulty' => 'medium'
    ],
    [
        'subject_slug' => 'chemistry',
        'exam_type' => 'NECO',
        'year' => 2022,
        'question_text' => 'Which of the following compounds exhibits hydrogen bonding in its liquid state?',
        'option_a' => 'CH4',
        'option_b' => 'HCl',
        'option_c' => 'H2O',
        'option_d' => 'H2S',
        'correct_option' => 'C',
        'explanation' => 'Water (H2O) contains hydrogen bonded directly to highly electronegative oxygen atoms, resulting in strong intermolecular hydrogen bonding.',
        'difficulty' => 'medium'
    ],
    [
        'subject_slug' => 'economics',
        'exam_type' => 'NECO',
        'year' => 2023,
        'question_text' => 'An increase in the supply of a commodity while demand remains constant will typically cause:',
        'option_a' => 'An increase in equilibrium price and quantity',
        'option_b' => 'A decrease in equilibrium price and increase in equilibrium quantity',
        'option_c' => 'A decrease in equilibrium price and quantity',
        'option_d' => 'No change in equilibrium price',
        'correct_option' => 'B',
        'explanation' => 'A rightward shift in supply creates surplus at the current price, driving down equilibrium price and increasing equilibrium quantity.',
        'difficulty' => 'easy'
    ],
    [
        'subject_slug' => 'biology',
        'exam_type' => 'NECO',
        'year' => 2021,
        'question_text' => 'Which cell organelle is primarily responsible for the synthesis of adenosine triphosphate (ATP)?',
        'option_a' => 'Ribosome',
        'option_b' => 'Golgi body',
        'option_c' => 'Mitochondrion',
        'option_d' => 'Endoplasmic reticulum',
        'correct_option' => 'C',
        'explanation' => 'The mitochondrion is known as the powerhouse of the cell because cellular respiration and ATP generation take place there.',
        'difficulty' => 'easy'
    ],
    [
        'subject_slug' => 'physics',
        'exam_type' => 'NECO',
        'year' => 2023,
        'question_text' => 'A ray of light strikes a plane mirror at an angle of incidence of 35°. What is the angle between the incident ray and the reflected ray?',
        'option_a' => '35°',
        'option_b' => '55°',
        'option_c' => '70°',
        'option_d' => '90°',
        'correct_option' => 'C',
        'explanation' => 'By the law of reflection, angle of reflection equals angle of incidence ($35^\circ$). The angle between incident and reflected rays is $35^\circ + 35^\circ = 70^\circ$.',
        'difficulty' => 'easy'
    ]
];

$stmt = $pdo->prepare("
    INSERT INTO past_questions 
    (subject_slug, exam_type, year, question_text, option_a, option_b, option_c, option_d, correct_option, explanation, difficulty, created_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
");

foreach ($moreNeco as $q) {
    $stmtCheck = $pdo->prepare("SELECT id FROM past_questions WHERE subject_slug = ? AND exam_type = ? AND question_text = ? LIMIT 1");
    $stmtCheck->execute([$q['subject_slug'], $q['exam_type'], $q['question_text']]);
    if (!$stmtCheck->fetch()) {
        $stmt->execute([
            $q['subject_slug'],
            $q['exam_type'],
            $q['year'],
            $q['question_text'],
            $q['option_a'],
            $q['option_b'],
            $q['option_c'],
            $q['option_d'],
            $q['correct_option'],
            $q['explanation'],
            $q['difficulty']
        ]);
    }
}

// Ensure at least one secondary test student exists
$studentUser = $pdo->query("SELECT id FROM users WHERE role = 'student' LIMIT 1")->fetch(PDO::FETCH_ASSOC);

if (!$studentUser) {
    // Create demo secondary student
    $hash = password_hash('password123', PASSWORD_DEFAULT);
    $pdo->prepare("
        INSERT INTO users (first_name, last_name, username, email, password, role, status, email_verified_at, created_at)
        VALUES ('Chinedu', 'Okafor', 'chinedu_secondary', 'chinedu@example.com', ?, 'student', 'active', NOW(), NOW())
    ")->execute([$hash]);
    $userId = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO students (user_id, student_number, student_type, academic_level, target_exam) VALUES (?, 'STD-2026-0001', 'secondary', 'SS3', 'WAEC / JAMB')")->execute([$userId]);
} else {
    $uid = (int)$studentUser['id'];
    $stCheck = $pdo->prepare("SELECT id FROM students WHERE user_id = ? LIMIT 1");
    $stCheck->execute([$uid]);
    $st = $stCheck->fetch(PDO::FETCH_ASSOC);
    if (!$st) {
        $pdo->prepare("INSERT INTO students (user_id, student_number, student_type, academic_level, target_exam) VALUES (?, 'STD-2026-0001', 'secondary', 'SS3', 'WAEC / JAMB')")->execute([$uid]);
    } else {
        $pdo->prepare("UPDATE students SET student_type = 'secondary', academic_level = 'SS3', target_exam = 'WAEC / JAMB' WHERE id = ?")->execute([$st['id']]);
    }
}

echo "Successfully seeded additional NECO past questions and ensured Secondary Student test profile.\n";
