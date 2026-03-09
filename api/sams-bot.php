<?php
/**
 * SAMS Bot API
 * Tenant-aware AI endpoint with moderate security and rate limits.
 */

session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/sams-ai-engine.php';

header('Content-Type: application/json');

function bot_json($payload, $statusCode = 200)
{
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    bot_json(['success' => false, 'error' => 'Method not allowed'], 405);
}

if (!isset($_SESSION['user_id'])) {
    bot_json(['success' => false, 'error' => 'Unauthorized'], 401);
}

$userId = (int)$_SESSION['user_id'];
$userRole = (string)($_SESSION['role'] ?? ($_SESSION['user_role'] ?? 'guest'));

$raw = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!is_array($input)) {
    bot_json(['success' => false, 'error' => 'Invalid JSON payload'], 400);
}

$message = trim((string)($input['message'] ?? ''));
if ($message === '') {
    bot_json(['success' => false, 'error' => 'Message required'], 400);
}

if (mb_strlen($message, 'UTF-8') > 1000) {
    bot_json(['success' => false, 'error' => 'Message is too long. Keep it under 1000 characters.'], 400);
}

$engine = new SAMS_AI_Engine(
    $userId,
    $userRole,
    session_id(),
    (string)($_SERVER['REMOTE_ADDR'] ?? ''),
    (string)($_SERVER['HTTP_USER_AGENT'] ?? '')
);

$rate = $engine->enforceRateLimit($message);
if (!$rate['allowed']) {
    bot_json([
        'success' => false,
        'error' => 'Rate limit reached. Please try again shortly.',
        'retry_after' => (int)$rate['retry_after'],
        'remaining_minute' => 0,
        'remaining_hour' => (int)$rate['remaining_hour']
    ], 429);
}

$security = $engine->securityAssessment($message);
if (!$security['allow']) {
    bot_json([
        'success' => false,
        'error' => 'Your message was blocked for security reasons. Please rephrase it.',
        'risk_level' => $security['risk_level']
    ], 400);
}

try {
    $context = isset($input['context']) && is_array($input['context']) ? $input['context'] : [];
    $result = $engine->processMessage($message, $context);

    bot_json([
        'success' => true,
        'response' => (string)($result['message'] ?? 'I can help with that.'),
        'intent' => (string)($result['intent'] ?? 'general'),
        'suggestions' => array_values($result['suggestions'] ?? []),
        'actions' => array_values($result['actions'] ?? []),
        'learning_cards' => array_values($result['learning_cards'] ?? []),
        'tenant' => $result['tenant'] ?? $engine->getTenantContext(),
        'rate_limit' => [
            'remaining_minute' => (int)$rate['remaining_minute'],
            'remaining_hour' => (int)$rate['remaining_hour']
        ],
        'timestamp' => date('Y-m-d H:i:s')
    ]);
} catch (Throwable $e) {
    error_log('SAMS Bot API error: ' . $e->getMessage());
    bot_json([
        'success' => false,
        'error' => 'AI assistant is temporarily unavailable. Please try again.'
    ], 500);
}
?>
