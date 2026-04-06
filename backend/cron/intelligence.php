<?php

/**
 * Intelligence Kernel — Cron Entry Point
 *
 * Runs the full Platform Intelligence pipeline.
 * Recommended: every 15 minutes.
 *
 * CLI:   php cron/intelligence.php
 * Admin: /cron/intelligence.php?key=dashboard (browser, requires admin session)
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
$lockFile = $basePath . '/storage/intelligence.lock';
if (!is_dir(dirname($lockFile))) {
  mkdir(dirname($lockFile), 0755, true);
}

if (is_file($lockFile)) {
  $lockAge = time() - filemtime($lockFile);
  if ($lockAge > 900) {
    unlink($lockFile); // Stale lock (> 15 minutes)
  } else {
    $msg = "Intelligence cycle already running (lock age: {$lockAge}s)";
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
  $result = IntelligenceKernel::run();

  if (php_sapi_name() === 'cli') {
    echo "Intelligence Kernel Cycle Complete" . PHP_EOL;
    echo "  Intelligence Score: {$result['intelligence_score']}/100" . PHP_EOL;
    echo "  DevOps Score:       {$result['devops_score']}/100" . PHP_EOL;
    echo "  Behavior Score:     {$result['behavior_score']}/100" . PHP_EOL;
    echo "  Prediction Risk:    {$result['prediction_risk']}" . PHP_EOL;
    echo "  Decisions Made:     {$result['decisions_made']}" . PHP_EOL;
    echo "  Anomalies:          {$result['anomalies']}" . PHP_EOL;
    echo "  Graph Nodes:        {$result['graph_nodes']}" . PHP_EOL;
    echo "  Graph Edges:        {$result['graph_edges']}" . PHP_EOL;
    echo "  Elapsed:            {$result['elapsed_ms']}ms" . PHP_EOL;
  } else {
    echo json_encode([
      'status'             => 'completed',
      'intelligence_score' => $result['intelligence_score'],
      'devops_score'       => $result['devops_score'],
      'behavior_score'     => $result['behavior_score'],
      'prediction_risk'    => $result['prediction_risk'],
      'decisions_made'     => $result['decisions_made'],
      'anomalies'          => $result['anomalies'],
      'graph_nodes'        => $result['graph_nodes'],
      'graph_edges'        => $result['graph_edges'],
      'elapsed_ms'         => $result['elapsed_ms'],
    ]);
  }
} catch (\Throwable $e) {
  ErrorCollector::log('intelligence_cron', 'FATAL: ' . $e->getMessage(), 'CRITICAL');
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
