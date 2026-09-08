<?php
/**
 * StudyMe AI Platform — Payment Gateways Webhook Receiver
 */
require_once dirname(__DIR__) . '/config/main.php';

header('Content-Type: application/json');

// Mock response for future production webhook endpoint
echo json_encode([
    'status' => 'success',
    'message' => 'StudyMe Webhook Receiver Active. Ignored in development mode.',
    'env' => APP_ENV,
    'timestamp' => time()
]);
exit;
