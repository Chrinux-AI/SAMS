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

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Authentication required', 'data' => null]);
    exit;
}

$currentUserId = (int)$_SESSION['user_id'];
if (!isset($_SESSION['tenant_id'])) {
    set_user_tenant_session($currentUserId);
}

if (!user_in_current_tenant($currentUserId)) {
    echo json_encode(['success' => false, 'message' => 'Tenant access denied', 'data' => null]);
    exit;
}

$currentTenantId = current_tenant_id();
$currentRole = strtolower((string)($_SESSION['role'] ?? $_SESSION['user_role'] ?? 'student'));

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$response = ['success' => false, 'message' => '', 'data' => null];

try {
    switch ($action) {
        case 'register_device':
            $user_id = $currentUserId;
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
                update_flexible('mobile_devices', mobile_tenant_payload('mobile_devices', $currentTenantId) + [
                    'device_name' => $device_name,
                    'app_version' => $app_version,
                    'os_version' => $os_version,
                    'is_active' => 1,
                    'last_sync' => date('Y-m-d H:i:s')
                ], 'id = ?', [$existing['id']]);

                $device_id = $existing['id'];
            } else {
                // Register new device
                $device_id = insert_flexible('mobile_devices', mobile_tenant_payload('mobile_devices', $currentTenantId) + [
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
            $user_id = $currentUserId;
            $device_id = $_POST['device_id'] ?? null;
            $sync_data = json_decode($_POST['sync_data'] ?? '[]', true);

            if (!$user_id || !$device_id) {
                throw new Exception('Missing required fields');
            }

            if (!is_array($sync_data)) {
                throw new Exception('sync_data must be valid JSON');
            }

            if (!mobile_device_owned_by_user((int)$device_id, $user_id, $currentTenantId)) {
                throw new Exception('Invalid device for current tenant');
            }

            $synced_count = 0;
            $failed_count = 0;

            foreach ($sync_data as $item) {
                try {
                    if (!is_array($item) || empty($item['action_type']) || !isset($item['data']) || !is_array($item['data'])) {
                        throw new Exception('Invalid sync item payload');
                    }

                    // Queue offline action for processing
                    insert_flexible('offline_sync_queue', mobile_tenant_payload('offline_sync_queue', $currentTenantId) + [
                        'user_id' => $user_id,
                        'device_id' => $device_id,
                        'action_type' => $item['action_type'],
                        'data' => json_encode($item['data']),
                        'sync_status' => 'pending',
                        'created_at' => date('Y-m-d H:i:s')
                    ]);

                    // Process action immediately
                    process_offline_action($item['action_type'], $item['data'], $user_id, $currentTenantId, $currentRole);
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
            $requested_user_id = (int)($_POST['user_id'] ?? $currentUserId);
            $user_id = $requested_user_id;
            $title = $_POST['title'] ?? '';
            $message = $_POST['message'] ?? '';
            $category = $_POST['category'] ?? 'general';
            $priority = $_POST['priority'] ?? 'normal';
            $payload = json_decode($_POST['payload'] ?? '{}', true);

            if (!$user_id || !$title || !$message) {
                throw new Exception('Missing required fields');
            }

            if ($requested_user_id !== $currentUserId && !in_array($currentRole, ['admin', 'principal'], true)) {
                throw new Exception('You are not allowed to send notifications to other users');
            }

            if (!mobile_user_in_tenant($requested_user_id, $currentTenantId)) {
                throw new Exception('Target user is not in the current tenant');
            }

            // Get user's active devices
            $devices = db()->fetchAll(
                "SELECT * FROM mobile_devices WHERE user_id = ? AND is_active = 1" . mobile_tenant_where('mobile_devices', $currentTenantId) . " ORDER BY id DESC",
                mobile_tenant_where_params([$user_id], 'mobile_devices', $currentTenantId)
            );

            $sent_count = 0;
            foreach ($devices as $device) {
                // Create notification record
                $notif_id = insert_flexible('push_notifications', mobile_tenant_payload('push_notifications', $currentTenantId) + [
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
            $zoneWhere = table_has_column('geofencing_zones', 'tenant_id') ? 'WHERE tenant_id = ? AND is_active = 1' : 'WHERE is_active = 1';
            $params = table_has_column('geofencing_zones', 'tenant_id') ? [$currentTenantId] : [];
            $zones = db()->fetchAll("SELECT * FROM geofencing_zones {$zoneWhere}", $params);

            $response = [
                'success' => true,
                'message' => 'Geofence zones retrieved',
                'data' => $zones
            ];
            break;

        case 'check_geofence':
            $latitude = floatval($_POST['latitude'] ?? 0);
            $longitude = floatval($_POST['longitude'] ?? 0);

            $zoneWhere = table_has_column('geofencing_zones', 'tenant_id') ? 'WHERE tenant_id = ? AND is_active = 1' : 'WHERE is_active = 1';
            $params = table_has_column('geofencing_zones', 'tenant_id') ? [$currentTenantId] : [];
            $zones = db()->fetchAll("SELECT * FROM geofencing_zones {$zoneWhere}", $params);
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
function process_offline_action($action_type, $data, $user_id, $tenant_id, $role)
{
    switch ($action_type) {
        case 'attendance_checkin':
            $attendanceTable = table_exists('attendance') ? 'attendance' : 'attendance_records';
            $dateValue = date('Y-m-d', strtotime($data['timestamp'] ?? 'now'));
            $checkInValue = date('Y-m-d H:i:s', strtotime($data['timestamp'] ?? 'now'));
            $studentId = (int)($data['student_id'] ?? 0);
            $classId = (int)($data['class_id'] ?? 0);
            $status = (string)($data['status'] ?? 'present');

            if ($studentId <= 0 || $classId <= 0) {
                throw new Exception('Attendance sync requires student_id and class_id');
            }

            if (!mobile_validate_attendance_target($studentId, $classId, $tenant_id, $user_id, $role)) {
                throw new Exception('Attendance target is not valid for current tenant');
            }

            $payload = [
                'student_id' => $studentId,
                'class_id' => $classId,
                'status' => $status,
                'created_at' => date('Y-m-d H:i:s'),
            ];

            if ($attendanceTable === 'attendance') {
                $payload['teacher_id'] = (int)$user_id;
                $payload['date'] = $dateValue;
                $payload = mobile_tenant_payload($attendanceTable, $tenant_id) + $payload;
                if (table_has_column($attendanceTable, 'check_in_time')) {
                    $payload['check_in_time'] = $checkInValue;
                }
                if (table_has_column($attendanceTable, 'notes')) {
                    $payload['notes'] = 'Offline sync';
                }
                if (table_has_column($attendanceTable, 'updated_by')) {
                    $payload['updated_by'] = $user_id;
                }
                $existing = db()->fetchOne(
                    "SELECT id FROM attendance WHERE student_id = ? AND class_id = ? AND date = ?" . mobile_tenant_where($attendanceTable, $tenant_id) . " LIMIT 1",
                    mobile_tenant_where_params([$payload['student_id'], $payload['class_id'], $dateValue], $attendanceTable, $tenant_id)
                );
                if ($existing) {
                    update_flexible('attendance', $payload, 'id = ?', [$existing['id']]);
                    return true;
                }
            } else {
                if (table_has_column($attendanceTable, 'attendance_date')) {
                    $payload['attendance_date'] = $dateValue;
                }
                $payload['marked_by'] = (int)$user_id;
                $payload = mobile_tenant_payload($attendanceTable, $tenant_id) + $payload;
                if (table_has_column($attendanceTable, 'check_in_time')) {
                    $payload['check_in_time'] = $checkInValue;
                }
                if (table_has_column($attendanceTable, 'updated_at')) {
                    $payload['updated_at'] = date('Y-m-d H:i:s');
                }
                if (table_has_column($attendanceTable, 'remarks')) {
                    $payload['remarks'] = 'Offline sync';
                }
                $existing = mobile_find_existing_attendance_record($attendanceTable, $payload['student_id'], $payload['class_id'], $dateValue, $tenant_id);
                if ($existing) {
                    update_flexible('attendance_records', $payload, 'id = ?', [$existing['id']]);
                    return true;
                }
            }

            insert_flexible($attendanceTable, $payload);
            break;
            // Add more action types as needed
    }

    return true;
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

function mobile_tenant_payload(string $tableName, int $tenantId): array
{
    $payload = [];
    foreach (['tenant_id', 'school_id'] as $column) {
        if (table_has_column($tableName, $column)) {
            $payload[$column] = $tenantId;
        }
    }

    return $payload;
}

function mobile_tenant_where(string $tableName, int $tenantId): string
{
    foreach (['tenant_id', 'school_id'] as $column) {
        if (table_has_column($tableName, $column)) {
            return " AND {$column} = ?";
        }
    }

    return '';
}

function mobile_tenant_where_params(array $params, string $tableName, int $tenantId): array
{
    foreach (['tenant_id', 'school_id'] as $column) {
        if (table_has_column($tableName, $column)) {
            $params[] = $tenantId;
            break;
        }
    }

    return $params;
}

function mobile_device_owned_by_user(int $deviceId, int $userId, int $tenantId): bool
{
    $device = db()->fetchOne(
        "SELECT id FROM mobile_devices WHERE id = ? AND user_id = ?" . mobile_tenant_where('mobile_devices', $tenantId) . " LIMIT 1",
        mobile_tenant_where_params([$deviceId, $userId], 'mobile_devices', $tenantId)
    );

    return (bool)$device;
}

function mobile_user_in_tenant(int $userId, int $tenantId): bool
{
    if ($userId <= 0) {
        return false;
    }

    if (table_exists('tenant_users')) {
        $membership = db()->fetchOne(
            "SELECT id FROM tenant_users WHERE user_id = ? AND tenant_id = ? AND is_active = 1 LIMIT 1",
            [$userId, $tenantId]
        );
        if ($membership) {
            return true;
        }
    }

    $user = db()->fetchOne(
        "SELECT tenant_id, school_id FROM users WHERE id = ? LIMIT 1",
        [$userId]
    ) ?: [];

    return in_array($tenantId, array_filter([(int)($user['tenant_id'] ?? 0), (int)($user['school_id'] ?? 0)]), true);
}

function mobile_validate_attendance_target(int $studentId, int $classId, int $tenantId, int $userId, string $role): bool
{
    $student = db()->fetchOne(
        "SELECT id FROM students WHERE id = ?" . mobile_tenant_where('students', $tenantId) . " LIMIT 1",
        mobile_tenant_where_params([$studentId], 'students', $tenantId)
    );
    if (!$student) {
        return false;
    }

    $class = db()->fetchOne(
        "SELECT * FROM classes WHERE id = ?" . mobile_tenant_where('classes', $tenantId) . " LIMIT 1",
        mobile_tenant_where_params([$classId], 'classes', $tenantId)
    );
    if (!$class) {
        return false;
    }

    if (table_exists('class_enrollments')) {
        $enrollment = db()->fetchOne(
            "SELECT id FROM class_enrollments WHERE student_id = ? AND class_id = ?" .
            (table_has_column('class_enrollments', 'status') ? " AND status = 'active'" : '') .
            mobile_tenant_where('class_enrollments', $tenantId) . " LIMIT 1",
            mobile_tenant_where_params([$studentId, $classId], 'class_enrollments', $tenantId)
        );
        if (!$enrollment) {
            return false;
        }
    }

    if (in_array($role, ['admin', 'owner', 'principal', 'vice_principal', 'admin_officer'], true)) {
        return true;
    }

    if ($role !== 'teacher') {
        return false;
    }

    $teacherProfile = table_exists('teachers')
        ? db()->fetchOne(
            "SELECT id FROM teachers WHERE user_id = ?" . mobile_tenant_where('teachers', $tenantId) . " LIMIT 1",
            mobile_tenant_where_params([$userId], 'teachers', $tenantId)
        )
        : null;
    $teacherProfileId = (int)($teacherProfile['id'] ?? 0);

    foreach (['teacher_id', 'class_teacher_id', 'teacher_user_id'] as $column) {
        if (isset($class[$column]) && (int)$class[$column] > 0) {
            if ((int)$class[$column] === $userId || ($teacherProfileId > 0 && (int)$class[$column] === $teacherProfileId)) {
                return true;
            }
        }
    }

    return $teacherProfileId === 0;
}

function mobile_find_existing_attendance_record(string $tableName, int $studentId, int $classId, string $dateValue, int $tenantId): ?array
{
    $params = [$studentId, $classId];
    if (table_has_column($tableName, 'attendance_date')) {
        $sql = "SELECT id FROM {$tableName} WHERE student_id = ? AND class_id = ? AND attendance_date = ?";
        $params[] = $dateValue;
    } elseif (table_has_column($tableName, 'check_in_time')) {
        $sql = "SELECT id FROM {$tableName} WHERE student_id = ? AND class_id = ? AND DATE(check_in_time) = ?";
        $params[] = $dateValue;
    } else {
        $sql = "SELECT id FROM {$tableName} WHERE student_id = ? AND class_id = ?";
    }

    $sql .= mobile_tenant_where($tableName, $tenantId) . " LIMIT 1";
    $row = db()->fetchOne($sql, mobile_tenant_where_params($params, $tableName, $tenantId));

    return $row ?: null;
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
