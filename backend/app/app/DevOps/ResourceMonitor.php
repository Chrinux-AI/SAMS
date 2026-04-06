<?php

/**
 * ResourceMonitor — System Observability Engine
 *
 * Tracks real-time metrics: CPU, memory, DB latency, API response times,
 * failed requests, session counts, and slow PHP execution.
 * Stores metrics in the system_metrics table for trend analysis.
 */
class ResourceMonitor
{
  /**
   * Collect all system metrics in one pass.
   *
   * @return array<string, array{value: float, severity: string}>
   */
  public static function collectAll(): array
  {
    $metrics = [];

    $metrics['memory_usage'] = self::measureMemory();
    $metrics['memory_peak'] = self::measurePeakMemory();
    $metrics['db_latency'] = self::measureDbLatency();
    $metrics['db_connections'] = self::measureDbConnections();
    $metrics['db_size_mb'] = self::measureDbSize();
    $metrics['slow_queries'] = self::measureSlowQueries();
    $metrics['session_count'] = self::measureSessionCount();
    $metrics['disk_free_pct'] = self::measureDiskFree();
    $metrics['php_error_count'] = self::measurePhpErrors();
    $metrics['uptime_seconds'] = self::measureUptime();

    return $metrics;
  }

  /**
   * Collect and persist all metrics to the database.
   */
  public static function snapshot(): array
  {
    $metrics = self::collectAll();
    self::persistMetrics($metrics);
    return $metrics;
  }

