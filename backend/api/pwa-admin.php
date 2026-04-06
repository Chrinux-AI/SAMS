<?php

/**
 * PWA Admin API
 * Handles admin-level PWA feature toggles.
 */

session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['success' => false, 'message' => 'Method not allowed']);
  exit;
}

$role = strtolower((string)($_SESSION['role'] ?? $_SESSION['user_role'] ?? ''));
if (!isset($_SESSION['user_id']) || !in_array($role, ['admin', 'super_admin', 'superadmin', 'owner'], true)) {
  http_response_code(403);
  echo json_encode(['success' => false, 'message' => 'Unauthorized']);
  exit;
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
  http_response_code(400);
  echo json_encode(['success' => false, 'message' => 'Invalid JSON payload']);
  exit;
}

$csrfToken = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($payload['csrf_token'] ?? ''));
if (!verify_csrf_token($csrfToken)) {
  http_response_code(403);
  echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
  exit;
}

$action = (string)($payload['action'] ?? '');

if ($action !== 'toggle_feature') {
  http_response_code(400);
  echo json_encode(['success' => false, 'message' => 'Invalid action']);
  exit;
}

if (!table_exists('pwa_feature_flags')) {
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => 'Feature flags table unavailable']);
  exit;
}

$featureName = trim((string)($payload['feature_name'] ?? ''));
$enabled = !empty($payload['enabled']) ? 1 : 0;

if ($featureName === '' || !preg_match('/^[a-zA-Z0-9_\-]+$/', $featureName)) {
  http_response_code(422);
  echo json_encode(['success' => false, 'message' => 'Invalid feature name']);
  exit;
}

$existing = db()->fetchOne('SELECT id FROM pwa_feature_flags WHERE feature_name = ? LIMIT 1', [$featureName]);

if ($existing) {
  db()->update('pwa_feature_flags', [
    'is_enabled' => $enabled,
    'updated_at' => date('Y-m-d H:i:s'),
  ], 'id = ?', [(int)$existing['id']]);
} else {
  db()->insert('pwa_feature_flags', [
    'feature_name' => $featureName,
    'description' => ucwords(str_replace(['_', '-'], ' ', $featureName)),
    'is_enabled' => $enabled,
    'created_at' => date('Y-m-d H:i:s'),
    'updated_at' => date('Y-m-d H:i:s'),
  ]);
}

if (function_exists('log_activity')) {
  log_activity((int)$_SESSION['user_id'], 'pwa_feature_toggle', 'pwa_feature_flags', 0, sprintf('Feature %s set to %s', $featureName, $enabled ? 'enabled' : 'disabled'));
}

echo json_encode([
  'success' => true,
  'message' => 'Feature updated successfully',
  'data' => [
    'feature_name' => $featureName,
    'enabled' => (bool)$enabled,
  ],
]);
exit;
