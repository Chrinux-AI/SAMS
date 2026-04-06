<?php

/**
 * Core AI Service — Base class for all role-specific AI services.
 * Provides shared AI logic: prompt building, safety filtering, rate limiting.
 */
class CoreAIService
{
  protected string $role = 'guest';
  protected array $allowedTopics = [];
  protected array $blockedTopics = [];
  protected string $systemPrompt = '';
  protected int $maxTokens = 500;
  protected int $rateLimitPerMinute = 30;

  /** Injection patterns to block */
  private static array $injectionPatterns = [
    '/ignore\s+(all\s+)?previous\s+instructions/i',
    '/you\s+are\s+now\s+(a|an|the)\b/i',
    '/\bsystem\s*prompt\b/i',
    '/\bbypass\s+(security|filter|restriction)/i',
    '/\boverride\s+(instructions|rules|policy)/i',
    '/\bpretend\s+(you|to\s+be)/i',
    '/\bjailbreak\b/i',
    '/\bDAN\s+mode\b/i',
    '/<script[\s>]/i',
    '/\bon\w+\s*=/i',
    '/\b(SELECT|INSERT|UPDATE|DELETE|DROP|ALTER)\b.*\b(FROM|INTO|TABLE|SET)\b/i',
    '/\bunion\s+select\b/i',
  ];

  public function __construct(string $role)
  {
    $this->role = $role;
  }

  /**
   * Process a user message through the full safety pipeline.
   * Returns ['success' => bool, 'response' => string, 'blocked' => bool, 'reason' => string]
   */
  public function process(string $message, int $userId = 0): array
  {
    // 1. Input validation
    $message = trim($message);
    if ($message === '') {
      return $this->blocked('Empty message');
    }
    if (mb_strlen($message) > 2000) {
      return $this->blocked('Message too long (max 2000 characters)');
    }

    // 2. Injection check
    if ($this->detectInjection($message)) {
      $this->logSecurity($userId, 'injection_attempt', $message);
      return $this->blocked('Your message was flagged by our safety system. Please rephrase.');
    }

    // 3. Topic check
    if (!$this->isTopicAllowed($message)) {
      return $this->blocked("I can only help with topics related to my role. Please ask something within my scope.");
    }

    // 4. Build context
    $context = $this->buildContext($userId);

    // 5. Generate response — subclasses override generateResponse()
    $response = $this->generateResponse($message, $context);

    // 6. Sanitize output
    $response = $this->sanitizeResponse($response);

    return [
      'success'     => true,
      'response'    => $response,
      'blocked'     => false,
      'reason'      => '',
      'role'        => $this->role,
      'timestamp'   => date('c'),
    ];
  }

  /**
   * Override in subclasses for role-specific responses.
   */
  protected function generateResponse(string $message, array $context): string
  {
    return "I'm the SAMS AI assistant. How can I help you today?";
  }

  /**
   * Build context data available to this AI role.
   */
  protected function buildContext(int $userId): array
  {
    return [
      'role'          => $this->role,
      'user_id'       => $userId,
      'system_prompt' => $this->systemPrompt,
      'timestamp'     => date('c'),
    ];
  }

  /**
   * Check if the message topic is within this role's scope.
   */
  protected function isTopicAllowed(string $message): bool
  {
    $lower = mb_strtolower($message);
    foreach ($this->blockedTopics as $blocked) {
      if (str_contains($lower, mb_strtolower($blocked))) {
        return false;
      }
    }
    return true;
  }

  /**
   * Detect prompt injection / adversarial input.
   */
  protected function detectInjection(string $message): bool
  {
    foreach (self::$injectionPatterns as $pattern) {
      if (preg_match($pattern, $message)) {
        return true;
      }
    }
    return false;
  }

  /**
   * Sanitize AI response before sending to client.
   */
  protected function sanitizeResponse(string $response): string
  {
    // Strip any HTML/script tags
    $response = strip_tags($response);
    // Remove any leaked SQL
    $response = preg_replace('/\b(SELECT|INSERT|UPDATE|DELETE|DROP|ALTER)\s+(FROM|INTO|TABLE)\b/i', '[redacted]', $response);
    return $response;
  }

  /**
   * Log a security event.
   */
  protected function logSecurity(int $userId, string $event, string $details): void
  {
    try {
      db()->insert('audit_logs', [
        'user_id'    => $userId ?: null,
        'action'     => 'ai_' . $event,
        'details'    => mb_substr($details, 0, 500),
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
        'created_at' => date('Y-m-d H:i:s'),
      ]);
    } catch (\Throwable $e) {
      error_log("AI Security log failed: " . $e->getMessage());
    }
  }

  /**
   * Return a blocked-response array.
   */
  protected function blocked(string $reason): array
  {
    return [
      'success'  => false,
      'response' => $reason,
      'blocked'  => true,
      'reason'   => $reason,
      'role'     => $this->role,
    ];
  }

  public function getRole(): string
  {
    return $this->role;
  }

  public function getSystemPrompt(): string
  {
    return $this->systemPrompt;
  }

  public function getAllowedTopics(): array
  {
    return $this->allowedTopics;
  }
}
