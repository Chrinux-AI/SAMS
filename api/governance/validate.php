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
  if (!class_exists('ValidationPipeline')) {
    throw new RuntimeException('ValidationPipeline not loaded');
  }

  $result = ValidationPipeline::run();
  echo json_encode(['success' => true, 'data' => $result]);
} catch (\Throwable $e) {
  http_response_code(500);
  echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
