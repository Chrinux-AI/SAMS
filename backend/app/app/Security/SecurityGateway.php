<?php

/**
 * Security Gateway — Unified entry point for all security checks.
 * Combines: InputSanitizer, XSSGuard, SQLInjectionGuard, AuditLogger, RateLimiterService.
 *
 * Usage: SecurityGateway::guard() at the top of any request handler.
 */

require_once __DIR__ . '/InputSanitizer.php';
require_once __DIR__ . '/XSSGuard.php';
require_once __DIR__ . '/SQLInjectionGuard.php';
require_once __DIR__ . '/AuditLogger.php';
require_once __DIR__ . '/RateLimiterService.php';

class SecurityGateway
{
  private static bool $initialized = false;

  /**
   * Run full security guard on the current request.
   * Call once at the top of API endpoints or page handlers.
   *
   * @param array $options  Optional overrides:
   *   'require_auth'  => bool   (default: false)
   *   'require_role'  => string|array (default: null)
   *   'rate_action'   => string (default: 'api')
   *   'csrf'          => bool   (default: true for POST)
   *   'xss_check'     => bool   (default: true)
   *   'sqli_check'    => bool   (default: true)
   */
  public static function guard(array $options = []): void
  {
    $requireAuth = $options['require_auth'] ?? false;
    $requireRole = $options['require_role'] ?? null;
    $rateAction  = $options['rate_action'] ?? null;
    $csrfCheck   = $options['csrf'] ?? ($_SERVER['REQUEST_METHOD'] === 'POST');
    $xssCheck    = $options['xss_check'] ?? true;
    $sqliCheck   = $options['sqli_check'] ?? true;

    // 1. Authentication check
    if ($requireAuth) {
      if (!isset($_SESSION['user_id'])) {
        self::deny(401, 'Authentication required');
      }
    }

    // 2. Role check
    if ($requireRole !== null) {
      $roles = is_array($requireRole) ? $requireRole : [$requireRole];
      $userRole = $_SESSION['role'] ?? $_SESSION['user_role'] ?? '';
      if (!in_array($userRole, $roles, true)) {
        AuditLogger::logSecurity("Access denied: role '{$userRole}' tried to access role-restricted resource");
        self::deny(403, 'Insufficient permissions');
      }
    }

    // 3. Rate limiting
    if ($rateAction !== null) {
      $result = RateLimiterService::check($rateAction);
      if (!$result['allowed']) {
        AuditLogger::logSecurity("Rate limit exceeded for action '{$rateAction}'");
        http_response_code(429);
        header('Content-Type: application/json');
        header('Retry-After: ' . ($result['retry_after'] ?? 60));
        echo json_encode([
          'success'     => false,
          'error'       => 'Rate limit exceeded. Please wait.',
          'retry_after' => $result['retry_after'] ?? 60,
        ]);
        exit;
      }
      RateLimiterService::record($rateAction);
    }

    // 4. XSS detection
    if ($xssCheck && !XSSGuard::guardRequest()) {
      self::deny(400, 'Request blocked by security filter');
    }

    // 5. SQL injection detection
    if ($sqliCheck && !SQLInjectionGuard::guardRequest()) {
      self::deny(400, 'Request blocked by security filter');
    }

    // 6. CSRF validation (POST requests)
    if ($csrfCheck && $_SERVER['REQUEST_METHOD'] === 'POST') {
      $token = $_POST['csrf_token']
        ?? $_SERVER['HTTP_X_CSRF_TOKEN']
        ?? '';
      if (!self::validateCSRF($token)) {
        AuditLogger::logSecurity('CSRF validation failed');
        self::deny(403, 'Invalid security token. Please refresh and try again.');
      }
    }

    self::$initialized = true;
  }

  /**
   * Quick guard for API endpoints: auth + rate limit + security checks.
   */
  public static function apiGuard(string $rateAction = 'api', $requireRole = null): void
  {
    self::guard([
      'require_auth' => true,
      'require_role' => $requireRole,
      'rate_action'  => $rateAction,
      'csrf'         => false,  // APIs use tokens/headers
    ]);
  }

  /**
   * Guard for public endpoints (no auth, tight rate limits).
   */
  public static function publicGuard(string $rateAction = 'api'): void
  {
    self::guard([
      'require_auth' => false,
      'rate_action'  => $rateAction,
      'csrf'         => false,
    ]);
  }

  /**
   * Validate CSRF token.
   */
  private static function validateCSRF(string $token): bool
  {
    if (empty($token) || empty($_SESSION['csrf_token'])) {
      return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
  }

  /**
   * Deny request with HTTP error.
   */
  private static function deny(int $code, string $message): void
  {
    http_response_code($code);
    if (self::isApiRequest()) {
      header('Content-Type: application/json');
      echo json_encode(['success' => false, 'error' => $message]);
    } else {
      // For page requests, redirect to login or show error
      if ($code === 401) {
        header('Location: ' . (defined('APP_URL') ? APP_URL : '/attendance') . '/login.php');
      } else {
        echo "<!DOCTYPE html><html><body><h1>Error {$code}</h1><p>" . htmlspecialchars($message) . "</p></body></html>";
      }
    }
    exit;
  }

  /**
   * Determine if the current request is an API/AJAX call.
   */
  private static function isApiRequest(): bool
  {
    if (str_contains($_SERVER['REQUEST_URI'] ?? '', '/api/')) {
      return true;
    }
    if (($_SERVER['HTTP_ACCEPT'] ?? '') === 'application/json') {
      return true;
    }
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
      return true;
    }
    return false;
  }

  /**
   * Get sanitized input value from POST.
   */
  public static function input(string $key, $default = ''): string
  {
    $value = $_POST[$key] ?? $default;
    return InputSanitizer::sanitize((string) $value);
  }

  /**
   * Get sanitized integer from POST/GET.
   */
  public static function inputInt(string $key, int $default = 0): int
  {
    $value = $_POST[$key] ?? $_GET[$key] ?? $default;
    return InputSanitizer::integer($value);
  }

  /**
   * Get sanitized query parameter from GET.
   */
  public static function query(string $key, $default = ''): string
  {
    $value = $_GET[$key] ?? $default;
    return InputSanitizer::sanitize((string) $value);
  }
}
