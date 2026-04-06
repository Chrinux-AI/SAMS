<?php

/**
 * InstitutionalModel — Digital Twin of the School
 *
 * Builds a live representation of the institution:
 *   - Departments, academic levels, classes
 *   - Staff efficiency, student engagement
 *   - Operational stress indicators
 *
 * Enables reasoning like:
 *   Low attendance + difficult subject + morning schedule
 *   → structural learning friction detected
 */
class InstitutionalModel
{
  /**
   * Build the complete institutional model snapshot.
   *
   * @return array Full institutional state
   */
  public static function build(): array
  {
    $model = [
      'departments'   => self::modelDepartments(),
      'academic_levels' => self::modelAcademicLevels(),
      'classes'       => self::modelClasses(),
      'staff'         => self::modelStaffEfficiency(),
      'students'      => self::modelStudentEngagement(),
      'operations'    => self::modelOperationalStress(),
      'frictions'     => [],
      'built_at'      => date('Y-m-d H:i:s'),
    ];

    // Detect structural frictions
    $model['frictions'] = self::detectFrictions($model);

    // Persist snapshot
    self::persistSnapshot($model);

    return $model;
  }

  /**
   * Model department structure.
   */
  private static function modelDepartments(): array
  {
    try {
      // Infer departments from class/subject data
      $subjects = db()->fetchAll(
        "SELECT DISTINCT subject_name, COUNT(*) as class_count
         FROM classes WHERE subject_name IS NOT NULL AND subject_name != ''
         GROUP BY subject_name ORDER BY class_count DESC LIMIT 30"
      ) ?: [];

      if (empty($subjects)) {
        // Fallback: infer from class names
        $subjects = db()->fetchAll(
          "SELECT class_name as subject_name, 1 as class_count FROM classes LIMIT 30"
        ) ?: [];
      }

      return array_map(function ($s) {
        return [
          'name'        => $s['subject_name'],
          'class_count' => (int) $s['class_count'],
        ];
      }, $subjects);
    } catch (\Throwable $e) {
      return [];
    }
  }

  /**
   * Model academic levels.
   */
  private static function modelAcademicLevels(): array
  {
    try {
      $levels = db()->fetchAll(
        "SELECT c.class_name, COUNT(DISTINCT ce.student_id) as student_count
         FROM classes c
         LEFT JOIN class_enrollments ce ON c.id = ce.class_id
         GROUP BY c.id, c.class_name
         ORDER BY c.class_name"
      ) ?: [];

      return array_map(function ($l) {
        return [
          'name'          => $l['class_name'],
          'student_count' => (int) $l['student_count'],
        ];
      }, $levels);
    } catch (\Throwable $e) {
      return [];
    }
  }

  /**
   * Model class-level detail with attendance metrics.
   */
  private static function modelClasses(): array
  {
    try {
      $classes = db()->fetchAll(
        "SELECT c.id, c.class_name,
                COUNT(DISTINCT ce.student_id) as enrolled,
                (SELECT COUNT(*) FROM attendance a
                 JOIN class_enrollments ce2 ON a.student_id = ce2.student_id AND ce2.class_id = c.id
                 WHERE a.status = 'present' AND a.date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                ) as recent_present,
                (SELECT COUNT(*) FROM attendance a
                 JOIN class_enrollments ce2 ON a.student_id = ce2.student_id AND ce2.class_id = c.id
                 WHERE a.date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                ) as recent_total
         FROM classes c
         LEFT JOIN class_enrollments ce ON c.id = ce.class_id
         GROUP BY c.id, c.class_name
         ORDER BY c.class_name
         LIMIT 50"
      ) ?: [];

      return array_map(function ($c) {
        $total = (int) $c['recent_total'];
        $present = (int) $c['recent_present'];
        $rate = $total > 0 ? round(($present / $total) * 100, 1) : 100;
        return [
          'id'              => (int) $c['id'],
          'name'            => $c['class_name'],
          'enrolled'        => (int) $c['enrolled'],
          'attendance_rate' => $rate,
          'period'          => '30d',
        ];
      }, $classes);
    } catch (\Throwable $e) {
      return [];
    }
  }

