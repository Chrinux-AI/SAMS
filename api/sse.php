<?php

/**
 * Server-Sent Events (SSE) endpoint for real-time updates.
 * Streams events for: new messages, typing indicators, online status changes, notifications.
 */
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/database.php';

if (!isset($_SESSION['user_id'])) {
  http_response_code(401);
  echo "data: {\"error\": \"unauthorized\"}\n\n";
  exit;
}

$userId = (int) $_SESSION['user_id'];
$tenantId = current_tenant_id();
if (!isset($_SESSION['tenant_id'])) {
  set_user_tenant_session($userId);
  $tenantId = current_tenant_id();
}
if (!user_in_current_tenant($userId) || !$tenantId) {
  http_response_code(403);
  echo "data: {\"error\": \"tenant_access_denied\"}\n\n";
  exit;
}

$conversationId = isset($_GET['conversation_id']) ? (int) $_GET['conversation_id'] : 0;
$conversationTenantScoped = table_has_column('conversation_messages', 'tenant_id');
$participantTenantScoped = table_has_column('conversation_participants', 'tenant_id');

// Set SSE headers
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no'); // nginx

// Disable output buffering
if (function_exists('apache_setenv')) {
  @apache_setenv('no-gzip', '1');
}
@ini_set('zlib.output_compression', '0');
while (ob_get_level()) ob_end_clean();

// Update online status
try {
  db()->query(
    "INSERT INTO user_online_status (user_id, is_online, last_seen, last_activity)
         VALUES (?, 1, NOW(), NOW())
         ON DUPLICATE KEY UPDATE is_online = 1, last_seen = NOW(), last_activity = NOW()",
    [$userId]
  );
} catch (Exception $e) {
  // Table may not exist
}

// Track last known state
$lastMessageId = isset($_GET['last_message_id']) ? (int) $_GET['last_message_id'] : 0;
$lastNotificationId = isset($_GET['last_notification_id']) ? (int) $_GET['last_notification_id'] : 0;

$conversationTenantClause = $conversationTenantScoped ? ' AND cm.tenant_id = ?' : '';
$participantTenantClause = $participantTenantScoped ? ' AND cp.tenant_id = ?' : '';
$typingTenantClause = table_has_column('typing_indicators', 'tenant_id') ? ' AND ti.tenant_id = ?' : '';
$notificationTenantClause = table_has_column('notifications', 'tenant_id') ? ' AND tenant_id = ?' : '';

if ($conversationId > 0) {
  $participantSql = "SELECT cp.id
         FROM conversation_participants cp
         WHERE cp.conversation_id = ? AND cp.user_id = ?";
  $participantParams = [$conversationId, $userId];
  if ($participantTenantScoped) {
    $participantSql .= " AND cp.tenant_id = ?";
    $participantParams[] = $tenantId;
  }

  $conversationAccess = db()->fetchOne($participantSql . " LIMIT 1", $participantParams);
  if (!$conversationAccess) {
    http_response_code(403);
    echo "data: {\"error\": \"conversation_access_denied\"}\n\n";
    exit;
  }
}

// Send initial connection event
sendEvent('connected', ['user_id' => $userId, 'timestamp' => date('c')]);

$maxIterations = 300; // ~5 minutes at 1s intervals
$iteration = 0;

