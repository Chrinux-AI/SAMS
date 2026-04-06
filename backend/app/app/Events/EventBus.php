<?php

/**
 * EventBus — Real-Time Update Propagation
 *
 * Flow: Update → Event Dispatch → Cache Clear → Live Refresh
 *
 * Uses AJAX polling (short term), WebSocket upgrade path (future).
 */
class EventBus
{
  private static string $eventFile = '';

  /**
   * Ensure event storage exists.
   */
  private static function ensureStorage(): void
  {
    if (!self::$eventFile) {
      self::$eventFile = BASE_PATH . '/storage/event-bus.json';
    }
    $dir = dirname(self::$eventFile);
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    if (!is_file(self::$eventFile)) {
      file_put_contents(self::$eventFile, json_encode([]));
    }
  }

  /**
   * Dispatch an event.
   */
  public static function dispatch(string $channel, string $event, array $payload = []): void
  {
    self::ensureStorage();

    $entry = [
      'id'        => bin2hex(random_bytes(8)),
      'channel'   => $channel,
      'event'     => $event,
      'payload'   => $payload,
      'timestamp' => time(),
      'datetime'  => date('Y-m-d H:i:s'),
    ];

    // Append to event log (keep last 200)
    $events = self::loadEvents();
    $events[] = $entry;
    $events = array_slice($events, -200);
    file_put_contents(self::$eventFile, json_encode($events, JSON_PRETTY_PRINT), LOCK_EX);

    // Clear relevant caches
    self::clearCache($channel);
  }

  /**
   * Poll for events since a given timestamp.
   */
  public static function poll(int $since, string $channel = ''): array
  {
    self::ensureStorage();
    $events = self::loadEvents();

    $filtered = [];
    foreach ($events as $e) {
      if (($e['timestamp'] ?? 0) <= $since) continue;
      if ($channel && ($e['channel'] ?? '') !== $channel) continue;
      $filtered[] = $e;
    }

    return $filtered;
  }

  /**
   * Get recent events.
   */
  public static function getRecent(int $limit = 20, string $channel = ''): array
  {
    self::ensureStorage();
    $events = self::loadEvents();

    if ($channel) {
      $events = array_filter($events, fn($e) => ($e['channel'] ?? '') === $channel);
    }

    return array_slice(array_values($events), -$limit);
  }

  /**
   * Clear cache for a channel.
   */
  private static function clearCache(string $channel): void
  {
    $cacheDir = BASE_PATH . '/cache';
    if (!is_dir($cacheDir)) return;

    // Clear channel-specific cache files
    $pattern = $cacheDir . '/' . preg_replace('/[^a-zA-Z0-9_]/', '_', $channel) . '_*.cache';
    foreach (glob($pattern) as $file) {
      @unlink($file);
    }
  }

  /**
   * Load events from storage.
   */
  private static function loadEvents(): array
  {
    if (!is_file(self::$eventFile)) return [];
    $data = json_decode(file_get_contents(self::$eventFile), true);
    return is_array($data) ? $data : [];
  }

  /**
   * Prune old events.
   */
  public static function prune(int $maxAgeSeconds = 3600): int
  {
    self::ensureStorage();
    $events = self::loadEvents();
    $cutoff = time() - $maxAgeSeconds;
    $before = count($events);

    $events = array_filter($events, fn($e) => ($e['timestamp'] ?? 0) >= $cutoff);
    file_put_contents(self::$eventFile, json_encode(array_values($events), JSON_PRETTY_PRINT), LOCK_EX);

    return $before - count($events);
  }

  /**
   * Get summary for dashboard.
   */
  public static function getSummary(): array
  {
    self::ensureStorage();
    $events = self::loadEvents();
    $recentHour = array_filter($events, fn($e) => ($e['timestamp'] ?? 0) >= time() - 3600);

    $channels = [];
    foreach ($recentHour as $e) {
      $ch = $e['channel'] ?? 'unknown';
      $channels[$ch] = ($channels[$ch] ?? 0) + 1;
    }

    return [
      'total_events'   => count($events),
      'last_hour'      => count($recentHour),
      'channels'       => $channels,
      'status'         => 'active',
    ];
  }
}
