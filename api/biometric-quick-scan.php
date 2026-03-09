<?php

/**
 * Biometric Quick Scan API
 * Handles instant attendance marking via biometric authentication
 */

session_start();
header('Content-Type: application/json');

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get_student_info':
            // Get student info after biometric authentication
            $credential_id = $_POST['credential_id'] ?? '';

            if (empty($credential_id)) {
                throw new Exception('Credential ID is required');
            }

            // Get biometric credential and associated user
            $credential = db()->fetchOne(
                "SELECT bc.*, u.id as user_id, u.full_name, u.role, s.id as student_id, s.admission_number as student_code
                FROM biometric_credentials bc
                JOIN users u ON bc.user_id = u.id
                LEFT JOIN students s ON u.id = s.user_id
                WHERE bc.credential_id = ? AND bc.status = 'active'",
                [$credential_id]
            );

            if (!$credential) {
                throw new Exception('Biometric credential not found or inactive');
            }

            if ($credential['role'] !== 'student') {
                throw new Exception('Quick scan is only available for students');
            }

            // Get student's current classes
            $classes = db()->fetchAll(
                "SELECT c.id, c.class_name, c.class_code, c.start_time, c.end_time,
                        t.first_name as teacher_first, t.last_name as teacher_last
                FROM class_enrollments ce
                JOIN classes c ON ce.class_id = c.id
                LEFT JOIN teachers t ON c.teacher_id = t.id
                WHERE ce.student_id = ? AND ce.status = 'active'
                ORDER BY c.start_time",
                [$credential['student_id']]
            );

            // Get today's attendance status
            $today_attendance = db()->fetchAll(
                "SELECT ar.*, c.class_name, c.class_code
                FROM attendance_records ar
                JOIN classes c ON ar.class_id = c.id
                WHERE ar.student_id = ? AND DATE(ar.attendance_date) = CURDATE()",
                [$credential['student_id']]
            );

            echo json_encode([
                'success' => true,
                'student' => [
                    'id' => $credential['student_id'],
                    'code' => $credential['student_code'],
                    'name' => $credential['full_name'],
                    'user_id' => $credential['user_id']
                ],
                'classes' => $classes,
                'today_attendance' => $today_attendance
            ]);
            break;

        case 'mark_attendance':
            // Mark attendance via biometric scan
            $student_id = intval($_POST['student_id'] ?? 0);
            $class_id = intval($_POST['class_id'] ?? 0);
            $credential_id = $_POST['credential_id'] ?? '';

            if (!$student_id || !$class_id) {
                throw new Exception('Student ID and Class ID are required');
            }

            // Verify student is enrolled in this class
            $enrollment = db()->fetchOne(
                "SELECT id FROM class_enrollments WHERE student_id = ? AND class_id = ? AND status = 'active'",
                [$student_id, $class_id]
            );

            if (!$enrollment) {
                throw new Exception('Student is not enrolled in this class');
            }

            // Check if already marked today
            $existing = db()->fetchOne(
                "SELECT id FROM attendance_records
                WHERE student_id = ? AND class_id = ? AND DATE(attendance_date) = CURDATE()",
                [$student_id, $class_id]
            );

            if ($existing) {
                throw new Exception('Attendance already marked for this class today');
            }

            // Get class info for timing
            $class = db()->fetchOne("SELECT * FROM classes WHERE id = ?", [$class_id]);
            $current_time = date('H:i:s');

            // Determine status based on time
            $status = 'present';
            if ($class && $class['start_time']) {
                $start = strtotime($class['start_time']);
                $now = strtotime($current_time);
                $diff_minutes = ($now - $start) / 60;

                if ($diff_minutes > 15) {
                    $status = 'late';
                } else if ($diff_minutes < -30) {
                    $status = 'early';
                }
            }

            // Insert attendance record
            $attendance_id = db()->insert('attendance_records', [
                'student_id' => $student_id,
                'class_id' => $class_id,
                'attendance_date' => date('Y-m-d'),
                'status' => $status,
                'check_in_time' => $current_time,
                'marked_by' => $_SESSION['user_id'],
                'method' => 'biometric',
                'created_at' => date('Y-m-d H:i:s')
            ]);

            // Log biometric authentication
            db()->insert('biometric_auth_logs', [
                'user_id' => db()->fetchOne("SELECT user_id FROM students WHERE id = ?", [$student_id])['user_id'],
                'credential_id' => $credential_id,
                'auth_method' => 'platform',
                'success' => 1,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                'created_at' => date('Y-m-d H:i:s')
            ]);

            // Log activity
            log_activity(
                $_SESSION['user_id'],
                'mark_attendance',
                'attendance_records',
                $attendance_id,
                "Marked attendance via biometric quick scan for student ID: {$student_id}, Class ID: {$class_id}, Status: {$status}"
            );

            echo json_encode([
                'success' => true,
                'message' => 'Attendance marked successfully',
                'attendance_id' => $attendance_id,
                'status' => $status,
                'time' => $current_time
            ]);
            break;

        case 'get_recent_scans':
            // Get recent biometric scans (last 24 hours)
            $scans = db()->fetchAll(
                "SELECT bal.*, u.full_name, u.role,
                        CASE
                            WHEN u.role = 'student' THEN s.admission_number
                            WHEN u.role = 'teacher' THEN t.teacher_id
                            ELSE NULL
                        END as person_code
                FROM biometric_auth_logs bal
                JOIN users u ON bal.user_id = u.id
                LEFT JOIN students s ON u.id = s.user_id
                LEFT JOIN teachers t ON u.id = t.user_id
                WHERE bal.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                ORDER BY bal.created_at DESC
                LIMIT 50"
            );

            echo json_encode([
                'success' => true,
                'scans' => $scans
            ]);
            break;

        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
