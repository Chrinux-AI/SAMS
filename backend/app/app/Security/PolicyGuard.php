<?php

/**
 * PolicyGuard — Row-Level Security (Data Access Guard).
 *
 * Ensures users only access permitted records:
 *   Teacher → ONLY assigned students/classes
 *   Parent  → ONLY child data
 *   Student → ONLY own profile
 *   Admin   → All records
 *
 * Execute before every data query on sensitive models.
 */
class PolicyGuard
{
  /**
   * Apply row-level filter to a query.
   * Returns a WHERE clause fragment and parameters to restrict data access.
   *
   * @param string   $model  The data model/table being accessed
   * @param int      $userId The current user ID
   * @param string   $role   The current user's role
   * @return array{where: string, params: array}
   */
  public static function filter(string $model, int $userId, string $role): array
  {
    // Admin has unrestricted access
    if ($role === 'admin') {
      return ['where' => '1=1', 'params' => []];
    }

    $model = strtolower($model);

    switch ($model) {
      case 'students':
      case 'student':
        return self::filterStudents($userId, $role);

      case 'attendance':
        return self::filterAttendance($userId, $role);

      case 'grades':
      case 'results':
        return self::filterGrades($userId, $role);

      case 'classes':
      case 'class':
        return self::filterClasses($userId, $role);

      case 'users':
      case 'user':
        return self::filterUsers($userId, $role);

      case 'notices':
      case 'notice':
        return self::filterNotices($userId, $role);

      case 'messages':
      case 'conversations':
        return self::filterMessages($userId, $role);

      default:
        // Default: restrict to own records
        return ['where' => 'user_id = :policy_uid', 'params' => ['policy_uid' => $userId]];
    }
  }

  /**
   * Check if a user has access to a specific record.
   *
   * @return bool
   */
  public static function canAccess(string $model, int $recordId, int $userId, string $role): bool
  {
    if ($role === 'admin') {
      return true;
    }

    $filter = self::filter($model, $userId, $role);
    $model = strtolower($model);
    $tableName = self::resolveTable($model);
    if (!$tableName) {
      return false;
    }

    try {
      $params = array_merge($filter['params'], ['record_id' => $recordId]);
      $row = db()->fetchOne(
        "SELECT id FROM `{$tableName}` WHERE id = :record_id AND ({$filter['where']}) LIMIT 1",
        $params
      );
      return $row !== false;
    } catch (\Throwable $e) {
      error_log("PolicyGuard canAccess error: " . $e->getMessage());
      return false;
    }
  }

