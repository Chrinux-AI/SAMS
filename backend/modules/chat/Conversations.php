<?php

/**
 * Conversations — Chat Conversation Management
 *
 * Manages conversations, group chats, role-based chats, participant lists.
 * Core data layer for the Communication OS chat module.
 */
class Conversations
{
  /**
   * Ensure chat tables exist.
   */
  public static function ensureTables(): void
  {
    // comm_* tables are the canonical messaging tables
    if (table_exists('comm_conversations')) return;

    $pdo = db()->getConnection();

    $pdo->exec("CREATE TABLE IF NOT EXISTS comm_conversations (
      id INT AUTO_INCREMENT PRIMARY KEY,
      title VARCHAR(255) DEFAULT NULL,
      type ENUM('direct','group','role') DEFAULT 'direct',
      created_by INT NOT NULL,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      INDEX idx_conv_type (type),
      INDEX idx_conv_created (created_by)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS comm_participants (
      id INT AUTO_INCREMENT PRIMARY KEY,
      conversation_id INT NOT NULL,
      user_id INT NOT NULL,
      role VARCHAR(20) DEFAULT 'member',
      joined_at DATETIME DEFAULT CURRENT_TIMESTAMP,
      UNIQUE KEY uk_conv_user (conversation_id, user_id),
      INDEX idx_part_user (user_id),
      INDEX idx_part_conv (conversation_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS comm_messages (
      id INT AUTO_INCREMENT PRIMARY KEY,
      conversation_id INT NOT NULL,
      sender_id INT NOT NULL,
      body TEXT NOT NULL,
      reply_to_id INT DEFAULT NULL,
      is_deleted TINYINT(1) DEFAULT 0,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
      INDEX idx_msg_conv (conversation_id),
      INDEX idx_msg_sender (sender_id),
      INDEX idx_msg_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS comm_reads (
      id INT AUTO_INCREMENT PRIMARY KEY,
      message_id INT NOT NULL,
      user_id INT NOT NULL,
      read_at DATETIME DEFAULT CURRENT_TIMESTAMP,
      UNIQUE KEY uk_msg_user (message_id, user_id),
      INDEX idx_reads_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS comm_typing (
      conversation_id INT NOT NULL,
      user_id INT NOT NULL,
      updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (conversation_id, user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS comm_attachments (
      id INT AUTO_INCREMENT PRIMARY KEY,
      message_id INT NOT NULL,
      file_name VARCHAR(255),
      file_path VARCHAR(500),
      file_type VARCHAR(100),
      file_size INT DEFAULT 0,
      INDEX idx_att_msg (message_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  }

  /**
   * Create a new conversation.
   */
  public static function create(int $createdBy, array $participantIds, string $title = '', string $type = 'direct'): array
  {
    self::ensureTables();

    try {
      // For direct chats, check if one already exists between these two users
      if ($type === 'direct' && count($participantIds) === 1) {
        $existing = self::findDirect($createdBy, $participantIds[0]);
        if ($existing) {
          return ['success' => true, 'conversation_id' => $existing['id'], 'existing' => true];
        }
      }

      $convId = insert_flexible('comm_conversations', [
        'title'      => $title ?: null,
        'type'       => $type,
        'created_by' => $createdBy,
        'created_at' => date('Y-m-d H:i:s'),
      ]);

      // Add creator as participant
      $allParticipants = array_unique(array_merge([$createdBy], $participantIds));
      foreach ($allParticipants as $uid) {
        db()->query(
          "INSERT INTO comm_participants (conversation_id, user_id, role) VALUES (?, ?, 'member')",
          [$convId, (int)$uid]
        );
      }

      EventBus::dispatch('chat', 'conversation_created', [
        'conversation_id' => $convId,
        'type'            => $type,
        'participants'    => count($allParticipants),
      ]);

      return ['success' => true, 'conversation_id' => $convId];
    } catch (\Throwable $e) {
      ErrorCollector::log('chat_conversations', 'Create failed: ' . $e->getMessage(), 'MEDIUM');
      return ['success' => false, 'error' => $e->getMessage()];
    }
  }

  /**
   * Find an existing direct conversation between two users.
   */
  public static function findDirect(int $user1, int $user2): ?array
  {
    try {
      return db()->fetchOne(
        "SELECT c.* FROM comm_conversations c
         JOIN comm_participants p1 ON p1.conversation_id = c.id AND p1.user_id = ?
         JOIN comm_participants p2 ON p2.conversation_id = c.id AND p2.user_id = ?
         WHERE c.type = 'direct'
         LIMIT 1",
        [$user1, $user2]
      );
    } catch (\Throwable $e) {
      return null;
    }
  }

  /**
   * Get conversations for a user.
   */
  public static function getForUser(int $userId, int $limit = 50): array
  {
    self::ensureTables();
    try {
      return db()->fetchAll(
        "SELECT c.*,
                (SELECT COUNT(*) FROM comm_messages cm
                 LEFT JOIN comm_reads cr ON cr.message_id = cm.id AND cr.user_id = ?
                 WHERE cm.conversation_id = c.id AND cm.sender_id != ? AND cr.id IS NULL AND cm.is_deleted = 0) as unread_count,
                (SELECT m2.body FROM comm_messages m2 WHERE m2.conversation_id = c.id ORDER BY m2.created_at DESC LIMIT 1) as last_message,
                (SELECT m3.created_at FROM comm_messages m3 WHERE m3.conversation_id = c.id ORDER BY m3.created_at DESC LIMIT 1) as last_message_at
         FROM comm_conversations c
         JOIN comm_participants cp ON cp.conversation_id = c.id AND cp.user_id = ?
         ORDER BY last_message_at DESC
         LIMIT " . max(1, min(100, $limit)),
        [$userId, $userId, $userId]
      );
    } catch (\Throwable $e) {
      return [];
    }
  }

  /**
   * Get participants of a conversation.
   */
  public static function getParticipants(int $conversationId): array
  {
    try {
      return db()->fetchAll(
        "SELECT u.id, u.username, u.first_name, u.last_name, u.role, cp.joined_at
         FROM comm_participants cp
         JOIN users u ON u.id = cp.user_id
         WHERE cp.conversation_id = ?
         ORDER BY u.first_name",
        [$conversationId]
      );
    } catch (\Throwable $e) {
      return [];
    }
  }

  /**
   * Check if a user is a participant.
   */
  public static function isParticipant(int $conversationId, int $userId): bool
  {
    try {
      $row = db()->fetchOne(
        "SELECT id FROM comm_participants WHERE conversation_id = ? AND user_id = ?",
        [$conversationId, $userId]
      );
      return (bool) $row;
    } catch (\Throwable $e) {
      return false;
    }
  }

  /**
   * Mark conversation as read for a user.
   */
  public static function markRead(int $conversationId, int $userId): void
  {
    try {
      // Mark all unread messages as read via comm_reads
      $messages = db()->fetchAll(
        "SELECT m.id FROM comm_messages m
         LEFT JOIN comm_reads cr ON cr.message_id = m.id AND cr.user_id = ?
         WHERE m.conversation_id = ? AND m.sender_id != ? AND cr.id IS NULL",
        [$userId, $conversationId, $userId]
      );
      foreach ($messages as $msg) {
        try {
          db()->query("INSERT INTO comm_reads (message_id, user_id) VALUES (?, ?)", [$msg['id'], $userId]);
        } catch (\Throwable $e) { /* duplicate */
        }
      }
    } catch (\Throwable $e) {
      // Silently fail
    }
  }
}
