<?php

/**
 * EthicalGuard — AI Safety & Control System
 *
 * Ensures the cognitive system never:
 *   - Exposes sensitive data
 *   - Overrides human authority
 *   - Makes irreversible decisions automatically
 *   - Biases decisions across roles
 *
 * Principle: AI recommends. Humans approve. System executes safely.
 */
class EthicalGuard
{
  /** Actions that always require human approval */
  private static array $humanApprovalRequired = [
    'delete_student',
    'modify_grades',
    'expel_student',
    'terminate_teacher',
    'change_policy',
    'data_export',
    'system_override',
    'role_change',
    'bulk_modify',
    'schema_change',
  ];

  /** Keywords that indicate destructive intent */
  private static array $destructiveKeywords = [
    'DELETE',
    'DROP',
    'TRUNCATE',
    'OVERWRITE',
    'PURGE',
    'DESTROY',
    'WIPE',
    'RESET_ALL',
  ];

  /** Sensitive data fields that must never be exposed in AI outputs */
  private static array $sensitiveFields = [
    'password',
    'password_hash',
    'token',
    'secret',
    'api_key',
    'ssn',
    'national_id',
    'bank_account',
    'credit_card',
    'medical_record',
    'otp',
    'reset_token',
  ];

  /**
   * Validate whether an action is ethically permissible.
   *
   * @return array ['allowed' => bool, 'reason' => string, 'requires_approval' => bool]
   */
  public static function validate(string $action, array $context = []): array
  {
    // Check destructive keywords
    $upper = strtoupper($action);
    foreach (self::$destructiveKeywords as $kw) {
      if (str_contains($upper, $kw)) {
        self::logViolation('destructive_blocked', $action, $context);
        return [
          'allowed'           => false,
          'reason'            => "Destructive action blocked: contains '{$kw}'",
          'requires_approval' => true,
        ];
      }
    }

    // Check if human approval is required
    foreach (self::$humanApprovalRequired as $restricted) {
      if (str_contains(strtolower($action), $restricted)) {
        self::logViolation('approval_required', $action, $context);
        return [
          'allowed'           => false,
          'reason'            => "Action '{$restricted}' requires human approval",
          'requires_approval' => true,
        ];
      }
    }

    // Check for bias indicators
    $biasCheck = self::checkBias($context);
    if (!$biasCheck['passed']) {
      self::logViolation('bias_detected', $action, $context);
      return [
        'allowed'           => false,
        'reason'            => 'Potential bias detected: ' . $biasCheck['detail'],
        'requires_approval' => true,
      ];
    }

    // Check data exposure risk
    $exposureCheck = self::checkDataExposure($context);
    if (!$exposureCheck['safe']) {
      self::logViolation('data_exposure', $action, $context);
      return [
        'allowed'           => false,
        'reason'            => 'Sensitive data exposure risk: ' . $exposureCheck['detail'],
        'requires_approval' => false,
      ];
    }

    return [
      'allowed'           => true,
      'reason'            => 'Action passes ethical review',
      'requires_approval' => false,
    ];
  }

  /**
   * Sanitize AI output to remove sensitive data.
   */
  public static function sanitizeOutput(array $data): array
  {
    array_walk_recursive($data, function (&$value, $key) {
      if (is_string($key)) {
        $lower = strtolower($key);
        foreach (self::$sensitiveFields as $field) {
          if (str_contains($lower, $field)) {
            $value = '[REDACTED]';
            return;
          }
        }
      }
    });
    return $data;
  }

