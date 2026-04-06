<?php

/**
 * cron/os.php — Autonomous School OS Cron Entry Point
 *
 * Runs the full OS Kernel cycle: boot → schedule → automate → heal.
 * Execute via: php cron/os.php  (CLI)
 * Or via HTTP with ?key=<CRON_KEY>
 *
 * Recommended: every 2 minutes via cron/Task Scheduler.
 */

// CLI or authenticated HTTP only
$isCli = (php_sapi_name() === 'cli');
if (!$isCli) {
  $cronKey = $_GET['key'] ?? '';
  $validKey = getenv('CRON_KEY') ?: 'sams-cron-key';
  if ($cronKey !== $validKey) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
  }
}

require_once __DIR__ . '/../includes/config.php';
require_once INCLUDES_PATH . '/functions.php';
require_once BASE_PATH . '/app/bootstrap.php';

// Lock file to prevent overlapping runs
$lockFile = BASE_PATH . '/storage/os-kernel.lock';
$lockDir  = dirname($lockFile);
if (!is_dir($lockDir)) mkdir($lockDir, 0755, true);

if (is_file($lockFile)) {
  $lockAge = time() - filemtime($lockFile);
  if ($lockAge < 120) {
    $msg = "OS Kernel locked ({$lockAge}s ago). Skipping.";
    if ($isCli) echo $msg . "\n";
    else echo json_encode(['status' => 'locked', 'message' => $msg]);
    exit;
  }
  // Stale lock — remove
  unlink($lockFile);
}

file_put_contents($lockFile, date('c'));

try {
  // Seed defaults if needed
  ProcessScheduler::seedDefaults();
  AutomationEngine::seedDefaults();

  // Run OS Kernel cycle
  $result = OSKernel::run();

  // Prune presence data
  PresenceService::prune();

  if ($isCli) {
    echo "═══ OS Kernel Cycle Complete ═══\n";
    echo "Health:   {$result['os_health']}/100\n";
    echo "Phases:   {$result['phase_count']}\n";
    echo "Duration: {$result['duration']}s\n";
    echo "Time:     {$result['timestamp']}\n";
  } else {
    header('Content-Type: application/json');
    echo json_encode($result, JSON_PRETTY_PRINT);
  }
} catch (\Throwable $e) {
  $error = 'OS Kernel cron error: ' . $e->getMessage();
  ErrorCollector::log('os_cron', $error, 'CRITICAL');
  if ($isCli) echo "ERROR: {$error}\n";
  else {
    http_response_code(500);
    echo json_encode(['error' => $error]);
  }
} finally {
  if (is_file($lockFile)) {
    unlink($lockFile);
  }
}
