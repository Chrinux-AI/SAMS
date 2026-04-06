<?php

/**
 * PolicyRuntime — OS-Level Security Governance
 *
 * Enforces session timeout, CSRF validation, role-based access,
 * rate limiting, audit logging, and security policies.
 */
class PolicyRuntime
{
  private static array $policies = [];

  /**
   * Enforce all security policies for the current request.
   */
  public static function enforce(): array
  {
    $results = [];

    // Session security
    $results['session'] = self::enforceSession();

    // Rate limiting (lightweight)
    $results['rate_limit'] = self::checkRateLimit();

    // Audit logging
    $results['audit'] = self::auditRequest();

    return $results;
  }

  /**
   * Enforce session security policies.
   */
  private static function enforceSession(): array
  {
    if (session_status() !== PHP_SESSION_ACTIVE) {
      return ['status' => 'no_session'];
    }

    // Session fixation protection
    if (!isset($_SESSION['_os_initiated'])) {
      session_regenerate_id(false);
      $_SESSION['_os_initiated'] = true;
      return ['status' => 'regenerated'];
    }

    // Session timeout
    $timeout = defined('SESSION_TIMEOUT') ? SESSION_TIMEOUT : 1800;
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout) {
      return ['status' => 'expired'];
    }

    $_SESSION['last_activity'] = time();

    return ['status' => 'valid'];
  }

  /**
   * Simple request rate limiting using session.
   */
  private static function checkRateLimit(): array
  {
    if (session_status() !== PHP_SESSION_ACTIVE) {
      return ['status' => 'no_session'];
    }

    $limit  = 120; // requests per minute
    $window = 60;
    $now    = time();

    if (!isset($_SESSION['_rate_requests'])) {
      $_SESSION['_rate_requests'] = [];
    }

    // Prune old requests
    $_SESSION['_rate_requests'] = array_filter(
      $_SESSION['_rate_requests'],
      fn($t) => ($now - $t) < $window
    );

    $count = count($_SESSION['_rate_requests']);
    $_SESSION['_rate_requests'][] = $now;

    if ($count >= $limit) {
      ErrorCollector::log('policy_runtime', 'Rate limit exceeded', 'HIGH', [
        'user_id' => $_SESSION['user_id'] ?? 0,
        'count'   => $count,
      ]);
      return ['status' => 'limited', 'count' => $count];
    }

    return ['status' => 'ok', 'count' => $count];
  }

  /**
   * Audit the current request.
   */
  private static function auditRequest(): array
  {
    if (php_sapi_name() === 'cli') {
      return ['status' => 'cli'];
    }

    try {
      if (!table_exists('activity_log')) {
        return ['status' => 'no_table'];
      }

      $userId = $_SESSION['user_id'] ?? 0;
      if (!$userId) {
        return ['status' => 'anonymous'];
      }

      // Only log navigation (not AJAX polls)
      $uri    = $_SERVER['REQUEST_URI'] ?? '';
      $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

      // Skip high-frequency endpoints
      if (strpos($uri, '/api/') !== false && $method === 'GET') {
        return ['status' => 'skipped'];
      }

      db()->insert('activity_log', [
        'user_id'    => $userId,
        'action'     => $method . ' ' . strtok($uri, '?'),
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
        'created_at' => date('Y-m-d H:i:s'),
      ]);

      return ['status' => 'logged'];
    } catch (\Throwable $e) {
      return ['status' => 'error'];
    }
  }

  /**
   * Verify CSRF token for the current request (POST/PUT/DELETE).
   */
  public static function requireCSRF(): bool
  {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if ($method === 'GET') return true;

    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    return verify_csrf_token($token);
  }

  /**
   * Require a specific role or abort.
   */
  public static function requireRole(string $role): bool
  {
    return IdentityCore::hasRole($role);
  }

  /**
   * Require any of the specified roles.
   */
  public static function requireAnyRole(array $roles): bool
  {
    return IdentityCore::hasAnyRole($roles);
  }

  /**
   * Get policy stats.
   */
  public static function getStats(): array
  {
    $rateCurrent = 0;
    if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['_rate_requests'])) {
      $rateCurrent = count($_SESSION['_rate_requests']);
    }
    return [
      'session_status' => session_status() === PHP_SESSION_ACTIVE ? 'active' : 'inactive',
      'rate_current'   => $rateCurrent,
      'rate_limit'     => 120,
    ];
  }
}
