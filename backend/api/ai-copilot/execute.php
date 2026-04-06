<?php

/**
 * Secure AI Copilot Execution Endpoint
 * Enforces intent parser -> permission -> validation -> confirmation -> API execution -> audit.
 */

session_start();

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/rate-limiter.php';

require_once __DIR__ . '/../../app/AICopilot/ActionRegistry.php';
require_once __DIR__ . '/../../app/AICopilot/AIIntentParser.php';
require_once __DIR__ . '/../../app/AICopilot/PermissionEngine.php';
require_once __DIR__ . '/../../app/AICopilot/ValidationService.php';
require_once __DIR__ . '/../../app/AICopilot/ConfirmationWorkflowManager.php';
require_once __DIR__ . '/../../app/AICopilot/SecureApiGateway.php';
require_once __DIR__ . '/../../app/AICopilot/AuditLoggingService.php';
require_once __DIR__ . '/../../app/AICopilot/AIExecutionController.php';

header('Content-Type: application/json');

function ai_exec_json(array $payload, int $status = 200): void
{
  http_response_code($status);
  echo json_encode($payload);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  ai_exec_json(['success' => false, 'message' => 'Method not allowed.'], 405);
}

if (!isset($_SESSION['user_id'])) {
  ai_exec_json(['success' => false, 'message' => 'Unauthorized.'], 401);
}

$raw = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!is_array($input)) {
  ai_exec_json(['success' => false, 'message' => 'Invalid JSON payload.'], 400);
}

// Endpoint-specific rate limit (per user)
$userIdKey = (string)($_SESSION['user_id'] ?? '0');
$limit = rate_limiter()->check('ai_copilot_execute_user', $userIdKey, 30, 60);
if (!$limit['allowed']) {
  ai_exec_json([
    'success' => false,
    'message' => 'Too many AI action requests. Please wait.',
    'retry_after' => (int)$limit['retry_after']
  ], 429);
}
rate_limiter()->record('ai_copilot_execute_user', $userIdKey);

$message = trim((string)($input['message'] ?? ''));
$explicit = is_array($input['intent_payload'] ?? null) ? $input['intent_payload'] : [];

if ($message === '' && empty($explicit)) {
  ai_exec_json(['success' => false, 'message' => 'Provide message or intent_payload.'], 400);
}

$controller = new AICopilotExecutionController();
$result = $controller->handle($message, $explicit);
ai_exec_json($result, (int)($result['status'] ?? 200));
