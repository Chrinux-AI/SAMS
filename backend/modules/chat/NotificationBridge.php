<?php

/**
 * NotificationBridge — Push / Email / In-App Notification Bridge
 *
 * Bridges chat events to the notification system. Dispatches in-app notifications,
 * emails (if configured), and WhatsApp alerts (if configured) for new messages.
 */
class NotificationBridge
{
  /**
   * Handle a new message event.
   */
  public static function onNewMessage(int $conversationId, int $senderId, string $body, int $messageId): void
  {
    try {
      $participants = Conversations::getParticipants($conversationId);
      $sender       = null;

      foreach ($participants as $p) {
        if ((int) $p['id'] === $senderId) {
          $sender = $p;
          break;
        }
      }

      $senderName = $sender
        ? trim(($sender['first_name'] ?? '') . ' ' . ($sender['last_name'] ?? ''))
        : 'Someone';

      if ($senderName === '') $senderName = $sender['username'] ?? 'User';

      // In-app notification for each participant (except sender)
      foreach ($participants as $p) {
        if ((int) $p['id'] === $senderId) continue;

        self::sendInApp(
          (int) $p['id'],
          "New message from {$senderName}",
          mb_substr($body, 0, 100)
        );
      }

      // Log the notification dispatch
      EventBus::dispatch('notifications', 'chat_notification_sent', [
        'conversation_id' => $conversationId,
        'message_id'      => $messageId,
        'recipients'      => count($participants) - 1,
      ]);
    } catch (\Throwable $e) {
      ErrorCollector::log('notification_bridge', 'Chat notification failed: ' . $e->getMessage(), 'LOW');
    }
  }

  /**
   * Send an in-app notification.
   */
  public static function sendInApp(int $userId, string $title, string $message): void
  {
    try {
      CommunicationOS::send('notification', [$userId], $title, $message);
    } catch (\Throwable $e) {
      // Silently fail — chat should not break on notification failure
    }
  }

  /**
   * Broadcast an announcement notification to a role group.
   */
  public static function broadcastToRole(string $role, string $title, string $message): int
  {
    try {
      $users = db()->fetchAll("SELECT id FROM users WHERE role = ? AND status = 'active'", [$role]);
      $ids   = array_column($users, 'id');
      if (empty($ids)) return 0;

      CommunicationOS::send('notification', $ids, $title, $message);
      return count($ids);
    } catch (\Throwable $e) {
      return 0;
    }
  }

  /**
   * Get notification summary for a user.
   */
  public static function getSummary(int $userId): array
  {
    return [
      'unread_messages'       => MessageQueue::getUnreadCount($userId),
      'unread_notifications'  => CommunicationOS::getUnreadCount($userId),
    ];
  }
}
