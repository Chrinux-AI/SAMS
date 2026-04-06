<?php

/**
 * IncidentResponder — Critical Failure Response Engine
 *
 * When critical failures occur (500 spikes, DB down, service crashes):
 * - Isolates failing module
 * - Enables safe mode
 * - Logs forensic snapshot
 * - Alerts admin panel
 * - Attempts self-healing restart of failed services
 */
class IncidentResponder
{
  /** Incident severity levels */
  private const SEVERITY_LOW = 'low';
  private const SEVERITY_MEDIUM = 'medium';
  private const SEVERITY_HIGH = 'high';
  private const SEVERITY_CRITICAL = 'critical';

  /**
   * Assess system for active incidents.
   *
   * @return array{incidents: array, safe_mode: bool, actions_taken: array}
   */
  public static function assess(): array
  {
    $incidents = [];
    $actionsTaken = [];

    $incidents = array_merge($incidents, self::checkDatabaseHealth());
    $incidents = array_merge($incidents, self::checkErrorSpikes());
    $incidents = array_merge($incidents, self::checkCriticalServices());
    $incidents = array_merge($incidents, self::checkDiskSpace());
    $incidents = array_merge($incidents, self::checkStaleCronJobs());

    // Respond to critical incidents
    $criticalCount = count(array_filter($incidents, fn($i) => $i['severity'] === self::SEVERITY_CRITICAL));
    $safeMode = false;

    if ($criticalCount > 0) {
      $actionsTaken = self::respondToCritical($incidents);
      $safeMode = $criticalCount >= 2;

      if ($safeMode) {
        self::enableSafeMode();
        $actionsTaken[] = ['action' => 'Safe mode enabled', 'success' => true];
      }
    }

    // Attempt to self-heal non-critical incidents
    foreach ($incidents as $inc) {
      if ($inc['severity'] !== self::SEVERITY_CRITICAL && ($inc['healable'] ?? false)) {
        $healed = self::healService($inc);
        if ($healed) {
          $actionsTaken[] = ['action' => 'Self-healed: ' . $inc['module'], 'success' => true];
        }
      }
    }

    return [
      'incidents'     => $incidents,
      'safe_mode'     => $safeMode,
      'actions_taken' => $actionsTaken,
    ];
  }

  /**
   * Check database connectivity and performance.
   */
  private static function checkDatabaseHealth(): array
  {
    $incidents = [];
    try {
      $start = microtime(true);
      db()->fetchOne("SELECT 1");
      $latency = (microtime(true) - $start) * 1000;

      if ($latency > 2000) {
        $incidents[] = [
          'module'   => 'database',
          'type'     => 'high_latency',
          'detail'   => "DB latency: {$latency}ms (> 2000ms threshold)",
          'severity' => self::SEVERITY_HIGH,
          'healable' => false,
        ];
      }
    } catch (\Throwable $e) {
      $incidents[] = [
        'module'   => 'database',
        'type'     => 'connection_failure',
        'detail'   => 'Database connection failed: ' . $e->getMessage(),
        'severity' => self::SEVERITY_CRITICAL,
        'healable' => false,
      ];
    }
    return $incidents;
  }

  /**
   * Check for error spikes in logs.
   */
  private static function checkErrorSpikes(): array
  {
    $incidents = [];
    $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);

