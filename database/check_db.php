<?php
require_once dirname(__DIR__) . '/config/main.php';

$pdo = getDBConnection();
$teachers = $pdo->query("SELECT t.id, t.user_id, t.assigned_course_id, u.first_name, u.last_name, u.email FROM teachers t JOIN users u ON t.user_id = u.id")->fetchAll(PDO::FETCH_ASSOC);
echo "Teachers in DB: " . json_encode($teachers) . "\n";
exit;


echo "=== CURRENT UNIVERSITY COURSES IN DB ===\n";
$uni = $pdo->query("SELECT c.id, c.title, c.slug, c.price, c.teacher_id, cat.slug as cat_slug FROM courses c JOIN categories cat ON c.category_id = cat.id WHERE cat.slug = 'university' ORDER BY c.title ASC")->fetchAll(PDO::FETCH_ASSOC);
echo "Count: " . count($uni) . "\n";
foreach ($uni as $u) {
    echo "ID: {$u['id']} | Title: {$u['title']} | TeacherID: " . ($u['teacher_id'] ?: 'NONE') . " | Price: {$u['price']}\n";
}
exit;



