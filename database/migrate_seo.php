<?php
/**
 * StudyMe AI Platform — SEO Schema Database Migration
 * Adds SEO fields (seo_title, seo_description, seo_keywords) and seeds tailored metadata.
 */
require_once dirname(__DIR__) . '/config/main.php';

$pdo = getDBConnection();
echo "Starting SEO Schema Migration...\n";

// 1. Add SEO columns to courses table if they don't exist
$cols = $pdo->query("SHOW COLUMNS FROM courses")->fetchAll(PDO::FETCH_COLUMN);
if (!in_array('seo_title', $cols)) {
    $pdo->exec("ALTER TABLE courses ADD COLUMN seo_title VARCHAR(255) NULL AFTER title");
    echo "✔ Added seo_title to courses\n";
}
if (!in_array('seo_description', $cols)) {
    $pdo->exec("ALTER TABLE courses ADD COLUMN seo_description TEXT NULL AFTER description");
    echo "✔ Added seo_description to courses\n";
}
if (!in_array('seo_keywords', $cols)) {
    $pdo->exec("ALTER TABLE courses ADD COLUMN seo_keywords VARCHAR(255) NULL AFTER seo_description");
    echo "✔ Added seo_keywords to courses\n";
}

// 2. Add SEO columns to categories table if they don't exist
$catCols = $pdo->query("SHOW COLUMNS FROM categories")->fetchAll(PDO::FETCH_COLUMN);
if (!in_array('seo_title', $catCols)) {
    $pdo->exec("ALTER TABLE categories ADD COLUMN seo_title VARCHAR(255) NULL AFTER name");
    echo "✔ Added seo_title to categories\n";
}
if (!in_array('seo_description', $catCols)) {
    $pdo->exec("ALTER TABLE categories ADD COLUMN seo_description TEXT NULL AFTER description");
    echo "✔ Added seo_description to categories\n";
}

// 3. Update Category SEO Metadata
$categorySeo = [
    'technology' => [
        'title' => 'Technology Courses & Bootcamps | StudyMe',
        'desc'  => 'Master in-demand technology skills with StudyMe. Practical bootcamps in Web Development, Cybersecurity, AI Content Creation, Python, and UI/UX with 24/7 AI tutor assistance.'
    ],
    'secondary-waec-neco' => [
        'title' => 'Secondary School, WAEC, NECO & JAMB Preparation | StudyMe',
        'desc'  => 'Comprehensive senior secondary school subjects and exam preparation for WAEC, NECO, and JAMB. Access full syllabi, verified past questions, and step-by-step AI solutions.'
    ],
    'university' => [
        'title' => 'University Courses & Undergraduate Modules | StudyMe',
        'desc'  => 'Undergraduate university curriculum modules in Computer Science, Mathematics, Physics, Chemistry, Economics, and Accounting with lecture notes and 24/7 AI tutoring.'
    ],
    'teacher' => [
        'title' => 'Teacher Suite & Instructor Program | StudyMe',
        'desc'  => 'Join StudyMe as a verified educator. Create courses, publish video lessons, build automated quizzes, and monitor student analytics with an AI co-pilot.'
    ]
];

$stmtCat = $pdo->prepare("UPDATE categories SET seo_title = ?, seo_description = ? WHERE slug = ?");
foreach ($categorySeo as $slug => $data) {
    $stmtCat->execute([$data['title'], $data['desc'], $slug]);
}
echo "✔ Updated category SEO metadata\n";

// 4. Update Course SEO Metadata
$courses = $pdo->query("SELECT id, title, slug, short_description, level, price FROM courses")->fetchAll(PDO::FETCH_ASSOC);
$stmtCourseSeo = $pdo->prepare("UPDATE courses SET seo_title = ?, seo_description = ?, seo_keywords = ? WHERE id = ?");

foreach ($courses as $c) {
    $seoTitle = $c['title'] . ' Course | StudyMe';
    $seoDesc = !empty($c['short_description']) 
        ? substr($c['short_description'], 0, 155) . ' Learn with StudyMe AI Tutor.' 
        : 'Enroll in ' . $c['title'] . ' on StudyMe. Master practical skills with 24/7 AI tutoring, quizzes, and certificates.';
    $seoKeywords = strtolower(str_replace([' - ', ' (', ')', '/', '&'], ', ', $c['title'])) . ', online learning, StudyMe AI course';

    $stmtCourseSeo->execute([$seoTitle, $seoDesc, $seoKeywords, $c['id']]);
}
echo "✔ Updated SEO metadata for " . count($courses) . " published courses\n";
echo "SEO Schema Migration completed successfully!\n";
