<?php

/**
 * Notification Service — Centralized notification creation, delivery, and management.
 *
 * Features:
 *  - Create notifications for users, roles, or all
 *  - Unread counter
 *  - Instant push (via Broadcaster)
 *  - Scheduled delivery
 *  - Role targeting
 *  - Mark read / mark all read
 */
class NotificationService
{
  /** Notification types */
  public const TYPE_INFO     = 'info';
  public const TYPE_SUCCESS  = 'success';
  public const TYPE_WARNING  = 'warning';
  public const TYPE_DANGER   = 'danger';
  public const TYPE_NOTICE   = 'notice';
  public const TYPE_MESSAGE  = 'message';
  public const TYPE_SYSTEM   = 'system';

  /**
   * Send a notification to a specific user.
   */
  public static function sendToUser(
    int $userId,
    string $title,
    string $body,
    string $type = self::TYPE_INFO,
    ?string $link = null,
    ?int $referenceId = null
  ): bool {
    try {
      $id = db()->insert('user_notifications', [
        'user_id'      => $userId,
        'title'        => mb_substr($title, 0, 255),
        'body'         => mb_substr($body, 0, 1000),
        'type'         => $type,
        'link'         => $link,
        'reference_id' => $referenceId,
        'is_read'      => 0,
        'created_at'   => date('Y-m-d H:i:s'),
      ]);

      // Real-time push via Broadcaster
      if ($id && class_exists('Broadcaster')) {
        Broadcaster::toUser($userId, 'notification', [
          'id'    => $id,
          'title' => $title,
          'body'  => mb_substr($body, 0, 200),
          'type'  => $type,
          'link'  => $link,
        ]);
      }

      return (bool) $id;
    } catch (\Throwable $e) {
      error_log("NotificationService::sendToUser failed: " . $e->getMessage());
      return false;
    }
  }

  /**
   * Send a notification to all users with a specific role.
   */
  public static function sendToRole(
    string $role,
    string $title,
    string $body,
    string $type = self::TYPE_INFO,
    ?string $link = null,
    ?int $referenceId = null
  ): int {
    $count = 0;
    try {
      $users = db()->fetchAll(
        "SELECT id FROM users WHERE role = :role AND status = 'active'",
        ['role' => $role]
      );
      foreach ($users as $user) {
        if (self::sendToUser($user['id'], $title, $body, $type, $link, $referenceId)) {
          $count++;
        }
      }
    } catch (\Throwable $e) {
      error_log("NotificationService::sendToRole failed: " . $e->getMessage());
    }
    return $count;
  }

  /**
   * Send a notification to ALL active users.
   */
  public static function sendToAll(
    string $title,
    string $body,
    string $type = self::TYPE_INFO,
    ?string $link = null,
    ?int $referenceId = null,
    ?int $excludeUserId = null
  ): int {
    $count = 0;
    try {
      $users = db()->fetchAll("SELECT id FROM users WHERE status = 'active'");
      foreach ($users as $user) {
        if ($excludeUserId !== null && $user['id'] == $excludeUserId) {
          continue;
        }
        if (self::sendToUser($user['id'], $title, $body, $type, $link, $referenceId)) {
          $count++;
        }
      }
    } catch (\Throwable $e) {
      error_log("NotificationService::sendToAll failed: " . $e->getMessage());
    }
    return $count;
  }

  /**
   * Get notifications for a user with pagination.
   */
  public static function getForUser(int $userId, int $limit = 20, int $offset = 0, bool $unreadOnly = false): array
  {
    try {
      $where = "user_id = :uid";
      $params = ['uid' => $userId];

      if ($unreadOnly) {
        $where .= " AND is_read = 0";
      }

      return db()->fetchAll(
        "SELECT * FROM user_notifications
                 WHERE {$where}
                 ORDER BY created_at DESC
                 LIMIT {$limit} OFFSET {$offset}",
        $params
      );
    } catch (\Throwable $e) {
      return [];
    }
  }

  /**
   * Get unread count for a user.
   */
  public static function unreadCount(int $userId): int
  {
    try {
      return db()->count('user_notifications', 'user_id = :uid AND is_read = 0', ['uid' => $userId]);
    } catch (\Throwable $e) {
      return 0;
    }
  }

  /**
   * Mark a single notification as read.
   */
  public static function markRead(int $notificationId, int $userId): bool
  {
    try {
      return db()->update(
        'user_notifications',
        ['is_read' => 1, 'read_at' => date('Y-m-d H:i:s')],
        'id = :id AND user_id = :uid',
        ['id' => $notificationId, 'uid' => $userId]
      );
    } catch (\Throwable $e) {
      return false;
    }
  }

  /**
   * Mark all notifications as read for a user.
   */
  public static function markAllRead(int $userId): bool
  {
    try {
      db()->query(
        "UPDATE user_notifications SET is_read = 1, read_at = :now WHERE user_id = :uid AND is_read = 0",
        ['now' => date('Y-m-d H:i:s'), 'uid' => $userId]
      );
      return true;
    } catch (\Throwable $e) {
      return false;
    }
  }

  /**
   * Delete old read notifications (cleanup).
   */
  public static function cleanup(int $days = 30): int
  {
    try {
      $cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));
      $stmt = db()->query(
        "DELETE FROM user_notifications WHERE is_read = 1 AND created_at < :cutoff",
        ['cutoff' => $cutoff]
      );
      return $stmt ? $stmt->rowCount() : 0;
    } catch (\Throwable $e) {
      return 0;
    }
  }
}
