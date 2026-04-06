<?php

/**
 * Autonomous Fix Loop — Cron Entry Point
 *
 * Run via cron or scheduled task:
 *   Dev:  every 2 minutes   star/2 * * * * php /path/to/cron/autofix.php
 *   Prod: every 15 minutes  star/15 * * * * php /path/to/cron/autofix.php
 *
 * Can also be triggered via browser by admin:
 *   /cron/autofix.php?key=YOUR_CRON_KEY
 */

// Determine base path
$basePath = dirname(__DIR__);

// Load core dependencies
require_once $basePath . '/includes/config.php';
require_once $basePath . '/includes/database.php';
require_once $basePath . '/includes/functions.php';
require_once $basePath . '/app/bootstrap.php';

// Security: If run from browser, require a secret key or admin session
if (php_sapi_name() !== 'cli') {
  session_start();
  $cronKey = $_GET['key'] ?? '';
  $isAdmin = !empty($_SESSION['role']) && $_SESSION['role'] === 'admin';

  if (!$isAdmin && $cronKey !== (defined('CRON_SECRET_KEY') ? CRON_SECRET_KEY : '')) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
  }

  header('Content-Type: application/json');
}

// Prevent overlapping runs via lock file
$lockFile = $basePath . '/storage/autofix.lock';
if (!is_dir(dirname($lockFile))) {
  mkdir(dirname($lockFile), 0755, true);
}

if (is_file($lockFile)) {
  $lockAge = time() - filemtime($lockFile);
  // Stale lock (older than 5 minutes) — remove it
  if ($lockAge > 300) {
    unlink($lockFile);
  } else {
    $msg = 'Autofix already running (lock age: ' . $lockAge . 's)';
    if (php_sapi_name() === 'cli') {
      echo $msg . PHP_EOL;
    } else {
      echo json_encode(['status' => 'skipped', 'message' => $msg]);
    }
    exit;
  }
}

// Create lock
file_put_contents($lockFile, date('Y-m-d H:i:s'));

try {
  $result = AutonomousFixLoop::run();

  // Output result
  if (php_sapi_name() === 'cli') {
    echo "Autonomous Fix Loop Complete" . PHP_EOL;
    echo "  Score:      {$result['final_score']}/100 ({$result['final_grade']})" . PHP_EOL;
    echo "  Iterations: {$result['iterations']}" . PHP_EOL;
    echo "  Repairs:    {$result['repairs_made']}" . PHP_EOL;
  } else {
    echo json_encode([
      'status' => 'completed',
      'score'  => $result['final_score'],
      'grade'  => $result['final_grade'],
      'iterations' => $result['iterations'],
      'repairs'    => $result['repairs_made'],
    ]);
  }
} catch (\Throwable $e) {
  ErrorCollector::log('cron', 'FATAL: ' . $e->getMessage(), 'CRITICAL');
  if (php_sapi_name() === 'cli') {
    echo "FATAL ERROR: " . $e->getMessage() . PHP_EOL;
  } else {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
  }
} finally {
  // Release lock
  if (is_file($lockFile)) {
    unlink($lockFile);
  }
}