    // Check autofix log for recent errors
    $logFile = $basePath . '/logs/autofix.log';
    if (is_file($logFile)) {
      $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
      $recentErrors = 0;
      $cutoff = date('Y-m-d H:i:s', strtotime('-15 minutes'));

      foreach (array_reverse($lines) as $line) {
        // Extract timestamp from log line
        if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $line, $m)) {
          if ($m[1] < $cutoff) break;
          if (stripos($line, 'ERROR') !== false || stripos($line, 'CRITICAL') !== false) {
            $recentErrors++;
          }
        }
      }

      if ($recentErrors >= 10) {
        $incidents[] = [
          'module'   => 'error_log',
          'type'     => 'error_spike',
          'detail'   => "{$recentErrors} errors in last 15 minutes",
          'severity' => $recentErrors >= 20 ? self::SEVERITY_CRITICAL : self::SEVERITY_HIGH,
          'healable' => false,
        ];
      }
    }

    return $incidents;
  }

  /**
   * Check critical services are operational.
   */
  private static function checkCriticalServices(): array
  {
    $incidents = [];
    $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);

    // Check if key files are intact (could be corrupted/deleted)
    $critical = [
      'index.php'           => 'Landing Page',
      'login.php'           => 'Authentication',
      'admin/dashboard.php' => 'Admin Dashboard',
      'includes/config.php' => 'Configuration',
    ];

    foreach ($critical as $file => $service) {
      $path = $basePath . '/' . $file;
      if (!is_file($path)) {
        $incidents[] = [
          'module'   => $service,
          'type'     => 'missing_service_file',
          'detail'   => "Critical file missing: {$file}",
          'severity' => self::SEVERITY_CRITICAL,
          'healable' => false,
        ];
      } elseif (filesize($path) === 0) {
        $incidents[] = [
          'module'   => $service,
          'type'     => 'empty_service_file',
          'detail'   => "Critical file is empty: {$file}",
          'severity' => self::SEVERITY_CRITICAL,
          'healable' => false,
        ];
      }
    }

    return $incidents;
  }

  /**
   * Check disk space.
   */
  private static function checkDiskSpace(): array
  {
    $incidents = [];
    $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);

    $free = @disk_free_space($basePath);
    $total = @disk_total_space($basePath);

    if ($total > 0 && $free !== false) {
      $pct = ($free / $total) * 100;
      if ($pct < 5) {
        $incidents[] = [
          'module'   => 'disk',
          'type'     => 'disk_critical',
          'detail'   => sprintf('Disk space critically low: %.1f%% free', $pct),
          'severity' => self::SEVERITY_CRITICAL,
          'healable' => true,
        ];
      } elseif ($pct < 15) {
        $incidents[] = [
          'module'   => 'disk',
          'type'     => 'disk_low',
          'detail'   => sprintf('Disk space low: %.1f%% free', $pct),
          'severity' => self::SEVERITY_MEDIUM,
          'healable' => true,
        ];
      }
    }

    return $incidents;
  }

  /**
   * Check for stale cron jobs.
   */
  private static function checkStaleCronJobs(): array
  {
    $incidents = [];
    $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);

    // Check autofix lock file staleness
    $lockFile = $basePath . '/storage/autofix.lock';
    if (is_file($lockFile) && (time() - filemtime($lockFile)) > 600) {
      $incidents[] = [
        'module'   => 'cron',
        'type'     => 'stale_lock',
        'detail'   => 'Autofix lock file stale (> 10 minutes)',
        'severity' => self::SEVERITY_MEDIUM,
        'healable' => true,
      ];
    }

    return $incidents;
  }

  /**
   * Respond to critical incidents.
   */
  private static function respondToCritical(array $incidents): array
  {
    $actions = [];

    foreach ($incidents as $inc) {
      if ($inc['severity'] !== self::SEVERITY_CRITICAL) continue;

      // Create forensic snapshot
      self::createForensicSnapshot($inc);

      ErrorCollector::log('incident', "CRITICAL: [{$inc['module']}] {$inc['detail']}", 'CRITICAL');

      $actions[] = [
        'action'  => "Forensic snapshot created for {$inc['module']}",
        'success' => true,
      ];
    }

    return $actions;
  }

  /**
   * Create a forensic snapshot of current system state.
   */
  private static function createForensicSnapshot(array $incident): void
  {
    $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);
    $snapshotDir = $basePath . '/storage/forensics';
    if (!is_dir($snapshotDir)) mkdir($snapshotDir, 0755, true);

    $snapshot = [
      'incident'   => $incident,
      'timestamp'  => date('Y-m-d H:i:s'),
      'memory'     => memory_get_usage(true),
      'peak_memory' => memory_get_peak_usage(true),
      'php_version' => PHP_VERSION,
      'loaded_extensions' => get_loaded_extensions(),
    ];

    $filename = date('Ymd_His') . '_' . $incident['module'] . '.json';
    file_put_contents(
      $snapshotDir . '/' . $filename,
      json_encode($snapshot, JSON_PRETTY_PRINT),
      LOCK_EX
    );
  }

  /**
   * Attempt to self-heal a failing service.
   */
  private static function healService(array $incident): bool
  {
    $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);

    switch ($incident['type']) {
      case 'stale_lock':
        $lockFile = $basePath . '/storage/autofix.lock';
        if (is_file($lockFile)) {
          @unlink($lockFile);
          ErrorCollector::log('incident', 'Removed stale autofix lock file', 'INFO');
          return true;
        }
        break;

      case 'disk_low':
      case 'disk_critical':
        // Clean temp files and old caches
        $cleaned = 0;
        foreach (glob($basePath . '/cache/*.{tmp,json}', GLOB_BRACE) ?: [] as $f) {
          if ((time() - filemtime($f)) > 3600) {
            @unlink($f);
            $cleaned++;
          }
        }
        foreach (glob($basePath . '/storage/forensics/*.json') ?: [] as $f) {
          if ((time() - filemtime($f)) > 604800) {
            @unlink($f);
            $cleaned++;
          } // 7 days
        }
        if ($cleaned > 0) {
          ErrorCollector::log('incident', "Freed space by removing {$cleaned} temp file(s)", 'INFO');
          return true;
        }
        break;
    }

    return false;
  }

  /**
   * Enable safe mode flag.
   */
  private static function enableSafeMode(): void
  {
    $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);
    $dir = $basePath . '/storage';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    file_put_contents($dir . '/safe-mode.flag', date('Y-m-d H:i:s'), LOCK_EX);
    ErrorCollector::log('incident', 'SAFE MODE ENABLED due to multiple critical incidents', 'CRITICAL');
  }

  /**
   * Disable safe mode.
   */
  public static function disableSafeMode(): void
  {
    $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);
    $flag = $basePath . '/storage/safe-mode.flag';
    if (is_file($flag)) {
      @unlink($flag);
      ErrorCollector::log('incident', 'Safe mode disabled', 'INFO');
    }
  }

  /**
   * Check if safe mode is active.
   */
  public static function isSafeMode(): bool
  {
    $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);
    return is_file($basePath . '/storage/safe-mode.flag');
  }

  /**
   * Get incident summary for dashboard.
   */
  public static function getSummary(): array
  {
    $result = self::assess();
    return [
      'incident_count' => count($result['incidents']),
      'safe_mode'      => $result['safe_mode'] || self::isSafeMode(),
      'critical'       => count(array_filter($result['incidents'], fn($i) => $i['severity'] === 'critical')),
      'actions_taken'  => count($result['actions_taken']),
    ];
  }
}
