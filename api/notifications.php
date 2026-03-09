<?php

/**
 * Notifications API
 * Handles notification creation, retrieval, marking as read, and user preferences
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
$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    // Ensure notifications table exists
    db()->query("
        CREATE TABLE IF NOT EXISTS notifications (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            title VARCHAR(255),
            message TEXT NOT NULL,
            icon VARCHAR(50) DEFAULT 'bell',
            category VARCHAR(50),
            link VARCHAR(255),
            created_at DATETIME NOT NULL,
            is_read TINYINT(1) DEFAULT 0,
            read_at DATETIME,
            INDEX idx_user_read (user_id, is_read),
            INDEX idx_created (created_at)
        )
    ");

    switch ($action) {
        case 'get_all':
            $limit = (int)($_GET['limit'] ?? 50);
            $offset = (int)($_GET['offset'] ?? 0);
            $filter = $_GET['filter'] ?? 'all';
            $category = $_GET['category'] ?? 'all';

            $sql = "SELECT * FROM notifications WHERE user_id = ?";
            $params = [$user_id];

            if ($filter === 'unread') {
                $sql .= " AND is_read = 0";
            }

            if ($category !== 'all') {
                $sql .= " AND category = ?";
                $params[] = $category;
            }

            $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;

            $notifications = db()->fetchAll($sql, $params);

            echo json_encode(['success' => true, 'notifications' => $notifications]);
            break;

        case 'get_unread_count':
            $count = db()->fetchOne("
                SELECT COUNT(*) as count
                FROM notifications
                WHERE user_id = ? AND is_read = 0
            ", [$user_id])['count'];

            echo json_encode(['success' => true, 'count' => (int)$count]);
            break;

        case 'mark_read':
            $notif_id = $_POST['id'] ?? null;

            if (!$notif_id) {
                throw new Exception('Notification ID required');
            }

            db()->update(
                'notifications',
                ['is_read' => 1, 'read_at' => date('Y-m-d H:i:s')],
                ['id' => $notif_id, 'user_id' => $user_id]
            );

            echo json_encode(['success' => true, 'message' => 'Marked as read']);
            break;

        case 'mark_all_read':
            db()->query("
                UPDATE notifications
                SET is_read = 1, read_at = NOW()
                WHERE user_id = ? AND is_read = 0
            ", [$user_id]);

            echo json_encode(['success' => true, 'message' => 'All notifications marked as read']);
            break;

        case 'delete':
            $notif_id = $_POST['id'] ?? null;

            if (!$notif_id) {
                throw new Exception('Notification ID required');
            }

            db()->delete('notifications', ['id' => $notif_id, 'user_id' => $user_id]);

            echo json_encode(['success' => true, 'message' => 'Notification deleted']);
            break;

        case 'clear_all':
            db()->query("DELETE FROM notifications WHERE user_id = ?", [$user_id]);

            echo json_encode(['success' => true, 'message' => 'All notifications cleared']);
            break;

        case 'create':
            // Only for system/admin use
            if ($_SESSION['role'] !== 'admin') {
                throw new Exception('Unauthorized');
            }

            $target_user_id = $_POST['user_id'] ?? null;
            $title = trim($_POST['title'] ?? '');
            $message = trim($_POST['message'] ?? '');
            $icon = $_POST['icon'] ?? 'bell';
            $category = $_POST['category'] ?? 'general';
            $link = $_POST['link'] ?? null;

            if (!$target_user_id || !$message) {
                throw new Exception('User ID and message required');
            }

            $id = db()->insert('notifications', [
                'user_id' => $target_user_id,
                'title' => $title,
                'message' => $message,
                'icon' => $icon,
                'category' => $category,
                'link' => $link,
                'created_at' => date('Y-m-d H:i:s'),
                'is_read' => 0
            ]);

            // Send push notification if enabled
            sendPushNotification($target_user_id, $title, $message);

            echo json_encode(['success' => true, 'notification_id' => $id]);
            break;

        case 'broadcast':
            // Admin only - broadcast to all users of a role
            if ($_SESSION['role'] !== 'admin') {
                throw new Exception('Unauthorized');
            }

            $target_role = $_POST['target_role'] ?? null;
            $title = trim($_POST['title'] ?? '');
            $message = trim($_POST['message'] ?? '');
            $icon = $_POST['icon'] ?? 'bullhorn';
            $category = $_POST['category'] ?? 'announcement';

            if (!$target_role || !$message) {
                throw new Exception('Target role and message required');
            }

            $users = db()->fetchAll("SELECT id FROM users WHERE role = ? AND status = 'active'", [$target_role]);

            foreach ($users as $user) {
                db()->insert('notifications', [
                    'user_id' => $user['id'],
                    'title' => $title,
                    'message' => $message,
                    'icon' => $icon,
                    'category' => $category,
                    'created_at' => date('Y-m-d H:i:s'),
                    'is_read' => 0
                ]);
            }

            echo json_encode(['success' => true, 'sent_to' => count($users)]);
            break;

        case 'save_settings':
            // Save user notification preferences
            $settings = $_POST['settings'] ?? [];

            // Create settings table if not exists
            db()->query("
                CREATE TABLE IF NOT EXISTS notification_settings (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    user_id INT NOT NULL UNIQUE,
                    push_enabled TINYINT(1) DEFAULT 1,
                    sound_enabled TINYINT(1) DEFAULT 1,
                    vibration_enabled TINYINT(1) DEFAULT 1,
                    email_urgent TINYINT(1) DEFAULT 1,
                    email_digest TINYINT(1) DEFAULT 0,
                    email_assignments TINYINT(1) DEFAULT 1,
                    cat_attendance TINYINT(1) DEFAULT 1,
                    cat_assignments TINYINT(1) DEFAULT 1,
                    cat_grades TINYINT(1) DEFAULT 1,
                    cat_messages TINYINT(1) DEFAULT 1,
                    cat_events TINYINT(1) DEFAULT 1,
                    updated_at DATETIME
                )
            ");

            // Convert boolean values
            foreach ($settings as $key => $value) {
                $settings[$key] = $value ? 1 : 0;
            }

            $settings['updated_at'] = date('Y-m-d H:i:s');

            // Insert or update
            $existing = db()->fetchOne("SELECT id FROM notification_settings WHERE user_id = ?", [$user_id]);

            if ($existing) {
                db()->update('notification_settings', $settings, ['user_id' => $user_id]);
            } else {
                $settings['user_id'] = $user_id;
                db()->insert('notification_settings', $settings);
            }

            echo json_encode(['success' => true, 'message' => 'Settings saved']);
            break;

        case 'get_settings':
            $settings = db()->fetchOne("SELECT * FROM notification_settings WHERE user_id = ?", [$user_id]);

            if (!$settings) {
                // Return defaults
                $settings = [
                    'push_enabled' => 1,
                    'sound_enabled' => 1,
                    'vibration_enabled' => 1,
                    'email_urgent' => 1,
                    'email_digest' => 0,
                    'email_assignments' => 1,
                    'cat_attendance' => 1,
                    'cat_assignments' => 1,
                    'cat_grades' => 1,
                    'cat_messages' => 1,
                    'cat_events' => 1
                ];
            }

            echo json_encode(['success' => true, 'settings' => $settings]);
            break;

        case 'get_stats':
            $stats = [
                'total' => db()->fetchOne("SELECT COUNT(*) as c FROM notifications WHERE user_id = ?", [$user_id])['c'],
                'unread' => db()->fetchOne("SELECT COUNT(*) as c FROM notifications WHERE user_id = ? AND is_read = 0", [$user_id])['c'],
                'today' => db()->fetchOne("SELECT COUNT(*) as c FROM notifications WHERE user_id = ? AND DATE(created_at) = CURDATE()", [$user_id])['c'],
                'this_week' => db()->fetchOne("SELECT COUNT(*) as c FROM notifications WHERE user_id = ? AND YEARWEEK(created_at) = YEARWEEK(NOW())", [$user_id])['c'],
                'by_category' => []
            ];

            $categories = db()->fetchAll("
                SELECT category, COUNT(*) as count
                FROM notifications
                WHERE user_id = ?
                GROUP BY category
            ", [$user_id]);

            foreach ($categories as $cat) {
                $stats['by_category'][$cat['category'] ?? 'general'] = (int)$cat['count'];
            }

            echo json_encode(['success' => true, 'stats' => $stats]);
            break;

        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    Logger::error('Notifications API error', ['action' => $action, 'user' => $user_id, 'error' => $e->getMessage()]);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

/**
 * Send push notification (placeholder for WebSocket/Firebase implementation)
 */
function sendPushNotification($user_id, $title, $message)
{
    // Check if user has push enabled
    $settings = db()->fetchOne("SELECT push_enabled FROM notification_settings WHERE user_id = ?", [$user_id]);

    if (!$settings || !$settings['push_enabled']) {
        return;
    }

    // TODO: Implement actual push notification service
    // This could be:
    // - WebSocket server for real-time updates
    // - Firebase Cloud Messaging for mobile apps
    // - Web Push API for browser notifications

    Logger::info('Push notification queued', ['user_id' => $user_id, 'title' => $title]);
}