  /**
   * Check for role bias in decision context.
   */
  private static function checkBias(array $context): array
  {
    // Check if a decision disproportionately affects one role
    if (isset($context['target_role']) && isset($context['action_severity'])) {
      $severity = (float) ($context['action_severity'] ?? 0);
      $targetRole = $context['target_role'] ?? '';

      // High-severity actions targeting specific vulnerable roles need review
      if ($severity > 0.8 && in_array($targetRole, ['student', 'parent'])) {
        return [
          'passed' => false,
          'detail' => "High-severity action ({$severity}) targeting vulnerable role '{$targetRole}'",
        ];
      }
    }

    // Check for repetitive targeting of specific users
    if (isset($context['target_user_id'])) {
      try {
        $recentActions = db()->fetchOne(
          "SELECT COUNT(*) as cnt FROM institution_memory
           WHERE subject = ? AND category = 'cognitive_action' AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)",
          ['user_' . $context['target_user_id']]
        );
        if (((int) ($recentActions['cnt'] ?? 0)) >= 10) {
          return [
            'passed' => false,
            'detail' => 'Excessive automated actions against single user in 24h',
          ];
        }
      } catch (\Throwable $e) {
        // Table may not exist yet — allow
      }
    }

    return ['passed' => true, 'detail' => ''];
  }

  /**
   * Check if context could expose sensitive data.
   */
  private static function checkDataExposure(array $context): array
  {
    $contextStr = strtolower(json_encode($context));
    foreach (self::$sensitiveFields as $field) {
      if (str_contains($contextStr, $field) && !str_contains($contextStr, 'redacted')) {
        return [
          'safe'   => false,
          'detail' => "Context contains reference to sensitive field: {$field}",
        ];
      }
    }
    return ['safe' => true, 'detail' => ''];
  }

  /**
   * Log an ethical violation.
   */
  private static function logViolation(string $type, string $action, array $context): void
  {
    ErrorCollector::log('cognitive', "Ethical guard: {$type} — {$action}", 'HIGH');

    try {
      InstitutionalMemory::record(
        'ethical_guard',
        $type,
        $action,
        json_encode(array_slice($context, 0, 5)),
        'blocked',
        1.0,
        0.0
      );
    } catch (\Throwable $e) {
      // Non-critical
    }
  }

  /**
   * Check if the system is operating within safe bounds.
   */
  public static function systemSafetyCheck(): array
  {
    $violations = [];

    // Check for excessive automated actions in last hour
    try {
      $autoActions = db()->fetchOne(
        "SELECT COUNT(*) as cnt FROM institution_memory
         WHERE category = 'cognitive_action' AND outcome = 'executed' AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)"
      );
      $count = (int) ($autoActions['cnt'] ?? 0);
      if ($count > 50) {
        $violations[] = [
          'type'   => 'excessive_automation',
          'detail' => "{$count} automated actions in last hour (limit: 50)",
          'severity' => 'high',
        ];
      }
    } catch (\Throwable $e) {
      // Table may not exist
    }

    // Check for blocked actions ratio
    try {
      $blocked = db()->fetchOne(
        "SELECT COUNT(*) as cnt FROM institution_memory
         WHERE category = 'ethical_guard' AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
      );
      $blockedCount = (int) ($blocked['cnt'] ?? 0);
      if ($blockedCount > 20) {
        $violations[] = [
          'type'   => 'high_block_rate',
          'detail' => "{$blockedCount} actions blocked in 24h — system may be misconfigured",
          'severity' => 'medium',
        ];
      }
    } catch (\Throwable $e) {
      // Table may not exist
    }

    return [
      'safe'       => empty($violations),
      'violations' => $violations,
      'checked_at' => date('Y-m-d H:i:s'),
    ];
  }

  /**
   * Summary for dashboard.
   */
  public static function getSummary(): array
  {
    $safety = self::systemSafetyCheck();

    $recentBlocks = [];
    try {
      $recentBlocks = InstitutionalMemory::recall('ethical_guard', null, 10);
    } catch (\Throwable $e) {
      // Table may not exist
    }

    return [
      'safety'        => $safety,
      'recent_blocks' => $recentBlocks,
      'rules_count'   => count(self::$humanApprovalRequired) + count(self::$destructiveKeywords),
    ];
  }
}
