<?php

/**
 * Secure API Gateway
 * Only executes registered intents through approved backend operations.
 */
class AICopilotSecureApiGateway
{
  /**
   * @param array<string,mixed> $intentPacket
   * @return array<string,mixed>
   */
  public function execute(array $intentPacket): array
  {
    $intent = (string)($intentPacket['intent'] ?? '');
    $params = is_array($intentPacket['parameters'] ?? null) ? $intentPacket['parameters'] : [];

    if ($intent === 'CREATE_CLASS') {
      return $this->createClass($params);
    }
    if ($intent === 'UPDATE_CLASS') {
      return $this->updateClass($params);
    }
    if ($intent === 'DELETE_CLASS') {
      return $this->deleteClass($params);
    }
    if ($intent === 'MARK_ATTENDANCE') {
      return $this->markAttendance($params);
    }
    if ($intent === 'SEND_NOTICE') {
      return $this->sendNotice($params);
    }

    return ['ok' => false, 'status' => 403, 'message' => 'Intent not executable.'];
  }

  /** @param array<string,mixed> $params */
  private function createClass(array $params): array
  {
    if (!table_exists('classes')) {
      return ['ok' => false, 'status' => 500, 'message' => 'Classes table is unavailable.'];
    }

    $payload = [
      'name' => trim((string)$params['name']),
      'grade_level' => trim((string)$params['grade_level']),
      'teacher_id' => isset($params['teacher_id']) ? (int)$params['teacher_id'] : null,
      'academic_year' => (string)($params['academic_year'] ?? date('Y')),
      'is_active' => 1,
      'created_at' => date('Y-m-d H:i:s'),
      'updated_at' => date('Y-m-d H:i:s')
    ];

    $id = insert_flexible('classes', $payload);
    if (!$id) {
      return ['ok' => false, 'status' => 500, 'message' => 'Failed to create class.'];
    }

    return ['ok' => true, 'status' => 201, 'message' => 'Class created successfully.', 'data' => ['class_id' => (int)$id]];
  }

  /** @param array<string,mixed> $params */
  private function updateClass(array $params): array
  {
    if (!table_exists('classes')) {
      return ['ok' => false, 'status' => 500, 'message' => 'Classes table is unavailable.'];
    }

    $classId = (int)($params['class_id'] ?? 0);
    if ($classId <= 0) {
      return ['ok' => false, 'status' => 422, 'message' => 'class_id is required.'];
    }

    $payload = [
      'name' => $params['name'] ?? null,
      'grade_level' => $params['grade_level'] ?? null,
      'teacher_id' => isset($params['teacher_id']) ? (int)$params['teacher_id'] : null,
      'academic_year' => $params['academic_year'] ?? null,
      'updated_at' => date('Y-m-d H:i:s')
    ];
    $payload = array_filter($payload, static fn($v) => $v !== null && $v !== '');

    if (empty($payload)) {
      return ['ok' => false, 'status' => 422, 'message' => 'No valid fields provided for update.'];
    }

    $ok = update_flexible('classes', $payload, 'id = ?', [$classId]);
    if (!$ok) {
      return ['ok' => false, 'status' => 500, 'message' => 'Failed to update class.'];
    }

    return ['ok' => true, 'status' => 200, 'message' => 'Class updated successfully.', 'data' => ['class_id' => $classId]];
  }

  /** @param array<string,mixed> $params */
  private function deleteClass(array $params): array
  {
    if (!table_exists('classes')) {
      return ['ok' => false, 'status' => 500, 'message' => 'Classes table is unavailable.'];
    }

    $classId = (int)($params['class_id'] ?? 0);
    if ($classId <= 0) {
      return ['ok' => false, 'status' => 422, 'message' => 'class_id is required.'];
    }

    // Soft delete where supported
    if (table_has_column('classes', 'is_active')) {
      $ok = update_flexible('classes', ['is_active' => 0, 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$classId]);
      if (!$ok) {
        return ['ok' => false, 'status' => 500, 'message' => 'Failed to archive class.'];
      }
      return ['ok' => true, 'status' => 200, 'message' => 'Class archived successfully.', 'data' => ['class_id' => $classId]];
    }

    $ok = db()->delete('classes', 'id = ?', [$classId]);
    if (!$ok) {
      return ['ok' => false, 'status' => 500, 'message' => 'Failed to delete class.'];
    }

    return ['ok' => true, 'status' => 200, 'message' => 'Class deleted successfully.', 'data' => ['class_id' => $classId]];
  }

  /** @param array<string,mixed> $params */
  private function markAttendance(array $params): array
  {
    if (!table_exists('attendance')) {
      return ['ok' => false, 'status' => 500, 'message' => 'Attendance table is unavailable.'];
    }

    $studentId = (int)($params['student_id'] ?? 0);
    $classId = (int)($params['class_id'] ?? 0);
    $status = strtolower((string)($params['status'] ?? ''));
    $date = (string)($params['date'] ?? date('Y-m-d'));

    $payload = [
      'student_id' => $studentId,
      'class_id' => $classId,
      'status' => $status,
      'date' => $date,
      'recorded_by' => (int)($_SESSION['user_id'] ?? 0),
      'created_at' => date('Y-m-d H:i:s'),
      'updated_at' => date('Y-m-d H:i:s')
    ];

    $id = insert_flexible('attendance', $payload);
    if (!$id) {
      return ['ok' => false, 'status' => 500, 'message' => 'Failed to record attendance.'];
    }

    return ['ok' => true, 'status' => 201, 'message' => 'Attendance recorded successfully.', 'data' => ['attendance_id' => (int)$id]];
  }

  /** @param array<string,mixed> $params */
  private function sendNotice(array $params): array
  {
    $table = table_exists('notices') ? 'notices' : (table_exists('announcements') ? 'announcements' : null);
    if (!$table) {
      return ['ok' => false, 'status' => 500, 'message' => 'Notice/announcement table is unavailable.'];
    }

    $payload = [
      'title' => trim((string)$params['title']),
      'content' => trim((string)($params['message'] ?? $params['title'] ?? '')),
      'audience' => (string)($params['audience'] ?? 'all'),
      'created_by' => (int)($_SESSION['user_id'] ?? 0),
      'created_at' => date('Y-m-d H:i:s'),
      'updated_at' => date('Y-m-d H:i:s')
    ];

    $id = insert_flexible($table, $payload);
    if (!$id) {
      return ['ok' => false, 'status' => 500, 'message' => 'Failed to publish notice.'];
    }

    return ['ok' => true, 'status' => 201, 'message' => 'Notice published successfully.', 'data' => ['notice_id' => (int)$id]];
  }
}
