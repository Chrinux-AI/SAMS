<?php

/**
 * ClassRepository — Data-access layer for the classes table.
 *
 * Every database read/write for classes goes through this class.
 * Handles the schedule column, teacher join, and enrollment count.
 */
class ClassRepository
{
  /**
   * Fetch all classes with teacher + enrollment data + schedules from class_schedules table.
   *
   * @return array
   */
  public static function all(): array
  {
    $teacherCol = self::teacherColumn();

    $sql = "SELECT c.*,
                       c.class_name AS name,
                       COALESCE(c.section, CONCAT('CLS-', c.id)) AS class_code,
                       c.{$teacherCol} AS teacher_id,
                       CONCAT(u.first_name, ' ', u.last_name) AS teacher_name,
                       u.email AS teacher_email";

    if (function_exists('table_exists') && table_exists('class_enrollments')) {
      $sql .= ", COUNT(DISTINCT ce.student_id) AS student_count";
    } else {
      $sql .= ", 0 AS student_count";
    }

    $sql .= " FROM classes c
                   LEFT JOIN users u ON c.{$teacherCol} = u.id";

    if (function_exists('table_exists') && table_exists('class_enrollments')) {
      $sql .= " LEFT JOIN class_enrollments ce ON c.id = ce.class_id";
    }

    $sql .= " GROUP BY c.id ORDER BY c.grade_level, c.class_name";

    $classes = db()->fetchAll($sql);

    // Attach schedules from class_schedules table
    if (function_exists('table_exists') && table_exists('class_schedules')) {
      foreach ($classes as &$class) {
        $class['schedules'] = self::getSchedules((int)$class['id']);
        $class['schedule_display'] = self::formatScheduleDisplay($class['schedules']);
      }
      unset($class);
    }

    return $classes;
  }

  /**
   * Fetch a single class by id.
   */
  public static function find(int $id): ?array
  {
    $teacherCol = self::teacherColumn();

    $sql = "SELECT c.*,
                       c.class_name AS name,
                       COALESCE(c.section, CONCAT('CLS-', c.id)) AS class_code,
                       c.{$teacherCol} AS teacher_id,
                       CONCAT(u.first_name, ' ', u.last_name) AS teacher_name
                FROM classes c
                LEFT JOIN users u ON c.{$teacherCol} = u.id
                WHERE c.id = ?";

    return db()->fetchOne($sql, [$id]);
  }

  /**
   * Create a new class.
   *
   * @return int|false The new id or false on failure
   */
  public static function create(array $data)
  {
    if (function_exists('insert_flexible')) {
      return insert_flexible('classes', $data);
    }
    return db()->insert('classes', $data);
  }

  /**
   * Update a class by id.
   */
  public static function update(int $id, array $data): bool
  {
    $data['updated_at'] = date('Y-m-d H:i:s');

    if (function_exists('update_flexible')) {
      return (bool)update_flexible('classes', $data, 'id = ?', [$id]);
    }
    return (bool)db()->update('classes', $data, 'id = ?', [$id]);
  }

  /**
   * Delete a class by id.
   */
  public static function delete(int $id): bool
  {
    return (bool)db()->delete('classes', 'id = ?', [$id]);
  }

  /**
   * Fetch schedules for a class from the class_schedules table.
   */
  public static function getSchedules(int $classId): array
  {
    return db()->fetchAll(
      "SELECT * FROM class_schedules WHERE class_id = ? ORDER BY FIELD(day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'), start_time",
      [$classId]
    );
  }

  /**
   * Save schedules for a class (replace all existing).
   */
  public static function saveSchedules(int $classId, array $schedules): void
  {
    db()->query("DELETE FROM class_schedules WHERE class_id = ?", [$classId]);
    foreach ($schedules as $s) {
      $day = $s['day_of_week'] ?? '';
      $start = $s['start_time'] ?? '';
      $end = $s['end_time'] ?? '';
      $room = $s['room'] ?? '';
      if ($day && $start && $end) {
        db()->query(
          "INSERT INTO class_schedules (class_id, day_of_week, start_time, end_time, room) VALUES (?, ?, ?, ?, ?)",
          [$classId, $day, $start, $end, $room]
        );
      }
    }
  }

  /**
   * Format schedules into a readable string.
   */
  public static function formatScheduleDisplay(array $schedules): string
  {
    if (empty($schedules)) return 'No schedule assigned';
    $parts = [];
    foreach ($schedules as $s) {
      $day = substr($s['day_of_week'], 0, 3);
      $start = date('g:ia', strtotime($s['start_time']));
      $end = date('g:ia', strtotime($s['end_time']));
      $room = !empty($s['room']) ? " ({$s['room']})" : '';
      $parts[] = "{$day} {$start}-{$end}{$room}";
    }
    return implode(', ', $parts);
  }

  /**
   * Detect the teacher foreign-key column name (varies between installs).
   */
  private static function teacherColumn(): string
  {
    if (function_exists('table_has_column') && table_has_column('classes', 'class_teacher_id')) {
      return 'class_teacher_id';
    }
    return 'teacher_id';
  }
}
