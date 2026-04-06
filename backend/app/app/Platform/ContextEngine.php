<?php

/**
 * ContextEngine — System State Determinator
 *
 * Analyzes platform conditions to determine operational context:
 *   Academic Peak, Attendance Risk, System Stress, Admin Activity
 *
 * Other engines use context to prioritize actions.
 */
class ContextEngine
{
  /** Context types and their detection thresholds */
  private const CONTEXTS = [
    'academic_peak',
    'attendance_risk',
    'system_stress',
    'admin_activity',
    'enrollment_period',
    'low_activity',
  ];

  /**
   * Evaluate all contexts and return active ones.
   *
   * @return array ['contexts' => [...], 'primary' => string, 'signals' => [...]]
   */
  public static function evaluate(): array
  {
    $active = [];
    $signals = [];

    // Academic Peak — exams within 14 days
    $examContext = self::detectAcademicPeak();
    if ($examContext['active']) {
      $active[] = [
        'type'       => 'academic_peak',
        'label'      => 'Academic Peak Period',
        'severity'   => 'high',
        'detail'     => $examContext['detail'],
        'confidence' => $examContext['confidence'],
      ];
      $signals[] = $examContext['signal'];
    }

    // Attendance Risk — absence rate rising
    $attendanceCtx = self::detectAttendanceRisk();
    if ($attendanceCtx['active']) {
      $active[] = [
        'type'       => 'attendance_risk',
        'label'      => 'Attendance Risk Detected',
        'severity'   => $attendanceCtx['severity'],
        'detail'     => $attendanceCtx['detail'],
        'confidence' => $attendanceCtx['confidence'],
      ];
      $signals[] = $attendanceCtx['signal'];
    }

    // System Stress — high resource usage
    $stressCtx = self::detectSystemStress();
    if ($stressCtx['active']) {
      $active[] = [
        'type'       => 'system_stress',
        'label'      => 'System Under Stress',
        'severity'   => $stressCtx['severity'],
        'detail'     => $stressCtx['detail'],
        'confidence' => $stressCtx['confidence'],
      ];
      $signals[] = $stressCtx['signal'];
    }

    // Admin Activity — high admin operations
    $adminCtx = self::detectAdminActivity();
    if ($adminCtx['active']) {
      $active[] = [
        'type'       => 'admin_activity',
        'label'      => 'Heavy Admin Activity',
        'severity'   => 'medium',
        'detail'     => $adminCtx['detail'],
        'confidence' => $adminCtx['confidence'],
      ];
      $signals[] = $adminCtx['signal'];
    }

    // Low Activity — weekends or off hours
    $lowCtx = self::detectLowActivity();
    if ($lowCtx['active']) {
      $active[] = [
        'type'       => 'low_activity',
        'label'      => 'Low Activity Window',
        'severity'   => 'low',
        'detail'     => $lowCtx['detail'],
        'confidence' => $lowCtx['confidence'],
      ];
      $signals[] = $lowCtx['signal'];
    }

    $primary = !empty($active) ? $active[0]['type'] : 'normal';

    return [
      'contexts' => $active,
      'primary'  => $primary,
      'signals'  => $signals,
      'total'    => count($active),
    ];
  }

  /**
   * Get short context label for other engines.
   */
  public static function getPrimaryContext(): string
  {
    $result = self::evaluate();
    return $result['primary'];
  }

  /**
   * Check if a specific context is active.
   */
  public static function isActive(string $contextType): bool
  {
    $result = self::evaluate();
    foreach ($result['contexts'] as $ctx) {
      if ($ctx['type'] === $contextType) return true;
    }
    return false;
  }

  // ── Detectors ──

