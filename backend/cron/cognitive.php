<?php

/**
 * Cognitive Kernel — Cron Entry Point
 *
 * Runs the full Cognitive Institution pipeline.
 * Recommended: every 30 minutes.
 *
 * CLI:   php cron/cognitive.php
 * Admin: /cron/cognitive.php?key=dashboard (browser, requires admin session)
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
$lockFile = $basePath . '/storage/cognitive.lock';
if (!is_dir(dirname($lockFile))) {
  mkdir(dirname($lockFile), 0755, true);
}

if (is_file($lockFile)) {
  $lockAge = time() - filemtime($lockFile);
  if ($lockAge > 1200) {
    unlink($lockFile); // Stale lock (> 20 minutes)
  } else {
    $msg = "Cognitive cycle already running (lock age: {$lockAge}s)";
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
  $result = CognitiveKernel::run();

  if (php_sapi_name() === 'cli') {
    echo "Cognitive Kernel Cycle Complete" . PHP_EOL;
    echo "  Cognitive Score:   {$result['cognitive_score']}/100" . PHP_EOL;
    echo "  Intelligence:      {$result['intelligence_score']}/100" . PHP_EOL;
    echo "  Academic:          {$result['academic_score']}/100" . PHP_EOL;
    echo "  Adaptive:          {$result['adaptive_score']}/100" . PHP_EOL;
    echo "  Interaction:       {$result['interaction_score']}/100" . PHP_EOL;
    echo "  Ethics:            " . ($result['ethics_safe'] ? 'SAFE' : 'ALERT') . PHP_EOL;
    echo "  Insights:          {$result['insights_generated']}" . PHP_EOL;
    echo "  Policies Triggered:{$result['policies_triggered']}" . PHP_EOL;
    echo "  Frictions:         {$result['frictions_detected']}" . PHP_EOL;
    echo "  Elapsed:           {$result['elapsed_ms']}ms" . PHP_EOL;
  } else {
    echo json_encode([
      'status'              => 'completed',
      'cognitive_score'     => $result['cognitive_score'],
      'intelligence_score'  => $result['intelligence_score'],
      'academic_score'      => $result['academic_score'],
      'adaptive_score'      => $result['adaptive_score'],
      'interaction_score'   => $result['interaction_score'],
      'ethics_safe'         => $result['ethics_safe'],
      'insights_generated'  => $result['insights_generated'],
      'policies_triggered'  => $result['policies_triggered'],
      'frictions_detected'  => $result['frictions_detected'],
      'elapsed_ms'          => $result['elapsed_ms'],
    ]);
  }
} catch (\Throwable $e) {
  ErrorCollector::log('cognitive_cron', 'FATAL: ' . $e->getMessage(), 'CRITICAL');
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
