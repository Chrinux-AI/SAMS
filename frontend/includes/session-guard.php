<?php

/**
 * SAMS Session Guard
 * Role-based session timeouts, idle detection, session heartbeat.
 * Include this early in every authenticated page via config.php.
 */

if (!defined('SESSION_GUARD_LOADED')) {
  define('SESSION_GUARD_LOADED', true);
}

// Role-based timeout durations (seconds)
define('SESSION_TIMEOUT_ADMIN', 600);   // 10 minutes for admin
define('SESSION_TIMEOUT_STAFF', 900);   // 15 minutes for staff roles
define('SESSION_TIMEOUT_DEFAULT', 1200); // 20 minutes for students/others

/**
 * Get the session timeout for current user's role.
 */
function get_session_timeout(): int
{
  $role = $_SESSION['role'] ?? ($_SESSION['user_role'] ?? '');
  if (in_array($role, ['admin', 'super_admin', 'superadmin'], true)) {
    return SESSION_TIMEOUT_ADMIN;
  }
  if (in_array($role, ['teacher', 'bursar', 'accountant', 'librarian', 'transport', 'forum_moderator'], true)) {
    return SESSION_TIMEOUT_STAFF;
  }
  return SESSION_TIMEOUT_DEFAULT;
}

/**
 * Check and enforce session timeout based on user role.
 * Should be called on every authenticated request.
 * Returns true if session is valid, false/redirects if expired.
 */
function enforce_session_timeout(): bool
{
  // Skip if not logged in
  if (!isset($_SESSION['user_id'])) {
    return true;
  }

  $timeout = get_session_timeout();

  if (isset($_SESSION['last_activity'])) {
    $idle_time = time() - $_SESSION['last_activity'];
    if ($idle_time > $timeout) {
      $expired_role = $_SESSION['role'] ?? 'unknown';
      $expired_user = $_SESSION['user_id'] ?? 0;

      // Log the timeout
      error_log("Session timeout for user {$expired_user} (role: {$expired_role}) after {$idle_time}s idle");

      // Destroy session
      $_SESSION = [];
      if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
          session_name(),
          '',
          time() - 42000,
          $params['path'],
          $params['domain'],
          $params['secure'],
          $params['httponly']
        );
      }
      session_destroy();

      // Start new session for flash message
      session_start();
      $_SESSION['flash_message'] = 'Your session has expired due to inactivity. Please log in again.';
      $_SESSION['flash_type'] = 'warning';

      // Determine redirect — API calls get JSON, pages get redirect
      if (
        (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
        (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false)
      ) {
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'session_expired', 'message' => 'Session expired']);
        exit;
      }

      // Build redirect path
      $base = '/attendance/login.php?timeout=1';
      header("Location: {$base}");
      exit;
    }
  }

  // Update last activity
  $_SESSION['last_activity'] = time();
  return true;
}

/**
 * Regenerate session ID safely (call after login).
 */
function regenerate_session(): void
{
  if (session_status() === PHP_SESSION_ACTIVE) {
    session_regenerate_id(true);
  }
}
