<?php

/**
 * AcademicReasoner — Analyzes the Academic Ecosystem
 *
 * Reasoning areas:
 *   - Attendance vs performance correlations
 *   - Subject difficulty patterns
 *   - Teacher effectiveness signals
 *   - Timetable optimization opportunities
 *
 * Produces insights like:
 *   "Mathematics performance drops after timetable compression."
 */
class AcademicReasoner
{
  /**
   * Run full academic reasoning cycle.
   *
   * @return array ['insights' => [], 'metrics' => [], 'score' => int]
   */
  public static function reason(): array
  {
    $insights = [];
    $metrics = [];

    // Attendance patterns
    $attendance = self::analyzeAttendancePatterns();
    $insights = array_merge($insights, $attendance['insights']);
    $metrics['attendance'] = $attendance['metrics'];

    // Subject difficulty
    $subjects = self::analyzeSubjectDifficulty();
    $insights = array_merge($insights, $subjects['insights']);
    $metrics['subjects'] = $subjects['metrics'];

    // Teacher effectiveness
    $teachers = self::analyzeTeacherEffectiveness();
    $insights = array_merge($insights, $teachers['insights']);
    $metrics['teachers'] = $teachers['metrics'];

    // Timetable patterns
    $timetable = self::analyzeTimetablePatterns();
    $insights = array_merge($insights, $timetable['insights']);
    $metrics['timetable'] = $timetable['metrics'];

    // Academic health score
    $score = self::calculateAcademicScore($metrics);

    return [
      'insights'    => $insights,
      'metrics'     => $metrics,
      'score'       => $score,
      'reasoned_at' => date('Y-m-d H:i:s'),
    ];
  }

  /**
   * Analyze attendance patterns across the institution.
   */
  private static function analyzeAttendancePatterns(): array
  {
    $insights = [];
    $metrics = ['weekly_rates' => [], 'day_distribution' => []];

    try {
      // Weekly attendance trend (6 weeks)
      $weeks = db()->fetchAll(
        "SELECT YEARWEEK(date) as yw, COUNT(*) as total,
                SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present
         FROM attendance
         WHERE date >= DATE_SUB(CURDATE(), INTERVAL 42 DAY)
         GROUP BY yw ORDER BY yw"
      ) ?: [];

      $rates = [];
      foreach ($weeks as $w) {
        $total = (int) $w['total'];
        $rate = $total > 0 ? round((int) $w['present'] / $total * 100, 1) : 100;
        $rates[] = $rate;
        $metrics['weekly_rates'][] = ['week' => $w['yw'], 'rate' => $rate];
      }

      // Detect declining trend
      if (count($rates) >= 3) {
        $recent = array_slice($rates, -3);
        if ($recent[2] < $recent[0] - 5) {
          $insights[] = [
            'type'     => 'attendance_declining',
            'severity' => 'high',
            'detail'   => 'Attendance declining over 3 weeks: ' . implode('% → ', $recent) . '%',
            'confidence' => 0.85,
          ];
        }
      }

      // Day-of-week distribution
      $dayDist = db()->fetchAll(
        "SELECT DAYNAME(date) as day_name, DAYOFWEEK(date) as dow,
                COUNT(*) as total,
                SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absences
         FROM attendance
         WHERE date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
         GROUP BY day_name, dow ORDER BY dow"
      ) ?: [];

      $worstDay = null;
      $worstRate = 0;
      foreach ($dayDist as $d) {
        $total = (int) $d['total'];
        $absRate = $total > 0 ? round((int) $d['absences'] / $total * 100, 1) : 0;
        $metrics['day_distribution'][] = ['day' => $d['day_name'], 'absence_rate' => $absRate];
        if ($absRate > $worstRate) {
          $worstRate = $absRate;
          $worstDay = $d['day_name'];
        }
      }

      if ($worstDay && $worstRate > 15) {
        $insights[] = [
          'type'     => 'day_absence_pattern',
          'severity' => 'medium',
          'detail'   => "{$worstDay} has highest absence rate: {$worstRate}%",
          'confidence' => 0.75,
        ];
      }
    } catch (\Throwable $e) {
      ErrorCollector::log('cognitive', 'Academic attendance analysis error: ' . $e->getMessage(), 'MEDIUM');
    }

    return ['insights' => $insights, 'metrics' => $metrics];
  }

  /**
   * Analyze subject difficulty patterns.
   */
  private static function analyzeSubjectDifficulty(): array
  {
    $insights = [];
    $metrics = [];

    try {
      // Class-level attendance as proxy for engagement
      $classes = db()->fetchAll(
        "SELECT c.id, c.class_name,
                COUNT(a.id) as total_records,
                SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present,
                SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent
         FROM classes c
         JOIN class_enrollments ce ON c.id = ce.class_id
         JOIN attendance a ON a.student_id = ce.student_id AND a.date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
         GROUP BY c.id, c.class_name
         HAVING total_records > 5
         ORDER BY (absent / total_records) DESC
         LIMIT 20"
      ) ?: [];

      foreach ($classes as $c) {
        $total = (int) $c['total_records'];
        $absRate = $total > 0 ? round((int) $c['absent'] / $total * 100, 1) : 0;
        $metrics[] = [
          'class'        => $c['class_name'],
          'absence_rate' => $absRate,
          'records'      => $total,
        ];

        if ($absRate > 30) {
          $insights[] = [
            'type'     => 'high_difficulty_class',
            'severity' => 'high',
            'detail'   => "{$c['class_name']}: {$absRate}% absence rate — potential difficulty/engagement issue",
            'confidence' => 0.7,
          ];
        }
      }
    } catch (\Throwable $e) {
      // Non-critical
    }

    return ['insights' => $insights, 'metrics' => $metrics];
  }

