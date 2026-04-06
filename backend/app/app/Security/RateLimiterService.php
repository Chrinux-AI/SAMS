<?php

/**
 * Rate Limiter Service — Application-level rate limiting for API endpoints.
 * Wraps the existing RateLimiter from includes/ with a service-oriented API.
 */
class RateLimiterService
{
  /** Default limits per action type */
  private static array $limits = [
    'login'        => ['max' => 5,  'window' => 300],   // 5 per 5 min
    'login_ip'     => ['max' => 20, 'window' => 900],   // 20 per 15 min
    'api'          => ['max' => 60, 'window' => 60],     // 60 per min
    'ai_guest'     => ['max' => 10, 'window' => 60],     // 10 per min
    'ai_user'      => ['max' => 30, 'window' => 60],     // 30 per min
    'upload'       => ['max' => 10, 'window' => 300],    // 10 per 5 min
    'message'      => ['max' => 60, 'window' => 60],     // 60 per min
    'search'       => ['max' => 30, 'window' => 60],     // 30 per min
    'export'       => ['max' => 5,  'window' => 300],    // 5 per 5 min
    'notification' => ['max' => 20, 'window' => 60],     // 20 per min
  ];

  /**
   * Check and enforce a rate limit. Returns true if allowed.
   */
  public static function check(string $action, string $identity = ''): array
  {
    if ($identity === '') {
      $identity = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    $config = self::$limits[$action] ?? ['max' => 60, 'window' => 60];

    if (function_exists('rate_limiter')) {
      return rate_limiter()->check($action, $identity, $config['max'], $config['window']);
    }

    // Fallback: session-based rate limiting
    return self::sessionCheck($action, $identity, $config['max'], $config['window']);
  }

  /**
   * Record a rate-limit hit.
   */
  public static function record(string $action, string $identity = ''): void
  {
    if ($identity === '') {
      $identity = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    if (function_exists('rate_limiter')) {
      rate_limiter()->record($action, $identity);
    }
  }

  /**
   * Clear rate limit on success (e.g., after successful login).
   */
  public static function clear(string $action, string $identity = ''): void
  {
    if ($identity === '') {
      $identity = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    if (function_exists('rate_limiter')) {
      rate_limiter()->clear($action, $identity);
    }
  }

  /**
   * Enforce rate limit — returns 429 response if exceeded.
   */
  public static function enforce(string $action, string $identity = ''): void
  {
    $result = self::check($action, $identity);
    if (!$result['allowed']) {
      http_response_code(429);
      header('Content-Type: application/json');
      header('Retry-After: ' . ($result['retry_after'] ?? 60));
      echo json_encode([
        'success'     => false,
        'error'       => 'Rate limit exceeded',
        'retry_after' => $result['retry_after'] ?? 60,
      ]);
      exit;
    }
    self::record($action, $identity);
  }

  /**
   * Session-based fallback rate limiter.
   */
  private static function sessionCheck(string $action, string $identity, int $max, int $window): array
  {
    $key = "rl_{$action}_{$identity}";
    $now = time();

    if (!isset($_SESSION[$key])) {
      $_SESSION[$key] = ['count' => 0, 'start' => $now];
    }

    $data = &$_SESSION[$key];

    // Reset window if expired
    if ($now - $data['start'] >= $window) {
      $data = ['count' => 0, 'start' => $now];
    }

    $remaining = max(0, $max - $data['count']);
    $allowed = $data['count'] < $max;
    $retryAfter = $allowed ? 0 : ($data['start'] + $window - $now);

    if ($allowed) {
      $data['count']++;
    }

    return [
      'allowed'     => $allowed,
      'remaining'   => $remaining,
      'retry_after' => $retryAfter,
    ];
  }
}
