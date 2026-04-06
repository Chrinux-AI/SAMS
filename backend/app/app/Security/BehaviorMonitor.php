<?php

/**
 * Behavioral Monitoring Engine — Tracks user activity patterns and assigns risk scores.
 *
 * Monitors: login frequency, navigation patterns, rapid data access, privilege escalation,
 *           unusual admin activity, and automated interaction patterns.
 *
 * Risk Score Model:
 *   0–30  Normal   → Allow
 *   31–60 Suspicious → Log + monitor
 *   61–80 High Risk  → Re-authentication
 *   81–100 Critical  → Lock session
 */
class BehaviorMonitor
{
  // Risk score thresholds
  public const THRESHOLD_NORMAL     = 30;
  public const THRESHOLD_SUSPICIOUS = 60;
  public const THRESHOLD_HIGH_RISK  = 80;
  public const THRESHOLD_CRITICAL   = 100;

  // Risk levels
  public const LEVEL_NORMAL     = 'normal';
  public const LEVEL_SUSPICIOUS = 'suspicious';
  public const LEVEL_HIGH_RISK  = 'high_risk';
  public const LEVEL_CRITICAL   = 'critical';

  // Common admin navigation patterns (baseline for anomaly detection)
  private static array $normalAdminPatterns = [
    'dashboard',
    'students',
    'teachers',
    'classes',
    'attendance',
    'settings',
    'notices',
    'announcements',
    'reports',
    'analytics',
  ];

  // High-risk admin actions (carry extra weight)
  private static array $highRiskActions = [
    'export',
    'delete',
    'role_change',
    'bulk_delete',
    'backup',
    'user_create',
    'password_reset',
    'settings_change',
    'api_key',
  ];

  /**
   * Record a user action and compute the current risk score.
   *
   * @return array{risk_score: int, level: string, factors: array}
   */
  public static function recordAction(int $userId, string $action, array $meta = []): array
  {
    $role = $_SESSION['role'] ?? $_SESSION['user_role'] ?? 'unknown';

    // Store the activity
    self::storeActivity($userId, $action, $role, $meta);

    // Compute risk score based on recent behavior
    $factors = self::analyzeRecentBehavior($userId, $role);
    $riskScore = self::computeRiskScore($factors);
    $level = self::classifyRisk($riskScore);

    // Update session risk score
    $_SESSION['_risk_score'] = $riskScore;
    $_SESSION['_risk_level'] = $level;

    // Log elevated risks
    if ($riskScore > self::THRESHOLD_NORMAL) {
      self::logRiskEvent($userId, $riskScore, $level, $factors);
    }

    return [
      'risk_score' => $riskScore,
      'level'      => $level,
      'factors'    => $factors,
    ];
  }

  /**
   * Get the current risk score for a user's session.
   */
  public static function getCurrentRisk(int $userId): array
  {
    return [
      'risk_score' => $_SESSION['_risk_score'] ?? 0,
      'level'      => $_SESSION['_risk_level'] ?? self::LEVEL_NORMAL,
    ];
  }

