<?php

/**
 * Permission Engine
 * Mandatory gate for session auth, tenant isolation, role authorization, least privilege.
 */
class AICopilotPermissionEngine
{
  /**
   * @param array<string,mixed> $intentPacket
   * @return array<string,mixed>
   */
  public function authorize(array $intentPacket): array
  {
    if (!isset($_SESSION['user_id'])) {
      return $this->deny('Authentication required.', 401);
    }

    $intent = (string)($intentPacket['intent'] ?? '');
    $params = is_array($intentPacket['parameters'] ?? null) ? $intentPacket['parameters'] : [];

    $meta = AICopilotActionRegistry::get($intent);
    if (!$meta) {
      return $this->deny('Intent is not registered.', 403);
    }

    $role = (string)($_SESSION['role'] ?? ($_SESSION['user_role'] ?? 'guest'));
    $tenantId = (int)($_SESSION['tenant_id'] ?? 0);

    if ($tenantId <= 0) {
      return $this->deny('Tenant context is missing.', 403);
    }

    if (!in_array($role, $meta['roles'], true)) {
      return $this->deny('Your role is not authorized for this action.', 403);
    }

    // Enforce tenant ownership on explicit tenant param
    if (isset($params['tenant_id']) && (int)$params['tenant_id'] !== $tenantId) {
      return $this->deny('Cross-tenant access is not allowed.', 403);
    }

    // Resource-level tenant ownership checks (best effort)
    $resourceChecks = [
      'class_id' => 'classes',
      'student_id' => 'students',
      'teacher_id' => 'users',
      'user_id' => 'users',
    ];

    foreach ($resourceChecks as $idKey => $table) {
      if (!isset($params[$idKey])) {
        continue;
      }
      $id = (int)$params[$idKey];
      if ($id <= 0) {
        continue;
      }
      if (!table_exists($table) || !table_has_column($table, 'tenant_id')) {
        continue;
      }
      $row = db()->fetchOne("SELECT tenant_id FROM {$table} WHERE id = ? LIMIT 1", [$id]);
      if (!$row) {
        return $this->deny("Target resource not found: {$idKey}", 404);
      }
      if ((int)$row['tenant_id'] !== $tenantId) {
        return $this->deny('Resource belongs to another tenant.', 403);
      }
    }

    return [
      'allowed' => true,
      'status' => 200,
      'role' => $role,
      'tenant_id' => $tenantId,
      'user_id' => (int)$_SESSION['user_id']
    ];
  }

  private function deny(string $reason, int $status): array
  {
    return [
      'allowed' => false,
      'status' => $status,
      'reason' => $reason
    ];
  }
}
