<?php

/**
 * SystemHealthScore — Domain-Separated Health Calculation
 *
 * Replaces the flat scoring model in HealthReporter with 4 distinct domains:
 *   Performance + Security + Stability + Integrity = SYSTEM HEALTH
 *
 * Each domain scores 0-100, overall is a weighted average.
 *
 * Usage:
 *   $health = SystemHealthScore::calculate();
 *   // Returns: ['overall' => 87, 'performance' => 92, 'security' => 85, 'stability' => 90, 'integrity' => 80]
 */
class SystemHealthScore
{
  private static array $weights = [
    'performance' => 0.20,
    'security'    => 0.30,
    'stability'   => 0.30,
    'integrity'   => 0.20,
  ];

  /**
   * Calculate all domain scores and overall health.
   *
   * @return array{overall: int, performance: int, security: int, stability: int, integrity: int, details: array}
   */
  public static function calculate(): array
  {
    $scores = [
      'performance' => self::scorePerformance(),
      'security'    => self::scoreSecurity(),
      'stability'   => self::scoreStability(),
      'integrity'   => self::scoreIntegrity(),
    ];

    $weighted = 0;
    foreach ($scores as $domain => $data) {
      $weighted += $data['score'] * self::$weights[$domain];
    }

    return [
      'overall'     => (int)round($weighted),
      'performance' => $scores['performance']['score'],
      'security'    => $scores['security']['score'],
      'stability'   => $scores['stability']['score'],
      'integrity'   => $scores['integrity']['score'],
      'details'     => $scores,
      'timestamp'   => date('Y-m-d H:i:s'),
    ];
  }

  /**
   * Get the health grade label from a score.
   */
  public static function grade(int $score): string
  {
    if ($score >= 90) return 'Excellent';
    if ($score >= 75) return 'Good';
    if ($score >= 60) return 'Fair';
    if ($score >= 40) return 'Poor';
    return 'Critical';
  }

  /**
   * Get grade color for UI rendering.
   */
  public static function gradeColor(int $score): string
  {
    if ($score >= 90) return '#28a745';
    if ($score >= 75) return '#17a2b8';
    if ($score >= 60) return '#ffc107';
    if ($score >= 40) return '#fd7e14';
    return '#dc3545';
  }

  // ── Domain Scorers ──

  private static function scorePerformance(): array
  {
    $score = 100;
    $factors = [];

    // Memory usage (deduct if >80% of limit)
    $memUsed = memory_get_usage(true);
    $memLimit = self::parseBytes(ini_get('memory_limit') ?: '128M');
    if ($memLimit > 0) {
      $memPct = ($memUsed / $memLimit) * 100;
      if ($memPct > 80) {
        $deduct = min(30, (int)(($memPct - 80) * 1.5));
        $score -= $deduct;
        $factors[] = "High memory usage: " . round($memPct, 1) . "%";
      }
    }

    // OPcache status
    if (function_exists('opcache_get_status')) {
      $opcache = @opcache_get_status(false);
      if ($opcache && isset($opcache['opcache_statistics'])) {
        $hitRate = $opcache['opcache_statistics']['opcache_hit_rate'] ?? 0;
        if ($hitRate < 80) {
          $score -= 10;
          $factors[] = "Low OPcache hit rate: " . round($hitRate, 1) . "%";
        }
      } elseif ($opcache === false) {
        $score -= 5;
        $factors[] = "OPcache disabled";
      }
    }

    // Cache directory health
    $cacheDir = dirname(__DIR__, 2) . '/cache';
    if (is_dir($cacheDir)) {
      $cacheFiles = glob($cacheDir . '/*.json');
      if (count($cacheFiles) > 200) {
        $score -= 10;
        $factors[] = "Cache bloat: " . count($cacheFiles) . " files";
      }
    }

    // Disk space
    $root = dirname(__DIR__, 2);
    $freeSpace = @disk_free_space($root);
    $totalSpace = @disk_total_space($root);
    if ($freeSpace && $totalSpace && ($freeSpace / $totalSpace) < 0.1) {
      $score -= 15;
      $factors[] = "Low disk space: " . round(($freeSpace / $totalSpace) * 100, 1) . "% free";
    }

    return ['score' => max(0, $score), 'factors' => $factors];
  }

