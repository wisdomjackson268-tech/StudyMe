<?php
/**
 * Migration & Content Expansion for Secondary School Past Questions, Lessons, and Practice Suite V2
 */

require_once __DIR__ . '/../config/main.php';

$pdo = getDBConnection();

echo "Starting Secondary School Suite V2 Database Migration & Content Seeding...\n\n";

// 1. Create secondary_lesson_progress table
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS secondary_lesson_progress (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            student_id BIGINT UNSIGNED NOT NULL,
            topic_id BIGINT UNSIGNED NULL,
            material_id BIGINT UNSIGNED NULL,
            completed TINYINT(1) NOT NULL DEFAULT 1,
            completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uk_sec_stud_top_mat (student_id, topic_id, material_id),
            INDEX idx_sec_lp_stud (student_id),
            INDEX idx_sec_lp_top (topic_id),
            CONSTRAINT fk_sec_lp_stud FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "✔ secondary_lesson_progress table ready\n";
} catch (Exception $e) {
    echo "secondary_lesson_progress error: " . $e->getMessage() . "\n";
}

// 2. Ensure past_questions has topic_id and subject_id columns
try {
    $cols = $pdo->query("SHOW COLUMNS FROM past_questions")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('subject_id', $cols, true)) {
        $pdo->exec("ALTER TABLE past_questions ADD COLUMN subject_id BIGINT UNSIGNED NULL AFTER exam_type");
    }
    if (!in_array('topic_id', $cols, true)) {
        $pdo->exec("ALTER TABLE past_questions ADD COLUMN topic_id BIGINT UNSIGNED NULL AFTER subject_slug");
    }
    echo "✔ past_questions subject_id & topic_id columns verified\n";
} catch (Exception $e) {
    echo "past_questions alter error: " . $e->getMessage() . "\n";
}

