<?php

/**
 * Ecosystem — Sync Patterns Endpoint
 *
 * Process:
 *   1. Local insights generated
 *   2. Sanitization applied
 *   3. Pattern hashed
 *   4. Uploaded to ecosystem registry
 *   5. Other schools receive improvements
 */
require_once __DIR__ . '/../includes/config.php';
require_once INCLUDES_PATH . '/database.php';
require_once INCLUDES_PATH . '/functions.php';
require_once BASE_PATH . '/app/bootstrap.php';

header('Content-Type: application/json');

// Auth: admin session or API key
session_start();
$authenticated = false;

if (isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'admin') {
  $authenticated = true;
} elseif (isset($_SERVER['HTTP_X_ECOSYSTEM_KEY'])) {
  $key = $_SERVER['HTTP_X_ECOSYSTEM_KEY'];
  $expected = getenv('ECOSYSTEM_API_KEY') ?: '';
  if ($expected && hash_equals($expected, $key)) {
    $authenticated = true;
  }
}

if (!$authenticated) {
  http_response_code(401);
  echo json_encode(['error' => 'Unauthorized']);
  exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'sync';

try {
  switch ($action) {
    case 'sync':
      $result = KnowledgeExchange::syncLocalInsights();
      echo json_encode(['success' => true, 'data' => $result]);
      break;

    case 'available':
      $category = $_GET['category'] ?? '';
      $result = KnowledgeExchange::getAvailableImprovements($category);
      echo json_encode(['success' => true, 'data' => $result]);
      break;

    case 'adopt':
      $id = (int)($_POST['exchange_id'] ?? 0);
      if ($id <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid exchange ID']);
        break;
      }
      $result = KnowledgeExchange::adopt($id);
      echo json_encode(['success' => $result]);
      break;

    default:
      echo json_encode(['error' => 'Unknown action']);
  }
} catch (\Throwable $e) {
  http_response_code(500);
  echo json_encode(['error' => 'Internal error']);
}
