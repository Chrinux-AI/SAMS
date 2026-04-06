<?php

/**
 * Event Dispatcher — Central event bus for the SAMS system.
 * Decouples components via observer pattern.
 *
 * Usage:
 *   EventDispatcher::listen('StudentUpdated', function($data) { ... });
 *   EventDispatcher::dispatch('StudentUpdated', ['student_id' => 1, ...]);
 */
class EventDispatcher
{
  /** @var array<string, array<callable>> Registered listeners */
  private static array $listeners = [];

  /** @var array Queue of events to dispatch asynchronously */
  private static array $queue = [];

  /** @var bool Whether to log dispatched events */
  private static bool $logging = true;

  /**
   * Register a listener for an event.
   *
   * @param string   $event    Event name (e.g., 'StudentUpdated')
   * @param callable $listener Callback function receiving event data array
   * @param int      $priority Higher = earlier execution (default: 0)
   */
  public static function listen(string $event, callable $listener, int $priority = 0): void
  {
    self::$listeners[$event][] = [
      'callback' => $listener,
      'priority' => $priority,
    ];

    // Sort by priority (descending)
    usort(self::$listeners[$event], function ($a, $b) {
      return $b['priority'] - $a['priority'];
    });
  }

  /**
   * Dispatch an event immediately, calling all registered listeners.
   *
   * @param string $event Event name
   * @param array  $data  Event payload
   * @return array Results from each listener
   */
  public static function dispatch(string $event, array $data = []): array
  {
    $data['_event'] = $event;
    $data['_timestamp'] = date('c');
    $data['_user_id'] = $_SESSION['user_id'] ?? null;

    $results = [];

    if (isset(self::$listeners[$event])) {
      foreach (self::$listeners[$event] as $entry) {
        try {
          $results[] = call_user_func($entry['callback'], $data);
        } catch (\Throwable $e) {
          error_log("Event listener error [{$event}]: " . $e->getMessage());
          $results[] = ['error' => $e->getMessage()];
        }
      }
    }

    // Log the dispatch
    if (self::$logging) {
      self::logEvent($event, $data);
    }

    return $results;
  }

  /**
   * Queue an event for deferred dispatch (end of request).
   */
  public static function queue(string $event, array $data = []): void
  {
    self::$queue[] = ['event' => $event, 'data' => $data];
  }

  /**
   * Flush and dispatch all queued events.
   * Call at the end of the request lifecycle.
   */
  public static function flush(): void
  {
    while (!empty(self::$queue)) {
      $item = array_shift(self::$queue);
      self::dispatch($item['event'], $item['data']);
    }
  }

  /**
   * Check if an event has any listeners.
   */
  public static function hasListeners(string $event): bool
  {
    return !empty(self::$listeners[$event]);
  }

  /**
   * Remove all listeners for an event.
   */
  public static function forget(string $event): void
  {
    unset(self::$listeners[$event]);
  }

  /**
   * Remove ALL listeners.
   */
  public static function clear(): void
  {
    self::$listeners = [];
  }

  /**
   * Get list of all registered events.
   */
  public static function getRegisteredEvents(): array
  {
    return array_keys(self::$listeners);
  }

  /**
   * Enable or disable event logging.
   */
  public static function setLogging(bool $enabled): void
  {
    self::$logging = $enabled;
  }

  /**
   * Log event dispatch to DB for audit trail.
   */
  private static function logEvent(string $event, array $data): void
  {
    // Only log significant events (not heartbeats/typing)
    $skipEvents = ['Heartbeat', 'TypingStarted', 'TypingStopped', 'OnlineStatusChanged'];
    if (in_array($event, $skipEvents, true)) {
      return;
    }

    try {
      db()->insert('system_events', [
        'event_name' => $event,
        'payload'    => json_encode(array_diff_key($data, array_flip(['_event', '_timestamp'])), JSON_UNESCAPED_UNICODE),
        'user_id'    => $data['_user_id'] ?? null,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
        'created_at' => date('Y-m-d H:i:s'),
      ]);
    } catch (\Throwable $e) {
      // system_events table may not exist — degrade gracefully
      error_log("Event log skipped ({$event}): " . $e->getMessage());
    }
  }
}
