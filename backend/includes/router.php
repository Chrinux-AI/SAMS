<?php

/**
 * Router — Routing Normalization
 *
 * BASE_URL defined globally. All links use absolute routing helper.
 * Replaces all relative link patterns with consistent absolute routing.
 */

if (!defined('BASE_URL')) {
  define('BASE_URL', rtrim(APP_URL, '/') . '/frontend');
}

/**
 * Generate an absolute URL from a relative path.
 *
 * @param string $path Relative path (e.g., 'admin/dashboard.php')
 * @return string Absolute URL (e.g., '/attendance/admin/dashboard.php')
 */
function route(string $path): string
{
  return BASE_URL . '/' . ltrim($path, '/');
}

/**
 * Generate a URL for an asset file.
 *
 * @param string $path Asset path (e.g., 'css/style.css')
 * @return string Absolute URL to the asset
 */
function asset(string $path): string
{
  return BASE_URL . '/assets/' . ltrim($path, '/');
}

/**
 * Redirect to a route.
 *
 * @param string $path The route path
 * @param string $message Optional flash message
 * @param string $type Message type ('success', 'error', 'info')
 */
function route_redirect(string $path, string $message = '', string $type = 'info'): void
{
  if ($message && isset($_SESSION)) {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
  }
  header('Location: ' . route($path));
  exit;
}

/**
 * Check if current page matches a route (for active nav states).
 *
 * @param string $path Route to check against
 * @return bool
 */
function is_current_route(string $path): bool
{
  $current = $_SERVER['REQUEST_URI'] ?? '';
  $target = route($path);
  return strpos($current, $target) === 0;
}

/**
 * Get the developer portal base route.
 */
function dev_route(string $path): string
{
  return route('developer/' . ltrim($path, '/'));
}
