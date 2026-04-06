<?php

/**
 * MCC API — Cache Clear
 * POST: Clears system caches (all, AI, views, memory).
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

require_once __DIR__ . '/../controllers/AIController.php';

$target = $_POST['target'] ?? 'all';
$results = [];

try {
  switch ($target) {
    case 'ai':
      $results = AIController::clearAICache();
      break;

    case 'views':
      $cleared = 0;
      foreach (glob(BASE_PATH . '/cache/views_*.php') ?: [] as $f) {
        @unlink($f);
        $cleared++;
      }
      $results = ['cleared' => $cleared, 'type' => 'views'];
      break;

    case 'all':
    default:
      $cleared = 0;
      // Clear all cache files
      $cacheDirs = [BASE_PATH . '/cache'];
      foreach ($cacheDirs as $dir) {
        if (!is_dir($dir)) continue;
        foreach (glob($dir . '/*.{json,php,tmp}', GLOB_BRACE) ?: [] as $f) {
          @unlink($f);
          $cleared++;
        }
      }
      // Clear storage caches
      foreach (glob(BASE_PATH . '/storage/*-cache.json') ?: [] as $f) {
        @unlink($f);
        $cleared++;
      }
      // Flush CacheSynchronizer if available
      try {
        if (class_exists('CacheSynchronizer')) {
          CacheSynchronizer::flush('all');
        }
      } catch (\Throwable $e) {
      }

      $results = ['cleared' => $cleared, 'type' => 'all'];
      break;
  }

  try {
    AuditLogger::log('cache_clear', 'system', "Cache cleared: target=$target", $_SESSION['user_id'] ?? null);
  } catch (\Throwable $e) {
  }

  echo json_encode(['ok' => true, 'result' => $results]);
} catch (\Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
