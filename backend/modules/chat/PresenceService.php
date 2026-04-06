<?php

/**
 * PresenceService — Online / Typing Indicators
 *
 * Tracks user presence status (online/away/offline) and typing indicators.
 * Uses session-based storage for lightweight real-time presence.
 */
class PresenceService
{
  private static string $presencePath = '';

  private static function init(): void
  {
    if (!self::$presencePath) {
      self::$presencePath = BASE_PATH . '/storage/presence.json';
    }
  }

  /**
   * Heartbeat — user is active.
   */
  public static function heartbeat(int $userId): void
  {
    self::init();
    $data = self::loadPresence();
    $data[$userId] = [
      'status'     => 'online',
      'last_seen'  => time(),
      'typing_in'  => $data[$userId]['typing_in'] ?? null,
    ];
    self::savePresence($data);
  }

  /**
   * Set typing indicator.
   */
  public static function setTyping(int $userId, ?int $conversationId): void
  {
    self::init();
    $data = self::loadPresence();
    if (!isset($data[$userId])) {
      $data[$userId] = ['status' => 'online', 'last_seen' => time()];
    }
    $data[$userId]['typing_in']  = $conversationId;
    $data[$userId]['typing_at']  = $conversationId ? time() : null;
    self::savePresence($data);
  }

  /**
   * Get presence status for a user.
   */
  public static function getStatus(int $userId): string
  {
    self::init();
    $data = self::loadPresence();
    if (!isset($data[$userId])) return 'offline';

    $lastSeen = $data[$userId]['last_seen'] ?? 0;
    $elapsed  = time() - $lastSeen;

    if ($elapsed < 120) return 'online';
    if ($elapsed < 600) return 'away';
    return 'offline';
  }

  /**
   * Get presence for multiple users.
   */
  public static function getMultiple(array $userIds): array
  {
    self::init();
    $data   = self::loadPresence();
    $result = [];

    foreach ($userIds as $uid) {
      $uid = (int) $uid;
      if (!isset($data[$uid])) {
        $result[$uid] = ['status' => 'offline', 'typing' => false];
        continue;
      }

      $lastSeen = $data[$uid]['last_seen'] ?? 0;
      $elapsed  = time() - $lastSeen;

      $status = 'offline';
      if ($elapsed < 120) $status = 'online';
      elseif ($elapsed < 600) $status = 'away';

      $typing = false;
      if (isset($data[$uid]['typing_in']) && $data[$uid]['typing_in']) {
        $typingElapsed = time() - ($data[$uid]['typing_at'] ?? 0);
        $typing = ($typingElapsed < 5);
      }

      $result[$uid] = ['status' => $status, 'typing' => $typing];
    }

    return $result;
  }

  /**
   * Get who is typing in a conversation.
   */
  public static function getTypingIn(int $conversationId): array
  {
    self::init();
    $data   = self::loadPresence();
    $typing = [];

    foreach ($data as $uid => $info) {
      if (($info['typing_in'] ?? 0) === $conversationId) {
        $elapsed = time() - ($info['typing_at'] ?? 0);
        if ($elapsed < 5) {
          $typing[] = (int) $uid;
        }
      }
    }

    return $typing;
  }

  /**
   * Get online user count.
   */
  public static function getOnlineCount(): int
  {
    self::init();
    $data  = self::loadPresence();
    $count = 0;
    foreach ($data as $info) {
      $elapsed = time() - ($info['last_seen'] ?? 0);
      if ($elapsed < 120) $count++;
    }
    return $count;
  }

  /**
   * Prune stale entries (>1 hour).
   */
  public static function prune(): int
  {
    self::init();
    $data    = self::loadPresence();
    $before  = count($data);
    $cutoff  = time() - 3600;
    $data    = array_filter($data, fn($info) => ($info['last_seen'] ?? 0) > $cutoff);
    self::savePresence($data);
    return $before - count($data);
  }

  private static function loadPresence(): array
  {
    self::init();
    if (!is_file(self::$presencePath)) return [];
    $data = json_decode(file_get_contents(self::$presencePath), true);
    return is_array($data) ? $data : [];
  }

  private static function savePresence(array $data): void
  {
    self::init();
    $dir = dirname(self::$presencePath);
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    file_put_contents(self::$presencePath, json_encode($data, JSON_PRETTY_PRINT), LOCK_EX);
  }
}
