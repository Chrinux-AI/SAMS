<?php

/**
 * Cron Entry — Healing Kernel (SHPA)
 * Runs the self-healing cycle: detect → diagnose → repair → verify → learn.
 *
 * Usage:
 *   php cron/healing.php          (CLI)
 *   GET cron/healing.php?key=...  (HTTP with CRON_KEY)
 *
 * Recommended: run every 1-5 minutes via cron/scheduler.
 */

defined('BASE_PATH') || define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/database.php';
require_once BASE_PATH . '/app/bootstrap.php';

// Auth: CLI or valid cron key
$isCli = php_sapi_name() === 'cli';
if (!$isCli) {
  $cronKey = defined('CRON_KEY') ? CRON_KEY : 'sams-cron-2024';
  if (($_GET['key'] ?? '') !== $cronKey) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
  }
}

// Lock file to prevent overlapping runs
$lockFile = BASE_PATH . '/storage/healing-cron.lock';
if (file_exists($lockFile)) {
  $lockAge = time() - filemtime($lockFile);
  if ($lockAge < 120) { // 2-minute lock
    $msg = "Healing cron already running (lock age: {$lockAge}s)";
    echo $isCli ? "$msg\n" : json_encode(['status' => 'locked', 'message' => $msg]);
    exit;
  }
  unlink($lockFile);
}
file_put_contents($lockFile, date('c'));

$startTime = microtime(true);

try {
  $result = HealingKernel::run();

  $duration = round(microtime(true) - $startTime, 3);
  $score = $result['stability_score'] ?? 0;

  $output = [
    'status'    => 'completed',
    'score'     => $score,
    'duration'  => $duration . 's',
    'timestamp' => date('c'),
    'phases'    => count($result['phases'] ?? []),
  ];

  if ($isCli) {
    echo "=== Healing Cycle Complete ===\n";
    echo "Stability: $score / 100\n";
    echo "Duration:  {$duration}s\n";
    echo "Phases:    " . $output['phases'] . "\n";
    echo "Time:      " . $output['timestamp'] . "\n";
  } else {
    header('Content-Type: application/json');
    echo json_encode($output, JSON_PRETTY_PRINT);
  }
} catch (\Throwable $e) {
  $error = $e->getMessage();
  if (class_exists('ErrorCollector')) {
    ErrorCollector::log('HealingCron', $error, 'HIGH');
  }
  error_log("Healing cron failed: $error");

  if ($isCli) {
    echo "ERROR: $error\n";
    exit(1);
  } else {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $error]);
  }
} finally {
  if (file_exists($lockFile)) {
    unlink($lockFile);
  }
}
