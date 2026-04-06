<?php

/**
 * AIC — Student Engagement AI
 * Monitors student engagement through attendance patterns, participation, and activity metrics.
 */
class StudentEngagementAI
{
  /**
   * Analyze student engagement across the institution.
   */
  public static function analyze(): array
  {
    $metrics = self::getEngagementMetrics();
    $atRisk  = self::identifyAtRiskStudents();
    $distribution = self::getEngagementDistribution($metrics);
    $alerts  = self::generateAlerts($metrics, $atRisk);

    return [
      'engagement_score' => $metrics['overall_score'],
      'active_students'  => $metrics['active'],
      'inactive_students' => $metrics['inactive'],
      'at_risk_count'    => count($atRisk),
      'distribution'     => $distribution,
      'at_risk_students' => array_slice($atRisk, 0, 10),
      'alerts'           => $alerts,
    ];
  }

  /**
   * Get overall engagement metrics.
   */
  private static function getEngagementMetrics(): array
  {
    try {
      $pdo = db()->getConnection();

      $total = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student' AND status = 'active'")->fetchColumn();

      // Students who attended at least once in last 7 days
      $active = (int) $pdo->query("
                SELECT COUNT(DISTINCT user_id) FROM attendance
                WHERE date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                AND status IN ('present', 'late')
            ")->fetchColumn();

      $inactive = max(0, $total - $active);
      $score = $total > 0 ? (int) round($active / $total * 100) : 100;

      return [
        'total'         => $total,
        'active'        => $active,
        'inactive'      => $inactive,
        'overall_score' => $score,
      ];
    } catch (\Throwable $e) {
      return ['total' => 0, 'active' => 0, 'inactive' => 0, 'overall_score' => 0];
    }
  }

  /**
   * Identify students at risk of disengagement.
   * Criteria: absent >= 3 times in last 14 days.
   */
  private static function identifyAtRiskStudents(): array
  {
    try {
      $pdo = db()->getConnection();
      $stmt = $pdo->query("
                SELECT u.id, u.full_name, COUNT(*) as absence_count
                FROM attendance a
                JOIN users u ON u.id = a.user_id
                WHERE a.date >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
                AND a.status = 'absent'
                AND u.role = 'student' AND u.status = 'active'
                GROUP BY u.id, u.full_name
                HAVING absence_count >= 3
                ORDER BY absence_count DESC
                LIMIT 20
            ");
      return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    } catch (\Throwable $e) {
      return [];
    }
  }

  /**
   * Break down engagement into tiers.
   */
  private static function getEngagementDistribution(array $metrics): array
  {
    $total = max($metrics['total'], 1);
    $active = $metrics['active'];
    $inactive = $metrics['inactive'];

    // Categorize: Highly engaged (90%+ attendance), moderate (70-89%), at-risk (<70%)
    try {
      $pdo = db()->getConnection();
      $stmt = $pdo->query("
                SELECT
                    SUM(CASE WHEN rate >= 90 THEN 1 ELSE 0 END) as high,
                    SUM(CASE WHEN rate >= 70 AND rate < 90 THEN 1 ELSE 0 END) as moderate,
                    SUM(CASE WHEN rate < 70 THEN 1 ELSE 0 END) as low
                FROM (
                    SELECT user_id,
                           SUM(CASE WHEN status IN ('present','late') THEN 1 ELSE 0 END) / COUNT(*) * 100 as rate
                    FROM attendance
                    WHERE date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                    GROUP BY user_id
                ) rates
            ");
      $dist = $stmt->fetch(\PDO::FETCH_ASSOC);
      return [
        'high'     => (int) ($dist['high'] ?? 0),
        'moderate' => (int) ($dist['moderate'] ?? 0),
        'low'      => (int) ($dist['low'] ?? 0),
      ];
    } catch (\Throwable $e) {
      return ['high' => 0, 'moderate' => 0, 'low' => 0];
    }
  }

  /**
   * Generate engagement alerts.
   */
  private static function generateAlerts(array $metrics, array $atRisk): array
  {
    $alerts = [];

    if ($metrics['overall_score'] < 60) {
      $alerts[] = ['severity' => 'CRITICAL', 'message' => 'Student engagement critically low: ' . $metrics['overall_score'] . '%'];
    } elseif ($metrics['overall_score'] < 75) {
      $alerts[] = ['severity' => 'HIGH', 'message' => 'Student engagement below target: ' . $metrics['overall_score'] . '%'];
    }

    $riskCount = count($atRisk);
    if ($riskCount >= 10) {
      $alerts[] = ['severity' => 'HIGH', 'message' => "$riskCount students at high disengagement risk"];
    } elseif ($riskCount > 0) {
      $alerts[] = ['severity' => 'MEDIUM', 'message' => "$riskCount student(s) showing disengagement patterns"];
    }

    return $alerts;
  }
}
