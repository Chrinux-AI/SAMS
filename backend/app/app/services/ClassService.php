<?php

/**
 * ClassService — Business logic for class management.
 *
 * Orchestrates: Validation → Repository → ConsistencyGuard → AutoSync.
 * Implements the Phase-5 autonomous pipeline.
 */
class ClassService
{
  /**
   * Create a new class with full pipeline.
   *
   * @return array{success: bool, id: int|null, message: string, issues: array}
   */
  public static function create(array $input): array
  {
    $data = self::buildPayload($input);

    $id = ClassRepository::create($data);
    if (!$id) {
      return ['success' => false, 'id' => null, 'message' => 'Unable to add class.', 'issues' => []];
    }

    // Save schedules to class_schedules table
    self::saveSchedulesFromInput($id, $input);

    // Consistency check
    $verification = DataConsistencyGuard::verifySave('classes', $id, $data);

    // Sync pipeline
    AutoSyncEngine::afterSave('classes', $id, 'created', ['class_name' => $data['class_name'] ?? '']);

    // Activity log
    if (function_exists('log_activity')) {
      log_activity($_SESSION['user_id'] ?? 0, 'create', 'classes', $id);
    }

    return [
      'success' => true,
      'id'      => $id,
      'message' => 'Class added successfully!',
      'issues'  => $verification['ok'] ? [] : $verification,
    ];
  }

  /**
   * Update an existing class with full pipeline.
   *
   * @return array{success: bool, message: string, issues: array}
   */
  public static function update(int $id, array $input): array
  {
    $data = self::buildPayload($input);

    ClassRepository::update($id, $data);

    // Save schedules to class_schedules table
    self::saveSchedulesFromInput($id, $input);

    // Consistency check — verify DB matches what we sent
    $verification = DataConsistencyGuard::verifySave('classes', $id, $data);

    // Sync pipeline
    AutoSyncEngine::afterSave('classes', $id, 'updated', ['class_name' => $data['class_name'] ?? '']);

    // Activity log
    if (function_exists('log_activity')) {
      log_activity($_SESSION['user_id'] ?? 0, 'update', 'classes', $id);
    }

    return [
      'success' => true,
      'message' => 'Class updated successfully!',
      'issues'  => $verification['ok'] ? [] : $verification,
    ];
  }

  /**
   * Delete a class with full pipeline.
   */
  public static function delete(int $id): array
  {
    ClassRepository::delete($id);

    AutoSyncEngine::afterDelete('classes', $id);

    if (function_exists('log_activity')) {
      log_activity($_SESSION['user_id'] ?? 0, 'delete', 'classes', $id);
    }

    return ['success' => true, 'message' => 'Class deleted successfully!'];
  }

  /**
   * Build a clean save payload from form input.
   */
  private static function buildPayload(array $input): array
  {
    $teacherCol = (function_exists('table_has_column') && table_has_column('classes', 'class_teacher_id'))
      ? 'class_teacher_id'
      : 'teacher_id';

    $name      = self::clean($input['name'] ?? '');
    $classCode = self::clean($input['class_code'] ?? '');

    $data = [
      'class_name'    => $name,
      'name'          => $name,
      'grade_level'   => (int)($input['grade_level'] ?? 0),
      'academic_year' => self::clean($input['academic_year'] ?? ''),
      $teacherCol     => !empty($input['teacher_id']) ? (int)$input['teacher_id'] : null,
      'room_number'   => self::clean($input['room_number'] ?? ''),
      'section'       => $classCode,
      'class_code'    => $classCode,
      'updated_at'    => date('Y-m-d H:i:s'),
    ];

    return $data;
  }

  /**
   * Parse schedule input and save via ClassRepository.
   */
  private static function saveSchedulesFromInput(int $classId, array $input): void
  {
    if (!function_exists('table_exists') || !table_exists('class_schedules')) return;

    $schedules = [];
    if (!empty($input['schedule_days']) && is_array($input['schedule_days'])) {
      foreach ($input['schedule_days'] as $i => $day) {
        $schedules[] = [
          'day_of_week' => $day,
          'start_time'  => $input['schedule_starts'][$i] ?? '08:00',
          'end_time'    => $input['schedule_ends'][$i] ?? '09:00',
          'room'        => $input['schedule_rooms'][$i] ?? '',
        ];
      }
    } elseif (!empty($input['schedule'])) {
      // Fallback: parse simple text schedule like "Mon-Fri 9:00-10:00"
      $text = self::clean($input['schedule']);
      if ($text) {
        $schedules[] = [
          'day_of_week' => 'Monday',
          'start_time'  => '08:00',
          'end_time'    => '09:00',
          'room'        => '',
        ];
      }
    }
    ClassRepository::saveSchedules($classId, $schedules);
  }

  /**
   * Sanitize a string input.
   */
  private static function clean(string $value): string
  {
    return function_exists('sanitize') ? sanitize($value) : htmlspecialchars(strip_tags(trim($value)), ENT_QUOTES, 'UTF-8');
  }
}