  private static function scoreSecurity(): array
  {
    $score = 100;
    $factors = [];

    try {
      if (function_exists('db')) {
        $pdo = db()->getConnection();

        // Failed logins in last 24h
        $tables = $pdo->query("SHOW TABLES LIKE 'audit_logs'")->fetchAll();
        if (!empty($tables)) {
          $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM audit_logs
                         WHERE action LIKE :action AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)"
          );
          $stmt->execute([':action' => '%failed%login%']);
          $failedLogins = (int)$stmt->fetchColumn();

          if ($failedLogins > 50) {
            $score -= 25;
            $factors[] = "High failed logins: {$failedLogins} in 24h";
          } elseif ($failedLogins > 20) {
            $score -= 10;
            $factors[] = "Elevated failed logins: {$failedLogins} in 24h";
          }

          // Suspicious actions
          $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM audit_logs
                         WHERE action LIKE :action AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)"
          );
          $stmt->execute([':action' => '%blocked%']);
          $blocked = (int)$stmt->fetchColumn();
          if ($blocked > 10) {
            $score -= 15;
            $factors[] = "Blocked requests: {$blocked} in 24h";
          }
        }
      }
    } catch (\Throwable $e) {
      $factors[] = "Could not query security logs";
      $score -= 5;
    }

    // Check security headers are enabled
    if (!ini_get('session.cookie_httponly')) {
      $score -= 5;
      $factors[] = "session.cookie_httponly disabled";
    }
    if (!ini_get('session.cookie_secure') && (($_SERVER['HTTPS'] ?? '') === 'on')) {
      $score -= 5;
      $factors[] = "session.cookie_secure disabled on HTTPS";
    }

    // Check for SecurityGateway
    if (!class_exists('SecurityGateway')) {
      $score -= 15;
      $factors[] = "SecurityGateway not loaded";
    }

    return ['score' => max(0, $score), 'factors' => $factors];
  }

  private static function scoreStability(): array
  {
    $score = 100;
    $factors = [];

    // Check OS phase errors
    if (class_exists('OSKernel')) {
      try {
        $osData = OSKernel::getDashboardData();
        $phaseErrors = $osData['phase_errors'] ?? 0;
        if ($phaseErrors > 5) {
          $score -= min(30, $phaseErrors * 3);
          $factors[] = "OS phase errors: {$phaseErrors}";
        }
      } catch (\Throwable $e) {
        $factors[] = "OSKernel unavailable";
        $score -= 5;
      }
    }

    // Check healing cycles
    if (class_exists('SelfHealingKernel')) {
      try {
        $healData = SelfHealingKernel::getDashboardData();
        $cycles = $healData['total_cycles'] ?? 0;
        $failures = $healData['failed_repairs'] ?? 0;
        if ($cycles > 0 && ($failures / $cycles) > 0.3) {
          $score -= 20;
          $factors[] = "High repair failure rate: " . round(($failures / $cycles) * 100) . "%";
        }
      } catch (\Throwable $e) {
        // Not critical
      }
    }

    // Critical failure count
    $criticals = FailureContainment::getRecentFailures(50);
    $recentCriticals = array_filter($criticals, function ($f) {
      return isset($f['timestamp']) && strtotime($f['timestamp']) > strtotime('-24 hours');
    });
    $criticalCount = count($recentCriticals);
    if ($criticalCount > 5) {
      $score -= min(30, $criticalCount * 5);
      $factors[] = "Critical failures in 24h: {$criticalCount}";
    } elseif ($criticalCount > 0) {
      $score -= $criticalCount * 3;
      $factors[] = "Critical failures in 24h: {$criticalCount}";
    }

    // PHP error log size
    $errorLog = ini_get('error_log');
    if ($errorLog && is_file($errorLog)) {
      $logSize = filesize($errorLog);
      if ($logSize > 50 * 1024 * 1024) { // 50MB
        $score -= 10;
        $factors[] = "Error log oversized: " . round($logSize / 1024 / 1024) . "MB";
      }
    }

    return ['score' => max(0, $score), 'factors' => $factors];
  }

  private static function scoreIntegrity(): array
  {
    $score = 100;
    $factors = [];

    try {
      if (function_exists('db')) {
        $pdo = db()->getConnection();

        // Check for tables without primary keys
        $dbName = defined('DB_NAME') ? DB_NAME : 'attendance_system';
        $stmt = $pdo->prepare(
          "SELECT t.TABLE_NAME
                     FROM information_schema.TABLES t
                     LEFT JOIN information_schema.KEY_COLUMN_USAGE k
                       ON t.TABLE_SCHEMA = k.TABLE_SCHEMA
                       AND t.TABLE_NAME = k.TABLE_NAME
                       AND k.CONSTRAINT_NAME = 'PRIMARY'
                     WHERE t.TABLE_SCHEMA = :db AND t.TABLE_TYPE = 'BASE TABLE'
                       AND k.COLUMN_NAME IS NULL"
        );
        $stmt->execute([':db' => $dbName]);
        $noPk = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        if (count($noPk) > 0) {
          $score -= min(20, count($noPk) * 5);
          $factors[] = "Tables without primary key: " . implode(', ', array_slice($noPk, 0, 5));
        }

        // Check for crashed/corrupt tables
        $stmt = $pdo->prepare(
          "SELECT TABLE_NAME FROM information_schema.TABLES
                     WHERE TABLE_SCHEMA = :db AND TABLE_COMMENT LIKE '%crash%'"
        );
        $stmt->execute([':db' => $dbName]);
        $crashed = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        if (!empty($crashed)) {
          $score -= count($crashed) * 15;
          $factors[] = "Crashed tables: " . implode(', ', $crashed);
        }
      }
    } catch (\Throwable $e) {
      $factors[] = "Database integrity check failed";
      $score -= 10;
    }

    // Check critical files exist
    $requiredFiles = [
      'includes/config.php',
      'includes/database.php',
      'app/bootstrap.php',
      'config/routes.php',
    ];
    $root = dirname(__DIR__, 2);
    foreach ($requiredFiles as $file) {
      if (!is_file($root . '/' . $file)) {
        $score -= 15;
        $factors[] = "Missing critical file: {$file}";
      }
    }

    // Storage directory writable
    $storageDir = $root . '/storage';
    if (!is_dir($storageDir) || !is_writable($storageDir)) {
      $score -= 10;
      $factors[] = "Storage directory not writable";
    }

    return ['score' => max(0, $score), 'factors' => $factors];
  }

  // ── Utility ──

  private static function parseBytes(string $val): int
  {
    $val = trim($val);
    $num = (int)$val;
    $suffix = strtolower(substr($val, -1));
    switch ($suffix) {
      case 'g':
        return $num * 1024 * 1024 * 1024;
      case 'm':
        return $num * 1024 * 1024;
      case 'k':
        return $num * 1024;
      default:
        return $num;
    }
  }
}
