<?php

/**
 * RealtimeGateway — AJAX Polling Gateway for Chat
 *
 * Provides real-time message delivery via AJAX long-polling.
 * Designed with WebSocket upgrade path in mind.
 */
class RealtimeGateway
{
  /**
   * Poll for new messages in a conversation.
   */
  public static function poll(int $conversationId, int $userId, int $sinceId = 0): array
  {
    Conversations::ensureTables();

    // Verify participant
    if (!Conversations::isParticipant($conversationId, $userId)) {
      return ['success' => false, 'error' => 'Not a participant'];
    }

    try {
      // Get new messages
      $sql = "SELECT m.*, u.first_name, u.last_name, u.username, u.role as sender_role
              FROM comm_messages m
              JOIN users u ON u.id = m.sender_id
              WHERE m.conversation_id = ? AND m.id > ?
              ORDER BY m.created_at ASC
              LIMIT 50";

      $messages = db()->fetchAll($sql, [$conversationId, $sinceId]);

      // Get typing indicators
      $typing = PresenceService::getTypingIn($conversationId);
      $typing = array_filter($typing, fn($uid) => $uid !== $userId);

      // Update presence
      PresenceService::heartbeat($userId);

      // Mark messages as read
      foreach ($messages as $msg) {
        if ((int) $msg['sender_id'] !== $userId) {
          MessageQueue::markRead((int) $msg['id'], $userId);
        }
      }

      return [
        'success'  => true,
        'messages' => $messages,
        'typing'   => array_values($typing),
        'count'    => count($messages),
      ];
    } catch (\Throwable $e) {
      return ['success' => false, 'error' => $e->getMessage()];
    }
  }

  /**
   * Poll for conversation list updates.
   */
  public static function pollConversations(int $userId): array
  {
    try {
      PresenceService::heartbeat($userId);

      $conversations = Conversations::getForUser($userId, 20);
      $totalUnread   = MessageQueue::getUnreadCount($userId);

      return [
        'success'       => true,
        'conversations' => $conversations,
        'total_unread'  => $totalUnread,
        'online_count'  => PresenceService::getOnlineCount(),
      ];
    } catch (\Throwable $e) {
      return ['success' => false, 'error' => $e->getMessage()];
    }
  }

  /**
   * Send a typing indicator.
   */
  public static function sendTyping(int $userId, int $conversationId): array
  {
    PresenceService::setTyping($userId, $conversationId);
    return ['success' => true];
  }

  /**
   * Clear typing indicator.
   */
  public static function clearTyping(int $userId): array
  {
    PresenceService::setTyping($userId, null);
    return ['success' => true];
  }

  /**
   * Get system event stream (for dashboard widgets).
   */
  public static function pollSystem(int $since = 0): array
  {
    try {
      $events = EventBus::poll($since ?: (time() - 30));
      return [
        'success' => true,
        'events'  => $events,
        'count'   => count($events),
      ];
    } catch (\Throwable $e) {
      return ['success' => false, 'error' => $e->getMessage()];
    }
  }
}
