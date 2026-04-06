<?php

/**
 * AIC — Academic Predictor
 * Predicts academic risks based on attendance patterns and institutional trends.
 */
class AcademicPredictor
{
  /**
   * Generate academic predictions.
   */
  public static function predict(): array
  {
    $predictions = [];
    $alerts = [];

    // 1. Predict term-end attendance rate
    $termPrediction = self::predictTermAttendance();
    $predictions[] = $termPrediction;

    // 2. Predict students likely to become chronically absent
    $chronicRisk = self::predictChronicAbsentees();
    $predictions[] = $chronicRisk;

    // 3. Predict teacher capacity issues
    $capacityRisk = self::predictCapacityIssues();
    $predictions[] = $capacityRisk;

    // Gather alerts from predictions
    foreach ($predictions as $p) {
      if (isset($p['alert'])) {
        $alerts[] = $p['alert'];
      }
    }

    return [
      'predictions' => $predictions,
      'alerts'      => $alerts,
      'count'       => count($predictions),
    ];
  }

  /**
   * Predict term-end attendance rate based on current trajectory.
   */
  private static function predictTermAttendance(): array
  {
    try {
      $pdo = db()->getConnection();

      // Get weekly attendance rates for the last 4 weeks
      $stmt = $pdo->query("
                SELECT YEARWEEK(date) as wk,
                       SUM(CASE WHEN status IN ('present','late') THEN 1 ELSE 0 END) / COUNT(*) * 100 as rate
                FROM attendance
                WHERE date >= DATE_SUB(CURDATE(), INTERVAL 28 DAY)
                GROUP BY YEARWEEK(date)
                ORDER BY wk
            ");
      $weeks = $stmt->fetchAll(\PDO::FETCH_ASSOC);

      if (count($weeks) < 2) {
        return ['type' => 'term_attendance', 'predicted_rate' => null, 'confidence' => 0, 'message' => 'Insufficient data for prediction'];
      }

      // Simple linear regression on weekly rates
      $rates = array_column($weeks, 'rate');
      $n = count($rates);
      $slope = ($rates[$n - 1] - $rates[0]) / max($n - 1, 1);
      $predicted = round($rates[$n - 1] + ($slope * 4), 1); // Project 4 weeks ahead
      $predicted = max(0, min(100, $predicted));

      $result = [
        'type'           => 'term_attendance',
        'current_rate'   => round(end($rates), 1),
        'predicted_rate' => $predicted,
        'trend'          => $slope > 0.5 ? 'improving' : ($slope < -0.5 ? 'declining' : 'stable'),
        'confidence'     => min(85, 50 + ($n * 10)),
        'message'        => "Projected attendance rate in 4 weeks: {$predicted}%",
      ];

      if ($predicted < 80) {
        $result['alert'] = ['severity' => 'HIGH', 'message' => "Attendance projected to drop to {$predicted}% — intervention needed"];
      }

      return $result;
    } catch (\Throwable $e) {
      return ['type' => 'term_attendance', 'error' => $e->getMessage()];
    }
  }

  /**
   * Predict students likely to become chronically absent.
   */
  private static function predictChronicAbsentees(): array
  {
    try {
      $pdo = db()->getConnection();
      $stmt = $pdo->query("
                SELECT u.id, u.full_name,
                       SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absences,
                       COUNT(*) as total_records,
                       SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) / COUNT(*) * 100 as absence_rate
                FROM attendance a
                JOIN users u ON u.id = a.user_id
                WHERE a.date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                AND u.role = 'student' AND u.status = 'active'
                GROUP BY u.id, u.full_name
                HAVING absence_rate BETWEEN 7 AND 15
                ORDER BY absence_rate DESC
                LIMIT 15
            ");
      $approaching = $stmt->fetchAll(\PDO::FETCH_ASSOC);

      $result = [
        'type'               => 'chronic_risk',
        'approaching_count'  => count($approaching),
        'students'           => array_slice($approaching, 0, 5),
        'message'            => count($approaching) . ' student(s) approaching chronic absenteeism threshold',
      ];

      if (count($approaching) >= 5) {
        $result['alert'] = ['severity' => 'MEDIUM', 'message' => count($approaching) . ' students trending toward chronic absenteeism'];
      }

      return $result;
    } catch (\Throwable $e) {
      return ['type' => 'chronic_risk', 'error' => $e->getMessage()];
    }
  }

  /**
   * Predict teacher capacity issues.
   */
  private static function predictCapacityIssues(): array
  {
    try {
      $pdo = db()->getConnection();

      $totalTeachers = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'teacher' AND status = 'active'")->fetchColumn();
      $totalClasses  = (int) $pdo->query("SELECT COUNT(*) FROM classes")->fetchColumn();

      $ratio = $totalTeachers > 0 ? round($totalClasses / $totalTeachers, 1) : 0;

      $result = [
        'type'     => 'capacity',
        'teachers' => $totalTeachers,
        'classes'  => $totalClasses,
        'ratio'    => $ratio,
        'message'  => "Class-to-teacher ratio: {$ratio}:1",
      ];

      if ($ratio > 6) {
        $result['alert'] = ['severity' => 'HIGH', 'message' => "High class-to-teacher ratio ({$ratio}:1) — teachers may be overloaded"];
      }

      return $result;
    } catch (\Throwable $e) {
      return ['type' => 'capacity', 'error' => $e->getMessage()];
    }
  }
}
