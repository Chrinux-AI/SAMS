<?php

/**
 * Enhanced Student Messaging API
 * Complete communication platform with all features from specification
 */

session_start();
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/logger.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        // ==================== DIRECT MESSAGING ====================
        case 'send_message':
            $to_user_id = $_POST['to_user_id'] ?? null;
            $message = trim($_POST['message'] ?? '');
            $parent_id = $_POST['parent_message_id'] ?? null;
            $attachment = $_FILES['attachment'] ?? null;

            if (!$to_user_id || !$message) {
                throw new Exception('Recipient and message required');
            }

            // Handle file upload
            $file_path = null;
            $file_name = null;
            $file_size = null;
            $file_type = null;

            if ($attachment && $attachment['error'] === UPLOAD_ERR_OK) {
                $allowed_types = [
                    'image/jpeg',
                    'image/png',
                    'image/gif',
                    'application/pdf',
                    'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'audio/mpeg',
                    'audio/wav',
                    'audio/ogg'
                ];
                $max_size = 5 * 1024 * 1024; // 5MB

                if (!in_array($attachment['type'], $allowed_types)) {
                    throw new Exception('Invalid file type');
                }

                if ($attachment['size'] > $max_size) {
                    throw new Exception('File too large (max 5MB)');
                }

                $upload_dir = '../uploads/messages/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }

                $file_ext = pathinfo($attachment['name'], PATHINFO_EXTENSION);
                $file_name = $attachment['name'];
                $file_path = $upload_dir . uniqid() . '.' . $file_ext;
                $file_size = $attachment['size'];
                $file_type = $attachment['type'];

                if (!move_uploaded_file($attachment['tmp_name'], $file_path)) {
                    throw new Exception('File upload failed');
                }
            }

            // Insert message
            $id = db()->insert('student_messages', [
                'from_student_id' => $user_id,
                'to_student_id' => $to_user_id,
                'message' => $message,
                'parent_message_id' => $parent_id,
                'file_path' => $file_path,
                'file_name' => $file_name,
                'file_size' => $file_size,
                'file_type' => $file_type,
                'sent_at' => date('Y-m-d H:i:s'),
                'is_read' => 0
            ]);

            // Create notification
            try {
                db()->insert('notifications', [
                    'user_id' => $to_user_id,
                    'message' => 'New message from ' . $_SESSION['full_name'],
                    'icon' => 'envelope',
                    'link' => '/student/messages.php',
                    'created_at' => date('Y-m-d H:i:s'),
                    'is_read' => 0
                ]);
            } catch (Exception $e) {
                // Notification table may not exist yet
            }

            Logger::info('Message sent', ['from' => $user_id, 'to' => $to_user_id, 'id' => $id]);

            echo json_encode([
                'success' => true,
                'message' => 'Message sent successfully',
                'message_id' => $id
            ]);
            break;

        case 'get_conversations':
            $conversations = db()->fetchAll("
                SELECT DISTINCT
                    CASE
                        WHEN m.from_student_id = ? THEN m.to_student_id
                        ELSE m.from_student_id
                    END as contact_id,
                    u.first_name, u.last_name, u.email,
                    MAX(m.sent_at) as last_message_time,
                    (SELECT COUNT(*) FROM student_messages WHERE to_student_id = ? AND from_student_id = contact_id AND is_read = 0) as unread_count,
                    (SELECT message FROM student_messages sm WHERE (sm.from_student_id = contact_id AND sm.to_student_id = ?) OR (sm.from_student_id = ? AND sm.to_student_id = contact_id) ORDER BY sm.sent_at DESC LIMIT 1) as last_message
                FROM student_messages m
                JOIN users u ON u.id = CASE WHEN m.from_student_id = ? THEN m.to_student_id ELSE m.from_student_id END
                WHERE m.from_student_id = ? OR m.to_student_id = ?
                GROUP BY contact_id
                ORDER BY last_message_time DESC
            ", [$user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id]);

            echo json_encode(['success' => true, 'conversations' => $conversations]);
            break;

        case 'get_messages':
            $contact_id = $_GET['contact_id'] ?? null;
            $limit = (int)($_GET['limit'] ?? 50);
            $offset = (int)($_GET['offset'] ?? 0);

            if (!$contact_id) {
                throw new Exception('Contact ID required');
            }

            $messages = db()->fetchAll("
                SELECT m.*,
                    u_from.first_name as from_first_name, u_from.last_name as from_last_name,
                    pm.message as parent_message_text,
                    (SELECT COUNT(*) FROM message_reactions WHERE message_id = m.id) as reaction_count
                FROM student_messages m
                JOIN users u_from ON m.from_student_id = u_from.id
                LEFT JOIN student_messages pm ON m.parent_message_id = pm.id
                WHERE (m.from_student_id = ? AND m.to_student_id = ?) OR (m.from_student_id = ? AND m.to_student_id = ?)
                ORDER BY m.sent_at DESC
                LIMIT ? OFFSET ?
            ", [$user_id, $contact_id, $contact_id, $user_id, $limit, $offset]);

            // Mark as read
            db()->query("
                UPDATE student_messages SET is_read = 1, read_at = NOW()
                WHERE to_student_id = ? AND from_student_id = ? AND is_read = 0
            ", [$user_id, $contact_id]);

            echo json_encode(['success' => true, 'messages' => array_reverse($messages)]);
            break;

        case 'delete_message':
            $message_id = $_POST['message_id'] ?? null;

            if (!$message_id) {
                throw new Exception('Message ID required');
            }

            // Verify ownership
            $message = db()->fetchOne("SELECT * FROM student_messages WHERE id = ? AND from_student_id = ?", [$message_id, $user_id]);
            if (!$message) {
                throw new Exception('Message not found or unauthorized');
            }

            db()->delete('student_messages', ['id' => $message_id]);

            echo json_encode(['success' => true, 'message' => 'Message deleted']);
            break;

        case 'react_to_message':
            $message_id = $_POST['message_id'] ?? null;
            $reaction = $_POST['reaction'] ?? null;

            if (!$message_id || !$reaction) {
                throw new Exception('Message ID and reaction required');
            }

            // Check if reaction table exists, create if not
            try {
                db()->query("
                    CREATE TABLE IF NOT EXISTS message_reactions (
                        id INT PRIMARY KEY AUTO_INCREMENT,
                        message_id INT NOT NULL,
                        user_id INT NOT NULL,
                        reaction VARCHAR(10) NOT NULL,
                        created_at DATETIME NOT NULL,
                        UNIQUE KEY unique_reaction (message_id, user_id)
                    )
                ");
            } catch (Exception $e) {
            }

            // Insert or update reaction
            db()->query("
                INSERT INTO message_reactions (message_id, user_id, reaction, created_at)
                VALUES (?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE reaction = ?
            ", [$message_id, $user_id, $reaction, $reaction]);

            echo json_encode(['success' => true, 'message' => 'Reaction added']);
            break;

        // ==================== GROUP MESSAGING ====================
        case 'create_group':
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $member_ids = json_decode($_POST['member_ids'] ?? '[]', true);

            if (!$name || empty($member_ids)) {
                throw new Exception('Group name and members required');
            }

            // Create group table if not exists
            db()->query("
                CREATE TABLE IF NOT EXISTS message_groups (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    name VARCHAR(255) NOT NULL,
                    description TEXT,
                    created_by INT NOT NULL,
                    created_at DATETIME NOT NULL
                )
            ");

            db()->query("
                CREATE TABLE IF NOT EXISTS group_members (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    group_id INT NOT NULL,
                    user_id INT NOT NULL,
                    role VARCHAR(20) DEFAULT 'member',
                    joined_at DATETIME NOT NULL,
                    UNIQUE KEY unique_member (group_id, user_id)
                )
            ");

            // Create group
            $group_id = db()->insert('message_groups', [
                'name' => $name,
                'description' => $description,
                'created_by' => $user_id,
                'created_at' => date('Y-m-d H:i:s')
            ]);

            // Add creator
            db()->insert('group_members', [
                'group_id' => $group_id,
                'user_id' => $user_id,
                'role' => 'admin',
                'joined_at' => date('Y-m-d H:i:s')
            ]);

            // Add members
            foreach ($member_ids as $member_id) {
                try {
                    db()->insert('group_members', [
                        'group_id' => $group_id,
                        'user_id' => $member_id,
                        'role' => 'member',
                        'joined_at' => date('Y-m-d H:i:s')
                    ]);
                } catch (Exception $e) {
                }
            }

            echo json_encode(['success' => true, 'group_id' => $group_id]);
            break;

        case 'send_group_message':
            $group_id = $_POST['group_id'] ?? null;
            $message = trim($_POST['message'] ?? '');

            if (!$group_id || !$message) {
                throw new Exception('Group ID and message required');
            }

            // Verify membership
            $member = db()->fetchOne("SELECT * FROM group_members WHERE group_id = ? AND user_id = ?", [$group_id, $user_id]);
            if (!$member) {
                throw new Exception('Not a group member');
            }

            // Create group messages table if not exists
            db()->query("
                CREATE TABLE IF NOT EXISTS group_messages (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    group_id INT NOT NULL,
                    user_id INT NOT NULL,
                    message TEXT NOT NULL,
                    sent_at DATETIME NOT NULL
                )
            ");

            // Send message
            $id = db()->insert('group_messages', [
                'group_id' => $group_id,
                'user_id' => $user_id,
                'message' => $message,
                'sent_at' => date('Y-m-d H:i:s')
            ]);

            echo json_encode(['success' => true, 'message_id' => $id]);
            break;

        case 'get_groups':
            try {
                $groups = db()->fetchAll("
                    SELECT g.*,
                        (SELECT COUNT(*) FROM group_members WHERE group_id = g.id) as member_count,
                        gm.role as my_role
                    FROM message_groups g
                    JOIN group_members gm ON g.id = gm.group_id
                    WHERE gm.user_id = ?
                    ORDER BY g.created_at DESC
                ", [$user_id]);

                echo json_encode(['success' => true, 'groups' => $groups]);
            } catch (Exception $e) {
                echo json_encode(['success' => true, 'groups' => []]);
            }
            break;

        case 'get_group_messages':
            $group_id = $_GET['group_id'] ?? null;
            $limit = (int)($_GET['limit'] ?? 50);

            if (!$group_id) {
                throw new Exception('Group ID required');
            }

            $messages = db()->fetchAll("
                SELECT gm.*, u.first_name, u.last_name
                FROM group_messages gm
                JOIN users u ON gm.user_id = u.id
                WHERE gm.group_id = ?
                ORDER BY gm.sent_at DESC
                LIMIT ?
            ", [$group_id, $limit]);

            echo json_encode(['success' => true, 'messages' => array_reverse($messages)]);
            break;

        // ==================== TYPING INDICATORS ====================
        case 'set_typing':
            $contact_id = $_POST['contact_id'] ?? null;
            $is_typing = (bool)($_POST['is_typing'] ?? false);

            if (!$contact_id) {
                throw new Exception('Contact ID required');
            }

            // Store in cache
            $cache_key = "typing_{$user_id}_{$contact_id}";
            if ($is_typing) {
                file_put_contents("/tmp/{$cache_key}", time());
            } else {
                @unlink("/tmp/{$cache_key}");
            }

            echo json_encode(['success' => true]);
            break;

        case 'get_typing_status':
            $contact_id = $_GET['contact_id'] ?? null;

            if (!$contact_id) {
                throw new Exception('Contact ID required');
            }

            $cache_key = "typing_{$contact_id}_{$user_id}";
            $is_typing = false;

            if (file_exists("/tmp/{$cache_key}")) {
                $timestamp = (int)file_get_contents("/tmp/{$cache_key}");
                if (time() - $timestamp < 3) {
                    $is_typing = true;
                } else {
                    @unlink("/tmp/{$cache_key}");
                }
            }

            echo json_encode(['success' => true, 'is_typing' => $is_typing]);
            break;

        // ==================== SEARCH & ARCHIVE ====================
        case 'search_messages':
            $query = trim($_GET['q'] ?? '');
            $contact_id = $_GET['contact_id'] ?? null;

            if (!$query) {
                throw new Exception('Search query required');
            }

            $sql = "
                SELECT m.*,
                    u_from.first_name as from_first_name, u_from.last_name as from_last_name,
                    u_to.first_name as to_first_name, u_to.last_name as to_last_name
                FROM student_messages m
                JOIN users u_from ON m.from_student_id = u_from.id
                JOIN users u_to ON m.to_student_id = u_to.id
                WHERE (m.from_student_id = ? OR m.to_student_id = ?)
                AND m.message LIKE ?
            ";

            $params = [$user_id, $user_id, "%{$query}%"];

            if ($contact_id) {
                $sql .= " AND (m.from_student_id = ? OR m.to_student_id = ?)";
                $params[] = $contact_id;
                $params[] = $contact_id;
            }

            $sql .= " ORDER BY m.sent_at DESC LIMIT 50";

            $results = db()->fetchAll($sql, $params);

            echo json_encode(['success' => true, 'results' => $results]);
            break;

        case 'archive_conversation':
            $contact_id = $_POST['contact_id'] ?? null;

            if (!$contact_id) {
                throw new Exception('Contact ID required');
            }

            // Create archive table if not exists
            db()->query("
                CREATE TABLE IF NOT EXISTS archived_conversations (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    user_id INT NOT NULL,
                    contact_id INT NOT NULL,
                    archived_at DATETIME NOT NULL,
                    UNIQUE KEY unique_archive (user_id, contact_id)
                )
            ");

            db()->query("
                INSERT INTO archived_conversations (user_id, contact_id, archived_at)
                VALUES (?, ?, NOW())
                ON DUPLICATE KEY UPDATE archived_at = NOW()
            ", [$user_id, $contact_id]);

            echo json_encode(['success' => true, 'message' => 'Conversation archived']);
            break;

        case 'export_conversation':
            $contact_id = $_GET['contact_id'] ?? null;

            if (!$contact_id) {
                throw new Exception('Contact ID required');
            }

            $messages = db()->fetchAll("
                SELECT m.*,
                    u_from.first_name as from_first_name, u_from.last_name as from_last_name
                FROM student_messages m
                JOIN users u_from ON m.from_student_id = u_from.id
                WHERE (m.from_student_id = ? AND m.to_student_id = ?) OR (m.from_student_id = ? AND m.to_student_id = ?)
                ORDER BY m.sent_at ASC
            ", [$user_id, $contact_id, $contact_id, $user_id]);

            $export_text = "Message Export - Generated on " . date('Y-m-d H:i:s') . "\n\n";

            foreach ($messages as $msg) {
                $sender = $msg['from_student_id'] == $user_id ? 'You' : $msg['from_first_name'] . ' ' . $msg['from_last_name'];
                $export_text .= "[{$msg['sent_at']}] {$sender}: {$msg['message']}\n";
            }

            echo json_encode(['success' => true, 'export' => $export_text]);
            break;

        // ==================== MODERATION ====================
        case 'report_message':
            $message_id = $_POST['message_id'] ?? null;
            $reason = trim($_POST['reason'] ?? '');

            if (!$message_id || !$reason) {
                throw new Exception('Message ID and reason required');
            }

            // Create reports table if not exists
            db()->query("
                CREATE TABLE IF NOT EXISTS message_reports (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    message_id INT NOT NULL,
                    reported_by INT NOT NULL,
                    reason TEXT NOT NULL,
                    reported_at DATETIME NOT NULL,
                    status VARCHAR(20) DEFAULT 'pending'
                )
            ");

            db()->insert('message_reports', [
                'message_id' => $message_id,
                'reported_by' => $user_id,
                'reason' => $reason,
                'reported_at' => date('Y-m-d H:i:s'),
                'status' => 'pending'
            ]);

            Logger::audit('Message reported', $user_id, ['message_id' => $message_id, 'reason' => $reason]);

            echo json_encode(['success' => true, 'message' => 'Report submitted']);
            break;

        case 'block_user':
            $blocked_user_id = $_POST['blocked_user_id'] ?? null;

            if (!$blocked_user_id) {
                throw new Exception('User ID required');
            }

            // Create blocked users table if not exists
            db()->query("
                CREATE TABLE IF NOT EXISTS blocked_users (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    user_id INT NOT NULL,
                    blocked_user_id INT NOT NULL,
                    blocked_at DATETIME NOT NULL,
                    UNIQUE KEY unique_block (user_id, blocked_user_id)
                )
            ");

            db()->query("
                INSERT INTO blocked_users (user_id, blocked_user_id, blocked_at)
                VALUES (?, ?, NOW())
                ON DUPLICATE KEY UPDATE blocked_at = NOW()
            ", [$user_id, $blocked_user_id]);

            echo json_encode(['success' => true, 'message' => 'User blocked']);
            break;

        case 'unblock_user':
            $blocked_user_id = $_POST['blocked_user_id'] ?? null;

            if (!$blocked_user_id) {
                throw new Exception('User ID required');
            }

            db()->delete('blocked_users', [
                'user_id' => $user_id,
                'blocked_user_id' => $blocked_user_id
            ]);

            echo json_encode(['success' => true, 'message' => 'User unblocked']);
            break;

        // ==================== VOICE NOTES ====================
        case 'upload_voice_note':
            $voice_file = $_FILES['voice_note'] ?? null;
            $to_user_id = $_POST['to_user_id'] ?? null;

            if (!$voice_file || !$to_user_id) {
                throw new Exception('Voice note and recipient required');
            }

            $allowed_audio = ['audio/mpeg', 'audio/wav', 'audio/ogg', 'audio/webm'];
            $max_size = 2 * 1024 * 1024; // 2MB

            if (!in_array($voice_file['type'], $allowed_audio)) {
                throw new Exception('Invalid audio format');
            }

            if ($voice_file['size'] > $max_size) {
                throw new Exception('Voice note too large (max 2MB)');
            }

            $upload_dir = '../uploads/voice/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            $file_path = $upload_dir . uniqid() . '.ogg';

            if (!move_uploaded_file($voice_file['tmp_name'], $file_path)) {
                throw new Exception('Upload failed');
            }

            // Create message with voice note
            $id = db()->insert('student_messages', [
                'from_student_id' => $user_id,
                'to_student_id' => $to_user_id,
                'message' => '[Voice Note]',
                'file_path' => $file_path,
                'file_type' => 'audio/ogg',
                'file_size' => $voice_file['size'],
                'sent_at' => date('Y-m-d H:i:s'),
                'is_read' => 0
            ]);

            echo json_encode(['success' => true, 'message_id' => $id]);
            break;

        // ==================== STATS ====================
        case 'get_messaging_stats':
            $stats = [
                'total_sent' => db()->fetchOne("SELECT COUNT(*) as c FROM student_messages WHERE from_student_id = ?", [$user_id])['c'],
                'total_received' => db()->fetchOne("SELECT COUNT(*) as c FROM student_messages WHERE to_student_id = ?", [$user_id])['c'],
                'unread' => db()->fetchOne("SELECT COUNT(*) as c FROM student_messages WHERE to_student_id = ? AND is_read = 0", [$user_id])['c'],
                'groups' => 0
            ];

            try {
                $stats['groups'] = db()->fetchOne("SELECT COUNT(*) as c FROM group_members WHERE user_id = ?", [$user_id])['c'];
            } catch (Exception $e) {
            }

            echo json_encode(['success' => true, 'stats' => $stats]);
            break;

        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    Logger::error('Messaging API error', ['action' => $action, 'user' => $user_id, 'error' => $e->getMessage()]);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
