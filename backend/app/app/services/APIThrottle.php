<?php

/**
 * API Throttle — Lightweight per-endpoint rate limiting for API routes.
 * Integrates with RateLimiterService or works standalone.
 */
class APIThrottle
{
  /** Default endpoint limits */
  private static array $endpointLimits = [
    '/api/sams-bot.php'      => ['max' => 30, 'window' => 60],
    '/api/public-ai.php'     => ['max' => 10, 'window' => 60],
    '/api/chat.php'          => ['max' => 60, 'window' => 60],
    '/api/sse.php'           => ['max' => 5,  'window' => 60],
    '/api/upload-avatar.php' => ['max' => 10, 'window' => 300],
    '/api/session-heartbeat.php' => ['max' => 120, 'window' => 60],
    '/api/notifications.php' => ['max' => 30, 'window' => 60],
  ];

  /**
   * Throttle the current request based on the endpoint.
   * Sends 429 response and exits if rate exceeded.
   */
  public static function throttle(?string $endpoint = null): void
  {
    if ($endpoint === null) {
      $endpoint = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';
      // Normalize path
      $endpoint = str_replace('/attendance', '', $endpoint);
    }

    $config = self::$endpointLimits[$endpoint] ?? ['max' => 60, 'window' => 60];
    $identity = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $action = 'api_' . md5($endpoint);

    if (class_exists('RateLimiterService')) {
      $result = RateLimiterService::check($action, $identity);
      if (!$result['allowed']) {
        self::sendThrottleResponse($result['retry_after'] ?? $config['window']);
      }
      RateLimiterService::record($action, $identity);
    } elseif (function_exists('rate_limiter')) {
      $result = rate_limiter()->check($action, $identity, $config['max'], $config['window']);
      if (!$result['allowed']) {
        self::sendThrottleResponse($result['retry_after'] ?? $config['window']);
      }
      rate_limiter()->record($action, $identity);
    }

    // Set rate limit headers
    header("X-RateLimit-Limit: {$config['max']}");
    header("X-RateLimit-Window: {$config['window']}s");
  }

  /**
   * Send 429 Too Many Requests response and exit.
   */
  private static function sendThrottleResponse(int $retryAfter): void
  {
    http_response_code(429);
    header('Content-Type: application/json');
    header("Retry-After: {$retryAfter}");
    echo json_encode([
      'success'     => false,
      'error'       => 'Too many requests. Please slow down.',
      'retry_after' => $retryAfter,
    ]);
    exit;
  }
}
