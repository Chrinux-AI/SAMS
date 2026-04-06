<?php

/**
 * AcademicRuntime — Academic Lifecycle Engine
 *
 * Manages class sessions, attendance tracking status, term/semester awareness,
 * grading periods, promotion logic readiness.
 */
class AcademicRuntime
{
  /**
   * Get the current academic runtime status.
   */
  public static function status(): array
  {
    $period = InstitutionalState::academicPeriod();

    return [
      'period'            => $period,
      'attendance_open'   => self::isAttendanceOpen(),
      'classes_summary'   => self::getClassesSummary(),
      'today_stats'       => self::getTodayStats(),
    ];
  }

  /**
   * Check if attendance recording is currently open.
   */
  public static function isAttendanceOpen(): bool
  {
    $period = InstitutionalState::academicPeriod();
    return $period['is_school_day'] && $period['is_school_hour'];
  }

  /**
   * Get summary of all classes.
   */
  public static function getClassesSummary(): array
  {
    try {
      $classes = db()->fetchAll(
        "SELECT c.id, c.class_name, c.grade_level,
                (SELECT COUNT(*) FROM users u WHERE u.role = 'student') as total_students
         FROM classes c
         ORDER BY c.class_name
         LIMIT 50"
      );
      return [
        'total'   => count($classes),
        'classes' => $classes,
      ];
    } catch (\Throwable $e) {
      return ['total' => 0, 'classes' => []];
    }
  }

  /**
   * Get today's attendance stats.
   */
  public static function getTodayStats(): array
  {
    return InstitutionalState::attendanceState();
  }

  /**
   * Record attendance for a student.
   */
  public static function recordAttendance(int $studentId, string $status, int $classId, ?int $recordedBy = null): array
  {
    $validStatuses = defined('ATTENDANCE_STATUSES') ? ATTENDANCE_STATUSES : ['present', 'absent', 'late', 'excused'];
    if (!in_array($status, $validStatuses, true)) {
      return ['success' => false, 'error' => 'Invalid status'];
    }

    try {
      $today = date('Y-m-d');

      // Check if already recorded
      $existing = db()->fetchOne(
        "SELECT id FROM attendance WHERE student_id = ? AND date = ? AND class_id = ?",
        [$studentId, $today, $classId]
      );

      if ($existing) {
        db()->update('attendance', [
          'status'     => $status,
          'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$existing['id']]);
      } else {
        db()->insert('attendance', [
          'student_id'  => $studentId,
          'class_id'    => $classId,
          'date'        => $today,
          'status'      => $status,
          'recorded_by' => $recordedBy,
          'created_at'  => date('Y-m-d H:i:s'),
        ]);
      }

      EventBus::dispatch('academic', 'attendance_recorded', [
        'student_id' => $studentId,
        'status'     => $status,
        'class_id'   => $classId,
      ]);

      // Trigger automation on absence
      if ($status === 'absent') {
        EventBus::dispatch('automation', 'student_absent', [
          'student_id' => $studentId,
          'class_id'   => $classId,
          'date'       => $today,
        ]);
      }

      return ['success' => true];
    } catch (\Throwable $e) {
      ErrorCollector::log('academic_runtime', 'Attendance record failed: ' . $e->getMessage(), 'MEDIUM');
      return ['success' => false, 'error' => $e->getMessage()];
    }
  }

  /**
   * Get attendance report for a class on a date.
   */
  public static function getClassAttendance(int $classId, string $date = ''): array
  {
    $date = $date ?: date('Y-m-d');
    try {
      return db()->fetchAll(
        "SELECT a.*, u.first_name, u.last_name, u.username
         FROM attendance a
         JOIN users u ON u.id = a.student_id
         WHERE a.class_id = ? AND a.date = ?
         ORDER BY u.last_name, u.first_name",
        [$classId, $date]
      );
    } catch (\Throwable $e) {
      return [];
    }
  }
}
