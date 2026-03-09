<?php
/**
 * API Endpoint: AI Form Processing
 * Receives Google Form submissions and creates user accounts
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, X-Webhook-Secret');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/AIAccountCreator.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Verify webhook secret if configured
$webhookSecret = $_SERVER['HTTP_X_WEBHOOK_SECRET'] ?? '';
$expectedSecret = defined('AI_WEBHOOK_SECRET') ? AI_WEBHOOK_SECRET : 'sams-webhook-secret';

if ($webhookSecret !== $expectedSecret) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized - Invalid webhook secret']);
    exit;
}

// Get POST data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON data']);
    exit;
}

try {
    // Initialize AI Account Creator
    $creator = new SAMS_AIAccountCreator();
    
    // Process form submission
    $result = $creator->processFormSubmission($data);
    
    // Log the request
    $logData = [
        'timestamp' => date('Y-m-d H:i:s'),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'result' => $result
    ];
    error_log('AI Form Processing: ' . json_encode($logData));
    
    // Return response
    if ($result['success']) {
        http_response_code(200);
    } else {
        http_response_code(422);
    }
    
    echo json_encode($result);
    
} catch (Exception $e) {
    http_response_code(500);
    error_log('AI Form Processing Error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error',
        'message' => $e->getMessage()
    ]);
}
