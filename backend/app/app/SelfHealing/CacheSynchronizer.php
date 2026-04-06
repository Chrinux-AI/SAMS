<?php

/**
 * CacheSynchronizer — Ensures edits reflect immediately everywhere.
 *
 * Flow: Admin Edit → DB Commit → Cache Flush → Event Broadcast → Panels Reload.
 */
class CacheSynchronizer
{
  /**
   * Flush all caches for a given domain.
   */
  public static function flush(string $domain = 'all'): array
  {
    $flushed = [];

    if ($domain === 'all' || $domain === 'routes') {
      $flushed[] = self::flushRoutes();
    }
    if ($domain === 'all' || $domain === 'views') {
      $flushed[] = self::flushViewCache();
    }
    if ($domain === 'all' || $domain === 'data') {
      $flushed[] = self::flushDataCache();
    }
    if ($domain === 'all' || $domain === 'events') {
      $flushed[] = self::pruneEvents();
    }
    if ($domain === 'all' || $domain === 'summaries') {
      $flushed[] = self::clearStaleSummaries();
    }

    // Broadcast cache clear event
    if (class_exists('EventBus')) {
      EventBus::dispatch('system', 'cache.flushed', ['domain' => $domain, 'results' => $flushed]);
    }

    return $flushed;
  }

  /**
   * Trigger post-edit synchronization for a specific entity.
   */
  public static function afterEdit(string $entity, int $entityId, string $action = 'updated'): void
  {
    // Clear related caches
    self::flushDataCache();

    // Broadcast change event
    if (class_exists('EventBus')) {
      EventBus::dispatch($entity, $action, ['id' => $entityId]);
    }

    ErrorCollector::log('cache_sync', "Post-edit sync: $entity #$entityId $action", 'INFO');
  }

  /**
   * Flush route cache.
   */
  private static function flushRoutes(): string
  {
    $routeCache = BASE_PATH . '/cache/routes.json';
    if (file_exists($routeCache)) {
      unlink($routeCache);
      return 'routes: cleared';
    }
    return 'routes: no cache';
  }

  /**
   * Flush view/template cache files.
   */
  private static function flushViewCache(): string
  {
    $cacheDir = BASE_PATH . '/cache';
    if (!is_dir($cacheDir)) return 'views: no cache dir';

    $count = 0;
    $files = glob($cacheDir . '/*.cache');
    foreach ($files as $f) {
      unlink($f);
      $count++;
    }
    return "views: cleared $count files";
  }

  /**
   * Flush data caches (JSON summary files regenerated on next run).
   */
  private static function flushDataCache(): string
  {
    $cacheFiles = glob(BASE_PATH . '/cache/*.json');
    $count = 0;
    foreach ($cacheFiles as $f) {
      // Don't delete route index — that's rebuilt separately
      if (basename($f) === 'routes.json') continue;
      unlink($f);
      $count++;
    }
    return "data: cleared $count files";
  }

  /**
   * Prune old events from EventBus.
   */
  private static function pruneEvents(): string
  {
    if (!class_exists('EventBus')) return 'events: EventBus unavailable';

    try {
      EventBus::prune(3600); // Keep last hour
      return 'events: pruned';
    } catch (\Throwable $e) {
      return 'events: prune failed';
    }
  }

  /**
   * Clear stale AI summary files so they regenerate fresh.
   */
  private static function clearStaleSummaries(): string
  {
    $summaries = [
      'storage/ecosystem-summary.json',
      'storage/cognitive-summary.json',
      'storage/intelligence-summary.json',
    ];

    $cleared = 0;
    foreach ($summaries as $s) {
      $path = BASE_PATH . '/' . $s;
      if (file_exists($path)) {
        $age = time() - filemtime($path);
        if ($age > 86400) { // Stale > 24 hours
          unlink($path);
          $cleared++;
        }
      }
    }
    return "summaries: cleared $cleared stale files";
  }

  public static function getSummary(): array
  {
    $cacheDir = BASE_PATH . '/cache';
    $cacheFiles = is_dir($cacheDir) ? count(glob($cacheDir . '/*')) : 0;
    return [
      'cache_files'   => $cacheFiles,
      'cache_writable' => is_dir($cacheDir) && is_writable($cacheDir),
    ];
  }
}
