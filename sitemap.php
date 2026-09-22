<?php

require_once __DIR__ . '/config/main.php';

header('Content-Type: application/xml; charset=utf-8');

$baseUrl = get_base_url();
$pdo = getDBConnection();
$today = date('Y-m-d');

$staticPages = [
    ['loc' => $baseUrl . '/index.php', 'priority' => '1.0', 'changefreq' => 'daily', 'lastmod' => $today],
    ['loc' => $baseUrl . '/courses/index.php', 'priority' => '0.9', 'changefreq' => 'daily', 'lastmod' => $today],
    ['loc' => $baseUrl . '/courses/technology.php', 'priority' => '0.9', 'changefreq' => 'weekly', 'lastmod' => $today],
    ['loc' => $baseUrl . '/courses/university.php', 'priority' => '0.9', 'changefreq' => 'weekly', 'lastmod' => $today],
    ['loc' => $baseUrl . '/courses/secondary.php', 'priority' => '0.9', 'changefreq' => 'weekly', 'lastmod' => $today],
    ['loc' => $baseUrl . '/courses/teacher.php', 'priority' => '0.8', 'changefreq' => 'weekly', 'lastmod' => $today],
    ['loc' => $baseUrl . '/courses/past-questions.php', 'priority' => '0.8', 'changefreq' => 'weekly', 'lastmod' => $today],
    ['loc' => $baseUrl . '/ai-learning.php', 'priority' => '0.9', 'changefreq' => 'weekly', 'lastmod' => $today],
    ['loc' => $baseUrl . '/teachers.php', 'priority' => '0.8', 'changefreq' => 'weekly', 'lastmod' => $today],
    ['loc' => $baseUrl . '/pricing.php', 'priority' => '0.8', 'changefreq' => 'weekly', 'lastmod' => $today],
    ['loc' => $baseUrl . '/about.php', 'priority' => '0.7', 'changefreq' => 'monthly', 'lastmod' => $today],
    ['loc' => $baseUrl . '/contact.php', 'priority' => '0.7', 'changefreq' => 'monthly', 'lastmod' => $today],
    ['loc' => $baseUrl . '/careers.php', 'priority' => '0.6', 'changefreq' => 'monthly', 'lastmod' => $today],
    ['loc' => $baseUrl . '/community.php', 'priority' => '0.7', 'changefreq' => 'weekly', 'lastmod' => $today],
    ['loc' => $baseUrl . '/faq.php', 'priority' => '0.7', 'changefreq' => 'weekly', 'lastmod' => $today],
    ['loc' => $baseUrl . '/help.php', 'priority' => '0.7', 'changefreq' => 'weekly', 'lastmod' => $today],
    ['loc' => $baseUrl . '/certificates/verify.php', 'priority' => '0.7', 'changefreq' => 'monthly', 'lastmod' => $today],
    ['loc' => $baseUrl . '/privacy.php', 'priority' => '0.5', 'changefreq' => 'yearly', 'lastmod' => $today],
    ['loc' => $baseUrl . '/terms.php', 'priority' => '0.5', 'changefreq' => 'yearly', 'lastmod' => $today],
    ['loc' => $baseUrl . '/cookies.php', 'priority' => '0.5', 'changefreq' => 'yearly', 'lastmod' => $today],
];

$categories = [];
try {
    $categories = $pdo->query("SELECT slug, updated_at FROM categories WHERE status = 'active'")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $categories = [];
}

$courses = [];
try {
    $courses = $pdo->query("SELECT slug, updated_at FROM courses WHERE status = 'published'")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $courses = [];
}

$xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
$xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

foreach ($staticPages as $page) {
    $xml .= "  <url>\n";
    $xml .= "    <loc>" . htmlspecialchars($page['loc'], ENT_XML1, 'UTF-8') . "</loc>\n";
    $xml .= "    <lastmod>" . $page['lastmod'] . "</lastmod>\n";
    $xml .= "    <changefreq>" . $page['changefreq'] . "</changefreq>\n";
    $xml .= "    <priority>" . $page['priority'] . "</priority>\n";
    $xml .= "  </url>\n";
}

foreach ($categories as $cat) {
    $catUrl = $baseUrl . '/courses/category.php?slug=' . urlencode($cat['slug']);
    $lastmod = !empty($cat['updated_at']) ? date('Y-m-d', strtotime($cat['updated_at'])) : $today;
    $xml .= "  <url>\n";
    $xml .= "    <loc>" . htmlspecialchars($catUrl, ENT_XML1, 'UTF-8') . "</loc>\n";
    $xml .= "    <lastmod>" . $lastmod . "</lastmod>\n";
    $xml .= "    <changefreq>weekly</changefreq>\n";
    $xml .= "    <priority>0.8</priority>\n";
    $xml .= "  </url>\n";
}

foreach ($courses as $c) {
    $courseUrl = $baseUrl . '/courses/details.php?slug=' . urlencode($c['slug']);
    $lastmod = !empty($c['updated_at']) ? date('Y-m-d', strtotime($c['updated_at'])) : $today;
    $xml .= "  <url>\n";
    $xml .= "    <loc>" . htmlspecialchars($courseUrl, ENT_XML1, 'UTF-8') . "</loc>\n";
    $xml .= "    <lastmod>" . $lastmod . "</lastmod>\n";
    $xml .= "    <changefreq>weekly</changefreq>\n";
    $xml .= "    <priority>0.8</priority>\n";
    $xml .= "  </url>\n";
}

$xml .= '</urlset>';

@file_put_contents(BASE_PATH . '/sitemap.xml', $xml);

echo $xml;
