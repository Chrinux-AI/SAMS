<?php

/**
 * AdaptiveLearningEngine — Institutional Personalization
 *
 * Creates adaptive behaviors:
 *   - Tailored reminders
 *   - Optimized notice timing
 *   - Personalized dashboards
 *   - Learning support triggers
 *
 * Example:
 *   Student frequently absent Mondays → proactive reminder Sunday evening
 */
class AdaptiveLearningEngine
{
  /**
   * Generate adaptive recommendations for the institution.
   *
   * @return array ['recommendations' => [], 'adaptations' => [], 'score' => int]
   */
  public static function adapt(): array
  {
    $recommendations = [];
    $adaptations = [];

    // Student-level adaptations
    $studentAdapt = self::adaptStudentBehavior();
    $recommendations = array_merge($recommendations, $studentAdapt['recommendations']);
    $adaptations = array_merge($adaptations, $studentAdapt['adaptations']);

    // Teacher-level adaptations
    $teacherAdapt = self::adaptTeacherWorkflow();
    $recommendations = array_merge($recommendations, $teacherAdapt['recommendations']);
    $adaptations = array_merge($adaptations, $teacherAdapt['adaptations']);

    // Communication timing optimization
    $timing = self::optimizeCommunicationTiming();
    $recommendations = array_merge($recommendations, $timing['recommendations']);

    // Adaptive score
    $score = self::calculateAdaptiveScore($recommendations, $adaptations);

    return [
      'recommendations' => $recommendations,
      'adaptations'     => $adaptations,
      'score'           => $score,
      'adapted_at'      => date('Y-m-d H:i:s'),
    ];
  }

  /**
   * Detect student absence day-of-week patterns and generate reminders.
   */
  private static function adaptStudentBehavior(): array
  {
    $recommendations = [];
    $adaptations = [];

    try {
      // Find students with specific day patterns (e.g., always absent Mondays)
      $dayPatterns = db()->fetchAll(
        "SELECT student_id, DAYOFWEEK(date) as dow, DAYNAME(date) as day_name,
                COUNT(*) as absences
         FROM attendance
         WHERE status = 'absent' AND date >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)
         GROUP BY student_id, dow, day_name
         HAVING absences >= 3
         ORDER BY absences DESC
         LIMIT 30"
      ) ?: [];

      foreach ($dayPatterns as $p) {
        $adaptations[] = [
          'type'       => 'proactive_reminder',
          'target'     => 'student',
          'target_id'  => (int) $p['student_id'],
          'detail'     => "Student frequently absent {$p['day_name']}s ({$p['absences']} times in 60d)",
          'action'     => "Send reminder the evening before {$p['day_name']}",
          'confidence' => min(1.0, (int) $p['absences'] / 8),
        ];
      }

      if (!empty($dayPatterns)) {
        $recommendations[] = [
          'type'     => 'student_day_pattern',
          'severity' => 'medium',
          'detail'   => count($dayPatterns) . ' student(s) with recurring day-specific absence patterns',
          'action'   => 'Enable proactive reminders for affected students',
          'confidence' => 0.8,
        ];
      }

      // Find students with recent declining engagement
      $declining = db()->fetchAll(
        "SELECT student_id,
                (SELECT COUNT(*) FROM attendance WHERE student_id = a.student_id AND status = 'absent'
                 AND date BETWEEN DATE_SUB(CURDATE(), INTERVAL 14 DAY) AND CURDATE()) as recent_absences,
                (SELECT COUNT(*) FROM attendance WHERE student_id = a.student_id AND status = 'absent'
                 AND date BETWEEN DATE_SUB(CURDATE(), INTERVAL 28 DAY) AND DATE_SUB(CURDATE(), INTERVAL 14 DAY)) as prior_absences
         FROM attendance a
         WHERE date >= DATE_SUB(CURDATE(), INTERVAL 28 DAY)
         GROUP BY student_id
         HAVING recent_absences > prior_absences AND recent_absences >= 3
         LIMIT 20"
      ) ?: [];

      foreach ($declining as $d) {
        $adaptations[] = [
          'type'       => 'engagement_intervention',
          'target'     => 'student',
          'target_id'  => (int) $d['student_id'],
          'detail'     => "Recent absences ({$d['recent_absences']}) > prior period ({$d['prior_absences']})",
          'action'     => 'Trigger learning support check-in',
          'confidence' => 0.75,
        ];
      }
    } catch (\Throwable $e) {
      ErrorCollector::log('cognitive', 'Adaptive student analysis error: ' . $e->getMessage(), 'MEDIUM');
    }

