<?php

/**
 * Prompt Firewall — Protects ALL AI assistants from adversarial prompts.
 *
 * Blocks attempts like:
 *   "show database schema"
 *   "ignore previous instructions"
 *   "act as admin"
 *   system probing questions
 *
 * Pipeline: User Prompt → Prompt Firewall → Sanitized Prompt → AI Engine
 */
class PromptFirewall
{
  // Severity levels for detected threats
  public const SEVERITY_LOW      = 'low';
  public const SEVERITY_MEDIUM   = 'medium';
  public const SEVERITY_HIGH     = 'high';
  public const SEVERITY_CRITICAL = 'critical';

  // Injection patterns — ordered by severity
  private static array $patterns = [
    // Critical: Direct system manipulation
    ['pattern' => '/ignore\s+(all\s+)?(previous|prior|above)\s+(instructions|prompts|rules)/i', 'severity' => 'critical', 'category' => 'instruction_override'],
    ['pattern' => '/\byou\s+are\s+now\s+(a|an|the|my)\b/i', 'severity' => 'critical', 'category' => 'identity_hijack'],
    ['pattern' => '/\bact\s+as\s+(an?\s+)?(admin|root|superuser|system)/i', 'severity' => 'critical', 'category' => 'privilege_escalation'],
    ['pattern' => '/\bDAN\s+mode\b|\bDo\s+Anything\s+Now\b/i', 'severity' => 'critical', 'category' => 'jailbreak'],
    ['pattern' => '/\bjailbreak\b|\bunlock\s+restrictions\b/i', 'severity' => 'critical', 'category' => 'jailbreak'],
    ['pattern' => '/\bsystem\s*prompt\b|\boriginal\s+instructions\b/i', 'severity' => 'critical', 'category' => 'system_probe'],
    ['pattern' => '/\bpretend\s+(you|to\s+be|that)/i', 'severity' => 'critical', 'category' => 'identity_hijack'],

    // High: Data exfiltration / schema probing
    ['pattern' => '/show\s+(me\s+)?(the\s+)?(database|db)\s+(schema|structure|tables|columns)/i', 'severity' => 'high', 'category' => 'data_probe'],
    ['pattern' => '/\b(SELECT|INSERT|UPDATE|DELETE|DROP|ALTER|UNION)\s+(FROM|INTO|TABLE|ALL)\b/i', 'severity' => 'high', 'category' => 'sql_injection'],
    ['pattern' => '/\bINFORMATION_SCHEMA\b|\bSHOW\s+TABLES\b|\bDESCRIBE\s+\w+/i', 'severity' => 'high', 'category' => 'schema_probe'],
    ['pattern' => '/\b(password|secret|token|api.?key|credential)s?\s+(for|of|list|dump|show)/i', 'severity' => 'high', 'category' => 'credential_probe'],
    ['pattern' => '/\bbypass\s+(security|filter|restriction|authentication|authorization)/i', 'severity' => 'high', 'category' => 'bypass_attempt'],
    ['pattern' => '/\boverride\s+(instructions|rules|policy|restrictions|safety)/i', 'severity' => 'high', 'category' => 'override_attempt'],
    ['pattern' => '/\bexecute\s+(command|code|script|query|shell)/i', 'severity' => 'high', 'category' => 'code_execution'],
    ['pattern' => '/\bread\s+(file|directory|folder|config|\.env)/i', 'severity' => 'high', 'category' => 'file_access'],

    // Medium: Suspicious probing
    ['pattern' => '/what\s+(is|are)\s+(your|the)\s+(instructions|rules|constraints|limitations)/i', 'severity' => 'medium', 'category' => 'instruction_probe'],
    ['pattern' => '/\blist\s+(all\s+)?(users|students|teachers|admins|accounts|emails)/i', 'severity' => 'medium', 'category' => 'data_enumeration'],
    ['pattern' => '/\bhow\s+(do|can)\s+(i|you)\s+(hack|exploit|break|bypass)/i', 'severity' => 'medium', 'category' => 'exploit_inquiry'],
    ['pattern' => '/\b(admin|teacher|student)\s+password/i', 'severity' => 'medium', 'category' => 'credential_probe'],
    ['pattern' => '/\bserver\s+(ip|address|location|config)/i', 'severity' => 'medium', 'category' => 'infrastructure_probe'],
    ['pattern' => '/\b(\.env|config\.php|database\.php|\.htaccess)\b/i', 'severity' => 'medium', 'category' => 'file_probe'],

    // Low: Suspicious but may be legitimate
    ['pattern' => '/\bsource\s+code\b|\bgit\s+repo(sitory)?\b/i', 'severity' => 'low', 'category' => 'code_probe'],
    ['pattern' => '/\btell\s+me\s+everything\s+(you\s+)?know\b/i', 'severity' => 'low', 'category' => 'broad_extraction'],
  ];

  // Topics that should never be discussed by the AI
  private static array $forbiddenTopics = [
    'database credentials',
    'sql queries',
    'server configuration',
    'source code',
    'api keys',
    'authentication bypass',
    'other users passwords',
    'system architecture internals',
  ];

