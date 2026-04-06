<?php

/**
 * AIC — Workload Balancer
 * Analyzes teacher workload distribution and identifies imbalances.
 */
class WorkloadBalancer
{
  /**
   * Analyze teacher workload distribution.
   */
  public static function analyze(): array
  {
    $workloads = self::getTeacherWorkloads();
    $balance   = self::calculateBalance($workloads);
    $alerts    = self::generateAlerts($workloads, $balance);

    return [
      'balance_score'    => $balance['score'],
      'total_teachers'   => count($workloads),
      'avg_classes'      => $balance['avg_classes'],
      'avg_students'     => $balance['avg_students'],
      'overloaded'       => $balance['overloaded'],
      'underutilized'    => $balance['underutilized'],
      'workloads'        => array_slice($workloads, 0, 10), // top 10 for display
      'alerts'           => $alerts,
    ];
  }

  /**
   * Get workload data per teacher.
   */
  private static function getTeacherWorkloads(): array
  {
    try {
      $pdo = db()->getConnection();

      // Get each teacher's class count and student count
      $stmt = $pdo->query("
                SELECT u.id, u.full_name,
                       COUNT(DISTINCT c.id) as class_count,
                       COUNT(DISTINCT ce.student_id) as student_count
                FROM users u
                LEFT JOIN classes c ON c.class_teacher_id = u.id
                LEFT JOIN class_enrollments ce ON ce.class_id = c.id
                WHERE u.role = 'teacher' AND u.status = 'active'
                GROUP BY u.id, u.full_name
                ORDER BY class_count DESC
            ");
      $teachers = $stmt->fetchAll(\PDO::FETCH_ASSOC);

      return array_map(function ($t) {
        return [
          'id'            => (int) $t['id'],
          'name'          => $t['full_name'],
          'classes'       => (int) $t['class_count'],
          'students'      => (int) $t['student_count'],
        ];
      }, $teachers);
    } catch (\Throwable $e) {
      return [];
    }
  }

  /**
   * Calculate balance metrics.
   */
  private static function calculateBalance(array $workloads): array
  {
    if (empty($workloads)) {
      return ['score' => 100, 'avg_classes' => 0, 'avg_students' => 0, 'overloaded' => 0, 'underutilized' => 0];
    }

    $classCounts   = array_column($workloads, 'classes');
    $studentCounts = array_column($workloads, 'students');

    $avgClasses  = round(array_sum($classCounts) / count($classCounts), 1);
    $avgStudents = round(array_sum($studentCounts) / count($studentCounts), 1);

    // Standard deviation of class counts
    $variance = 0;
    foreach ($classCounts as $c) {
      $variance += ($c - $avgClasses) ** 2;
    }
    $stdDev = sqrt($variance / count($classCounts));

    // Overloaded: more than avg + 1 std dev
    $overloaded = 0;
    $underutilized = 0;
    foreach ($workloads as $w) {
      if ($w['classes'] > $avgClasses + $stdDev) $overloaded++;
      if ($w['classes'] < max(1, $avgClasses - $stdDev)) $underutilized++;
    }

    // Balance score: lower std dev = higher score
    $maxExpectedDev = max($avgClasses * 0.5, 1);
    $score = max(0, min(100, (int) round(100 - ($stdDev / $maxExpectedDev * 50))));

    return [
      'score'         => $score,
      'avg_classes'   => $avgClasses,
      'avg_students'  => $avgStudents,
      'overloaded'    => $overloaded,
      'underutilized' => $underutilized,
    ];
  }

  /**
   * Generate workload alerts.
   */
  private static function generateAlerts(array $workloads, array $balance): array
  {
    $alerts = [];

    if ($balance['overloaded'] > 0) {
      $alerts[] = [
        'severity' => $balance['overloaded'] > 3 ? 'HIGH' : 'MEDIUM',
        'message'  => "{$balance['overloaded']} teacher(s) overloaded (above average class count)",
      ];
    }

    if ($balance['score'] < 50) {
      $alerts[] = ['severity' => 'HIGH', 'message' => 'Teacher workload severely unbalanced (score: ' . $balance['score'] . ')'];
    }

    return $alerts;
  }
}
