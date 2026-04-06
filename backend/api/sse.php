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
$conversationId = isset($_GET['conversation_id']) ? (int) $_GET['conversation_id'] : 0;

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
      $newMessages = db()->fetchAll(
        "SELECT cm.id, cm.conversation_id, cm.sender_id, cm.message_text, cm.created_at,
                        cm.reply_to_message_id, cm.is_edited,
                        u.first_name, u.last_name, u.profile_picture
                 FROM conversation_messages cm
                 JOIN users u ON cm.sender_id = u.id
                 WHERE cm.conversation_id = ? AND cm.id > ? AND cm.is_deleted = 0
                 ORDER BY cm.id ASC LIMIT 20",
        [$conversationId, $lastMessageId]
      );
    } else {
      // Check all conversations for the user
      $newMessages = db()->fetchAll(
        "SELECT cm.id, cm.conversation_id, cm.sender_id, cm.message_text, cm.created_at,
                        u.first_name, u.last_name
                 FROM conversation_messages cm
                 JOIN users u ON cm.sender_id = u.id
                 JOIN conversation_participants cp ON cp.conversation_id = cm.conversation_id AND cp.user_id = ?
                 WHERE cm.id > ? AND cm.is_deleted = 0
                 ORDER BY cm.id ASC LIMIT 20",
        [$userId, $lastMessageId]
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
      $typers = db()->fetchAll(
        "SELECT ti.user_id, u.first_name
                 FROM typing_indicators ti
                 JOIN users u ON ti.user_id = u.id
                 WHERE ti.conversation_id = ? AND ti.user_id != ? AND ti.is_typing = 1 AND ti.updated_at > DATE_SUB(NOW(), INTERVAL 5 SECOND)",
        [$conversationId, $userId]
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
      $newNotifications = db()->fetchAll(
        "SELECT id, title, message, type, link, created_at
                 FROM notifications
                 WHERE user_id = ? AND id > ? AND is_read = 0
                 ORDER BY id ASC LIMIT 10",
        [$userId, $lastNotificationId]
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
      $unread = db()->fetchOne(
        "SELECT COUNT(*) as cnt FROM conversation_messages cm
                 JOIN conversation_participants cp ON cp.conversation_id = cm.conversation_id AND cp.user_id = ?
                 WHERE cm.sender_id != ? AND cm.is_deleted = 0
                   AND (cp.last_read_at IS NULL OR cm.created_at > cp.last_read_at)",
        [$userId, $userId]
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
