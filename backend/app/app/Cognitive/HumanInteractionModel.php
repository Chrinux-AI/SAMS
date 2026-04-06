<?php

/**
 * HumanInteractionModel — Understands Role Behavior
 *
 * Tracks:
 *   - Admin workload patterns
 *   - Teacher response speed
 *   - Student engagement cycles
 *
 * Goal: Reduce friction between humans and system workflows.
 */
class HumanInteractionModel
{
  /**
   * Analyze human interaction patterns across all roles.
   *
   * @return array ['roles' => [], 'frictions' => [], 'score' => int]
   */
  public static function analyze(): array
  {
    $roles = [];
    $frictions = [];

    $roles['admin'] = self::analyzeAdminWorkload();
    $roles['teacher'] = self::analyzeTeacherEngagement();
    $roles['student'] = self::analyzeStudentPatterns();

    // Detect interaction frictions
    $frictions = self::detectFrictions($roles);

    $score = self::calculateInteractionScore($roles, $frictions);

    return [
      'roles'       => $roles,
      'frictions'   => $frictions,
      'score'       => $score,
      'analyzed_at' => date('Y-m-d H:i:s'),
    ];
  }

  /**
   * Analyze admin workload patterns.
   */
  private static function analyzeAdminWorkload(): array
  {
    $result = ['workload_level' => 'low', 'metrics' => []];

    try {
      // Admin actions per day distribution
      $daily = db()->fetchAll(
        "SELECT DATE(created_at) as day, COUNT(*) as actions
         FROM activity_log
         WHERE user_role = 'admin' AND created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
         GROUP BY day ORDER BY day"
      ) ?: [];

      $totalActions = array_sum(array_column($daily, 'actions'));
      $avgDaily = count($daily) > 0 ? round($totalActions / count($daily)) : 0;

      $result['metrics']['avg_daily_actions'] = $avgDaily;
      $result['metrics']['total_14d'] = $totalActions;

      // Peak activity hours
      $peakHours = db()->fetchAll(
        "SELECT HOUR(created_at) as hr, COUNT(*) as cnt
         FROM activity_log
         WHERE user_role = 'admin' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
         GROUP BY hr ORDER BY cnt DESC LIMIT 3"
      ) ?: [];
      $result['metrics']['peak_hours'] = array_column($peakHours, 'hr');

      // Off-hours work detection
      $offHours = db()->fetchOne(
        "SELECT COUNT(*) as cnt FROM activity_log
         WHERE user_role = 'admin' AND (HOUR(created_at) < 7 OR HOUR(created_at) > 20)
         AND created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)"
      );
      $result['metrics']['off_hours_actions'] = (int) ($offHours['cnt'] ?? 0);

      $result['workload_level'] = $avgDaily > 100 ? 'high' : ($avgDaily > 40 ? 'moderate' : 'low');
    } catch (\Throwable $e) {
      // Non-critical
    }

