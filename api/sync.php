<?php

/**
 * PWA Offline Sync API
 * Handles synchronization of offline data
 */

require_once '../config/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

// Enable CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

session_start();

if (!isset($_SESSION['user_id'])) {
    sendResponse(false, 'Authentication required');
}

$currentUserId = (int)$_SESSION['user_id'];
if (!isset($_SESSION['tenant_id'])) {
    set_user_tenant_session($currentUserId);
}

if (!user_in_current_tenant($currentUserId)) {
    sendResponse(false, 'Tenant access denied');
}

$currentTenantId = current_tenant_id();
$currentRole = strtolower((string)($_SESSION['role'] ?? $_SESSION['user_role'] ?? 'student'));

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
    case 'check_updates':
        checkUpdates($db);
        break;

    case 'sync_attendance':
        syncAttendance($db, $input);
        break;

    case 'sync_messages':
        syncMessages($db, $input);
        break;

    case 'sync_submissions':
        syncSubmissions($db, $input);
        break;

    case 'get_cached_data':
        getCachedData($db, $input);
        break;

    case 'get_sync_status':
        getSyncStatus($db);
        break;

    default:
        sendResponse(false, 'Invalid action');
}

/**
 * Check for updates
 */
function checkUpdates($db)
{
    $userId = (int)$_SESSION['user_id'];
    $tenantId = current_tenant_id();
    $lastSync = $_GET['last_sync'] ?? 0;

    try {
        $updates = [];

        // Check new messages
        $messageTenantClause = table_has_column('messages', 'tenant_id') ? ' AND tenant_id = :tenant_id' : '';
        $query = "SELECT COUNT(*) as count FROM messages
                  WHERE receiver_id = :user_id
                  AND created_at > FROM_UNIXTIME(:last_sync)
                  AND is_read = 0{$messageTenantClause}";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->bindParam(':last_sync', $lastSync);
        if (table_has_column('messages', 'tenant_id')) {
            $stmt->bindParam(':tenant_id', $tenantId);
        }
        $stmt->execute();
        $updates['new_messages'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        // Check new announcements
        $announcementTenantClause = table_has_column('announcements', 'tenant_id') ? ' AND tenant_id = :tenant_id' : '';
        $query = "SELECT COUNT(*) as count FROM announcements
                  WHERE created_at > FROM_UNIXTIME(:last_sync)
                  AND status = 'active'{$announcementTenantClause}";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':last_sync', $lastSync);
        if (table_has_column('announcements', 'tenant_id')) {
            $stmt->bindParam(':tenant_id', $tenantId);
        }
        $stmt->execute();
        $updates['new_announcements'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        // Check assignment updates
        $assignmentTenantClause = table_has_column('assignments', 'tenant_id') ? ' AND a.tenant_id = :tenant_id' : '';
        $query = "SELECT COUNT(*) as count FROM assignments a
                  JOIN student_classes sc ON a.class_id = sc.class_id
                  WHERE sc.student_id = :user_id
                  AND a.updated_at > FROM_UNIXTIME(:last_sync){$assignmentTenantClause}";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->bindParam(':last_sync', $lastSync);
        if (table_has_column('assignments', 'tenant_id')) {
            $stmt->bindParam(':tenant_id', $tenantId);
        }
        $stmt->execute();
        $updates['updated_assignments'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        // Check attendance updates
        $attendanceTable = sync_attendance_table();
        $attendanceTenantClause = table_has_column($attendanceTable, 'tenant_id') ? ' AND tenant_id = :tenant_id' : '';
        $attendanceUpdatedColumn = table_has_column($attendanceTable, 'updated_at') ? 'updated_at' : 'created_at';
        $query = "SELECT COUNT(*) as count FROM {$attendanceTable}
                  WHERE student_id = :user_id
                  AND {$attendanceUpdatedColumn} > FROM_UNIXTIME(:last_sync){$attendanceTenantClause}";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->bindParam(':last_sync', $lastSync);
        if (table_has_column($attendanceTable, 'tenant_id')) {
            $stmt->bindParam(':tenant_id', $tenantId);
        }
        $stmt->execute();
        $updates['attendance_changes'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        sendResponse(true, 'Updates checked', $updates);
    } catch (PDOException $e) {
        error_log("Check updates error: " . $e->getMessage());
        sendResponse(false, 'Failed to check updates');
    }
}

/**
 * Sync attendance data
 */
function syncAttendance($db, $data)
{
    $userId = (int)$_SESSION['user_id'];
    $tenantId = current_tenant_id();
    $role = strtolower((string)($_SESSION['role'] ?? $_SESSION['user_role'] ?? 'student'));

    if (!in_array($role, ['admin', 'teacher', 'principal'], true)) {
        sendResponse(false, 'Attendance sync is restricted to staff roles');
    }

    $records = $data['records'] ?? $data['data']['records'] ?? [];
    if (empty($records) && isset($data['student_id'], $data['class_id'])) {
        $records = [$data];
    }

    if (empty($records)) {
        sendResponse(false, 'No records to sync');
    }

    $synced = 0;
    $failed = [];

    try {
        $db->beginTransaction();

        foreach ($records as $record) {
            try {
                $payload = sync_normalize_attendance_record($record, $tenantId, $userId);
                sync_assert_attendance_access($payload['lookup']['student_id'], $payload['lookup']['class_id'], $tenantId, $userId, $role);
                $table = $payload['table'];
                $existing = sync_find_existing_attendance($db, $table, $payload['lookup']);

                if ($existing) {
                    update_flexible($table, $payload['data'], 'id = ?', [$existing['id']]);
                } else {
                    insert_flexible($table, $payload['data']);
                }

                $synced++;
            } catch (Throwable $e) {
                $failed[] = [
                    'record' => $record,
                    'error' => $e->getMessage()
                ];
            }
        }

        $db->commit();

        sendResponse(true, "Synced $synced records", [
            'synced' => $synced,
            'failed' => count($failed),
            'errors' => $failed
        ]);
    } catch (PDOException $e) {
        $db->rollBack();
        error_log("Sync attendance error: " . $e->getMessage());
        sendResponse(false, 'Failed to sync attendance');
    }
}

/**
 * Sync messages
 */
function syncMessages($db, $data)
{
    $userId = (int)$_SESSION['user_id'];
    $tenantId = current_tenant_id();
    $messages = $data['messages'] ?? $data['data']['messages'] ?? [];

    if (empty($messages)) {
        sendResponse(false, 'No messages to sync');
    }

    $synced = 0;
    $failed = [];

    try {
        $db->beginTransaction();

        foreach ($messages as $msg) {
            try {
                $receiverId = (int)($msg['receiver_id'] ?? 0);
                if ($receiverId <= 0 || $receiverId === $userId) {
                    throw new Exception('Invalid receiver_id');
                }
                if (!sync_user_in_tenant($receiverId, $tenantId)) {
                    throw new Exception('Receiver is not in the current tenant');
                }

                $payload = [
                    'sender_id' => $userId,
                    'receiver_id' => $receiverId,
                    'subject' => (string)($msg['subject'] ?? ''),
                    'message' => (string)($msg['message'] ?? ''),
                    'created_at' => date('Y-m-d H:i:s'),
                ];

                $payload += sync_tenant_payload('messages', $tenantId);
                if (table_has_column('messages', 'sender_tenant_id')) {
                    $payload['sender_tenant_id'] = $tenantId;
                }
                if (table_has_column('messages', 'receiver_tenant_id')) {
                    $payload['receiver_tenant_id'] = $tenantId;
                }

                insert_flexible('messages', $payload);

                $synced++;
            } catch (Throwable $e) {
                $failed[] = [
                    'message' => $msg,
                    'error' => $e->getMessage()
                ];
            }
        }

        $db->commit();

        sendResponse(true, "Synced $synced messages", [
            'synced' => $synced,
            'failed' => count($failed),
            'errors' => $failed
        ]);
    } catch (PDOException $e) {
        $db->rollBack();
        error_log("Sync messages error: " . $e->getMessage());
        sendResponse(false, 'Failed to sync messages');
    }
}

/**
 * Sync assignment submissions
 */
function syncSubmissions($db, $data)
{
    $userId = (int)$_SESSION['user_id'];
    $tenantId = current_tenant_id();
    $submissions = $data['submissions'] ?? $data['data']['submissions'] ?? [];

    if (empty($submissions)) {
        sendResponse(false, 'No submissions to sync');
    }

    $synced = 0;
    $failed = [];

    try {
        $db->beginTransaction();

        foreach ($submissions as $submission) {
            try {
                $assignmentId = (int)($submission['assignment_id'] ?? 0);
                if ($assignmentId <= 0 || !sync_assignment_in_tenant($assignmentId, $tenantId)) {
                    throw new Exception('Assignment is not available in the current tenant');
                }

                // Handle file uploads if present
                $filePath = null;
                if (!empty($submission['file_data'])) {
                    $filePath = saveBase64File(
                        $submission['file_data'],
                        $submission['file_name'],
                        $userId
                    );
                }

                $payload = [
                    'assignment_id' => $assignmentId,
                    'student_id' => $userId,
                    'submission_text' => (string)($submission['text'] ?? $submission['submission_text'] ?? ''),
                    'file_path' => $filePath,
                    'submitted_at' => date('Y-m-d H:i:s'),
                ];
                $payload += sync_tenant_payload('assignment_submissions', $tenantId);

                insert_flexible('assignment_submissions', $payload);

                $synced++;
            } catch (Throwable $e) {
                $failed[] = [
                    'submission' => $submission,
                    'error' => $e->getMessage()
                ];
            }
        }

        $db->commit();

        sendResponse(true, "Synced $synced submissions", [
            'synced' => $synced,
            'failed' => count($failed),
            'errors' => $failed
        ]);
    } catch (PDOException $e) {
        $db->rollBack();
        error_log("Sync submissions error: " . $e->getMessage());
        sendResponse(false, 'Failed to sync submissions');
    }
}

/**
 * Get cached data for offline use
 */
function getCachedData($db, $data)
{
    $userId = (int)$_SESSION['user_id'];
    $tenantId = current_tenant_id();
    $role = strtolower((string)($_SESSION['role'] ?? $_SESSION['user_role'] ?? 'student'));
    $dataTypes = $data['types'] ?? $data['data']['types'] ?? ['all'];

    try {
        $cachedData = [];

        // User profile
        if (in_array('profile', $dataTypes) || in_array('all', $dataTypes)) {
            $profileTenantClause = table_has_column('users', 'tenant_id') ? ' AND tenant_id = :tenant_id' : '';
            $query = "SELECT * FROM users WHERE id = :user_id{$profileTenantClause}";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':user_id', $userId);
            if (table_has_column('users', 'tenant_id')) {
                $stmt->bindParam(':tenant_id', $tenantId);
            }
            $stmt->execute();
            $cachedData['profile'] = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        // Schedule
        if (in_array('schedule', $dataTypes) || in_array('all', $dataTypes)) {
            if ($role === 'student') {
                $scheduleTenantClause = table_has_column('classes', 'tenant_id') ? ' AND c.tenant_id = :tenant_id' : '';
                $query = "SELECT c.*, sc.section
                          FROM classes c
                          JOIN student_classes sc ON c.id = sc.class_id
                          WHERE sc.student_id = :user_id{$scheduleTenantClause}";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':user_id', $userId);
                if (table_has_column('classes', 'tenant_id')) {
                    $stmt->bindParam(':tenant_id', $tenantId);
                }
                $stmt->execute();
                $cachedData['schedule'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }

        // Recent messages (last 50)
        if (in_array('messages', $dataTypes) || in_array('all', $dataTypes)) {
            $messageTenantClause = table_has_column('messages', 'tenant_id') ? ' AND tenant_id = :tenant_id' : '';
            $query = "SELECT * FROM messages
                      WHERE receiver_id = :user_id{$messageTenantClause}
                      ORDER BY created_at DESC
                      LIMIT 50";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':user_id', $userId);
            if (table_has_column('messages', 'tenant_id')) {
                $stmt->bindParam(':tenant_id', $tenantId);
            }
            $stmt->execute();
            $cachedData['messages'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // Assignments
        if (in_array('assignments', $dataTypes) || in_array('all', $dataTypes)) {
            if ($role === 'student') {
                $assignmentTenantClause = table_has_column('assignments', 'tenant_id') ? ' AND a.tenant_id = :tenant_id' : '';
                $query = "SELECT a.* FROM assignments a
                          JOIN student_classes sc ON a.class_id = sc.class_id
                          WHERE sc.student_id = :user_id
                          AND a.due_date >= CURDATE(){$assignmentTenantClause}
                          ORDER BY a.due_date ASC";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':user_id', $userId);
                if (table_has_column('assignments', 'tenant_id')) {
                    $stmt->bindParam(':tenant_id', $tenantId);
                }
                $stmt->execute();
                $cachedData['assignments'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }

        sendResponse(true, 'Cached data retrieved', $cachedData);
    } catch (PDOException $e) {
        error_log("Get cached data error: " . $e->getMessage());
        sendResponse(false, 'Failed to retrieve cached data');
    }
}

/**
 * Get sync status
 */
function getSyncStatus($db)
{
    $userId = (int)$_SESSION['user_id'];
    $tenantId = current_tenant_id();

    try {
        // Get last sync time
        $statusTenantClause = table_has_column('user_sync_status', 'tenant_id') ? ' AND tenant_id = :tenant_id' : '';
        $query = "SELECT last_sync FROM user_sync_status
                  WHERE user_id = :user_id{$statusTenantClause}";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        if (table_has_column('user_sync_status', 'tenant_id')) {
            $stmt->bindParam(':tenant_id', $tenantId);
        }
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $lastSync = strtotime($row['last_sync']);
        } else {
            $lastSync = 0;
        }

        // Update last sync
        $payloadColumns = ['user_id' => $userId, 'last_sync' => date('Y-m-d H:i:s')];
        if (table_has_column('user_sync_status', 'tenant_id')) {
            $payloadColumns['tenant_id'] = $tenantId;
        }

        $query = "INSERT INTO user_sync_status (" . implode(', ', array_keys($payloadColumns)) . ")
                  VALUES (" . implode(', ', array_fill(0, count($payloadColumns), '?')) . ")
                  ON DUPLICATE KEY UPDATE last_sync = NOW()";
        $stmt = $db->prepare($query);
        $stmt->execute(array_values($payloadColumns));

        sendResponse(true, 'Sync status retrieved', [
            'last_sync' => $lastSync,
            'current_time' => time()
        ]);
    } catch (PDOException $e) {
        error_log("Get sync status error: " . $e->getMessage());
        sendResponse(false, 'Failed to get sync status');
    }
}

/**
 * Save base64 encoded file
 */
function saveBase64File($base64Data, $fileName, $userId)
{
    $uploadDir = '../uploads/submissions/' . $userId . '/';

    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $fileData = explode(',', $base64Data);
    $data = base64_decode($fileData[1] ?? $fileData[0]);

    $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '', $fileName);
    $filePath = $uploadDir . time() . '_' . $safeName;

    file_put_contents($filePath, $data);

    return str_replace('../', '', $filePath);
}

function sync_attendance_table(): string
{
    return table_exists('attendance') ? 'attendance' : 'attendance_records';
}

function sync_attendance_date_column(string $table): string
{
    if (table_has_column($table, 'date')) {
        return 'date';
    }
    if (table_has_column($table, 'attendance_date')) {
        return 'attendance_date';
    }

    return '';
}

function sync_normalize_attendance_record(array $record, int $tenantId, int $userId): array
{
    $raw = isset($record['data']) && is_array($record['data']) ? $record['data'] : $record;
    $table = sync_attendance_table();
    $dateColumn = sync_attendance_date_column($table);
    $dateValue = (string)($raw[$dateColumn] ?? $raw['date'] ?? $raw['attendance_date'] ?? date('Y-m-d'));
    $checkInValue = (string)($raw['check_in_time'] ?? $raw['timestamp'] ?? date('Y-m-d H:i:s'));
    $status = (string)($raw['status'] ?? 'present');

    $payload = [
        'student_id' => (int)($raw['student_id'] ?? 0),
        'class_id' => (int)($raw['class_id'] ?? 0),
        'status' => $status,
        'created_at' => date('Y-m-d H:i:s'),
    ];
    $payload += sync_tenant_payload($table, $tenantId);

    if ($table === 'attendance') {
        $payload['teacher_id'] = (int)($raw['teacher_id'] ?? $userId);
        $payload['date'] = $dateValue;
        if (table_has_column($table, 'check_in_time')) {
            $payload['check_in_time'] = $checkInValue;
        }
        if (table_has_column($table, 'notes')) {
            $payload['notes'] = (string)($raw['notes'] ?? 'Offline sync');
        }
        if (table_has_column($table, 'updated_by')) {
            $payload['updated_by'] = $userId;
        }
        $lookup = [
            'tenant_id' => $tenantId,
            'student_id' => (int)($raw['student_id'] ?? 0),
            'class_id' => (int)($raw['class_id'] ?? 0),
            'date' => $dateValue,
        ];
    } else {
        if ($dateColumn !== '') {
            $payload[$dateColumn] = $dateValue;
        }
        $payload['marked_by'] = (int)($raw['marked_by'] ?? $userId);
        if (table_has_column($table, 'check_in_time')) {
            $payload['check_in_time'] = $checkInValue;
        }
        if (table_has_column($table, 'updated_at')) {
            $payload['updated_at'] = date('Y-m-d H:i:s');
        }
        if (table_has_column($table, 'remarks')) {
            $payload['remarks'] = (string)($raw['remarks'] ?? 'Offline sync');
        }
        $lookup = [
            'tenant_id' => $tenantId,
            'student_id' => (int)($raw['student_id'] ?? 0),
            'class_id' => (int)($raw['class_id'] ?? 0),
            'attendance_date' => $dateValue,
        ];
    }

    return [
        'table' => $table,
        'data' => $payload,
        'lookup' => $lookup,
    ];
}

function sync_find_existing_attendance($db, string $table, array $lookup)
{
    $whereParts = [];
    $params = [];

    foreach (['tenant_id', 'school_id'] as $tenantColumn) {
        if (table_has_column($table, $tenantColumn)) {
            $whereParts[] = "{$tenantColumn} = ?";
            $params[] = $lookup['tenant_id'];
            break;
        }
    }

    $whereParts[] = 'student_id = ?';
    $whereParts[] = 'class_id = ?';
    $params[] = $lookup['student_id'];
    $params[] = $lookup['class_id'];

    $dateColumn = sync_attendance_date_column($table);
    if ($dateColumn !== '') {
        $whereParts[] = "{$dateColumn} = ?";
        $params[] = $lookup['attendance_date'];
    } elseif (table_has_column($table, 'check_in_time')) {
        $whereParts[] = 'DATE(check_in_time) = ?';
        $params[] = $lookup['attendance_date'];
    }

    $stmt = $db->prepare("SELECT id FROM {$table} WHERE " . implode(' AND ', $whereParts) . " LIMIT 1");
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function sync_tenant_payload(string $table, int $tenantId): array
{
    $payload = [];
    foreach (['tenant_id', 'school_id'] as $column) {
        if (table_has_column($table, $column)) {
            $payload[$column] = $tenantId;
        }
    }

    return $payload;
}

function sync_user_in_tenant(int $userId, int $tenantId): bool
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

    $user = db()->fetchOne("SELECT tenant_id, school_id FROM users WHERE id = ? LIMIT 1", [$userId]) ?: [];
    return in_array($tenantId, array_filter([(int)($user['tenant_id'] ?? 0), (int)($user['school_id'] ?? 0)]), true);
}

function sync_assert_attendance_access(int $studentId, int $classId, int $tenantId, int $userId, string $role): void
{
    $student = db()->fetchOne(
        "SELECT id FROM students WHERE id = ? AND (" .
        (table_has_column('students', 'tenant_id') ? 'tenant_id = ?' : 'school_id = ?') .
        ") LIMIT 1",
        [$studentId, $tenantId]
    );
    if (!$student) {
        throw new Exception('Student is not in the current tenant');
    }

    $classTenantColumn = table_has_column('classes', 'tenant_id') ? 'tenant_id' : (table_has_column('classes', 'school_id') ? 'school_id' : '');
    $classParams = [$classId];
    $classSql = "SELECT * FROM classes WHERE id = ?";
    if ($classTenantColumn !== '') {
        $classSql .= " AND {$classTenantColumn} = ?";
        $classParams[] = $tenantId;
    }
    $classSql .= " LIMIT 1";
    $class = db()->fetchOne($classSql, $classParams);
    if (!$class) {
        throw new Exception('Class is not in the current tenant');
    }

    if (table_exists('class_enrollments')) {
        $enrollmentSql = "SELECT id FROM class_enrollments WHERE student_id = ? AND class_id = ?";
        $enrollmentParams = [$studentId, $classId];
        if (table_has_column('class_enrollments', 'status')) {
            $enrollmentSql .= " AND status = 'active'";
        }
        if (table_has_column('class_enrollments', 'tenant_id')) {
            $enrollmentSql .= " AND tenant_id = ?";
            $enrollmentParams[] = $tenantId;
        } elseif (table_has_column('class_enrollments', 'school_id')) {
            $enrollmentSql .= " AND school_id = ?";
            $enrollmentParams[] = $tenantId;
        }
        $enrollmentSql .= " LIMIT 1";
        if (!db()->fetchOne($enrollmentSql, $enrollmentParams)) {
            throw new Exception('Student is not enrolled in this class');
        }
    }

    if (in_array($role, ['admin', 'owner', 'principal', 'vice_principal', 'admin_officer'], true)) {
        return;
    }

    if ($role !== 'teacher') {
        throw new Exception('Attendance sync is not allowed for this role');
    }

    $teacherProfile = table_exists('teachers')
        ? db()->fetchOne(
            "SELECT id FROM teachers WHERE user_id = ? " .
            (table_has_column('teachers', 'tenant_id') ? "AND tenant_id = ?" : (table_has_column('teachers', 'school_id') ? "AND school_id = ?" : '')) .
            " LIMIT 1",
            (table_has_column('teachers', 'tenant_id') || table_has_column('teachers', 'school_id')) ? [$userId, $tenantId] : [$userId]
        )
        : null;
    $teacherProfileId = (int)($teacherProfile['id'] ?? 0);

    foreach (['teacher_id', 'class_teacher_id', 'teacher_user_id'] as $column) {
        if (isset($class[$column]) && (int)$class[$column] > 0) {
            if ((int)$class[$column] === $userId || ($teacherProfileId > 0 && (int)$class[$column] === $teacherProfileId)) {
                return;
            }
        }
    }

    if ($teacherProfileId > 0) {
        throw new Exception('Teacher is not assigned to this class');
    }
}

function sync_assignment_in_tenant(int $assignmentId, int $tenantId): bool
{
    if ($assignmentId <= 0) {
        return false;
    }

    $sql = "SELECT id FROM assignments WHERE id = ?";
    $params = [$assignmentId];
    if (table_has_column('assignments', 'tenant_id')) {
        $sql .= " AND tenant_id = ?";
        $params[] = $tenantId;
    } elseif (table_has_column('assignments', 'school_id')) {
        $sql .= " AND school_id = ?";
        $params[] = $tenantId;
    }
    $sql .= " LIMIT 1";

    return (bool)db()->fetchOne($sql, $params);
}
