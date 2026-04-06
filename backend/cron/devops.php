<?php

/**
 * DevOps Kernel — Cron Entry Point
 *
 * Runs the full DevOps pipeline on schedule.
 * Recommended: every 15 minutes in production, every 5 minutes in dev.
 *
 * CLI:   php cron/devops.php
 * Admin: /cron/devops.php?key=dashboard (browser, requires admin session)
 */

$basePath = dirname(__DIR__);

require_once $basePath . '/includes/config.php';
require_once $basePath . '/includes/database.php';
require_once $basePath . '/includes/functions.php';
require_once $basePath . '/app/bootstrap.php';

// Security: browser access requires admin session or cron key
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

// Lock file to prevent overlapping runs
$lockFile = $basePath . '/storage/devops.lock';
if (!is_dir(dirname($lockFile))) {
  mkdir(dirname($lockFile), 0755, true);
}

if (is_file($lockFile)) {
  $lockAge = time() - filemtime($lockFile);
  if ($lockAge > 600) {
    unlink($lockFile); // Stale lock (> 10 minutes)
  } else {
    $msg = "DevOps cycle already running (lock age: {$lockAge}s)";
    if (php_sapi_name() === 'cli') {
      echo $msg . PHP_EOL;
    } else {
      echo json_encode(['status' => 'skipped', 'message' => $msg]);
    }
    exit;
  }
}

file_put_contents($lockFile, date('Y-m-d H:i:s'));

try {
  $result = DevOpsKernel::run();

  if (php_sapi_name() === 'cli') {
    echo "DevOps Kernel Cycle Complete" . PHP_EOL;
    echo "  System Score:   {$result['system_score']}/100" . PHP_EOL;
    echo "  Health Score:   {$result['health_score']}/100" . PHP_EOL;
    echo "  Security Score: {$result['security_score']}/100" . PHP_EOL;
    echo "  Deployment:     " . ($result['deployment_safe'] ? 'SAFE' : 'BLOCKED') . PHP_EOL;
    echo "  Drift:          " . ($result['drifted'] ? 'DETECTED' : 'NONE') . PHP_EOL;
    echo "  Incidents:      {$result['incidents']}" . PHP_EOL;
    echo "  Threats:        {$result['threats']}" . PHP_EOL;
    echo "  Repairs:        {$result['repairs']}" . PHP_EOL;
    echo "  Elapsed:        {$result['elapsed_ms']}ms" . PHP_EOL;
  } else {
    echo json_encode([
      'status'         => 'completed',
      'system_score'   => $result['system_score'],
      'health_score'   => $result['health_score'],
      'security_score' => $result['security_score'],
      'deployment_safe' => $result['deployment_safe'],
      'incidents'      => $result['incidents'],
      'threats'        => $result['threats'],
      'repairs'        => $result['repairs'],
      'elapsed_ms'     => $result['elapsed_ms'],
    ]);
  }
} catch (\Throwable $e) {
  ErrorCollector::log('devops_cron', 'FATAL: ' . $e->getMessage(), 'CRITICAL');
  if (php_sapi_name() === 'cli') {
    echo "FATAL ERROR: " . $e->getMessage() . PHP_EOL;
  } else {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
  }
} finally {
  if (is_file($lockFile)) {
    unlink($lockFile);
  }
}
