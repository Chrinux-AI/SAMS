<?php

/**
 * Audit Logging Service
 * Immutable append-only logs for every AI-triggered action.
 */
class AICopilotAuditLoggingService
{
  public function __construct()
  {
    $this->ensureTable();
  }

  /**
   * @param array<string,mixed> $entry
   */
  public function log(array $entry): void
  {
    $payload = [
      'tenant_id' => (int)($entry['tenant_id'] ?? 0),
      'user_id' => (int)($entry['user_id'] ?? 0),
      'role' => (string)($entry['role'] ?? 'unknown'),
      'intent' => (string)($entry['intent'] ?? 'UNKNOWN'),
      'parameters_json' => json_encode($this->sanitizeParameters((array)($entry['parameters'] ?? []))),
      'status' => (string)($entry['status'] ?? 'unknown'),
      'approval_confirmed' => !empty($entry['approval_confirmed']) ? 1 : 0,
      'message' => (string)($entry['message'] ?? ''),
      'created_at' => date('Y-m-d H:i:s')
    ];

    db()->insert('ai_copilot_audit_logs', $payload);
  }

  private function ensureTable(): void
  {
    db()->query("CREATE TABLE IF NOT EXISTS ai_copilot_audit_logs (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT NOT NULL,
            user_id INT NOT NULL,
            role VARCHAR(50) NOT NULL,
            intent VARCHAR(100) NOT NULL,
            parameters_json LONGTEXT NOT NULL,
            status VARCHAR(40) NOT NULL,
            approval_confirmed TINYINT(1) NOT NULL DEFAULT 0,
            message TEXT NULL,
            created_at DATETIME NOT NULL,
            INDEX idx_tenant_created (tenant_id, created_at),
            INDEX idx_user_created (user_id, created_at),
            INDEX idx_intent_created (intent, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  }

  /**
   * @param array<string,mixed> $parameters
   * @return array<string,mixed>
   */
  private function sanitizeParameters(array $parameters): array
  {
    $sensitive = ['password', 'token', 'secret', 'otp'];
    $out = [];
    foreach ($parameters as $k => $v) {
      $key = (string)$k;
      if (in_array(strtolower($key), $sensitive, true)) {
        $out[$key] = '[REDACTED]';
        continue;
      }
      if (is_scalar($v) || $v === null) {
        $out[$key] = $v;
      } else {
        $out[$key] = json_encode($v);
      }
    }
    return $out;
  }
}
