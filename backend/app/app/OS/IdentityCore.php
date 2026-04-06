<?php

/**
 * IdentityCore — Unified Identity & Access Control
 *
 * Session validation, role management, inactivity timeout enforcement,
 * SSO readiness, multi-role identity resolution.
 */
class IdentityCore
{
  /**
   * Validate the current session identity.
   */
  public static function validate(): array
  {
    $result = [
      'authenticated' => false,
      'role'          => null,
      'user_id'       => null,
      'session_valid' => false,
      'checks'        => [],
    ];

    // Check session exists
    if (session_status() !== PHP_SESSION_ACTIVE) {
      $result['checks'][] = 'no_active_session';
      return $result;
    }

    // Check user_id in session
    if (empty($_SESSION['user_id'])) {
      $result['checks'][] = 'no_user_id';
      return $result;
    }

    $result['user_id']       = (int) $_SESSION['user_id'];
    $result['role']          = $_SESSION['role'] ?? $_SESSION['user_role'] ?? 'unknown';
    $result['authenticated'] = true;

    // Session timeout check
    $timeout = defined('SESSION_TIMEOUT') ? SESSION_TIMEOUT : 1800;
    if (isset($_SESSION['last_activity'])) {
      $idle = time() - $_SESSION['last_activity'];
      if ($idle > $timeout) {
        $result['checks'][]     = 'session_expired';
        $result['session_valid'] = false;
        return $result;
      }
    }
    $_SESSION['last_activity'] = time();
    $result['session_valid'] = true;

    // Verify user still exists in DB
    try {
      $user = db()->fetchOne("SELECT id, role, status FROM users WHERE id = ?", [$result['user_id']]);
      if (!$user) {
        $result['checks'][] = 'user_not_found';
        $result['session_valid'] = false;
        return $result;
      }
      if (($user['status'] ?? 'active') !== 'active') {
        $result['checks'][] = 'user_inactive';
        $result['session_valid'] = false;
        return $result;
      }
      // Sync role from DB
      if ($user['role'] !== $result['role']) {
        $_SESSION['role']      = $user['role'];
        $_SESSION['user_role'] = $user['role'];
        $result['role']        = $user['role'];
        $result['checks'][]    = 'role_synced_from_db';
      }
    } catch (\Throwable $e) {
      $result['checks'][] = 'db_check_failed';
    }

    $result['checks'][] = 'valid';
    return $result;
  }

  /**
   * Get the current user's profile.
   */
  public static function getCurrentUser(): ?array
  {
    if (empty($_SESSION['user_id'])) return null;

    try {
      return db()->fetchOne(
        "SELECT id, username, email, role, first_name, last_name, status, created_at FROM users WHERE id = ?",
        [(int) $_SESSION['user_id']]
      );
    } catch (\Throwable $e) {
      return null;
    }
  }

  /**
   * Check if the current user has a specific role.
   */
  public static function hasRole(string $role): bool
  {
    return ($_SESSION['role'] ?? $_SESSION['user_role'] ?? '') === $role;
  }

  /**
   * Check if the current user has any of the given roles.
   */
  public static function hasAnyRole(array $roles): bool
  {
    $current = $_SESSION['role'] ?? $_SESSION['user_role'] ?? '';
    return in_array($current, $roles, true);
  }

  /**
   * Get all available roles in the system.
   */
  public static function getSystemRoles(): array
  {
    return ['admin', 'developer', 'teacher', 'student', 'parent', 'accountant', 'bursar', 'librarian', 'transport'];
  }

  /**
   * Get user count by role.
   */
  public static function getRoleCounts(): array
  {
    try {
      $rows = db()->fetchAll("SELECT role, COUNT(*) as cnt FROM users GROUP BY role");
      $counts = [];
      foreach ($rows as $row) {
        $counts[$row['role']] = (int) $row['cnt'];
      }
      return $counts;
    } catch (\Throwable $e) {
      return [];
    }
  }

  /**
   * Destroy the current identity session.
   */
  public static function logout(): void
  {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
      $p = session_get_cookie_params();
      setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
      session_destroy();
    }
  }
}