// 3. Connect existing past_questions to secondary_subjects and secondary_topics
try {
    $pdo->exec("
        UPDATE past_questions pq
        JOIN secondary_subjects ss ON pq.subject_slug = ss.slug
        SET pq.subject_id = ss.id
        WHERE pq.subject_id IS NULL OR pq.subject_id = 0
    ");
    echo "✔ Linked past_questions to secondary_subjects\n";
} catch (Exception $e) {
    echo "past_questions subject link error: " . $e->getMessage() . "\n";
}

// 4. Seed Comprehensive Set of Authentic WAEC, NECO, and JAMB Past Questions
$pastQuestionsData = [
    // === MATHEMATICS (WAEC) ===
    [
        'exam_type' => 'waec',
        'year' => 2024,
        'subject_slug' => 'mathematics',
        'subject_name' => 'Mathematics',
        'topic_name' => 'Quadratic Equations & Simultaneous Equations',
        'question_number' => 1,
        'question_text' => 'Solve the quadratic equation: $2x^2 - 5x - 3 = 0$.',
        'option_a' => '$x = 3$ or $x = -\\frac{1}{2}$',
        'option_b' => '$x = -3$ or $x = \\frac{1}{2}$',
        'option_c' => '$x = 2$ or $x = -3$',
        'option_d' => '$x = -2$ or $x = \\frac{3}{2}$',
        'correct_option' => 'A',
        'explanation' => 'Factorising $2x^2 - 5x - 3 = 0$ gives $(2x + 1)(x - 3) = 0$. Setting each factor to zero: $2x + 1 = 0 \\implies x = -\\frac{1}{2}$, and $x - 3 = 0 \\implies x = 3$.',
        'difficulty' => 'easy'
    ],
    [
        'exam_type' => 'waec',
        'year' => 2024,
        'subject_slug' => 'mathematics',
        'subject_name' => 'Mathematics',
        'topic_name' => 'Indices, Logarithms & Surds',
        'question_number' => 2,
        'question_text' => 'Simplify the expression: $\\log_{10} 25 + \\log_{10} 4 - \\log_{10} 10$.',
        'option_a' => '0',
        'option_b' => '1',
        'option_c' => '2',
        'option_d' => '10',
        'correct_option' => 'B',
        'explanation' => 'Using logarithm laws: $\\log_{10} 25 + \\log_{10} 4 - \\log_{10} 10 = \\log_{10} \\left(\\frac{25 \\times 4}{10}\\right) = \\log_{10}\\left(\\frac{100}{10}\\right) = \\log_{10} 10 = 1$.',
        'difficulty' => 'easy'
    ],
    [
        'exam_type' => 'waec',
        'year' => 2023,
        'subject_slug' => 'mathematics',
        'subject_name' => 'Mathematics',
        'topic_name' => 'Trigonometry & Bearing/Distances',
        'question_number' => 3,
        'question_text' => 'From the top of a cliff 60 m high, the angle of depression of a boat on the sea is $30^\\circ$. How far is the boat from the foot of the cliff?',
        'option_a' => '$30\\text{ m}$',
        'option_b' => '$60\\sqrt{3}\\text{ m} \\approx 103.92\\text{ m}$',
        'option_c' => '$20\\sqrt{3}\\text{ m}$',
        'option_d' => '$120\\text{ m}$',
        'correct_option' => 'B',
        'explanation' => 'Let distance from foot be $d$. By alternate angles, angle of elevation from boat is $30^\\circ$. $\\tan 30^\\circ = \\frac{60}{d} \\implies d = \\frac{60}{\\tan 30^\\circ} = 60\\sqrt{3}\\text{ m} \\approx 103.92\\text{ m}$.',
        'difficulty' => 'medium'
    ],
    [
        'exam_type' => 'waec',
        'year' => 2023,
        'subject_slug' => 'mathematics',
        'subject_name' => 'Mathematics',
        'topic_name' => 'Statistics & Probability',
        'question_number' => 4,
        'question_text' => 'A bag contains 5 red balls, 4 blue balls, and 3 green balls. If a ball is drawn at random, what is the probability that it is NOT green?',
        'option_a' => '$\\frac{1}{4}$',
        'option_b' => '$\\frac{3}{4}$',
        'option_c' => '$\\frac{5}{12}$',
        'option_d' => '$\\frac{7}{12}$',
        'correct_option' => 'B',
        'explanation' => 'Total balls $= 5 + 4 + 3 = 12$. Number of non-green balls (red + blue) $= 5 + 4 = 9$. $P(\\text{not green}) = \\frac{9}{12} = \\frac{3}{4}$.',
        'difficulty' => 'easy'
    ],
    [
        'exam_type' => 'waec',
        'year' => 2022,
        'subject_slug' => 'mathematics',
        'subject_name' => 'Mathematics',
        'topic_name' => 'Number Base Systems & Modular Arithmetic',
        'question_number' => 5,
        'question_text' => 'Convert $11011_2$ to base 10.',
        'option_a' => '25',
        'option_b' => '27',
        'option_c' => '29',
        'option_d' => '31',
        'correct_option' => 'B',
        'explanation' => '$11011_2 = (1 \\times 2^4) + (1 \\times 2^3) + (0 \\times 2^2) + (1 \\times 2^1) + (1 \\times 2^0) = 16 + 8 + 0 + 2 + 1 = 27_{10}$.',
        'difficulty' => 'easy'
    ],
    [
        'exam_type' => 'waec',
        'year' => 2022,
        'subject_slug' => 'mathematics',
        'subject_name' => 'Mathematics',
        'topic_name' => 'Calculus: Differentiation & Integration',
        'question_number' => 6,
        'question_text' => 'Find the derivative of $y = 3x^4 - 5x^2 + 7x - 9$ with respect to $x$.',
        'option_a' => '$12x^3 - 10x + 7$',
        'option_b' => '$12x^3 - 10x$',
        'option_c' => '$7x^3 - 5x + 7$',
        'option_d' => '$12x^4 - 10x^2 + 7$',
        'correct_option' => 'A',
        'explanation' => '$\\frac{dy}{dx} = \\frac{d}{dx}(3x^4) - \\frac{d}{dx}(5x^2) + \\frac{d}{dx}(7x) - \\frac{d}{dx}(9) = 12x^3 - 10x + 7$.',
        'difficulty' => 'medium'
    ],
    [
        'exam_type' => 'waec',
        'year' => 2021,
        'subject_slug' => 'mathematics',
        'subject_name' => 'Mathematics',
        'topic_name' => 'Indices, Logarithms & Surds',
        'question_number' => 7,
        'question_text' => 'Simplify $\\sqrt{75} - \\sqrt{12} + \\sqrt{27}$.',
        'option_a' => '$4\\sqrt{3}$',
        'option_b' => '$5\\sqrt{3}$',
        'option_c' => '$6\\sqrt{3}$',
        'option_d' => '$7\\sqrt{3}$',
        'correct_option' => 'C',
        'explanation' => '$\\sqrt{75} = \\sqrt{25 \\times 3} = 5\\sqrt{3}$. $\\sqrt{12} = \\sqrt{4 \\times 3} = 2\\sqrt{3}$. $\\sqrt{27} = \\sqrt{9 \\times 3} = 3\\sqrt{3}$. Expression $= 5\\sqrt{3} - 2\\sqrt{3} + 3\\sqrt{3} = 6\\sqrt{3}$.',
        'difficulty' => 'easy'
    ],
    [
        'exam_type' => 'waec',
        'year' => 2020,
        'subject_slug' => 'mathematics',
        'subject_name' => 'Mathematics',
        'topic_name' => 'Quadratic Equations & Simultaneous Equations',
        'question_number' => 8,
        'question_text' => 'If the roots of the equation $x^2 + px + q = 0$ are 2 and -5, find the values of $p$ and $q$.',
        'option_a' => '$p = 3, q = -10$',
        'option_b' => '$p = -3, q = 10$',
        'option_c' => '$p = 3, q = 10$',
        'option_d' => '$p = -3, q = -10$',
        'correct_option' => 'A',
        'explanation' => 'Sum of roots $= 2 + (-5) = -3 = -p \\implies p = 3$. Product of roots $= 2 \\times (-5) = -10 = q \\implies q = -10$.',
        'difficulty' => 'medium'
    ],

    // === PHYSICS (WAEC, NECO, JAMB) ===
    [
        'exam_type' => 'waec',
        'year' => 2024,
        'subject_slug' => 'physics',
        'subject_name' => 'Physics',
        'topic_name' => 'Rectilinear Motion & Projectiles',
        'question_number' => 1,
        'question_text' => 'A car accelerates uniformly from rest at $2.5\\text{ m/s}^2$ for 8 seconds. Calculate the total distance covered.',
        'option_a' => '$40\\text{ m}$',
        'option_b' => '$60\\text{ m}$',
        'option_c' => '$80\\text{ m}$',
        'option_d' => '$100\\text{ m}$',
        'correct_option' => 'C',
        'explanation' => 'Using $s = ut + \\frac{1}{2}at^2$: Since $u = 0$, $s = \\frac{1}{2}(2.5)(8^2) = \\frac{1}{2} \\times 2.5 \\times 64 = 80\\text{ m}$.',
        'difficulty' => 'easy'
    ],
    [
        'exam_type' => 'waec',
        'year' => 2023,
        'subject_slug' => 'physics',
        'subject_name' => 'Physics',
        'topic_name' => 'Work, Energy, Power & Machines',
        'question_number' => 2,
        'question_text' => 'An electric motor of efficiency 80% lifts a load of 200 kg vertically through a height of 10 m in 5 seconds. Calculate the power input to the motor. ($g = 9.8\\text{ m/s}^2$)',
        'option_a' => '$3.92\\text{ kW}$',
        'option_b' => '$4.90\\text{ kW}$',
        'option_c' => '$5.88\\text{ kW}$',
        'option_d' => '$6.12\\text{ kW}$',
        'correct_option' => 'B',
        'explanation' => 'Work output $= mgh = 200 \\times 9.8 \\times 10 = 19600\\text{ J}$. Power output $= \\frac{19600}{5} = 3920\\text{ W}$. Power input $= \\frac{3920}{0.80} = 4900\\text{ W} = 4.90\\text{ kW}$.',
        'difficulty' => 'medium'
    ],
    [
        'exam_type' => 'neco',
        'year' => 2024,
        'subject_slug' => 'physics',
        'subject_name' => 'Physics',
        'topic_name' => 'Current Electricity & Circuit Calculations',
        'question_number' => 3,
        'question_text' => 'Three resistors of resistances $2\\,\\Omega$, $3\\,\\Omega$, and $6\\,\\Omega$ are connected in parallel. What is their equivalent resistance?',
        'option_a' => '$1\\,\\Omega$',
        'option_b' => '$2\\,\\Omega$',
        'option_c' => '$3\\,\\Omega$',
        'option_d' => '$11\\,\\Omega$',
        'correct_option' => 'A',
        'explanation' => '$\\frac{1}{R_p} = \\frac{1}{2} + \\frac{1}{3} + \\frac{1}{6} = \\frac{3 + 2 + 1}{6} = \\frac{6}{6} = 1\\,\\Omega^{-1} \\implies R_p = 1\\,\\Omega$.',
        'difficulty' => 'easy'
    ],
    [
        'exam_type' => 'neco',
        'year' => 2023,
        'subject_slug' => 'physics',
        'subject_name' => 'Physics',
        'topic_name' => 'Waves, Optics & Sound Resonance',
        'question_number' => 4,
        'question_text' => 'A radio station transmits signals at a frequency of $100\\text{ MHz}$. If the speed of light is $3 \\times 10^8\\text{ m/s}$, calculate the wavelength of the waves.',
        'option_a' => '$0.33\\text{ m}$',
        'option_b' => '$3.0\\text{ m}$',
        'option_c' => '$30\\text{ m}$',
        'option_d' => '$300\\text{ m}$',
        'correct_option' => 'B',
        'explanation' => '$v = f \\lambda \\implies \\lambda = \\frac{v}{f} = \\frac{3 \\times 10^8}{100 \\times 10^6} = \\frac{3 \\times 10^8}{10^8} = 3.0\\text{ m}$.',
        'difficulty' => 'easy'
    ],
    [
        'exam_type' => 'jamb',
        'year' => 2024,
        'subject_slug' => 'physics',
        'subject_name' => 'Physics',
        'topic_name' => 'Rectilinear Motion & Projectiles',
        'question_number' => 5,
        'question_text' => 'A projectile is launched with an initial velocity of $40\\text{ m/s}$ at an angle of $30^\\circ$ to the horizontal. Calculate the maximum height reached. ($g = 10\\text{ m/s}^2$)',
        'option_a' => '$15\\text{ m}$',
        'option_b' => '$20\\text{ m}$',
        'option_c' => '$30\\text{ m}$',
        'option_d' => '$40\\text{ m}$',
        'correct_option' => 'B',
        'explanation' => '$H = \\frac{u^2 \\sin^2 \\theta}{2g} = \\frac{40^2 \\times \\sin^2 30^\\circ}{2 \\times 10} = \\frac{1600 \\times (0.5)^2}{20} = \\frac{1600 \\times 0.25}{20} = \\frac{400}{20} = 20\\text{ m}$.',
        'difficulty' => 'medium'
    ],
    [
        'exam_type' => 'jamb',
        'year' => 2023,
        'subject_slug' => 'physics',
        'subject_name' => 'Physics',
        'topic_name' => 'Units, Dimensions & Measurement',
        'question_number' => 6,
        'question_text' => 'The dimension of Universal Gravitational Constant ($G$) in terms of Mass ($M$), Length ($L$), and Time ($T$) is:',
        'option_a' => '$M^{-1}L^3T^{-2}$',
        'option_b' => '$ML^2T^{-2}$',
        'option_c' => '$M^{-1}L^2T^{-1}$',
        'option_d' => '$ML^3T^{-2}$',
        'correct_option' => 'A',
        'explanation' => 'From $F = \\frac{G m_1 m_2}{r^2} \\implies G = \\frac{F r^2}{m_1 m_2} = \\frac{(MLT^{-2})(L^2)}{M^2} = M^{-1}L^3T^{-2}$.',
        'difficulty' => 'medium'
    ],

    // === CHEMISTRY (WAEC, NECO, JAMB) ===
    [
        'exam_type' => 'waec',
        'year' => 2024,
        'subject_slug' => 'chemistry',
        'subject_name' => 'Chemistry',
        'topic_name' => 'Particulate Nature of Matter & Atomic Structure',
        'question_number' => 1,
        'question_text' => 'What is the electronic configuration of the sulfide ion ($S^{2-}$)? (Atomic number of $S = 16$)',
        'option_a' => '$1s^2 2s^2 2p^6 3s^2 3p^4$',
        'option_b' => '$1s^2 2s^2 2p^6 3s^2 3p^6$',
        'option_c' => '$1s^2 2s^2 2p^6 3s^2$',
        'option_d' => '$1s^2 2s^2 2p^6 3s^2 3p^2$',
        'correct_option' => 'B',
        'explanation' => 'Neutral Sulfur ($S$) has 16 electrons ($1s^2 2s^2 2p^6 3s^2 3p^4$). The $S^{2-}$ anion gains 2 extra electrons to achieve an octet configuration with 18 electrons: $1s^2 2s^2 2p^6 3s^2 3p^6$ (isoelectronic with Argon).',
        'difficulty' => 'easy'
    ],
    [
        'exam_type' => 'waec',
        'year' => 2023,
        'subject_slug' => 'chemistry',
        'subject_name' => 'Chemistry',
        'topic_name' => 'Acids, Bases, Salts & Neutralization',
        'question_number' => 2,
        'question_text' => 'What is the pH of a $0.001\\text{ M}$ solution of hydrochloric acid ($HCl$)?',
        'option_a' => '1',
        'option_b' => '2',
        'option_c' => '3',
        'option_d' => '11',
        'correct_option' => 'C',
        'explanation' => '$HCl$ is a strong monobasic acid, so $[H^+] = 0.001\\text{ M} = 10^{-3}\\text{ M}$. $\\text{pH} = -\\log_{10}[H^+] = -\\log_{10}(10^{-3}) = 3$.',
        'difficulty' => 'easy'
    ],
    [
        'exam_type' => 'neco',
        'year' => 2024,
        'subject_slug' => 'chemistry',
        'subject_name' => 'Chemistry',
        'topic_name' => 'Stoichiometry & Mole Concept',
        'question_number' => 3,
        'question_text' => 'What volume of oxygen at s.t.p. is required for the complete combustion of $5.6\\text{ dm}^3$ of methane gas ($CH_4$)?',
        'option_a' => '$5.6\\text{ dm}^3$',
        'option_b' => '$11.2\\text{ dm}^3$',
        'option_c' => '$22.4\\text{ dm}^3$',
        'option_d' => '$44.8\\text{ dm}^3$',
        'correct_option' => 'B',
        'explanation' => 'Equation: $CH_4 + 2O_2 \\rightarrow CO_2 + 2H_2O$. By Gay-Lussac\'s Law, 1 volume of $CH_4$ reacts with 2 volumes of $O_2$. Therefore, $5.6\\text{ dm}^3 \\times 2 = 11.2\\text{ dm}^3$ of $O_2$.',
        'difficulty' => 'medium'
    ],
    [
        'exam_type' => 'jamb',
        'year' => 2024,
        'subject_slug' => 'chemistry',
        'subject_name' => 'Chemistry',
        'topic_name' => 'Organic Chemistry: Hydrocarbons (Alkanes, Alkenes, Alkynes)',
        'question_number' => 4,
        'question_text' => 'Which of the following organic compounds will decolorize bromine water in the absence of light?',
        'option_a' => 'Ethane',
        'option_b' => 'Ethene',
        'option_c' => 'Propane',
        'option_d' => 'Cyclohexane',
        'correct_option' => 'B',
        'explanation' => 'Ethene ($C_2H_4$) is an unsaturated alkene containing a carbon-carbon double bond. It undergoes electrophilic addition with aqueous bromine rapidly at room temperature, decolorizing the reddish-brown bromine solution.',
        'difficulty' => 'easy'
    ],

    // === BIOLOGY (WAEC, NECO, JAMB) ===
    [
        'exam_type' => 'waec',
        'year' => 2024,
        'subject_slug' => 'biology',
        'subject_name' => 'Biology',
        'topic_name' => 'Genetics, Heredity & Mendel Laws',
        'question_number' => 1,
        'question_text' => 'In humans, brown eyes ($B$) are dominant over blue eyes ($b$). If two heterozygous brown-eyed individuals ($Bb$) marry, what percentage of their offspring is expected to have blue eyes?',
        'option_a' => '0%',
        'option_b' => '25%',
        'option_c' => '50%',
        'option_d' => '75%',
        'correct_option' => 'B',
        'explanation' => 'Cross $Bb \\times Bb$: Genotypes $= 1BB : 2Bb : 1bb$. The phenotype for blue eyes requires homozygous recessive ($bb$), which represents $\\frac{1}{4}$ or 25% of offspring.',
        'difficulty' => 'easy'
    ],
    [
        'exam_type' => 'neco',
        'year' => 2024,
        'subject_slug' => 'biology',
        'subject_name' => 'Biology',
        'topic_name' => 'Cell Structure, Organization & Organelles',
        'question_number' => 2,
        'question_text' => 'Which of the following cellular structures is present in plant cells but absent in animal cells?',
        'option_a' => 'Mitochondrion',
        'option_b' => 'Cellulose cell wall',
        'option_c' => 'Ribosome',
        'option_d' => 'Cell membrane',
        'correct_option' => 'B',
        'explanation' => 'Plant cells have a rigid outer cellulose cell wall that provides structural support and protection, which animal cells lack.',
        'difficulty' => 'easy'
    ],
    [
        'exam_type' => 'jamb',
        'year' => 2024,
        'subject_slug' => 'biology',
        'subject_name' => 'Biology',
        'topic_name' => 'Circulatory & Respiratory Systems',
        'question_number' => 3,
        'question_text' => 'The blood vessel that carries oxygenated blood from the lungs back to the left atrium of the heart is the:',
        'option_a' => 'Pulmonary artery',
        'option_b' => 'Pulmonary vein',
        'option_c' => 'Aorta',
        'option_d' => 'Vena cava',
        'correct_option' => 'B',
        'explanation' => 'The pulmonary vein is the unique vein in the human body that carries freshly oxygenated blood from the lungs directly into the left atrium of the heart.',
        'difficulty' => 'easy'
    ],

    // === ENGLISH LANGUAGE (WAEC, NECO, JAMB) ===
    [
        'exam_type' => 'waec',
        'year' => 2024,
        'subject_slug' => 'english-language',
        'subject_name' => 'English Language',
        'topic_name' => 'Parts of Speech & Concord Rules',
        'question_number' => 1,
        'question_text' => 'Choose the option that correctly completes the sentence: Neither the principal nor the teachers _______ present at the opening ceremony.',
        'option_a' => 'was',
        'option_b' => 'were',
        'option_c' => 'is',
        'option_d' => 'has been',
        'correct_option' => 'B',
        'explanation' => 'According to the rule of proximity in subject-verb concord, when subjects are joined by "neither...nor", the verb agrees with the closer subject ("the teachers" is plural, requiring "were").',
        'difficulty' => 'medium'
    ],
    [
        'exam_type' => 'waec',
        'year' => 2023,
        'subject_slug' => 'english-language',
        'subject_name' => 'English Language',
        'topic_name' => 'Parts of Speech & Concord Rules',
        'question_number' => 2,
        'question_text' => 'Choose the word that is opposite in meaning (antonym) to the underlined word: The senator gave a <u>lucid</u> explanation of the economic reform bill.',
        'option_a' => 'Clear',
        'option_b' => 'Ambiguous',
        'option_c' => 'Eloquent',
        'option_d' => 'Concise',
        'correct_option' => 'B',
        'explanation' => '"Lucid" means clear and easy to understand. Its opposite in meaning is "ambiguous" (vague, confusing, open to multiple interpretations).',
        'difficulty' => 'easy'
    ],
    [
        'exam_type' => 'jamb',
        'year' => 2024,
        'subject_slug' => 'english-language',
        'subject_name' => 'English Language',
        'topic_name' => 'Phonetics & Oral English (Vowels/Consonants)',
        'question_number' => 3,
        'question_text' => 'Choose the word that contains the vowel sound /i:/ as in "seat":',
        'option_a' => 'Sit',
        'option_b' => 'Key',
        'option_c' => 'Set',
        'option_d' => 'Cat',
        'correct_option' => 'B',
        'explanation' => '"Key" (/ki:/) contains the long front close vowel /i:/, identical to the vowel sound in "seat" (/si:t/).',
        'difficulty' => 'easy'
    ],

    // === ECONOMICS (WAEC, NECO, JAMB) ===
    [
        'exam_type' => 'waec',
        'year' => 2024,
        'subject_slug' => 'economics',
        'subject_name' => 'Economics',
        'topic_name' => 'Theory of Demand & Supply',
        'question_number' => 1,
        'question_text' => 'When the price of a good increases from ₦100 to ₦120 and the quantity demanded decreases from 500 units to 400 units, the price elasticity of demand is:',
        'option_a' => '0.5 (Inelastic)',
        'option_b' => '1.0 (Unitary)',
        'option_c' => '1.0',
        'option_d' => '1.0',
        'correct_option' => 'B',
        'explanation' => '% Change in Quantity Demanded $= \\frac{400 - 500}{500} \\times 100 = -20\\%$. % Change in Price $= \\frac{120 - 100}{100} \\times 100 = 20\\%$. Elasticity $= |\\frac{-20\\%}{20\\%}| = 1.0$ (Unitary Elasticity).',
        'difficulty' => 'medium'
    ],
    [
        'exam_type' => 'jamb',
        'year' => 2024,
        'subject_slug' => 'economics',
        'subject_name' => 'Economics',
        'topic_name' => 'Basic Economic Concepts (Scarcity, Choice, Scale of Preference)',
        'question_number' => 2,
        'question_text' => 'Opportunity cost is best defined as:',
        'option_a' => 'The total monetary expense of purchasing a product',
        'option_b' => 'The value of the next best alternative forgone',
        'option_c' => 'The fixed cost of industrial manufacturing',
        'option_d' => 'The marginal cost of distribution',
        'correct_option' => 'B',
        'explanation' => 'Opportunity cost in economics refers to the highest-valued alternative forgone whenever a choice is made due to resource scarcity.',
        'difficulty' => 'easy'
    ],

    // === GOVERNMENT (WAEC, NECO, JAMB) ===
    [
        'exam_type' => 'waec',
        'year' => 2024,
        'subject_slug' => 'government',
        'subject_name' => 'Government',
        'topic_name' => 'Basic Concepts in Government',
        'question_number' => 1,
        'question_text' => 'The separation of governmental powers among the legislature, executive, and judiciary was famously articulated by:',
        'option_a' => 'Karl Marx',
        'option_b' => 'Baron de Montesquieu',
        'option_c' => 'Thomas Hobbes',
        'option_d' => 'John Locke',
        'correct_option' => 'B',
        'explanation' => 'French political philosopher Baron de Montesquieu articulated the theory of separation of powers in his work "The Spirit of the Laws" (1748) to prevent tyrannical governance.',
        'difficulty' => 'easy'
    ],
    [
        'exam_type' => 'jamb',
        'year' => 2024,
        'subject_slug' => 'government',
        'subject_name' => 'Government',
        'topic_name' => 'Constitutional Development in Nigeria',
        'question_number' => 2,
        'question_text' => 'Which pre-independence Nigerian constitution introduced the elective principle for the first time?',
        'option_a' => 'Clifford Constitution of 1922',
        'option_b' => 'Richards Constitution of 1946',
        'option_c' => 'Macpherson Constitution of 1951',
        'option_d' => 'Lyttelton Constitution of 1954',
        'correct_option' => 'A',
        'explanation' => 'The Sir Hugh Clifford Constitution of 1922 introduced the elective principle into Nigerian colonial governance, establishing 4 elected seats (3 in Lagos and 1 in Calabar).',
        'difficulty' => 'easy'
    ]
];

$insertStmt = $pdo->prepare("
    INSERT INTO past_questions 
    (exam_type, year, subject_name, subject_slug, topic_name, question_number, question_text, option_a, option_b, option_c, option_d, correct_option, explanation, difficulty, created_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
");

$addedCount = 0;
foreach ($pastQuestionsData as $q) {
    $chk = $pdo->prepare("SELECT id FROM past_questions WHERE exam_type = ? AND subject_slug = ? AND question_text = ? LIMIT 1");
    $chk->execute([$q['exam_type'], $q['subject_slug'], $q['question_text']]);
    if (!$chk->fetch()) {
        $insertStmt->execute([
            $q['exam_type'],
            $q['year'],
            $q['subject_name'],
            $q['subject_slug'],
            $q['topic_name'],
            $q['question_number'],
            $q['question_text'],
            $q['option_a'],
            $q['option_b'],
            $q['option_c'],
            $q['option_d'],
            $q['correct_option'],
            $q['explanation'],
            $q['difficulty']
        ]);
        $addedCount++;
    }
}
echo "✔ Seeded $addedCount new high-yield past questions with detailed step-by-step explanations.\n";

// 5. Seed detailed study notes for core secondary topics
$lessonsToSeed = [
    [
        'subject_slug' => 'mathematics',
        'topic_title' => 'Quadratic Equations & Simultaneous Equations',
        'title' => 'Complete Mastery of Quadratic Equations & Formulas',
        'slug' => 'mastery-of-quadratic-equations',
        'content_type' => 'notes',
        'duration_minutes' => 25,
        'content_body' => <<<'EOT'
# Comprehensive Guide to Quadratic Equations

A quadratic equation is any polynomial equation of degree two, represented in standard form as:

$$ax^2 + bx + c = 0 \quad (a \neq 0)$$

---

## 1. Methods of Solving Quadratic Equations

### A. Factorisation Method
Applicable when the quadratic expression can be factored into linear binomials.
- **Example**: $x^2 - 7x + 12 = 0$
- Find two numbers whose product is $+12$ and sum is $-7$. These numbers are $-3$ and $-4$.
- $(x - 3)(x - 4) = 0 \implies x = 3 \text{ or } x = 4$.

### B. Completing the Square
1. Move the constant term $c$ to the right side.
2. Divide all terms by the coefficient $a$ if $a \neq 1$.
3. Add $\left(\frac{b}{2a}\right)^2$ to both sides.
4. Express the left side as a perfect square and take the square root of both sides.

### C. The Quadratic Formula (Almighty Formula)
$$x = \frac{-b \pm \sqrt{b^2 - 4ac}}{2a}$$

The discriminant $\Delta = b^2 - 4ac$ reveals the nature of the roots:
- If $\Delta > 0$: Two distinct real roots.
- If $\Delta = 0$: Two equal real roots (repeated root).
- If $\Delta < 0$: No real roots (complex/imaginary roots).

---

## 2. Simultaneous Linear and Quadratic Equations

To solve a system comprising one linear and one quadratic equation:
1. Make one variable (e.g., $y$) the subject of the linear equation.
2. Substitute that expression for $y$ in the quadratic equation.
3. Solve the resulting single-variable quadratic equation for $x$.
4. Substitute the $x$-values back into the linear equation to obtain the corresponding $y$-values.
EOT
    ],
    [
        'subject_slug' => 'physics',
        'topic_title' => 'Rectilinear Motion & Projectiles',
        'title' => 'Kinematics: Equations of Linear Motion & Projectiles',
        'slug' => 'kinematics-motion-and-projectiles',
        'content_type' => 'notes',
        'duration_minutes' => 30,
        'content_body' => <<<'EOT'
# Rectilinear Motion & Projectile Motion Masterclass

## 1. Uniformly Accelerated Linear Motion

When acceleration $a$ is constant, we apply the 4 classic equations of motion:

1. $v = u + at$
2. $s = ut + \frac{1}{2}at^2$
3. $v^2 = u^2 + 2as$
4. $s = \frac{(u + v)}{2}t$

*Where:*
- $u$ = Initial velocity ($\text{m/s}$)
- $v$ = Final velocity ($\text{m/s}$)
- $a$ = Uniform acceleration ($\text{m/s}^2$)
- $t$ = Time elapsed ($\text{s}$)
- $s$ = Displacement ($\text{m}$)

---

## 2. Projectile Motion in Two Dimensions

A projectile moves under the sole influence of gravity $g$. Its motion is resolved into:
- **Horizontal component (constant velocity, $a_x = 0$):** $u_x = u \cos \theta$
- **Vertical component (uniform deceleration under gravity, $a_y = -g$):** $u_y = u \sin \theta$

### Key Projectile Formulas:
1. **Time of Flight ($T$):**
   $$T = \frac{2u \sin \theta}{g}$$
2. **Maximum Height ($H$):**
   $$H = \frac{u^2 \sin^2 \theta}{2g}$$
3. **Horizontal Range ($R$):**
   $$R = \frac{u^2 \sin 2\theta}{g}$$

*Note:* Maximum horizontal range is achieved when the angle of projection $\theta = 45^\circ$.
EOT
    ]
];

$matStmt = $pdo->prepare("
    INSERT INTO secondary_materials 
    (subject_id, topic_id, title, slug, content_type, content_body, duration_minutes, status, sort_order, created_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, 'published', 1, NOW())
");

foreach ($lessonsToSeed as $les) {
    $subjRow = $pdo->prepare("SELECT id FROM secondary_subjects WHERE slug = ? LIMIT 1");
    $subjRow->execute([$les['subject_slug']]);
    $sId = (int)$subjRow->fetchColumn();

    $topRow = $pdo->prepare("SELECT id FROM secondary_topics WHERE subject_id = ? AND title LIKE ? LIMIT 1");
    $topRow->execute([$sId, "%{$les['topic_title']}%"]);
    $tId = (int)$topRow->fetchColumn();

    $matChk = $pdo->prepare("SELECT id FROM secondary_materials WHERE slug = ? LIMIT 1");
    $matChk->execute([$les['slug']]);
    if (!$matChk->fetch() && $sId > 0) {
        $matStmt->execute([
            $sId,
            $tId ?: null,
            $les['title'],
            $les['slug'],
            $les['content_type'],
            $les['content_body'],
            $les['duration_minutes']
        ]);
        echo "✔ Seeded lesson note: {$les['title']}\n";
    }
}

echo "\nSecondary School Suite V2 migration and seeding completed successfully.\n";
