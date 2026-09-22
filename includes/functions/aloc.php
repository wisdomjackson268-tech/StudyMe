<?php
function normalize_aloc_exam_type(?string $examType): ?string {
    if (empty($examType)) {
        return null;
    }
    $lower = strtolower(trim($examType));
    if ($lower === 'jamb') {
        return 'utme';
    }
    return in_array($lower, ['utme', 'waec', 'neco', 'post-utme']) ? $lower : 'waec';
}

function fetch_aloc_questions(string $subject, ?string $examType = null, ?int $year = null, int $count = 10, int $page = 1): array {
    $apiKey = get_aloc_api_key();
    $subject = strtolower(trim($subject));
    $normExam = normalize_aloc_exam_type($examType);
    $count = max(1, min(40, (int)$count));

    $supportedSubjects = get_aloc_supported_subjects();
    if (!isset($supportedSubjects[$subject])) {
        $subject = 'mathematics';
    }

    $errorMessage = null;
    $errorCode = null;

    if (!empty($apiKey)) {
        $baseUrl = get_aloc_base_url();
        $queryParams = ['subject' => $subject];

        if (!empty($normExam)) {
            $queryParams['type'] = $normExam;
        }
        if (!empty($year) && $year > 1990 && $year <= (int)date('Y')) {
            $queryParams['year'] = (int)$year;
        }
        if ($page > 1) {
            $queryParams['page'] = (int)$page;
        }

        $endpoint = $baseUrl . '/m/' . $count . '?' . http_build_query($queryParams);

        if (function_exists('curl_init')) {
            $ch = curl_init($endpoint);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'AccessToken: ' . $apiKey,
                'Accept: application/json',
                'User-Agent: StudyMe-Platform/1.0'
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 12);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

            $responseBody = curl_exec($ch);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if (!$curlError && !empty($responseBody)) {
                $data = json_decode($responseBody, true);
                if ($httpCode === 200 && is_array($data) && isset($data['data'])) {
                    $rawQuestions = is_array($data['data']) ? $data['data'] : [];
                    if (isset($rawQuestions['question'])) {
                        $rawQuestions = [$rawQuestions];
                    }

                    if (!empty($rawQuestions)) {
                        $normalizedList = [];
                        foreach ($rawQuestions as $index => $q) {
                            $normalizedList[] = normalize_aloc_question_item($q, $subject, $normExam, $year, $index + 1);
                        }

                        return [
                            'success'       => true,
                            'source'        => 'aloc_live',
                            'subject'       => $subject,
                            'subject_name'  => $supportedSubjects[$subject]['name'] ?? ucfirst($subject),
                            'exam_type'     => $normExam ?: 'all',
                            'year'          => $year,
                            'questions'     => $normalizedList,
                            'total'         => count($normalizedList),
                            'notice'        => 'Live questions streaming from ALOC Station API',
                            'error'         => null
                        ];
                    }
                } else {
                    $errorCode = $httpCode;
                    $errorMessage = $data['error'] ?? ($data['message'] ?? "ALOC API responded with HTTP {$httpCode}");
                }
            } else {
                $errorCode = 500;
                $errorMessage = "cURL Network Error: " . ($curlError ?: 'Empty response');
            }
        }
    } else {
        $errorMessage = 'ALOC_API_KEY is not configured in .env';
        $errorCode = 401;
    }

    $localQuestions = fetch_local_past_questions($subject, $normExam, $year, $count);

    if (!empty($localQuestions)) {
        return [
            'success'       => true,
            'source'        => 'local_vault',
            'subject'       => $subject,
            'subject_name'  => $supportedSubjects[$subject]['name'] ?? ucfirst($subject),
            'exam_type'     => $normExam ?: 'all',
            'year'          => $year,
            'questions'     => $localQuestions,
            'total'         => count($localQuestions),
            'notice'        => !empty($errorMessage) 
                ? "Loaded from StudyMe Verified Vault (ALOC notice: {$errorMessage})" 
                : "Loaded from StudyMe Verified Vault",
            'error'         => $errorMessage,
            'error_code'    => $errorCode
        ];
    }

    return [
        'success'       => false,
        'source'        => 'none',
        'subject'       => $subject,
        'subject_name'  => $supportedSubjects[$subject]['name'] ?? ucfirst($subject),
        'exam_type'     => $normExam ?: 'all',
        'year'          => $year,
        'questions'     => [],
        'total'         => 0,
        'error'         => $errorMessage ?: 'No questions currently available for this subject and exam filter.',
        'error_code'    => $errorCode ?: 404
    ];
}

