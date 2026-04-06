<?php

/**
 * Cron Entry — Ecosystem Kernel (Phase-10)
 * Runs the full ecosystem cycle: cognitive → tenants → federation → knowledge → analytics.
 *
 * Usage:
 *   php cron/ecosystem.php          (CLI)
 *   GET cron/ecosystem.php?key=...  (HTTP with CRON_KEY)
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
$lockFile = BASE_PATH . '/storage/ecosystem-cron.lock';
if (file_exists($lockFile)) {
  $lockAge = time() - filemtime($lockFile);
  if ($lockAge < 300) { // 5-minute lock
    $msg = "Ecosystem cron already running (lock age: {$lockAge}s)";
    echo $isCli ? "$msg\n" : json_encode(['status' => 'locked', 'message' => $msg]);
    exit;
  }
  unlink($lockFile);
}
file_put_contents($lockFile, date('c'));

$startTime = microtime(true);

try {
  $kernel = new EcosystemKernel();
  $result = $kernel->run();

  $duration = round(microtime(true) - $startTime, 3);
  $score = $result['ecosystem_score'] ?? 0;

  $output = [
    'status'    => 'completed',
    'score'     => $score,
    'duration'  => $duration . 's',
    'timestamp' => date('c'),
    'phases'    => count($result['phases'] ?? []),
  ];

  if ($isCli) {
    echo "=== Ecosystem Cycle Complete ===\n";
    echo "Score:    $score / 100\n";
    echo "Duration: {$duration}s\n";
    echo "Phases:   " . ($output['phases']) . "\n";
    echo "Time:     " . $output['timestamp'] . "\n";
  } else {
    header('Content-Type: application/json');
    echo json_encode($output, JSON_PRETTY_PRINT);
  }
} catch (\Throwable $e) {
  $error = $e->getMessage();
  if (class_exists('ErrorCollector')) {
    ErrorCollector::log('EcosystemCron', $error, 'HIGH');
  }
  error_log("Ecosystem cron failed: $error");

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
