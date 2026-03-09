<?php

/**
 * Enhanced Chat API - WhatsApp/Telegram Style
 * Real-time messaging with threading, reactions, typing indicators
 */

session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Update user online status
updateOnlineStatus($user_id);

switch ($action) {

    case 'get_conversations':
        // Get all conversations for the user
        $query = "
            SELECT DISTINCT c.*,
                   cp.last_read_at,
                   cp.is_muted,
                   cp.is_archived,
                   cp.is_pinned,
                   u.first_name, u.last_name, u.role as other_user_role,
                   uos.is_online, uos.last_seen
            FROM conversations c
            JOIN conversation_participants cp ON c.id = cp.conversation_id
            LEFT JOIN conversation_participants cp2 ON c.id = cp2.conversation_id AND cp2.user_id != ?
            LEFT JOIN users u ON cp2.user_id = u.id
            LEFT JOIN user_online_status uos ON u.id = uos.user_id
            WHERE cp.user_id = ? AND cp.is_archived = 0
            ORDER BY cp.is_pinned DESC, c.last_message_at DESC
            LIMIT 100
        ";

        $conversations = db()->fetchAll($query, [$user_id, $user_id]);

        // Calculate unread count for each conversation
        foreach ($conversations as &$conv) {
            $unread = db()->fetchOne("
                SELECT COUNT(*) as count FROM conversation_messages
                WHERE conversation_id = ?
                AND created_at > COALESCE(?, '1970-01-01')
                AND sender_id != ?
            ", [$conv['id'], $conv['last_read_at'], $user_id]);
            $conv['unread_count'] = $unread['count'] ?? 0;
        }

        echo json_encode(['success' => true, 'conversations' => $conversations]);
        break;

    case 'get_messages':
        $conversation_id = intval($_GET['conversation_id'] ?? 0);
        $limit = intval($_GET['limit'] ?? 50);
        $before_id = intval($_GET['before_id'] ?? 0);

        $where = "cm.conversation_id = ?";
        $params = [$conversation_id];

        if ($before_id > 0) {
            $where .= " AND cm.id < ?";
            $params[] = $before_id;
        }

        $messages = db()->fetchAll("
            SELECT cm.*,
                   u.first_name, u.last_name, u.role,
                   cm.is_read_by,
                   reply_msg.message_text as reply_to_text,
                   reply_user.first_name as reply_to_user_first_name,
                   reply_user.last_name as reply_to_user_last_name
            FROM conversation_messages cm
            JOIN users u ON cm.sender_id = u.id
            LEFT JOIN conversation_messages reply_msg ON cm.reply_to_message_id = reply_msg.id
            LEFT JOIN users reply_user ON reply_msg.sender_id = reply_user.id
            WHERE $where
            ORDER BY cm.created_at DESC
            LIMIT ?
        ", array_merge($params, [$limit]));

        // Get reactions for each message
        foreach ($messages as &$msg) {
            $msg['reactions'] = db()->fetchAll("
                SELECT mr.*, u.first_name, u.last_name
                FROM message_reactions mr
                JOIN users u ON mr.user_id = u.id
                WHERE mr.message_id = ?
            ", [$msg['id']]);

            // Get attachments
            $msg['attachments'] = db()->fetchAll("
                SELECT * FROM message_attachments WHERE message_id = ?
            ", [$msg['id']]);

            // Decode is_read_by JSON
            $msg['is_read_by'] = json_decode($msg['is_read_by'] ?? '[]', true);
        }

        // Mark messages as read
        markConversationAsRead($conversation_id, $user_id);

        echo json_encode(['success' => true, 'messages' => array_reverse($messages)]);
        break;

    case 'send_message':
        $conversation_id = intval($_POST['conversation_id'] ?? 0);
        $message_text = trim($_POST['message'] ?? '');
        $reply_to_id = intval($_POST['reply_to_message_id'] ?? 0);

        if (empty($message_text) && empty($_FILES['attachments'])) {
            echo json_encode(['success' => false, 'error' => 'Message or attachment required']);
            exit;
        }

        // If no conversation_id, create new conversation
        if ($conversation_id == 0) {
            $recipient_id = intval($_POST['recipient_id'] ?? 0);
            if (!$recipient_id) {
                echo json_encode(['success' => false, 'error' => 'Recipient required for new conversation']);
                exit;
            }

            // Check if conversation already exists
            $existing = db()->fetchOne("
                SELECT c.id FROM conversations c
                JOIN conversation_participants cp1 ON c.id = cp1.conversation_id
                JOIN conversation_participants cp2 ON c.id = cp2.conversation_id
                WHERE c.is_group = 0
                AND cp1.user_id = ? AND cp2.user_id = ?
            ", [$user_id, $recipient_id]);

            if ($existing) {
                $conversation_id = $existing['id'];
            } else {
                // Create new conversation
                $conversation_id = db()->insert('conversations', [
                    'subject' => 'Direct Chat',
                    'started_by' => $user_id,
                    'participants' => json_encode([$user_id, $recipient_id]),
                    'is_group' => 0,
                    'created_at' => date('Y-m-d H:i:s')
                ]);

                // Add participants
                db()->insert('conversation_participants', [
                    'conversation_id' => $conversation_id,
                    'user_id' => $user_id,
                    'joined_at' => date('Y-m-d H:i:s')
                ]);

                db()->insert('conversation_participants', [
                    'conversation_id' => $conversation_id,
                    'user_id' => $recipient_id,
                    'joined_at' => date('Y-m-d H:i:s')
                ]);
            }
        }

        // Insert message
        $message_id = db()->insert('conversation_messages', [
            'conversation_id' => $conversation_id,
            'sender_id' => $user_id,
            'message_text' => $message_text,
            'reply_to_message_id' => $reply_to_id > 0 ? $reply_to_id : null,
            'is_read_by' => json_encode([$user_id]),
            'created_at' => date('Y-m-d H:i:s')
        ]);

        // Handle file attachments
        $attachments = [];
        if (!empty($_FILES['attachments'])) {
            $upload_dir = '../uploads/chat_attachments/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            foreach ($_FILES['attachments']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['attachments']['error'][$key] == 0) {
                    $file_name = sanitize_filename($_FILES['attachments']['name'][$key]);
                    $file_path = $upload_dir . time() . '_' . $file_name;

                    if (move_uploaded_file($tmp_name, $file_path)) {
                        $attachment_id = db()->insert('message_attachments', [
                            'message_id' => $message_id,
                            'file_name' => $file_name,
                            'file_path' => $file_path,
                            'file_type' => $_FILES['attachments']['type'][$key],
                            'file_size' => $_FILES['attachments']['size'][$key]
                        ]);

                        $attachments[] = [
                            'id' => $attachment_id,
                            'file_name' => $file_name,
                            'file_path' => $file_path
                        ];
                    }
                }
            }
        }

        // Update conversation last_message
        db()->execute("
            UPDATE conversations
            SET last_message_at = ?,
                last_message_text = ?,
                last_message_sender_id = ?
            WHERE id = ?
        ", [date('Y-m-d H:i:s'), substr($message_text, 0, 100), $user_id, $conversation_id]);

        // Send notifications to other participants
        $participants = db()->fetchAll("
            SELECT user_id FROM conversation_participants
            WHERE conversation_id = ? AND user_id != ?
        ", [$conversation_id, $user_id]);

        foreach ($participants as $participant) {
            // Create notification
            db()->insert('notifications', [
                'user_id' => $participant['user_id'],
                'title' => 'New message',
                'message' => substr($message_text, 0, 100),
                'type' => 'message',
                'link' => '/attendance/chat.php?conversation=' . $conversation_id,
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }

        echo json_encode([
            'success' => true,
            'message_id' => $message_id,
            'conversation_id' => $conversation_id,
            'attachments' => $attachments
        ]);
        break;

    case 'mark_as_read':
        $message_id = intval($_POST['message_id'] ?? 0);
        $conversation_id = intval($_POST['conversation_id'] ?? 0);

        if ($conversation_id > 0) {
            markConversationAsRead($conversation_id, $user_id);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Invalid conversation']);
        }
        break;

    case 'add_reaction':
        $message_id = intval($_POST['message_id'] ?? 0);
        $reaction = trim($_POST['reaction'] ?? '');

        if ($message_id && $reaction) {
            try {
                db()->insert('message_reactions', [
                    'message_id' => $message_id,
                    'user_id' => $user_id,
                    'reaction' => $reaction
                ]);
                echo json_encode(['success' => true]);
            } catch (Exception $e) {
                // Reaction might already exist
                echo json_encode(['success' => false, 'error' => 'Reaction already added']);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
        }
        break;

    case 'remove_reaction':
        $message_id = intval($_POST['message_id'] ?? 0);
        $reaction = trim($_POST['reaction'] ?? '');

        db()->execute("
            DELETE FROM message_reactions
            WHERE message_id = ? AND user_id = ? AND reaction = ?
        ", [$message_id, $user_id, $reaction]);

        echo json_encode(['success' => true]);
        break;

    case 'typing':
        $conversation_id = intval($_POST['conversation_id'] ?? 0);
        $is_typing = intval($_POST['is_typing'] ?? 1);

        // Delete old typing indicator
        db()->execute(
            "DELETE FROM typing_indicators WHERE user_id = ? AND conversation_id = ?",
            [$user_id, $conversation_id]
        );

        if ($is_typing) {
            db()->insert('typing_indicators', [
                'user_id' => $user_id,
                'conversation_id' => $conversation_id,
                'is_typing' => 1
            ]);
        }

        echo json_encode(['success' => true]);
        break;

    case 'get_typing':
        $conversation_id = intval($_GET['conversation_id'] ?? 0);

        // Get users typing (exclude self, and only recent within 10 seconds)
        $typing_users = db()->fetchAll("
            SELECT ti.user_id, u.first_name, u.last_name
            FROM typing_indicators ti
            JOIN users u ON ti.user_id = u.id
            WHERE ti.conversation_id = ?
            AND ti.user_id != ?
            AND ti.updated_at >= DATE_SUB(NOW(), INTERVAL 10 SECOND)
        ", [$conversation_id, $user_id]);

        echo json_encode(['success' => true, 'typing_users' => $typing_users]);
        break;

    case 'delete_message':
        $message_id = intval($_POST['message_id'] ?? 0);

        // Only sender can delete
        $message = db()->fetchOne("SELECT * FROM conversation_messages WHERE id = ?", [$message_id]);

        if ($message && $message['sender_id'] == $user_id) {
            db()->execute("
                UPDATE conversation_messages
                SET is_deleted = 1, message_text = '[Message deleted]'
                WHERE id = ?
            ", [$message_id]);

            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        }
        break;

    case 'edit_message':
        $message_id = intval($_POST['message_id'] ?? 0);
        $new_text = trim($_POST['message_text'] ?? '');

        if (empty($new_text)) {
            echo json_encode(['success' => false, 'error' => 'Message cannot be empty']);
            exit;
        }

        $message = db()->fetchOne("SELECT * FROM conversation_messages WHERE id = ?", [$message_id]);

        if ($message && $message['sender_id'] == $user_id) {
            // Save edit history
            db()->insert('message_edit_history', [
                'message_id' => $message_id,
                'original_text' => $message['message_text']
            ]);

            // Update message
            db()->execute("
                UPDATE conversation_messages
                SET message_text = ?, is_edited = 1, edited_at = NOW()
                WHERE id = ?
            ", [$new_text, $message_id]);

            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        }
        break;

    case 'search_users':
        $query = trim($_GET['q'] ?? '');

        if (strlen($query) < 2) {
            echo json_encode(['success' => true, 'users' => []]);
            exit;
        }

        $users = db()->fetchAll("
            SELECT id, first_name, last_name, email, role
            FROM users
            WHERE id != ?
            AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ?)
            AND status = 'active'
            LIMIT 20
        ", [$user_id, "%$query%", "%$query%", "%$query%"]);

        echo json_encode(['success' => true, 'users' => $users]);
        break;

    case 'get_online_status':
        $user_ids = json_decode($_GET['user_ids'] ?? '[]', true);

        if (empty($user_ids)) {
            echo json_encode(['success' => true, 'statuses' => []]);
            exit;
        }

        $placeholders = implode(',', array_fill(0, count($user_ids), '?'));
        $statuses = db()->fetchAll("
            SELECT user_id, is_online, last_seen
            FROM user_online_status
            WHERE user_id IN ($placeholders)
        ", $user_ids);

        echo json_encode(['success' => true, 'statuses' => $statuses]);
        break;

    case 'create_group':
        $group_name = trim($_POST['group_name'] ?? '');
        $participant_ids = json_decode($_POST['participant_ids'] ?? '[]', true);

        if (empty($group_name) || empty($participant_ids)) {
            echo json_encode(['success' => false, 'error' => 'Group name and participants required']);
            exit;
        }

        // Add creator to participants
        $participant_ids[] = $user_id;
        $participant_ids = array_unique($participant_ids);

        // Create conversation
        $conversation_id = db()->insert('conversations', [
            'subject' => $group_name,
            'started_by' => $user_id,
            'participants' => json_encode($participant_ids),
            'is_group' => 1,
            'created_at' => date('Y-m-d H:i:s')
        ]);

        // Add all participants
        foreach ($participant_ids as $pid) {
            db()->insert('conversation_participants', [
                'conversation_id' => $conversation_id,
                'user_id' => $pid,
                'joined_at' => date('Y-m-d H:i:s')
            ]);
        }

        echo json_encode(['success' => true, 'conversation_id' => $conversation_id]);
        break;

    case 'add_contact':
        $contact_user_id = intval($_POST['contact_user_id'] ?? 0);
        $nickname = trim($_POST['nickname'] ?? '');

        if (!$contact_user_id) {
            echo json_encode(['success' => false, 'error' => 'Contact user ID required']);
            exit;
        }

        try {
            db()->insert('chat_contacts', [
                'user_id' => $user_id,
                'contact_user_id' => $contact_user_id,
                'nickname' => $nickname,
                'added_at' => date('Y-m-d H:i:s')
            ]);
            echo json_encode(['success' => true, 'message' => 'Contact added successfully']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Contact already exists or error occurred']);
        }
        break;

    case 'remove_contact':
        $contact_user_id = intval($_POST['contact_user_id'] ?? 0);

        db()->execute("
            DELETE FROM chat_contacts
            WHERE user_id = ? AND contact_user_id = ?
        ", [$user_id, $contact_user_id]);

        echo json_encode(['success' => true, 'message' => 'Contact removed']);
        break;

    case 'toggle_favorite':
        $contact_user_id = intval($_POST['contact_user_id'] ?? 0);

        // Check if contact exists
        $contact = db()->fetchOne("
            SELECT * FROM chat_contacts
            WHERE user_id = ? AND contact_user_id = ?
        ", [$user_id, $contact_user_id]);

        if ($contact) {
            // Toggle favorite
            db()->execute("
                UPDATE chat_contacts
                SET is_favorite = NOT is_favorite
                WHERE user_id = ? AND contact_user_id = ?
            ", [$user_id, $contact_user_id]);
        } else {
            // Add as contact and favorite
            db()->insert('chat_contacts', [
                'user_id' => $user_id,
                'contact_user_id' => $contact_user_id,
                'is_favorite' => 1,
                'added_at' => date('Y-m-d H:i:s')
            ]);
        }

        echo json_encode(['success' => true]);
        break;

    case 'get_contacts':
        $contacts = db()->fetchAll("
            SELECT cc.*, u.first_name, u.last_name, u.email, u.role,
                   uos.is_online, uos.last_seen
            FROM chat_contacts cc
            JOIN users u ON cc.contact_user_id = u.id
            LEFT JOIN user_online_status uos ON u.id = uos.user_id
            WHERE cc.user_id = ? AND cc.is_blocked = 0
            ORDER BY cc.is_favorite DESC, u.first_name ASC
        ", [$user_id]);

        echo json_encode(['success' => true, 'contacts' => $contacts]);
        break;

    case 'search_all_users':
        $query = trim($_GET['q'] ?? '');
        $role_filter = trim($_GET['role'] ?? '');

        if (strlen($query) < 2) {
            echo json_encode(['success' => true, 'users' => []]);
            exit;
        }

        $where = "u.id != ? AND u.status = 'active' AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
        $params = [$user_id, "%$query%", "%$query%", "%$query%"];

        if ($role_filter && in_array($role_filter, ['student', 'teacher', 'parent', 'admin'])) {
            $where .= " AND u.role = ?";
            $params[] = $role_filter;
        }

        $users = db()->fetchAll("
            SELECT u.id, u.first_name, u.last_name, u.email, u.role,
                   uos.is_online, uos.last_seen,
                   cc.id as is_contact, cc.is_favorite,
                   (SELECT COUNT(*) FROM conversation_participants cp1
                    JOIN conversation_participants cp2 ON cp1.conversation_id = cp2.conversation_id
                    JOIN conversations c ON cp1.conversation_id = c.id
                    WHERE cp1.user_id = ? AND cp2.user_id = u.id AND c.is_group = 0) as has_conversation
            FROM users u
            LEFT JOIN user_online_status uos ON u.id = uos.user_id
            LEFT JOIN chat_contacts cc ON cc.user_id = ? AND cc.contact_user_id = u.id
            WHERE $where
            ORDER BY cc.is_favorite DESC, u.role ASC, u.first_name ASC
            LIMIT 50
        ", array_merge([$user_id, $user_id], $params));

        echo json_encode(['success' => true, 'users' => $users]);
        break;

    case 'get_user_by_role':
        $role = trim($_GET['role'] ?? '');

        if (!in_array($role, ['student', 'teacher', 'parent', 'admin'])) {
            echo json_encode(['success' => false, 'error' => 'Invalid role']);
            exit;
        }

        $users = db()->fetchAll("
            SELECT u.id, u.first_name, u.last_name, u.email, u.role,
                   uos.is_online, uos.last_seen,
                   cc.id as is_contact, cc.is_favorite
            FROM users u
            LEFT JOIN user_online_status uos ON u.id = uos.user_id
            LEFT JOIN chat_contacts cc ON cc.user_id = ? AND cc.contact_user_id = u.id
            WHERE u.role = ? AND u.id != ? AND u.status = 'active'
            ORDER BY u.first_name ASC
            LIMIT 100
        ", [$user_id, $role, $user_id]);

        echo json_encode(['success' => true, 'users' => $users]);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        break;
}

// Helper functions
function updateOnlineStatus($user_id)
{
    try {
        db()->execute("
            INSERT INTO user_online_status (user_id, is_online, last_seen, last_activity)
            VALUES (?, 1, NOW(), NOW())
            ON DUPLICATE KEY UPDATE is_online = 1, last_activity = NOW()
        ", [$user_id]);
    } catch (Exception $e) {
        error_log("Online status update error: " . $e->getMessage());
    }
}

function markConversationAsRead($conversation_id, $user_id)
{
    // Update participant last_read_at
    db()->execute("
        UPDATE conversation_participants
        SET last_read_at = NOW()
        WHERE conversation_id = ? AND user_id = ?
    ", [$conversation_id, $user_id]);

    // Update is_read_by for all messages
    $messages = db()->fetchAll("
        SELECT id, is_read_by FROM conversation_messages
        WHERE conversation_id = ? AND sender_id != ?
    ", [$conversation_id, $user_id]);

    foreach ($messages as $msg) {
        $read_by = json_decode($msg['is_read_by'] ?? '[]', true);
        if (!in_array($user_id, $read_by)) {
            $read_by[] = $user_id;
            db()->execute("
                UPDATE conversation_messages
                SET is_read_by = ?
                WHERE id = ?
            ", [json_encode($read_by), $msg['id']]);
        }
    }
}

function sanitize_filename($filename)
{
    $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
    return $filename;
}
