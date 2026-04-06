<?php

require_once __DIR__ . '/../../includes/config.php';
header('Content-Type: application/json');

$role = strtolower((string)($_SESSION['role'] ?? $_SESSION['user_role'] ?? ''));
if (empty($_SESSION['user_id']) || !in_array($role, ['admin', 'super_admin', 'owner', 'developer'], true)) {
  http_response_code(403);
  echo json_encode(['success' => false, 'error' => 'Forbidden']);
  exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
  http_response_code(405);
  echo json_encode(['success' => false, 'error' => 'Method not allowed']);
  exit;
}

if (function_exists('verify_csrf_token')) {
  $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
  if (!$token || !verify_csrf_token($token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'CSRF validation failed']);
    exit;
  }
}

try {
  if (!class_exists('GovernanceEngine')) {
    throw new RuntimeException('GovernanceEngine not loaded');
  }

  $result = GovernanceEngine::emergencyRecovery();
  echo json_encode(['success' => true, 'data' => $result]);
} catch (\Throwable $e) {
  http_response_code(500);
  echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
