<?php

require_once dirname(__DIR__) . '/config/main.php';

$pdo = getDBConnection();
echo "Starting StudyMe Master Fix Migration...\n";

$pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");

$plans = [
    ['slug' => 'secondary-student', 'price' => 3000.00, 'name' => 'Secondary Student'],
    ['slug' => 'university-student', 'price' => 5000.00, 'name' => 'University Student'],
    ['slug' => 'teacher',            'price' => 4000.00, 'name' => 'Teacher Plan'],
    ['slug' => 'tech',               'price' => 10000.00, 'name' => 'Tech Plan'],
];

foreach ($plans as $p) {
    $stmt = $pdo->prepare("UPDATE subscription_plans SET price = ?, name = ? WHERE slug = ?");
    $stmt->execute([$p['price'], $p['name'], $p['slug']]);
}
echo "✔ Updated subscription_plans to official rates.\n";

$settings = [
    'price_tech'       => '10000.00',
    'price_secondary'  => '3000.00',
    'price_university' => '5000.00',
    'price_teacher'    => '4000.00',
    'free_testing_mode'=> '1',
    'bonus_rate_teacher'    => '1500.00',
    'bonus_rate_university' => '1000.00',
    'bonus_rate_secondary'  => '1000.00',
];

foreach ($settings as $k => $v) {
    $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    $stmt->execute([$k, $v]);
}
echo "✔ Updated settings table pricing and referral bonus rates.\n";

