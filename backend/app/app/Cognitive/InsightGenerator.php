<?php

/**
 * InsightGenerator — Executive Insight Automation
 *
 * Automatically produces executive-grade insights from all cognitive subsystems:
 *   "Grade SS1 engagement declining."
 *   "Teacher workload imbalance detected."
 *   "Exam season stress increasing system usage."
 *
 * Insights are displayed in Admin Intelligence Center.
 */
class InsightGenerator
{
  /**
   * Generate all insights across cognitive subsystems.
   *
   * @return array ['insights' => [], 'categories' => [], 'generated_at' => string]
   */
  public static function generate(): array
  {
    $insights = [];

    // Academic insights
    $insights = array_merge($insights, self::academicInsights());

    // Operational insights
    $insights = array_merge($insights, self::operationalInsights());

    // Human interaction insights
    $insights = array_merge($insights, self::interactionInsights());

    // Policy effectiveness insights
    $insights = array_merge($insights, self::policyInsights());

    // Trend-based insights
    $insights = array_merge($insights, self::trendInsights());

    // Sort by severity
    usort($insights, function ($a, $b) {
      $order = ['critical' => 0, 'high' => 1, 'medium' => 2, 'low' => 3, 'info' => 4];
      return ($order[$a['severity'] ?? 'info'] ?? 5) - ($order[$b['severity'] ?? 'info'] ?? 5);
    });

    // Record to institutional memory
    foreach (array_slice($insights, 0, 5) as $in) {
      try {
        InstitutionalMemory::record(
          'insight',
          $in['category'] ?? 'general',
          $in['title'] ?? '',
          $in['detail'] ?? '',
          'generated',
          $in['confidence'] ?? 0.5
        );
      } catch (\Throwable $e) {
        // Non-critical
      }
    }

    // Categorize
    $categories = [];
    foreach ($insights as $in) {
      $cat = $in['category'] ?? 'general';
      if (!isset($categories[$cat])) $categories[$cat] = 0;
      $categories[$cat]++;
    }

    return [
      'insights'     => $insights,
      'categories'   => $categories,
      'total'        => count($insights),
      'generated_at' => date('Y-m-d H:i:s'),
    ];
  }

  /**
   * Generate academic insights.
   */
  private static function academicInsights(): array
  {
    $insights = [];

    try {
      // Overall attendance trend
      $current = db()->fetchOne(
        "SELECT COUNT(*) as total, SUM(CASE WHEN status='present' THEN 1 ELSE 0 END) as present
         FROM attendance WHERE date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)"
      );
      $prior = db()->fetchOne(
        "SELECT COUNT(*) as total, SUM(CASE WHEN status='present' THEN 1 ELSE 0 END) as present
         FROM attendance WHERE date BETWEEN DATE_SUB(CURDATE(), INTERVAL 14 DAY) AND DATE_SUB(CURDATE(), INTERVAL 7 DAY)"
      );

      $curTotal = (int) ($current['total'] ?? 0);
      $curPresent = (int) ($current['present'] ?? 0);
      $priTotal = (int) ($prior['total'] ?? 0);
      $priPresent = (int) ($prior['present'] ?? 0);

      $curRate = $curTotal > 0 ? round($curPresent / $curTotal * 100, 1) : 100;
      $priRate = $priTotal > 0 ? round($priPresent / $priTotal * 100, 1) : 100;

      if ($curRate < $priRate - 5) {
        $insights[] = [
          'category'   => 'academic',
          'title'      => 'Attendance Declining',
          'detail'     => "This week: {$curRate}% vs last week: {$priRate}% (down " . round($priRate - $curRate, 1) . "%)",
          'severity'   => $curRate < 70 ? 'critical' : 'high',
          'confidence' => 0.85,
          'icon'       => 'chart-line-down',
        ];
      } elseif ($curRate > $priRate + 5) {
        $insights[] = [
          'category'   => 'academic',
          'title'      => 'Attendance Improving',
          'detail'     => "This week: {$curRate}% vs last week: {$priRate}% (up " . round($curRate - $priRate, 1) . "%)",
          'severity'   => 'info',
          'confidence' => 0.85,
          'icon'       => 'chart-line',
        ];
      }

      // Class-level engagement
      $lowClasses = db()->fetchAll(
        "SELECT c.class_name, COUNT(*) as total,
                SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absences
         FROM classes c
         JOIN class_enrollments ce ON c.id = ce.class_id
         JOIN attendance a ON a.student_id = ce.student_id AND a.date >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
         GROUP BY c.id, c.class_name
         HAVING (absences / total) > 0.3 AND total > 5
         ORDER BY (absences / total) DESC
         LIMIT 5"
      ) ?: [];

      foreach ($lowClasses as $lc) {
        $absRate = round((int) $lc['absences'] / (int) $lc['total'] * 100, 1);
        $insights[] = [
          'category'   => 'academic',
          'title'      => "{$lc['class_name']} engagement declining",
          'detail'     => "{$absRate}% absence rate over 14 days",
          'severity'   => $absRate > 50 ? 'critical' : 'high',
          'confidence' => 0.75,
          'icon'       => 'users-slash',
        ];
      }
    } catch (\Throwable $e) {
      // Non-critical
    }

