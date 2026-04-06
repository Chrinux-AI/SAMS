<?php

/**
 * ErrorCollector — Centralized error/failure log collector.
 *
 * Collects PHP exceptions, failed queries, AJAX errors, API failures,
 * and permission violations into logs/autofix.log and the system_failures table.
 */
class ErrorCollector
{
  private static string $logFile = '';

  private static function logPath(): string
  {
    if (!self::$logFile) {
      self::$logFile = (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2)) . '/logs/autofix.log';
      $dir = dirname(self::$logFile);
      if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
      }
    }
    return self::$logFile;
  }

  /**
   * Log a detected issue.
   */
  public static function log(string $module, string $problem, string $severity = 'MEDIUM', array $context = []): void
  {
    $entry = sprintf(
      "[%s] [%s] [%s] %s | %s\n",
      date('Y-m-d H:i:s'),
      $severity,
      $module,
      $problem,
      $context ? json_encode($context, JSON_UNESCAPED_SLASHES) : ''
    );
    @file_put_contents(self::logPath(), $entry, FILE_APPEND | LOCK_EX);
  }

  /**
   * Record a failure to the system_failures table for learning.
   */
  public static function recordFailure(string $errorType, string $module, string $fixApplied, bool $success): void
  {
    try {
      if (!function_exists('table_exists') || !table_exists('system_failures')) {
        return;
      }
      db()->query(
        "INSERT INTO system_failures (error_type, module, fix_applied, success, created_at)
                 VALUES (?, ?, ?, ?, NOW())",
        [$errorType, $module, $fixApplied, $success ? 1 : 0]
      );
    } catch (\Throwable $e) {
      // Non-critical — don't break the loop
    }
  }

  /**
   * Get recent failures for dashboard display.
   */
  public static function getRecentFailures(int $limit = 50): array
  {
    try {
      if (!function_exists('table_exists') || !table_exists('system_failures')) {
        return [];
      }
      return db()->fetchAll(
        "SELECT * FROM system_failures ORDER BY created_at DESC LIMIT ?",
        [$limit]
      );
    } catch (\Throwable $e) {
      return [];
    }
  }

  /**
   * Get success rate for a module.
   */
  public static function getModuleSuccessRate(string $module): float
  {
    try {
      if (!function_exists('table_exists') || !table_exists('system_failures')) {
        return 0.0;
      }
      $total = db()->fetchOne(
        "SELECT COUNT(*) as c FROM system_failures WHERE module = ?",
        [$module]
      );
      $success = db()->fetchOne(
        "SELECT COUNT(*) as c FROM system_failures WHERE module = ? AND success = 1",
        [$module]
      );
      $t = (int)($total['c'] ?? 0);
      if ($t === 0) return 100.0;
      return round(((int)($success['c'] ?? 0) / $t) * 100, 1);
    } catch (\Throwable $e) {
      return 0.0;
    }
  }

  /**
   * Read the last N lines of the autofix log.
   */
  public static function getRecentLogs(int $lines = 50): array
  {
    $path = self::logPath();
    if (!is_file($path)) return [];
    $all = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    return array_slice(array_reverse($all), 0, $lines);
  }

  /**
   * Prune old log entries (keep last 5000 lines).
   */
  public static function pruneLog(int $keepLines = 5000): void
  {
    $path = self::logPath();
    if (!is_file($path)) return;
    $all = file($path, FILE_IGNORE_NEW_LINES);
    if (count($all) > $keepLines) {
      $trimmed = array_slice($all, -$keepLines);
      file_put_contents($path, implode("\n", $trimmed) . "\n", LOCK_EX);
    }
  }
}