$pdo->exec("
    CREATE TABLE IF NOT EXISTS wallet_transactions (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT UNSIGNED NOT NULL,
        type ENUM('referral_bonus', 'withdrawal', 'withdrawal_refund', 'adjustment') NOT NULL,
        amount DECIMAL(12,2) NOT NULL,
        description VARCHAR(255) NOT NULL,
        balance_after DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        reference VARCHAR(100) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_trans (user_id),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");
echo "✔ Ensured wallet_transactions table exists.\n";

try {
    $pdo->exec("ALTER TABLE teachers ADD COLUMN education TEXT NULL AFTER qualification");
} catch (Exception $e) {  }

try {
    $pdo->exec("ALTER TABLE teachers ADD COLUMN skills TEXT NULL AFTER experience_years");
} catch (Exception $e) {  }

try {
    $pdo->exec("ALTER TABLE courses MODIFY teacher_id BIGINT UNSIGNED NULL DEFAULT NULL");
} catch (Exception $e) {  }
echo "✔ Ensured teachers table has education and skills columns, and courses.teacher_id is nullable.\n";

$passHash = password_hash('Password123!', PASSWORD_DEFAULT);

$defaultUsers = [
    [
        'id' => 1,
        'first_name' => 'System',
        'last_name'  => 'Admin',
        'username'   => 'admin',
        'email'      => 'admin@studyme.ng',
        'phone'      => '+2348012345678',
        'role'       => 'admin',
        'status'     => 'active'
    ],
    [
        'id' => 2,
        'first_name' => 'Dr. Sarah',
        'last_name'  => 'Jenkins',
        'username'   => 'sarah_teacher',
        'email'      => 'teacher@studyme.ng',
        'phone'      => '+2348023456789',
        'role'       => 'teacher',
        'status'     => 'active'
    ],
    [
        'id' => 3,
        'first_name' => 'Alex',
        'last_name'  => 'Rivera',
        'username'   => 'alex_teacher',
        'email'      => 'alex@studyme.ng',
        'phone'      => '+2348034567890',
        'role'       => 'teacher',
        'status'     => 'active'
    ],
    [
        'id' => 4,
        'first_name' => 'David',
        'last_name'  => 'Okonkwo',
        'username'   => 'david_student',
        'email'      => 'student@studyme.ng',
        'phone'      => '+2348045678901',
        'role'       => 'student',
        'status'     => 'active'
    ],
];

foreach ($defaultUsers as $u) {
    $stmt = $pdo->prepare("
        INSERT INTO users (id, first_name, last_name, username, email, password, phone, role, status, email_verified_at, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ON DUPLICATE KEY UPDATE
            first_name = VALUES(first_name),
            last_name = VALUES(last_name),
            username = VALUES(username),
            password = VALUES(password),
            role = VALUES(role),
            status = 'active'
    ");
    $stmt->execute([
        $u['id'], $u['first_name'], $u['last_name'], $u['username'],
        $u['email'], $passHash, $u['phone'], $u['role'], $u['status']
    ]);
}
echo "✔ Standard default accounts verified (admin, teacher, alex, student).\n";

$pdo->exec("
    INSERT INTO teachers (id, user_id, teacher_number, qualification, specialization, experience_years, bio, rating, total_students, total_courses, status)
    VALUES
    (1, 2, 'TCH-001', 'Ph.D. in Computer Science', 'Artificial Intelligence & Full-Stack Development', 10, 'Passionate educator and AI researcher dedicated to making high-tech concepts accessible to everyone.', 4.95, 3420, 4, 'active'),
    (2, 3, 'TCH-002', 'M.Sc. in Human Computer Interaction', 'UI/UX Design & Frontend Architecture', 8, 'Former lead designer at FinTech unicorns, helping students master human-centric product design.', 4.88, 2150, 3, 'active')
    ON DUPLICATE KEY UPDATE
        qualification = VALUES(qualification),
        specialization = VALUES(specialization),
        bio = VALUES(bio),
        status = 'active';
");

$pdo->exec("
    INSERT INTO students (id, user_id, student_number, date_of_birth, gender, bio, city, country)
    VALUES
    (1, 4, 'STD-2026-0042', '2002-05-14', 'male', 'Aspiring software engineer and university student learning with StudyMe AI.', 'Lagos', 'Nigeria')
    ON DUPLICATE KEY UPDATE student_number = VALUES(student_number);
");

$catStmt = $pdo->query("SELECT id, slug FROM categories");
$catMap = [];
while ($row = $catStmt->fetch(PDO::FETCH_ASSOC)) {
    $catMap[$row['slug']] = (int)$row['id'];
}

$sarahTId = (int)$pdo->query("SELECT id FROM teachers WHERE user_id = 2 LIMIT 1")->fetchColumn();
$alexTId  = (int)$pdo->query("SELECT id FROM teachers WHERE user_id = 3 LIMIT 1")->fetchColumn();

$pdo->query("UPDATE courses SET teacher_id = NULL");

if ($sarahTId) {
    $firstCsId = (int)$pdo->query("SELECT id FROM courses WHERE title LIKE '%Computer Science%' OR slug LIKE '%computer-science%' LIMIT 1")->fetchColumn();
    if (!$firstCsId) {
        $firstCsId = (int)$pdo->query("SELECT id FROM courses WHERE category_id = 8 LIMIT 1")->fetchColumn();
    }
    if ($firstCsId) {
        $pdo->prepare("UPDATE courses SET teacher_id = ? WHERE id = ?")->execute([$sarahTId, $firstCsId]);
        $pdo->prepare("UPDATE teachers SET assigned_course_id = ?, qualification = 'Ph.D. in Computer Science', specialization = 'Artificial Intelligence & Data Systems', experience_years = 12, bio = 'Senior academic researcher and educator specializing in AI, distributed systems, and machine learning.' WHERE id = ?")
            ->execute([$firstCsId, $sarahTId]);
    }
}

if ($alexTId) {
    $firstTechId = (int)$pdo->query("SELECT id FROM courses WHERE title LIKE '%Web Development%' OR slug LIKE '%web-development%' LIMIT 1")->fetchColumn();
    if (!$firstTechId) {
        $firstTechId = (int)$pdo->query("SELECT id FROM courses WHERE category_id = 6 LIMIT 1")->fetchColumn();
    }
    if ($firstTechId) {
        $pdo->prepare("UPDATE courses SET teacher_id = ? WHERE id = ?")->execute([$alexTId, $firstTechId]);
        $pdo->prepare("UPDATE teachers SET assigned_course_id = ?, qualification = 'M.Sc. Software Engineering', specialization = 'Full Stack Web Architecture', experience_years = 8, bio = 'Lead software architect with 8+ years developing scalable web applications and instructing modern developer bootcamps.' WHERE id = ?")
            ->execute([$firstTechId, $alexTId]);
    }
}

echo "Assigned Sarah Jenkins (TID $sarahTId) and Alex Rivera (TID $alexTId). All other courses set to Teacher: Currently unavailable.\n";

$uniCatId  = $catMap['university'] ?? 8;
$techCatId = $catMap['technology'] ?? 6;
$secCatId  = $catMap['secondary-waec-neco'] ?? 7;

$pdo->prepare("UPDATE courses SET price = 10000.00 WHERE category_id = ?")->execute([$techCatId]);

$pdo->prepare("UPDATE courses SET price = 3000.00, teacher_id = NULL WHERE category_id = ?")->execute([$secCatId]);

$pdo->prepare("UPDATE courses SET price = 5000.00 WHERE category_id = ?")->execute([$uniCatId]);

echo "✔ Standardized existing course prices (Tech: ₦10k, Sec: ₦3k, Uni: ₦5k).\n";

$uniCatalog = [

    ['Civil Engineering', 'civil-engineering', 'Structural analysis, concrete technology, highway engineering, and sustainable urban infrastructure.', 'https://images.unsplash.com/photo-1581092160607-ee22621dd758?w=600&q=80'],
    ['Mechanical Engineering', 'mechanical-engineering', 'Thermodynamics, fluid mechanics, machine design, CAD/CAM, and automotive mechanics.', 'https://images.unsplash.com/photo-1537462715879-360eeb61a0ad?w=600&q=80'],
    ['Electrical Engineering', 'electrical-engineering', 'Circuit analysis, power distribution, transformers, electrical machines, and high-voltage systems.', 'https://images.unsplash.com/photo-1473341304170-971dccb5ac1e?w=600&q=80'],
    ['Electronics Engineering', 'electronics-engineering', 'Semiconductors, microprocessors, embedded systems, analog/digital filters, and PCB design.', 'https://images.unsplash.com/photo-1517077304055-6e89abbf09b0?w=600&q=80'],
    ['Electrical/Electronics Engineering', 'electrical-electronics-engineering', 'Combined power engineering, modern control theory, telecommunications, and digital hardware.', 'https://images.unsplash.com/photo-1509228468518-180dd4864904?w=600&q=80'],
    ['Computer Engineering', 'computer-engineering', 'Computer architecture, VLSI design, robotics, firmware development, and hardware-software co-design.', 'https://images.unsplash.com/photo-1550751827-4bd374c3f58b?w=600&q=80'],
    ['Chemical Engineering', 'chemical-engineering', 'Mass transfer, chemical reaction kinetics, process control, distillation, and refinery plant operations.', 'https://images.unsplash.com/photo-1532094349884-543bc11b234d?w=600&q=80'],
    ['Petroleum Engineering', 'petroleum-engineering', 'Reservoir engineering, drilling technology, well logging, production optimization, and pipeline safety.', 'https://images.unsplash.com/photo-1518709268805-4e9042af9f23?w=600&q=80'],
    ['Marine Engineering', 'marine-engineering', 'Ship propulsion systems, naval architecture, marine power plants, and offshore structural dynamics.', 'https://images.unsplash.com/photo-1505705694340-019e1e335916?w=600&q=80'],
    ['Mechatronics Engineering', 'mechatronics-engineering', 'Robotics, automation, programmable logic controllers (PLCs), sensors, and electromechanical integration.', 'https://images.unsplash.com/photo-1485827404703-89b55fcc595e?w=600&q=80'],
    ['Biomedical Engineering', 'biomedical-engineering', 'Medical imaging systems, biomechanics, biomaterials, prosthetic design, and clinical instrumentation.', 'https://images.unsplash.com/photo-1530497610245-94d3c16cda28?w=600&q=80'],
    ['Agricultural Engineering', 'agricultural-engineering', 'Farm mechanization, irrigation systems, post-harvest processing, and soil-water conservation.', 'https://images.unsplash.com/photo-1500937386664-56d1dfef3854?w=600&q=80'],
    ['Environmental Engineering', 'environmental-engineering', 'Water and wastewater treatment, air pollution monitoring, solid waste management, and EIA compliance.', 'https://images.unsplash.com/photo-1497435334941-8c899ee9e8e9?w=600&q=80'],
    ['Structural Engineering', 'structural-engineering', 'Advanced structural dynamics, earthquake-resistant design, steel frames, and bridge engineering.', 'https://images.unsplash.com/photo-1541888946425-d0fbb18015f5?w=600&q=80'],
    ['Water Resources Engineering', 'water-resources-engineering', 'Hydrology, open channel flow, dam engineering, groundwater modeling, and flood mitigation.', 'https://images.unsplash.com/photo-1468421870903-4df1664ac249?w=600&q=80'],
    ['Telecommunications Engineering', 'telecommunications-engineering', 'Wireless networks, fiber optics, 5G architectures, RF propagation, and satellite links.', 'https://images.unsplash.com/photo-1544197150-b99a580bb7a8?w=600&q=80'],
    ['Industrial Engineering', 'industrial-engineering', 'Operations research, supply chain optimization, ergonomics, production scheduling, and Six Sigma.', 'https://images.unsplash.com/photo-1581091226825-a6a2a5aee158?w=600&q=80'],
    ['Production Engineering', 'production-engineering', 'Manufacturing processes, CNC machining, metal forming, quality assurance, and assembly automation.', 'https://images.unsplash.com/photo-1581092335397-9583fe92d232?w=600&q=80'],
    ['Materials & Metallurgical Engineering', 'materials-metallurgical-engineering', 'Phase diagrams, metallurgy, polymers, corrosion prevention, and nanomaterials synthesis.', 'https://images.unsplash.com/photo-1563986768609-322da13575f3?w=600&q=80'],
    ['Mining Engineering', 'mining-engineering', 'Mineral exploration, surface and underground mining, rock mechanics, and mine ventilation systems.', 'https://images.unsplash.com/photo-1578328819058-b69f3a3b0f6b?w=600&q=80'],
    ['Aeronautical & Aerospace Engineering', 'aeronautical-aerospace-engineering', 'Aerodynamics, flight mechanics, propulsion systems, avionics, and space trajectory modeling.', 'https://images.unsplash.com/photo-1517976487502-86ec5e34ebfb?w=600&q=80'],
    ['Automotive Engineering', 'automotive-engineering', 'Vehicle dynamics, internal combustion engines, EV powertrains, chassis tuning, and crash safety.', 'https://images.unsplash.com/photo-1503376780353-7e6692767b70?w=600&q=80'],
    ['Software Engineering (University Core)', 'software-engineering-uni', 'Software design patterns, microservices, testing methodologies, DevOps, and enterprise systems.', 'https://images.unsplash.com/photo-1555066931-4365d14bab8c?w=600&q=80'],

    ['Computer Science', 'computer-science-uni', 'Data structures, algorithms, discrete mathematics, theory of computation, and operating systems.', 'https://images.unsplash.com/photo-1517694712202-14dd9538aa97?w=600&q=80'],
    ['Information Technology', 'information-technology-uni', 'Enterprise networking, system administration, cloud storage, IT governance, and IT security.', 'https://images.unsplash.com/photo-1519389950473-47ba0277781c?w=600&q=80'],
    ['Information Systems', 'information-systems', 'Database systems, business process modeling, enterprise resource planning (ERP), and business intelligence.', 'https://images.unsplash.com/photo-1460925895917-afdab827c52f?w=600&q=80'],
    ['Cybersecurity', 'cybersecurity-uni', 'Network security, ethical hacking, cryptography, threat intelligence, and digital forensics.', 'https://images.unsplash.com/photo-1550751827-4bd374c3f58b?w=600&q=80'],
    ['Data Science', 'data-science-uni', 'Statistical learning, Python data stack, predictive modeling, machine learning, and big data analysis.', 'https://images.unsplash.com/photo-1551288049-bebda4e38f71?w=600&q=80'],
    ['Artificial Intelligence', 'artificial-intelligence-uni', 'Neural networks, deep learning, NLP, computer vision, reinforcement learning, and AI ethics.', 'https://images.unsplash.com/photo-1618005182384-a83a8bd57fbe?w=600&q=80'],
    ['Networking & Telecommunications', 'networking-telecom', 'TCP/IP protocols, routing and switching, network administration, subnetting, and VoIP systems.', 'https://images.unsplash.com/photo-1544197150-b99a580bb7a8?w=600&q=80'],
    ['Cloud Computing', 'cloud-computing-uni', 'AWS, Azure, cloud architectures, container orchestration with Kubernetes, and serverless backends.', 'https://images.unsplash.com/photo-1451187580459-43490279c0fa?w=600&q=80'],
    ['Data Analytics', 'data-analytics-uni', 'SQL querying, Tableau/PowerBI visualization, exploratory data analysis, and metric dashboarding.', 'https://images.unsplash.com/photo-1543286386-713bdd548da4?w=600&q=80'],
    ['Database Management Systems', 'database-management-uni', 'Relational database theory, normalization, indexing, transaction processing, and NoSQL databases.', 'https://images.unsplash.com/photo-1544383835-bda2bc66a55d?w=600&q=80'],

    ['Medicine & Surgery', 'medicine-and-surgery', 'Clinical pathology, pharmacology, general surgery, internal medicine, and patient diagnostics.', 'https://images.unsplash.com/photo-1576091160399-112ba8d25d1d?w=600&q=80'],
    ['Nursing Science', 'nursing-science', 'Patient care, community nursing, pharmacology for nurses, emergency medicine, and maternal health.', 'https://images.unsplash.com/photo-1584515979956-d9f6e5d09982?w=600&q=80'],
    ['Pharmacy', 'pharmacy-uni', 'Pharmaceutics, medicinal chemistry, clinical pharmacokinetics, toxicology, and drug compounding.', 'https://images.unsplash.com/photo-1587854692152-cbe660dbde88?w=600&q=80'],
    ['Medical Laboratory Science', 'medical-laboratory-science', 'Hematology, clinical chemistry, histopathology, medical microbiology, and blood banking.', 'https://images.unsplash.com/photo-1579154204601-01588f351e67?w=600&q=80'],
    ['Dentistry & Dental Surgery', 'dentistry-dental-surgery', 'Oral pathology, orthodontics, periodontics, restorative dentistry, and maxillofacial surgery.', 'https://images.unsplash.com/photo-1606811841689-23dfddce3e95?w=600&q=80'],
    ['Physiotherapy', 'physiotherapy', 'Musculoskeletal rehabilitation, exercise physiology, electrotherapy, and sports injury management.', 'https://images.unsplash.com/photo-1576091160550-2173dba999ef?w=600&q=80'],
    ['Radiography & Radiation Science', 'radiography-radiation-science', 'X-ray physics, MRI, CT scanning, ultrasound imaging, and radiation protection safety.', 'https://images.unsplash.com/photo-1516549655169-df83a0774514?w=600&q=80'],
    ['Public Health', 'public-health-uni', 'Epidemiology, biostatistics, global health policy, health promotion, and disease surveillance.', 'https://images.unsplash.com/photo-1505751172876-fa1923c5c528?w=600&q=80'],
    ['Human Anatomy', 'human-anatomy', 'Gross anatomy, neuroanatomy, embryology, histology, and functional biomechanics.', 'https://images.unsplash.com/photo-1530210124550-912dc1381cb8?w=600&q=80'],
    ['Human Physiology', 'human-physiology', 'Cardiovascular, respiratory, renal, endocrine, and neural systems physiology.', 'https://images.unsplash.com/photo-1532187863486-abf9dbad1b69?w=600&q=80'],
    ['Medical Biochemistry', 'medical-biochemistry', 'Metabolic pathways, enzymology, molecular genetics, lipid metabolism, and clinical enzymology.', 'https://images.unsplash.com/photo-1532094349884-543bc11b234d?w=600&q=80'],
    ['Medical Microbiology', 'medical-microbiology', 'Bacteriology, virology, mycology, parasitology, antimicrobial resistance, and immunology.', 'https://images.unsplash.com/photo-1583912267670-6575ad472688?w=600&q=80'],
    ['Nutrition & Dietetics', 'nutrition-and-dietetics', 'Clinical nutrition, food chemistry, diet therapy, community nutrition, and therapeutic meal plans.', 'https://images.unsplash.com/photo-1490645935967-10de6ba17061?w=600&q=80'],
    ['Medical Rehabilitation', 'medical-rehabilitation', 'Occupational therapy, neuro-rehabilitation, assistive technology, and prosthetic rehabilitation.', 'https://images.unsplash.com/photo-1584515933487-779824d29309?w=600&q=80'],
    ['Optometry', 'optometry', 'Ocular anatomy, visual optics, contact lens practice, binocular vision, and ocular pharmacology.', 'https://images.unsplash.com/photo-1591076482161-42ce6da69f67?w=600&q=80'],
    ['Biomedical Science', 'biomedical-science', 'Molecular biology, cellular pathology, medical genetics, and clinical diagnostics.', 'https://images.unsplash.com/photo-1532187863486-abf9dbad1b69?w=600&q=80'],

    ['Accounting', 'accounting-uni', 'Financial accounting, managerial accounting, auditing, taxation, and international financial reporting (IFRS).', 'https://images.unsplash.com/photo-1554224155-8d04cb21cd6c?w=600&q=80'],
    ['Business Administration', 'business-administration-uni', 'Organizational behavior, corporate strategy, operations management, leadership, and entrepreneurship.', 'https://images.unsplash.com/photo-1507679799987-c73779587ccf?w=600&q=80'],
    ['Banking & Finance', 'banking-and-finance', 'Corporate finance, investment analysis, financial markets, commercial banking, and risk management.', 'https://images.unsplash.com/photo-1611974789855-9c2a0a7236a3?w=600&q=80'],
    ['Economics (Undergraduate)', 'economics-uni', 'Microeconomic theory, macroeconomic policy, econometrics, monetary economics, and development.', 'https://images.unsplash.com/photo-1611974789855-9c2a0a7236a3?w=600&q=80'],
    ['Marketing', 'marketing-uni', 'Consumer behavior, digital marketing strategy, brand management, market research, and sales strategy.', 'https://images.unsplash.com/photo-1460925895917-afdab827c52f?w=600&q=80'],
    ['Entrepreneurship', 'entrepreneurship-uni', 'Venture creation, business models, startup financing, innovation management, and intellectual property.', 'https://images.unsplash.com/photo-1556761175-5973dc0f32e7?w=600&q=80'],
    ['Insurance & Risk Management', 'insurance-risk-management', 'Actuarial principles, underwriting, life and non-life insurance, reinsurance, and claims management.', 'https://images.unsplash.com/photo-1450133064473-71024230f91b?w=600&q=80'],
    ['Actuarial Science', 'actuarial-science', 'Financial mathematics, probability theory, life contingencies, statistical loss models, and risk analysis.', 'https://images.unsplash.com/photo-1551288049-bebda4e38f71?w=600&q=80'],
    ['Management', 'management-uni', 'Principles of management, strategic planning, corporate governance, and change management.', 'https://images.unsplash.com/photo-1519389950473-47ba0277781c?w=600&q=80'],
    ['Human Resources Management', 'human-resources-management', 'Talent acquisition, employee relations, compensation and benefits, labor law, and organizational training.', 'https://images.unsplash.com/photo-1521737604893-d14cc237f11d?w=600&q=80'],
    ['Procurement & Supply Chain Management', 'procurement-supply-chain', 'Strategic sourcing, logistics management, inventory control, vendor contracts, and global trade.', 'https://images.unsplash.com/photo-1586528116311-ad8dd3c8310d?w=600&q=80'],
    ['Logistics & Transport Management', 'logistics-transport-management', 'Fleet management, multimodal transport, supply chain logistics, freight forwarding, and warehousing.', 'https://images.unsplash.com/photo-1519003722824-194d4455a60c?w=600&q=80'],
    ['International Business', 'international-business', 'Global business environments, international trade policy, cross-border marketing, and FX risk.', 'https://images.unsplash.com/photo-1526304640581-d334cdbbf45e?w=600&q=80'],

    ['Political Science', 'political-science', 'Comparative politics, political theory, public policy analysis, governance, and political economy.', 'https://images.unsplash.com/photo-1541872703-74c5e44368f9?w=600&q=80'],
    ['Sociology', 'sociology-uni', 'Social structure, cultural sociology, research methodology, social stratification, and urban sociology.', 'https://images.unsplash.com/photo-1529156069898-49953e39b3ac?w=600&q=80'],
    ['Psychology', 'psychology-uni', 'Cognitive psychology, social psychology, developmental psychology, psychopathology, and research ethics.', 'https://images.unsplash.com/photo-1507679799987-c73779587ccf?w=600&q=80'],
    ['Mass Communication', 'mass-communication', 'Media theory, print journalism, broadcasting, public relations, advertising, and digital communications.', 'https://images.unsplash.com/photo-1495020689067-958852a7765e?w=600&q=80'],
    ['Journalism', 'journalism-uni', 'Investigative reporting, news writing, media law and ethics, multimedia production, and photojournalism.', 'https://images.unsplash.com/photo-1504711434969-e33886168f5c?w=600&q=80'],
    ['International Relations', 'international-relations', 'Diplomacy, foreign policy, international law, conflict resolution, and multilateral institutions.', 'https://images.unsplash.com/photo-1526304640581-d334cdbbf45e?w=600&q=80'],
    ['Criminology & Security Studies', 'criminology-security-studies', 'Theories of crime, criminal justice system, forensic science, policing, and intelligence analysis.', 'https://images.unsplash.com/photo-1589829545856-d10d557cf95f?w=600&q=80'],
    ['Peace & Conflict Studies', 'peace-and-conflict-studies', 'Conflict analysis, mediation techniques, post-war peacebuilding, transitional justice, and human rights.', 'https://images.unsplash.com/photo-1469571486292-0ba58a3f068b?w=600&q=80'],
    ['Social Work', 'social-work', 'Community development, social welfare policy, counseling psychology, family therapy, and child protection.', 'https://images.unsplash.com/photo-1593113598332-cd288d649433?w=600&q=80'],
    ['Geography & Environmental Management', 'geography-environmental-management', 'Geographic information systems (GIS), climatology, cartography, remote sensing, and land resource planning.', 'https://images.unsplash.com/photo-1524661135-423995f22d0b?w=600&q=80'],
    ['Development Studies', 'development-studies', 'Sustainable development, poverty alleviation, rural economics, NGO management, and development finance.', 'https://images.unsplash.com/photo-1488521787991-ed7bbaae773c?w=600&q=80'],

    ['Commercial & Corporate Law', 'commercial-corporate-law', 'Company law, corporate finance law, consumer rights, intellectual property, and arbitration.', 'https://images.unsplash.com/photo-1589829545856-d10d557cf95f?w=600&q=80'],
    ['Constitutional & Administrative Law', 'constitutional-administrative-law', 'Rule of law, separation of powers, fundamental human rights, judicial review, and state powers.', 'https://images.unsplash.com/photo-1453733190371-0a9bedd82893?w=600&q=80'],
    ['Criminal Law & Procedure', 'criminal-law-procedure', 'Elements of crime, criminal liability, trial procedure, evidence presentation, and sentencing jurisprudence.', 'https://images.unsplash.com/photo-1589829545856-d10d557cf95f?w=600&q=80'],

    ['Physics & Classical Mechanics', 'physics-classical-mechanics', 'Newtonian mechanics, electrodynamics, optics, quantum theory, and statistical thermodynamics.', 'https://images.unsplash.com/photo-1636466497217-26a8cbeaf0aa?w=600&q=80'],
    ['General & Organic Chemistry', 'general-organic-chemistry', 'Chemical bonding, stereochemistry, organic synthesis mechanisms, spectroscopy, and kinetics.', 'https://images.unsplash.com/photo-1532094349884-543bc11b234d?w=600&q=80'],
    ['Calculus & Linear Algebra', 'calculus-linear-algebra', 'Multivariable calculus, differential equations, vector spaces, eigenvalues, and mathematical analysis.', 'https://images.unsplash.com/photo-1509228468518-180dd4864904?w=600&q=80'],
    ['Molecular Biology & Genetics', 'molecular-biology-genetics', 'DNA replication, transcription, gene expression regulation, recombinant DNA, and genomics.', 'https://images.unsplash.com/photo-1530210124550-912dc1381cb8?w=600&q=80'],

    ['English Language & Literary Studies', 'english-literary-studies', 'English syntax, phonology, African literature, post-colonial discourse, and creative writing.', 'https://images.unsplash.com/photo-1456513080510-7bf3a84b82f8?w=600&q=80'],
    ['History & International Studies', 'history-international-studies', 'African historical development, world civilizations, diplomatic history, and historiography.', 'https://images.unsplash.com/photo-1461360370896-922624d12aa1?w=600&q=80'],
    ['Philosophy & Critical Logic', 'philosophy-critical-logic', 'Epistemology, formal deductive logic, moral philosophy, metaphysics, and philosophical inquiry.', 'https://images.unsplash.com/photo-1507679799987-c73779587ccf?w=600&q=80'],

    ['Educational Management & Planning', 'educational-management-planning', 'School administration, curriculum implementation, policy formulation, and institutional leadership.', 'https://images.unsplash.com/photo-1524178232363-1fb2b075b655?w=600&q=80'],
    ['Guidance & Counseling Psychology', 'guidance-counseling-psychology', 'Educational counseling, psychological assessment, behavioral therapy, and career guidance.', 'https://images.unsplash.com/photo-1577495508048-b635879837f1?w=600&q=80'],

    ['Agronomy & Crop Science', 'agronomy-crop-science', 'Crop production systems, plant breeding, soil fertility management, and weed biology.', 'https://images.unsplash.com/photo-1500937386664-56d1dfef3854?w=600&q=80'],
    ['Animal Science & Livestock Production', 'animal-science-livestock', 'Animal genetics, feeds and nutrition, veterinary health, livestock housing, and dairy management.', 'https://images.unsplash.com/photo-1516467508483-a7212febe31a?w=600&q=80'],
    ['Agricultural Economics & Agribusiness', 'agricultural-economics-agribusiness', 'Farm financial management, agricultural policy, commodity marketing, and value-chain development.', 'https://images.unsplash.com/photo-1595974482597-4b8da8879bc5?w=600&q=80'],

    ['Architecture & Spatial Design', 'architecture-spatial-design', 'Architectural design studio, building construction technology, environmental systems, and Revit/BIM.', 'https://images.unsplash.com/photo-1513694203232-719a280e022f?w=600&q=80'],
    ['Quantity Surveying & Cost Engineering', 'quantity-surveying-cost-engineering', 'Construction cost estimation, bills of quantities (BOQ), contract administration, and project valuation.', 'https://images.unsplash.com/photo-1504307651254-35680f356dfd?w=600&q=80'],
    ['Estate Management & Valuation', 'estate-management-valuation', 'Property valuation methodology, land economy, real estate development, and property law.', 'https://images.unsplash.com/photo-1560518883-ce09059eeffa?w=600&q=80'],
    ['Urban & Regional Planning', 'urban-regional-planning', 'Land use planning, transportation planning, urban governance, GIS mapping, and environmental sustainability.', 'https://images.unsplash.com/photo-1477959858617-67f30bc75b82?w=600&q=80'],
];

$stmtInsertCourse = $pdo->prepare("
    INSERT INTO courses (category_id, teacher_id, title, slug, short_description, description, level, price, duration_minutes, thumbnail, status, featured, certificate_enabled, created_at, updated_at)
    VALUES (?, NULL, ?, ?, ?, ?, 'beginner', 5000.00, 2400, ?, 'published', 0, 1, NOW(), NOW())
    ON DUPLICATE KEY UPDATE
        price = 5000.00,
        status = 'published',
        short_description = VALUES(short_description),
        thumbnail = IF(thumbnail IS NULL OR thumbnail = '', VALUES(thumbnail), thumbnail)
");

$insertedCount = 0;
foreach ($uniCatalog as $item) {
    $title = $item[0];
    $slug  = $item[1];
    $short = $item[2];
    $thumb = $item[3];
    $desc  = "Comprehensive undergraduate study in {$title}. Covers theoretical foundations, practical problem-solving methodologies, university examinations review, and 24/7 AI tutor guidance.\n\nWhat You Will Learn:\n• Core foundational concepts and curriculum mastery in {$title}\n• Analytical frameworks, formulas, and real-world problem sets\n• Exam preparation and module evaluation checkpoints\n• 24/7 AI Tutor homework assistance and concept breakdowns\n\nPrerequisites:\n• University undergraduate registration or foundational secondary school knowledge\n• Dedication to consistent self-paced and AI-guided learning.";

    $stmtInsertCourse->execute([$uniCatId, $title, $slug, $short, $desc, $thumb]);
    $insertedCount++;
}

echo "✔ Successfully synced {$insertedCount} University courses under official price ₦5,000.\n";

$pdo->exec("UPDATE courses SET teacher_id = NULL WHERE teacher_id IS NOT NULL AND teacher_id NOT IN (SELECT id FROM teachers)");

$pdo->exec("UPDATE courses SET teacher_id = 1 WHERE slug = 'computer-science-uni' OR slug = 'computer-science'");

echo "✔ Assigned Dr. Sarah Jenkins (Teacher ID: 1) to Computer Science (Undergraduate Core).\n";
echo "✔ All other University courses have teacher_id = NULL ('Teacher: Currently unavailable').\n";
echo "All master fix migrations completed successfully!\n";
