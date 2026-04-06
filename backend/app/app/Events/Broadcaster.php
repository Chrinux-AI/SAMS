<?php

/**
 * SSE Broadcaster — Pushes events to connected SSE clients.
 * Writes events to a broadcast table that api/sse.php polls.
 */
class Broadcaster
{
  /**
   * Broadcast an event to specific users via SSE.
   *
   * @param string $channel  Channel name (e.g., 'user.{id}', 'role.admin', 'global')
   * @param string $event    Event type name
   * @param array  $data     Event payload
   * @param array  $userIds  Specific user IDs to target (empty = channel-based)
   */
  public static function send(string $channel, string $event, array $data = [], array $userIds = []): void
  {
    $payload = json_encode([
      'event'   => $event,
      'channel' => $channel,
      'data'    => $data,
      'time'    => date('c'),
    ], JSON_UNESCAPED_UNICODE);

    try {
      if (!empty($userIds)) {
        // Send to specific users
        foreach ($userIds as $uid) {
          db()->insert('broadcast_events', [
            'channel'    => "user.{$uid}",
            'event_type' => $event,
            'payload'    => $payload,
            'target_user_id' => (int) $uid,
            'created_at' => date('Y-m-d H:i:s'),
          ]);
        }
      } else {
        // Send to channel
        db()->insert('broadcast_events', [
          'channel'    => $channel,
          'event_type' => $event,
          'payload'    => $payload,
          'target_user_id' => null,
          'created_at' => date('Y-m-d H:i:s'),
        ]);
      }
    } catch (\Throwable $e) {
      // Broadcast table may not exist yet — degrade gracefully
      error_log("Broadcaster failed: " . $e->getMessage());
    }
  }

  /**
   * Broadcast to all users with a specific role.
   */
  public static function toRole(string $role, string $event, array $data = []): void
  {
    self::send("role.{$role}", $event, $data);
  }

  /**
   * Broadcast to a specific user.
   */
  public static function toUser(int $userId, string $event, array $data = []): void
  {
    self::send("user.{$userId}", $event, $data, [$userId]);
  }

  /**
   * Broadcast to all connected users.
   */
  public static function toAll(string $event, array $data = []): void
  {
    self::send('global', $event, $data);
  }

  /**
   * Fetch pending events for a user (called by SSE endpoint).
   *
   * @param int    $userId      User ID
   * @param string $role        User's role
   * @param int    $lastEventId Last event ID received by client
   * @return array
   */
  public static function fetchPending(int $userId, string $role, int $lastEventId = 0): array
  {
    try {
      return db()->fetchAll(
        "SELECT * FROM broadcast_events
                 WHERE id > :last_id
                   AND (target_user_id = :uid OR channel = :global OR channel = :role_ch)
                   AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
                 ORDER BY id ASC
                 LIMIT 50",
        [
          'last_id'  => $lastEventId,
          'uid'      => $userId,
          'global'   => 'global',
          'role_ch'  => "role.{$role}",
        ]
      );
    } catch (\Throwable $e) {
      return [];
    }
  }

  /**
   * Clean up old broadcast events (call from cron).
   */
  public static function cleanup(int $minutes = 10): int
  {
    try {
      $stmt = db()->query(
        "DELETE FROM broadcast_events WHERE created_at < DATE_SUB(NOW(), INTERVAL :mins MINUTE)",
        ['mins' => $minutes]
      );
      return $stmt ? $stmt->rowCount() : 0;
    } catch (\Throwable $e) {
      return 0;
    }
  }
}