while ($iteration < $maxIterations) {
  if (connection_aborted()) break;
  $iteration++;

  $events = [];

  // 1. Check for new messages
  try {
    if ($conversationId > 0) {
      $conversationMessageSql = "SELECT cm.id, cm.conversation_id, cm.sender_id, cm.message_text, cm.created_at,
                        cm.reply_to_message_id, cm.is_edited,
                        u.first_name, u.last_name, u.profile_picture
                 FROM conversation_messages cm
                 JOIN users u ON cm.sender_id = u.id
                 JOIN conversation_participants cp ON cp.conversation_id = cm.conversation_id AND cp.user_id = ?
                 WHERE cm.conversation_id = ? AND cm.id > ? AND cm.is_deleted = 0{$conversationTenantClause}{$participantTenantClause}
                 ORDER BY cm.id ASC LIMIT 20";
      $conversationParams = [$userId, $conversationId, $lastMessageId];
      if ($conversationTenantScoped) {
        $conversationParams[] = $tenantId;
      }
      if ($participantTenantScoped) {
        $conversationParams[] = $tenantId;
      }
      $newMessages = db()->fetchAll(
        $conversationMessageSql,
        $conversationParams
      );
    } else {
      // Check all conversations for the user
      $messageParams = [$userId, $lastMessageId];
      if ($conversationTenantClause !== '') {
        $messageParams[] = $tenantId;
      }
      if ($participantTenantClause !== '') {
        $messageParams[] = $tenantId;
      }
      $newMessages = db()->fetchAll(
        "SELECT cm.id, cm.conversation_id, cm.sender_id, cm.message_text, cm.created_at,
                        u.first_name, u.last_name
                 FROM conversation_messages cm
                 JOIN users u ON cm.sender_id = u.id
                 JOIN conversation_participants cp ON cp.conversation_id = cm.conversation_id AND cp.user_id = ?
                 WHERE cm.id > ? AND cm.is_deleted = 0{$conversationTenantClause}{$participantTenantClause}
                 ORDER BY cm.id ASC LIMIT 20",
        $messageParams
      );
    }

    if (!empty($newMessages)) {
      foreach ($newMessages as $msg) {
        $lastMessageId = max($lastMessageId, (int) $msg['id']);
        sendEvent('new_message', $msg);
      }
    }
  } catch (Exception $e) {
    // Silently skip
  }

  // 2. Check typing indicators (only for active conversation)
  if ($conversationId > 0) {
    try {
      $typingTenantJoin = table_has_column('typing_indicators', 'tenant_id') ? ' AND ti.tenant_id = ?' : '';
      $typingParams = [$conversationId, $userId];
      if ($typingTenantJoin !== '') {
        $typingParams[] = $tenantId;
      }
      $typers = db()->fetchAll(
        "SELECT ti.user_id, u.first_name
                 FROM typing_indicators ti
                 JOIN users u ON ti.user_id = u.id
                 WHERE ti.conversation_id = ? AND ti.user_id != ? AND ti.is_typing = 1 AND ti.updated_at > DATE_SUB(NOW(), INTERVAL 5 SECOND){$typingTenantJoin}",
        $typingParams
      );
      if (!empty($typers)) {
        sendEvent('typing', ['conversation_id' => $conversationId, 'users' => $typers]);
      }
    } catch (Exception $e) {
      // Table may not exist
    }
  }

  // 3. Check for new notifications (every 5 iterations)
  if ($iteration % 5 === 0) {
    try {
      $notificationTypeSelect = table_has_column('notifications', 'category')
        ? "COALESCE(category, 'general') AS type"
        : (table_has_column('notifications', 'type') ? 'type' : "'general' AS type");
      $notificationSql = "SELECT id, title, message, {$notificationTypeSelect}, link, created_at
                 FROM notifications
                 WHERE user_id = ? AND id > ? AND is_read = 0{$notificationTenantClause}
                 ORDER BY id ASC LIMIT 10";
      $newNotifications = db()->fetchAll(
        $notificationSql,
        table_has_column('notifications', 'tenant_id') ? [$userId, $lastNotificationId, $tenantId] : [$userId, $lastNotificationId]
      );
      if (!empty($newNotifications)) {
        foreach ($newNotifications as $notif) {
          $lastNotificationId = max($lastNotificationId, (int) $notif['id']);
          sendEvent('notification', $notif);
        }
      }
    } catch (Exception $e) {
      // Silently skip
    }

    // 4. Unread conversation counts
    try {
      $unreadTenantJoin = table_has_column('conversation_messages', 'tenant_id') ? ' AND cm.tenant_id = ?' : '';
      $participantUnreadTenantJoin = table_has_column('conversation_participants', 'tenant_id') ? ' AND cp.tenant_id = ?' : '';
      $unreadParams = [$userId, $userId];
      if ($unreadTenantJoin !== '') {
        $unreadParams[] = $tenantId;
      }
      if ($participantUnreadTenantJoin !== '') {
        $unreadParams[] = $tenantId;
      }
      $unread = db()->fetchOne(
        "SELECT COUNT(*) as cnt FROM conversation_messages cm
                 JOIN conversation_participants cp ON cp.conversation_id = cm.conversation_id AND cp.user_id = ?
                 WHERE cm.sender_id != ? AND cm.is_deleted = 0
                   AND (cp.last_read_at IS NULL OR cm.created_at > cp.last_read_at){$unreadTenantJoin}{$participantUnreadTenantJoin}",
        $unreadParams
      );
      sendEvent('unread_count', ['total' => (int)($unread['cnt'] ?? 0)]);
    } catch (Exception $e) {
      // Silently skip
    }
  }

  // Send heartbeat to keep connection alive
  sendEvent('heartbeat', ['t' => time()]);

  // Flush output
  if (ob_get_level()) ob_flush();
  flush();

  sleep(1);
}

// Cleanup
sendEvent('disconnected', ['reason' => 'timeout']);

function sendEvent(string $event, array $data): void
{
  echo "event: {$event}\n";
  echo "data: " . json_encode($data) . "\n\n";
  if (ob_get_level()) ob_flush();
  flush();
}
