<?php

/**
 * Communication API — Real-time messaging backend
 * Delegates to MessageService for all operations.
 */
session_start();
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/database.php';

// Rate limit API calls
RateLimiterService::enforce('message');

header('Content-Type: application/json');

if (!is_logged_in()) {
  http_response_code(401);
  echo json_encode(['error' => 'Unauthorized']);
  exit;
}

$user_id = (int)$_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? 'student';
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
  switch ($action) {

    // ─── LIST CONVERSATIONS ───
    case 'conversations':
      $rows = MessageService::getConversations($user_id);
      foreach ($rows as &$row) {
        MessageService::enrichConversation($row, $user_id);
      }
      unset($row);
      echo json_encode(['conversations' => $rows]);
      break;

    // ─── GET MESSAGES FOR CONVERSATION ───
    case 'messages':
      $conv_id = (int)($_GET['conversation_id'] ?? 0);
      if (!$conv_id) {
        echo json_encode(['error' => 'Missing conversation_id']);
        break;
      }
      if (!MessageService::isParticipant($conv_id, $user_id)) {
        http_response_code(403);
        echo json_encode(['error' => 'Not a participant']);
        break;
      }

      $before = isset($_GET['before']) ? (int)$_GET['before'] : null;
      $limit = min((int)($_GET['limit'] ?? 50), 100);
      $messages = MessageService::getMessages($conv_id, $user_id, $limit, $before);
      echo json_encode(['messages' => $messages]);
      break;

    // ─── SEND MESSAGE ───
    case 'send':
      if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['error' => 'POST required']);
        break;
      }

      $conv_id = (int)($_POST['conversation_id'] ?? 0);
      $body = trim($_POST['body'] ?? '');
      $reply_to = (int)($_POST['reply_to_id'] ?? 0) ?: null;

      if (!$conv_id || !$body) {
        echo json_encode(['error' => 'Missing conversation_id or body']);
        break;
      }
      if (!MessageService::isParticipant($conv_id, $user_id)) {
        http_response_code(403);
        echo json_encode(['error' => 'Not a participant']);
        break;
      }

      $result = MessageService::send($conv_id, $user_id, $body, $reply_to);
      echo json_encode($result);
      break;

    // ─── CREATE CONVERSATION ───
    case 'create_conversation':
      if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['error' => 'POST required']);
        break;
      }

      $target_id = (int)($_POST['user_id'] ?? 0);
      $type = $_POST['type'] ?? 'direct';
      $title = sanitize($_POST['title'] ?? '');

      if ($type === 'direct' && $target_id) {
        $target = db()->fetchOne("SELECT id, role FROM users WHERE id = ?", [$target_id]);
        if (!$target) {
          echo json_encode(['error' => 'User not found']);
          break;
        }
        if (!MessageService::canCommunicate($user_role, $target['role'])) {
          echo json_encode(['error' => 'You do not have permission to message this user']);
          break;
        }
        $result = MessageService::createDirect($user_id, $target_id);
        echo json_encode($result);
      } elseif ($type === 'group') {
        if (!in_array($user_role, ['admin', 'teacher'])) {
          echo json_encode(['error' => 'Only admin and teacher can create group chats']);
          break;
        }
        $member_ids = json_decode($_POST['member_ids'] ?? '[]', true);
        if (!is_array($member_ids) || count($member_ids) < 1) {
          echo json_encode(['error' => 'At least one member required']);
          break;
        }
        $result = MessageService::createGroup($user_id, $member_ids, $title ?: 'Group Chat');
        echo json_encode($result);
      } else {
        echo json_encode(['error' => 'Invalid type or missing user_id']);
      }
      break;

    // ─── TYPING INDICATOR ───
    case 'typing':
      $conv_id = (int)($_POST['conversation_id'] ?? 0);
      if ($conv_id) MessageService::setTyping($conv_id, $user_id);
      echo json_encode(['ok' => true]);
      break;

    case 'stop_typing':
      $conv_id = (int)($_POST['conversation_id'] ?? 0);
      if ($conv_id) MessageService::stopTyping($conv_id, $user_id);
      echo json_encode(['ok' => true]);
      break;

    // ─── GET TYPING USERS ───
    case 'typing_users':
      $conv_id = (int)($_GET['conversation_id'] ?? 0);
      if (!$conv_id) {
        echo json_encode(['users' => []]);
        break;
      }
      echo json_encode(['users' => MessageService::getTypingUsers($conv_id, $user_id)]);
      break;

    // ─── SEARCH USERS FOR NEW CONVERSATION ───
    case 'search_users':
      $q = sanitize($_GET['q'] ?? '');
      if (strlen($q) < 2) {
        echo json_encode(['users' => []]);
        break;
      }
      echo json_encode(['users' => MessageService::searchUsers($q, $user_id, $user_role)]);
      break;

    // ─── DELETE MESSAGE (soft) ───
    case 'delete_message':
      $msg_id = (int)($_POST['message_id'] ?? 0);
      if (!$msg_id) {
        echo json_encode(['error' => 'Missing message_id']);
        break;
      }
      echo json_encode(MessageService::deleteMessage($msg_id, $user_id, $user_role));
      break;

    // ─── CONVERSATION INFO ───
    case 'conversation_info':
      $conv_id = (int)($_GET['conversation_id'] ?? 0);
      $info = MessageService::getConversationInfo($conv_id);
      if (!$info) {
        echo json_encode(['error' => 'Not found']);
        break;
      }
      echo json_encode($info);
      break;

    // ─── POLL FOR NEW MESSAGES ───
    case 'poll':
      $conv_id = (int)($_GET['conversation_id'] ?? 0);
      $after = (int)($_GET['after'] ?? 0);
      if (!$conv_id || !MessageService::isParticipant($conv_id, $user_id)) {
        echo json_encode(['messages' => []]);
        break;
      }
      echo json_encode(['messages' => MessageService::poll($conv_id, $user_id, $after)]);
      break;

    default:
      echo json_encode(['error' => 'Unknown action']);
  }
} catch (Throwable $e) {
  error_log("Communication API error: " . $e->getMessage());
  http_response_code(500);
  echo json_encode(['error' => 'Server error']);
}
