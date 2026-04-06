<?php
/**
 * Chatbot API Endpoint
 * Processes user messages and returns responses
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/services/ChatbotService.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Get POST data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['message'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Message required']);
    exit;
}

try {
    $container = SAMS_ServiceContainer::getInstance();
    $chatbot = new SAMS_ChatbotService($container);
    
    $result = $chatbot->processMessage($data['message']);
    
    echo json_encode([
        'success' => true,
        'response' => $result['response'],
        'intent' => $result['intent'],
        'confidence' => $result['confidence'],
        'suggested_actions' => $result['suggested_actions'] ?? []
    ]);
    
} catch (Exception $e) {
    error_log('Chatbot Error: ' . $e->getMessage());
    echo json_encode([
        'success' => true, // Return success with fallback message
        'response' => 'I\'m sorry, I\'m having trouble processing your request right now. Please try again later.',
        'intent' => 'error',
        'confidence' => 0
    ]);
}
