<?php

/**
 * MCC API — Repair Trigger
 * POST: Triggers healing cycle, auto-fix, route rebuild, or schema repair.
 */
require_once __DIR__ . '/../../../includes/config.php';
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

require_once __DIR__ . '/../controllers/DevOpsController.php';
require_once __DIR__ . '/../controllers/HealingController.php';
require_once __DIR__ . '/../controllers/DatabaseController.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
  switch ($action) {
    case 'autofix':
      echo json_encode(['ok' => true, 'result' => DevOpsController::runAutoFix()]);
      break;
    case 'healing':
      echo json_encode(['ok' => true, 'result' => HealingController::runHealingCycle()]);
      break;
    case 'routes':
      echo json_encode(['ok' => true, 'result' => DevOpsController::rebuildRoutes()]);
      break;
    case 'migrations':
      echo json_encode(['ok' => true, 'result' => DatabaseController::runMigrations()]);
      break;
    case 'optimize':
      echo json_encode(['ok' => true, 'result' => DatabaseController::optimizeTables()]);
      break;
    default:
      http_response_code(400);
      echo json_encode(['ok' => false, 'error' => 'Unknown action: ' . htmlspecialchars($action)]);
  }
} catch (\Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
