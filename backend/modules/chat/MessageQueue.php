<?php

/**
 * MessageQueue — Chat Message Pipeline
 *
 * Send → Queue → Store → Broadcast → Update Status → Notify
 *
 * Handles message creation, retrieval, status tracking, and event dispatch.
 */
class MessageQueue
{
  /**
   * Send a message to a conversation.
   */
  public static function send(int $conversationId, int $senderId, string $body, string $type = 'text', ?string $attachmentUrl = null): array
  {
    Conversations::ensureTables();

    // Verify sender is a participant
    if (!Conversations::isParticipant($conversationId, $senderId)) {
      return ['success' => false, 'error' => 'Not a participant'];
    }

    if (trim($body) === '' && $type === 'text') {
      return ['success' => false, 'error' => 'Empty message'];
    }

    try {
      // Store message
      $msg_id = insert_flexible('comm_messages', [
        'conversation_id' => $conversationId,
        'sender_id'       => $senderId,
        'body'            => $body,
        'reply_to_id'     => null,
        'created_at'      => date('Y-m-d H:i:s'),
      ]);

      $messageId = (int)$msg_id;

      // Create read receipts for other participants
      $participants = Conversations::getParticipants($conversationId);
      foreach ($participants as $p) {
        if ((int) $p['id'] === $senderId) continue;
        // Reads will be created when the user views the message
      }

      // Update conversation timestamp
      db()->query("UPDATE comm_conversations SET updated_at = NOW() WHERE id = ?", [$conversationId]);

      // Dispatch events
      EventBus::dispatch('chat', 'message_sent', [
        'message_id'      => $messageId,
        'conversation_id' => $conversationId,
        'sender_id'       => $senderId,
        'type'            => $type,
      ]);

      // Trigger notification bridge
      NotificationBridge::onNewMessage($conversationId, $senderId, $body, $messageId);

      return ['success' => true, 'message_id' => $messageId];
    } catch (\Throwable $e) {
      ErrorCollector::log('message_queue', 'Send failed: ' . $e->getMessage(), 'MEDIUM');
      return ['success' => false, 'error' => $e->getMessage()];
    }
  }

  /**
   * Get messages for a conversation.
   */
  public static function getMessages(int $conversationId, int $limit = 50, int $beforeId = 0): array
  {
    Conversations::ensureTables();
    try {
      $sql = "SELECT m.*, u.first_name, u.last_name, u.username, u.role as sender_role
              FROM comm_messages m
              JOIN users u ON u.id = m.sender_id
              WHERE m.conversation_id = ?";
      $params = [$conversationId];

      if ($beforeId > 0) {
        $sql .= " AND m.id < ?";
        $params[] = $beforeId;
      }

      $sql .= " ORDER BY m.created_at DESC LIMIT " . max(1, min(100, $limit));

      $messages = db()->fetchAll($sql, $params);

      // Return in chronological order
      return array_reverse($messages);
    } catch (\Throwable $e) {
      return [];
    }
  }

  /**
   * Get unread count for a user across all conversations.
   */
  public static function getUnreadCount(int $userId): int
  {
    Conversations::ensureTables();
    try {
      $row = db()->fetchOne(
        "SELECT COUNT(*) as cnt FROM comm_messages m
         JOIN comm_participants cp ON m.conversation_id = cp.conversation_id AND cp.user_id = ?
         LEFT JOIN comm_reads cr ON m.id = cr.message_id AND cr.user_id = ?
         WHERE m.sender_id != ? AND cr.id IS NULL AND m.is_deleted = 0",
        [$userId, $userId, $userId]
      );
      return (int) ($row['cnt'] ?? 0);
    } catch (\Throwable $e) {
      return 0;
    }
  }

  /**
   * Mark a specific message as read.
   */
  public static function markRead(int $messageId, int $userId): void
  {
    try {
      try {
        db()->query("INSERT INTO comm_reads (message_id, user_id) VALUES (?, ?)", [$messageId, $userId]);
      } catch (\Throwable $e) { /* duplicate */
      }
    } catch (\Throwable $e) {
      // Silently fail
    }
  }

  /**
   * Get delivery status for a message.
   */
  public static function getStatus(int $messageId): array
  {
    try {
      return db()->fetchAll(
        "SELECT cr.*, u.first_name, u.last_name
         FROM comm_reads cr
         JOIN users u ON u.id = cr.user_id
         WHERE cr.message_id = ?",
        [$messageId]
      );
    } catch (\Throwable $e) {
      return [];
    }
  }

  /**
   * Delete a message (soft — replace body with system message).
   */
  public static function deleteMessage(int $messageId, int $requestingUserId): array
  {
    try {
      $msg = db()->fetchOne("SELECT * FROM comm_messages WHERE id = ?", [$messageId]);
      if (!$msg) {
        return ['success' => false, 'error' => 'Message not found'];
      }
      if ((int) $msg['sender_id'] !== $requestingUserId) {
        return ['success' => false, 'error' => 'Not the sender'];
      }

      $pdo = db()->getConnection();
      $stmt = $pdo->prepare(
        "UPDATE comm_messages SET body = '[Message deleted]', is_deleted = 1 WHERE id = ?"
      );
      $stmt->execute([$messageId]);

      EventBus::dispatch('chat', 'message_deleted', [
        'message_id'      => $messageId,
        'conversation_id' => (int) $msg['conversation_id'],
      ]);

      return ['success' => true];
    } catch (\Throwable $e) {
      return ['success' => false, 'error' => $e->getMessage()];
    }
  }
}
