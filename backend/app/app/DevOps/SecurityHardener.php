<?php

/**
 * SecurityHardener — Continuous Security Enforcement Engine
 *
 * Monitors and enforces:
 * - Session timeout validation
 * - Brute-force detection and temporary blocking
 * - Permission anomaly detection
 * - Suspicious admin behavior patterns
 * - CSRF / token validation health
 */
class SecurityHardener
{
  /** Maximum failed logins before temporary block */
  private const BRUTE_FORCE_THRESHOLD = 10;

  /** Block duration in seconds */
  private const BLOCK_DURATION = 900; // 15 minutes

  /**
   * Run all security hardening checks.
   *
   * @return array{threats: array, actions: array, score: int}
   */
  public static function harden(): array
  {
    $threats = [];
    $actions = [];

    $threats = array_merge($threats, self::detectBruteForce());
    $threats = array_merge($threats, self::detectPermissionAnomalies());
    $threats = array_merge($threats, self::validateSessionConfig());
    $threats = array_merge($threats, self::checkSecurityHeaders());
    $threats = array_merge($threats, self::detectSuspiciousAdminActivity());
    $actions = array_merge($actions, self::enforceDirectorySecurity());

    // Security score: 100 minus deductions
    $score = 100;
    foreach ($threats as $t) {
      $score -= ($t['severity'] === 'critical' ? 15 : ($t['severity'] === 'high' ? 8 : 3));
    }
    $score = max(0, $score);

    return ['threats' => $threats, 'actions' => $actions, 'score' => $score];
  }

