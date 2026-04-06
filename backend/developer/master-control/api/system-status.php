<?php

/**
 * MCC API — System Status
 * Returns full system status JSON for dashboard auto-refresh.
 */
require_once __DIR__ . '/../../../includes/config.php';
require_once INCLUDES_PATH . '/functions.php';
require_once BASE_PATH . '/app/bootstrap.php';

header('Content-Type: application/json');

// Access control — developer/admin only
SecurityGateway::guard([
  'require_auth' => true,
  'require_role' => ['admin', 'developer'],
]);

// Load controllers
require_once __DIR__ . '/../controllers/SystemController.php';
require_once __DIR__ . '/../controllers/SecurityController.php';
require_once __DIR__ . '/../controllers/AIController.php';
require_once __DIR__ . '/../controllers/DevOpsController.php';
require_once __DIR__ . '/../controllers/DatabaseController.php';
require_once __DIR__ . '/../controllers/HealingController.php';
require_once __DIR__ . '/../controllers/InstitutionController.php';

$section = $_GET['section'] ?? 'all';

try {
  switch ($section) {
    case 'system':
      echo json_encode(['ok' => true, 'data' => SystemController::getStatus()]);
      break;
    case 'security':
      echo json_encode(['ok' => true, 'data' => SecurityController::getStatus()]);
      break;
    case 'ai':
      echo json_encode(['ok' => true, 'data' => AIController::getStatus()]);
      break;
    case 'devops':
      echo json_encode(['ok' => true, 'data' => DevOpsController::getStatus()]);
      break;
    case 'database':
      echo json_encode(['ok' => true, 'data' => DatabaseController::getStatus()]);
      break;
    case 'healing':
      echo json_encode(['ok' => true, 'data' => HealingController::getStatus()]);
      break;
    case 'institution':
      echo json_encode(['ok' => true, 'data' => InstitutionController::getStatus()]);
      break;
    case 'all':
    default:
      echo json_encode([
        'ok'   => true,
        'data' => [
          'system'      => SystemController::getStatus(),
          'security'    => SecurityController::getStatus(),
          'ai'          => AIController::getStatus(),
          'devops'      => DevOpsController::getStatus(),
          'database'    => DatabaseController::getStatus(),
          'healing'     => HealingController::getStatus(),
          'institution' => InstitutionController::getStatus(),
        ],
        'timestamp' => date('c'),
      ]);
      break;
  }
} catch (\Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
