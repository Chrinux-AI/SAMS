<?php

/**
 * API — ACI Execute
 * POST: Execute an ACI action manually.
 * Required: action (string)
 */
require_once __DIR__ . '/../../includes/config.php';
require_once INCLUDES_PATH . '/functions.php';
require_once BASE_PATH . '/app/bootstrap.php';

header('Content-Type: application/json');

SecurityGateway::guard([
  'require_auth' => true,
  'require_role' => ['admin', 'developer'],
]);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'error' => 'POST required']);
  exit;
}

$action = $_POST['action'] ?? '';
$context = $_POST;
unset($context['action']);

echo json_encode(CommandAPI::handle('execute', ['action' => $action] + $context));
