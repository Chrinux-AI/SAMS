<?php

/**
 * PWA Push Notification API
 * Handles Web Push subscriptions and notifications
 */

require_once '../config/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

// Enable CORS for PWA
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

session_start();

// Get request data
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? $_GET['action'] ?? '';

// Database connection
$database = new Database();
$db = $database->getConnection();

// Response helper
function sendResponse($success, $message, $data = null)
{
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => time()
    ]);
    exit;
}

// Actions
switch ($action) {
    case 'subscribe':
        subscribeToPush($db, $input);
        break;

    case 'unsubscribe':
        unsubscribeFromPush($db, $input);
        break;

    case 'send':
        sendPushNotification($db, $input);
        break;

    case 'send_bulk':
        sendBulkNotifications($db, $input);
        break;

    case 'get_subscriptions':
        getUserSubscriptions($db);
        break;

    case 'update_preferences':
        updateNotificationPreferences($db, $input);
        break;

    case 'get_preferences':
        getNotificationPreferences($db);
        break;

    default:
        sendResponse(false, 'Invalid action');
}

/**
 * Subscribe to push notifications
 */
function subscribeToPush($db, $data)
{
    if (!isset($_SESSION['user_id'])) {
        sendResponse(false, 'Authentication required');
    }

    $userId = $_SESSION['user_id'];
    $subscription = $data['subscription'] ?? null;

    if (!$subscription) {
        sendResponse(false, 'Subscription data required');
    }

    // Extract subscription details
    $endpoint = $subscription['endpoint'] ?? '';
    $p256dh = $subscription['keys']['p256dh'] ?? '';
    $auth = $subscription['keys']['auth'] ?? '';

    if (empty($endpoint) || empty($p256dh) || empty($auth)) {
        sendResponse(false, 'Invalid subscription format');
    }

    try {
        // Check if subscription already exists
        $query = "SELECT id FROM push_subscriptions
                  WHERE user_id = :user_id AND endpoint = :endpoint";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->bindParam(':endpoint', $endpoint);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            // Update existing subscription
            $query = "UPDATE push_subscriptions
                      SET p256dh = :p256dh, auth = :auth, updated_at = NOW()
                      WHERE user_id = :user_id AND endpoint = :endpoint";
        } else {
            // Insert new subscription
            $query = "INSERT INTO push_subscriptions
                      (user_id, endpoint, p256dh, auth, created_at)
                      VALUES (:user_id, :endpoint, :p256dh, :auth, NOW())";
        }

        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->bindParam(':endpoint', $endpoint);
        $stmt->bindParam(':p256dh', $p256dh);
        $stmt->bindParam(':auth', $auth);
        $stmt->execute();

        sendResponse(true, 'Push subscription saved successfully');
    } catch (PDOException $e) {
        error_log("Push subscription error: " . $e->getMessage());
        sendResponse(false, 'Failed to save subscription');
    }
}

/**
 * Unsubscribe from push notifications
 */
function unsubscribeFromPush($db, $data)
{
    if (!isset($_SESSION['user_id'])) {
        sendResponse(false, 'Authentication required');
    }

    $userId = $_SESSION['user_id'];
    $endpoint = $data['endpoint'] ?? '';

    if (empty($endpoint)) {
        sendResponse(false, 'Endpoint required');
    }

    try {
        $query = "DELETE FROM push_subscriptions
                  WHERE user_id = :user_id AND endpoint = :endpoint";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->bindParam(':endpoint', $endpoint);
        $stmt->execute();

        sendResponse(true, 'Unsubscribed successfully');
    } catch (PDOException $e) {
        error_log("Unsubscribe error: " . $e->getMessage());
        sendResponse(false, 'Failed to unsubscribe');
    }
}

/**
 * Send push notification to user(s)
 */
function sendPushNotification($db, $data)
{
    // Admin or system only
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        sendResponse(false, 'Unauthorized');
    }

    $targetUserId = $data['user_id'] ?? null;
    $title = $data['title'] ?? 'SAMS Notification';
    $message = $data['message'] ?? '';
    $type = $data['type'] ?? 'general';
    $url = $data['url'] ?? '/attendance/';

    if (empty($message)) {
        sendResponse(false, 'Message required');
    }

    try {
        // Get user subscriptions
        $query = "SELECT * FROM push_subscriptions WHERE user_id = :user_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $targetUserId);
        $stmt->execute();
        $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $sentCount = 0;
        foreach ($subscriptions as $sub) {
            $result = sendWebPush(
                $sub['endpoint'],
                $sub['p256dh'],
                $sub['auth'],
                $title,
                $message,
                $type,
                $url
            );

            if ($result) {
                $sentCount++;
            }
        }

        sendResponse(true, "Sent to $sentCount devices", ['count' => $sentCount]);
    } catch (PDOException $e) {
        error_log("Send push error: " . $e->getMessage());
        sendResponse(false, 'Failed to send notification');
    }
}

/**
 * Send bulk notifications to multiple users
 */