  /**
   * Enforce access — denies with 403 if user cannot access record.
   */
  public static function enforce(string $model, int $recordId, int $userId, string $role): void
  {
    if (!self::canAccess($model, $recordId, $userId, $role)) {
      AuditLogger::logSecurity(
        "PolicyGuard denied: user #{$userId} ({$role}) tried to access {$model} #{$recordId}"
      );
      http_response_code(403);
      if (self::isApi()) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Access denied']);
      } else {
        echo '<!DOCTYPE html><html><body><h1>403 Forbidden</h1><p>You do not have access to this resource.</p></body></html>';
      }
      exit;
    }
  }

    // ─────── Model-specific filters ───────

  /**
   * Students: teacher sees assigned, parent sees children, student sees self.
   */
  private static function filterStudents(int $userId, string $role): array
  {
    switch ($role) {
      case 'teacher':
        return [
          'where' => 'id IN (SELECT student_id FROM class_enrollments WHERE class_id IN (SELECT id FROM classes WHERE teacher_id = :policy_uid))',
          'params' => ['policy_uid' => $userId],
        ];
      case 'parent':
        return [
          'where' => 'id IN (SELECT student_id FROM parent_student WHERE parent_id = :policy_uid)',
          'params' => ['policy_uid' => $userId],
        ];
      case 'student':
        return [
          'where' => 'user_id = :policy_uid',
          'params' => ['policy_uid' => $userId],
        ];
      default:
        return ['where' => '0=1', 'params' => []]; // Deny all
    }
  }

  /**
   * Attendance: scoped by student access rules.
   */
  private static function filterAttendance(int $userId, string $role): array
  {
    switch ($role) {
      case 'teacher':
        return [
          'where' => 'class_id IN (SELECT id FROM classes WHERE teacher_id = :policy_uid)',
          'params' => ['policy_uid' => $userId],
        ];
      case 'parent':
        return [
          'where' => 'student_id IN (SELECT student_id FROM parent_student WHERE parent_id = :policy_uid)',
          'params' => ['policy_uid' => $userId],
        ];
      case 'student':
        return [
          'where' => 'student_id IN (SELECT id FROM students WHERE user_id = :policy_uid)',
          'params' => ['policy_uid' => $userId],
        ];
      default:
        return ['where' => '0=1', 'params' => []];
    }
  }

  /**
   * Grades: same scope as attendance.
   */
  private static function filterGrades(int $userId, string $role): array
  {
    return self::filterAttendance($userId, $role);
  }

  /**
   * Classes: teacher sees own, students/parents see enrolled.
   */
  private static function filterClasses(int $userId, string $role): array
  {
    switch ($role) {
      case 'teacher':
        return [
          'where' => 'teacher_id = :policy_uid',
          'params' => ['policy_uid' => $userId],
        ];
      case 'student':
        return [
          'where' => 'id IN (SELECT class_id FROM class_enrollments WHERE student_id IN (SELECT id FROM students WHERE user_id = :policy_uid))',
          'params' => ['policy_uid' => $userId],
        ];
      case 'parent':
        return [
          'where' => 'id IN (SELECT class_id FROM class_enrollments WHERE student_id IN (SELECT student_id FROM parent_student WHERE parent_id = :policy_uid))',
          'params' => ['policy_uid' => $userId],
        ];
      default:
        return ['where' => '0=1', 'params' => []];
    }
  }

  /**
   * Users: everyone sees only themselves (non-admin).
   */
  private static function filterUsers(int $userId, string $role): array
  {
    return ['where' => 'id = :policy_uid', 'params' => ['policy_uid' => $userId]];
  }

  /**
   * Notices: filter by visibility/role.
   */
  private static function filterNotices(int $userId, string $role): array
  {
    return [
      'where' => "(visibility = 'public' OR visibility = 'all_roles' OR target_role = :policy_role OR target_role IS NULL)",
      'params' => ['policy_role' => $role],
    ];
  }

  /**
   * Messages: only own conversations.
   */
  private static function filterMessages(int $userId, string $role): array
  {
    return [
      'where' => 'conversation_id IN (SELECT conversation_id FROM conversation_participants WHERE user_id = :policy_uid)',
      'params' => ['policy_uid' => $userId],
    ];
  }

  // ─────── Helpers ───────

  private static function resolveTable(string $model): ?string
  {
    $map = [
      'student' => 'students',
      'students' => 'students',
      'user' => 'users',
      'users' => 'users',
      'class' => 'classes',
      'classes' => 'classes',
      'attendance' => 'attendance',
      'grade' => 'grades',
      'grades' => 'grades',
      'results' => 'grades',
      'notice' => 'notices',
      'notices' => 'notices',
      'message' => 'conversation_messages',
      'messages' => 'conversation_messages',
      'conversation' => 'conversations',
      'conversations' => 'conversations',
    ];
    $table = $map[$model] ?? null;
    if ($table !== null && !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]{0,63}$/', $table)) {
      return null;
    }
    return $table;
  }

  private static function isApi(): bool
  {
    return str_contains($_SERVER['REQUEST_URI'] ?? '', '/api/') ||
      ($_SERVER['HTTP_ACCEPT'] ?? '') === 'application/json' ||
      !empty($_SERVER['HTTP_X_REQUESTED_WITH']);
  }
}
