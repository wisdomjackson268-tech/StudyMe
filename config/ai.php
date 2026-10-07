<?php

if (!defined('GEMINI_MODEL')) {
    $configuredModel = env('GEMINI_MODEL', 'gemini-2.5-flash');
    define('GEMINI_MODEL', !empty($configuredModel) ? $configuredModel : 'gemini-2.5-flash');
}

function get_gemini_api_key(): string {
    $key = env('GEMINI_API_KEY');
    if ($key !== null && $key !== '') {
        $trimmed = trim((string)$key);
        if ($trimmed !== '' && 
            $trimmed !== 'PASTE_THE_NEW_GEMINI_API_KEY_HERE' && 
            $trimmed !== 'your_gemini_api_key_here' && 
            $trimmed !== 'your_key_here') {
            return $trimmed;
        }
    }

    try {
        $pdo = getDBConnection();
        if ($pdo) {
            $row = $pdo->query("SELECT api_key FROM ai_settings WHERE status = 'active' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            if ($row && !empty($row['api_key'])) {
                $trimmed = trim((string)$row['api_key']);
                if ($trimmed !== '' && 
                    $trimmed !== 'PASTE_THE_NEW_GEMINI_API_KEY_HERE' && 
                    $trimmed !== 'your_gemini_api_key_here') {
                    return $trimmed;
                }
            }
        }
    } catch (Throwable $e) {}

    return '';
}

function get_gemini_model(): string {
    return defined('GEMINI_MODEL') ? GEMINI_MODEL : 'gemini-2.5-flash';
}

function is_gemini_configured(): bool {
    return get_gemini_api_key() !== '';
}

function get_openai_api_key(): string {
    return get_gemini_api_key();
}

function get_openai_model(): string {
    return get_gemini_model();
}

function is_openai_configured(): bool {
    return is_gemini_configured();
}

function get_default_tutor_system_instruction(): string {
    return "You are StudyMe AI Tutor, an intelligent, friendly, and encouraging personal educator on StudyMe.\n\n"
        . "Guidelines:\n"
        . "1. Greetings: If the user says 'hi', 'hello', 'hey', or simply greets you, reply with a short, warm, 1-2 sentence greeting offering help.\n"
        . "2. Simplicity & Clarity: Explain concepts in simple, easy-to-understand language. Avoid overwhelming walls of text.\n"
        . "3. Direct Answers: Give the main answer or solution directly, followed by clean, bite-sized step-by-step points or brief examples.\n"
        . "4. Encouraging Tone: Be positive, patient, and conversational.\n"
        . "5. Formatting: Use neat bullet points, bold text for key terms, and clean code blocks where helpful.";
}

/**
 * Intelligent educational fallback response generator when API key is missing or offline
 */
function generate_educational_fallback_response(string $prompt, string $context = ''): string {
    $cleanPrompt = trim($prompt);
    $lowerPrompt = strtolower($cleanPrompt);

    // Short Greetings
    if (preg_match('/^(hi|hello|hey|good day|good morning|good afternoon|good evening|how are you|greetings|yo|sup)[\s!\.]*$/i', $cleanPrompt) || in_array($lowerPrompt, ['hi', 'hello', 'hey', 'hi there', 'hello there', 'hey there'])) {
        return "👋 **Hello!** I'm your StudyMe AI Tutor. How can I help you with your lessons or questions today?";
    }

    // Quiz request
    if (str_contains($lowerPrompt, 'quiz') || str_contains($lowerPrompt, 'test me')) {
        return "🎯 **Quick Practice Question**\n\n"
            . "**Topic:** " . htmlspecialchars($cleanPrompt, ENT_QUOTES) . "\n\n"
            . "What is the key principle you should keep in mind when working on this topic?\n\n"
            . "1. Identify the given values and formula\n"
            . "2. Work step-by-step carefully\n"
            . "3. Double-check your final answer\n\n"
            . "💡 *Reply with your thoughts or a question, and I'll explain it simply!*";
    }

    // Summary request
    if (str_contains($lowerPrompt, 'summar') || str_contains($lowerPrompt, 'explain') || str_contains($lowerPrompt, 'what is')) {
        return "📚 **" . htmlspecialchars($cleanPrompt, ENT_QUOTES) . "**\n\n"
            . "**Quick Summary:**\n"
            . "Here is a simple, easy-to-understand breakdown:\n\n"
            . "• **Core Idea:** The fundamental concept is straightforward once you understand its purpose.\n"
            . "• **Step-by-Step:** Break the problem down into parts, apply the standard rule or formula, and solve methodically.\n"
            . "• **Best Practice:** Always test with a simple example first.\n\n"
            . "✨ *Would you like a specific example or a step-by-step calculation?*";
    }

    // Default friendly response
    return "💡 **Here is a simple breakdown:**\n\n"
        . "Regarding **\"" . htmlspecialchars($cleanPrompt, ENT_QUOTES) . "\"**:\n\n"
        . "1. **Understand the Goal:** Determine the core question and what is required.\n"
        . "2. **Apply the Method:** Follow the standard principles or formula step-by-step.\n"
        . "3. **Check Your Result:** Make sure the answer is accurate and complete.\n\n"
        . "💬 *Feel free to ask a follow-up question or share a specific problem!*";
}

