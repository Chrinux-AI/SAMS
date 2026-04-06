<?php

/**
 * Validation & Safety Layer
 * Schema validation + business rules + rate limiting + anomaly hooks.
 */
class AICopilotValidationService
{
  /**
   * @param array<string,mixed> $intentPacket
   * @return array<string,mixed>
   */
  public function validate(array $intentPacket): array
  {
    $intent = (string)($intentPacket['intent'] ?? '');
    $params = is_array($intentPacket['parameters'] ?? null) ? $intentPacket['parameters'] : [];
    $meta = AICopilotActionRegistry::get($intent);

    if (!$meta) {
      return $this->fail('Intent is not in action registry.', ['intent']);
    }

    $schema = $meta['schema'] ?? ['required' => [], 'types' => []];
    $required = $schema['required'] ?? [];
    $types = $schema['types'] ?? [];

    $errors = [];

    foreach ($required as $field) {
      if (!array_key_exists($field, $params) || $params[$field] === '' || $params[$field] === null) {
        $errors[] = "Missing required field: {$field}";
      }
    }

    foreach ($types as $field => $type) {
      if (!array_key_exists($field, $params)) {
        continue;
      }
      $value = $params[$field];
      if (!$this->matchType($value, $type)) {
        $errors[] = "Invalid type for {$field}. Expected {$type}.";
      }
    }

    // Business rules
    if ($intent === 'MARK_ATTENDANCE' && isset($params['status'])) {
      $allowed = ['present', 'absent', 'late', 'excused'];
      if (!in_array(strtolower((string)$params['status']), $allowed, true)) {
        $errors[] = 'Attendance status must be one of: present, absent, late, excused.';
      }
    }

    if ($intent === 'CREATE_CLASS' && isset($params['teacher_id'])) {
      $teacherId = (int)$params['teacher_id'];
      if ($teacherId > 0 && table_exists('users')) {
        $tenantId = (int)($_SESSION['tenant_id'] ?? 0);
        if ($tenantId > 0 && table_has_column('users', 'tenant_id')) {
          $teacher = db()->fetchOne(
            "SELECT id, role, tenant_id FROM users WHERE id = ? LIMIT 1",
            [$teacherId]
          );
          if (!$teacher) {
            $errors[] = 'Teacher not found.';
          } elseif ((string)($teacher['role'] ?? '') !== 'teacher') {
            $errors[] = 'Selected teacher_id does not belong to a teacher account.';
          } elseif ((int)($teacher['tenant_id'] ?? 0) !== $tenantId) {
            $errors[] = 'Teacher belongs to another tenant.';
          }
        }
      }
    }

    // Rate limiting per user for action execution
    $userId = (string)($_SESSION['user_id'] ?? '0');
    $limit = rate_limiter()->check('ai_action_user', $userId, 20, 60);
    if (!$limit['allowed']) {
      return [
        'ok' => false,
        'message' => 'Rate limit reached for AI actions.',
        'issues' => ['retry_after' => (int)$limit['retry_after']],
        'status' => 429
      ];
    }

    // Basic anomaly detection hook
    if ($this->isPotentialAbuse($intentPacket)) {
      $errors[] = 'Action flagged by anomaly guard; please reduce operation scope.';
    }

    if (!empty($errors)) {
      return [
        'ok' => false,
        'message' => 'Validation failed.',
        'issues' => $errors,
        'status' => 422
      ];
    }

    rate_limiter()->record('ai_action_user', $userId);

    return ['ok' => true, 'message' => 'Validation passed.', 'issues' => [], 'status' => 200];
  }

  private function matchType($value, string $type): bool
  {
    if ($type === 'string') {
      return is_string($value) && trim($value) !== '';
    }
    if ($type === 'int') {
      return is_int($value) || (is_string($value) && preg_match('/^-?[0-9]+$/', $value));
    }
    if ($type === 'date') {
      return is_string($value) && strtotime($value) !== false;
    }
    return true;
  }

  /**
   * @param array<string,mixed> $intentPacket
   */
  private function isPotentialAbuse(array $intentPacket): bool
  {
    $params = is_array($intentPacket['parameters'] ?? null) ? $intentPacket['parameters'] : [];
    if (isset($params['bulk_count']) && (int)$params['bulk_count'] > 100) {
      return true;
    }
    return false;
  }

  /**
   * @param string[] $issues
   */
  private function fail(string $message, array $issues): array
  {
    return ['ok' => false, 'message' => $message, 'issues' => $issues, 'status' => 422];
  }
}
