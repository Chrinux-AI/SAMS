<?php

/**
 * API — ACI Cycle
 * POST: Trigger a full ACI cycle manually.
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

echo json_encode(CommandAPI::handle('cycle'));
