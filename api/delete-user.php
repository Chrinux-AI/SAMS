<?php

/**
 * User Deletion API
 * Handles complete deletion of users and all related data
 */

// Start output buffering to prevent any premature output
ob_start();

session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';

// Require admin access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    ob_clean(); // Clear any buffered output
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Clear any buffered output before sending JSON
ob_clean();
header('Content-Type: application/json');

// Get request data
$data = json_decode(file_get_contents('php://input'), true);
$action = $_GET['action'] ?? '';

/**
 * Delete user completely with all related data
 */
function deleteUserCompletely($user_id)
{
    try {
        db()->beginTransaction();

        // Get user details first
        $user = db()->fetchOne("SELECT * FROM users WHERE id = ?", [$user_id]);
        if (!$user) {
            throw new Exception('User not found');
        }

        // 1. Delete from role-specific tables
        if ($user['role'] === 'student') {
            $student = db()->fetchOne("SELECT id FROM students WHERE user_id = ?", [$user_id]);
            if ($student) {
                $student_id = $student['id'];

                // Delete attendance records
                db()->delete('attendance_records', 'student_id = ?', [$student_id]);

                // Delete class enrollments
                db()->delete('class_enrollments', 'student_id = ?', [$student_id]);

                // Delete student record
                db()->delete('students', 'user_id = ?', [$user_id]);
            }
        } elseif ($user['role'] === 'teacher') {
            $teacher = db()->fetchOne("SELECT id FROM teachers WHERE user_id = ?", [$user_id]);
            if ($teacher) {
                $teacher_id = $teacher['id'];

                // Delete teacher assignments
                db()->delete('class_teachers', 'teacher_id = ?', [$teacher_id]);

                // Delete teacher record
                db()->delete('teachers', 'user_id = ?', [$user_id]);
            }
        } elseif ($user['role'] === 'parent') {
            // Delete parent-student relationships
            db()->delete('parent_student', 'parent_id = ?', [$user_id]);
        }

        // 2. Delete biometric data
        db()->delete('biometric_credentials', 'user_id = ?', [$user_id]);
        db()->delete('biometric_auth_logs', 'user_id = ?', [$user_id]);
        db()->delete('attendance_biometric', 'user_id = ?', [$user_id]);

        // 3. Delete notifications
        db()->delete('notifications', 'user_id = ?', [$user_id]);

        // 4. Delete messages (sent and received)
        if (db()->tableExists('messages')) {
            db()->delete('messages', 'sender_id = ? OR receiver_id = ?', [$user_id, $user_id]);
        }

        // 5. Delete audit logs
        db()->delete('audit_logs', 'user_id = ?', [$user_id]);

        // 6. Delete uploaded files from storage
        deleteUserFiles($user_id, $user);

        // 7. Finally, delete the user account
        db()->delete('users', 'id = ?', [$user_id]);

        // Log the deletion
        log_activity(
            $_SESSION['user_id'],
            'delete_user',
            'user',
            $user_id,
            "Deleted user: {$user['first_name']} {$user['last_name']} ({$user['email']}) - Role: {$user['role']}"
        );

        db()->commit();

        return [
            'success' => true,
            'message' => 'User deleted successfully',
            'deleted_user' => $user['first_name'] . ' ' . $user['last_name']
        ];
    } catch (Exception $e) {
        db()->rollback();
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Delete user files from storage
 */
function deleteUserFiles($user_id, $user)
{
    $upload_path = BASE_PATH . '/uploads';

    // Common user file directories
    $directories = [
        $upload_path . '/profiles/' . $user_id,
        $upload_path . '/documents/' . $user_id,
        $upload_path . '/photos/' . $user_id,
        $upload_path . '/students/' . $user_id,
        $upload_path . '/teachers/' . $user_id,
    ];

    foreach ($directories as $dir) {
        if (is_dir($dir)) {
            deleteDirectory($dir);
        }
    }

    // Delete individual profile photo if exists
    $profile_photo = $upload_path . '/profiles/' . $user['email'] . '.jpg';
    if (file_exists($profile_photo)) {
        unlink($profile_photo);
    }
}

/**
 * Recursively delete directory
 */
function deleteDirectory($dir)
{
    if (!is_dir($dir)) {
        return false;
    }

    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        is_dir($path) ? deleteDirectory($path) : unlink($path);
    }

    return rmdir($dir);
}

/**
 * Bulk delete users
 */
function bulkDeleteUsers($user_ids)
{
    $results = [
        'success' => true,
        'deleted' => 0,
        'failed' => 0,
        'errors' => []
    ];

    foreach ($user_ids as $user_id) {
        $result = deleteUserCompletely($user_id);
        if ($result['success']) {
            $results['deleted']++;
        } else {
            $results['failed']++;
            $results['errors'][] = "User ID $user_id: " . $result['error'];
        }
    }

    if ($results['failed'] > 0) {
        $results['success'] = false;
    }

    return $results;
}

// Handle actions
switch ($action) {
    case 'delete':
        if (!isset($data['user_id'])) {
            echo json_encode(['success' => false, 'error' => 'User ID required']);
            exit;
        }

        // Prevent self-deletion
        if ($data['user_id'] == $_SESSION['user_id']) {
            echo json_encode(['success' => false, 'error' => 'You cannot delete your own account']);
            exit;
        }

        $result = deleteUserCompletely($data['user_id']);
        echo json_encode($result);
        break;

    case 'bulk_delete':
        if (!isset($data['user_ids']) || !is_array($data['user_ids'])) {
            echo json_encode(['success' => false, 'error' => 'User IDs array required']);
            exit;
        }

        // Remove current user from the list
        $user_ids = array_filter($data['user_ids'], function ($id) {
            return $id != $_SESSION['user_id'];
        });

        if (empty($user_ids)) {
            echo json_encode(['success' => false, 'error' => 'No valid users to delete']);
            exit;
        }

        $result = bulkDeleteUsers($user_ids);
        echo json_encode($result);
        break;

    case 'delete_pending':
        // Delete all pending users
        if (!isset($data['confirm']) || $data['confirm'] !== 'DELETE_ALL_PENDING') {
            echo json_encode(['success' => false, 'error' => 'Confirmation required']);
            exit;
        }

        $pending_users = db()->fetchAll("SELECT id FROM users WHERE status = 'pending'");
        $user_ids = array_column($pending_users, 'id');

        $result = bulkDeleteUsers($user_ids);
        echo json_encode($result);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        break;
}