  /**
   * Model staff efficiency indicators.
   */
  private static function modelStaffEfficiency(): array
  {
    try {
      $teachers = db()->fetchAll(
        "SELECT u.id, u.full_name,
                (SELECT COUNT(DISTINCT DATE(a.date)) FROM attendance a WHERE a.marked_by = u.id AND a.date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) as active_days,
                (SELECT COUNT(*) FROM attendance a WHERE a.marked_by = u.id AND a.date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) as records_marked
         FROM users u
         WHERE u.role = 'teacher' AND u.status = 'active'
         ORDER BY active_days DESC
         LIMIT 30"
      ) ?: [];

      $avgDays = 0;
      if (!empty($teachers)) {
        $avgDays = round(array_sum(array_column($teachers, 'active_days')) / count($teachers), 1);
      }

      return [
        'total_teachers' => count($teachers),
        'avg_active_days' => $avgDays,
        'teachers' => array_map(function ($t) {
          return [
            'id'             => (int) $t['id'],
            'name'           => $t['full_name'],
            'active_days'    => (int) $t['active_days'],
            'records_marked' => (int) $t['records_marked'],
            'efficiency'     => (int) $t['active_days'] > 20 ? 'high' : ((int) $t['active_days'] > 10 ? 'medium' : 'low'),
          ];
        }, array_slice($teachers, 0, 15)),
      ];
    } catch (\Throwable $e) {
      return ['total_teachers' => 0, 'avg_active_days' => 0, 'teachers' => []];
    }
  }

