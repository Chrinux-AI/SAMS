<?php

/**
 * BehaviorAnalyzer — Behavioral Intelligence Engine
 *
 * Tracks and analyzes patterns:
 *   login times, admin edits, teacher attendance trends,
 *   student absence cycles, role-based usage anomalies
 *
 * Detects abnormal deviations from historical baselines.
 */
class BehaviorAnalyzer
{
  /**
   * Run full behavior analysis.
   *
   * @return array ['anomalies' => [...], 'patterns' => [...], 'score' => int]
   */
  public static function analyze(): array
  {
    $anomalies = [];
    $patterns = [];

    // Login time analysis
    $loginAnalysis = self::analyzeLoginTimes();
    $anomalies = array_merge($anomalies, $loginAnalysis['anomalies']);
    $patterns = array_merge($patterns, $loginAnalysis['patterns']);

    // Admin edit frequency
    $adminAnalysis = self::analyzeAdminEdits();
    $anomalies = array_merge($anomalies, $adminAnalysis['anomalies']);
    $patterns = array_merge($patterns, $adminAnalysis['patterns']);

    // Teacher attendance patterns
    $teacherAnalysis = self::analyzeTeacherPatterns();
    $anomalies = array_merge($anomalies, $teacherAnalysis['anomalies']);
    $patterns = array_merge($patterns, $teacherAnalysis['patterns']);

    // Student absence cycles
    $studentAnalysis = self::analyzeStudentAbsenceCycles();
    $anomalies = array_merge($anomalies, $studentAnalysis['anomalies']);
    $patterns = array_merge($patterns, $studentAnalysis['patterns']);

    // Behavioral health score: 100 minus weighted anomaly count
    $score = max(0, 100 - (count($anomalies) * 8));

    if (!empty($anomalies)) {
      ErrorCollector::log('platform', count($anomalies) . ' behavioral anomalies detected', 'MEDIUM');
    }

    return [
      'anomalies' => $anomalies,
      'patterns'  => $patterns,
      'score'     => $score,
      'analyzed'  => date('Y-m-d H:i:s'),
    ];
  }

  /**
   * Analyze login time patterns — detect off-hours logins.
   */
  private static function analyzeLoginTimes(): array
  {
    $anomalies = [];
    $patterns = [];

    try {
      if (!function_exists('table_exists') || !table_exists('activity_log')) {
        return ['anomalies' => [], 'patterns' => []];
      }

      // Find users logging in at unusual hours (midnight-5am)
      $offHours = db()->fetchAll(
        "SELECT al.user_id, u.username, u.role, COUNT(*) AS cnt,
                MIN(al.created_at) AS first_seen, MAX(al.created_at) AS last_seen
         FROM activity_log al
         JOIN users u ON u.id = al.user_id
         WHERE al.action LIKE '%login%'
           AND HOUR(al.created_at) BETWEEN 0 AND 4
           AND al.created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
         GROUP BY al.user_id, u.username, u.role
         HAVING cnt >= 3
         ORDER BY cnt DESC
         LIMIT 20"
      );

      foreach ($offHours as $row) {
        $anomalies[] = [
          'type'       => 'off_hours_login',
          'severity'   => $row['cnt'] >= 7 ? 'high' : 'medium',
          'user_id'    => (int) $row['user_id'],
          'username'   => $row['username'],
          'role'       => $row['role'],
          'detail'     => "{$row['username']} ({$row['role']}) logged in {$row['cnt']} times between midnight-5am in 14 days",
          'count'      => (int) $row['cnt'],
          'last_seen'  => $row['last_seen'],
        ];
      }

      // General login patterns — peak hour distribution
      $hourDist = db()->fetchAll(
        "SELECT HOUR(created_at) AS hr, COUNT(*) AS cnt
         FROM activity_log
         WHERE action LIKE '%login%' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
         GROUP BY hr ORDER BY hr"
      );
      if (!empty($hourDist)) {
        $peakHour = 0;
        $peakCount = 0;
        foreach ($hourDist as $h) {
          if ((int) $h['cnt'] > $peakCount) {
            $peakCount = (int) $h['cnt'];
            $peakHour = (int) $h['hr'];
          }
        }
        $patterns[] = [
          'type'   => 'peak_login_hour',
          'detail' => "Peak login hour: {$peakHour}:00 ({$peakCount} logins this week)",
          'value'  => $peakHour,
        ];
      }
    } catch (\Throwable $e) {
      // Non-critical
    }

    return ['anomalies' => $anomalies, 'patterns' => $patterns];
  }

