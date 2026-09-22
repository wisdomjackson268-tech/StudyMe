<?php
$_GET['subject'] = 'mathematics';
$_GET['exam'] = 'utme';
$_GET['year'] = '2024';

ob_start();
require __DIR__ . '/api/past-questions/index.php';
$jsonOut = ob_get_clean();

$apiData = json_decode($jsonOut, true);
echo "API Test Result:\n";
echo "Success: " . ($apiData['success'] ? 'true' : 'false') . "\n";
echo "Source: " . ($apiData['source'] ?? '') . "\n";
echo "Total: " . ($apiData['total'] ?? 0) . "\n";
if (!empty($apiData['questions'])) {
    echo "Question 1: " . $apiData['questions'][0]['question_text'] . "\n";
}
