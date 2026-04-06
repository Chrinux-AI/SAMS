<?php

/**
 * Device Sync API — Smart School Device Integration
 *
 * Endpoints for device registration, data sync, and status checks.
 * Used by biometric scanners, tablets, kiosks.
 *
 * Methods: GET (status), POST (sync/register)
 */
require_once __DIR__ . '/../includes/config.php';
require_once INCLUDES_PATH . '/functions.php';
require_once BASE_PATH . '/app/bootstrap.php';

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Simple API key authentication for devices

$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
$validKey = trim((string)(getenv('DEVICE_API_KEY') ?: ''));

$isStatusRequest = $method === 'GET' && (($_GET['action'] ?? 'status') === 'status');

if ($validKey === '' && !$isStatusRequest) {
  http_response_code(503);
  echo json_encode(['error' => 'Service unavailable', 'message' => 'DEVICE_API_KEY is not configured']);
  exit;
}

if (!$isStatusRequest && (!hash_equals($validKey, (string)$apiKey))) {
  http_response_code(401);
  echo json_encode(['error' => 'Unauthorized', 'message' => 'Invalid API key']);
  exit;
}

$action = $_GET['action'] ?? 'status';

try {
  switch ($method) {
    case 'GET':
      handleGet($action);
      break;
    case 'POST':
      handlePost($action);
      break;
    default:
      http_response_code(405);
      echo json_encode(['error' => 'Method not allowed']);
  }
} catch (\Throwable $e) {
  http_response_code(500);
  ErrorCollector::log('device_api', 'API error: ' . $e->getMessage(), 'HIGH');
  echo json_encode(['error' => 'Internal server error']);
}

function handleGet(string $action): void
{
  switch ($action) {
    case 'status':
      echo json_encode([
        'status'   => 'ok',
        'version'  => defined('APP_VERSION') ? APP_VERSION : '1.0.0',
        'time'     => date('c'),
        'devices'  => DeviceIntegration::syncStatus(),
      ]);
      break;

    case 'devices':
      echo json_encode([
        'devices'  => DeviceIntegration::getRegisteredDevices(),
      ]);
      break;

    default:
      http_response_code(400);
      echo json_encode(['error' => 'Unknown action: ' . $action]);
  }
}

function handlePost(string $action): void
{
  $input = json_decode(file_get_contents('php://input'), true);
  if (!is_array($input)) {
    $input = $_POST;
  }

  switch ($action) {
    case 'register':
      $deviceId = $input['device_id'] ?? '';
      $type     = $input['type'] ?? 'generic';
      $name     = $input['name'] ?? '';

      if (!$deviceId || !$name) {
        http_response_code(400);
        echo json_encode(['error' => 'device_id and name are required']);
        return;
      }

      $result = DeviceIntegration::registerDevice(
        (string) $deviceId,
        (string) $type,
        (string) $name,
        $input['meta'] ?? []
      );
      echo json_encode($result);
      break;

    case 'sync':
      $deviceId = $input['device_id'] ?? '';
      $data     = $input['data'] ?? [];

      if (!$deviceId) {
        http_response_code(400);
        echo json_encode(['error' => 'device_id is required']);
        return;
      }

      $result = DeviceIntegration::syncDevice((string) $deviceId, $data);
      echo json_encode($result);
      break;

    case 'heartbeat':
      $deviceId = $input['device_id'] ?? '';
      if (!$deviceId) {
        http_response_code(400);
        echo json_encode(['error' => 'device_id is required']);
        return;
      }

      // Update last sync time
      if (table_exists('devices')) {
        $pdo = db()->getConnection();
        $stmt = $pdo->prepare("UPDATE devices SET last_sync = NOW() WHERE device_id = ?");
        $stmt->execute([$deviceId]);
      }

      echo json_encode(['success' => true, 'time' => date('c')]);
      break;

    default:
      http_response_code(400);
      echo json_encode(['error' => 'Unknown action: ' . $action]);
  }
}
