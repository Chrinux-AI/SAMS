<?php

/**
 * MessageService — Unified Messaging Service Layer
 *
 * Wraps all messaging operations against comm_* tables.
 * Used by communication/api/messages.php and modules/chat/ classes.
 */
class MessageService
{
  // ─── Permission Matrix ───

  private static array $permissionMatrix = [
    'admin'      => ['*'],
    'teacher'    => ['student', 'parent', 'teacher', 'admin'],
    'student'    => ['teacher', 'student', 'admin'],
    'parent'     => ['teacher', 'admin'],
    'staff'      => ['admin', 'teacher', 'staff'],
    'librarian'  => ['admin', 'teacher', 'student', 'librarian'],
    'bursar'     => ['admin', 'parent', 'bursar'],
    'accountant' => ['admin', 'bursar', 'accountant'],
    'transport'  => ['admin', 'parent', 'transport'],
  ];

  public static function canCommunicate(string $fromRole, string $toRole): bool
  {
    if ($fromRole === 'admin') return true;
    if ($toRole === 'admin') return true;
    $allowed = self::$permissionMatrix[$fromRole] ?? ['admin'];
    return in_array('*', $allowed, true) || in_array($toRole, $allowed, true);
  }

  // ─── Conversations ───

  public static function getConversations(int $userId): array
  {
    return db()->fetchAll("
            SELECT c.id, c.title, c.type, c.updated_at,
                   (SELECT body FROM comm_messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_message,
                   (SELECT CONCAT(u2.first_name,' ',u2.last_name) FROM comm_messages cm2
                    JOIN users u2 ON cm2.sender_id = u2.id
                    WHERE cm2.conversation_id = c.id ORDER BY cm2.created_at DESC LIMIT 1) as last_sender,
                   (SELECT cm3.created_at FROM comm_messages cm3
                    WHERE cm3.conversation_id = c.id ORDER BY cm3.created_at DESC LIMIT 1) as last_message_at,
                   (SELECT COUNT(*) FROM comm_messages cm4
                    JOIN comm_participants cp2 ON cm4.conversation_id = cp2.conversation_id AND cp2.user_id = ?
                    LEFT JOIN comm_reads cr ON cm4.id = cr.message_id AND cr.user_id = ?
                    WHERE cm4.conversation_id = c.id AND cm4.sender_id != ? AND cr.id IS NULL AND cm4.is_deleted = 0) as unread_count
            FROM comm_conversations c
            JOIN comm_participants cp ON c.id = cp.conversation_id
            WHERE cp.user_id = ?
            ORDER BY COALESCE((SELECT cm5.created_at FROM comm_messages cm5 WHERE cm5.conversation_id = c.id ORDER BY cm5.created_at DESC LIMIT 1), c.created_at) DESC
        ", [$userId, $userId, $userId, $userId]);
  }

  public static function enrichConversation(array &$row, int $userId): void
  {
    if ($row['type'] === 'direct') {
      $other = db()->fetchOne("
                SELECT u.id, u.first_name, u.last_name, u.role, u.profile_picture
                FROM comm_participants cp JOIN users u ON cp.user_id = u.id
                WHERE cp.conversation_id = ? AND cp.user_id != ?
                LIMIT 1
            ", [$row['id'], $userId]);
      $row['other_user'] = $other ?: null;
      if ($other) {
        $row['display_name'] = $other['first_name'] . ' ' . $other['last_name'];
        $row['display_role'] = $other['role'];
      }
    } else {
      $row['display_name'] = $row['title'] ?? 'Group Chat';
      $pcount = db()->fetchOne("SELECT COUNT(*) as c FROM comm_participants WHERE conversation_id = ?", [$row['id']]);
      $row['participant_count'] = $pcount['c'] ?? 0;
    }
  }

  public static function getConversationInfo(int $convId): ?array
  {
    $conv = db()->fetchOne("SELECT * FROM comm_conversations WHERE id = ?", [$convId]);
    if (!$conv) return null;

    $participants = db()->fetchAll("
            SELECT u.id, u.first_name, u.last_name, u.role, u.profile_picture, cp.role as chat_role
            FROM comm_participants cp JOIN users u ON cp.user_id = u.id
            WHERE cp.conversation_id = ?
        ", [$convId]);

    return ['conversation' => $conv, 'participants' => $participants];
  }

  public static function isParticipant(int $convId, int $userId): bool
  {
    $row = db()->fetchOne(
      "SELECT id FROM comm_participants WHERE conversation_id = ? AND user_id = ?",
      [$convId, $userId]
    );
    return (bool)$row;
  }

  public static function createDirect(int $userId, int $targetId): array
  {
    // Check if conversation already exists
    $existing = db()->fetchOne("
            SELECT c.id FROM comm_conversations c
            JOIN comm_participants p1 ON c.id = p1.conversation_id AND p1.user_id = ?
            JOIN comm_participants p2 ON c.id = p2.conversation_id AND p2.user_id = ?
            WHERE c.type = 'direct' LIMIT 1
        ", [$userId, $targetId]);

    if ($existing) {
      return ['success' => true, 'conversation_id' => $existing['id'], 'existing' => true];
    }

    $convId = insert_flexible('comm_conversations', [
      'type'       => 'direct',
      'created_by' => $userId,
      'created_at' => date('Y-m-d H:i:s'),
    ]);
    db()->query("INSERT INTO comm_participants (conversation_id, user_id, role) VALUES (?, ?, 'admin')", [$convId, $userId]);
    db()->query("INSERT INTO comm_participants (conversation_id, user_id, role) VALUES (?, ?, 'member')", [$convId, $targetId]);

    return ['success' => true, 'conversation_id' => $convId];
  }

  public static function createGroup(int $userId, array $memberIds, string $title = 'Group Chat'): array
  {
    $convId = insert_flexible('comm_conversations', [
      'title'      => $title,
      'type'       => 'group',
      'created_by' => $userId,
      'created_at' => date('Y-m-d H:i:s'),
    ]);
    db()->query("INSERT INTO comm_participants (conversation_id, user_id, role) VALUES (?, ?, 'admin')", [$convId, $userId]);
    foreach ($memberIds as $mid) {
      $mid = (int)$mid;
      if ($mid && $mid !== $userId) {
        try {
          db()->query("INSERT INTO comm_participants (conversation_id, user_id) VALUES (?, ?)", [$convId, $mid]);
        } catch (\Throwable $e) { /* duplicate */
        }
      }
    }
    return ['success' => true, 'conversation_id' => $convId];
  }

  // ─── Messages ───

  public static function getMessages(int $convId, int $userId, int $limit = 50, ?int $before = null): array
  {
    $params = [$convId];
    $sql = "SELECT m.id, m.conversation_id, m.sender_id, m.body, m.reply_to_id, m.is_deleted, m.created_at,
                       CONCAT(u.first_name,' ',u.last_name) as sender_name, u.role as sender_role, u.profile_picture as sender_avatar
                FROM comm_messages m
                JOIN users u ON m.sender_id = u.id
                WHERE m.conversation_id = ?";
    if ($before) {
      $sql .= " AND m.id < ?";
      $params[] = (int)$before;
    }
    $limit = min(max(1, $limit), 100);
    $sql .= " ORDER BY m.created_at DESC LIMIT $limit";

    $messages = db()->fetchAll($sql, $params);

    foreach ($messages as &$msg) {
      $msg['reads'] = db()->fetchAll(
        "SELECT cr.user_id, cr.read_at, CONCAT(u.first_name,' ',u.last_name) as reader
                 FROM comm_reads cr JOIN users u ON cr.user_id = u.id WHERE cr.message_id = ?",
        [$msg['id']]
      );
      $msg['attachments'] = db()->fetchAll(
        "SELECT id, file_name, file_path, file_type, file_size FROM comm_attachments WHERE message_id = ?",
        [$msg['id']]
      );
      if ($msg['reply_to_id']) {
        $msg['reply_preview'] = db()->fetchOne(
          "SELECT m.body, CONCAT(u.first_name,' ',u.last_name) as sender_name
                     FROM comm_messages m JOIN users u ON m.sender_id = u.id WHERE m.id = ?",
          [$msg['reply_to_id']]
        );
      }
    }
    unset($msg);

    // Auto-mark as read
    self::markMessagesRead($messages, $userId);

    return array_reverse($messages);
  }

  public static function send(int $convId, int $userId, string $body, ?int $replyToId = null): array
  {
    $msgId = insert_flexible('comm_messages', [
      'conversation_id' => $convId,
      'sender_id'       => $userId,
      'body'            => $body,
      'reply_to_id'     => $replyToId,
      'created_at'      => date('Y-m-d H:i:s'),
    ]);

    db()->query("UPDATE comm_conversations SET updated_at = NOW() WHERE id = ?", [$convId]);
    db()->query("DELETE FROM comm_typing WHERE conversation_id = ? AND user_id = ?", [$convId, $userId]);

    // Broadcast if available
    self::broadcast($convId, $userId, $msgId, $body);

    $newMsg = db()->fetchOne(
      "SELECT m.*, CONCAT(u.first_name,' ',u.last_name) as sender_name, u.role as sender_role
             FROM comm_messages m JOIN users u ON m.sender_id = u.id WHERE m.id = ?",
      [$msgId]
    );

    return ['success' => true, 'message' => $newMsg];
  }

  public static function deleteMessage(int $msgId, int $userId, string $userRole): array
  {
    $msg = db()->fetchOne("SELECT id, sender_id FROM comm_messages WHERE id = ?", [$msgId]);
    if (!$msg || ((int)$msg['sender_id'] !== $userId && $userRole !== 'admin')) {
      return ['success' => false, 'error' => 'Not allowed'];
    }
    db()->query("UPDATE comm_messages SET is_deleted = 1, body = '[Message deleted]' WHERE id = ?", [$msgId]);
    return ['success' => true];
  }

  public static function poll(int $convId, int $userId, int $afterId): array
  {
    return db()->fetchAll("
            SELECT m.*, CONCAT(u.first_name,' ',u.last_name) as sender_name, u.role as sender_role
            FROM comm_messages m JOIN users u ON m.sender_id = u.id
            WHERE m.conversation_id = ? AND m.id > ?
            ORDER BY m.created_at ASC
        ", [$convId, $afterId]);
  }

  // ─── Typing ───

  public static function setTyping(int $convId, int $userId): void
  {
    try {
      db()->query("INSERT INTO comm_typing (conversation_id, user_id, updated_at) VALUES (?, ?, NOW())
                ON DUPLICATE KEY UPDATE updated_at = NOW()", [$convId, $userId]);
    } catch (\Throwable $e) { /* ignore */
    }
  }

  public static function stopTyping(int $convId, int $userId): void
  {
    db()->query("DELETE FROM comm_typing WHERE conversation_id = ? AND user_id = ?", [$convId, $userId]);
  }

  public static function getTypingUsers(int $convId, int $userId): array
  {
    return db()->fetchAll("
            SELECT CONCAT(u.first_name,' ',u.last_name) as name
            FROM comm_typing t JOIN users u ON t.user_id = u.id
            WHERE t.conversation_id = ? AND t.user_id != ? AND t.updated_at > DATE_SUB(NOW(), INTERVAL 5 SECOND)
        ", [$convId, $userId]);
  }

  // ─── User Search ───

  public static function searchUsers(string $query, int $excludeUserId, string $fromRole): array
  {
    $users = db()->fetchAll("
            SELECT id, first_name, last_name, role, email, profile_picture
            FROM users
            WHERE id != ? AND status = 'active'
              AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ?)
            ORDER BY first_name LIMIT 20
        ", [$excludeUserId, "%$query%", "%$query%", "%$query%"]);

    return array_values(array_filter($users, function ($u) use ($fromRole) {
      return self::canCommunicate($fromRole, $u['role']);
    }));
  }

  // ─── Unread Count ───

  public static function getUnreadCount(int $userId): int
  {
    $row = db()->fetchOne("
            SELECT COUNT(*) as cnt FROM comm_messages m
            JOIN comm_participants cp ON m.conversation_id = cp.conversation_id AND cp.user_id = ?
            LEFT JOIN comm_reads cr ON m.id = cr.message_id AND cr.user_id = ?
            WHERE m.sender_id != ? AND cr.id IS NULL AND m.is_deleted = 0
        ", [$userId, $userId, $userId]);
    return (int)($row['cnt'] ?? 0);
  }

  // ─── Internal Helpers ───

  private static function markMessagesRead(array $messages, int $userId): void
  {
    if (empty($messages)) return;
    $msgIds = array_column($messages, 'id');
    $in = implode(',', array_fill(0, count($msgIds), '?'));
    $existing = db()->fetchAll(
      "SELECT message_id FROM comm_reads WHERE user_id = ? AND message_id IN ($in)",
      array_merge([$userId], $msgIds)
    );
    $existingIds = array_column($existing, 'message_id');
    foreach ($msgIds as $mid) {
      if (!in_array($mid, $existingIds) && $mid) {
        try {
          db()->query("INSERT INTO comm_reads (message_id, user_id) VALUES (?, ?)", [$mid, $userId]);
        } catch (\Throwable $e) { /* duplicate key */
        }
      }
    }
  }

  private static function broadcast(int $convId, int $userId, int $msgId, string $body): void
  {
    try {
      if (class_exists('Broadcaster')) {
        $participants = db()->fetchAll(
          "SELECT user_id FROM comm_participants WHERE conversation_id = ? AND user_id != ?",
          [$convId, $userId]
        );
        $pIds = array_column($participants, 'user_id');
        if ($pIds) {
          Broadcaster::send('communication', 'new_message', [
            'conversation_id' => $convId,
            'message_id'      => $msgId,
            'sender_id'       => $userId,
            'preview'         => mb_substr($body, 0, 100),
          ], $pIds);
        }
      }
    } catch (\Throwable $e) { /* non-critical */
    }
  }
}