  /**
   * Get metric history for a specific metric.
   *
   * @return array
   */
  public static function getHistory(string $metric, int $hours = 24): array
  {
    try {
      if (!function_exists('table_exists') || !table_exists('system_metrics')) {
        return [];
      }
      return db()->fetchAll(
        "SELECT value, severity, recorded_at FROM system_metrics
                 WHERE metric = ? AND recorded_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)
                 ORDER BY recorded_at ASC",
        [$metric, $hours]
      );
    } catch (\Throwable $e) {
      return [];
    }
  }

  /**
   * Get the latest value for each metric.
   */
  public static function getLatestAll(): array
  {
    try {
      if (!function_exists('table_exists') || !table_exists('system_metrics')) {
        return [];
      }
      return db()->fetchAll(
        "SELECT m.metric, m.value, m.severity, m.recorded_at
                 FROM system_metrics m
                 INNER JOIN (
                     SELECT metric, MAX(recorded_at) as max_time
                     FROM system_metrics GROUP BY metric
                 ) latest ON m.metric = latest.metric AND m.recorded_at = latest.max_time
                 ORDER BY m.metric"
      );
    } catch (\Throwable $e) {
      return [];
    }
  }

  /**
   * Prune old metrics beyond retention period.
   */
  public static function pruneOldMetrics(int $keepDays = 30): int
  {
    try {
      if (!function_exists('table_exists') || !table_exists('system_metrics')) {
        return 0;
      }
      $stmt = db()->query(
        "DELETE FROM system_metrics WHERE recorded_at < DATE_SUB(NOW(), INTERVAL ? DAY)",
        [$keepDays]
      );
      return $stmt->rowCount();
    } catch (\Throwable $e) {
      return 0;
    }
  }

  // ── Individual Metric Collectors ──────────────────────────────

  private static function measureMemory(): array
  {
    $bytes = memory_get_usage(true);
    $mb = round($bytes / 1048576, 1);
    return ['value' => $mb, 'severity' => $mb > 128 ? 'warning' : ($mb > 256 ? 'critical' : 'normal')];
  }

  private static function measurePeakMemory(): array
  {
    $bytes = memory_get_peak_usage(true);
    $mb = round($bytes / 1048576, 1);
    return ['value' => $mb, 'severity' => $mb > 256 ? 'warning' : 'normal'];
  }

  private static function measureDbLatency(): array
  {
    try {
      $start = microtime(true);
      db()->fetchOne("SELECT 1");
      $ms = round((microtime(true) - $start) * 1000, 1);
      $sev = 'normal';
      if ($ms > 1500) $sev = 'critical';
      elseif ($ms > 500) $sev = 'warning';
      return ['value' => $ms, 'severity' => $sev];
    } catch (\Throwable $e) {
      return ['value' => -1, 'severity' => 'critical'];
    }
  }

  private static function measureDbConnections(): array
  {
    try {
      $row = db()->fetchOne("SHOW GLOBAL STATUS LIKE 'Threads_connected'");
      $val = (int)($row['Value'] ?? 0);
      return ['value' => $val, 'severity' => $val > 50 ? 'warning' : 'normal'];
    } catch (\Throwable $e) {
      return ['value' => 0, 'severity' => 'warning'];
    }
  }

  private static function measureDbSize(): array
  {
    try {
      $row = db()->fetchOne(
        "SELECT SUM(data_length + index_length) / 1048576 AS size_mb
                 FROM information_schema.TABLES WHERE table_schema = ?",
        [DB_NAME]
      );
      $mb = round((float)($row['size_mb'] ?? 0), 1);
      return ['value' => $mb, 'severity' => $mb > 500 ? 'warning' : 'normal'];
    } catch (\Throwable $e) {
      return ['value' => 0, 'severity' => 'warning'];
    }
  }

  private static function measureSlowQueries(): array
  {
    try {
      $row = db()->fetchOne("SHOW GLOBAL STATUS LIKE 'Slow_queries'");
      $val = (int)($row['Value'] ?? 0);
      return ['value' => $val, 'severity' => $val > 10 ? 'warning' : 'normal'];
    } catch (\Throwable $e) {
      return ['value' => 0, 'severity' => 'normal'];
    }
  }

  private static function measureSessionCount(): array
  {
    $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);
    $sessionPath = session_save_path() ?: sys_get_temp_dir();
    $count = 0;
    if (is_dir($sessionPath) && is_readable($sessionPath)) {
      $files = glob($sessionPath . '/sess_*');
      $count = $files ? count($files) : 0;
    }
    return ['value' => $count, 'severity' => $count > 500 ? 'warning' : 'normal'];
  }

  private static function measureDiskFree(): array
  {
    $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);
    $free = @disk_free_space($basePath);
    $total = @disk_total_space($basePath);
    if ($total > 0 && $free !== false) {
      $pct = round(($free / $total) * 100, 1);
      return ['value' => $pct, 'severity' => $pct < 10 ? 'critical' : ($pct < 20 ? 'warning' : 'normal')];
    }
    return ['value' => -1, 'severity' => 'warning'];
  }

  private static function measurePhpErrors(): array
  {
    $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);
    $logFile = $basePath . '/logs/php-errors.log';
    if (!is_file($logFile)) return ['value' => 0, 'severity' => 'normal'];

    $size = filesize($logFile);
    $kb = round($size / 1024, 1);
    return ['value' => $kb, 'severity' => $kb > 500 ? 'warning' : 'normal'];
  }

  private static function measureUptime(): array
  {
    try {
      $row = db()->fetchOne("SHOW GLOBAL STATUS LIKE 'Uptime'");
      $val = (int)($row['Value'] ?? 0);
      return ['value' => $val, 'severity' => 'normal'];
    } catch (\Throwable $e) {
      return ['value' => 0, 'severity' => 'warning'];
    }
  }

  // ── Persistence ──────────────────────────────────────────────

  private static function persistMetrics(array $metrics): void
  {
    try {
      if (!function_exists('table_exists') || !table_exists('system_metrics')) {
        return;
      }
      foreach ($metrics as $name => $data) {
        db()->query(
          "INSERT INTO system_metrics (metric, value, module, severity, recorded_at)
                     VALUES (?, ?, 'system', ?, NOW())",
          [$name, $data['value'], $data['severity']]
        );
      }
    } catch (\Throwable $e) {
      ErrorCollector::log('devops', 'Failed to persist metrics: ' . $e->getMessage(), 'ERROR');
    }
  }
}