    return $insights;
  }

  /**
   * Generate operational insights.
   */
  private static function operationalInsights(): array
  {
    $insights = [];

    try {
      // System error rate
      $errors = db()->fetchOne(
        "SELECT COUNT(*) as cnt FROM system_failures WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
      );
      $errorCount = (int) ($errors['cnt'] ?? 0);

      if ($errorCount > 20) {
        $insights[] = [
          'category'   => 'operational',
          'title'      => 'Elevated system error rate',
          'detail'     => "{$errorCount} errors in 24 hours",
          'severity'   => $errorCount > 50 ? 'critical' : 'high',
          'confidence' => 0.9,
          'icon'       => 'exclamation-triangle',
        ];
      }
    } catch (\Throwable $e) {
      // Table may not exist
    }

    // Check memory and DB
    $memPct = round(memory_get_usage(true) / self::parseBytes(ini_get('memory_limit')) * 100);
    if ($memPct > 60) {
      $insights[] = [
        'category'   => 'operational',
        'title'      => 'Memory usage elevated',
        'detail'     => "Currently at {$memPct}% memory utilization",
        'severity'   => $memPct > 80 ? 'high' : 'medium',
        'confidence' => 0.95,
        'icon'       => 'memory',
      ];
    }

    // Check exam season
    $month = (int) date('n');
    if (in_array($month, [3, 6, 10, 12])) {
      $insights[] = [
        'category'   => 'operational',
        'title'      => 'Exam season detected',
        'detail'     => 'Increased system usage expected — exam period month',
        'severity'   => 'info',
        'confidence' => 0.8,
        'icon'       => 'file-alt',
      ];
    }

    return $insights;
  }

  /**
   * Generate human interaction insights.
   */
  private static function interactionInsights(): array
  {
    $insights = [];

    try {
      // Teacher workload imbalance
      $teachers = db()->fetchAll(
        "SELECT marked_by, COUNT(*) as records
         FROM attendance WHERE marked_by IS NOT NULL AND date >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
         GROUP BY marked_by"
      ) ?: [];

      if (count($teachers) >= 3) {
        $records = array_column($teachers, 'records');
        $max = max($records);
        $min = min($records);
        $avg = array_sum($records) / count($records);

        if ($max > $avg * 2 && $min < $avg * 0.3) {
          $insights[] = [
            'category'   => 'interaction',
            'title'      => 'Teacher workload imbalance detected',
            'detail'     => "Max: {$max} records vs Min: {$min} records (avg: " . round($avg) . ")",
            'severity'   => 'medium',
            'confidence' => 0.7,
            'icon'       => 'balance-scale',
          ];
        }
      }

      // Admin activity during off-hours
      $offHours = db()->fetchOne(
        "SELECT COUNT(*) as cnt FROM activity_log
         WHERE user_role = 'admin' AND (HOUR(created_at) < 7 OR HOUR(created_at) > 20)
         AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
      );
      $offCount = (int) ($offHours['cnt'] ?? 0);
      if ($offCount > 30) {
        $insights[] = [
          'category'   => 'interaction',
          'title'      => 'Admin working extended hours',
          'detail'     => "{$offCount} off-hours actions in 7 days — consider workflow optimization",
          'severity'   => 'low',
          'confidence' => 0.65,
          'icon'       => 'clock',
        ];
      }
    } catch (\Throwable $e) {
      // Non-critical
    }

    return $insights;
  }

  /**
   * Generate policy effectiveness insights.
   */
  private static function policyInsights(): array
  {
    $insights = [];

    try {
      $policies = db()->fetchAll(
        "SELECT name, success_count, fail_count FROM cognitive_policies WHERE active = 1"
      ) ?: [];

      foreach ($policies as $p) {
        $success = (int) $p['success_count'];
        $fail = (int) $p['fail_count'];
        $total = $success + $fail;

        if ($total > 5 && $fail > $success) {
          $insights[] = [
            'category'   => 'policy',
            'title'      => "Policy underperforming: {$p['name']}",
            'detail'     => "Success: {$success}, Fail: {$fail} — consider adjustment",
            'severity'   => 'medium',
            'confidence' => 0.7,
            'icon'       => 'cog',
          ];
        }
      }
    } catch (\Throwable $e) {
      // Non-critical
    }

    return $insights;
  }

  /**
   * Generate trend-based insights.
   */
  private static function trendInsights(): array
  {
    $insights = [];

    try {
      // User growth
      $recentUsers = db()->fetchOne(
        "SELECT COUNT(*) as cnt FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
      );
      $newUsers = (int) ($recentUsers['cnt'] ?? 0);
      if ($newUsers > 10) {
        $insights[] = [
          'category'   => 'trend',
          'title'      => 'User registration spike',
          'detail'     => "{$newUsers} new registrations in 7 days",
          'severity'   => 'info',
          'confidence' => 0.9,
          'icon'       => 'user-plus',
        ];
      }

      // DB growth
      $dbSize = db()->fetchOne(
        "SELECT SUM(data_length + index_length) as total FROM information_schema.TABLES WHERE table_schema = DATABASE()"
      );
      $sizeMb = round(((float) ($dbSize['total'] ?? 0)) / 1048576, 1);
      if ($sizeMb > 100) {
        $insights[] = [
          'category'   => 'trend',
          'title'      => 'Database growing large',
          'detail'     => "Current size: {$sizeMb} MB — consider archiving old records",
          'severity'   => $sizeMb > 500 ? 'high' : 'medium',
          'confidence' => 0.95,
          'icon'       => 'database',
        ];
      }
    } catch (\Throwable $e) {
      // Non-critical
    }

    return $insights;
  }

  /**
   * Parse memory limit to bytes.
   */
  private static function parseBytes(string $val): int
  {
    $val = trim($val);
    if ($val === '-1') return PHP_INT_MAX;
    $unit = strtolower(substr($val, -1));
    $num = (int) $val;
    return match ($unit) {
      'g' => $num * 1073741824,
      'm' => $num * 1048576,
      'k' => $num * 1024,
      default => $num,
    };
  }

  /**
   * Summary for dashboard.
   */
  public static function getSummary(): array
  {
    $result = self::generate();
    return [
      'insights'   => array_slice($result['insights'], 0, 15),
      'categories' => $result['categories'],
      'total'      => $result['total'],
    ];
  }
}
