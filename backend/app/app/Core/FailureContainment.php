<?php

/**
 * FailureContainment — Critical Failure Isolation + Admin Alerting
 *
 * Wraps around ErrorHandler to provide:
 *  - User-layer isolation (users see friendly error, never stack traces)
 *  - Admin alerting on CRITICAL severity failures
 *  - Event dispatch for error tracking (AI layers can observe)
 *  - Failure log persisted for admin dashboard pickup
 *
 * Integrates with:
 *  - ErrorHandler (registers as secondary handler)
 *  - EventBus (dispatches containment events)
 *  - Notification table (inserts admin alert rows)
 */
class FailureContainment
{
  private static bool $initialized = false;

  /** @var string Path to critical failure log */
  private static string $criticalLogPath;

  /**
   * Initialize containment layer.
   * Call once from config.php or bootstrap.php after ErrorHandler.
   */
  public static function init(): void
  {
    if (self::$initialized) {
      return;
    }
    self::$initialized = true;

    $storagePath = defined('STORAGE_PATH')
      ? STORAGE_PATH
      : dirname(__DIR__, 2) . '/storage';
    self::$criticalLogPath = $storagePath . '/critical-failures.json';

    // Register a shutdown function that catches fatal errors
    register_shutdown_function([self::class, 'onShutdown']);
  }

  /**
   * Handle a caught exception with containment.
   *
   * @param \Throwable $e       The exception
   * @param string     $module  Which module caught it
   * @param string     $severity  'low', 'medium', 'high', 'critical'
   */
  public static function handle(\Throwable $e, string $module = 'unknown', string $severity = 'high'): void
  {
    // Log to ErrorCollector if available
    if (class_exists('ErrorCollector')) {
      ErrorCollector::log($module, $e->getMessage(), $severity);
    }

    // Dispatch event for AI layers
    self::dispatchEvent('system', 'failure_contained', [
      'module'   => $module,
      'severity' => $severity,
      'error'    => $e->getMessage(),
      'file'     => $e->getFile(),
      'line'     => $e->getLine(),
    ]);

    // On CRITICAL: alert admins and persist
    if ($severity === 'critical') {
      self::alertAdmins($module, $e);
      self::persistCriticalFailure($module, $e);
      self::dispatchEvent('system', 'critical_failure', [
        'module'  => $module,
        'error'   => $e->getMessage(),
        'file'    => $e->getFile(),
        'line'    => $e->getLine(),
        'trace'   => array_slice($e->getTrace(), 0, 5),
      ]);
    }
  }

  /**
   * Shutdown handler — catches fatal errors that bypass exception handlers.
   */
  public static function onShutdown(): void
  {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE], true)) {
      self::handle(
        new \ErrorException($error['message'], 0, $error['type'], $error['file'], $error['line']),
        'php_fatal',
        'critical'
      );
    }
  }

  /**
   * Insert an admin notification for critical failure.
   */
  private static function alertAdmins(string $module, \Throwable $e): void
  {
    try {
      if (!function_exists('db')) {
        return;
      }
      $pdo = db()->getConnection();
      if (!$pdo) {
        return;
      }

      // Check if notifications table exists
      $tables = $pdo->query("SHOW TABLES LIKE 'notifications'")->fetchAll();
      if (empty($tables)) {
        return;
      }

      // Get admin user IDs
      $stmt = $pdo->prepare("SELECT id FROM users WHERE role = 'admin' LIMIT 10");
      $stmt->execute();
      $admins = $stmt->fetchAll(\PDO::FETCH_COLUMN);

      $message = "CRITICAL FAILURE in [{$module}]: " . mb_substr($e->getMessage(), 0, 200);

      $insert = $pdo->prepare(
        "INSERT INTO notifications (user_id, type, title, message, created_at, is_read)
                 VALUES (:uid, 'critical_failure', 'System Critical Failure', :msg, NOW(), 0)"
      );

      foreach ($admins as $adminId) {
        $insert->execute([
          ':uid' => $adminId,
          ':msg' => $message,
        ]);
      }
    } catch (\Throwable $inner) {
      // Don't let notification failures cascade
      error_log('[FailureContainment] Alert dispatch failed: ' . $inner->getMessage());
    }
  }

  /**
   * Persist critical failure to JSON file for admin dashboard.
   */
  private static function persistCriticalFailure(string $module, \Throwable $e): void
  {
    try {
      $failures = [];
      if (is_file(self::$criticalLogPath)) {
        $raw = file_get_contents(self::$criticalLogPath);
        $failures = json_decode($raw, true) ?: [];
      }

      $failures[] = [
        'timestamp' => date('Y-m-d H:i:s'),
        'module'    => $module,
        'error'     => mb_substr($e->getMessage(), 0, 500),
        'file'      => $e->getFile(),
        'line'      => $e->getLine(),
      ];

      // Keep last 100 entries
      $failures = array_slice($failures, -100);

      $dir = dirname(self::$criticalLogPath);
      if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
      }
      file_put_contents(self::$criticalLogPath, json_encode($failures, JSON_PRETTY_PRINT), LOCK_EX);
    } catch (\Throwable $inner) {
      error_log('[FailureContainment] Persist failed: ' . $inner->getMessage());
    }
  }

  /**
   * Get recent critical failures (for admin dashboard / MCC).
   */
  public static function getRecentFailures(int $limit = 20): array
  {
    if (!isset(self::$criticalLogPath)) {
      $storagePath = defined('STORAGE_PATH')
        ? STORAGE_PATH
        : dirname(__DIR__, 2) . '/storage';
      self::$criticalLogPath = $storagePath . '/critical-failures.json';
    }

    if (!is_file(self::$criticalLogPath)) {
      return [];
    }
    $raw = file_get_contents(self::$criticalLogPath);
    $failures = json_decode($raw, true) ?: [];
    return array_slice(array_reverse($failures), 0, $limit);
  }

  private static function dispatchEvent(string $channel, string $event, array $payload): void
  {
    if (class_exists('EventBus')) {
      try {
        EventBus::dispatch($channel, $event, $payload);
      } catch (\Throwable $ex) {
        // Silent
      }
    }
  }
}
