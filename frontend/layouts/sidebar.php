<?php

/**
 * Unified Sidebar Include
 *
 * This file centralizes sidebar inclusion across all pages.
 * Instead of each page having its own sidebar logic or varying include paths,
 * all pages include this file which delegates to the single source of truth.
 *
 * Usage from any page:
 *   require_once BASE_PATH . '/layouts/sidebar.php';
 *   OR
 *   require_once __DIR__ . '/../layouts/sidebar.php';  (from role directories)
 *
 * The actual sidebar implementation lives in includes/sidebar-nav.php.
 * This wrapper ensures consistent loading regardless of calling page location.
 */

if (!defined('BASE_PATH')) {
  define('BASE_PATH', dirname(__DIR__));
}

// Load the URL helper for route-based links if available
if (!function_exists('named_route')) {
  $routeMap = BASE_PATH . '/config/routes.php';
  if (is_file($routeMap)) {
    require_once $routeMap;
  }
}

// Include the single source of truth sidebar
require_once BASE_PATH . '/includes/sidebar-nav.php';
