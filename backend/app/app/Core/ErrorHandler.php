<?php

/**
 * Enterprise Error Handler — Production-grade error management.
 *
 * Provides:
 *  - Friendly user-facing error pages (with theme support)
 *  - Full stack trace + context for admins only
 *  - Structured JSON responses for API endpoints
 *  - Logging to storage/logs/system.log with request context
 *  - Integration with AuditLogger if available
 *
 * This class enhances the existing includes/error-handler.php by adding
 * admin debug context and theme-aware error pages.
 */
class ErrorHandler
{
  private static bool $registered = false;

  /**
   * Register all error/exception/shutdown handlers.
   * Safe to call multiple times — only registers once.
   */
  public static function register(): void
  {
    if (self::$registered) {
      return;
    }
    self::$registered = true;

    set_error_handler([self::class, 'handleError']);
    set_exception_handler([self::class, 'handleException']);
    register_shutdown_function([self::class, 'handleShutdown']);
  }

  /**
   * Convert PHP errors to ErrorException.
   */
  public static function handleError(int $severity, string $message, string $file, int $line): bool
  {
    if (!(error_reporting() & $severity)) {
      return false;
    }
    throw new \ErrorException($message, 0, $severity, $file, $line);
  }

  /**
   * Handle uncaught exceptions.
   */
  public static function handleException(\Throwable $e): void
  {
    self::logException($e);

    if (headers_sent()) {
      echo '<!-- An error occurred. Check logs for details. -->';
      return;
    }

    if (self::isApiRequest()) {
      self::renderJsonError($e);
      return;
    }

    self::renderHtmlError($e);
  }

  /**
   * Catch fatal errors at shutdown.
   */
  public static function handleShutdown(): void
  {
    $error = error_get_last();
    if ($error === null) {
      return;
    }

    $fatal = E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR;
    if (!($error['type'] & $fatal)) {
      return;
    }

    $exception = new \ErrorException(
      $error['message'],
      0,
      $error['type'],
      $error['file'],
      $error['line']
    );

    self::logException($exception);

    if (headers_sent()) {
      return;
    }

    if (self::isApiRequest()) {
      self::renderJsonError($exception);
    } else {
      self::renderHtmlError($exception);
    }
  }

  /**
   * Log full exception details.
   */
  private static function logException(\Throwable $e): void
  {
    $log = sprintf(
      "[%s] %s in %s:%d\nURL: %s %s\nIP: %s\nUser: %s\nTrace:\n%s\n",
      date('Y-m-d H:i:s'),
      $e->getMessage(),
      $e->getFile(),
      $e->getLine(),
      $_SERVER['REQUEST_METHOD'] ?? 'CLI',
      $_SERVER['REQUEST_URI'] ?? '-',
      $_SERVER['REMOTE_ADDR'] ?? '-',
      $_SESSION['user_id'] ?? 'guest',
      $e->getTraceAsString()
    );
    error_log($log);

    // Also log to AuditLogger if available
    if (class_exists('AuditLogger', false)) {
      try {
        AuditLogger::logSecurity('system_error', [
          'message' => mb_substr($e->getMessage(), 0, 500),
          'file'    => $e->getFile(),
          'line'    => $e->getLine(),
          'url'     => $_SERVER['REQUEST_URI'] ?? '-',
        ]);
      } catch (\Throwable $ignore) {
        // Don't let audit logging cause another error
      }
    }
  }

  /**
   * JSON error for API/AJAX requests.
   */
  private static function renderJsonError(\Throwable $e): void
  {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');

    $payload = ['success' => false, 'error' => 'Internal server error'];

    // Add debug details for logged-in admins
    if (self::isAdmin()) {
      $payload['debug'] = [
        'message' => $e->getMessage(),
        'file'    => $e->getFile(),
        'line'    => $e->getLine(),
      ];
    }

    echo json_encode($payload);
  }

  /**
   * HTML error page with admin debug panel.
   */
  private static function renderHtmlError(\Throwable $e): void
  {
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');

    $isAdmin = self::isAdmin();
    $errorView = (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2)) . '/resources/errors/500.php';

    if (is_file($errorView)) {
      // Pass variables to the view
      $error_message = $e->getMessage();
      $error_file    = $e->getFile();
      $error_line    = $e->getLine();
      $error_trace   = $e->getTraceAsString();
      $show_debug    = $isAdmin;
      include $errorView;
    } else {
      // Fallback inline error page
      echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Error</title>';
      echo '<style>body{font-family:Inter,system-ui,sans-serif;background:#f8fafc;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}';
      echo '.box{background:#fff;border-radius:16px;padding:48px;max-width:500px;text-align:center;box-shadow:0 4px 24px rgba(0,0,0,.08)}';
      echo '.box h1{margin:0 0 12px;font-size:1.5rem} .box p{color:#64748b;margin:0 0 24px}';
      echo '.box a{display:inline-block;padding:12px 24px;background:#4F46E5;color:#fff;border-radius:10px;text-decoration:none;font-weight:600}</style></head>';
      echo '<body><div class="box"><h1>Something went wrong</h1><p>We encountered an error. It has been logged.</p>';
      if ($isAdmin) {
        echo '<details style="text-align:left;margin:16px 0;"><summary style="cursor:pointer;font-weight:600;">Debug Info</summary>';
        echo '<pre style="background:#f1f5f9;padding:12px;border-radius:8px;overflow:auto;font-size:0.8rem;margin-top:8px;">';
        echo htmlspecialchars($e->getMessage()) . "\n" . htmlspecialchars($e->getFile()) . ':' . $e->getLine();
        echo '</pre></details>';
      }
      echo '<a href="/attendance/">Return to Home</a></div></body></html>';
    }
  }

  private static function isApiRequest(): bool
  {
    return (
      str_contains($_SERVER['REQUEST_URI'] ?? '', '/api/') ||
      str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') ||
      !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
    );
  }

  private static function isAdmin(): bool
  {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
  }
}
