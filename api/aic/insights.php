<?php

/**
 * API — AIC Insights
 * GET: Returns full institutional intelligence insights.
 * POST: Trigger a fresh AIC cycle.
 */
require_once __DIR__ . '/../../includes/config.php';
require_once INCLUDES_PATH . '/functions.php';
require_once BASE_PATH . '/app/bootstrap.php';

header('Content-Type: application/json');

SecurityGateway::guard([
  'require_auth' => true,
  'require_role' => ['admin', 'developer'],
]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  echo json_encode(['ok' => true, 'data' => InstitutionBrain::cycle()]);
} else {
  echo json_encode(['ok' => true, 'data' => InstitutionBrain::getStatus()]);
}
