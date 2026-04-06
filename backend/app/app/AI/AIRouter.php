<?php

/**
 * AI Router — Routes AI requests to the correct role-scoped service.
 * Pipeline: Request → RoleDetector → AI Router → Scoped Service → Response Filter
 */

require_once __DIR__ . '/CoreAIService.php';
require_once __DIR__ . '/AdminAIService.php';
require_once __DIR__ . '/TeacherAIService.php';
require_once __DIR__ . '/StudentAIService.php';
require_once __DIR__ . '/ParentAIService.php';
require_once __DIR__ . '/PublicAIService.php';

class AIRouter
{
  /** @var array<string, CoreAIService> Cached service instances */
  private static array $services = [];

  /** Map of role names to service classes */
  private static array $roleMap = [
    'admin'           => AdminAIService::class,
    'teacher'         => TeacherAIService::class,
    'student'         => StudentAIService::class,
    'parent'          => ParentAIService::class,
    'librarian'       => StudentAIService::class,   // Shares student-level scope
    'bursar'          => AdminAIService::class,      // Shares admin-level scope
    'accountant'      => AdminAIService::class,
    'transport'       => StudentAIService::class,
    'forum_moderator' => StudentAIService::class,
    'guest'           => PublicAIService::class,
  ];

  /**
   * Detect the current user's role from the session.
   */
  public static function detectRole(): string
  {
    if (!isset($_SESSION['user_id'])) {
      return 'guest';
    }
    $role = $_SESSION['role'] ?? $_SESSION['user_role'] ?? 'guest';
    return self::normalizeRole($role);
  }

  /**
   * Normalize a role string to a known key.
   */
  public static function normalizeRole(string $role): string
  {
    $role = strtolower(trim($role));
    return array_key_exists($role, self::$roleMap) ? $role : 'guest';
  }

  /**
   * Get the AI service instance for a specific role.
   */
  public static function getService(string $role = ''): CoreAIService
  {
    if ($role === '') {
      $role = self::detectRole();
    }
    $role = self::normalizeRole($role);

    if (!isset(self::$services[$role])) {
      $class = self::$roleMap[$role];
      self::$services[$role] = new $class();
    }

    return self::$services[$role];
  }

  /**
   * Route and process an AI message through the full pipeline.
   *
   * @param string $message  User message
   * @param string $role     Explicit role (auto-detected if empty)
   * @param int    $userId   User ID (0 for guests)
   * @return array Response payload
   */
  public static function route(string $message, string $role = '', int $userId = 0): array
  {
    // 1. Detect role
    if ($role === '') {
      $role = self::detectRole();
    }
    if ($userId === 0 && isset($_SESSION['user_id'])) {
      $userId = (int) $_SESSION['user_id'];
    }

    // 2. Rate limit check
    if (function_exists('rate_limiter')) {
      $limit = ($role === 'guest') ? 10 : 30;
      $window = 60;
      $check = rate_limiter()->check('ai_' . $role, $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', $limit, $window);
      if (!$check['allowed']) {
        return [
          'success'    => false,
          'response'   => 'Rate limit exceeded. Please wait before sending another message.',
          'blocked'    => true,
          'reason'     => 'rate_limited',
          'retry_after' => $check['retry_after'],
        ];
      }
      rate_limiter()->record('ai_' . $role, $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    // 3. Get scoped service
    $service = self::getService($role);

    // 4. Process through service pipeline
    $result = $service->process($message, $userId);

    // 5. Add metadata
    $result['suggestions'] = ($service instanceof PublicAIService)
      ? $service->getSuggestions()
      : [];

    return $result;
  }

  /**
   * Get the permission matrix for documentation/debugging.
   */
  public static function getPermissionMatrix(): array
  {
    $matrix = [];
    foreach (self::$roleMap as $role => $class) {
      $service = self::getService($role);
      $matrix[$role] = [
        'service_class'  => $class,
        'allowed_topics' => $service->getAllowedTopics(),
        'system_prompt'  => $service->getSystemPrompt(),
      ];
    }
    return $matrix;
  }
}
