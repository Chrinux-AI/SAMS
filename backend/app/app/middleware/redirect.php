<?php

/**
 * Redirect Protection Middleware
 * Catches requests to non-existent pages and redirects to the appropriate dashboard.
 *
 * Usage — add to includes/config.php or a front-controller:
 *   require_once BASE_PATH . '/app/middleware/redirect.php';
 *   RedirectProtection::check();
 */
class RedirectProtection
{
  /**
   * Check if the current request targets a valid page.
   * If the page doesn't exist, redirect to the user's dashboard with a flash message.
   * Only runs for direct page requests (not AJAX, not API, not static assets).
   */
  public static function check(): void
  {
    // Skip for CLI
    if (php_sapi_name() === 'cli') return;

    // Skip for AJAX/API requests
    if (self::isAjax() || self::isApiRequest()) return;

    // Skip if file exists (normal request)
    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    $scriptFilename = $_SERVER['SCRIPT_FILENAME'] ?? '';

    // If the script file exists, the page is valid
    if (is_file($scriptFilename)) return;

    // Log the missing page
    if (class_exists('ErrorCollector')) {
      ErrorCollector::log('missing_page', "404: $requestUri", 'MEDIUM');
    }

    // Dispatch route_failure event for AI layers
    if (class_exists('EventBus')) {
      EventBus::dispatch('system', 'route_failure', [
        'uri'    => $requestUri,
        'file'   => $scriptFilename,
        'user'   => $_SESSION['user_id'] ?? null,
        'role'   => $_SESSION['role'] ?? 'guest',
      ]);
    }

    // Determine redirect target
    $target = self::getRedirectTarget();

    // Set flash message
    if (session_status() === PHP_SESSION_ACTIVE) {
      $_SESSION['flash_message'] = 'The page you requested was not found. You have been redirected to your dashboard.';
      $_SESSION['flash_type'] = 'warning';
    }

    header('Location: ' . $target);
    exit;
  }

  /**
   * Get the appropriate redirect target based on user role.
   */
  private static function getRedirectTarget(): string
  {
    if (!isset($_SESSION['user_id'])) {
      return self::buildUrl('login.php');
    }

    $role = $_SESSION['role'] ?? $_SESSION['user_role'] ?? '';
    $dashboards = [
      'admin'           => 'admin/dashboard.php',
      'teacher'         => 'teacher/dashboard.php',
      'student'         => 'student/dashboard.php',
      'parent'          => 'parent/dashboard.php',
      'developer'       => 'developer/index.php',
      'accountant'      => 'accountant/dashboard.php',
      'bursar'          => 'bursar/dashboard.php',
      'librarian'       => 'librarian/dashboard.php',
      'transport'       => 'transport/dashboard.php',
      'forum_moderator' => 'forum-moderator/dashboard.php',
    ];

    $path = $dashboards[$role] ?? 'login.php';
    return self::buildUrl($path);
  }

  private static function buildUrl(string $path): string
  {
    $base = defined('BASE_URL') ? BASE_URL : '/attendance';
    return $base . '/' . ltrim($path, '/');
  }

  private static function isAjax(): bool
  {
    return (isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
      strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
      || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));
  }

  private static function isApiRequest(): bool
  {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    return str_contains($uri, '/api/') || str_contains($uri, '/ajax/');
  }
}
