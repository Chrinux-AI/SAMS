<?php

/**
 * SessionGuard — Login Inactivity Timeout (Mandatory)
 *
 * 15-minute inactivity timeout. Include globally in authenticated pages.
 * Destroys session and redirects to login on timeout.
 */
class SessionGuard
{
  private static int $timeout = 900; // 15 minutes

  /**
   * Enforce session timeout. Call at the top of every authenticated page.
   */
  public static function enforce(): void
  {
    if (session_status() === PHP_SESSION_NONE) {
      session_start();
    }

    // Skip if not logged in
    if (!isset($_SESSION['user_id'])) return;

    // Check inactivity
    if (isset($_SESSION['LAST_ACTIVITY'])) {
      $elapsed = time() - $_SESSION['LAST_ACTIVITY'];
      if ($elapsed > self::$timeout) {
        $userId = $_SESSION['user_id'] ?? 0;
        session_unset();
        session_destroy();

        // Log the timeout
        try {
          ErrorCollector::log('auth', "Session timeout for user {$userId} after {$elapsed}s inactivity", 'INFO');
        } catch (\Throwable $e) {
          // ErrorCollector may not be loaded
        }

        // Redirect with timeout flag
        $loginUrl = '/attendance/login.php?timeout=1';
        header("Location: {$loginUrl}");
        exit;
      }
    }

    // Update last activity
    $_SESSION['LAST_ACTIVITY'] = time();
  }

  /**
   * Get remaining session time in seconds.
   */
  public static function getRemaining(): int
  {
    if (!isset($_SESSION['LAST_ACTIVITY'])) return self::$timeout;
    $elapsed = time() - $_SESSION['LAST_ACTIVITY'];
    return max(0, self::$timeout - $elapsed);
  }

  /**
   * Get the timeout duration.
   */
  public static function getTimeout(): int
  {
    return self::$timeout;
  }

  /**
   * Check if session is about to expire (< 2 minutes remaining).
   */
  public static function isExpiring(): bool
  {
    return self::getRemaining() < 120;
  }

  /**
   * Refresh activity timestamp (e.g., from AJAX heartbeat).
   */
  public static function heartbeat(): void
  {
    if (session_status() === PHP_SESSION_NONE) {
      session_start();
    }
    if (isset($_SESSION['user_id'])) {
      $_SESSION['LAST_ACTIVITY'] = time();
    }
  }
}
