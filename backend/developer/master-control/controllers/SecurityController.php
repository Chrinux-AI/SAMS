<?php

/**
 * MCC — Security Command Center Controller
 * Failed logins, blocked IPs, rate limits, emergency actions.
 */
class SecurityController
{
  public static function getStatus(): array
  {
    $failedLogins = 0;
    $blockedIPs = 0;
    $rateLimitHits = 0;
    $suspiciousActivity = 0;
    $recentEvents = [];

    // Failed logins (last 24h)
    try {
      $failedLogins = (int) db()->fetchOne(
        "SELECT COUNT(*) FROM audit_logs WHERE action = 'login_failed' AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
      );
    } catch (\Throwable $e) {
    }

    // Blocked IPs
    try {
      $blockedIPs = (int) db()->fetchOne(
        "SELECT COUNT(*) FROM ip_bans WHERE expires_at > NOW() OR expires_at IS NULL"
      );
    } catch (\Throwable $e) {
    }

    // Rate limit hits (last hour)
    try {
      $rateLimitHits = (int) db()->fetchOne(
        "SELECT COUNT(*) FROM rate_limits WHERE hits > 10 AND last_hit >= DATE_SUB(NOW(), INTERVAL 1 HOUR)"
      );
    } catch (\Throwable $e) {
    }

    // Security events (last 24h)
    try {
      $suspiciousActivity = (int) db()->fetchOne(
        "SELECT COUNT(*) FROM security_events WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) AND severity IN ('HIGH','CRITICAL')"
      );
    } catch (\Throwable $e) {
    }

    // Recent security events
    try {
      $recentEvents = db()->fetchAll(
        "SELECT * FROM security_events ORDER BY created_at DESC LIMIT 10"
      ) ?: [];
    } catch (\Throwable $e) {
    }

    return [
      'failed_logins_24h'   => $failedLogins,
      'blocked_ips'         => $blockedIPs,
      'rate_limit_hits'     => $rateLimitHits,
      'suspicious_activity' => $suspiciousActivity,
      'recent_events'       => $recentEvents,
      'security_score'      => self::calculateScore($failedLogins, $blockedIPs, $suspiciousActivity),
    ];
  }

  private static function calculateScore(int $failed, int $blocked, int $suspicious): int
  {
    $score = 100;
    $score -= min(20, $failed);
    $score -= min(15, $blocked * 3);
    $score -= min(30, $suspicious * 5);
    return max(0, $score);
  }

  public static function forceLogoutAll(): array
  {
    $sessionPath = session_save_path() ?: sys_get_temp_dir();
    $count = 0;
    $currentId = session_id();
    foreach (glob($sessionPath . '/sess_*') as $file) {
      if (basename($file) !== 'sess_' . $currentId) {
        @unlink($file);
        $count++;
      }
    }
    try {
      AuditLogger::log('force_logout_all', 'sessions', "Force cleared $count sessions", $_SESSION['user_id'] ?? null);
    } catch (\Throwable $e) {
    }
    return ['cleared' => $count];
  }

  public static function enableMaintenanceMode(): array
  {
    $file = BASE_PATH . '/storage/maintenance.flag';
    $dir = dirname($file);
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    file_put_contents($file, json_encode([
      'enabled'    => true,
      'enabled_by' => $_SESSION['user_id'] ?? 0,
      'enabled_at' => date('c'),
      'message'    => 'System is under maintenance. Please check back shortly.',
    ]), LOCK_EX);
    try {
      AuditLogger::log('maintenance_mode', 'system', 'Maintenance mode enabled', $_SESSION['user_id'] ?? null);
    } catch (\Throwable $e) {
    }
    return ['status' => 'enabled'];
  }

  public static function disableMaintenanceMode(): array
  {
    $file = BASE_PATH . '/storage/maintenance.flag';
    if (is_file($file)) @unlink($file);
    try {
      AuditLogger::log('maintenance_mode', 'system', 'Maintenance mode disabled', $_SESSION['user_id'] ?? null);
    } catch (\Throwable $e) {
    }
    return ['status' => 'disabled'];
  }

  public static function isMaintenanceMode(): bool
  {
    $file = BASE_PATH . '/storage/maintenance.flag';
    if (!is_file($file)) return false;
    $data = json_decode(file_get_contents($file), true);
    return !empty($data['enabled']);
  }
}