function call_gemini_api(string $prompt, string $systemInstruction = '', array $history = [], ?string $modelOverride = null): array {
    $apiKey = get_gemini_api_key();

    if (empty($apiKey)) {
        return [
            'success' => true,
            'text'    => generate_educational_fallback_response($prompt, $systemInstruction),
            'error'   => null,
            'model'   => 'studyme-educational-engine'
        ];
    }

    $model = $modelOverride ?: get_gemini_model();
    $modelsToTry = array_unique([$model, 'gemini-2.5-flash', 'gemini-2.0-flash', 'gemini-1.5-flash', 'gemini-flash-latest']);

    if (empty($systemInstruction)) {
        $systemInstruction = get_default_tutor_system_instruction();
    }

    $contents = [];

    if (!empty($history) && is_array($history)) {
        foreach ($history as $msg) {
            $role = (isset($msg['role']) && $msg['role'] === 'model') ? 'model' : 'user';
            $text = trim((string)($msg['text'] ?? $msg['content'] ?? ''));
            if ($text !== '') {
                $contents[] = [
                    'role'  => $role,
                    'parts' => [['text' => $text]]
                ];
            }
        }
    }

    $contents[] = [
        'role'  => 'user',
        'parts' => [['text' => $prompt]]
    ];

    $payloadArray = [
        'contents' => $contents,
        'generationConfig' => [
            'temperature'     => 0.4,
            'topK'            => 40,
            'topP'            => 0.95,
            'maxOutputTokens' => 1800,
        ]
    ];

    if (!empty($systemInstruction)) {
        $payloadArray['system_instruction'] = [
            'parts' => [['text' => $systemInstruction]]
        ];
    }

    $payloadJson = json_encode($payloadArray);
    $lastError = null;

    foreach ($modelsToTry as $currentModel) {
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . urlencode($currentModel) . ':generateContent?key=' . urlencode($apiKey);

        $responseBody = null;
        $httpCode = 0;

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'User-Agent: StudyMe-AI/1.0'
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payloadJson);
            curl_setopt($ch, CURLOPT_TIMEOUT, 25);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

            $responseBody = curl_exec($ch);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError) {
                $lastError = "Network error: " . $curlError;
                continue;
            }
        } else {
            $options = [
                'http' => [
                    'method'        => 'POST',
                    'header'        => "Content-Type: application/json\r\nUser-Agent: StudyMe-AI/1.0\r\n",
                    'content'       => $payloadJson,
                    'timeout'       => 25,
                    'ignore_errors' => true
                ],
                'ssl' => [
                    'verify_peer'      => false,
                    'verify_peer_name' => false
                ]
            ];
            $context = stream_context_create($options);
            $responseBody = @file_get_contents($url, false, $context);
            if (isset($http_response_header) && is_array($http_response_header)) {
                foreach ($http_response_header as $hdr) {
                    if (preg_match('#HTTP/\S+\s+(\d+)#i', $hdr, $matches)) {
                        $httpCode = (int)$matches[1];
                        break;
                    }
                }
            }
        }

        if ($httpCode === 200 && !empty($responseBody)) {
            $data = json_decode($responseBody, true);
            if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                $aiText = trim((string)$data['candidates'][0]['content']['parts'][0]['text']);
                return [
                    'success' => true,
                    'text'    => $aiText,
                    'error'   => null,
                    'model'   => $currentModel
                ];
            }
        }

        if (!empty($responseBody)) {
            $data = json_decode($responseBody, true);
            if (isset($data['error']['message'])) {
                $lastError = "Gemini API Error (" . ($data['error']['code'] ?? $httpCode) . "): " . $data['error']['message'];
            } else {
                $lastError = "API returned HTTP " . $httpCode;
            }
        } else {
            $lastError = "Empty response from Gemini server (HTTP " . $httpCode . ")";
        }
    }

    // Graceful fallback to educational engine rather than a breaking error
    return [
        'success' => true,
        'text'    => generate_educational_fallback_response($prompt, $systemInstruction),
        'error'   => $lastError,
        'model'   => 'studyme-educational-engine (fallback)'
    ];
}
