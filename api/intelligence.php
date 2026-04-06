<?php

/**
 * Smart API — HTTP Entry Point
 *
 * Routes intelligence API requests to SmartAPI::handle()
 * Requires INTELLIGENCE_API_KEY header or admin session.
 *
 * Usage: /api/intelligence.php?endpoint=health
 */

$basePath = dirname(__DIR__);

require_once $basePath . '/includes/config.php';
require_once $basePath . '/includes/database.php';
require_once $basePath . '/includes/functions.php';
require_once $basePath . '/app/bootstrap.php';

header('Content-Type: application/json');

$sessionRole = strtolower((string)($_SESSION['role'] ?? $_SESSION['user_role'] ?? ''));
$isPrivilegedSession = isset($_SESSION['user_id']) && in_array($sessionRole, ['admin', 'super_admin', 'owner', 'developer'], true);
$apiKey = (string)($_SERVER['HTTP_X_INTELLIGENCE_API_KEY'] ?? '');
$expectedApiKey = trim((string)(getenv('INTELLIGENCE_API_KEY') ?: ''));
$hasValidApiKey = $expectedApiKey !== '' && hash_equals($expectedApiKey, $apiKey);

if (!$isPrivilegedSession && !$hasValidApiKey) {
  http_response_code(403);
  echo json_encode(['error' => 'Forbidden']);
  exit;
}

$endpoint = $_GET['endpoint'] ?? '';
$params = $_GET;
unset($params['endpoint']);

if (!$endpoint) {
  echo json_encode([
    'error' => 'Missing endpoint parameter',
    'available' => SmartAPI::getAvailableEndpoints(),
  ]);
  exit;
}

try {
  $result = SmartAPI::handle($endpoint, $params);
  echo json_encode($result);
} catch (\Throwable $e) {
  http_response_code(500);
  echo json_encode(['error' => $e->getMessage()]);
}
