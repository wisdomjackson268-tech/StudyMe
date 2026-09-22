<?php

require_once __DIR__ . '/config/main.php';

echo "========================================================\n";
echo "      STUDYME SEO SUITE VERIFICATION REPORT\n";
echo "========================================================\n\n";

$tests = [
    'Homepage' => ['file' => 'index.php', 'expected_index' => true],
    'Courses Index' => ['file' => 'courses/index.php', 'expected_index' => true],
    'Tech Hub' => ['file' => 'courses/technology.php', 'expected_index' => true],
    'University Hub' => ['file' => 'courses/university.php', 'expected_index' => true],
    'Secondary Hub' => ['file' => 'courses/secondary.php', 'expected_index' => true],
    'Teacher Hub' => ['file' => 'courses/teacher.php', 'expected_index' => true],
    'Past Questions' => ['file' => 'courses/past-questions.php', 'expected_index' => true],
    'AI Learning' => ['file' => 'ai-learning.php', 'expected_index' => true],
    'Teachers Public' => ['file' => 'teachers.php', 'expected_index' => true],
    'Pricing' => ['file' => 'pricing.php', 'expected_index' => true],
    'About' => ['file' => 'about.php', 'expected_index' => true],
    'Contact' => ['file' => 'contact.php', 'expected_index' => true],
    'FAQ' => ['file' => 'faq.php', 'expected_index' => true],
    'Help' => ['file' => 'help.php', 'expected_index' => true],
    'Certificate Verify' => ['file' => 'certificates/verify.php', 'expected_index' => true],
    'Privacy Policy' => ['file' => 'privacy.php', 'expected_index' => true],
    'Terms of Service' => ['file' => 'terms.php', 'expected_index' => true],
    'Cookies Policy' => ['file' => 'cookies.php', 'expected_index' => true],
    'Course Details (Web Dev)' => ['file' => 'courses/details.php', 'params' => ['slug' => 'web-development'], 'expected_index' => true],
    'Auth Login (Private)' => ['file' => 'auth/login.php', 'expected_index' => false],
    'Auth Register (Private)' => ['file' => 'auth/register.php', 'expected_index' => false],
    '404 Error Page' => ['file' => '404.php', 'expected_index' => false],
];

$passCount = 0;
$totalCount = count($tests);

function run_isolated_test($filePath, $params = []) {
    $_GET = $params;
    $_SERVER['SCRIPT_NAME'] = '/StudyMe/' . $filePath;
    $_SERVER['REQUEST_URI'] = '/StudyMe/' . $filePath;
    $_SERVER['PHP_SELF']    = '/StudyMe/' . $filePath;

    ob_start();
    try {
        include BASE_PATH . '/' . $filePath;
        return ob_get_clean();
    } catch (Throwable $t) {
        ob_end_clean();
        return false;
    }
}

foreach ($tests as $testLabel => $test) {
    $html = run_isolated_test($test['file'], $test['params'] ?? []);
    if ($html === false) {
        echo "❌ [FAIL] $testLabel: Exception during render\n";
        continue;
    }

    preg_match('/<title>(.*?)<\/title>/is', $html, $titleMatch);
    $title = $titleMatch[1] ?? 'MISSING';

    preg_match('/<meta name="description" content="(.*?)"/is', $html, $descMatch);
    $desc = $descMatch[1] ?? 'MISSING';

    preg_match('/<link rel="canonical" href="(.*?)"/is', $html, $canonMatch);
    $canon = $canonMatch[1] ?? 'MISSING';

    preg_match('/<meta name="robots" content="(.*?)"/is', $html, $robotsMatch);
    $robots = $robotsMatch[1] ?? 'MISSING';

    $hasSchema = (strpos($html, 'application/ld+json') !== false);

    $hasNoindex = (strpos($robots, 'noindex') !== false);
    $isCorrectIndex = ($test['expected_index'] && !$hasNoindex) || (!$test['expected_index'] && $hasNoindex);

    if ($title !== 'MISSING' && $isCorrectIndex) {
        $passCount++;
        $statusIcon = "✔";
    } else {
        $statusIcon = "❌";
    }

    echo "$statusIcon [$testLabel]\n";
    echo "   Title: " . htmlspecialchars_decode($title) . "\n";
    echo "   Canonical: $canon\n";
    echo "   Robots: $robots\n";
    echo "   Schema JSON-LD: " . ($hasSchema ? 'Yes' : 'No') . "\n\n";
}

echo "========================================================\n";
echo "Results: $passCount / $totalCount pages passed SEO audit.\n";
echo "========================================================\n";
