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
    'owner'      => ['*'],
    'principal'  => ['admin', 'owner', 'principal', 'vice_principal', 'teacher', 'parent', 'student', 'staff', 'nurse', 'librarian', 'bursar', 'accountant', 'transport'],
    'vice_principal' => ['admin', 'owner', 'principal', 'vice_principal', 'teacher', 'parent', 'student', 'staff', 'nurse', 'librarian', 'bursar', 'accountant', 'transport'],
    'teacher'    => ['student', 'parent', 'teacher', 'admin'],
    'student'    => ['teacher', 'student', 'admin'],
    'parent'     => ['teacher', 'admin'],
    'staff'      => ['admin', 'teacher', 'staff'],
    'nurse'      => ['admin', 'principal', 'vice_principal', 'teacher', 'parent', 'nurse'],
    'librarian'  => ['admin', 'teacher', 'student', 'librarian'],
    'bursar'     => ['admin', 'parent', 'bursar'],
    'accountant' => ['admin', 'bursar', 'accountant'],
    'transport'  => ['admin', 'parent', 'transport'],
    'forum_moderator' => ['admin', 'teacher', 'staff', 'forum_moderator'],
  ];

  public static function canCommunicate(string $fromRole, string $toRole): bool
  {
    $fromRole = self::normalizeRole($fromRole);
    $toRole = self::normalizeRole($toRole);

    if (in_array($fromRole, ['admin', 'owner'], true)) return true;
    if (in_array($toRole, ['admin', 'owner'], true)) return true;

    $allowed = self::$permissionMatrix[$fromRole] ?? ['admin'];
    return in_array('*', $allowed, true) || in_array($toRole, $allowed, true);
  }

  private static function normalizeRole(string $role): string
  {
    $role = trim(strtolower($role));
    return $role === 'forum-moderator' ? 'forum_moderator' : $role;
  }

  private static function tenantId(): int
  {
    $tenantId = function_exists('current_tenant_id')
      ? (int)current_tenant_id()
      : (int)($_SESSION['tenant_id'] ?? 1);

    return $tenantId > 0 ? $tenantId : 1;
  }

  private static function tableColumn(string $table, string $alias, array $preferredColumns): ?string
  {
    if (!function_exists('table_has_column')) {
      return null;
    }

    foreach ($preferredColumns as $column) {
      if (table_has_column($table, $column)) {
        return "{$alias}.{$column}";
      }
    }

    return null;
  }

  private static function tenantFilterForUsers(string $alias = 'u'): array
  {
    $column = self::tableColumn('users', $alias, ['tenant_id', 'school_id']);
    if ($column === null) {
      return ['1=1', []];
    }

    return ["COALESCE({$column}, 1) = ?", [self::tenantId()]];
  }

  private static function tenantFilterForConversation(string $conversationAlias = 'c'): array
  {
    $tenantId = self::tenantId();
    $clauses = [];
    $params = [];

    $conversationColumn = self::tableColumn('comm_conversations', $conversationAlias, ['tenant_id', 'school_id']);
    if ($conversationColumn !== null) {
      $clauses[] = "COALESCE({$conversationColumn}, 1) = ?";
      $params[] = $tenantId;
    }

    $userTenantColumn = self::tableColumn('users', 'u_scope', ['tenant_id', 'school_id']);
    if ($userTenantColumn !== null) {
      $clauses[] = "NOT EXISTS (
                SELECT 1
                FROM comm_participants cp_scope
                JOIN users u_scope ON u_scope.id = cp_scope.user_id
                WHERE cp_scope.conversation_id = {$conversationAlias}.id
                  AND COALESCE({$userTenantColumn}, 1) <> ?
            )";
      $params[] = $tenantId;
    }

    return [empty($clauses) ? '1=1' : implode(' AND ', $clauses), $params];
  }

  private static function tenantPayload(string $table): array
  {
    if (!function_exists('table_has_column')) {
      return [];
    }

    if (table_has_column($table, 'tenant_id')) {
      return ['tenant_id' => self::tenantId()];
    }

    if (table_has_column($table, 'school_id')) {
      return ['school_id' => self::tenantId()];
    }

    return [];
  }

  private static function sanitizeMemberIds(array $memberIds, int $excludeUserId): array
  {
    $sanitized = [];
    foreach ($memberIds as $memberId) {
      $memberId = (int)$memberId;
      if ($memberId > 0 && $memberId !== $excludeUserId) {
        $sanitized[$memberId] = $memberId;
      }
    }

    return array_values($sanitized);
  }

  // ─── Conversations ───

  public static function getConversations(int $userId): array
  {
    [$tenantFilter, $tenantParams] = self::tenantFilterForConversation('c');

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
            WHERE cp.user_id = ? AND {$tenantFilter}
            ORDER BY COALESCE((SELECT cm5.created_at FROM comm_messages cm5 WHERE cm5.conversation_id = c.id ORDER BY cm5.created_at DESC LIMIT 1), c.created_at) DESC
        ", array_merge([$userId, $userId, $userId, $userId], $tenantParams));
  }

  public static function enrichConversation(array &$row, int $userId): void
  {
    if ($row['type'] === 'direct') {
      [$userTenantFilter, $userTenantParams] = self::tenantFilterForUsers('u');
      $other = db()->fetchOne("
                SELECT u.id, u.first_name, u.last_name, u.role, u.profile_picture
                FROM comm_participants cp JOIN users u ON cp.user_id = u.id
                WHERE cp.conversation_id = ? AND cp.user_id != ? AND {$userTenantFilter}
                LIMIT 1
            ", array_merge([$row['id'], $userId], $userTenantParams));
      $row['other_user'] = $other ?: null;
      if ($other) {
        $row['display_name'] = $other['first_name'] . ' ' . $other['last_name'];
        $row['display_role'] = $other['role'];
      } else {
        $row['display_name'] = $row['title'] ?? 'Direct Message';
      }
    } else {
      $row['display_name'] = $row['title'] ?? 'Group Chat';
      $pcount = db()->fetchOne("SELECT COUNT(*) as c FROM comm_participants WHERE conversation_id = ?", [$row['id']]);
      $row['participant_count'] = $pcount['c'] ?? 0;
    }
  }

  public static function getConversationInfo(int $convId, int $viewerUserId = 0): ?array
  {
    [$tenantFilter, $tenantParams] = self::tenantFilterForConversation('c');
    $sql = "SELECT c.* FROM comm_conversations c WHERE c.id = ? AND {$tenantFilter}";
    $params = [$convId];

    if ($viewerUserId > 0) {
      $sql = "SELECT c.* FROM comm_conversations c
                JOIN comm_participants cp_viewer ON cp_viewer.conversation_id = c.id
              WHERE c.id = ? AND cp_viewer.user_id = ? AND {$tenantFilter}";
      $params[] = $viewerUserId;
    }

    $conv = db()->fetchOne($sql, array_merge($params, $tenantParams));
    if (!$conv) return null;

    [$userTenantFilter, $userTenantParams] = self::tenantFilterForUsers('u');
    $participants = db()->fetchAll("
            SELECT u.id, u.first_name, u.last_name, u.role, u.profile_picture, cp.role as chat_role
            FROM comm_participants cp JOIN users u ON cp.user_id = u.id
            WHERE cp.conversation_id = ? AND {$userTenantFilter}
        ", array_merge([$convId], $userTenantParams));

    return ['conversation' => $conv, 'participants' => $participants];
  }

  public static function isParticipant(int $convId, int $userId): bool
  {
    [$tenantFilter, $tenantParams] = self::tenantFilterForConversation('c');
    $row = db()->fetchOne(
      "SELECT cp.id
         FROM comm_participants cp
         JOIN comm_conversations c ON c.id = cp.conversation_id
        WHERE cp.conversation_id = ? AND cp.user_id = ? AND {$tenantFilter}",
      array_merge([$convId, $userId], $tenantParams)
    );
    return (bool)$row;
  }

  public static function createDirect(int $userId, int $targetId, ?string $requesterRole = null): array
  {
    [$targetTenantFilter, $targetTenantParams] = self::tenantFilterForUsers('u');
    $target = db()->fetchOne(
      "SELECT u.id, u.role
         FROM users u
        WHERE u.id = ? AND {$targetTenantFilter}
        LIMIT 1",
      array_merge([$targetId], $targetTenantParams)
    );

    if (!$target) {
      return ['success' => false, 'error' => 'User not found in the active tenant'];
    }

    if ($requesterRole !== null && !self::canCommunicate($requesterRole, $target['role'])) {
      return ['success' => false, 'error' => 'You do not have permission to message this user'];
    }

    [$tenantFilter, $tenantParams] = self::tenantFilterForConversation('c');

    // Check if conversation already exists
    $existing = db()->fetchOne("
            SELECT c.id FROM comm_conversations c
            JOIN comm_participants p1 ON c.id = p1.conversation_id AND p1.user_id = ?
            JOIN comm_participants p2 ON c.id = p2.conversation_id AND p2.user_id = ?
            WHERE c.type = 'direct' AND {$tenantFilter}
            LIMIT 1
        ", array_merge([$userId, $targetId], $tenantParams));

    if ($existing) {
      return ['success' => true, 'conversation_id' => $existing['id'], 'existing' => true];
    }

    $convId = insert_flexible('comm_conversations', array_merge(self::tenantPayload('comm_conversations'), [
      'type'       => 'direct',
      'created_by' => $userId,
      'created_at' => date('Y-m-d H:i:s'),
    ]));
    if (!$convId) {
      return ['success' => false, 'error' => 'Failed to create conversation'];
    }

    insert_flexible('comm_participants', array_merge(self::tenantPayload('comm_participants'), [
      'conversation_id' => $convId,
      'user_id' => $userId,
      'role' => 'admin',
    ]));
    insert_flexible('comm_participants', array_merge(self::tenantPayload('comm_participants'), [
      'conversation_id' => $convId,
      'user_id' => $targetId,
      'role' => 'member',
    ]));

    return ['success' => true, 'conversation_id' => $convId];
  }

  public static function createGroup(int $userId, array $memberIds, string $title = 'Group Chat', ?string $creatorRole = null): array
  {
    $memberIds = self::sanitizeMemberIds($memberIds, $userId);
    if (empty($memberIds)) {
      return ['success' => false, 'error' => 'At least one valid member is required'];
    }

    [$tenantUserFilter, $tenantUserParams] = self::tenantFilterForUsers('u');
    $placeholders = implode(',', array_fill(0, count($memberIds), '?'));
    $candidates = db()->fetchAll(
      "SELECT u.id, u.role
         FROM users u
        WHERE u.id IN ({$placeholders}) AND {$tenantUserFilter}",
      array_merge($memberIds, $tenantUserParams)
    );

    $validMembers = [];
    foreach ($candidates as $candidate) {
      if ($creatorRole !== null && !self::canCommunicate($creatorRole, $candidate['role'])) {
        continue;
      }
      $validMembers[(int)$candidate['id']] = (int)$candidate['id'];
    }

    if (count($validMembers) !== count($memberIds)) {
      return ['success' => false, 'error' => 'One or more selected users are outside your allowed communication scope'];
    }

    $convId = insert_flexible('comm_conversations', array_merge(self::tenantPayload('comm_conversations'), [
      'title'      => $title,
      'type'       => 'group',
      'created_by' => $userId,
      'created_at' => date('Y-m-d H:i:s'),
    ]));
    if (!$convId) {
      return ['success' => false, 'error' => 'Failed to create group conversation'];
    }

    insert_flexible('comm_participants', array_merge(self::tenantPayload('comm_participants'), [
      'conversation_id' => $convId,
      'user_id' => $userId,
      'role' => 'admin',
    ]));
    foreach ($validMembers as $memberId) {
      try {
        insert_flexible('comm_participants', array_merge(self::tenantPayload('comm_participants'), [
          'conversation_id' => $convId,
          'user_id' => $memberId,
          'role' => 'member',
        ]));
      } catch (\Throwable $e) { /* duplicate */
      }
    }

    return ['success' => true, 'conversation_id' => $convId];
  }

  // ─── Messages ───

  public static function getMessages(int $convId, int $userId, int $limit = 50, ?int $before = null): array
  {
    [$tenantFilter, $tenantParams] = self::tenantFilterForConversation('c');
    $params = [$convId];
    $sql = "SELECT m.id, m.conversation_id, m.sender_id, m.body, m.reply_to_id, m.is_deleted, m.created_at,
                       CONCAT(u.first_name,' ',u.last_name) as sender_name, u.role as sender_role, u.profile_picture as sender_avatar
                 FROM comm_messages m
                 JOIN comm_conversations c ON c.id = m.conversation_id
                 JOIN users u ON m.sender_id = u.id
                 WHERE m.conversation_id = ? AND {$tenantFilter}";
    $params = array_merge($params, $tenantParams);
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
    if (!self::isParticipant($convId, $userId)) {
      return ['success' => false, 'error' => 'Not a participant'];
    }

    if ($replyToId) {
      $replyTarget = db()->fetchOne(
        "SELECT id FROM comm_messages WHERE id = ? AND conversation_id = ? LIMIT 1",
        [$replyToId, $convId]
      );
      if (!$replyTarget) {
        return ['success' => false, 'error' => 'Reply target does not exist in this conversation'];
      }
    }

    $msgId = insert_flexible('comm_messages', array_merge(self::tenantPayload('comm_messages'), [
      'conversation_id' => $convId,
      'sender_id'       => $userId,
      'body'            => $body,
      'reply_to_id'     => $replyToId,
      'created_at'      => date('Y-m-d H:i:s'),
    ]));
    if (!$msgId) {
      return ['success' => false, 'error' => 'Failed to send message'];
    }

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
    [$tenantFilter, $tenantParams] = self::tenantFilterForConversation('c');
    $msg = db()->fetchOne(
      "SELECT m.id, m.sender_id
         FROM comm_messages m
         JOIN comm_conversations c ON c.id = m.conversation_id
        WHERE m.id = ? AND {$tenantFilter}",
      array_merge([$msgId], $tenantParams)
    );

    $normalizedRole = self::normalizeRole($userRole);
    if (!$msg || ((int)$msg['sender_id'] !== $userId && !in_array($normalizedRole, ['admin', 'owner'], true))) {
      return ['success' => false, 'error' => 'Not allowed'];
    }
    db()->query("UPDATE comm_messages SET is_deleted = 1, body = '[Message deleted]' WHERE id = ?", [$msgId]);
    return ['success' => true];
  }

  public static function poll(int $convId, int $userId, int $afterId): array
  {
    [$tenantFilter, $tenantParams] = self::tenantFilterForConversation('c');
    return db()->fetchAll("
            SELECT m.*, CONCAT(u.first_name,' ',u.last_name) as sender_name, u.role as sender_role
            FROM comm_messages m
            JOIN comm_conversations c ON c.id = m.conversation_id
            JOIN users u ON m.sender_id = u.id
            WHERE m.conversation_id = ? AND m.id > ? AND {$tenantFilter}
            ORDER BY m.created_at ASC
        ", array_merge([$convId, $afterId], $tenantParams));
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
    [$userTenantFilter, $userTenantParams] = self::tenantFilterForUsers('u');
    return db()->fetchAll("
            SELECT CONCAT(u.first_name,' ',u.last_name) as name
            FROM comm_typing t JOIN users u ON t.user_id = u.id
            WHERE t.conversation_id = ? AND t.user_id != ? AND t.updated_at > DATE_SUB(NOW(), INTERVAL 5 SECOND)
              AND {$userTenantFilter}
        ", array_merge([$convId, $userId], $userTenantParams));
  }

  // ─── User Search ───

  public static function searchUsers(string $query, int $excludeUserId, string $fromRole): array
  {
    [$tenantFilter, $tenantParams] = self::tenantFilterForUsers('u');
    $users = db()->fetchAll("
            SELECT u.id, u.first_name, u.last_name, u.role, u.email, u.profile_picture
            FROM users u
            WHERE u.id != ? AND u.status = 'active'
              AND {$tenantFilter}
              AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)
            ORDER BY u.first_name LIMIT 20
        ", array_merge([$excludeUserId], $tenantParams, ["%$query%", "%$query%", "%$query%"]));

    return array_values(array_filter($users, function ($u) use ($fromRole) {
      return self::canCommunicate($fromRole, $u['role']);
    }));
  }

  // ─── Unread Count ───

  public static function getUnreadCount(int $userId): int
  {
    [$tenantFilter, $tenantParams] = self::tenantFilterForConversation('c');
    $row = db()->fetchOne("
            SELECT COUNT(*) as cnt FROM comm_messages m
            JOIN comm_conversations c ON c.id = m.conversation_id
            JOIN comm_participants cp ON m.conversation_id = cp.conversation_id AND cp.user_id = ?
            LEFT JOIN comm_reads cr ON m.id = cr.message_id AND cr.user_id = ?
            WHERE m.sender_id != ? AND cr.id IS NULL AND m.is_deleted = 0 AND {$tenantFilter}
        ", array_merge([$userId, $userId, $userId], $tenantParams));
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
          insert_flexible('comm_reads', array_merge(self::tenantPayload('comm_reads'), [
            'message_id' => $mid,
            'user_id' => $userId,
          ]));
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
