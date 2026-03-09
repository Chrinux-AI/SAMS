<?php

/**
 * LTI API Endpoints
 * Handles LTI 1.3 operations and LMS integration
 */

header('Content-Type: application/json');
session_start();

require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';
require_once '../includes/lti.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {

        /**
         * Handle LTI 1.3 Tool Launch
         * POST: id_token, state
         */
        case 'launch':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Invalid request method');
            }

            $id_token = $_POST['id_token'] ?? '';
            $state = $_POST['state'] ?? '';
            $lti_config_id = $_POST['lti_config_id'] ?? 1; // Default to first config

            if (!$id_token) {
                throw new Exception('Missing ID token');
            }

            // Validate JWT token
            $payload = lti_validate_jwt($id_token, $lti_config_id);

            if (!$payload) {
                throw new Exception('Invalid LTI token');
            }

            // Handle launch
            $result = lti_handle_launch($payload, $lti_config_id);

            if (!$result['success']) {
                throw new Exception($result['error']);
            }

            // Set session for authenticated user
            $_SESSION['user_id'] = $result['user_id'];
            $_SESSION['lti_session'] = $result['session_token'];
            $_SESSION['lms_context_id'] = $result['lms_context_id'];

            // Get user details
            $user = db()->fetchOne("SELECT * FROM users WHERE id = ?", [$result['user_id']]);
            $_SESSION['role'] = $user['role'];
            $_SESSION['full_name'] = $user['first_name'] . ' ' . $user['last_name'];
            $_SESSION['username'] = $user['username'];

            echo json_encode([
                'success' => true,
                'message' => 'LTI launch successful',
                'redirect' => '/' . $user['role'] . '/dashboard.php',
                'new_user' => $result['new_user']
            ]);
            break;

        /**
         * Grade Passback to LMS
         * POST: user_id, lms_context_id, grade_value
         */
        case 'grade_passback':
            require_admin_or_teacher();

            $user_id = $_POST['user_id'] ?? 0;
            $lms_context_id = $_POST['lms_context_id'] ?? '';
            $grade_value = $_POST['grade_value'] ?? 0;
            $lti_config_id = $_POST['lti_config_id'] ?? 1;
            $sync_type = $_POST['sync_type'] ?? 'manual';

            if (!$user_id || !$lms_context_id) {
                throw new Exception('Missing required parameters');
            }

            $result = lti_grade_passback($user_id, $lti_config_id, $lms_context_id, $grade_value, $sync_type);

            echo json_encode([
                'success' => $result,
                'message' => $result ? 'Grade synced to LMS successfully' : 'Failed to sync grade'
            ]);
            break;

        /**
         * Create Deep Link
         * POST: resource_type, resource_id, title, lms_context_id
         */
        case 'deep_link':
            require_admin_or_teacher();

            $resource_type = $_POST['resource_type'] ?? '';
            $resource_id = $_POST['resource_id'] ?? 0;
            $title = $_POST['title'] ?? 'Attendance AI Resource';
            $lms_context_id = $_POST['lms_context_id'] ?? '';
            $lti_config_id = $_POST['lti_config_id'] ?? 1;

            if (!$resource_type || !$lms_context_id) {
                throw new Exception('Missing required parameters');
            }

            $result = lti_create_deep_link($lti_config_id, $resource_type, $resource_id, $title, $lms_context_id);

            echo json_encode($result);
            break;

        /**
         * Sync Course Roster from LMS
         * POST: lms_context_id, lti_config_id
         */
        case 'sync_courses':
            require_admin();

            $lms_context_id = $_POST['lms_context_id'] ?? '';
            $lti_config_id = $_POST['lti_config_id'] ?? 1;

            if (!$lms_context_id) {
                throw new Exception('Missing LMS context ID');
            }

            $result = lti_sync_course_roster($lti_config_id, $lms_context_id);

            echo json_encode($result);
            break;

        /**
         * Bulk Grade Sync
         * Sync attendance-based grades for all students in a class/course
         * POST: class_id, lms_context_id, lti_config_id
         */
        case 'bulk_grade_sync':
            require_admin_or_teacher();

            $class_id = $_POST['class_id'] ?? 0;
            $lms_context_id = $_POST['lms_context_id'] ?? '';
            $lti_config_id = $_POST['lti_config_id'] ?? 1;

            if (!$class_id || !$lms_context_id) {
                throw new Exception('Missing required parameters');
            }

            // Get all students in class
            $students = db()->fetchAll(
                "SELECT s.user_id, u.lms_user_id
                 FROM students s
                 JOIN users u ON s.user_id = u.id
                 WHERE s.class_id = ? AND u.lms_user_id IS NOT NULL",
                [$class_id]
            );

            $synced = 0;
            $failed = 0;

            foreach ($students as $student) {
                // Calculate attendance percentage
                $stats = db()->fetchOne(
                    "SELECT
                        COUNT(*) as total,
                        SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present
                     FROM attendance_records
                     WHERE student_id = (SELECT id FROM students WHERE user_id = ?)
                     AND DATE(check_in_time) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)",
                    [$student['user_id']]
                );

                $attendance_percentage = $stats['total'] > 0
                    ? ($stats['present'] / $stats['total']) * 100
                    : 0;

                // Sync to LMS
                $result = lti_grade_passback(
                    $student['user_id'],
                    $lti_config_id,
                    $lms_context_id,
                    $attendance_percentage,
                    'bulk'
                );

                if ($result) {
                    $synced++;
                } else {
                    $failed++;
                }
            }

            echo json_encode([
                'success' => true,
                'synced' => $synced,
                'failed' => $failed,
                'total' => count($students)
            ]);
            break;

        /**
         * Get LTI Session Info
         * GET: session_token
         */
        case 'session_info':
            $session_token = $_GET['session_token'] ?? $_SESSION['lti_session'] ?? '';

            if (!$session_token) {
                throw new Exception('No session token provided');
            }

            $session = lti_validate_session($session_token);

            if (!$session) {
                throw new Exception('Invalid or expired session');
            }

            echo json_encode([
                'success' => true,
                'session' => [
                    'user_id' => $session['user_id'],
                    'email' => $session['email'],
                    'role' => $session['role'],
                    'lms_context_id' => $session['lms_context_id'],
                    'expires_at' => $session['expires_at']
                ]
            ]);
            break;

        /**
         * Get Grade Sync History
         * GET: user_id (optional), lms_context_id (optional)
         */
        case 'sync_history':
            require_admin_or_teacher();

            $user_id = $_GET['user_id'] ?? null;
            $lms_context_id = $_GET['lms_context_id'] ?? null;

            $where = ['1=1'];
            $params = [];

            if ($user_id) {
                $where[] = 'user_id = ?';
                $params[] = $user_id;
            }

            if ($lms_context_id) {
                $where[] = 'lms_context_id = ?';
                $params[] = $lms_context_id;
            }

            $history = db()->fetchAll(
                "SELECT gsl.*, u.first_name, u.last_name, u.email,
                        lc.lms_name, lc.lms_platform
                 FROM lti_grade_sync_log gsl
                 JOIN users u ON gsl.user_id = u.id
                 JOIN lti_configurations lc ON gsl.lti_config_id = lc.id
                 WHERE " . implode(' AND ', $where) . "
                 ORDER BY gsl.synced_at DESC
                 LIMIT 100",
                $params
            );

            echo json_encode([
                'success' => true,
                'history' => $history
            ]);
            break;

        /**
         * Retry Failed Grade Sync
         * POST: sync_log_id
         */
        case 'retry_sync':
            require_admin();

            $sync_log_id = $_POST['sync_log_id'] ?? 0;

            if (!$sync_log_id) {
                throw new Exception('Missing sync log ID');
            }

            $log = db()->fetchOne(
                "SELECT * FROM lti_grade_sync_log WHERE id = ? AND status = 'failed'",
                [$sync_log_id]
            );

            if (!$log) {
                throw new Exception('Invalid or non-failed sync log');
            }

            $result = lti_grade_passback(
                $log['user_id'],
                $log['lti_config_id'],
                $log['lms_context_id'],
                $log['grade_value'],
                'manual'
            );

            echo json_encode([
                'success' => $result,
                'message' => $result ? 'Sync retry successful' : 'Sync retry failed'
            ]);
            break;

        /**
         * Get LTI Configuration List
         * GET: (admin only)
         */
        case 'configurations':
            require_admin();

            $configs = db()->fetchAll(
                "SELECT id, lms_platform, lms_name, client_id, is_active,
                        auto_sync_enabled, sync_frequency, last_sync_at, created_at
                 FROM lti_configurations
                 ORDER BY is_active DESC, lms_name"
            );

            echo json_encode([
                'success' => true,
                'configurations' => $configs
            ]);
            break;

        /**
         * Toggle LTI Configuration Active Status
         * POST: config_id, is_active
         */
        case 'toggle_config':
            require_admin();

            $config_id = $_POST['config_id'] ?? 0;
            $is_active = $_POST['is_active'] ?? 0;

            db()->execute(
                "UPDATE lti_configurations SET is_active = ? WHERE id = ?",
                [$is_active, $config_id]
            );

            echo json_encode([
                'success' => true,
                'message' => 'Configuration updated'
            ]);
            break;

        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * Helper: Require admin or teacher role
 */
function require_admin_or_teacher()
{
    if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'teacher'])) {
        throw new Exception('Unauthorized: Admin or teacher access required');
    }
}
