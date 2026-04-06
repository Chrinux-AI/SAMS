<?php

/**
 * MCC — System Status Controller
 * Real-time platform health, sessions, uptime, latency.
 */
class SystemController
{
  public static function getStatus(): array
  {
    $start = microtime(true);

    // Active sessions (count session files)
    $sessionPath = session_save_path() ?: sys_get_temp_dir();
    $sessionCount = 0;
    if (is_dir($sessionPath)) {
      $files = glob($sessionPath . '/sess_*');
      $sessionCount = $files ? count($files) : 0;
    }

    // DB latency
    $dbLatency = 0;
    try {
      $t = microtime(true);
      db()->fetch("SELECT 1");
      $dbLatency = round((microtime(true) - $t) * 1000, 1);
    } catch (\Throwable $e) {
      $dbLatency = -1;
    }

    // Active users (last 15 min)
    $activeUsers = 0;
    try {
      $activeUsers = (int) db()->fetchOne(
        "SELECT COUNT(*) FROM users WHERE last_login >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)"
      );
    } catch (\Throwable $e) {
    }

    // Error rate (last hour)
    $errorsLastHour = 0;
    try {
      $logFile = BASE_PATH . '/logs/error.log';
      if (is_file($logFile)) {
        $oneHourAgo = time() - 3600;
        $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $recent = array_slice($lines ?: [], -200);
        foreach ($recent as $line) {
          if (preg_match('/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})/', $line, $m)) {
            if (strtotime($m[1]) >= $oneHourAgo) $errorsLastHour++;
          }
        }
      }
    } catch (\Throwable $e) {
    }

    // OS health + stability
    $osHealth = 0;
    $stabilityScore = 0;
    try {
      $osData = OSKernel::getDashboardData();
      $osHealth = $osData['os_health'] ?? 0;
    } catch (\Throwable $e) {
    }
    try {
      $healData = HealingKernel::getDashboardData();
      $stabilityScore = $healData['last_run']['stability_score'] ?? 0;
    } catch (\Throwable $e) {
    }

    // PHP memory
    $memUsage = round(memory_get_usage(true) / 1024 / 1024, 1);
    $memPeak = round(memory_get_peak_usage(true) / 1024 / 1024, 1);

    // System status determination
    $system = 'STABLE';
    if ($dbLatency < 0) $system = 'DB_ERROR';
    elseif ($errorsLastHour > 50) $system = 'DEGRADED';
    elseif ($errorsLastHour > 10) $system = 'WARNING';

    return [
      'system'           => $system,
      'active_users'     => $activeUsers,
      'active_sessions'  => $sessionCount,
      'db_latency_ms'    => $dbLatency,
      'errors_last_hour' => $errorsLastHour,
      'os_health'        => $osHealth,
      'stability_score'  => $stabilityScore,
      'memory_mb'        => $memUsage,
      'memory_peak_mb'   => $memPeak,
      'php_version'      => PHP_VERSION,
      'uptime'           => self::getUptime(),
      'server_time'      => date('Y-m-d H:i:s'),
      'response_ms'      => round((microtime(true) - $start) * 1000, 1),
    ];
  }

  private static function getUptime(): string
  {
    $osFile = BASE_PATH . '/storage/os-summary.json';
    if (is_file($osFile)) {
      $data = json_decode(file_get_contents($osFile), true);
      if (!empty($data['timestamp'])) {
        return $data['timestamp'];
      }
    }
    return 'unknown';
  }
}
