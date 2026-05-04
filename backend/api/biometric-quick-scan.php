<?php

/**
 * Biometric Quick Scan API
 * Handles instant attendance marking via biometric authentication.
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/merit-integration.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
if (!isset($_SESSION['tenant_id'])) {
    set_user_tenant_session($userId);
}

$tenantId = current_tenant_id();
if ($tenantId <= 0 || !user_in_current_tenant($userId)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Tenant access denied']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get_student_info':
            $credentialId = trim((string)($_POST['credential_id'] ?? ''));
            if ($credentialId === '') {
                throw new Exception('Credential ID is required');
            }

            $credential = quick_scan_student_context($credentialId, $tenantId);
            if (!$credential) {
                throw new Exception('Biometric credential not found or inactive');
            }

            if (($credential['role'] ?? '') !== 'student') {
                throw new Exception('Quick scan is only available for students');
            }

            $enrollmentTenantWhere = quick_scan_tenant_where('class_enrollments', $tenantId, 'ce');
            $classTenantWhere = quick_scan_tenant_where('classes', $tenantId, 'c');
            $classes = db()->fetchAll(
                "SELECT c.id, c.class_name, c.class_code, c.start_time, c.end_time,
                        t.first_name AS teacher_first, t.last_name AS teacher_last
                 FROM class_enrollments ce
                 JOIN classes c ON ce.class_id = c.id
                 LEFT JOIN teachers t ON c.class_teacher_id = t.id
                 WHERE ce.student_id = ?
                   AND " . (table_has_column('class_enrollments', 'status') ? "ce.status = 'active'" : '1=1') .
                 "{$enrollmentTenantWhere['sql']}{$classTenantWhere['sql']}
                 ORDER BY c.start_time",
                array_merge([(int)$credential['student_id']], $enrollmentTenantWhere['params'], $classTenantWhere['params'])
            ) ?: [];

            $attendanceDateExpr = quick_scan_attendance_date_expression('ar');
            $attendanceTenantWhere = quick_scan_tenant_where('attendance_records', $tenantId, 'ar');
            $todayAttendance = db()->fetchAll(
                "SELECT ar.*, c.class_name, c.class_code
                 FROM attendance_records ar
                 JOIN classes c ON ar.class_id = c.id
                 WHERE ar.student_id = ?
                   AND {$attendanceDateExpr} = CURDATE()
                   {$attendanceTenantWhere['sql']}{$classTenantWhere['sql']}
                 ORDER BY c.start_time",
                array_merge([(int)$credential['student_id']], $attendanceTenantWhere['params'], $classTenantWhere['params'])
            ) ?: [];

            echo json_encode([
                'success' => true,
                'student' => [
                    'id' => (int)$credential['student_id'],
                    'code' => $credential['student_code'],
                    'name' => $credential['full_name'],
                    'user_id' => (int)$credential['user_id'],
                ],
                'classes' => $classes,
                'today_attendance' => $todayAttendance,
            ]);
            break;

        case 'mark_attendance':
            $studentId = (int)($_POST['student_id'] ?? 0);
            $classId = (int)($_POST['class_id'] ?? 0);
            $credentialId = trim((string)($_POST['credential_id'] ?? ''));

            if ($studentId <= 0 || $classId <= 0) {
                throw new Exception('Student ID and Class ID are required');
            }

            if ($credentialId !== '') {
                $credential = quick_scan_student_context($credentialId, $tenantId);
                if (!$credential || (int)$credential['student_id'] !== $studentId) {
                    throw new Exception('Credential does not match the selected student');
                }
            }

            $student = quick_scan_student_record($studentId, $tenantId);
            if (!$student) {
                throw new Exception('Student not found in current tenant');
            }

            $class = quick_scan_class_record($classId, $tenantId);
            if (!$class) {
                throw new Exception('Class not found in current tenant');
            }

            $enrollmentTenantWhere = quick_scan_tenant_where('class_enrollments', $tenantId);
            $enrollment = db()->fetchOne(
                "SELECT id FROM class_enrollments
                 WHERE student_id = ? AND class_id = ?
                   AND " . (table_has_column('class_enrollments', 'status') ? "status = 'active'" : '1=1') .
                 "{$enrollmentTenantWhere['sql']}
                 LIMIT 1",
                array_merge([$studentId, $classId], $enrollmentTenantWhere['params'])
            );

            if (!$enrollment) {
                throw new Exception('Student is not enrolled in this class');
            }

            $attendanceTenantWhere = quick_scan_tenant_where('attendance_records', $tenantId);
            $attendanceDateExpr = quick_scan_attendance_date_expression();
            $existing = db()->fetchOne(
                "SELECT id FROM attendance_records
                 WHERE student_id = ? AND class_id = ? AND {$attendanceDateExpr} = CURDATE()
                   {$attendanceTenantWhere['sql']}
                 LIMIT 1",
                array_merge([$studentId, $classId], $attendanceTenantWhere['params'])
            );

            if ($existing) {
                throw new Exception('Attendance already marked for this class today');
            }

            $currentTime = date('H:i:s');
            $status = 'present';
            if (!empty($class['start_time'])) {
                $start = strtotime((string)$class['start_time']);
                $now = strtotime($currentTime);
                $diffMinutes = ($now - $start) / 60;

                if ($diffMinutes > 15) {
                    $status = 'late';
                } elseif ($diffMinutes < -30) {
                    $status = 'early';
                }
            }

            $attendancePayload = quick_scan_tenant_payload('attendance_records', $tenantId) + [
                'student_id' => $studentId,
                'class_id' => $classId,
                'status' => $status,
                'marked_by' => $userId,
                'method' => 'biometric',
                'created_at' => date('Y-m-d H:i:s'),
            ];
            if (table_has_column('attendance_records', 'attendance_date')) {
                $attendancePayload['attendance_date'] = date('Y-m-d');
            }
            if (table_has_column('attendance_records', 'check_in_time')) {
                $attendancePayload['check_in_time'] = date('Y-m-d ') . $currentTime;
            }

            $attendanceId = insert_flexible('attendance_records', $attendancePayload);
            if (!$attendanceId) {
                throw new Exception('Failed to create attendance record');
            }

            try {
                sams_sync_attendance_merit(
                    (int)$attendanceId,
                    $studentId,
                    $classId,
                    $status,
                    $userId,
                    date('Y-m-d'),
                    'biometric_quick_scan'
                );
            } catch (Throwable $e) {
                error_log('Attendance merit sync failed (biometric quick scan): ' . $e->getMessage());
            }

            insert_flexible('biometric_auth_logs', quick_scan_tenant_payload('biometric_auth_logs', $tenantId) + [
                'user_id' => (int)$student['user_id'],
                'credential_id' => $credentialId,
                'auth_method' => 'platform',
                'success' => 1,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            log_activity(
                $userId,
                'mark_attendance',
                'attendance_records',
                (int)$attendanceId,
                "Marked attendance via biometric quick scan for student ID: {$studentId}, Class ID: {$classId}, Status: {$status}"
            );

            echo json_encode([
                'success' => true,
                'message' => 'Attendance marked successfully',
                'attendance_id' => (int)$attendanceId,
                'status' => $status,
                'time' => $currentTime,
            ]);
            break;

        case 'get_recent_scans':
            $userTenantWhere = quick_scan_tenant_where('users', $tenantId, 'u');
            $logTenantWhere = quick_scan_tenant_where('biometric_auth_logs', $tenantId, 'bal');
            $scans = db()->fetchAll(
                "SELECT bal.*, u.full_name, u.role,
                        CASE
                            WHEN u.role = 'student' THEN s.admission_number
                            WHEN u.role = 'teacher' THEN t.teacher_id
                            ELSE NULL
                        END AS person_code
                 FROM biometric_auth_logs bal
                 JOIN users u ON bal.user_id = u.id
                 LEFT JOIN students s ON u.id = s.user_id
                 LEFT JOIN teachers t ON u.id = t.user_id
                 WHERE bal.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                   {$logTenantWhere['sql']}{$userTenantWhere['sql']}
                 ORDER BY bal.created_at DESC
                 LIMIT 50",
                array_merge($logTenantWhere['params'], $userTenantWhere['params'])
            ) ?: [];

            echo json_encode([
                'success' => true,
                'scans' => $scans,
            ]);
            break;

        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}

function quick_scan_student_context(string $credentialId, int $tenantId): ?array
{
    $userTenantWhere = quick_scan_tenant_where('users', $tenantId, 'u');
    $studentTenantWhere = quick_scan_tenant_where('students', $tenantId, 's');
    $credentialTenantWhere = quick_scan_tenant_where('biometric_credentials', $tenantId, 'bc');

    $sql = "SELECT bc.*, u.id AS user_id,
                   COALESCE(u.full_name, TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')))) AS full_name,
                   u.role,
                   s.id AS student_id,
                   s.admission_number AS student_code
            FROM biometric_credentials bc
            JOIN users u ON bc.user_id = u.id
            LEFT JOIN students s ON u.id = s.user_id
            WHERE bc.credential_id = ? AND bc.status = 'active'
              {$credentialTenantWhere['sql']}{$userTenantWhere['sql']}{$studentTenantWhere['sql']}
            LIMIT 1";

    $params = array_merge([$credentialId], $credentialTenantWhere['params'], $userTenantWhere['params'], $studentTenantWhere['params']);

    $row = db()->fetchOne($sql, $params);
    return $row ?: null;
}

function quick_scan_student_record(int $studentId, int $tenantId): ?array
{
    $tenantWhere = quick_scan_tenant_where('students', $tenantId);
    $row = db()->fetchOne(
        "SELECT * FROM students WHERE id = ?{$tenantWhere['sql']} LIMIT 1",
        array_merge([$studentId], $tenantWhere['params'])
    );

    return $row ?: null;
}

function quick_scan_class_record(int $classId, int $tenantId): ?array
{
    $tenantWhere = quick_scan_tenant_where('classes', $tenantId);
    $row = db()->fetchOne(
        "SELECT * FROM classes WHERE id = ?{$tenantWhere['sql']} LIMIT 1",
        array_merge([$classId], $tenantWhere['params'])
    );

    return $row ?: null;
}

function quick_scan_tenant_where(string $tableName, int $tenantId, string $alias = ''): array
{
    $prefix = $alias !== '' ? $alias . '.' : '';
    foreach (['tenant_id', 'school_id'] as $column) {
        if (table_has_column($tableName, $column)) {
            return [
                'sql' => " AND {$prefix}{$column} = ?",
                'params' => [$tenantId],
            ];
        }
    }

    return ['sql' => '', 'params' => []];
}

function quick_scan_tenant_payload(string $tableName, int $tenantId): array
{
    $payload = [];
    foreach (['tenant_id', 'school_id'] as $column) {
        if (table_has_column($tableName, $column)) {
            $payload[$column] = $tenantId;
        }
    }

    return $payload;
}

function quick_scan_attendance_date_expression(string $alias = ''): string
{
    $prefix = $alias !== '' ? $alias . '.' : '';
    if (table_has_column('attendance_records', 'attendance_date')) {
        return 'DATE(' . $prefix . 'attendance_date)';
    }

    return 'DATE(' . $prefix . 'check_in_time)';
}
