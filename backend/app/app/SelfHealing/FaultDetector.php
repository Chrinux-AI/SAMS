<?php

/**
 * FaultDetector — System-wide fault detection engine.
 *
 * Detects: 500 errors, blank pages, broken links, DB mismatches,
 * failed updates, missing includes, session failures.
 */
class FaultDetector
{
  private static array $faults = [];

  /**
   * Run all detection scans and return discovered faults.
   */
  public static function scan(): array
  {
    self::$faults = [];

    self::checkCriticalFiles();
    self::checkDatabaseConnectivity();
    self::checkSessionIntegrity();
    self::checkDeveloperRoutes();
    self::checkCronHealth();
    self::checkStorageWritable();
    self::checkSchemaIntegrity();

    return self::$faults;
  }

  /**
   * Check that critical include files exist and are non-empty.
   */
  private static function checkCriticalFiles(): void
  {
    $criticalFiles = [
      'includes/config.php',
      'includes/database.php',
      'includes/functions.php',
      'app/bootstrap.php',
      'includes/layouts/header.php',
      'includes/layouts/footer.php',
      'resources/ui-core/layouts/master-dashboard.php',
    ];

    foreach ($criticalFiles as $rel) {
      $path = BASE_PATH . '/' . $rel;
      if (!file_exists($path)) {
        self::flag('missing_critical_file', "Critical file missing: $rel", ['file' => $rel]);
      } elseif (filesize($path) < 10) {
        self::flag('empty_critical_file', "Critical file empty: $rel", ['file' => $rel]);
      }
    }
  }

  /**
   * Check database connectivity.
   */
  private static function checkDatabaseConnectivity(): void
  {
    try {
      $pdo = db()->getConnection();
      $pdo->query("SELECT 1");
    } catch (\Throwable $e) {
      self::flag('database_failure', 'Database connection failed: ' . $e->getMessage());
    }
  }

  /**
   * Check session configuration.
   */
  private static function checkSessionIntegrity(): void
  {
    if (php_sapi_name() === 'cli') return;

    $savePath = session_save_path();
    if ($savePath && !is_writable($savePath)) {
      self::flag('session_failure', 'Session save path not writable: ' . $savePath);
    }
  }

  /**
   * Validate developer portal routes.
   */
  private static function checkDeveloperRoutes(): void
  {
    $devPages = [
      'developer/index.php',
      'developer/settings.php',
      'developer/system-health.php',
      'developer/logs.php',
      'developer/modules.php',
      'developer/ecosystem-center.php',
    ];

    foreach ($devPages as $page) {
      $path = BASE_PATH . '/' . $page;
      if (!file_exists($path)) {
        self::flag('broken_route', "Developer page missing: $page", ['file' => $page]);
      } else {
        $content = file_get_contents($path);
        $hasLayout = strpos($content, 'master-dashboard.php') !== false
          || strpos($content, '<!DOCTYPE html>') !== false;
        if (!$hasLayout) {
          self::flag('blank_page_risk', "Developer page has no layout: $page", ['file' => $page]);
        }
      }
    }

    // Check developer tabs execute logic
    $tabs = ['general', 'security', 'ai', 'themes', 'integrations'];
    foreach ($tabs as $tab) {
      $tabFile = BASE_PATH . '/developer/tabs/' . $tab . '.php';
      if (!file_exists($tabFile)) {
        self::flag('missing_tab', "Developer tab missing: $tab", ['tab' => $tab]);
      }
    }
  }

  /**
   * Verify cron jobs are healthy.
   */
  private static function checkCronHealth(): void
  {
    $cronFiles = [
      'cron/ecosystem.php'   => 'storage/ecosystem-summary.json',
      'cron/cognitive.php'   => 'storage/cognitive-summary.json',
    ];

    foreach ($cronFiles as $cron => $output) {
      if (!file_exists(BASE_PATH . '/' . $cron)) {
        self::flag('missing_cron', "Cron file missing: $cron", ['file' => $cron]);
        continue;
      }
      $outPath = BASE_PATH . '/' . $output;
      if (file_exists($outPath)) {
        $age = time() - filemtime($outPath);
        if ($age > 86400) { // > 24 hours stale
          self::flag('stale_cron', "Cron output stale ({$age}s): $output", ['file' => $output, 'age' => $age]);
        }
      }
    }
  }

  /**
   * Verify storage directory is writable.
   */
  private static function checkStorageWritable(): void
  {
    $dirs = ['storage', 'cache', 'logs'];
    foreach ($dirs as $dir) {
      $path = BASE_PATH . '/' . $dir;
      if (is_dir($path) && !is_writable($path)) {
        self::flag('storage_not_writable', "Directory not writable: $dir", ['dir' => $dir]);
      }
    }
  }

  /**
   * Check critical database tables exist.
   */
  private static function checkSchemaIntegrity(): void
  {
    $requiredTables = ['users', 'attendance', 'classes'];
    try {
      $pdo = db()->getConnection();
      foreach ($requiredTables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($table));
        if ($stmt->rowCount() === 0) {
          self::flag('missing_table', "Required table missing: $table", ['table' => $table]);
        }
      }
    } catch (\Throwable $e) {
      // DB check already flagged in connectivity scan
    }
  }

  /**
   * Check a specific URL via internal file probe (no HTTP needed).
   */
  public static function probeFile(string $relPath): array
  {
    $path = BASE_PATH . '/' . $relPath;
    $result = ['path' => $relPath, 'exists' => false, 'has_layout' => false, 'size' => 0];

    if (!file_exists($path)) return $result;

    $result['exists'] = true;
    $result['size'] = filesize($path);
    $content = file_get_contents($path);
    $result['has_layout'] = strpos($content, 'master-dashboard.php') !== false
      || strpos($content, '<!DOCTYPE html>') !== false
      || strpos($content, '<html') !== false;

    return $result;
  }

  /**
   * Register a fault.
   */
  private static function flag(string $type, string $message, array $context = []): void
  {
    self::$faults[] = [
      'type'      => $type,
      'message'   => $message,
      'context'   => $context,
      'timestamp' => date('c'),
    ];
  }

  /**
   * Get last scan faults.
   */
  public static function getLastFaults(): array
  {
    return self::$faults;
  }

  public static function getSummary(): array
  {
    return [
      'faults_detected' => count(self::$faults),
      'faults'          => array_map(fn($f) => $f['type'] . ': ' . $f['message'], self::$faults),
    ];
  }
}
