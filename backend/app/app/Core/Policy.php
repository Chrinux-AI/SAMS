<?php

/**
 * Enterprise Policy Engine
 *
 * Feature-level authorization based on config/policies.php.
 * Works alongside existing page guards (require_admin, etc.) and PolicyGuard (RLS).
 *
 * Usage:
 *   Policy::can('admin', 'finance.manage')    // true
 *   Policy::can('student', 'grades.manage')   // false
 *   Policy::check('teacher', 'attendance.mark') // throws 403 on failure
 *   Policy::forRole('teacher')                 // ['attendance.view', 'attendance.mark', ...]
 */

class Policy
{
  /** @var array<string, string[]>|null */
  private static ?array $policies = null;

  /**
   * Load the policies matrix.
   */
  private static function boot(): void
  {
    if (self::$policies !== null) {
      return;
    }
    $file = BASE_PATH . '/config/policies.php';
    self::$policies = is_file($file) ? (require $file) : [];
  }

  /**
   * Check if a role has a specific permission.
   */
  public static function can(string $role, string $permission): bool
  {
    self::boot();
    $perms = self::$policies[$role] ?? [];

    // Wildcard — superuser
    if (in_array('*', $perms, true)) {
      return true;
    }

    // Exact match
    if (in_array($permission, $perms, true)) {
      return true;
    }

    // Category wildcard: 'finance.*' matches 'finance.view'
    $category = explode('.', $permission)[0] ?? '';
    if ($category && in_array($category . '.*', $perms, true)) {
      return true;
    }

    return false;
  }

  /**
   * Enforce permission — abort with 403 if denied.
   */
  public static function check(string $role, string $permission): void
  {
    if (!self::can($role, $permission)) {
      http_response_code(403);
      if (defined('BASE_PATH') && is_file(BASE_PATH . '/resources/errors/403.php')) {
        include BASE_PATH . '/resources/errors/403.php';
      } else {
        echo '<h1>403 — Forbidden</h1><p>You do not have permission to access this resource.</p>';
      }
      exit;
    }
  }

  /**
   * Check permission for the current session user.
   */
  public static function authorize(string $permission): void
  {
    $role = $_SESSION['role'] ?? ($_SESSION['user_role'] ?? '');
    self::check($role, $permission);
  }

  /**
   * Check if current session user has permission (boolean).
   */
  public static function allowed(string $permission): bool
  {
    $role = $_SESSION['role'] ?? ($_SESSION['user_role'] ?? '');
    return self::can($role, $permission);
  }

  /**
   * Get all permissions for a role.
   *
   * @return string[]
   */
  public static function forRole(string $role): array
  {
    self::boot();
    return self::$policies[$role] ?? [];
  }

  /**
   * Get all roles that have a given permission.
   *
   * @return string[]
   */
  public static function rolesFor(string $permission): array
  {
    self::boot();
    $roles = [];
    foreach (self::$policies as $role => $perms) {
      if (self::can($role, $permission)) {
        $roles[] = $role;
      }
    }
    return $roles;
  }

  /**
   * Get the full policy matrix (for admin UI).
   *
   * @return array<string, string[]>
   */
  public static function all(): array
  {
    self::boot();
    return self::$policies;
  }
}