  /**
   * Analyze recent behavior patterns and return risk factors.
   */
  public static function analyzeRecentBehavior(int $userId, string $role): array
  {
    $factors = [];
    $window = date('Y-m-d H:i:s', strtotime('-15 minutes'));

    try {
      // 1. Rapid action frequency (actions in last 15 min)
      $actionCount = db()->count(
        'behavior_log',
        'user_id = :uid AND created_at > :window',
        ['uid' => $userId, 'window' => $window]
      );
      if ($actionCount > 100) {
        $factors['rapid_actions'] = ['score' => 25, 'detail' => "{$actionCount} actions in 15 min"];
      } elseif ($actionCount > 50) {
        $factors['rapid_actions'] = ['score' => 15, 'detail' => "{$actionCount} actions in 15 min"];
      }

      // 2. High-risk action clustering
      $riskyCount = (int) (db()->fetchOne(
        "SELECT COUNT(*) as cnt FROM behavior_log
                 WHERE user_id = :uid AND created_at > :window
                 AND action IN ('export','delete','role_change','bulk_delete','backup','settings_change')",
        ['uid' => $userId, 'window' => $window]
      )['cnt'] ?? 0);
      if ($riskyCount >= 5) {
        $factors['risk_action_cluster'] = ['score' => 30, 'detail' => "{$riskyCount} high-risk actions clustered"];
      } elseif ($riskyCount >= 3) {
        $factors['risk_action_cluster'] = ['score' => 15, 'detail' => "{$riskyCount} high-risk actions clustered"];
      }

      // 3. Page navigation speed (too many distinct pages = automation)
      $distinctPages = (int) (db()->fetchOne(
        "SELECT COUNT(DISTINCT action) as cnt FROM behavior_log
                 WHERE user_id = :uid AND created_at > :window",
        ['uid' => $userId, 'window' => $window]
      )['cnt'] ?? 0);
      if ($distinctPages > 30) {
        $factors['navigation_speed'] = ['score' => 20, 'detail' => "{$distinctPages} distinct pages in 15 min"];
      }

      // 4. Off-hours activity (configurable)
      $hour = (int) date('H');
      if ($hour >= 0 && $hour < 5) {
        $factors['off_hours'] = ['score' => 10, 'detail' => "Activity at {$hour}:00"];
      }

      // 5. Failed login attempts in session context
      $failedLogins = (int) (db()->fetchOne(
        "SELECT COUNT(*) as cnt FROM audit_logs
                 WHERE ip_address = :ip AND action = 'login_failed' AND created_at > :window",
        ['ip' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', 'window' => $window]
      )['cnt'] ?? 0);
      if ($failedLogins >= 5) {
        $factors['login_brute_force'] = ['score' => 35, 'detail' => "{$failedLogins} failed logins from this IP"];
      } elseif ($failedLogins >= 3) {
        $factors['login_brute_force'] = ['score' => 15, 'detail' => "{$failedLogins} failed logins from this IP"];
      }

      // 6. IP change detection mid-session
      if (isset($_SESSION['_login_ip']) && $_SESSION['_login_ip'] !== ($_SERVER['REMOTE_ADDR'] ?? '')) {
        $factors['ip_change'] = ['score' => 25, 'detail' => 'IP address changed mid-session'];
      }

      // 7. User agent change mid-session
      $currentUA = $_SERVER['HTTP_USER_AGENT'] ?? '';
      if (isset($_SESSION['_login_ua']) && $_SESSION['_login_ua'] !== $currentUA && $currentUA !== '') {
        $factors['ua_change'] = ['score' => 20, 'detail' => 'User agent changed mid-session'];
      }

      // 8. Privilege escalation pattern (non-admin accessing admin routes)
      if ($role !== 'admin') {
        $adminAccessAttempts = (int) (db()->fetchOne(
          "SELECT COUNT(*) as cnt FROM behavior_log
                     WHERE user_id = :uid AND created_at > :window
                     AND metadata LIKE '%admin%'",
          ['uid' => $userId, 'window' => $window]
        )['cnt'] ?? 0);
        if ($adminAccessAttempts >= 3) {
          $factors['privilege_escalation'] = ['score' => 30, 'detail' => "{$adminAccessAttempts} admin route attempts"];
        }
      }
    } catch (\Throwable $e) {
      error_log("BehaviorMonitor analysis error: " . $e->getMessage());
    }

    return $factors;
  }

  /**
   * Compute aggregate risk score from individual factors.
   */
  public static function computeRiskScore(array $factors): int
  {
    $total = 0;
    foreach ($factors as $factor) {
      $total += $factor['score'] ?? 0;
    }
    return min($total, 100);
  }

  /**
   * Classify risk level from numeric score.
   */
  public static function classifyRisk(int $score): string
  {
    if ($score <= self::THRESHOLD_NORMAL) {
      return self::LEVEL_NORMAL;
    }
    if ($score <= self::THRESHOLD_SUSPICIOUS) {
      return self::LEVEL_SUSPICIOUS;
    }
    if ($score <= self::THRESHOLD_HIGH_RISK) {
      return self::LEVEL_HIGH_RISK;
    }
    return self::LEVEL_CRITICAL;
  }

  /**
   * Store a user activity record.
   */
  private static function storeActivity(int $userId, string $action, string $role, array $meta): void
  {
    try {
      db()->insert('behavior_log', [
        'user_id'    => $userId,
        'action'     => mb_substr($action, 0, 100),
        'role'       => $role,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
        'user_agent' => mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
        'metadata'   => json_encode($meta, JSON_UNESCAPED_UNICODE),
        'created_at' => date('Y-m-d H:i:s'),
      ]);
    } catch (\Throwable $e) {
      error_log("BehaviorMonitor store failed: " . $e->getMessage());
    }
  }

  /**
   * Log an elevated risk event for review.
   */
  private static function logRiskEvent(int $userId, int $score, string $level, array $factors): void
  {
    try {
      db()->insert('security_events', [
        'event_type'  => 'risk_elevation',
        'severity'    => $level,
        'user_id'     => $userId,
        'ip_address'  => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
        'details'     => json_encode([
          'risk_score' => $score,
          'level'      => $level,
          'factors'    => $factors,
        ], JSON_UNESCAPED_UNICODE),
        'resolved'    => 0,
        'created_at'  => date('Y-m-d H:i:s'),
      ]);
    } catch (\Throwable $e) {
      error_log("BehaviorMonitor risk log failed: " . $e->getMessage());
    }
  }

  /**
   * Get recent risk events for the admin security dashboard.
   */
  public static function getRecentRiskEvents(int $limit = 50): array
  {
    try {
      return db()->fetchAll(
        "SELECT se.*, u.full_name, u.email
                 FROM security_events se
                 LEFT JOIN users u ON u.id = se.user_id
                 WHERE se.event_type = 'risk_elevation'
                 ORDER BY se.created_at DESC
                 LIMIT " . min($limit, 200)
      );
    } catch (\Throwable $e) {
      return [];
    }
  }

  /**
   * Get the behavior profile for a specific user.
   */
  public static function getUserProfile(int $userId, int $days = 7): array
  {
    try {
      $since = date('Y-m-d H:i:s', strtotime("-{$days} days"));

      $totalActions = db()->count('behavior_log', 'user_id = :uid AND created_at > :since', ['uid' => $userId, 'since' => $since]);

      $topActions = db()->fetchAll(
        "SELECT action, COUNT(*) as cnt FROM behavior_log
                 WHERE user_id = :uid AND created_at > :since
                 GROUP BY action ORDER BY cnt DESC LIMIT 10",
        ['uid' => $userId, 'since' => $since]
      );

      $riskEvents = db()->count('security_events', "user_id = :uid AND event_type = 'risk_elevation' AND created_at > :since", ['uid' => $userId, 'since' => $since]);

      $avgDaily = $days > 0 ? round($totalActions / $days, 1) : $totalActions;

      return [
        'user_id'       => $userId,
        'total_actions' => $totalActions,
        'avg_daily'     => $avgDaily,
        'top_actions'   => $topActions,
        'risk_events'   => $riskEvents,
        'period_days'   => $days,
      ];
    } catch (\Throwable $e) {
      return ['user_id' => $userId, 'error' => $e->getMessage()];
    }
  }

  /**
   * Clean up old behavior logs (call from cron).
   */
  public static function cleanup(int $days = 30): int
  {
    try {
      $cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));
      $stmt = db()->query("DELETE FROM behavior_log WHERE created_at < :cutoff", ['cutoff' => $cutoff]);
      return $stmt ? $stmt->rowCount() : 0;
    } catch (\Throwable $e) {
      return 0;
    }
  }
}
