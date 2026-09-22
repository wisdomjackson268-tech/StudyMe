<?php

require_once dirname(__DIR__, 2) . '/config/main.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'reply'   => 'Method not allowed. Please use POST.',
        'error'   => 'HTTP 405 Method Not Allowed'
    ]);
    exit;
}

$rawInput = file_get_contents('php://input');
$inputData = json_decode($rawInput, true);

if (!is_array($inputData)) {
    $inputData = $_POST;
}

$prompt  = trim((string)($inputData['prompt'] ?? ''));
$context = trim((string)($inputData['context'] ?? ''));
$history = isset($inputData['history']) && is_array($inputData['history']) ? $inputData['history'] : [];

if ($prompt === '') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'reply'   => 'Please provide a question or topic for the AI Tutor.',
        'error'   => 'Empty prompt'
    ]);
    exit;
}

$systemInstruction = get_default_tutor_system_instruction();

try {
    $pdo = getDBConnection();
    if ($pdo) {
        $aiRow = $pdo->query("SELECT system_prompt FROM ai_settings WHERE status = 'active' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if ($aiRow && !empty($aiRow['system_prompt'])) {
            $systemInstruction = trim($aiRow['system_prompt']);
        }
    }
} catch (Throwable $e) {
}

if (!empty($context)) {
    $systemInstruction .= "\n\nCurrent learning context / course: " . $context;
}

$result = call_gemini_api($prompt, $systemInstruction, $history);

if ($result['success']) {
    echo json_encode([
        'success' => true,
        'reply'   => $result['text'],
        'title'   => 'AI Tutor Response',
        'model'   => $result['model']
    ]);
} else {
    http_response_code(200);
    echo json_encode([
        'success' => false,
        'reply'   => $result['text'],
        'error'   => $result['error'],
        'model'   => $result['model']
    ]);
}
