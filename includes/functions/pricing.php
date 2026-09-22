<?php
require_once dirname(__DIR__, 2) . '/config/database.php';

function get_pricing_setting($key, $default = 0.00) {
    $pdo = getDBConnection();
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1");
        $stmt->execute([$key]);
        $val = $stmt->fetchColumn();
        return $val !== false ? (float)$val : (float)$default;
    } catch (Exception $e) {
        return (float)$default;
    }
}

function get_official_pricing_rates() {
    return [
        'tech'       => get_pricing_setting('price_tech', defined('OFFICIAL_PRICE_TECH') ? OFFICIAL_PRICE_TECH : 10000.00),
        'secondary'  => get_pricing_setting('price_secondary', defined('OFFICIAL_PRICE_SECONDARY') ? OFFICIAL_PRICE_SECONDARY : 3000.00),
        'university' => get_pricing_setting('price_university', defined('OFFICIAL_PRICE_UNIVERSITY') ? OFFICIAL_PRICE_UNIVERSITY : 5000.00),
        'teacher'    => get_pricing_setting('price_teacher', defined('OFFICIAL_PRICE_TEACHER') ? OFFICIAL_PRICE_TEACHER : 4000.00),
    ];
}

function get_course_official_price($courseId) {
    $pdo = getDBConnection();
    try {
        $stmt = $pdo->prepare("
            SELECT c.id, c.title, c.price, c.level, cat.name AS category_name, cat.slug AS category_slug, subj.name AS subject_name
            FROM courses c
            LEFT JOIN categories cat ON c.category_id = cat.id
            LEFT JOIN subjects subj ON c.subject_id = subj.id
            WHERE c.id = ? LIMIT 1
        ");
        $stmt->execute([$courseId]);
        $course = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$course) {
            return 10000.00;
        }

        if ((float)$course['price'] > 0) {
            return (float)$course['price'];
        }

        $rates = get_official_pricing_rates();
        $catSlug = strtolower($course['category_slug'] ?? '');
        $text = strtolower(($course['title'] ?? '') . ' ' . ($course['category_name'] ?? '') . ' ' . ($course['subject_name'] ?? ''));

        if ($catSlug === 'secondary-waec-neco' || preg_match('/(waec|neco|jamb|secondary|high school|ssce)/i', $text)) {
            return $rates['secondary'];
        }

        if ($catSlug === 'university' || preg_match('/(university|undergraduate|degree|b\.sc|college|faculty)/i', $text)) {
            return $rates['university'];
        }

        if ($catSlug === 'teacher' || preg_match('/(teacher|instructor|educator)/i', $text)) {
            return $rates['teacher'];
        }

        return $rates['tech'];
    } catch (Exception $e) {
        error_log("Error in get_course_official_price: " . $e->getMessage());
        return 10000.00;
    }
}
