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
require_once __DIR__ . '/../includes/ai-context-filter.php';
require_once __DIR__ . '/../app/AICopilot/ActionRegistry.php';
require_once __DIR__ . '/../app/AICopilot/AIIntentParser.php';
require_once __DIR__ . '/../app/AICopilot/PermissionEngine.php';
require_once __DIR__ . '/../app/AICopilot/ValidationService.php';
require_once __DIR__ . '/../app/AICopilot/ConfirmationWorkflowManager.php';
require_once __DIR__ . '/../app/AICopilot/SecureApiGateway.php';
require_once __DIR__ . '/../app/AICopilot/AuditLoggingService.php';
require_once __DIR__ . '/../app/AICopilot/AIExecutionController.php';

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

// AI endpoint rate limiting (IP-based)
$client_ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$ai_ip_limit = rate_limiter()->check('ai_chat_ip', $client_ip, 30, 60);
if (!$ai_ip_limit['allowed']) {
    bot_json(['success' => false, 'error' => 'Too many requests. Please slow down.'], 429);
}
rate_limiter()->record('ai_chat_ip', $client_ip);

$raw = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!is_array($input)) {
    bot_json(['success' => false, 'error' => 'Invalid JSON payload'], 400);
}

$message = trim((string)($input['message'] ?? ''));
if ($message === '' && empty($input['copilot_execute'])) {
    bot_json(['success' => false, 'error' => 'Message required'], 400);
}

if (mb_strlen($message, 'UTF-8') > 1000) {
    bot_json(['success' => false, 'error' => 'Message is too long. Keep it under 1000 characters.'], 400);
}

// ── Secure AI Copilot Execution Pipeline (Explicit Mode) ────────────
if (!empty($input['copilot_execute'])) {
    try {
        $controller = new AICopilotExecutionController();
        $intentPayload = is_array($input['intent_payload'] ?? null) ? $input['intent_payload'] : [];
        $result = $controller->handle($message, $intentPayload);

        bot_json([
            'success' => (bool)($result['success'] ?? false),
            'mode' => 'secure_execution',
            'intent' => (string)($result['intent'] ?? 'UNKNOWN'),
            'message' => (string)($result['message'] ?? ''),
            'issues' => $result['issues'] ?? [],
            'data' => $result['data'] ?? [],
            'requires_confirmation' => (bool)($result['requires_confirmation'] ?? false),
            'confirmation_token' => $result['confirmation_token'] ?? null,
            'expires_in' => $result['expires_in'] ?? null,
            'ui_refresh_hint' => (bool)($result['ui_refresh_hint'] ?? false),
            'timestamp' => $result['timestamp'] ?? date('Y-m-d H:i:s')
        ], (int)($result['status'] ?? 200));
    } catch (Throwable $e) {
        error_log('SAMS Bot secure execution error: ' . $e->getMessage());
        bot_json([
            'success' => false,
            'mode' => 'secure_execution',
            'error' => 'Unable to process secure action request at this time.'
        ], 500);
    }
}

// ── AI Context Filtering (Role Isolation) ────────────
$aiFilter = AIContextFilter::filterMessage($userRole, $message);
if (!$aiFilter['allowed']) {
    bot_json([
        'success' => true,
        'response' => $aiFilter['response'],
        'intent' => 'blocked',
        'suggestions' => [],
        'actions' => [],
        'filtered' => true,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
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
    // Inject role-scoped system prompt into context
    $context['system_prompt'] = AIContextFilter::getSystemPrompt($userRole);
    $context['role_scope'] = $userRole;
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
