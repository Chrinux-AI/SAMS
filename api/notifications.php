<?php

/**
 * Notifications API
 * Handles notification creation, retrieval, marking as read, and user preferences
 */

session_start();
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/logger.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$currentTenantId = current_tenant_id();
if (!isset($_SESSION['tenant_id'])) {
    set_user_tenant_session($user_id);
    $currentTenantId = current_tenant_id();
}
if (!user_in_current_tenant($user_id) || !$currentTenantId) {
    echo json_encode(['success' => false, 'message' => 'Tenant access denied']);
    exit;
}

$currentRole = strtolower((string) ($_SESSION['role'] ?? $_SESSION['user_role'] ?? 'student'));
$rawBody = file_get_contents('php://input');
$payload = json_decode($rawBody ?: '', true);
if (!is_array($payload)) {
    $payload = [];
}

function notifications_request_value(string $key, $default = null)
{
    global $payload;

    if (array_key_exists($key, $_POST)) {
        return $_POST[$key];
    }
    if (array_key_exists($key, $payload)) {
        return $payload[$key];
    }
    if (array_key_exists($key, $_GET)) {
        return $_GET[$key];
    }

    return $default;
}

$action = (string)notifications_request_value('action', '');

try {
    // Ensure notifications table exists
    db()->query("
        CREATE TABLE IF NOT EXISTS notifications (
            id INT PRIMARY KEY AUTO_INCREMENT,
            tenant_id INT NULL,
            user_id INT NOT NULL,
            title VARCHAR(255),
            message TEXT NOT NULL,
            icon VARCHAR(50) DEFAULT 'bell',
            category VARCHAR(50),
            link VARCHAR(255),
            created_at DATETIME NOT NULL,
            is_read TINYINT(1) DEFAULT 0,
            read_at DATETIME,
            INDEX idx_tenant_user_read (tenant_id, user_id, is_read),
            INDEX idx_user_read (user_id, is_read),
            INDEX idx_created (created_at)
        )
    ");

    $notificationTenantScoped = table_has_column('notifications', 'tenant_id');
    $settingsTenantScoped = table_has_column('notification_settings', 'tenant_id');
    $notificationTenantClause = $notificationTenantScoped ? ' AND tenant_id = ?' : '';
    $notificationTenantParams = $notificationTenantScoped ? [$currentTenantId] : [];

    switch ($action) {
        case 'get_all':
            $limit = (int)($_GET['limit'] ?? 50);
            $offset = (int)($_GET['offset'] ?? 0);
            $filter = $_GET['filter'] ?? 'all';
            $category = $_GET['category'] ?? 'all';

            $sql = "SELECT * FROM notifications WHERE user_id = ?{$notificationTenantClause}";
            $params = array_merge([$user_id], $notificationTenantParams);

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
                WHERE user_id = ? AND is_read = 0{$notificationTenantClause}
            ", array_merge([$user_id], $notificationTenantParams))['count'];

            echo json_encode(['success' => true, 'count' => (int)$count]);
            break;

        case 'mark_read':
            $notif_id = notifications_request_value('id');

            if (!$notif_id) {
                throw new Exception('Notification ID required');
            }

            if (table_has_column('notifications', 'tenant_id')) {
                db()->update(
                    'notifications',
                    ['is_read' => 1, 'read_at' => date('Y-m-d H:i:s')],
                    'id = ? AND user_id = ? AND tenant_id = ?',
                    [$notif_id, $user_id, $currentTenantId]
                );
            } else {
                db()->update(
                    'notifications',
                    ['is_read' => 1, 'read_at' => date('Y-m-d H:i:s')],
                    'id = ? AND user_id = ?',
                    [$notif_id, $user_id]
                );
            }

            echo json_encode(['success' => true, 'message' => 'Marked as read']);
            break;

        case 'mark_all_read':
            db()->query("
                UPDATE notifications
                SET is_read = 1, read_at = NOW()
                WHERE user_id = ? AND is_read = 0{$notificationTenantClause}
            ", array_merge([$user_id], $notificationTenantParams));

            echo json_encode(['success' => true, 'message' => 'All notifications marked as read']);
            break;

        case 'delete':
            $notif_id = notifications_request_value('id');

            if (!$notif_id) {
                throw new Exception('Notification ID required');
            }

            if (table_has_column('notifications', 'tenant_id')) {
                db()->delete('notifications', ['id' => $notif_id, 'user_id' => $user_id, 'tenant_id' => $currentTenantId]);
            } else {
                db()->delete('notifications', ['id' => $notif_id, 'user_id' => $user_id]);
            }

            echo json_encode(['success' => true, 'message' => 'Notification deleted']);
            break;

        case 'clear_all':
            db()->query("DELETE FROM notifications WHERE user_id = ?{$notificationTenantClause}", array_merge([$user_id], $notificationTenantParams));

            echo json_encode(['success' => true, 'message' => 'All notifications cleared']);
            break;

        case 'create':
            if (!notifications_can_manage($currentRole)) {
                throw new Exception('Unauthorized');
            }

            $target_user_id = (int)notifications_request_value('user_id', 0);
            $title = trim((string)notifications_request_value('title', ''));
            $message = trim((string)notifications_request_value('message', ''));
            $icon = (string)notifications_request_value('icon', 'bell');
            $category = (string)notifications_request_value('category', 'general');
            $link = notifications_request_value('link');

            if (!$target_user_id || !$message) {
                throw new Exception('User ID and message required');
            }

            if (!notifications_user_in_tenant($target_user_id, $currentTenantId)) {
                throw new Exception('Target user is not available in this tenant');
            }

            $id = insert_flexible('notifications', [
                'tenant_id' => $currentTenantId,
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
            if (!notifications_can_manage($currentRole)) {
                throw new Exception('Unauthorized');
            }

            $target_role = notifications_normalize_role((string)notifications_request_value('target_role', ''));
            $title = trim((string)notifications_request_value('title', ''));
            $message = trim((string)notifications_request_value('message', ''));
            $icon = (string)notifications_request_value('icon', 'bullhorn');
            $category = (string)notifications_request_value('category', 'announcement');

            if (!$target_role || !$message) {
                throw new Exception('Target role and message required');
            }

            $usersSql = "SELECT id FROM users WHERE LOWER(REPLACE(role, '-', '_')) = ? AND status = 'active'";
            $usersParams = [$target_role];
            if (table_has_column('users', 'tenant_id')) {
                $usersSql .= " AND tenant_id = ?";
                $usersParams[] = $currentTenantId;
            } elseif (table_has_column('users', 'school_id')) {
                $usersSql .= " AND school_id = ?";
                $usersParams[] = $currentTenantId;
            }
            $users = db()->fetchAll($usersSql, $usersParams);

            foreach ($users as $user) {
                insert_flexible('notifications', [
                    'tenant_id' => $currentTenantId,
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
            $settings = notifications_request_value('settings', []);

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
            $settingsWhere = 'user_id = ?';
            $settingsParams = [$user_id];
            if ($settingsTenantScoped) {
                $settingsWhere .= ' AND tenant_id = ?';
                $settingsParams[] = $currentTenantId;
            }
            $existing = db()->fetchOne("SELECT id FROM notification_settings WHERE {$settingsWhere}", $settingsParams);

            if ($existing) {
                update_flexible('notification_settings', $settings, $settingsWhere, $settingsParams);
            } else {
                $settings['user_id'] = $user_id;
                if ($settingsTenantScoped) {
                    $settings['tenant_id'] = $currentTenantId;
                }
                insert_flexible('notification_settings', $settings);
            }

            echo json_encode(['success' => true, 'message' => 'Settings saved']);
            break;

        case 'get_settings':
            $settingsSql = "SELECT * FROM notification_settings WHERE user_id = ?";
            $settingsParams = [$user_id];
            if ($settingsTenantScoped) {
                $settingsSql .= " AND tenant_id = ?";
                $settingsParams[] = $currentTenantId;
            }
            $settings = db()->fetchOne($settingsSql, $settingsParams);

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
            $statsParams = array_merge([$user_id], $notificationTenantParams);
            $stats = [
                'total' => db()->fetchOne("SELECT COUNT(*) as c FROM notifications WHERE user_id = ?{$notificationTenantClause}", $statsParams)['c'],
                'unread' => db()->fetchOne("SELECT COUNT(*) as c FROM notifications WHERE user_id = ? AND is_read = 0{$notificationTenantClause}", $statsParams)['c'],
                'today' => db()->fetchOne("SELECT COUNT(*) as c FROM notifications WHERE user_id = ? AND DATE(created_at) = CURDATE(){$notificationTenantClause}", $statsParams)['c'],
                'this_week' => db()->fetchOne("SELECT COUNT(*) as c FROM notifications WHERE user_id = ? AND YEARWEEK(created_at) = YEARWEEK(NOW()){$notificationTenantClause}", $statsParams)['c'],
                'by_category' => []
            ];

            $categories = db()->fetchAll("
                SELECT category, COUNT(*) as count
                FROM notifications
                WHERE user_id = ?{$notificationTenantClause}
                GROUP BY category
            ", $statsParams);

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
    $settingsSql = "SELECT push_enabled FROM notification_settings WHERE user_id = ?";
    $settingsParams = [$user_id];
    if (table_has_column('notification_settings', 'tenant_id')) {
        $settingsSql .= " AND tenant_id = ?";
        $settingsParams[] = current_tenant_id();
    }
    $settings = db()->fetchOne($settingsSql, $settingsParams);

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

function notifications_normalize_role(string $role): string
{
    return str_replace('-', '_', strtolower(trim($role)));
}

function notifications_can_manage(string $role): bool
{
    return in_array(
        notifications_normalize_role($role),
        ['admin', 'super_admin', 'superadmin', 'owner', 'principal', 'vice_principal', 'admin_officer'],
        true
    );
}

function notifications_user_in_tenant(int $targetUserId, int $tenantId): bool
{
    if ($targetUserId <= 0 || $tenantId <= 0) {
        return false;
    }

    if (table_exists('users')) {
        $userSql = "SELECT id FROM users WHERE id = ? AND status = 'active'";
        $userParams = [$targetUserId];

        if (table_has_column('users', 'tenant_id')) {
            $userSql .= " AND tenant_id = ?";
            $userParams[] = $tenantId;
        } elseif (table_has_column('users', 'school_id')) {
            $userSql .= " AND school_id = ?";
            $userParams[] = $tenantId;
        }

        $user = db()->fetchOne($userSql . " LIMIT 1", $userParams);
        if ($user) {
            return true;
        }
    }

    return false;
}