  private static function detectAcademicPeak(): array
  {
    $result = ['active' => false, 'detail' => '', 'confidence' => 0, 'signal' => ''];
    try {
      // Check for exams within 14 days using class_schedules or a dedicated exam table
      if (function_exists('table_exists') && table_exists('class_schedules')) {
        $upcoming = db()->fetchOne(
          "SELECT COUNT(*) AS cnt FROM class_schedules
           WHERE day_of_week IS NOT NULL
           AND created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)"
        );
        // Heuristic: heavy scheduling activity can indicate exam period
        $count = (int) ($upcoming['cnt'] ?? 0);
        if ($count > 20) {
          $result['active'] = true;
          $result['detail'] = "{$count} schedule entries in last 14 days — possible exam period";
          $result['confidence'] = min(0.9, $count / 50);
          $result['signal'] = "academic_peak:{$count}_schedules";
        }
      }

      // Also check month — mid-term and end-term periods (March, June, Oct, Dec)
      $month = (int) date('n');
      if (in_array($month, [3, 6, 10, 12])) {
        $result['active'] = true;
        $result['detail'] = ($result['detail'] ? $result['detail'] . '; ' : '') . 'Exam-season month detected';
        $result['confidence'] = max($result['confidence'], 0.7);
        $result['signal'] = $result['signal'] ?: "academic_peak:exam_month_{$month}";
      }
    } catch (\Throwable $e) {
      // Non-critical
    }
    return $result;
  }

  private static function detectAttendanceRisk(): array
  {
    $result = ['active' => false, 'severity' => 'medium', 'detail' => '', 'confidence' => 0, 'signal' => ''];
    try {
      // Compare this week's absence rate to last week
      $thisWeek = db()->fetchOne(
        "SELECT COUNT(*) AS total,
                SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) AS absences
         FROM attendance
         WHERE date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)"
      );
      $lastWeek = db()->fetchOne(
        "SELECT COUNT(*) AS total,
                SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) AS absences
         FROM attendance
         WHERE date BETWEEN DATE_SUB(CURDATE(), INTERVAL 14 DAY) AND DATE_SUB(CURDATE(), INTERVAL 7 DAY)"
      );

      $thisTotal = (int) ($thisWeek['total'] ?? 0);
      $thisAbsent = (int) ($thisWeek['absences'] ?? 0);
      $lastAbsent = (int) ($lastWeek['absences'] ?? 0);
      $lastTotal = (int) ($lastWeek['total'] ?? 0);

      if ($thisTotal > 0 && $lastTotal > 0) {
        $thisRate = ($thisAbsent / $thisTotal) * 100;
        $lastRate = ($lastAbsent / $lastTotal) * 100;
        $increase = $thisRate - $lastRate;

        if ($increase > 5) {
          $result['active'] = true;
          $result['severity'] = $increase > 15 ? 'critical' : ($increase > 10 ? 'high' : 'medium');
          $result['detail'] = sprintf('Absence rate up %.1f%% (%.1f%% → %.1f%%)', $increase, $lastRate, $thisRate);
          $result['confidence'] = min(0.95, $increase / 20);
          $result['signal'] = "attendance_risk:increase_{$increase}pct";
        }
      }
    } catch (\Throwable $e) {
      // Non-critical
    }
    return $result;
  }

  private static function detectSystemStress(): array
  {
    $result = ['active' => false, 'severity' => 'medium', 'detail' => '', 'confidence' => 0, 'signal' => ''];
    try {
      $memUsage = memory_get_usage(true);
      $memLimit = self::parseBytes(ini_get('memory_limit'));
      $memPct = ($memLimit > 0) ? ($memUsage / $memLimit) * 100 : 0;

      if ($memPct > 70) {
        $result['active'] = true;
        $result['severity'] = $memPct > 90 ? 'critical' : 'high';
        $result['detail'] = sprintf('Memory at %.0f%%', $memPct);
        $result['confidence'] = min(0.95, $memPct / 100);
        $result['signal'] = "system_stress:memory_{$memPct}pct";
        return $result;
      }

      // Check DB latency via recent metrics
      if (function_exists('table_exists') && table_exists('system_metrics')) {
        $latency = db()->fetchOne(
          "SELECT value FROM system_metrics WHERE metric = 'db_latency' ORDER BY recorded_at DESC LIMIT 1"
        );
        $lat = (float) ($latency['value'] ?? 0);
        if ($lat > 500) {
          $result['active'] = true;
          $result['severity'] = $lat > 2000 ? 'critical' : 'high';
          $result['detail'] = sprintf('DB latency %.0fms', $lat);
          $result['confidence'] = min(0.9, $lat / 3000);
          $result['signal'] = "system_stress:db_latency_{$lat}ms";
        }
      }
    } catch (\Throwable $e) {
      // Non-critical
    }
    return $result;
  }

  private static function detectAdminActivity(): array
  {
    $result = ['active' => false, 'detail' => '', 'confidence' => 0, 'signal' => ''];
    try {
      if (function_exists('table_exists') && table_exists('activity_log')) {
        $row = db()->fetchOne(
          "SELECT COUNT(*) AS cnt FROM activity_log
           WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
           AND user_id IN (SELECT id FROM users WHERE role = 'admin')"
        );
        $count = (int) ($row['cnt'] ?? 0);
        if ($count > 15) {
          $result['active'] = true;
          $result['detail'] = "{$count} admin operations in the last hour";
          $result['confidence'] = min(0.9, $count / 30);
          $result['signal'] = "admin_activity:{$count}_ops";
        }
      }
    } catch (\Throwable $e) {
      // Non-critical
    }
    return $result;
  }

  private static function detectLowActivity(): array
  {
    $hour = (int) date('G');
    $dayOfWeek = (int) date('w'); // 0=Sun, 6=Sat

    $isWeekend = ($dayOfWeek === 0 || $dayOfWeek === 6);
    $isOffHours = ($hour < 6 || $hour > 20);

    if ($isWeekend || $isOffHours) {
      return [
        'active'     => true,
        'detail'     => $isWeekend ? 'Weekend — low traffic expected' : 'Off-hours — low traffic expected',
        'confidence' => 0.85,
        'signal'     => $isWeekend ? 'low_activity:weekend' : 'low_activity:off_hours',
      ];
    }
    return ['active' => false, 'detail' => '', 'confidence' => 0, 'signal' => ''];
  }

  /**
   * Parse PHP memory limit string to bytes.
   */
  private static function parseBytes(string $val): int
  {
    $val = trim($val);
    if ($val === '-1') return PHP_INT_MAX;
    $last = strtolower($val[strlen($val) - 1]);
    $num = (int) $val;
    switch ($last) {
      case 'g':
        $num *= 1024;
      case 'm':
        $num *= 1024;
      case 'k':
        $num *= 1024;
    }
    return $num;
  }
}
