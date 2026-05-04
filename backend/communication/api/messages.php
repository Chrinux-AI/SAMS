<?php

/**
 * Communication API â€” Real-time messaging backend
 * Delegates to MessageService for all operations.
 */
session_start();

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/api-response.php';

function communication_success(array $payload = [], int $statusCode = 200): void
{
  api_json_response(array_merge(['success' => true], $payload), $statusCode);
}

function communication_error(string $message, int $statusCode = 400, array $meta = []): void
{
  api_json_response([
    'success' => false,
    'error' => $message,
    'meta' => $meta,
  ], $statusCode);
}

// Rate limit API calls
RateLimiterService::enforce('message');
api_require_auth();

$user_id = (int)$_SESSION['user_id'];
if (!isset($_SESSION['tenant_id'])) {
  set_user_tenant_session($user_id);
}
if (!user_in_current_tenant($user_id)) {
  communication_error('Tenant access denied', 403);
}

$user_role = $_SESSION['role'] ?? 'student';
$normalized_user_role = str_replace('-', '_', strtolower((string)$user_role));
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
  switch ($action) {
    case 'conversations':
      $rows = MessageService::getConversations($user_id);
      foreach ($rows as &$row) {
        MessageService::enrichConversation($row, $user_id);
      }
      unset($row);
      communication_success(['conversations' => $rows]);

    case 'messages':
      $conv_id = (int)($_GET['conversation_id'] ?? 0);
      if ($conv_id <= 0) {
        communication_error('Missing conversation_id', 422);
      }
      if (!MessageService::isParticipant($conv_id, $user_id)) {
        communication_error('Not a participant', 403);
      }

      $before = isset($_GET['before']) ? (int)$_GET['before'] : null;
      $limit = min((int)($_GET['limit'] ?? 50), 100);
      communication_success([
        'messages' => MessageService::getMessages($conv_id, $user_id, $limit, $before),
      ]);

    case 'send':
      if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        communication_error('POST required', 405);
      }

      $conv_id = (int)($_POST['conversation_id'] ?? 0);
      $body = trim((string)($_POST['body'] ?? ''));
      $reply_to = (int)($_POST['reply_to_id'] ?? 0) ?: null;

      if ($conv_id <= 0 || $body === '') {
        communication_error('Missing conversation_id or body', 422);
      }
      if (!MessageService::isParticipant($conv_id, $user_id)) {
        communication_error('Not a participant', 403);
      }

      $result = MessageService::send($conv_id, $user_id, $body, $reply_to);
      if (!($result['success'] ?? false)) {
        communication_error($result['error'] ?? 'Failed to send message', 422);
      }
      communication_success($result);

    case 'create_conversation':
      if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        communication_error('POST required', 405);
      }

      $target_id = (int)($_POST['user_id'] ?? 0);
      $type = trim((string)($_POST['type'] ?? 'direct'));
      $title = sanitize($_POST['title'] ?? '');

      if ($type === 'direct') {
        if ($target_id <= 0) {
          communication_error('Invalid type or missing user_id', 422);
        }

        $result = MessageService::createDirect($user_id, $target_id, $user_role);
        if (!($result['success'] ?? false)) {
          communication_error($result['error'] ?? 'Failed to create conversation', 422);
        }
        communication_success($result, !empty($result['existing']) ? 200 : 201);
      }

      if ($type === 'group') {
        if (!in_array($normalized_user_role, ['admin', 'teacher'], true)) {
          communication_error('Only admin and teacher can create group chats', 403);
        }

        $member_ids = json_decode($_POST['member_ids'] ?? '[]', true);
        if (!is_array($member_ids) || count($member_ids) < 1) {
          communication_error('At least one member required', 422);
        }

        $result = MessageService::createGroup($user_id, $member_ids, $title ?: 'Group Chat', $user_role);
        if (!($result['success'] ?? false)) {
          communication_error($result['error'] ?? 'Failed to create group conversation', 422);
        }
        communication_success($result, 201);
      }

      communication_error('Invalid type or missing user_id', 422);

    case 'typing':
    case 'stop_typing':
      if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        communication_error('POST required', 405);
      }

      $conv_id = (int)($_POST['conversation_id'] ?? 0);
      if ($conv_id <= 0) {
        communication_error('Missing conversation_id', 422);
      }
      if (!MessageService::isParticipant($conv_id, $user_id)) {
        communication_error('Not a participant', 403);
      }

      if ($action === 'typing') {
        MessageService::setTyping($conv_id, $user_id);
      } else {
        MessageService::stopTyping($conv_id, $user_id);
      }

      communication_success(['ok' => true]);

    case 'typing_users':
      $conv_id = (int)($_GET['conversation_id'] ?? 0);
      if ($conv_id <= 0) {
        communication_success(['users' => []]);
      }
      if (!MessageService::isParticipant($conv_id, $user_id)) {
        communication_error('Not a participant', 403);
      }

      communication_success([
        'users' => MessageService::getTypingUsers($conv_id, $user_id),
      ]);

    case 'search_users':
      $q = sanitize($_GET['q'] ?? '');
      if (strlen($q) < 2) {
        communication_success(['users' => []]);
      }

      communication_success([
        'users' => MessageService::searchUsers($q, $user_id, $user_role),
      ]);

    case 'delete_message':
      if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        communication_error('POST required', 405);
      }

      $msg_id = (int)($_POST['message_id'] ?? 0);
      if ($msg_id <= 0) {
        communication_error('Missing message_id', 422);
      }

      $result = MessageService::deleteMessage($msg_id, $user_id, $user_role);
      if (!($result['success'] ?? false)) {
        communication_error($result['error'] ?? 'Not allowed', 403);
      }
      communication_success($result);

    case 'conversation_info':
      $conv_id = (int)($_GET['conversation_id'] ?? 0);
      if ($conv_id <= 0) {
        communication_error('Missing conversation_id', 422);
      }

      $info = MessageService::getConversationInfo($conv_id, $user_id);
      if (!$info) {
        communication_error('Not found', 404);
      }
      communication_success($info);

    case 'poll':
      $conv_id = (int)($_GET['conversation_id'] ?? 0);
      $after = (int)($_GET['after'] ?? 0);
      if ($conv_id <= 0) {
        communication_error('Missing conversation_id', 422);
      }
      if (!MessageService::isParticipant($conv_id, $user_id)) {
        communication_error('Not a participant', 403);
      }

      communication_success([
        'messages' => MessageService::poll($conv_id, $user_id, $after),
      ]);

    default:
      communication_error('Unknown action', 404);
  }
} catch (Throwable $e) {
  error_log("Communication API error: " . $e->getMessage());
  communication_error('Server error', 500);
}
