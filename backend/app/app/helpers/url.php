<?php

/**
 * URL Helper — Dynamic route resolution and link generation utilities.
 * Extends the router with named route support, role-based dashboards, and breadcrumbs.
 *
 * Requires: includes/router.php (for route()), config/routes.php (for named_route())
 */

// Load route map if not already loaded
if (!function_exists('named_route')) {
  require_once (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2)) . '/config/routes.php';
}

/**
 * Get the dashboard URL for a given role.
 */
function role_dashboard(string $role): string
{
  $dashboards = [
    'admin'           => 'admin/dashboard.php',
    'teacher'         => 'teacher/dashboard.php',
    'student'         => 'student/dashboard.php',
    'parent'          => 'parent/dashboard.php',
    'developer'       => 'developer/index.php',
    'accountant'      => 'accountant/index.php?page=dashboard',
    'bursar'          => 'bursar/dashboard.php',
    'librarian'       => 'librarian/dashboard.php',
    'transport'       => 'transport/dashboard.php',
    'forum_moderator' => 'forum-moderator/dashboard.php',
  ];
  return route($dashboards[$role] ?? 'login.php');
}

/**
 * Get the dashboard URL for the currently logged-in user.
 */
function my_dashboard(): string
{
  $role = $_SESSION['role'] ?? $_SESSION['user_role'] ?? '';
  return role_dashboard($role);
}

/**
 * Generate a link to a page within the current user's role directory.
 * e.g., role_link('attendance.php') → '/attendance/admin/attendance.php' if role=admin
 */
function role_link(string $page): string
{
  $role = $_SESSION['role'] ?? $_SESSION['user_role'] ?? '';
  $dir = match ($role) {
    'forum_moderator' => 'forum-moderator',
    default           => $role,
  };
  return route($dir . '/' . ltrim($page, '/'));
}

/**
 * Generate a communication module link.
 */
function comm_link(string $page): string
{
  return route('communication/' . ltrim($page, '/'));
}

/**
 * Generate a forum link.
 */
function forum_link(string $page): string
{
  return route('forum/' . ltrim($page, '/'));
}

/**
 * Generate an API endpoint URL.
 */
function api_url(string $path): string
{
  return route('api/' . ltrim($path, '/'));
}

/**
 * Build a URL with query parameters.
 */
function route_with_params(string $path, array $params = []): string
{
  $url = route($path);
  if (!empty($params)) {
    $url .= '?' . http_build_query($params);
  }
  return $url;
}

/**
 * Check if a file path exists relative to BASE_PATH.
 */
function page_exists(string $path): bool
{
  $full = (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2)) . '/' . ltrim($path, '/');
  return is_file($full);
}

/**
 * Safe link: returns the route if the page exists, otherwise returns the dashboard.
 */
function safe_link(string $path): string
{
  if (page_exists($path)) {
    return route($path);
  }
  return my_dashboard();
}
