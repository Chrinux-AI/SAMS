<?php

/**
 * MCC API — Emergency Lockdown
 * POST: Activate/deactivate full system lockdown.
 * Effects: Disable login, freeze sessions, enable read-only mode.
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

require_once __DIR__ . '/../controllers/SecurityController.php';

$action = $_POST['action'] ?? '';

try {
  $lockFile = BASE_PATH . '/storage/lockdown.flag';

  switch ($action) {
    case 'activate':
      // 1. Enable maintenance mode
      SecurityController::enableMaintenanceMode();

      // 2. Create lockdown flag
      $dir = dirname($lockFile);
      if (!is_dir($dir)) mkdir($dir, 0755, true);
      file_put_contents($lockFile, json_encode([
        'active'       => true,
        'activated_by' => $_SESSION['user_id'] ?? 0,
        'activated_at' => date('c'),
        'reason'       => $_POST['reason'] ?? 'Emergency lockdown from MCC',
      ]), LOCK_EX);

      // 3. Force logout all users except current
      SecurityController::forceLogoutAll();

      try {
        AuditLogger::log('emergency_lockdown', 'system', 'EMERGENCY LOCKDOWN ACTIVATED from MCC', $_SESSION['user_id'] ?? null);
      } catch (\Throwable $e) {
      }

      echo json_encode(['ok' => true, 'status' => 'LOCKDOWN_ACTIVE']);
      break;

    case 'deactivate':
      // Remove lockdown
      if (is_file($lockFile)) @unlink($lockFile);

      // Disable maintenance mode
      SecurityController::disableMaintenanceMode();

      try {
        AuditLogger::log('lockdown_lifted', 'system', 'Emergency lockdown deactivated from MCC', $_SESSION['user_id'] ?? null);
      } catch (\Throwable $e) {
      }

      echo json_encode(['ok' => true, 'status' => 'LOCKDOWN_LIFTED']);
      break;

    case 'status':
      $active = false;
      $data = [];
      if (is_file($lockFile)) {
        $data = json_decode(file_get_contents($lockFile), true) ?: [];
        $active = !empty($data['active']);
      }
      echo json_encode(['ok' => true, 'active' => $active, 'data' => $data]);
      break;

    default:
      http_response_code(400);
      echo json_encode(['ok' => false, 'error' => 'Unknown action. Use: activate, deactivate, status']);
  }
} catch (\Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
