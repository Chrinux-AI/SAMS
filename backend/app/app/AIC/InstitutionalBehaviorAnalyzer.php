<?php

/**
 * AIC — Institutional Behavior Analyzer
 * Analyzes institutional-level behavioral patterns: login trends, usage patterns,
 * role activity distribution, peak hours, and anomalous behavior.
 *
 * Named InstitutionalBehaviorAnalyzer to avoid conflict with
 * app/Platform/BehaviorAnalyzer.php (individual user behavior).
 */
class InstitutionalBehaviorAnalyzer
{
  /**
   * Run institutional behavior analysis.
   */
  public static function analyze(): array
  {
    $loginTrends = self::analyzeLoginTrends();
    $roleActivity = self::analyzeRoleActivity();
    $peakHours = self::analyzePeakHours();

    return [
      'login_trends'   => $loginTrends,
      'role_activity'  => $roleActivity,
      'peak_hours'     => $peakHours,
    ];
  }

  /**
   * Analyze login trends over the past 7 days.
   */
  private static function analyzeLoginTrends(): array
  {
    try {
      $pdo = db()->getConnection();
      $stmt = $pdo->query("
                SELECT DATE(created_at) as login_date, COUNT(*) as login_count
                FROM audit_logs
                WHERE action = 'login' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                GROUP BY DATE(created_at)
                ORDER BY login_date
            ");
      $daily = $stmt->fetchAll(\PDO::FETCH_ASSOC);

      $counts = array_column($daily, 'login_count');
      $avg = count($counts) > 0 ? round(array_sum($counts) / count($counts)) : 0;

      return [
        'daily'     => $daily,
        'avg_daily' => $avg,
        'trend'     => count($counts) >= 3 ? (end($counts) > $avg ? 'increasing' : 'decreasing') : 'stable',
      ];
    } catch (\Throwable $e) {
      return ['daily' => [], 'avg_daily' => 0, 'trend' => 'unknown'];
    }
  }

  /**
   * Analyze activity distribution by role.
   */
  private static function analyzeRoleActivity(): array
  {
    try {
      $pdo = db()->getConnection();
      $stmt = $pdo->query("
                SELECT u.role, COUNT(DISTINCT u.id) as total_users,
                       COUNT(DISTINCT CASE WHEN a.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN a.user_id END) as active_users
                FROM users u
                LEFT JOIN audit_logs a ON a.user_id = u.id
                WHERE u.status = 'active'
                GROUP BY u.role
            ");
      $roles = $stmt->fetchAll(\PDO::FETCH_ASSOC);

      return array_map(function ($r) {
        $total = (int) $r['total_users'];
        $active = (int) $r['active_users'];
        return [
          'role'          => $r['role'],
          'total'         => $total,
          'active_7d'     => $active,
          'activity_rate' => $total > 0 ? round($active / $total * 100) : 0,
        ];
      }, $roles);
    } catch (\Throwable $e) {
      return [];
    }
  }

  /**
   * Determine peak usage hours.
   */
  private static function analyzePeakHours(): array
  {
    try {
      $pdo = db()->getConnection();
      $stmt = $pdo->query("
                SELECT HOUR(created_at) as hour, COUNT(*) as activity_count
                FROM audit_logs
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                GROUP BY HOUR(created_at)
                ORDER BY activity_count DESC
                LIMIT 5
            ");
      $hours = $stmt->fetchAll(\PDO::FETCH_ASSOC);

      return array_map(function ($h) {
        $hr = (int) $h['hour'];
        return [
          'hour'   => $hr,
          'label'  => sprintf('%02d:00-%02d:59', $hr, $hr),
          'count'  => (int) $h['activity_count'],
        ];
      }, $hours);
    } catch (\Throwable $e) {
      return [];
    }
  }
}