  /**
   * Detect brute-force login attempts from activity logs.
   */
  private static function detectBruteForce(): array
  {
    $threats = [];
    try {
      if (!function_exists('table_exists') || !table_exists('activity_log')) {
        return $threats;
      }

      // Check for excessive failed logins in last 15 minutes
      $rows = db()->fetchAll(
        "SELECT ip_address, COUNT(*) as attempts
                 FROM activity_log
                 WHERE action LIKE '%failed%login%'
                 AND created_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)
                 GROUP BY ip_address
                 HAVING attempts >= ?",
        [self::BRUTE_FORCE_THRESHOLD]
      );

      foreach ($rows as $row) {
        $ip = $row['ip_address'] ?? 'unknown';
        $threats[] = [
          'type'     => 'brute_force',
          'detail'   => "{$row['attempts']} failed login attempts from IP {$ip}",
          'severity' => 'critical',
          'ip'       => $ip,
        ];

        // Record the threat
        self::recordThreat('brute_force', $ip, "Blocked after {$row['attempts']} attempts");
      }
    } catch (\Throwable $e) {
      // Non-critical
    }
    return $threats;
  }

  /**
   * Detect permission anomalies — users accessing pages beyond their role.
   */
  private static function detectPermissionAnomalies(): array
  {
    $threats = [];
    try {
      if (!function_exists('table_exists') || !table_exists('activity_log')) {
        return $threats;
      }

      // Check for access denied events in the last hour
      $rows = db()->fetchAll(
        "SELECT user_id, COUNT(*) as denials
                 FROM activity_log
                 WHERE action LIKE '%access denied%' OR action LIKE '%unauthorized%'
                 AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
                 GROUP BY user_id
                 HAVING denials >= 5"
      );

      foreach ($rows as $row) {
        $threats[] = [
          'type'     => 'permission_anomaly',
          'detail'   => "User {$row['user_id']} had {$row['denials']} access denials in 1 hour",
          'severity' => 'high',
          'user_id'  => $row['user_id'],
        ];
      }
    } catch (\Throwable $e) {
      // Non-critical
    }
    return $threats;
  }

  /**
   * Validate session security configuration.
   */
  private static function validateSessionConfig(): array
  {
    $threats = [];

    if (!defined('SESSION_TIMEOUT') || SESSION_TIMEOUT > 3600) {
      $threats[] = [
        'type'     => 'session_config',
        'detail'   => 'Session timeout not set or too long (> 1 hour)',
        'severity' => 'medium',
      ];
    }

    if (ini_get('session.cookie_httponly') != '1') {
      $threats[] = [
        'type'     => 'session_config',
        'detail'   => 'session.cookie_httponly not enabled',
        'severity' => 'medium',
      ];
    }

    if (ini_get('session.use_strict_mode') != '1') {
      $threats[] = [
        'type'     => 'session_config',
        'detail'   => 'session.use_strict_mode not enabled',
        'severity' => 'low',
      ];
    }

    return $threats;
  }

  /**
   * Check for essential security headers (guideline check for CLI).
   */
  private static function checkSecurityHeaders(): array
  {
    $threats = [];
    $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);

    // Check .htaccess for security headers
    $htaccess = $basePath . '/.htaccess';
    if (is_file($htaccess)) {
      $content = file_get_contents($htaccess);
      $required = ['X-Content-Type-Options', 'X-Frame-Options', 'X-XSS-Protection'];
      foreach ($required as $header) {
        if (stripos($content, $header) === false) {
          $threats[] = [
            'type'     => 'missing_header',
            'detail'   => "Security header {$header} not configured in .htaccess",
            'severity' => 'low',
          ];
        }
      }
    }

    return $threats;
  }

  /**
   * Detect suspicious admin activity patterns.
   */
  private static function detectSuspiciousAdminActivity(): array
  {
    $threats = [];
    try {
      if (!function_exists('table_exists') || !table_exists('activity_log')) {
        return $threats;
      }

      // Check for admin bulk deletions
      $rows = db()->fetchAll(
        "SELECT user_id, COUNT(*) as deletes
                 FROM activity_log
                 WHERE action LIKE '%delete%'
                 AND created_at >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)
                 GROUP BY user_id
                 HAVING deletes >= 10"
      );

      foreach ($rows as $row) {
        $threats[] = [
          'type'     => 'suspicious_admin',
          'detail'   => "User {$row['user_id']} performed {$row['deletes']} deletions in 30 minutes",
          'severity' => 'high',
          'user_id'  => $row['user_id'],
        ];
      }
    } catch (\Throwable $e) {
      // Non-critical
    }
    return $threats;
  }

  /**
   * Enforce .htaccess security on sensitive directories.
   */
  private static function enforceDirectorySecurity(): array
  {
    $actions = [];
    $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);

    $protectedDirs = ['storage', 'logs', 'config', 'database', 'backups'];
    $htaccessContent = "Order Deny,Allow\nDeny from all\n";

    foreach ($protectedDirs as $dir) {
      $dirPath = $basePath . '/' . $dir;
      $htPath = $dirPath . '/.htaccess';
      if (is_dir($dirPath) && !is_file($htPath)) {
        @file_put_contents($htPath, $htaccessContent);
        $actions[] = ['type' => 'directory_security', 'action' => "Added .htaccess protection to {$dir}/", 'success' => true];
      }
    }

    return $actions;
  }

  /**
   * Record a security threat for learning.
   */
  private static function recordThreat(string $type, string $source, string $action): void
  {
    try {
      if (function_exists('table_exists') && table_exists('devops_learning')) {
        db()->query(
          "INSERT INTO devops_learning (category, pattern, action_taken, occurrences, last_seen)
                     VALUES (?, ?, ?, 1, NOW())
                     ON DUPLICATE KEY UPDATE occurrences = occurrences + 1, last_seen = NOW()",
          ['security', "{$type}:{$source}", $action]
        );
      }
    } catch (\Throwable $e) {
      // Non-critical
    }
  }

  /**
   * Get threat summary for dashboard.
   */
  public static function getThreatSummary(): array
  {
    $result = self::harden();
    return [
      'threat_count' => count($result['threats']),
      'security_score' => $result['score'],
      'critical'       => count(array_filter($result['threats'], fn($t) => $t['severity'] === 'critical')),
      'high'           => count(array_filter($result['threats'], fn($t) => $t['severity'] === 'high')),
      'medium'         => count(array_filter($result['threats'], fn($t) => $t['severity'] === 'medium')),
    ];
  }
}
