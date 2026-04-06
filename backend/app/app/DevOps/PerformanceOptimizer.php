<?php

/**
 * PerformanceOptimizer — Automatic Performance Tuning
 *
 * Detects and resolves performance bottlenecks:
 * - Cache intelligence (auto-cache frequent queries, invalidate on change)
 * - Response compression (gzip headers)
 * - Asset optimization guidance
 * - Query pattern analysis
 */
class PerformanceOptimizer
{
  /** Cache directory */
  private static function cachePath(): string
  {
    $base = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);
    $path = $base . '/cache/devops';
    if (!is_dir($path)) {
      mkdir($path, 0755, true);
    }
    return $path;
  }

  /**
   * Run all performance optimizations.
   *
   * @return array{actions: array, summary: string}
   */
  public static function optimize(): array
  {
    $actions = [];

    $actions = array_merge($actions, self::optimizeResponseHeaders());
    $actions = array_merge($actions, self::cleanExpiredCache());
    $actions = array_merge($actions, self::analyzeSlowEndpoints());
    $actions = array_merge($actions, self::optimizeSessionGarbage());

    $summary = count($actions) . ' optimization(s) applied';
    return ['actions' => $actions, 'summary' => $summary];
  }

  /**
   * Cache a value with automatic TTL.
   */
  public static function cacheGet(string $key, callable $generator, int $ttlSeconds = 300)
  {
    $file = self::cachePath() . '/' . md5($key) . '.cache';
    if (is_file($file) && (time() - filemtime($file)) < $ttlSeconds) {
      $data = @file_get_contents($file);
      if ($data !== false) {
        $decoded = @unserialize($data);
        if ($decoded !== false) return $decoded;
      }
    }

    $value = $generator();
    @file_put_contents($file, serialize($value), LOCK_EX);
    return $value;
  }

  /**
   * Invalidate a cache key or all cache.
   */
  public static function cacheInvalidate(?string $key = null): void
  {
    if ($key !== null) {
      $file = self::cachePath() . '/' . md5($key) . '.cache';
      if (is_file($file)) @unlink($file);
    } else {
      $files = glob(self::cachePath() . '/*.cache');
      if ($files) {
        foreach ($files as $f) @unlink($f);
      }
    }
  }

  /**
   * Ensure gzip/compression headers are set for PHP responses.
   */
  private static function optimizeResponseHeaders(): array
  {
    $actions = [];

    if (!headers_sent() && php_sapi_name() !== 'cli') {
      if (extension_loaded('zlib') && !ini_get('zlib.output_compression')) {
        @ini_set('zlib.output_compression', 'On');
        $actions[] = ['type' => 'compression', 'action' => 'Enabled zlib output compression', 'success' => true];
      }
    }

    return $actions;
  }

  /**
   * Clean expired cache entries.
   */
  private static function cleanExpiredCache(): array
  {
    $actions = [];
    $cleaned = 0;
    $maxAge = 3600; // 1 hour max for stale cache

    $files = glob(self::cachePath() . '/*.cache');
    if ($files) {
      foreach ($files as $file) {
        if ((time() - filemtime($file)) > $maxAge) {
          @unlink($file);
          $cleaned++;
        }
      }
    }

    // Also clean main cache directory
    $base = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);
    $mainCache = $base . '/cache';
    if (is_dir($mainCache)) {
      foreach (['*.json', '*.tmp'] as $pattern) {
        $cacheFiles = glob($mainCache . '/' . $pattern);
        if ($cacheFiles) {
          foreach ($cacheFiles as $f) {
            if ((time() - filemtime($f)) > $maxAge) {
              @unlink($f);
              $cleaned++;
            }
          }
        }
      }
    }

    if ($cleaned > 0) {
      $actions[] = ['type' => 'cache_cleanup', 'action' => "Cleaned {$cleaned} expired cache file(s)", 'success' => true];
    }

    return $actions;
  }

  /**
   * Analyze slow endpoints from error logs.
   */
  private static function analyzeSlowEndpoints(): array
  {
    $actions = [];

    try {
      if (!function_exists('table_exists') || !table_exists('system_metrics')) {
        return $actions;
      }

      // Check for DB latency warnings in recent metrics
      $slowMetrics = db()->fetchAll(
        "SELECT metric, value, recorded_at FROM system_metrics
                 WHERE metric = 'db_latency' AND value > 500
                 AND recorded_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
                 ORDER BY recorded_at DESC LIMIT 5"
      );

      if (count($slowMetrics) >= 3) {
        $actions[] = [
          'type'    => 'performance_warning',
          'action'  => 'DB latency exceeded 500ms ' . count($slowMetrics) . ' times in last hour',
          'success' => false,
        ];
      }
    } catch (\Throwable $e) {
      // Non-critical
    }

    return $actions;
  }

  /**
   * Clean expired PHP sessions.
   */
  private static function optimizeSessionGarbage(): array
  {
    $actions = [];
    $sessionPath = session_save_path() ?: sys_get_temp_dir();

    if (!is_dir($sessionPath) || !is_readable($sessionPath)) {
      return $actions;
    }

    $files = glob($sessionPath . '/sess_*');
    if (!$files) return $actions;

    $maxLifetime = (int)ini_get('session.gc_maxlifetime') ?: 1440;
    $cleaned = 0;
    $now = time();

    foreach ($files as $file) {
      if (($now - filemtime($file)) > $maxLifetime * 2) {
        @unlink($file);
        $cleaned++;
      }
    }

    if ($cleaned > 0) {
      $actions[] = ['type' => 'session_gc', 'action' => "Cleaned {$cleaned} expired session file(s)", 'success' => true];
    }

    return $actions;
  }

  /**
   * Get performance summary for dashboard.
   */
  public static function getSummary(): array
  {
    $cacheDir = self::cachePath();
    $cacheFiles = glob($cacheDir . '/*.cache');
    $cacheCount = $cacheFiles ? count($cacheFiles) : 0;
    $cacheSize = 0;
    if ($cacheFiles) {
      foreach ($cacheFiles as $f) $cacheSize += filesize($f);
    }

    return [
      'cache_entries'   => $cacheCount,
      'cache_size_kb'   => round($cacheSize / 1024, 1),
      'compression'     => extension_loaded('zlib'),
      'opcache'         => function_exists('opcache_get_status'),
    ];
  }
}