function sendBulkNotifications($db, $data)
{
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        sendResponse(false, 'Unauthorized');
    }

    $userIds = $data['user_ids'] ?? [];
    $role = $data['role'] ?? null;
    $title = $data['title'] ?? 'SAMS Notification';
    $message = $data['message'] ?? '';
    $type = $data['type'] ?? 'general';
    $url = $data['url'] ?? '/attendance/';

    if (empty($message)) {
        sendResponse(false, 'Message required');
    }

    try {
        // Build query based on filters
        if (!empty($userIds)) {
            $placeholders = implode(',', array_fill(0, count($userIds), '?'));
            $query = "SELECT * FROM push_subscriptions
                      WHERE user_id IN ($placeholders)";
            $stmt = $db->prepare($query);
            $stmt->execute($userIds);
        } elseif ($role) {
            $query = "SELECT ps.* FROM push_subscriptions ps
                      JOIN users u ON ps.user_id = u.id
                      WHERE u.role = :role";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':role', $role);
            $stmt->execute();
        } else {
            $query = "SELECT * FROM push_subscriptions";
            $stmt = $db->prepare($query);
            $stmt->execute();
        }

        $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $sentCount = 0;

        foreach ($subscriptions as $sub) {
            $result = sendWebPush(
                $sub['endpoint'],
                $sub['p256dh'],
                $sub['auth'],
                $title,
                $message,
                $type,
                $url
            );

            if ($result) {
                $sentCount++;
            }
        }

        sendResponse(true, "Sent to $sentCount devices", ['count' => $sentCount]);
    } catch (PDOException $e) {
        error_log("Bulk send error: " . $e->getMessage());
        sendResponse(false, 'Failed to send notifications');
    }
}

/**
 * Get user's push subscriptions
 */
function getUserSubscriptions($db)
{
    if (!isset($_SESSION['user_id'])) {
        sendResponse(false, 'Authentication required');
    }

    $userId = $_SESSION['user_id'];

    try {
        $query = "SELECT id, endpoint, created_at, updated_at
                  FROM push_subscriptions
                  WHERE user_id = :user_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();
        $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        sendResponse(true, 'Subscriptions retrieved', $subscriptions);
    } catch (PDOException $e) {
        error_log("Get subscriptions error: " . $e->getMessage());
        sendResponse(false, 'Failed to retrieve subscriptions');
    }
}

/**
 * Update notification preferences
 */
function updateNotificationPreferences($db, $data)
{
    if (!isset($_SESSION['user_id'])) {
        sendResponse(false, 'Authentication required');
    }

    $userId = $_SESSION['user_id'];
    $preferences = $data['preferences'] ?? [];

    try {
        // Check if preferences exist
        $query = "SELECT id FROM notification_preferences WHERE user_id = :user_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();

        $prefsJson = json_encode($preferences);

        if ($stmt->rowCount() > 0) {
            $query = "UPDATE notification_preferences
                      SET preferences = :preferences, updated_at = NOW()
                      WHERE user_id = :user_id";
        } else {
            $query = "INSERT INTO notification_preferences
                      (user_id, preferences, created_at)
                      VALUES (:user_id, :preferences, NOW())";
        }

        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->bindParam(':preferences', $prefsJson);
        $stmt->execute();

        sendResponse(true, 'Preferences updated successfully');
    } catch (PDOException $e) {
        error_log("Update preferences error: " . $e->getMessage());
        sendResponse(false, 'Failed to update preferences');
    }
}

/**
 * Get notification preferences
 */
function getNotificationPreferences($db)
{
    if (!isset($_SESSION['user_id'])) {
        sendResponse(false, 'Authentication required');
    }

    $userId = $_SESSION['user_id'];

    try {
        $query = "SELECT preferences FROM notification_preferences
                  WHERE user_id = :user_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $preferences = json_decode($row['preferences'], true);
        } else {
            // Default preferences
            $preferences = [
                'attendance' => true,
                'messages' => true,
                'assignments' => true,
                'announcements' => true,
                'grades' => true,
                'events' => true
            ];
        }

        sendResponse(true, 'Preferences retrieved', $preferences);
    } catch (PDOException $e) {
        error_log("Get preferences error: " . $e->getMessage());
        sendResponse(false, 'Failed to retrieve preferences');
    }
}

/**
 * Send Web Push notification (using web-push library)
 */
function sendWebPush($endpoint, $p256dh, $auth, $title, $message, $type, $url)
{
    // This requires the web-push PHP library
    // Install via: composer require minishlink/web-push

    try {
        require_once '../vendor/autoload.php';

        $payload = json_encode([
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'url' => $url,
            'options' => [
                'icon' => '/attendance/assets/images/icons/icon-192x192.png',
                'badge' => '/attendance/assets/images/icons/badge-72x72.png',
                'vibrate' => [200, 100, 200]
            ]
        ]);

        $subscription = [
            'endpoint' => $endpoint,
            'keys' => [
                'p256dh' => $p256dh,
                'auth' => $auth
            ]
        ];

        // VAPID keys (replace with your actual keys)
        $auth_keys = [
            'VAPID' => [
                'subject' => 'mailto:admin@sams.edu',
                'publicKey' => 'BEl62iUYgUivxIkv69yViEuiBIa-Ib37L8hxEvswJPg98BPWA2BU7qfhS_O3qUPPSJ7vBYpJmS7P2Fo9G3XMJoE',
                'privateKey' => 'UUxI4O8-FbRouAevSmBQ6o18hgE4nSG3qwvJTfKc-ls'
            ]
        ];

        $webPush = new Minishlink\WebPush\WebPush($auth_keys);
        $result = $webPush->sendOneNotification(
            Minishlink\WebPush\Subscription::create($subscription),
            $payload
        );

        return $result->isSuccess();
    } catch (Exception $e) {
        error_log("Web push send error: " . $e->getMessage());
        return false;
    }
}
