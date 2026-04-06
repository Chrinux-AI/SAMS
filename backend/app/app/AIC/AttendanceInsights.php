<?php

/**
 * AIC — Attendance Insights
 * Analyzes attendance data for patterns, anomalies, and institutional health.
 */
class AttendanceInsights
{
  /**
   * Run attendance analysis.
   */
  public static function analyze(): array
  {
    $stats = self::getAttendanceStats();
    $trends = self::detectTrends($stats);
    $alerts = self::generateAlerts($stats, $trends);

    $healthScore = self::calculateHealthScore($stats);

    return [
      'health_score'    => $healthScore,
      'overall_rate'    => $stats['overall_rate'] ?? 0,
      'present_today'   => $stats['present_today'] ?? 0,
      'absent_today'    => $stats['absent_today'] ?? 0,
      'late_today'      => $stats['late_today'] ?? 0,
      'total_students'  => $stats['total_students'] ?? 0,
      'chronic_absent'  => $stats['chronic_absent_count'] ?? 0,
      'trends'          => $trends,
      'alerts'          => $alerts,
    ];
  }

  /**
   * Get current attendance statistics.
   */
  private static function getAttendanceStats(): array
  {
    try {
      $pdo = db()->getConnection();
      $today = date('Y-m-d');

      $totalStudents = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student' AND status = 'active'")->fetchColumn();

      $todayStats = $pdo->prepare("SELECT status, COUNT(*) as cnt FROM attendance WHERE date = ? GROUP BY status");
      $todayStats->execute([$today]);
      $statusCounts = [];
      while ($row = $todayStats->fetch(\PDO::FETCH_ASSOC)) {
        $statusCounts[$row['status']] = (int) $row['cnt'];
      }

      $present = $statusCounts['present'] ?? 0;
      $absent  = $statusCounts['absent'] ?? 0;
      $late    = $statusCounts['late'] ?? 0;
      $excused = $statusCounts['excused'] ?? 0;
      $marked  = $present + $absent + $late + $excused;

      $overallRate = $totalStudents > 0 ? round(($present + $late) / max($marked, 1) * 100, 1) : 0;

      // Chronic absenteeism: students absent > threshold% in last 30 days
      $threshold = defined('CHRONIC_ABSENTEEISM_THRESHOLD') ? CHRONIC_ABSENTEEISM_THRESHOLD : 10;
      $chronicQuery = $pdo->prepare("
                SELECT COUNT(DISTINCT a.user_id) FROM attendance a
                WHERE a.date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                AND a.status = 'absent'
                GROUP BY a.user_id
                HAVING COUNT(*) >= ?
            ");
      $chronicQuery->execute([$threshold]);
      $chronicCount = $chronicQuery->rowCount();

      return [
        'total_students'      => $totalStudents,
        'present_today'       => $present,
        'absent_today'        => $absent,
        'late_today'          => $late,
        'excused_today'       => $excused,
        'marked_today'        => $marked,
        'overall_rate'        => $overallRate,
        'chronic_absent_count' => $chronicCount,
      ];
    } catch (\Throwable $e) {
      return ['error' => $e->getMessage(), 'total_students' => 0];
    }
  }

  /**
   * Detect attendance trends over the last 7 days.
   */
  private static function detectTrends(array $stats): array
  {
    try {
      $pdo = db()->getConnection();
      $stmt = $pdo->query("
                SELECT date,
                       SUM(CASE WHEN status IN ('present','late') THEN 1 ELSE 0 END) as attended,
                       COUNT(*) as total
                FROM attendance
                WHERE date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                GROUP BY date
                ORDER BY date
            ");
      $daily = $stmt->fetchAll(\PDO::FETCH_ASSOC);

      $rates = array_map(function ($d) {
        return [
          'date' => $d['date'],
          'rate' => $d['total'] > 0 ? round($d['attended'] / $d['total'] * 100, 1) : 0,
        ];
      }, $daily);

      $direction = 'stable';
      if (count($rates) >= 3) {
        $last = end($rates)['rate'];
        $first = reset($rates)['rate'];
        if ($last - $first > 3) $direction = 'improving';
        elseif ($first - $last > 3) $direction = 'declining';
      }

      return ['daily_rates' => $rates, 'direction' => $direction];
    } catch (\Throwable $e) {
      return ['direction' => 'unknown', 'error' => $e->getMessage()];
    }
  }

  /**
   * Generate attendance alerts.
   */
  private static function generateAlerts(array $stats, array $trends): array
  {
    $alerts = [];

    $rate = $stats['overall_rate'] ?? 100;
    if ($rate < 70) {
      $alerts[] = ['severity' => 'CRITICAL', 'message' => "Attendance rate critically low: {$rate}%"];
    } elseif ($rate < 85) {
      $alerts[] = ['severity' => 'HIGH', 'message' => "Attendance rate below target: {$rate}%"];
    }

    $chronic = $stats['chronic_absent_count'] ?? 0;
    if ($chronic > 10) {
      $alerts[] = ['severity' => 'HIGH', 'message' => "$chronic students with chronic absenteeism"];
    } elseif ($chronic > 0) {
      $alerts[] = ['severity' => 'MEDIUM', 'message' => "$chronic student(s) at chronic absenteeism risk"];
    }

    if (($trends['direction'] ?? '') === 'declining') {
      $alerts[] = ['severity' => 'MEDIUM', 'message' => 'Attendance trend declining over the past week'];
    }

    return $alerts;
  }

  private static function calculateHealthScore(array $stats): int
  {
    $rate = $stats['overall_rate'] ?? 0;
    $chronic = min($stats['chronic_absent_count'] ?? 0, 20);
    // Base from rate, penalty for chronic absences
    return max(0, min(100, (int) round($rate - ($chronic * 1.5))));
  }
}
