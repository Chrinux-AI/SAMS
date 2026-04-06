<?php

/**
 * Mobile App API
 * Handles device registration, offline sync, and push notifications
 */

session_start();
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$response = ['success' => false, 'message' => '', 'data' => null];

try {
    switch ($action) {
        case 'register_device':
            $user_id = $_SESSION['user_id'] ?? $_POST['user_id'] ?? null;
            $device_type = $_POST['device_type'] ?? '';
            $device_token = $_POST['device_token'] ?? '';
            $device_name = $_POST['device_name'] ?? '';
            $app_version = $_POST['app_version'] ?? '';
            $os_version = $_POST['os_version'] ?? '';

            if (!$user_id || !$device_type || !$device_token) {
                throw new Exception('Missing required fields');
            }

            // Check if device already registered
            $existing = db()->fetchOne(
                "SELECT id FROM mobile_devices WHERE user_id = ? AND device_token = ?",
                [$user_id, $device_token]
            );

            if ($existing) {
                // Update existing device
                db()->update('mobile_devices', [
                    'device_name' => $device_name,
                    'app_version' => $app_version,
                    'os_version' => $os_version,
                    'is_active' => 1,
                    'last_sync' => date('Y-m-d H:i:s')
                ], 'id = ?', [$existing['id']]);

                $device_id = $existing['id'];
            } else {
                // Register new device
                $device_id = db()->insert('mobile_devices', [
                    'user_id' => $user_id,
                    'device_type' => $device_type,
                    'device_token' => $device_token,
                    'device_name' => $device_name,
                    'app_version' => $app_version,
                    'os_version' => $os_version,
                    'last_sync' => date('Y-m-d H:i:s')
                ]);
            }

            $response = [
                'success' => true,
                'message' => 'Device registered successfully',
                'data' => ['device_id' => $device_id]
            ];
            break;

        case 'sync_offline_data':
            $user_id = $_SESSION['user_id'] ?? $_POST['user_id'] ?? null;
            $device_id = $_POST['device_id'] ?? null;
            $sync_data = json_decode($_POST['sync_data'] ?? '[]', true);

            if (!$user_id || !$device_id) {
                throw new Exception('Missing required fields');
            }

            $synced_count = 0;
            $failed_count = 0;

            foreach ($sync_data as $item) {
                try {
                    // Queue offline action for processing
                    db()->insert('offline_sync_queue', [
                        'user_id' => $user_id,
                        'device_id' => $device_id,
                        'action_type' => $item['action_type'],
                        'data' => json_encode($item['data']),
                        'sync_status' => 'pending'
                    ]);

                    // Process action immediately
                    process_offline_action($item['action_type'], $item['data'], $user_id);
                    $synced_count++;
                } catch (Exception $e) {
                    $failed_count++;
                    error_log("Sync failed: " . $e->getMessage());
                }
            }

            $response = [
                'success' => true,
                'message' => "Synced $synced_count items, $failed_count failed",
                'data' => [
                    'synced' => $synced_count,
                    'failed' => $failed_count
                ]
            ];
            break;

        case 'send_push_notification':
            $user_id = $_POST['user_id'] ?? null;
            $title = $_POST['title'] ?? '';
            $message = $_POST['message'] ?? '';
            $category = $_POST['category'] ?? 'general';
            $priority = $_POST['priority'] ?? 'normal';
            $payload = json_decode($_POST['payload'] ?? '{}', true);

            if (!$user_id || !$title || !$message) {
                throw new Exception('Missing required fields');
            }

            // Get user's active devices
            $devices = db()->fetchAll(
                "SELECT * FROM mobile_devices WHERE user_id = ? AND is_active = 1",
                [$user_id]
            );

            $sent_count = 0;
            foreach ($devices as $device) {
                // Create notification record
                $notif_id = db()->insert('push_notifications', [
                    'user_id' => $user_id,
                    'device_id' => $device['id'],
                    'title' => $title,
                    'message' => $message,
                    'category' => $category,
                    'priority' => $priority,
                    'payload' => json_encode($payload),
                    'sent_at' => date('Y-m-d H:i:s')
                ]);

                // Send via FCM/APNS (placeholder - implement with actual service)
                $sent = send_push_to_device($device, $title, $message, $payload);
                if ($sent) $sent_count++;
            }

            $response = [
                'success' => true,
                'message' => "Sent to $sent_count devices",
                'data' => ['sent_count' => $sent_count]
            ];
            break;

        case 'get_geofence_zones':
            $zones = db()->fetchAll("SELECT * FROM geofencing_zones WHERE is_active = 1");

            $response = [
                'success' => true,
                'message' => 'Geofence zones retrieved',
                'data' => $zones
            ];
            break;

        case 'check_geofence':
            $latitude = floatval($_POST['latitude'] ?? 0);
            $longitude = floatval($_POST['longitude'] ?? 0);

            $zones = db()->fetchAll("SELECT * FROM geofencing_zones WHERE is_active = 1");
            $inside_zone = null;

            foreach ($zones as $zone) {
                $distance = calculate_distance(
                    $latitude,
                    $longitude,
                    $zone['latitude'],
                    $zone['longitude']
                );

                if ($distance <= $zone['radius']) {
                    $inside_zone = $zone;
                    break;
                }
            }

            $response = [
                'success' => true,
                'message' => $inside_zone ? 'Inside zone' : 'Outside all zones',
                'data' => [
                    'inside_zone' => $inside_zone !== null,
                    'zone' => $inside_zone
                ]
            ];
            break;

        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    $response = [
        'success' => false,
        'message' => $e->getMessage()
    ];
}

echo json_encode($response);

// Helper functions
function process_offline_action($action_type, $data, $user_id)
{
    switch ($action_type) {
        case 'attendance_checkin':
            // Process attendance check-in
            db()->insert('attendance_records', [
                'student_id' => $data['student_id'],
                'class_id' => $data['class_id'],
                'check_in_time' => $data['timestamp'],
                'status' => 'present',
                'remarks' => 'Offline sync'
            ]);
            break;
            // Add more action types as needed
    }
}

function send_push_to_device($device, $title, $message, $payload)
{
    // Placeholder - implement with FCM for Android or APNS for iOS
    // Example for FCM:
    /*
    $fcm_api_key = 'YOUR_FCM_SERVER_KEY';
    $data = [
        'to' => $device['device_token'],
        'notification' => [
            'title' => $title,
            'body' => $message
        ],
        'data' => $payload
    ];

    $ch = curl_init('https://fcm.googleapis.com/fcm/send');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: key=' . $fcm_api_key,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $result = curl_exec($ch);
    curl_close($ch);

    return $result !== false;
    */
    return true; // Placeholder return
}

function calculate_distance($lat1, $lon1, $lat2, $lon2)
{
    $earth_radius = 6371000; // meters
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);

    $a = sin($dLat / 2) * sin($dLat / 2) +
        cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
        sin($dLon / 2) * sin($dLon / 2);

    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return $earth_radius * $c;
}
