<?php
require_once __DIR__ . '/../config/main.php';
$pdo = getDBConnection();

echo "=== USERS ===\n";
$users = $pdo->query("SELECT id, username, email, role, status FROM users LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
foreach ($users as $u) {
    echo "ID: {$u['id']} | User: {$u['username']} | Email: {$u['email']} | Role: {$u['role']} | Status: {$u['status']}\n";
}

echo "\n=== TEACHERS ===\n";
$teachers = $pdo->query("SELECT t.id, t.user_id, t.specialization, t.status, u.username FROM teachers t JOIN users u ON t.user_id = u.id LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
foreach ($teachers as $t) {
    echo "TeacherID: {$t['id']} | UserID: {$t['user_id']} | User: {$t['username']} | Spec: {$t['specialization']} | Status: {$t['status']}\n";
}

echo "=== COURSES CATEGORY_ID DISTRIBUTION ===\n";
$dist = $pdo->query("SELECT category_id, COUNT(*) as count FROM courses GROUP BY category_id")->fetchAll(PDO::FETCH_ASSOC);
foreach ($dist as $d) {
    echo "CatID: {$d['category_id']} | Count: {$d['count']}\n";
}

echo "\n=== FIRST 20 COURSES IN DB ===\n";
$first = $pdo->query("SELECT id, title, category_id, status FROM courses LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
foreach ($first as $f) {
    echo "ID: {$f['id']} | Title: {$f['title']} | CatID: {$f['category_id']} | Status: {$f['status']}\n";
}
exit;





echo "\n=== ALL THUMBNAILS IN COURSES TABLE ===\n";
$stmt2 = $pdo->query("SELECT DISTINCT thumbnail FROM courses");
$thumbs = $stmt2->fetchAll(PDO::FETCH_COLUMN);
foreach ($thumbs as $th) {
    echo "Thumbnail: " . $th . "\n";
}


echo "\n=== ALL UNIVERSITY COURSES ===\n";
$uniCourses = $pdo->query("SELECT c.id, c.title, c.category_id, c.teacher_id, c.price, c.thumbnail FROM courses c WHERE c.category_id = 8")->fetchAll(PDO::FETCH_ASSOC);
foreach ($uniCourses as $uc) {
    echo "ID: {$uc['id']} | Title: {$uc['title']} | Teacher: {$uc['teacher_id']} | Price: {$uc['price']} | Thumb: {$uc['thumbnail']}\n";
}

echo "\n=== ALL SECONDARY COURSES / SUBJECTS ===\n";
$secCourses = $pdo->query("SELECT c.id, c.title, c.category_id, c.teacher_id, c.price, c.thumbnail FROM courses c WHERE c.category_id = 7")->fetchAll(PDO::FETCH_ASSOC);
foreach ($secCourses as $sc) {
    echo "ID: {$sc['id']} | Title: {$sc['title']} | Teacher: {$sc['teacher_id']} | Price: {$sc['price']} | Thumb: {$sc['thumbnail']}\n";
}


echo "\n=== SUBSCRIPTION PLANS ===\n";
$plans = $pdo->query("SELECT * FROM subscription_plans")->fetchAll(PDO::FETCH_ASSOC);
foreach ($plans as $p) {
    echo "Plan ID: {$p['id']} | Name: {$p['name']} | Slug: {$p['slug']} | Price: {$p['price']} | Status: {$p['status']}\n";
}

echo "\n=== TABLES IN DB ===\n";
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
echo implode(", ", $tables) . "\n";

