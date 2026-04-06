<?php

/**
 * API — AIC Status
 * GET: Returns current Institutional Consciousness status.
 */
require_once __DIR__ . '/../../includes/config.php';
require_once INCLUDES_PATH . '/functions.php';
require_once BASE_PATH . '/app/bootstrap.php';

header('Content-Type: application/json');

SecurityGateway::guard([
  'require_auth' => true,
  'require_role' => ['admin', 'developer'],
]);

echo json_encode(['ok' => true, 'data' => InstitutionBrain::getStatus()]);