    return ['recommendations' => $recommendations, 'adaptations' => $adaptations];
  }

  /**
   * Adapt teacher workflows based on patterns.
   */
  private static function adaptTeacherWorkflow(): array
  {
    $recommendations = [];
    $adaptations = [];

    try {
      // Identify teachers who mark attendance late or inconsistently
      $teachers = db()->fetchAll(
        "SELECT marked_by as teacher_id,
                AVG(HOUR(created_at)) as avg_mark_hour,
                COUNT(DISTINCT DATE(date)) as unique_days,
                COUNT(*) as total_records
         FROM attendance
         WHERE marked_by IS NOT NULL AND date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
         GROUP BY marked_by
         HAVING unique_days >= 5
         ORDER BY avg_mark_hour DESC
         LIMIT 15"
      ) ?: [];

      foreach ($teachers as $t) {
        $avgHour = round((float) $t['avg_mark_hour'], 1);

        // Late markers (after 11 AM average)
        if ($avgHour > 11) {
          $adaptations[] = [
            'type'       => 'reminder_optimization',
            'target'     => 'teacher',
            'target_id'  => (int) $t['teacher_id'],
            'detail'     => "Average marking time: {$avgHour}:00 — consider earlier reminder",
            'action'     => 'Send attendance reminder at 8:00 AM',
            'confidence' => 0.7,
          ];
        }
      }

      if (!empty($adaptations)) {
        $recommendations[] = [
          'type'     => 'teacher_timing_optimization',
          'severity' => 'low',
          'detail'   => count($adaptations) . ' teacher(s) could benefit from optimized reminder timing',
          'action'   => 'Adjust notification schedule for late markers',
          'confidence' => 0.7,
        ];
      }
    } catch (\Throwable $e) {
      // Non-critical
    }

    return ['recommendations' => $recommendations, 'adaptations' => $adaptations];
  }

  /**
   * Optimize communication timing based on engagement data.
   */
  private static function optimizeCommunicationTiming(): array
  {
    $recommendations = [];

    try {
      // Find peak activity hours
      $peaks = db()->fetchAll(
        "SELECT HOUR(created_at) as hr, COUNT(*) as activity_count
         FROM activity_log
         WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
         GROUP BY hr
         ORDER BY activity_count DESC
         LIMIT 5"
      ) ?: [];

      if (!empty($peaks)) {
        $peakHour = (int) $peaks[0]['hr'];
        $peakCount = (int) $peaks[0]['activity_count'];

        $recommendations[] = [
          'type'     => 'optimal_notification_window',
          'severity' => 'info',
          'detail'   => "Peak activity at {$peakHour}:00 ({$peakCount} actions in 7d) — best time for notices",
          'action'   => "Schedule important notifications near {$peakHour}:00 for maximum visibility",
          'confidence' => 0.65,
        ];
      }
    } catch (\Throwable $e) {
      // Non-critical
    }

    return ['recommendations' => $recommendations];
  }

  /**
   * Calculate adaptive system score.
   */
  private static function calculateAdaptiveScore(array $recommendations, array $adaptations): int
  {
    $score = 100;

    // More adaptations needed = lower score (system hasn't adapted yet)
    $urgentRecs = array_filter($recommendations, fn($r) => ($r['severity'] ?? '') === 'high');
    $score -= count($urgentRecs) * 8;
    $score -= min(20, count($adaptations) * 2);

    return max(0, min(100, $score));
  }

  /**
   * Summary for dashboard.
   */
  public static function getSummary(): array
  {
    $result = self::adapt();
    return [
      'score'              => $result['score'],
      'recommendations'    => array_slice($result['recommendations'], 0, 8),
      'adaptations'        => array_slice($result['adaptations'], 0, 10),
      'recommendation_count' => count($result['recommendations']),
      'adaptation_count'   => count($result['adaptations']),
    ];
  }
}
