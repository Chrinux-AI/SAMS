<?php

/**
 * Global Error & Exception Handler — Self-Healing Platform Architecture
 *
 * Catches all uncaught exceptions and fatal errors.
 * Logs them, triggers healing, shows a friendly fallback page.
 * Users never see raw PHP errors.
 */

/**
 * Register the global error handler.
 * Call this once from bootstrap or early in request lifecycle.
 */
function shpa_register_error_handler(): void
{
  // Exception handler
  set_exception_handler('shpa_exception_handler');

  // Error handler — convert errors to exceptions
  set_error_handler('shpa_error_handler');

  // Shutdown handler — catch fatal errors
  register_shutdown_function('shpa_shutdown_handler');
}

/**
 * Handle uncaught exceptions.
 */
function shpa_exception_handler(\Throwable $e): void
{
  $module = 'global_error';
  $message = $e->getMessage();
  $file = $e->getFile();
  $line = $e->getLine();

  // Log to ErrorCollector
  if (class_exists('ErrorCollector')) {
    ErrorCollector::log($module, "$message in $file:$line", 'CRITICAL', [
      'exception' => get_class($e),
      'file'      => $file,
      'line'      => $line,
      'trace'     => $e->getTraceAsString(),
    ]);
  }

  // Log to PHP error log
  error_log("SHPA Exception: $message in $file:$line");

  // Trigger healing for runtime exceptions
  if (class_exists('HealingMemory')) {
    try {
      HealingMemory::ensureTable();
      HealingMemory::record('runtime_exception', 'error_handler_triggered', false, 0, [
        'message' => substr($message, 0, 200),
        'file'    => basename($file),
        'line'    => $line,
      ]);
    } catch (\Throwable $ignore) {
      // Don't let healing logging cause more errors
    }
  }

  // Show friendly error page (only if headers not sent)
  if (!headers_sent()) {
    http_response_code(500);
    shpa_render_friendly_error();
  }
}

/**
 * Convert PHP errors to ErrorException.
 */
function shpa_error_handler(int $severity, string $message, string $file, int $line): bool
{
  // Respect error_reporting level
  if (!(error_reporting() & $severity)) {
    return false;
  }

  // Don't convert notices/deprecations to exceptions — just log them
  if ($severity === E_NOTICE || $severity === E_DEPRECATED || $severity === E_USER_NOTICE || $severity === E_USER_DEPRECATED) {
    if (class_exists('ErrorCollector')) {
      ErrorCollector::log('php_notice', "$message in $file:$line", 'LOW');
    }
    return true;
  }

  throw new \ErrorException($message, 0, $severity, $file, $line);
}

/**
 * Handle fatal errors on shutdown.
 */
function shpa_shutdown_handler(): void
{
  $error = error_get_last();
  if ($error && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
    $message = $error['message'];
    $file = $error['file'];
    $line = $error['line'];

    if (class_exists('ErrorCollector')) {
      ErrorCollector::log('fatal_error', "$message in $file:$line", 'CRITICAL', [
        'type' => $error['type'],
        'file' => $file,
        'line' => $line,
      ]);
    }

    error_log("SHPA Fatal: $message in $file:$line");

    if (!headers_sent()) {
      http_response_code(500);
      shpa_render_friendly_error();
    }
  }
}

/**
 * Render a user-friendly error page.
 */
function shpa_render_friendly_error(): void
{
  // Only render HTML for web requests
  if (php_sapi_name() === 'cli') return;

  $appName = defined('APP_NAME') ? APP_NAME : 'SAMS';

  echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
  echo '<title>Temporary Issue — ' . htmlspecialchars($appName) . '</title>';
  echo '<style>*{margin:0;padding:0;box-sizing:border-box}body{font-family:"Segoe UI",system-ui,sans-serif;background:#f0f4f8;display:flex;justify-content:center;align-items:center;min-height:100vh;color:#333}';
  echo '.box{background:#fff;border-radius:12px;padding:48px;max-width:480px;text-align:center;box-shadow:0 4px 20px rgba(0,0,0,0.08)}';
  echo '.icon{font-size:3rem;margin-bottom:16px}.box h1{font-size:1.4rem;margin-bottom:8px;color:#1a73e8}.box p{color:#666;line-height:1.6;margin-bottom:20px}';
  echo '.btn{display:inline-block;padding:10px 24px;background:#1a73e8;color:#fff;text-decoration:none;border-radius:8px;font-size:0.9rem}</style></head>';
  echo '<body><div class="box"><div class="icon">&#9888;</div>';
  echo '<h1>Something went wrong</h1>';
  echo '<p>We encountered a temporary issue. Our system is already working to resolve it automatically. Please try again in a moment.</p>';
  echo '<a class="btn" href="javascript:location.reload()">Try Again</a>';
  echo '</div></body></html>';
}