  /**
   * Analyze admin edit frequency — detect unusual bursts.
   */
  private static function analyzeAdminEdits(): array
  {
    $anomalies = [];
    $patterns = [];

    try {
      if (!function_exists('table_exists') || !table_exists('activity_log')) {
        return ['anomalies' => [], 'patterns' => []];
      }

      // Detect admin doing 20+ modifications in 1 hour
      $bursts = db()->fetchAll(
        "SELECT al.user_id, u.username, DATE(al.created_at) AS day,
                HOUR(al.created_at) AS hr, COUNT(*) AS cnt
         FROM activity_log al
         JOIN users u ON u.id = al.user_id
         WHERE u.role = 'admin'
           AND (al.action LIKE '%update%' OR al.action LIKE '%delete%' OR al.action LIKE '%edit%')
           AND al.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
         GROUP BY al.user_id, u.username, day, hr
         HAVING cnt >= 20
         ORDER BY cnt DESC
         LIMIT 10"
      );

      foreach ($bursts as $b) {
        $anomalies[] = [
          'type'     => 'admin_edit_burst',
          'severity' => $b['cnt'] >= 40 ? 'high' : 'medium',
          'user_id'  => (int) $b['user_id'],
          'username' => $b['username'],
          'detail'   => "{$b['username']} made {$b['cnt']} edits/deletes on {$b['day']} at {$b['hr']}:00",
          'count'    => (int) $b['cnt'],
        ];
      }

      // Daily admin action count pattern
      $daily = db()->fetchOne(
        "SELECT ROUND(AVG(cnt)) AS avg_daily FROM (
           SELECT DATE(created_at) AS d, COUNT(*) AS cnt
           FROM activity_log
           WHERE user_id IN (SELECT id FROM users WHERE role = 'admin')
             AND created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
           GROUP BY d
         ) sub"
      );
      if ($daily && $daily['avg_daily']) {
        $patterns[] = [
          'type'   => 'avg_admin_actions',
          'detail' => "Average daily admin actions: {$daily['avg_daily']}",
          'value'  => (int) $daily['avg_daily'],
        ];
      }
    } catch (\Throwable $e) {
      // Non-critical
    }

    return ['anomalies' => $anomalies, 'patterns' => $patterns];
  }

  /**
   * Analyze teacher attendance-marking patterns.
   */
  private static function analyzeTeacherPatterns(): array
  {
    $anomalies = [];
    $patterns = [];

    try {
      // Teachers who haven't marked attendance in 7+ days
      if (function_exists('table_exists') && table_exists('attendance')) {
        $inactive = db()->fetchAll(
          "SELECT u.id, u.username, CONCAT(u.first_name, ' ', u.last_name) AS name,
                  MAX(a.date) AS last_marked
           FROM users u
           LEFT JOIN attendance a ON a.marked_by = u.id
           WHERE u.role = 'teacher' AND u.status = 'active'
           GROUP BY u.id, u.username, u.first_name, u.last_name
           HAVING last_marked < DATE_SUB(CURDATE(), INTERVAL 7 DAY) OR last_marked IS NULL
           LIMIT 20"
        );

        foreach ($inactive as $t) {
          $anomalies[] = [
            'type'     => 'teacher_inactive',
            'severity' => 'medium',
            'user_id'  => (int) $t['id'],
            'username' => $t['username'],
            'detail'   => "{$t['name']} hasn't marked attendance since " . ($t['last_marked'] ?? 'never'),
          ];
        }

        // Average daily attendance records per teacher
        $avgMarking = db()->fetchOne(
          "SELECT ROUND(AVG(cnt)) AS avg_per_day FROM (
             SELECT marked_by, DATE(date) AS d, COUNT(*) AS cnt
             FROM attendance
             WHERE date >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
             GROUP BY marked_by, d
           ) sub"
        );
        if ($avgMarking && $avgMarking['avg_per_day']) {
          $patterns[] = [
            'type'   => 'avg_attendance_per_teacher',
            'detail' => "Average daily attendance marks per teacher: {$avgMarking['avg_per_day']}",
            'value'  => (int) $avgMarking['avg_per_day'],
          ];
        }
      }
    } catch (\Throwable $e) {
      // Non-critical
    }

    return ['anomalies' => $anomalies, 'patterns' => $patterns];
  }

  /**
   * Analyze student absence cycles — detect chronically absent students.
   */
  private static function analyzeStudentAbsenceCycles(): array
  {
    $anomalies = [];
    $patterns = [];

    try {
      // Students with >25% absence rate in last 30 days
      $chronic = db()->fetchAll(
        "SELECT a.student_id, u.username, CONCAT(u.first_name, ' ', u.last_name) AS name,
                COUNT(*) AS total,
                SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) AS absences
         FROM attendance a
         JOIN users u ON u.id = a.student_id
         WHERE a.date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
         GROUP BY a.student_id, u.username, u.first_name, u.last_name
         HAVING (absences / total) > 0.25 AND total >= 5
         ORDER BY (absences / total) DESC
         LIMIT 20"
      );

      foreach ($chronic as $s) {
        $rate = round(($s['absences'] / $s['total']) * 100, 1);
        $severity = $rate > 50 ? 'high' : 'medium';
        $anomalies[] = [
          'type'       => 'chronic_absence',
          'severity'   => $severity,
          'user_id'    => (int) $s['student_id'],
          'username'   => $s['username'],
          'detail'     => "{$s['name']} absent {$rate}% ({$s['absences']}/{$s['total']}) in 30 days",
          'rate'       => $rate,
        ];
      }

      // Overall absence rate
      $overall = db()->fetchOne(
        "SELECT COUNT(*) AS total,
                SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) AS absences
         FROM attendance
         WHERE date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)"
      );
      if ($overall && (int) $overall['total'] > 0) {
        $rate = round(((int) $overall['absences'] / (int) $overall['total']) * 100, 1);
        $patterns[] = [
          'type'   => 'weekly_absence_rate',
          'detail' => "This week absence rate: {$rate}%",
          'value'  => $rate,
        ];
      }
    } catch (\Throwable $e) {
      // Non-critical
    }

    return ['anomalies' => $anomalies, 'patterns' => $patterns];
  }

  /**
   * Get summary for dashboard.
   */
  public static function getSummary(): array
  {
    $result = self::analyze();
    return [
      'anomaly_count' => count($result['anomalies']),
      'pattern_count' => count($result['patterns']),
      'score'         => $result['score'],
      'top_anomalies' => array_slice($result['anomalies'], 0, 5),
      'patterns'      => $result['patterns'],
    ];
  }
}
