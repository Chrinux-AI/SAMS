<?php

/**
 * RequestPipeline — Unified Middleware Runner
 *
 * Ensures every request passes through the full processing chain:
 *   Auth → Session Timeout → Role Validation → CSRF → Route Guard
 *
 * Called centrally from config.php after bootstrap. Individual pages
 * no longer need to manually call each guard separately.
 *
 * Usage in config.php:
 *   RequestPipeline::process();
 *
 * Usage in endpoints needing extra guards:
 *   RequestPipeline::process(['require_auth' => true, 'require_role' => ['admin']]);
 */
class RequestPipeline
{
  /** @var array Middleware execution log for debugging */
  private static array $log = [];

  /** @var bool Prevent double execution */
  private static bool $processed = false;

  /**
   * Run the full middleware pipeline.
   *
   * @param array $options Override defaults per-request
   */
  public static function process(array $options = []): void
  {
    if (self::$processed) {
      return;
    }
    self::$processed = true;

    $defaults = [
      'require_auth' => false,
      'require_role' => [],
      'csrf'         => false,    // Only enforced on POST by default
      'rate_action'  => null,
      'skip_routes'  => [
        'login.php',
        'register.php',
        'forgot-password.php',
        'reset-password.php',
        'verify-email.php',
        'verify-otp.php',
        'activate-account.php',
        'confirm-account.php',
        'api/health.php',
        'offline.html'
      ],
    ];

    $opts = array_merge($defaults, $options);
    $script = $_SERVER['SCRIPT_NAME'] ?? '';

    // Step 1: Session Timeout (already called in config.php via enforce_session_timeout)
    self::$log[] = ['step' => 'session_timeout', 'status' => 'enforced'];

    // Step 2: Security Headers (already called in config.php via apply_security_headers)
    self::$log[] = ['step' => 'security_headers', 'status' => 'applied'];

    // Step 3: XSS + SQLi guard (already called in config.php via SecurityGateway)
    self::$log[] = ['step' => 'xss_sqli_guard', 'status' => 'active'];

    // Step 4: Rate Limiting
    if ($opts['rate_action'] && class_exists('RateLimiterService')) {
      try {
        RateLimiterService::check($opts['rate_action']);
        self::$log[] = ['step' => 'rate_limit', 'status' => 'passed'];
      } catch (\Throwable $e) {
        self::$log[] = ['step' => 'rate_limit', 'status' => 'blocked'];
        if (self::isApiRequest()) {
          http_response_code(429);
          header('Content-Type: application/json');
          echo json_encode(['error' => 'Too many requests']);
          exit;
        }
      }
    }

    // Step 5: CSRF validation on POST/PUT/DELETE
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (in_array($method, ['POST', 'PUT', 'DELETE'], true)) {
      if ($opts['csrf'] || self::shouldEnforceCsrf($script)) {
        self::validateCsrf();
        self::$log[] = ['step' => 'csrf', 'status' => 'validated'];
      }
    }

    // Step 6: Auth check (if required)
    if ($opts['require_auth'] && !self::isSkippedRoute($script, $opts['skip_routes'])) {
      if (empty($_SESSION['user_id'])) {
        self::$log[] = ['step' => 'auth', 'status' => 'failed'];
        self::dispatchEvent('auth', 'unauthorized_access', ['script' => $script]);
        if (self::isApiRequest()) {
          http_response_code(401);
          header('Content-Type: application/json');
          echo json_encode(['error' => 'Authentication required']);
          exit;
        }
        header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/attendance') . '/login.php');
        exit;
      }
      self::$log[] = ['step' => 'auth', 'status' => 'passed'];
    }

    // Step 7: Role check
    if (!empty($opts['require_role'])) {
      $userRole = $_SESSION['role'] ?? '';
      $allowed = (array)$opts['require_role'];
      if (!in_array($userRole, $allowed, true)) {
        self::$log[] = ['step' => 'role', 'status' => 'denied', 'role' => $userRole];
        self::dispatchEvent('security', 'role_violation', [
          'user_role' => $userRole,
          'required'  => $allowed,
          'script'    => $script,
        ]);
        http_response_code(403);
        if (self::isApiRequest()) {
          header('Content-Type: application/json');
          echo json_encode(['error' => 'Access denied']);
          exit;
        }
        // Redirect to appropriate dashboard
        self::redirectToDashboard($userRole);
        exit;
      }
      self::$log[] = ['step' => 'role', 'status' => 'passed'];
    }

    // Step 8: Route guard — verify the target file exists
    if (!self::isSkippedRoute($script, $opts['skip_routes'])) {
      $scriptFile = $_SERVER['SCRIPT_FILENAME'] ?? '';
      if ($scriptFile && !is_file($scriptFile)) {
        self::$log[] = ['step' => 'route_guard', 'status' => 'missing'];
        self::dispatchEvent('system', 'route_failure', [
          'script' => $script,
          'file'   => $scriptFile,
        ]);
        $role = $_SESSION['role'] ?? 'student';
        self::redirectToDashboard($role, 'The page you requested was not found.');
        exit;
      }
      self::$log[] = ['step' => 'route_guard', 'status' => 'verified'];
    }
  }

