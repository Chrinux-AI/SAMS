<?php
/**
 * Messaging API - Send, Receive, Read Messages
 * Supports direct, broadcast, and role-based delivery.
 */

session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/api-response.php';
require_once __DIR__ . '/../includes/system-log.php';

api_require_auth();

$user_id = (int)($_SESSION['user_id'] ?? 0);
$user_role = (string)($_SESSION['role'] ?? '');
$action = trim((string)($_GET['action'] ?? $_POST['action'] ?? ''));

function messaging_excerpt(string $text, int $maxLen = 100): string
{
    if (function_exists('mb_substr') && function_exists('mb_strlen')) {
        return mb_substr($text, 0, $maxLen) . (mb_strlen($text) > $maxLen ? '...' : '');
    }
    return substr($text, 0, $maxLen) . (strlen($text) > $maxLen ? '...' : '');
}

if ($user_id <= 0) {
    api_error('Unauthorized', 401);
}

try {
    switch ($action) {
        case 'send':
            $receiver_id = isset($_POST['receiver_id']) ? (int)$_POST['receiver_id'] : 0;
            $recipient_role = trim((string)($_POST['recipient_role'] ?? ''));
            $subject = trim((string)($_POST['subject'] ?? ''));
            $message = trim((string)($_POST['message'] ?? ''));

            if ($subject === '' || $message === '') {
                api_error('Subject and message required', 422);
            }

            // Only admins can broadcast.
            if ($recipient_role !== '' && $recipient_role !== 'direct' && $user_role !== 'admin') {
                api_error('Only admins can send broadcast messages', 403);
            }

            $message_id = db()->insert('messages', [
                'sender_id' => $user_id,
                'receiver_id' => $receiver_id > 0 ? $receiver_id : null,
                'recipient_role' => $recipient_role !== '' ? $recipient_role : null,
                'subject' => $subject,
                'message' => $message,
                'created_at' => date('Y-m-d H:i:s')
            ]);

            if (!$message_id) {
                api_error('Failed to send message', 500);
            }

            if ($recipient_role !== '' && $recipient_role !== 'direct') {
                $where = '';
                $params = [];
                if ($recipient_role === 'all') {
                    $where = 'id != ? AND status = ? AND approved = ?';
                    $params = [$user_id, 'active', 1];
                } else {
                    $where = 'role = ? AND id != ? AND status = ? AND approved = ?';
                    $params = [$recipient_role, $user_id, 'active', 1];
                }

                $recipients = db()->fetchAll("SELECT id FROM users WHERE {$where}", $params);
                if (empty($recipients)) {
                    api_error('No recipients found for this role', 404);
                }

                foreach ($recipients as $recipient) {
                    db()->insert('message_recipients', [
                        'message_id' => $message_id,
                        'recipient_id' => (int)$recipient['id'],
                        'created_at' => date('Y-m-d H:i:s')
                    ]);

                    insert_flexible('notifications', [
                        'user_id' => (int)$recipient['id'],
                        'title' => 'New Message: ' . $subject,
                        'message' => messaging_excerpt($message, 100),
                        'type' => 'message',
                        'link' => '/attendance/messages.php?id=' . $message_id,
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                }

                api_json_response([
                    'success' => true,
                    'message_id' => $message_id,
                    'recipients_count' => count($recipients),
                    'message' => 'Broadcast message sent to ' . count($recipients) . ' users'
                ]);
            }

            if ($receiver_id <= 0) {
                api_error('No recipient specified', 422);
            }

            db()->insert('message_recipients', [
                'message_id' => $message_id,
                'recipient_id' => $receiver_id,
                'created_at' => date('Y-m-d H:i:s')
            ]);

            insert_flexible('notifications', [
                'user_id' => $receiver_id,
                'title' => 'New Message: ' . $subject,
                'message' => messaging_excerpt($message, 100),
                'type' => 'message',
                'link' => '/attendance/messages.php?id=' . $message_id,
                'created_at' => date('Y-m-d H:i:s')
            ]);

            api_json_response(['success' => true, 'message_id' => $message_id, 'message' => 'Direct message sent']);
            break;

        case 'inbox':
            $messages = db()->fetchAll("
                SELECT DISTINCT m.*, u.first_name, u.last_name, u.role AS sender_role,
                       COALESCE(mr.is_read, m.is_read, 0) AS is_read,
                       COALESCE(mr.read_at, m.read_at) AS read_at
                FROM messages m
                JOIN users u ON m.sender_id = u.id
                LEFT JOIN message_recipients mr ON m.id = mr.message_id AND mr.recipient_id = ?
                WHERE (mr.recipient_id = ? AND mr.deleted_at IS NULL)
                   OR (m.receiver_id = ? AND m.recipient_role IS NULL)
                ORDER BY m.created_at DESC
                LIMIT 100
            ", [$user_id, $user_id, $user_id]);

            api_json_response(['success' => true, 'messages' => $messages]);
            break;

        case 'sent':
            $messages = db()->fetchAll("
                SELECT m.*, u.first_name, u.last_name, u.role AS receiver_role
                FROM messages m
                LEFT JOIN users u ON m.receiver_id = u.id
                WHERE m.sender_id = ?
                ORDER BY m.created_at DESC
                LIMIT 100
            ", [$user_id]);

            api_json_response(['success' => true, 'messages' => $messages]);
            break;

        case 'read':
            $message_id = (int)($_POST['message_id'] ?? 0);
            if ($message_id <= 0) {
                api_error('Invalid message id', 422);
            }

            db()->query(
                "UPDATE message_recipients
                 SET is_read = 1, read_at = ?
                 WHERE message_id = ? AND recipient_id = ?",
                [date('Y-m-d H:i:s'), $message_id, $user_id]
            );

            db()->query(
                "UPDATE messages
                 SET is_read = 1, read_at = ?
                 WHERE id = ? AND receiver_id = ?",
                [date('Y-m-d H:i:s'), $message_id, $user_id]
            );

            api_json_response(['success' => true]);
            break;

        case 'delete':
            $message_id = (int)($_POST['message_id'] ?? 0);
            if ($message_id <= 0) {
                api_error('Invalid message id', 422);
            }

            db()->query(
                "UPDATE message_recipients
                 SET deleted_at = ?
                 WHERE message_id = ? AND recipient_id = ?",
                [date('Y-m-d H:i:s'), $message_id, $user_id]
            );

            api_json_response(['success' => true]);
            break;

        case 'users':
            $nameExpr = table_has_column('users', 'username')
                ? "COALESCE(NULLIF(username, ''), CONCAT(first_name, ' ', last_name))"
                : "CONCAT(first_name, ' ', last_name)";

            $users = db()->fetchAll("
                SELECT id, {$nameExpr} AS username, CONCAT(first_name, ' ', last_name) AS full_name, role, email
                FROM users
                WHERE id != ? AND status = 'active' AND approved = 1
                ORDER BY role, first_name
            ", [$user_id]);

            api_json_response(['success' => true, 'users' => $users]);
            break;

        case 'unread_count':
            $result = db()->fetchOne("
                SELECT COUNT(*) AS count
                FROM message_recipients
                WHERE recipient_id = ? AND is_read = 0 AND deleted_at IS NULL
            ", [$user_id]);
            $count = (int)($result['count'] ?? 0);
            api_json_response(['success' => true, 'count' => $count]);
            break;

        default:
            api_error('Invalid action', 404);
    }
} catch (Throwable $e) {
    error_log('Messaging API error: ' . $e->getMessage());
    system_log('ERROR', 'Messaging API failure', [
        'action' => $action,
        'user_id' => $user_id ?? null,
        'message' => $e->getMessage()
    ]);
    api_error('Internal server error', 500);
}