  /**
   * Analyze teacher effectiveness signals.
   */
  private static function analyzeTeacherEffectiveness(): array
  {
    $insights = [];
    $metrics = [];

    try {
      $teachers = db()->fetchAll(
        "SELECT u.id, u.full_name,
                COUNT(DISTINCT DATE(a.date)) as active_days,
                COUNT(a.id) as records_marked,
                (SELECT COUNT(DISTINCT ce.class_id) FROM class_enrollments ce
                 JOIN attendance a2 ON a2.student_id = ce.student_id AND a2.marked_by = u.id
                 WHERE a2.date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                ) as classes_serviced
         FROM users u
         LEFT JOIN attendance a ON a.marked_by = u.id AND a.date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
         WHERE u.role = 'teacher' AND u.status = 'active'
         GROUP BY u.id, u.full_name
         ORDER BY active_days DESC
         LIMIT 20"
      ) ?: [];

      $totalActive = 0;
      $totalDays = 0;
      foreach ($teachers as $t) {
        $days = (int) $t['active_days'];
        $totalActive++;
        $totalDays += $days;
        $metrics[] = [
          'name'        => $t['full_name'],
          'active_days' => $days,
          'records'     => (int) $t['records_marked'],
          'classes'     => (int) $t['classes_serviced'],
        ];

        if ($days === 0) {
          $insights[] = [
            'type'     => 'teacher_zero_activity',
            'severity' => 'high',
            'detail'   => "{$t['full_name']}: no attendance records marked in 30 days",
            'confidence' => 0.9,
          ];
        }
      }

      // Workload imbalance
      if ($totalActive >= 3) {
        $avgDays = $totalDays / $totalActive;
        $maxDays = max(array_column($metrics, 'active_days'));
        $minDays = min(array_column($metrics, 'active_days'));
        if ($maxDays > 0 && $minDays < ($avgDays * 0.3)) {
          $insights[] = [
            'type'     => 'teacher_workload_imbalance',
            'severity' => 'medium',
            'detail'   => "Workload imbalance: most active {$maxDays} days vs least active {$minDays} days (avg: " . round($avgDays, 1) . ")",
            'confidence' => 0.7,
          ];
        }
      }
    } catch (\Throwable $e) {
      // Non-critical
    }

    return ['insights' => $insights, 'metrics' => $metrics];
  }

  /**
   * Analyze timetable/schedule patterns.
   */
  private static function analyzeTimetablePatterns(): array
  {
    $insights = [];
    $metrics = [];

    try {
      // Analyze attendance by time-of-day if timestamps available
      $hourly = db()->fetchAll(
        "SELECT HOUR(created_at) as hr, COUNT(*) as total,
                SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present
         FROM attendance
         WHERE date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND created_at IS NOT NULL
         GROUP BY hr
         HAVING total > 5
         ORDER BY hr"
      ) ?: [];

      $morningRate = 0;
      $afternoonRate = 0;
      $mCount = 0;
      $aCount = 0;

      foreach ($hourly as $h) {
        $hr = (int) $h['hr'];
        $total = (int) $h['total'];
        $rate = $total > 0 ? round((int) $h['present'] / $total * 100, 1) : 100;
        $metrics[] = ['hour' => $hr, 'attendance_rate' => $rate, 'records' => $total];

        if ($hr >= 8 && $hr < 12) {
          $morningRate += $rate;
          $mCount++;
        } elseif ($hr >= 12 && $hr < 16) {
          $afternoonRate += $rate;
          $aCount++;
        }
      }

      if ($mCount > 0 && $aCount > 0) {
        $avgMorning = round($morningRate / $mCount, 1);
        $avgAfternoon = round($afternoonRate / $aCount, 1);

        if ($avgAfternoon < $avgMorning - 10) {
          $insights[] = [
            'type'     => 'afternoon_engagement_drop',
            'severity' => 'medium',
            'detail'   => "Afternoon attendance ({$avgAfternoon}%) significantly lower than morning ({$avgMorning}%)",
            'confidence' => 0.7,
          ];
        }
      }
    } catch (\Throwable $e) {
      // Non-critical
    }

    return ['insights' => $insights, 'metrics' => $metrics];
  }

  /**
   * Calculate overall academic health score.
   */
  private static function calculateAcademicScore(array $metrics): int
  {
    $score = 100;
    $deductions = 0;

    // Attendance trend penalty
    $weeklyRates = $metrics['attendance']['weekly_rates'] ?? [];
    if (!empty($weeklyRates)) {
      $lastRate = end($weeklyRates)['rate'] ?? 100;
      if ($lastRate < 80) $deductions += 15;
      elseif ($lastRate < 90) $deductions += 5;
    }

    // Subject difficulty penalty
    $difficultClasses = array_filter($metrics['subjects'] ?? [], fn($s) => ($s['absence_rate'] ?? 0) > 30);
    $deductions += count($difficultClasses) * 3;

    // Teacher effectiveness penalty
    $zeroActivity = array_filter($metrics['teachers'] ?? [], fn($t) => ($t['active_days'] ?? 0) === 0);
    $deductions += count($zeroActivity) * 5;

    return max(0, min(100, $score - $deductions));
  }

  /**
   * Summary for dashboard.
   */
  public static function getSummary(): array
  {
    $result = self::reason();
    return [
      'score'       => $result['score'],
      'insights'    => array_slice($result['insights'], 0, 10),
      'insight_count' => count($result['insights']),
      'reasoned_at' => $result['reasoned_at'],
    ];
  }
}