function normalize_aloc_question_item(array $q, string $subject, ?string $examType, ?int $year, int $num): array {
    $options = $q['option'] ?? [];
    $optA = is_array($options) ? ($options['a'] ?? '') : ($q['option_a'] ?? '');
    $optB = is_array($options) ? ($options['b'] ?? '') : ($q['option_b'] ?? '');
    $optC = is_array($options) ? ($options['c'] ?? '') : ($q['option_c'] ?? '');
    $optD = is_array($options) ? ($options['d'] ?? '') : ($q['option_d'] ?? '');

    $answer = strtolower(trim((string)($q['answer'] ?? ($q['correct_option'] ?? 'a'))));
    $explanation = trim((string)($q['solution'] ?? ($q['explanation'] ?? '')));
    $questionText = trim((string)($q['question'] ?? ''));
    $qYear = (int)($q['examyear'] ?? ($year ?: 2024));
    $qExam = strtolower(trim((string)($q['examtype'] ?? ($examType ?: 'waec'))));
    if ($qExam === 'jamb') $qExam = 'utme';

    return [
        'id'              => (int)($q['id'] ?? $num),
        'question_number' => (int)($q['question_number'] ?? $num),
        'question_text'   => $questionText,
        'option_a'        => (string)$optA,
        'option_b'        => (string)$optB,
        'option_c'        => (string)$optC,
        'option_d'        => (string)$optD,
        'correct_option'  => $answer,
        'explanation'     => $explanation,
        'exam_type'       => $qExam,
        'exam_year'       => $qYear,
        'subject'         => $subject,
        'image'           => !empty($q['image']) ? $q['image'] : null,
        'section'         => $q['section'] ?? null,
    ];
}

function fetch_local_past_questions(string $subject, ?string $examType = null, ?int $year = null, int $limit = 20): array {
    try {
        $pdo = getDBConnection();
        $conditions = ["(subject_slug = ? OR subject_name LIKE ?)"];
        $params = [$subject, "%{$subject}%"];

        if (!empty($examType)) {
            if ($examType === 'utme' || $examType === 'jamb') {
                $conditions[] = "(exam_type = 'utme' OR exam_type = 'jamb')";
            } else {
                $conditions[] = "exam_type = ?";
                $params[] = $examType;
            }
        }

        if (!empty($year)) {
            $conditions[] = "year = ?";
            $params[] = (int)$year;
        }

        $sql = "SELECT * FROM past_questions WHERE " . implode(' AND ', $conditions) . " ORDER BY question_number ASC LIMIT " . (int)$limit;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $normalized = [];
        foreach ($rows as $i => $r) {
            $normalized[] = [
                'id'              => (int)$r['id'],
                'question_number' => (int)($r['question_number'] ?: ($i + 1)),
                'question_text'   => $r['question_text'],
                'option_a'        => $r['option_a'],
                'option_b'        => $r['option_b'],
                'option_c'        => $r['option_c'],
                'option_d'        => $r['option_d'],
                'correct_option'  => strtolower($r['correct_option']),
                'explanation'     => $r['explanation'],
                'exam_type'       => strtolower($r['exam_type']) === 'jamb' ? 'utme' : strtolower($r['exam_type']),
                'exam_year'       => (int)$r['year'],
                'subject'         => $r['subject_slug'] ?: $subject,
                'image'           => null,
                'section'         => null
            ];
        }

        return $normalized;
    } catch (Throwable $e) {
        return [];
    }
}

function test_aloc_api_connection(): array {
    $apiKey = get_aloc_api_key();
    if (empty($apiKey)) {
        return [
            'success'    => false,
            'http_code'  => 0,
            'latency_ms' => 0,
            'message'    => 'ALOC_API_KEY is not configured in .env file.',
            'data'       => null
        ];
    }

    $url = get_aloc_base_url() . '/q?subject=mathematics';
    $startTime = microtime(true);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'AccessToken: ' . $apiKey,
        'Accept: application/json',
        'User-Agent: StudyMe-Platform/1.0'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $response = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    $latencyMs = round((microtime(true) - $startTime) * 1000);

    if ($curlError) {
        return [
            'success'    => false,
            'http_code'  => 0,
            'latency_ms' => $latencyMs,
            'message'    => "Connection error: {$curlError}",
            'data'       => null
        ];
    }

    $data = json_decode($response, true);

    if ($httpCode === 200 && isset($data['data'])) {
        return [
            'success'    => true,
            'http_code'  => $httpCode,
            'latency_ms' => $latencyMs,
            'message'    => 'ALOC Station API Connected Successfully! Questions are streaming live.',
            'data'       => $data['data']
        ];
    }

    $errMsg = $data['error'] ?? ($data['message'] ?? "API returned HTTP {$httpCode}");
    return [
        'success'    => false,
        'http_code'  => $httpCode,
        'latency_ms' => $latencyMs,
        'message'    => "ALOC Station Response (HTTP {$httpCode}): {$errMsg}",
        'data'       => $data
    ];
}
