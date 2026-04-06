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
    if (!isset($_SESSION['user_id'])) {
        sendResponse(false, 'Authentication required');
    }

    $userId = $_SESSION['user_id'];
    $lastSync = $_GET['last_sync'] ?? 0;

    try {
        $updates = [];

        // Check new messages
        $query = "SELECT COUNT(*) as count FROM messages
                  WHERE receiver_id = :user_id
                  AND created_at > FROM_UNIXTIME(:last_sync)
                  AND is_read = 0";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->bindParam(':last_sync', $lastSync);
        $stmt->execute();
        $updates['new_messages'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        // Check new announcements
        $query = "SELECT COUNT(*) as count FROM announcements
                  WHERE created_at > FROM_UNIXTIME(:last_sync)
                  AND status = 'active'";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':last_sync', $lastSync);
        $stmt->execute();
        $updates['new_announcements'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        // Check assignment updates
        $query = "SELECT COUNT(*) as count FROM assignments a
                  JOIN student_classes sc ON a.class_id = sc.class_id
                  WHERE sc.student_id = :user_id
                  AND a.updated_at > FROM_UNIXTIME(:last_sync)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->bindParam(':last_sync', $lastSync);
        $stmt->execute();
        $updates['updated_assignments'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        // Check attendance updates
        $query = "SELECT COUNT(*) as count FROM attendance
                  WHERE student_id = :user_id
                  AND updated_at > FROM_UNIXTIME(:last_sync)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->bindParam(':last_sync', $lastSync);
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
    if (!isset($_SESSION['user_id'])) {
        sendResponse(false, 'Authentication required');
    }

    $records = $data['records'] ?? [];

    if (empty($records)) {
        sendResponse(false, 'No records to sync');
    }

    $synced = 0;
    $failed = [];

    try {
        $db->beginTransaction();

        foreach ($records as $record) {
            try {
                $query = "INSERT INTO attendance
                          (student_id, class_id, date, status, check_in_time,
                           latitude, longitude, created_at)
                          VALUES
                          (:student_id, :class_id, :date, :status, :check_in_time,
                           :latitude, :longitude, NOW())
                          ON DUPLICATE KEY UPDATE
                          status = VALUES(status),
                          check_in_time = VALUES(check_in_time),
                          latitude = VALUES(latitude),
                          longitude = VALUES(longitude),
                          updated_at = NOW()";

                $stmt = $db->prepare($query);
                $stmt->bindParam(':student_id', $record['student_id']);
                $stmt->bindParam(':class_id', $record['class_id']);
                $stmt->bindParam(':date', $record['date']);
                $stmt->bindParam(':status', $record['status']);
                $stmt->bindParam(':check_in_time', $record['check_in_time']);
                $stmt->bindParam(':latitude', $record['latitude']);
                $stmt->bindParam(':longitude', $record['longitude']);
                $stmt->execute();

                $synced++;
            } catch (PDOException $e) {
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
    if (!isset($_SESSION['user_id'])) {
        sendResponse(false, 'Authentication required');
    }

    $userId = $_SESSION['user_id'];
    $messages = $data['messages'] ?? [];

    if (empty($messages)) {
        sendResponse(false, 'No messages to sync');
    }

    $synced = 0;
    $failed = [];

    try {
        $db->beginTransaction();

        foreach ($messages as $msg) {
            try {
                $query = "INSERT INTO messages
                          (sender_id, receiver_id, subject, message, created_at)
                          VALUES
                          (:sender_id, :receiver_id, :subject, :message, NOW())";

                $stmt = $db->prepare($query);
                $stmt->bindParam(':sender_id', $userId);
                $stmt->bindParam(':receiver_id', $msg['receiver_id']);
                $stmt->bindParam(':subject', $msg['subject']);
                $stmt->bindParam(':message', $msg['message']);
                $stmt->execute();

                $synced++;
            } catch (PDOException $e) {
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
    if (!isset($_SESSION['user_id'])) {
        sendResponse(false, 'Authentication required');
    }

    $userId = $_SESSION['user_id'];
    $submissions = $data['submissions'] ?? [];

    if (empty($submissions)) {
        sendResponse(false, 'No submissions to sync');
    }

    $synced = 0;
    $failed = [];

    try {
        $db->beginTransaction();

        foreach ($submissions as $submission) {
            try {
                // Handle file uploads if present
                $filePath = null;
                if (!empty($submission['file_data'])) {
                    $filePath = saveBase64File(
                        $submission['file_data'],
                        $submission['file_name'],
                        $userId
                    );
                }

                $query = "INSERT INTO assignment_submissions
                          (assignment_id, student_id, submission_text,
                           file_path, submitted_at)
                          VALUES
                          (:assignment_id, :student_id, :submission_text,
                           :file_path, NOW())
                          ON DUPLICATE KEY UPDATE
                          submission_text = VALUES(submission_text),
                          file_path = VALUES(file_path),
                          submitted_at = NOW()";

                $stmt = $db->prepare($query);
                $stmt->bindParam(':assignment_id', $submission['assignment_id']);
                $stmt->bindParam(':student_id', $userId);
                $stmt->bindParam(':submission_text', $submission['text']);
                $stmt->bindParam(':file_path', $filePath);
                $stmt->execute();

                $synced++;
            } catch (PDOException $e) {
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
    if (!isset($_SESSION['user_id'])) {
        sendResponse(false, 'Authentication required');
    }

    $userId = $_SESSION['user_id'];
    $role = $_SESSION['role'];
    $dataTypes = $data['types'] ?? ['all'];

    try {
        $cachedData = [];

        // User profile
        if (in_array('profile', $dataTypes) || in_array('all', $dataTypes)) {
            $query = "SELECT * FROM users WHERE id = :user_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':user_id', $userId);
            $stmt->execute();
            $cachedData['profile'] = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        // Schedule
        if (in_array('schedule', $dataTypes) || in_array('all', $dataTypes)) {
            if ($role === 'student') {
                $query = "SELECT c.*, sc.section
                          FROM classes c
                          JOIN student_classes sc ON c.id = sc.class_id
                          WHERE sc.student_id = :user_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':user_id', $userId);
                $stmt->execute();
                $cachedData['schedule'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }

        // Recent messages (last 50)
        if (in_array('messages', $dataTypes) || in_array('all', $dataTypes)) {
            $query = "SELECT * FROM messages
                      WHERE receiver_id = :user_id
                      ORDER BY created_at DESC
                      LIMIT 50";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':user_id', $userId);
            $stmt->execute();
            $cachedData['messages'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // Assignments
        if (in_array('assignments', $dataTypes) || in_array('all', $dataTypes)) {
            if ($role === 'student') {
                $query = "SELECT a.* FROM assignments a
                          JOIN student_classes sc ON a.class_id = sc.class_id
                          WHERE sc.student_id = :user_id
                          AND a.due_date >= CURDATE()
                          ORDER BY a.due_date ASC";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':user_id', $userId);
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
    if (!isset($_SESSION['user_id'])) {
        sendResponse(false, 'Authentication required');
    }

    $userId = $_SESSION['user_id'];

    try {
        // Get last sync time
        $query = "SELECT last_sync FROM user_sync_status
                  WHERE user_id = :user_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $lastSync = strtotime($row['last_sync']);
        } else {
            $lastSync = 0;
        }

        // Update last sync
        $query = "INSERT INTO user_sync_status (user_id, last_sync)
                  VALUES (:user_id, NOW())
                  ON DUPLICATE KEY UPDATE last_sync = NOW()";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();

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