    return $result;
  }

  /**
   * Analyze teacher engagement and response patterns.
   */
  private static function analyzeTeacherEngagement(): array
  {
    $result = ['engagement_level' => 'moderate', 'metrics' => []];

    try {
      // Teacher attendance marking consistency
      $teachers = db()->fetchAll(
        "SELECT u.id, u.full_name,
                COUNT(DISTINCT DATE(a.date)) as marking_days,
                MIN(TIME(a.created_at)) as earliest_mark,
                MAX(TIME(a.created_at)) as latest_mark
         FROM users u
         LEFT JOIN attendance a ON a.marked_by = u.id AND a.date >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
         WHERE u.role = 'teacher' AND u.status = 'active'
         GROUP BY u.id, u.full_name
         ORDER BY marking_days DESC
         LIMIT 20"
      ) ?: [];

      $activeCount = 0;
      $inactiveCount = 0;
      foreach ($teachers as $t) {
        $days = (int) $t['marking_days'];
        if ($days > 0) $activeCount++;
        else $inactiveCount++;
      }

      $result['metrics']['total_teachers'] = count($teachers);
      $result['metrics']['active_14d'] = $activeCount;
      $result['metrics']['inactive_14d'] = $inactiveCount;

      $engagementPct = count($teachers) > 0 ? round($activeCount / count($teachers) * 100) : 100;
      $result['metrics']['engagement_pct'] = $engagementPct;
      $result['engagement_level'] = $engagementPct > 80 ? 'high' : ($engagementPct > 50 ? 'moderate' : 'low');
    } catch (\Throwable $e) {
      // Non-critical
    }

    return $result;
  }

  /**
   * Analyze student interaction patterns.
   */
  private static function analyzeStudentPatterns(): array
  {
    $result = ['engagement_level' => 'moderate', 'metrics' => []];

    try {
      // Overall student activity (attendance participation)
      $stats = db()->fetchOne(
        "SELECT COUNT(DISTINCT student_id) as total_students,
                SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_count,
                COUNT(*) as total_records
         FROM attendance WHERE date >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)"
      );

      $totalRecords = (int) ($stats['total_records'] ?? 0);
      $presentCount = (int) ($stats['present_count'] ?? 0);
      $attendanceRate = $totalRecords > 0 ? round($presentCount / $totalRecords * 100, 1) : 100;

      $result['metrics']['total_students'] = (int) ($stats['total_students'] ?? 0);
      $result['metrics']['attendance_rate_14d'] = $attendanceRate;

      // Student login activity (if tracked)
      try {
        $logins = db()->fetchOne(
          "SELECT COUNT(*) as cnt FROM activity_log
           WHERE user_role = 'student' AND action = 'login'
           AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
        );
        $result['metrics']['student_logins_7d'] = (int) ($logins['cnt'] ?? 0);
      } catch (\Throwable $e) {
        $result['metrics']['student_logins_7d'] = 0;
      }

      $result['engagement_level'] = $attendanceRate > 85 ? 'high' : ($attendanceRate > 70 ? 'moderate' : 'low');
    } catch (\Throwable $e) {
      // Non-critical
    }

    return $result;
  }

  /**
   * Detect friction points between users and system.
   */
  private static function detectFrictions(array $roles): array
  {
    $frictions = [];

    // Admin overwork
    $admin = $roles['admin'];
    if ($admin['workload_level'] === 'high') {
      $frictions[] = [
        'type'     => 'admin_overload',
        'detail'   => "High admin workload: {$admin['metrics']['avg_daily_actions']} avg actions/day",
        'severity' => 'medium',
        'suggestion' => 'Consider delegating routine tasks or enabling more automation',
      ];
    }
    if (($admin['metrics']['off_hours_actions'] ?? 0) > 20) {
      $frictions[] = [
        'type'     => 'admin_off_hours',
        'detail'   => "{$admin['metrics']['off_hours_actions']} off-hours actions in 14d — possible workflow inefficiency",
        'severity' => 'low',
        'suggestion' => 'Review if after-hours work can be automated or scheduled',
      ];
    }

    // Teacher disengagement
    $teacher = $roles['teacher'];
    if ($teacher['engagement_level'] === 'low') {
      $frictions[] = [
        'type'     => 'teacher_disengagement',
        'detail'   => "Only {$teacher['metrics']['engagement_pct']}% teacher engagement in 14d",
        'severity' => 'high',
        'suggestion' => 'Simplify attendance workflow or provide training',
      ];
    }

    // Student disengagement
    $student = $roles['student'];
    if ($student['engagement_level'] === 'low') {
      $frictions[] = [
        'type'     => 'student_disengagement',
        'detail'   => "Attendance rate: {$student['metrics']['attendance_rate_14d']}%",
        'severity' => 'high',
        'suggestion' => 'Enable proactive engagement triggers and parent notifications',
      ];
    }

    return $frictions;
  }

  /**
   * Calculate human interaction score.
   */
  private static function calculateInteractionScore(array $roles, array $frictions): int
  {
    $score = 100;

    // Deduct for frictions
    foreach ($frictions as $f) {
      $score -= match ($f['severity'] ?? 'low') {
        'high'   => 12,
        'medium' => 6,
        default  => 3,
      };
    }

    // Bonus for high engagement
    foreach ($roles as $role) {
      if (($role['engagement_level'] ?? $role['workload_level'] ?? '') === 'high') {
        // High engagement is generally good (except admin overload)
      }
    }

    return max(0, min(100, $score));
  }

  /**
   * Summary for dashboard.
   */
  public static function getSummary(): array
  {
    $result = self::analyze();
    return [
      'score'      => $result['score'],
      'roles'      => [
        'admin'   => ['level' => $result['roles']['admin']['workload_level']],
        'teacher' => ['level' => $result['roles']['teacher']['engagement_level']],
        'student' => ['level' => $result['roles']['student']['engagement_level']],
      ],
      'frictions'       => $result['frictions'],
      'friction_count'  => count($result['frictions']),
    ];
  }
}