  /**
   * Model student engagement.
   */
  private static function modelStudentEngagement(): array
  {
    try {
      // Overall attendance distribution
      $engagement = db()->fetchOne(
        "SELECT
           COUNT(DISTINCT student_id) as total_students,
           SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as total_present,
           SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as total_absent,
           COUNT(*) as total_records
         FROM attendance
         WHERE date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)"
      );

      $totalRecords = (int) ($engagement['total_records'] ?? 0);
      $totalPresent = (int) ($engagement['total_present'] ?? 0);
      $rate = $totalRecords > 0 ? round(($totalPresent / $totalRecords) * 100, 1) : 100;

      // At-risk students (>25% absence rate)
      $atRisk = db()->fetchAll(
        "SELECT student_id, COUNT(*) as total,
                SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absences
         FROM attendance
         WHERE date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
         GROUP BY student_id
         HAVING (absences / total) > 0.25
         ORDER BY absences DESC
         LIMIT 20"
      ) ?: [];

      return [
        'total_students'     => (int) ($engagement['total_students'] ?? 0),
        'overall_rate'       => $rate,
        'at_risk_count'      => count($atRisk),
        'total_absences_30d' => (int) ($engagement['total_absent'] ?? 0),
      ];
    } catch (\Throwable $e) {
      return ['total_students' => 0, 'overall_rate' => 100, 'at_risk_count' => 0, 'total_absences_30d' => 0];
    }
  }

  /**
   * Model operational stress indicators.
   */
  private static function modelOperationalStress(): array
  {
    $stress = [];

    // Memory pressure
    $memUsed = memory_get_usage(true);
    $memLimit = self::parseBytes(ini_get('memory_limit'));
    $memPct = $memLimit > 0 ? round(($memUsed / $memLimit) * 100) : 0;
    $stress['memory_pct'] = $memPct;

    // DB size estimate
    try {
      $dbSize = db()->fetchOne(
        "SELECT SUM(data_length + index_length) as total_size
         FROM information_schema.TABLES WHERE table_schema = DATABASE()"
      );
      $stress['db_size_mb'] = round(((float) ($dbSize['total_size'] ?? 0)) / 1048576, 1);
    } catch (\Throwable $e) {
      $stress['db_size_mb'] = 0;
    }

    // Recent error rate
    try {
      $errors = db()->fetchOne(
        "SELECT COUNT(*) as cnt FROM system_failures WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)"
      );
      $stress['errors_1h'] = (int) ($errors['cnt'] ?? 0);
    } catch (\Throwable $e) {
      $stress['errors_1h'] = 0;
    }

    // Active user load
    try {
      $activeUsers = db()->fetchOne(
        "SELECT COUNT(*) as cnt FROM activity_log WHERE created_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)"
      );
      $stress['active_users_15m'] = (int) ($activeUsers['cnt'] ?? 0);
    } catch (\Throwable $e) {
      $stress['active_users_15m'] = 0;
    }

    $stress['level'] = 'low';
    if ($memPct > 70 || $stress['errors_1h'] > 10) $stress['level'] = 'high';
    elseif ($memPct > 50 || $stress['errors_1h'] > 5) $stress['level'] = 'moderate';

    return $stress;
  }

  /**
   * Detect structural learning frictions.
   */
  private static function detectFrictions(array $model): array
  {
    $frictions = [];

    // Low attendance classes
    foreach ($model['classes'] as $c) {
      if ($c['attendance_rate'] < 70 && $c['enrolled'] > 0) {
        $frictions[] = [
          'type'   => 'low_attendance_class',
          'detail' => "{$c['name']}: {$c['attendance_rate']}% attendance with {$c['enrolled']} students",
          'severity' => $c['attendance_rate'] < 50 ? 'critical' : 'high',
          'class_id' => $c['id'],
        ];
      }
    }

    // Staff efficiency gaps
    $staff = $model['staff'];
    if (!empty($staff['teachers'])) {
      foreach ($staff['teachers'] as $t) {
        if ($t['efficiency'] === 'low' && $t['active_days'] < 5) {
          $frictions[] = [
            'type'   => 'teacher_inactivity',
            'detail' => "{$t['name']}: only {$t['active_days']} active days in 30d",
            'severity' => 'medium',
          ];
        }
      }
    }

    // High at-risk student count
    $students = $model['students'];
    if ($students['at_risk_count'] > 10) {
      $frictions[] = [
        'type'   => 'high_at_risk_population',
        'detail' => "{$students['at_risk_count']} students with >25% absence rate",
        'severity' => $students['at_risk_count'] > 30 ? 'critical' : 'high',
      ];
    }

    // Operational stress
    if ($model['operations']['level'] === 'high') {
      $frictions[] = [
        'type'   => 'operational_stress',
        'detail' => "Memory: {$model['operations']['memory_pct']}%, Errors: {$model['operations']['errors_1h']}/h",
        'severity' => 'high',
      ];
    }

    return $frictions;
  }

  /**
   * Persist model snapshot to file.
   */
  private static function persistSnapshot(array $model): void
  {
    $path = dirname(__DIR__, 2) . '/storage/institutional-model.json';
    $dir = dirname($path);
    if (!is_dir($dir)) {
      mkdir($dir, 0755, true);
    }
    file_put_contents($path, json_encode($model, JSON_PRETTY_PRINT));
  }

  /**
   * Get last model snapshot.
   */
  public static function getLastSnapshot(): ?array
  {
    $path = dirname(__DIR__, 2) . '/storage/institutional-model.json';
    if (!is_file($path)) return null;
    $data = json_decode(file_get_contents($path), true);
    return is_array($data) ? $data : null;
  }

  /**
   * Summary for dashboard.
   */
  public static function getSummary(): array
  {
    $snapshot = self::getLastSnapshot();
    if (!$snapshot) {
      $snapshot = self::build();
    }

    return [
      'class_count'     => count($snapshot['classes'] ?? []),
      'friction_count'  => count($snapshot['frictions'] ?? []),
      'frictions'       => array_slice($snapshot['frictions'] ?? [], 0, 10),
      'staff_summary'   => $snapshot['staff'] ?? [],
      'student_summary' => $snapshot['students'] ?? [],
      'operations'      => $snapshot['operations'] ?? [],
      'built_at'        => $snapshot['built_at'] ?? null,
    ];
  }

  /**
   * Parse memory limit string to bytes.
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
}
