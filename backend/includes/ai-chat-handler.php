<?php
/**
 * AI Chat Handler - Process AI requests with security and intelligence
 */

session_start();
require_once 'config.php';
require_once 'functions.php';
require_once 'database.php';
require_once 'sams-ai-assistant.php';

// Security: Rate limiting
function checkRateLimit($user_id) {
    $cache_key = "ai_chat_limit_" . $user_id;
    $current_time = time();
    
    // Check if user has exceeded rate limit (10 messages per minute)
    if (isset($_SESSION[$cache_key])) {
        $requests = $_SESSION[$cache_key];
        $requests = array_filter($requests, function($time) use ($current_time) {
            return $current_time - $time < 60;
        });
        
        if (count($requests) >= 10) {
            return false;
        }
        
        $requests[] = $current_time;
        $_SESSION[$cache_key] = $requests;
    } else {
        $_SESSION[$cache_key] = [$current_time];
    }
    
    return true;
}

// Security: Input validation
function validateInput($data) {
    $message = $data['message'] ?? '';
    
    // Check for empty message
    if (empty(trim($message))) {
        return ['error' => 'Message cannot be empty'];
    }
    
    // Check message length
    if (strlen($message) > 1000) {
        return ['error' => 'Message too long'];
    }
    
    // Check for potential attacks
    $patterns = [
        '/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi',
        '/javascript:/i',
        '/on\w+\s*=/i'
    ];
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $message)) {
            return ['error' => 'Invalid message content'];
        }
    }
    
    return ['valid' => true];
}

// Security: Content filtering
function filterContent($message) {
    // Filter inappropriate content
    $blocked_words = [
        'password', 'hack', 'exploit', 'vulnerability', 
        'admin access', 'database', 'sql', 'inject'
    ];
    
    $message_lower = strtolower($message);
    foreach ($blocked_words as $word) {
        if (strpos($message_lower, $word) !== false) {
            return "I can't help with that request. Please ask about something else.";
        }
    }
    
    return $message;
}

// Main handler
header('Content-Type: application/json');

try {
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    
    $user_id = $_SESSION['user_id'];
    $user_role = $_SESSION['role'] ?? 'student';
    
    // Check rate limit
    if (!checkRateLimit($user_id)) {
        http_response_code(429);
        echo json_encode(['error' => 'Too many requests. Please wait a moment.']);
        exit;
    }
    
    // Get and validate input
    $input = json_decode(file_get_contents('php://input'), true);
    $validation = validateInput($input);
    
    if (isset($validation['error'])) {
        http_response_code(400);
        echo json_encode(['error' => $validation['error']]);
        exit;
    }
    
    // Filter content
    $message = filterContent($input['message']);
    
    // Initialize AI Assistant
    $ai_assistant = new SAMS_AI_Assistant($user_id, $user_role);
    
    // Get page context if available
    $page_context = $input['context'] ?? [];
    
    // Process the query
    $response = $ai_assistant->processQuery($message, $page_context);
    
    // Log interaction for learning (optional)
    logAIInteraction($user_id, $message, $response);
    
    // Return response
    echo json_encode([
        'success' => true,
        'response' => $response['message'] ?? 'I understand. Let me help you with that.',
        'type' => $response['type'] ?? 'general',
        'suggestions' => $response['suggestions'] ?? [],
        'data' => $response['data'] ?? null
    ]);
    
} catch (Exception $e) {
    error_log('AI Chat Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'An error occurred. Please try again.']);
}

/**
 * Log AI interactions for learning and analytics
 */
function logAIInteraction($user_id, $message, $response) {
    try {
        // Log to database for analytics and improvement
        $log_data = [
            'user_id' => $user_id,
            'message' => substr($message, 0, 500), // Limit length
            'response_type' => $response['type'] ?? 'unknown',
            'timestamp' => date('Y-m-d H:i:s'),
            'session_id' => session_id()
        ];
        
        // This would be stored in a dedicated ai_interactions table
        // For now, we'll just log to error log for debugging
        error_log('AI Interaction: ' . json_encode($log_data));
        
    } catch (Exception $e) {
        // Fail silently to not break the chat experience
        error_log('Failed to log AI interaction: ' . $e->getMessage());
    }
}
?>
