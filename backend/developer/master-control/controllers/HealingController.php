<?php

/**
 * MCC — Healing Engine Monitor Controller
 * Self-healing activity, repair logs, stability trends.
 */
class HealingController
{
  public static function getStatus(): array
  {
    $data = [
      'stability_score' => 0,
      'repairs_today'   => 0,
      'total_repairs'   => 0,
      'last_run'        => 'Never',
      'recent_repairs'  => [],
      'memory_stats'    => [],
      'route_status'    => [],
    ];

    try {
      $dashboard = HealingKernel::getDashboardData();
      $lastRun = $dashboard['last_run'] ?? [];

      $data['stability_score'] = $lastRun['stability_score'] ?? 0;
      $data['last_run'] = $lastRun['timestamp'] ?? 'Never';
      $data['duration'] = $lastRun['duration'] ?? 0;
      $data['phases'] = $lastRun['phases'] ?? [];
      $data['memory_stats'] = $dashboard['memory'] ?? [];
      $data['route_status'] = $dashboard['routes'] ?? [];
    } catch (\Throwable $e) {
    }

    // Parse healing log for recent repairs
    try {
      $logFile = BASE_PATH . '/logs/healing.log';
      if (is_file($logFile)) {
        $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $recent = array_slice($lines ?: [], -20);
        $today = date('Y-m-d');
        foreach ($recent as $line) {
          $data['recent_repairs'][] = $line;
          if (strpos($line, $today) !== false && strpos($line, '[OK]') !== false) {
            $data['repairs_today']++;
          }
        }
        $data['total_repairs'] = count(array_filter($lines ?: [], fn($l) => strpos($l, '[OK]') !== false));
      }
    } catch (\Throwable $e) {
    }

    return $data;
  }

  public static function runHealingCycle(): array
  {
    try {
      AuditLogger::log('manual_healing', 'system', 'Manual healing cycle triggered from MCC', $_SESSION['user_id'] ?? null);
    } catch (\Throwable $e) {
    }

    try {
      $result = HealingKernel::run();
      return ['status' => 'completed', 'result' => $result];
    } catch (\Throwable $e) {
      return ['status' => 'error', 'message' => $e->getMessage()];
    }
  }
}