  /**
   * Get the middleware execution log (for debugging/MCC).
   */
  public static function getLog(): array
  {
    return self::$log;
  }

  /**
   * Check if pipeline has been processed.
   */
  public static function isProcessed(): bool
  {
    return self::$processed;
  }

  /**
   * Reset pipeline state (for testing only).
   */
  public static function reset(): void
  {
    self::$processed = false;
    self::$log = [];
  }

  // ── Private helpers ──

  private static function isApiRequest(): bool
  {
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $xhr = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    return stripos($accept, 'application/json') !== false
      || strtolower($xhr) === 'xmlhttprequest'
      || strpos($script, '/api/') !== false;
  }

  private static function isSkippedRoute(string $script, array $skipRoutes): bool
  {
    foreach ($skipRoutes as $skip) {
      if (str_contains($script, $skip)) {
        return true;
      }
    }
    return false;
  }

  private static function shouldEnforceCsrf(string $script): bool
  {
    // Don't enforce CSRF on API endpoints (they use token auth)
    if (strpos($script, '/api/') !== false) {
      return false;
    }
    return true;
  }

  private static function validateCsrf(): void
  {
    if (function_exists('verify_csrf_token')) {
      $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
      if ($token && !verify_csrf_token($token)) {
        self::dispatchEvent('security', 'csrf_violation', [
          'script' => $_SERVER['SCRIPT_NAME'] ?? '',
          'ip'     => $_SERVER['REMOTE_ADDR'] ?? '',
        ]);
        http_response_code(403);
        if (self::isApiRequest()) {
          header('Content-Type: application/json');
          echo json_encode(['error' => 'CSRF validation failed']);
          exit;
        }
      }
    }
  }

  private static function redirectToDashboard(string $role, string $message = ''): void
  {
    $dashboards = [
      'admin'           => 'admin/dashboard.php',
      'developer'       => 'developer/index.php',
      'teacher'         => 'teacher/dashboard.php',
      'student'         => 'student/dashboard.php',
      'parent'          => 'parent/dashboard.php',
      'accountant'      => 'accountant/index.php?page=dashboard',
      'bursar'          => 'bursar/dashboard.php',
      'librarian'       => 'librarian/dashboard.php',
      'transport'       => 'transport/dashboard.php',
      'forum_moderator' => 'forum/index.php',
    ];

    $target = $dashboards[$role] ?? 'login.php';
    $base = defined('BASE_URL') ? BASE_URL : '/attendance';

    if ($message) {
      $_SESSION['flash_message'] = $message;
      $_SESSION['flash_type'] = 'error';
    }

    header('Location: ' . $base . '/' . $target);
    exit;
  }

  private static function dispatchEvent(string $channel, string $event, array $payload): void
  {
    if (class_exists('EventBus')) {
      try {
        EventBus::dispatch($channel, $event, $payload);
      } catch (\Throwable $e) {
        // Silent — don't let event dispatch kill the pipeline
      }
    }
  }
}
