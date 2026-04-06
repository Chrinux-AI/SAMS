<?php

/**
 * MCC API — Restart Service
 * POST: Restart/refresh specific services (sessions, cron, workers).
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

$service = $_POST['service'] ?? '';

try {
  switch ($service) {
    case 'sessions':
      // Force logout all users except current
      $result = SecurityController::forceLogoutAll();
      echo json_encode(['ok' => true, 'result' => $result]);
      break;

    case 'maintenance_on':
      $result = SecurityController::enableMaintenanceMode();
      echo json_encode(['ok' => true, 'result' => $result]);
      break;

    case 'maintenance_off':
      $result = SecurityController::disableMaintenanceMode();
      echo json_encode(['ok' => true, 'result' => $result]);
      break;

    case 'sync_panels':
      // Force sync — clear all cached summaries to trigger fresh recalculation
      $summaries = glob(BASE_PATH . '/storage/*-summary.json') ?: [];
      $refreshed = 0;
      foreach ($summaries as $f) {
        // Touch file to trigger reload, don't delete
        touch($f);
        $refreshed++;
      }
      echo json_encode(['ok' => true, 'result' => ['refreshed' => $refreshed]]);
      break;

    default:
      http_response_code(400);
      echo json_encode(['ok' => false, 'error' => 'Unknown service: ' . htmlspecialchars($service)]);
  }
} catch (\Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
