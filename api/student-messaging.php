<?php

/**
 * Student Messaging API
 * Handles peer-to-peer student communication
 */
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$student_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'send_message':
        $to_student_id = intval($_POST['to_student_id']);
        $message = trim($_POST['message'] ?? '');
        $subject = trim($_POST['subject'] ?? 'Chat Message');
        $reply_to_id = isset($_POST['reply_to_id']) ? intval($_POST['reply_to_id']) : null;

        if (empty($message)) {
            echo json_encode(['success' => false, 'error' => 'Message cannot be empty']);
            exit;
        }

        // Verify both are students and in same class
        $canMessage = db()->fetchOne("
            SELECT COUNT(*) as can_msg
            FROM class_enrollments ce1
            JOIN class_enrollments ce2 ON ce1.class_id = ce2.class_id
            WHERE ce1.student_id = ? AND ce2.student_id = ?
        ", [$student_id, $to_student_id]);

        if (!$canMessage || $canMessage['can_msg'] == 0) {
            echo json_encode(['success' => false, 'error' => 'You can only message classmates']);
            exit;
        }

        // Get or create conversation
        $conversation = db()->fetchOne("
            SELECT c.id
            FROM conversations c
            WHERE c.is_group = 0
            AND JSON_CONTAINS(c.participants, ?, '$')
            AND JSON_CONTAINS(c.participants, ?, '$')
        ", [json_encode($student_id), json_encode($to_student_id)]);

        if (!$conversation) {
            // Create new conversation
            $conversation_id = db()->insert('conversations', [
                'subject' => $subject,
                'started_by' => $student_id,
                'participants' => json_encode([$student_id, $to_student_id]),
                'last_message_at' => date('Y-m-d H:i:s'),
                'is_group' => 0
            ]);
        } else {
            $conversation_id = $conversation['id'];
            // Update last message time
            db()->update('conversations', [
                'last_message_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$conversation_id]);
        }

        // Insert message
        $message_id = db()->insert('student_messages', [
            'conversation_id' => $conversation_id,
            'from_student_id' => $student_id,
            'to_student_id' => $to_student_id,
            'subject' => $subject,
            'message' => $message,
            'parent_message_id' => $reply_to_id,
            'created_at' => date('Y-m-d H:i:s')
        ]);

        // Create notification for recipient
        try {
            $sender = db()->fetchOne("SELECT first_name, last_name FROM users WHERE id = ?", [$student_id]);
            db()->insert('notifications', [
                'user_id' => $to_student_id,
                'title' => 'New Message from ' . $sender['first_name'] . ' ' . $sender['last_name'],
                'message' => substr($message, 0, 100),
                'type' => 'message',
                'link' => '/attendance/student/communication.php',
                'created_at' => date('Y-m-d H:i:s')
            ]);
        } catch (Exception $e) {
            // Notifications optional
        }

        echo json_encode(['success' => true, 'message_id' => $message_id]);
        break;

    case 'get_messages':
        $contact_id = intval($_GET['contact_id']);

        // Get conversation
        $conversation = db()->fetchOne("
            SELECT c.id
            FROM conversations c
            WHERE c.is_group = 0
            AND JSON_CONTAINS(c.participants, ?, '$')
            AND JSON_CONTAINS(c.participants, ?, '$')
        ", [json_encode($student_id), json_encode($contact_id)]);

        if (!$conversation) {
            echo json_encode(['success' => true, 'messages' => []]);
            exit;
        }

        // Get messages
        $messages = db()->fetchAll("
            SELECT sm.*,
                   u1.first_name as from_first_name, u1.last_name as from_last_name,
                   u2.first_name as to_first_name, u2.last_name as to_last_name
            FROM student_messages sm
            JOIN users u1 ON sm.from_student_id = u1.id
            JOIN users u2 ON sm.to_student_id = u2.id
            WHERE sm.conversation_id = ?
            ORDER BY sm.created_at ASC
        ", [$conversation['id']]);

        // Mark as read
        db()->query("
            UPDATE student_messages
            SET is_read = 1
            WHERE conversation_id = ?
            AND to_student_id = ?
            AND is_read = 0
        ", [$conversation['id'], $student_id]);

        echo json_encode(['success' => true, 'messages' => $messages]);
        break;

    case 'unread_counts':
        // Get unread count for each contact
        $counts = db()->fetchAll("
            SELECT from_student_id, COUNT(*) as count
            FROM student_messages
            WHERE to_student_id = ? AND is_read = 0
            GROUP BY from_student_id
        ", [$student_id]);

        $result = [];
        foreach ($counts as $count) {
            $result[$count['from_student_id']] = $count['count'];
        }

        echo json_encode(['success' => true, 'counts' => $result]);
        break;

    case 'get_conversations':
        // Get all conversations with last message
        $conversations = db()->fetchAll("
            SELECT DISTINCT
                c.id,
                c.subject,
                c.last_message_at,
                CASE
                    WHEN JSON_EXTRACT(c.participants, '$[0]') = ? THEN JSON_EXTRACT(c.participants, '$[1]')
                    ELSE JSON_EXTRACT(c.participants, '$[0]')
                END as other_user_id,
                (SELECT COUNT(*) FROM student_messages WHERE conversation_id = c.id AND to_student_id = ? AND is_read = 0) as unread_count
            FROM conversations c
            WHERE JSON_CONTAINS(c.participants, ?, '$')
            AND c.is_group = 0
            ORDER BY c.last_message_at DESC
        ", [$student_id, $student_id, json_encode($student_id)]);

        // Get user details for each conversation
        foreach ($conversations as &$conv) {
            $user = db()->fetchOne("SELECT first_name, last_name FROM users WHERE id = ?", [$conv['other_user_id']]);
            $conv['other_user_name'] = $user['first_name'] . ' ' . $user['last_name'];

            // Get last message
            $lastMsg = db()->fetchOne("
                SELECT message, created_at
                FROM student_messages
                WHERE conversation_id = ?
                ORDER BY created_at DESC
                LIMIT 1
            ", [$conv['id']]);
            $conv['last_message'] = $lastMsg['message'] ?? '';
            $conv['last_message_time'] = $lastMsg['created_at'] ?? $conv['last_message_at'];
        }

        echo json_encode(['success' => true, 'conversations' => $conversations]);
        break;

    case 'mark_as_read':
        $conversation_id = intval($_POST['conversation_id']);

        db()->query("
            UPDATE student_messages
            SET is_read = 1
            WHERE conversation_id = ?
            AND to_student_id = ?
        ", [$conversation_id, $student_id]);

        echo json_encode(['success' => true]);
        break;

    case 'delete_conversation':
        $conversation_id = intval($_POST['conversation_id']);

        // Verify user is participant
        $conversation = db()->fetchOne("
            SELECT id
            FROM conversations
            WHERE id = ?
            AND JSON_CONTAINS(participants, ?, '$')
        ", [$conversation_id, json_encode($student_id)]);

        if (!$conversation) {
            echo json_encode(['success' => false, 'error' => 'Not authorized']);
            exit;
        }

        // Delete messages
        db()->query("DELETE FROM student_messages WHERE conversation_id = ?", [$conversation_id]);

        // Delete conversation
        db()->query("DELETE FROM conversations WHERE id = ?", [$conversation_id]);

        echo json_encode(['success' => true]);
        break;

    case 'save_contact_name':
        $contact_id = intval($_POST['contact_id']);
        $custom_name = trim($_POST['custom_name'] ?? '');

        if (empty($custom_name)) {
            echo json_encode(['success' => false, 'error' => 'Name cannot be empty']);
            exit;
        }

        // Check if custom name entry exists
        $existing = db()->fetchOne("
            SELECT id FROM contact_custom_names
            WHERE user_id = ? AND contact_id = ?
        ", [$student_id, $contact_id]);

        if ($existing) {
            db()->update('contact_custom_names', [
                'custom_name' => $custom_name,
                'updated_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$existing['id']]);
        } else {
            db()->insert('contact_custom_names', [
                'user_id' => $student_id,
                'contact_id' => $contact_id,
                'custom_name' => $custom_name,
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }

        echo json_encode(['success' => true, 'custom_name' => $custom_name]);
        break;

    case 'get_contact_name':
        $contact_id = intval($_GET['contact_id']);

        $custom = db()->fetchOne("
            SELECT custom_name FROM contact_custom_names
            WHERE user_id = ? AND contact_id = ?
        ", [$student_id, $contact_id]);

        $actual = db()->fetchOne("
            SELECT first_name, last_name FROM users WHERE id = ?
        ", [$contact_id]);

        echo json_encode([
            'success' => true,
            'custom_name' => $custom['custom_name'] ?? null,
            'actual_name' => ($actual['first_name'] ?? '') . ' ' . ($actual['last_name'] ?? '')
        ]);
        break;

    case 'set_typing':
        $contact_id = intval($_POST['contact_id']);
        $is_typing = isset($_POST['is_typing']) && $_POST['is_typing'];

        // Store typing status in session or cache (simplified version)
        $_SESSION['typing_status'][$contact_id] = [
            'user_id' => $student_id,
            'is_typing' => $is_typing,
            'timestamp' => time()
        ];

        echo json_encode(['success' => true]);
        break;

    case 'get_typing_status':
        $contact_id = intval($_GET['contact_id']);

        // Check if contact is typing
        $typing = $_SESSION['typing_status'][$student_id] ?? null;
        $is_typing = false;

        if ($typing && $typing['user_id'] == $contact_id) {
            // Check if timestamp is within last 5 seconds
            if ((time() - $typing['timestamp']) < 5) {
                $is_typing = $typing['is_typing'];
            }
        }

        echo json_encode(['success' => true, 'is_typing' => $is_typing]);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        break;
}
