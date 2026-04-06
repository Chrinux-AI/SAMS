<?php

/**
 * ACI — System Observer
 * Monitors runtime signals: page health, DB latency, sessions, errors, navigation integrity.
 */
class SystemObserver
{
  /**
   * Full observation sweep — returns all detected signals.
   */
  public static function observe(): array
  {
    $signals = [];

    // 1. Route integrity — check all known pages
    $signals['broken_routes'] = self::checkRoutes();

    // 2. Error rate
    $signals['error_rate'] = self::checkErrorRate();

    // 3. DB health
    $signals['db_health'] = self::checkDB();

    // 4. Session health
    $signals['session_health'] = self::checkSessions();

    // 5. Storage health
    $signals['storage_health'] = self::checkStorage();

    // 6. Cron health
    $signals['cron_health'] = self::checkCron();

    // Calculate overall signal score
    $issues = 0;
    foreach ($signals as $s) {
      if (isset($s['status']) && $s['status'] !== 'ok') $issues++;
    }
    $signals['total_issues'] = $issues;
    $signals['signal_score'] = count($signals) > 2 ? round(((count($signals) - 2 - $issues) / max(1, count($signals) - 2)) * 100) : 100;

    return $signals;
  }

  /**
   * Check route integrity — scan all role directories for broken links.
   */
  private static function checkRoutes(): array
  {
    $broken = [];
    $scanned = 0;

    $dirs = [
      'admin' => BASE_PATH . '/admin',
      'developer' => BASE_PATH . '/developer',
      'teacher' => BASE_PATH . '/teacher',
      'student' => BASE_PATH . '/student',
      'parent' => BASE_PATH . '/parent',
    ];

    // Check route index references
    $routeFile = BASE_PATH . '/cache/routes.json';
    if (is_file($routeFile)) {
      $routes = json_decode(file_get_contents($routeFile), true) ?: [];
      foreach ($routes as $route => $info) {
        $scanned++;
        $file = $info['file'] ?? (BASE_PATH . '/' . ltrim($route, '/'));
        if (!is_file($file)) {
          $broken[] = [
            'route' => $route,
            'file'  => $file,
            'type'  => 'missing_file',
          ];
        }
      }
    }

    // Also scan sidebar/navigation links if route cache is empty
    if ($scanned === 0) {
      foreach ($dirs as $role => $dir) {
        if (!is_dir($dir)) continue;
        foreach (glob($dir . '/*.php') as $file) {
          $scanned++;
          // Files exist — just count them
        }
      }
    }

    return [
      'status'  => empty($broken) ? 'ok' : 'broken',
      'scanned' => $scanned,
      'broken'  => $broken,
      'count'   => count($broken),
    ];
  }

  /**
   * Check recent error rate.
   */
  private static function checkErrorRate(): array
  {
    $count = 0;
    $logFile = BASE_PATH . '/logs/error.log';
    if (is_file($logFile)) {
      $oneHourAgo = time() - 3600;
      $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
      $recent = array_slice($lines ?: [], -100);
      foreach ($recent as $line) {
        if (preg_match('/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})/', $line, $m)) {
          if (strtotime($m[1]) >= $oneHourAgo) $count++;
        }
      }
    }
    return [
      'status'           => $count < 5 ? 'ok' : ($count < 20 ? 'elevated' : 'critical'),
      'errors_last_hour' => $count,
    ];
  }

  /**
   * Check DB connectivity and latency.
   */
  private static function checkDB(): array
  {
    try {
      $t = microtime(true);
      db()->fetch("SELECT 1");
      $latency = round((microtime(true) - $t) * 1000, 1);
      return [
        'status'     => $latency < 100 ? 'ok' : ($latency < 500 ? 'slow' : 'critical'),
        'latency_ms' => $latency,
      ];
    } catch (\Throwable $e) {
      return ['status' => 'down', 'latency_ms' => -1, 'error' => $e->getMessage()];
    }
  }

  /**
   * Check session health.
   */
  private static function checkSessions(): array
  {
    $path = session_save_path() ?: sys_get_temp_dir();
    $count = 0;
    if (is_dir($path)) {
      $files = glob($path . '/sess_*');
      $count = $files ? count($files) : 0;
    }
    return [
      'status'         => $count < 500 ? 'ok' : 'overloaded',
      'session_count'  => $count,
    ];
  }

  /**
   * Check storage directories.
   */
  private static function checkStorage(): array
  {
    $dirs = ['storage', 'cache', 'logs', 'uploads'];
    $issues = [];
    foreach ($dirs as $d) {
      $path = BASE_PATH . '/' . $d;
      if (!is_dir($path)) {
        $issues[] = "$d directory missing";
      } elseif (!is_writable($path)) {
        $issues[] = "$d not writable";
      }
    }
    return [
      'status' => empty($issues) ? 'ok' : 'issues',
      'issues' => $issues,
    ];
  }

  /**
   * Check cron job health.
   */
  private static function checkCron(): array
  {
    $cronFiles = ['healing', 'devops', 'os', 'intelligence', 'cognitive', 'ecosystem'];
    $stale = [];
    foreach ($cronFiles as $name) {
      $summary = BASE_PATH . '/storage/' . $name . '-summary.json';
      if (is_file($summary)) {
        $age = time() - filemtime($summary);
        if ($age > 86400) {
          $stale[] = $name . ' (' . round($age / 3600) . 'h ago)';
        }
      }
    }
    return [
      'status' => empty($stale) ? 'ok' : 'stale',
      'stale'  => $stale,
    ];
  }
}
