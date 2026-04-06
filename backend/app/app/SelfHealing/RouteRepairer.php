<?php

/**
 * RouteRepairer — Fixes broken routes, missing links, invalid paths.
 *
 * Scans anchor links, validates file existence, rewrites incorrect paths,
 * and maintains a cached route index at cache/routes.json.
 */
class RouteRepairer
{
  private static string $indexPath = '';

  private static function init(): void
  {
    if (!self::$indexPath) {
      self::$indexPath = BASE_PATH . '/cache/routes.json';
    }
  }

  /**
   * Rebuild the route index by scanning all routable PHP files.
   */
  public static function rebuildRouteIndex(): array
  {
    self::init();
    $routes = [];

    $scanDirs = [
      ''           => '/',
      'admin'      => '/admin/',
      'developer'  => '/developer/',
      'teacher'    => '/teacher/',
      'student'    => '/student/',
      'parent'     => '/parent/',
      'accountant' => '/accountant/',
      'librarian'  => '/librarian/',
      'bursar'     => '/bursar/',
      'transport'  => '/transport/',
      'api'        => '/api/',
      'ecosystem'  => '/ecosystem/',
      'public-ai'  => '/public-ai/',
    ];

    foreach ($scanDirs as $dir => $prefix) {
      $scanPath = BASE_PATH . ($dir ? '/' . $dir : '');
      if (!is_dir($scanPath)) continue;

      $files = glob($scanPath . '/*.php');
      foreach ($files as $file) {
        $basename = basename($file);
        $routePath = $prefix . $basename;
        $routes[$routePath] = [
          'file'   => ($dir ? $dir . '/' : '') . $basename,
          'exists' => true,
          'size'   => filesize($file),
        ];
      }
    }

    // Developer tabs
    $tabDir = BASE_PATH . '/developer/tabs';
    if (is_dir($tabDir)) {
      foreach (glob($tabDir . '/*.php') as $tab) {
        $name = basename($tab, '.php');
        $routes['/developer/settings?tab=' . $name] = [
          'file'   => 'developer/tabs/' . basename($tab),
          'exists' => true,
          'size'   => filesize($tab),
        ];
      }
    }

    // Persist
    $cacheDir = dirname(self::$indexPath);
    if (!is_dir($cacheDir)) mkdir($cacheDir, 0755, true);
    file_put_contents(self::$indexPath, json_encode($routes, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    ErrorCollector::log('self_healing', 'Route index rebuilt: ' . count($routes) . ' routes', 'INFO');

    return $routes;
  }

  /**
   * Get the cached route index.
   */
  public static function getRouteIndex(): array
  {
    self::init();
    if (!file_exists(self::$indexPath)) {
      return self::rebuildRouteIndex();
    }
    return json_decode(file_get_contents(self::$indexPath), true) ?: [];
  }

  /**
   * Validate a route path.
   */
  public static function validateRoute(string $path): array
  {
    $index = self::getRouteIndex();

    // Normalize
    $normalized = '/' . ltrim($path, '/');
    if (isset($index[$normalized])) {
      return ['valid' => true, 'route' => $index[$normalized]];
    }

    // Try with .php extension
    if (strpos($normalized, '.php') === false) {
      $withExt = $normalized . '.php';
      if (isset($index[$withExt])) {
        return ['valid' => true, 'route' => $index[$withExt], 'redirect' => $withExt];
      }
    }

    // Try file existence directly
    $filePath = BASE_PATH . $normalized;
    if (file_exists($filePath)) {
      return ['valid' => true, 'route' => ['file' => ltrim($normalized, '/'), 'exists' => true]];
    }

    return ['valid' => false, 'suggestion' => self::findClosest($normalized, array_keys($index))];
  }

  /**
   * Find broken links in a PHP file.
   */
  public static function scanFileForBrokenLinks(string $relPath): array
  {
    $fullPath = BASE_PATH . '/' . $relPath;
    if (!file_exists($fullPath)) return [];

    $content = file_get_contents($fullPath);
    $broken = [];

    // Match href="..." patterns
    if (preg_match_all('/href=["\']([^"\'#]+)["\']/', $content, $matches)) {
      foreach ($matches[1] as $link) {
        // Skip external links, javascript, mailto
        if (preg_match('#^(https?://|javascript:|mailto:|\#)#i', $link)) continue;

        // Resolve relative path
        $resolved = self::resolveLink($link, $relPath);
        if ($resolved && !file_exists(BASE_PATH . '/' . $resolved)) {
          $broken[] = ['link' => $link, 'resolved' => $resolved];
        }
      }
    }

    return $broken;
  }

  /**
   * Resolve a relative link from a source file.
   */
  private static function resolveLink(string $link, string $fromFile): ?string
  {
    // Strip query string
    $link = strtok($link, '?');
    if (!$link) return null;

    // Remove leading /attendance/ base
    $link = preg_replace('#^/attendance/#', '', $link);
    $link = ltrim($link, '/');

    if (!$link) return null;

    // If starts with ../, resolve relative to source directory
    if (str_starts_with($link, '../')) {
      $dir = dirname($fromFile);
      return ltrim($dir . '/' . $link, '/');
    }

    return $link;
  }

  /**
   * Find closest matching route for suggestions.
   */
  private static function findClosest(string $target, array $routes): ?string
  {
    $best = null;
    $bestDist = PHP_INT_MAX;
    foreach ($routes as $r) {
      $d = levenshtein($target, $r);
      if ($d < $bestDist && $d <= 5) {
        $bestDist = $d;
        $best = $r;
      }
    }
    return $best;
  }

  public static function getSummary(): array
  {
    $index = self::getRouteIndex();
    return [
      'total_routes' => count($index),
      'index_age'    => file_exists(self::$indexPath ?? '') ? time() - filemtime(self::$indexPath) : -1,
    ];
  }
}
