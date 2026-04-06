<?php

require_once __DIR__ . '/../../includes/config.php';
header('Content-Type: application/json');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
  http_response_code(405);
  echo json_encode(['success' => false, 'error' => 'Method not allowed']);
  exit;
}

$role = strtolower((string)($_SESSION['role'] ?? $_SESSION['user_role'] ?? ''));
if (empty($_SESSION['user_id']) || !in_array($role, ['admin', 'super_admin', 'owner', 'developer'], true)) {
  http_response_code(403);
  echo json_encode(['success' => false, 'error' => 'Forbidden']);
  exit;
}

try {
  if (!class_exists('SystemLogger')) {
    throw new RuntimeException('SystemLogger not loaded');
  }

  $limit = max(1, min(200, (int)($_GET['limit'] ?? 50)));
  $actor = (string)($_GET['actor'] ?? '');

  echo json_encode([
    'success' => true,
    'data' => [
      'entries' => SystemLogger::getRecent($limit, $actor),
      'stats'   => SystemLogger::getStats(),
    ],
  ]);
} catch (\Throwable $e) {
  http_response_code(500);
  echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