  /**
   * Scan a prompt and return the firewall decision.
   *
   * @return array{allowed: bool, sanitized: string, threats: array, severity: string|null}
   */
  public static function scan(string $prompt, int $userId = 0, string $role = 'guest'): array
  {
    $threats = [];
    $maxSeverity = null;

    // Check against all patterns
    foreach (self::$patterns as $rule) {
      if (preg_match($rule['pattern'], $prompt)) {
        $threats[] = [
          'category' => $rule['category'],
          'severity' => $rule['severity'],
          'pattern'  => $rule['pattern'],
        ];
        $maxSeverity = self::highestSeverity($maxSeverity, $rule['severity']);
      }
    }

    // Check forbidden topic mentions
    $lower = mb_strtolower($prompt);
    foreach (self::$forbiddenTopics as $topic) {
      if (str_contains($lower, $topic)) {
        $threats[] = [
          'category' => 'forbidden_topic',
          'severity' => 'medium',
          'topic'    => $topic,
        ];
        $maxSeverity = self::highestSeverity($maxSeverity, 'medium');
      }
    }

    // Log threats if found
    if (!empty($threats)) {
      self::logThreat($userId, $role, $prompt, $threats, $maxSeverity);
    }

    // Decision: block critical/high, allow low/medium with sanitization
    $blocked = in_array($maxSeverity, ['critical', 'high'], true);
    $sanitized = $blocked ? '' : self::sanitize($prompt);

    return [
      'allowed'   => !$blocked,
      'sanitized' => $sanitized,
      'threats'   => $threats,
      'severity'  => $maxSeverity,
    ];
  }

  /**
   * Quick check — returns true if prompt is safe.
   */
  public static function isSafe(string $prompt): bool
  {
    $result = self::scan($prompt);
    return $result['allowed'];
  }

  /**
   * Full pipeline: scan + sanitize + return safe prompt or rejection.
   */
  public static function filter(string $prompt, int $userId = 0, string $role = 'guest'): array
  {
    $result = self::scan($prompt, $userId, $role);

    if (!$result['allowed']) {
      return [
        'success'  => false,
        'prompt'   => '',
        'response' => 'Your message was blocked by our security system. Please rephrase your question within the scope of school-related topics.',
        'severity' => $result['severity'],
      ];
    }

    return [
      'success'  => true,
      'prompt'   => $result['sanitized'],
      'response' => null,
      'severity' => $result['severity'],
    ];
  }

  /**
   * Sanitize a prompt: neutralize potentially harmful content while preserving intent.
   */
  public static function sanitize(string $prompt): string
  {
    // Strip HTML/script tags
    $prompt = strip_tags($prompt);

    // Remove control characters
    $prompt = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $prompt);

    // Neutralize SQL-like fragments
    $prompt = preg_replace('/\b(SELECT|INSERT|UPDATE|DELETE|DROP|ALTER|UNION)\b/i', '[$1]', $prompt);

    // Limit length
    $prompt = mb_substr($prompt, 0, 2000);

    return trim($prompt);
  }

  /**
   * Get firewall statistics for the security dashboard.
   */
  public static function getStats(int $hours = 24): array
  {
    try {
      $since = date('Y-m-d H:i:s', strtotime("-{$hours} hours"));

      $total = (int) (db()->fetchOne(
        "SELECT COUNT(*) as cnt FROM security_events WHERE event_type = 'prompt_threat' AND created_at > :since",
        ['since' => $since]
      )['cnt'] ?? 0);

      $bySeverity = db()->fetchAll(
        "SELECT severity, COUNT(*) as cnt FROM security_events
                 WHERE event_type = 'prompt_threat' AND created_at > :since
                 GROUP BY severity",
        ['since' => $since]
      );

      return ['total' => $total, 'by_severity' => $bySeverity, 'period_hours' => $hours];
    } catch (\Throwable $e) {
      return ['total' => 0, 'by_severity' => [], 'period_hours' => $hours];
    }
  }

    // ─────── Internal ───────

  /**
   * Log a prompt threat to security_events.
   */
  private static function logThreat(int $userId, string $role, string $prompt, array $threats, string $severity): void
  {
    try {
      db()->insert('security_events', [
        'event_type'  => 'prompt_threat',
        'severity'    => $severity,
        'user_id'     => $userId ?: null,
        'ip_address'  => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
        'details'     => json_encode([
          'role'    => $role,
          'prompt'  => mb_substr($prompt, 0, 500),
          'threats' => $threats,
        ], JSON_UNESCAPED_UNICODE),
        'resolved'    => 0,
        'created_at'  => date('Y-m-d H:i:s'),
      ]);
    } catch (\Throwable $e) {
      error_log("PromptFirewall log failed: " . $e->getMessage());
    }
  }

  /**
   * Compare two severity levels and return the higher one.
   */
  private static function highestSeverity(?string $a, string $b): string
  {
    $order = ['low' => 1, 'medium' => 2, 'high' => 3, 'critical' => 4];
    $aVal = $order[$a ?? 'low'] ?? 0;
    $bVal = $order[$b] ?? 0;
    return $aVal >= $bVal ? ($a ?? $b) : $b;
  }
}
