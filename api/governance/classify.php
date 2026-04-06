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
  if (!class_exists('GovernanceEngine')) {
    throw new RuntimeException('GovernanceEngine not loaded');
  }

  $file = trim((string)($_GET['file'] ?? ''));
  if ($file === '') {
    throw new InvalidArgumentException('Missing query parameter: file');
  }
  if (strlen($file) > 260 || strpos($file, '..') !== false || preg_match('/[\x00-\x1F]/', $file)) {
    throw new InvalidArgumentException('Invalid file parameter');
  }

  $classification = GovernanceEngine::classifyChange($file);
  $zone = GovernanceEngine::getZone($file);
  $rule = GovernanceEngine::getDeploymentRule($classification);

  echo json_encode([
    'success' => true,
    'data' => [
      'file'           => $file,
      'classification' => $classification,
      'zone'           => $zone,
      'deployment'     => $rule,
    ],
  ]);
} catch (\Throwable $e) {
  http_response_code(400);
  echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
