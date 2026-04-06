<?php

/**
 * InstitutionalState — Real-Time System Awareness
 *
 * Tracks the live state of the institution: active classes, attendance flow,
 * academic period, user activity, system load. Provides a snapshot for
 * dashboards and the OSKernel.
 */
class InstitutionalState
{
  /**
   * Capture a full institutional snapshot.
   */
  public static function snapshot(): array
  {
    return [
      'timestamp'    => date('c'),
      'academic'     => self::academicPeriod(),
      'users'        => self::userActivity(),
      'attendance'   => self::attendanceState(),
      'classes'      => self::classState(),
      'system'       => self::systemLoad(),
    ];
  }

  /**
   * Determine current academic period.
   */
  public static function academicPeriod(): array
  {
    $month    = (int) date('n');
    $dayOfWeek = (int) date('N'); // 1=Mon, 7=Sun
    $hour     = (int) date('G');

    $term = 'unknown';
    if ($month >= 1 && $month <= 3) $term = 'Term 1';
    elseif ($month >= 4 && $month <= 6) $term = 'Term 2';
    elseif ($month >= 7 && $month <= 9) $term = 'Term 3';
    else $term = 'Term 4';

    $isSchoolDay  = ($dayOfWeek <= 5);
    $isSchoolHour = ($isSchoolDay && $hour >= 7 && $hour <= 16);

    return [
      'term'           => $term,
      'month'          => $month,
      'is_school_day'  => $isSchoolDay,
      'is_school_hour' => $isSchoolHour,
      'day_of_week'    => $dayOfWeek,
      'hour'           => $hour,
    ];
  }

  /**
   * Get user activity summary.
   */
  public static function userActivity(): array
  {
    try {
      $totalUsers = db()->count("users", "1=1");
      $activeUsers = 0;
      if (table_exists('activity_log')) {
        $row = db()->fetchOne(
          "SELECT COUNT(DISTINCT user_id) as cnt FROM activity_log WHERE created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)"
        );
        $activeUsers = (int) ($row['cnt'] ?? 0);
      }
      $roleCounts = IdentityCore::getRoleCounts();

      return [
        'total'       => $totalUsers,
        'active_now'  => $activeUsers,
        'by_role'     => $roleCounts,
      ];
    } catch (\Throwable $e) {
      return ['total' => 0, 'active_now' => 0, 'by_role' => []];
    }
  }

  /**
   * Get today's attendance state.
   */
  public static function attendanceState(): array
  {
    try {
      $today = date('Y-m-d');
      $total = db()->count("attendance", "date = ?", [$today]);
      $present = db()->count("attendance", "date = ? AND status = 'present'", [$today]);
      $absent  = db()->count("attendance", "date = ? AND status = 'absent'", [$today]);
      $late    = db()->count("attendance", "date = ? AND status = 'late'", [$today]);

      return [
        'date'    => $today,
        'total'   => $total,
        'present' => $present,
        'absent'  => $absent,
        'late'    => $late,
        'rate'    => $total > 0 ? round(($present / $total) * 100, 1) : 0,
      ];
    } catch (\Throwable $e) {
      return ['date' => date('Y-m-d'), 'total' => 0, 'present' => 0, 'absent' => 0, 'late' => 0, 'rate' => 0];
    }
  }

  /**
   * Get class state.
   */
  public static function classState(): array
  {
    try {
      $totalClasses = db()->count("classes", "1=1");
      return [
        'total'  => $totalClasses,
      ];
    } catch (\Throwable $e) {
      return ['total' => 0];
    }
  }

  /**
   * Get system load metrics.
   */
  public static function systemLoad(): array
  {
    $storageDir = BASE_PATH . '/storage';
    $uploadsDir = BASE_PATH . '/uploads';

    return [
      'php_version'     => PHP_VERSION,
      'memory_usage'    => round(memory_get_usage(true) / 1048576, 2) . ' MB',
      'memory_peak'     => round(memory_get_peak_usage(true) / 1048576, 2) . ' MB',
      'storage_exists'  => is_dir($storageDir),
      'uploads_exists'  => is_dir($uploadsDir),
      'disk_free'       => round(@disk_free_space(BASE_PATH) / 1073741824, 2) . ' GB',
    ];
  }
}
