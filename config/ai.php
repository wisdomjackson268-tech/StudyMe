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
    $lower = strtolower($cleanPrompt);

    // 1. Greetings & Well-being
    if (preg_match('/\b(hi|hello|hey|how are you|how do you do|hows it going|good day|good morning|good afternoon|good evening|sup|whats up|what is up|greetings|yo)\b/i', $lower)) {
        if (preg_match('/(how are you|how do you do|hows it going)/i', $lower)) {
            return "👋 **Hello!** I'm doing great, thank you! I'm ready to help you with your studies. What subject or question would you like to work on today?";
        }
        return "👋 **Hello!** I'm your StudyMe AI Tutor. How can I help you with your lessons or questions today?";
    }

    // 2. Identity & Purpose
    if (preg_match('/(who are you|what is your name|what are you|what can you do|introduce yourself)/i', $lower)) {
        return "🤖 **I am your StudyMe AI Tutor!**\n\nI am here 24/7 to help you understand topics easily, solve problems step-by-step, review code, or prepare for exams. Just ask any question you have!";
    }

    // 3. Thanks & Closing
    if (preg_match('/\b(thank you|thanks|thank u|appreciate it|awesome|great job|cool|bye|goodbye|see you)\b/i', $lower)) {
        return "You're very welcome! 😊 Keep up the great learning. Whenever you have another question, just type it here!";
    }

    // 4. Mathematics & Formulas
    if (preg_match('/(quadratic|pythagor|calculus|algebra|derivative|integral|geometry|fraction|circle area|trigonometr)/i', $lower)) {
        if (str_contains($lower, 'quadratic')) {
            return "📐 **Quadratic Equation Formula:**\n\n"
                . "For any equation **ax² + bx + c = 0**, the solutions are given by:\n"
                . "**x = (-b ± √(b² - 4ac)) / 2a**\n\n"
                . "**Example:** Solve *x² - 5x + 6 = 0*\n"
                . "1. Here, a = 1, b = -5, c = 6\n"
                . "2. b² - 4ac = 25 - 24 = 1\n"
                . "3. x = (5 ± 1) / 2 ➔ **x = 3 or x = 2**\n\n"
                . "💬 *Have a specific quadratic equation you'd like me to solve? Share it!*";
        }
        if (str_contains($lower, 'pythagor')) {
            return "📐 **Pythagorean Theorem:**\n\n"
                . "For any right-angled triangle:\n"
                . "**a² + b² = c²** (where *c* is the hypotenuse, the longest side).\n\n"
                . "**Example:** If a = 3 and b = 4:\n"
                . "• c² = 3² + 4² = 9 + 16 = 25\n"
                . "• c = √25 = **5**";
        }
        if (str_contains($lower, 'circle') && (str_contains($lower, 'area') || str_contains($lower, 'radius'))) {
            return "📐 **Area of a Circle:**\n\n"
                . "**Area = πr²** (where *r* is the radius, and π ≈ 3.14159 / 22/7)\n\n"
                . "**Example:** For a circle with radius r = 7 cm:\n"
                . "• Area = (22/7) × 7 × 7 = **154 cm²**";
        }
    }

    // 5. Programming & Coding
    if (preg_match('/(python|javascript|php|html|css|sql|function|loop|variable|array|object|class|algorithm)/i', $lower)) {
        if (str_contains($lower, 'python') && str_contains($lower, 'function')) {
            return "💻 **Defining Functions in Python:**\n\n"
                . "Use the `def` keyword followed by the function name and parameters:\n\n"
                . "```python\n"
                . "def greet_student(name):\n"
                . "    return f\"Hello, {name}! Welcome to StudyMe.\"\n\n"
                . "# Calling the function\n"
                . "message = greet_student(\"Alex\")\n"
                . "print(message)\n"
                . "```\n\n"
                . "• `def` initiates the function definition.\n"
                . "• `return` passes the result back to the caller.";
        }
        if (str_contains($lower, 'loop')) {
            return "💻 **Programming Loops:**\n\n"
                . "Loops execute a block of code repeatedly while a condition is met.\n\n"
                . "**1. For Loop (fixed iterations):**\n"
                . "```python\n"
                . "for i in range(5):\n"
                . "    print(i) # Prints 0 to 4\n"
                . "```\n\n"
                . "**2. While Loop (condition-based):**\n"
                . "```python\n"
                . "count = 0\n"
                . "while count < 3:\n"
                . "    print(count)\n"
                . "    count += 1\n"
                . "```";
        }
    }

    // 6. Science
    if (preg_match('/(photosynthesis|newton|osmosis|cell|atom|gravity|velocity|respiration)/i', $lower)) {
        if (str_contains($lower, 'photosynthesis')) {
            return "🌿 **Photosynthesis:**\n\n"
                . "Photosynthesis is the process by which green plants convert light energy, carbon dioxide, and water into glucose and oxygen.\n\n"
                . "**Chemical Equation:**\n"
                . "**6CO₂ + 6H₂O + Light ➔ C₆H₁₂O₆ + 6O₂**\n\n"
                . "• **Site:** Occurs inside chloroplasts using the pigment chlorophyll.\n"
                . "• **Importance:** Provides energy for plant growth and produces the oxygen living organisms breathe.";
        }
        if (str_contains($lower, 'newton')) {
            return "🔬 **Newton's Laws of Motion:**\n\n"
                . "1. **1st Law (Inertia):** An object remains at rest or in uniform motion unless acted upon by a net external force.\n"
                . "2. **2nd Law (Force):** **F = ma** (Force = mass × acceleration).\n"
                . "3. **3rd Law (Action/Reaction):** For every action, there is an equal and opposite reaction.";
        }
    }

    // 7. Quizzes
    if (str_contains($lower, 'quiz') || str_contains($lower, 'test me')) {
        return "🎯 **Quick Practice Question**\n\n"
            . "**Topic:** " . htmlspecialchars($cleanPrompt, ENT_QUOTES) . "\n\n"
            . "What is the key principle you should keep in mind when working on this topic?\n\n"
            . "1. Identify the given values and formula\n"
            . "2. Work step-by-step carefully\n"
            . "3. Double-check your final answer\n\n"
            . "💡 *Reply with your thoughts or a question, and I'll explain it simply!*";
    }

    // 8. General question
    return "💡 **" . htmlspecialchars($cleanPrompt, ENT_QUOTES) . "**\n\n"
        . "Here is a simple, easy-to-understand explanation:\n\n"
        . "• **Key Concept:** Focus on the main principle behind the question.\n"
        . "• **Step-by-Step Approach:** Break it down into simple parts, solve systematically, and verify each step.\n"
        . "• **Practical Tip:** Always review standard examples to strengthen your understanding.\n\n"
        . "💬 *Do you have a specific example or problem you'd like us to solve together?*";
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
