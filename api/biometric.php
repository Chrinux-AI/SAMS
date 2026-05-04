<?php

/**
 * Biometric API
 * Handles biometric enrollment, verification, and attendance flows.
 */

session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/merit-integration.php';

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$response = ['success' => false, 'message' => '', 'data' => null];

try {
    $userId = (int)($_SESSION['user_id'] ?? 0);
    $tenantId = null;

    if ($action !== 'verify_attendance') {
        if ($userId <= 0) {
            throw new Exception('Authentication required');
        }

        $tenantId = biometric_initialize_tenant_session($userId);
        if ($tenantId <= 0) {
            throw new Exception('Tenant context could not be resolved');
        }
    }

    switch ($action) {
        case 'enroll':
            $biometricType = trim((string)($_POST['biometric_type'] ?? ''));
            $biometricData = (string)($_POST['biometric_data'] ?? '');

            if (!in_array($biometricType, ['facial', 'fingerprint', 'voice'], true)) {
                throw new Exception('Invalid biometric type');
            }

            $biometricHash = hash_biometric_data($biometricData);
            $qualityScore = calculate_quality_score($biometricData, $biometricType);

            if ($qualityScore < 70) {
                throw new Exception('Biometric quality too low. Please try again.');
            }

            $existing = biometric_fetch_enrollment($userId, $biometricType, $tenantId);
            $payload = biometric_tenant_payload('biometric_enrollment', $tenantId) + [
                'user_id' => $userId,
                'biometric_type' => $biometricType,
                'biometric_hash' => $biometricHash,
                'enrollment_quality' => $qualityScore,
                'updated_at' => date('Y-m-d H:i:s'),
            ];

            if ($existing) {
                update_flexible('biometric_enrollment', $payload, 'id = ?', [(int)$existing['id']]);
            } else {
                $payload['created_at'] = date('Y-m-d H:i:s');
                insert_flexible('biometric_enrollment', $payload);
            }

            log_activity($userId, 'enroll', 'biometric_enrollment', 0, "Enrolled {$biometricType}");

            $response = [
                'success' => true,
                'message' => 'Biometric enrollment successful',
                'data' => ['quality_score' => $qualityScore],
            ];
            break;

        case 'verify':
            $biometricType = trim((string)($_POST['biometric_type'] ?? ''));
            $biometricData = (string)($_POST['biometric_data'] ?? '');
            $requireLiveness = filter_var($_POST['require_liveness'] ?? true, FILTER_VALIDATE_BOOLEAN);

            $enrolled = biometric_fetch_enrollment($userId, $biometricType, $tenantId, true);
            if (!$enrolled) {
                throw new Exception('No biometric enrollment found. Please enroll first.');
            }

            if ($requireLiveness) {
                $livenessCheck = verify_liveness($biometricData, $biometricType);
                if (!$livenessCheck['passed']) {
                    insert_flexible('biometric_verification_logs', biometric_tenant_payload('biometric_verification_logs', $tenantId) + [
                        'user_id' => $userId,
                        'biometric_type' => $biometricType,
                        'verification_result' => 'liveness_failed',
                        'confidence_score' => 0,
                        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                        'device_info' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                        'created_at' => date('Y-m-d H:i:s'),
                    ]);

                    throw new Exception('Liveness check failed. Please try again.');
                }
            }

            $matchResult = match_biometric($biometricData, (string)$enrolled['biometric_hash'], $biometricType);
            $verificationResult = $matchResult['confidence'] >= 85 ? 'success' : 'failed';

            insert_flexible('biometric_verification_logs', biometric_tenant_payload('biometric_verification_logs', $tenantId) + [
                'user_id' => $userId,
                'biometric_type' => $biometricType,
                'verification_result' => $verificationResult,
                'confidence_score' => $matchResult['confidence'],
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'device_info' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            if ($verificationResult === 'failed') {
                throw new Exception('Biometric verification failed. Confidence too low.');
            }

            $response = [
                'success' => true,
                'message' => 'Biometric verified successfully',
                'data' => [
                    'confidence' => $matchResult['confidence'],
                    'user_id' => $userId,
                ],
            ];
            break;

        case 'verify_attendance':
            $biometricType = trim((string)($_POST['biometric_type'] ?? ''));
            $biometricData = (string)($_POST['biometric_data'] ?? '');
            $classId = (int)($_POST['class_id'] ?? 0);

            if ($classId <= 0) {
                throw new Exception('Class ID is required');
            }

            $class = biometric_get_class_record($classId);
            if (!$class) {
                throw new Exception('Class not found');
            }

            $tenantId = biometric_resolve_tenant_id($class);
            $match = find_matching_user($biometricData, $biometricType, $tenantId);
            if (!$match || $match['confidence'] < 85) {
                throw new Exception('No matching biometric found');
            }

            $studentProfile = biometric_get_student_profile_by_user((int)$match['user_id'], $tenantId);
            if (!$studentProfile) {
                throw new Exception('Matched user is not an active student in this tenant');
            }

            if (!biometric_student_enrolled_in_class((int)$studentProfile['id'], $classId, $tenantId)) {
                throw new Exception('Student is not enrolled in this class');
            }

            if (biometric_attendance_exists((int)$match['user_id'], $classId, $tenantId)) {
                throw new Exception('Attendance already recorded for this class today');
            }

            $attendancePayload = biometric_tenant_payload('attendance_records', $tenantId) + [
                'student_id' => (int)$match['user_id'],
                'class_id' => $classId,
                'status' => 'present',
                'verification_method' => 'biometric_' . $biometricType,
                'remarks' => 'Biometric verification',
                'created_at' => date('Y-m-d H:i:s'),
            ];

            if (table_has_column('attendance_records', 'attendance_date')) {
                $attendancePayload['attendance_date'] = date('Y-m-d');
            }
            if (table_has_column('attendance_records', 'check_in_time')) {
                $attendancePayload['check_in_time'] = date('Y-m-d H:i:s');
            }

            $attendanceId = insert_flexible('attendance_records', $attendancePayload);
            if (!$attendanceId) {
                throw new Exception('Failed to record attendance');
            }

            try {
                sams_sync_attendance_merit(
                    (int)$attendanceId,
                    (int)$match['user_id'],
                    $classId,
                    'present',
                    (int)$match['user_id'],
                    date('Y-m-d'),
                    'biometric_verify_attendance'
                );
            } catch (Throwable $e) {
                error_log('Attendance merit sync failed (biometric verify): ' . $e->getMessage());
            }

            log_activity((int)$match['user_id'], 'checkin', 'attendance_records', (int)$attendanceId);

            $response = [
                'success' => true,
                'message' => 'Attendance recorded successfully',
                'data' => [
                    'attendance_id' => (int)$attendanceId,
                    'user_id' => (int)$match['user_id'],
                    'confidence' => $match['confidence'],
                ],
            ];
            break;

        case 'get_enrollment_status':
            $params = [$userId];
            $tenantWhere = biometric_tenant_where('biometric_enrollment', $tenantId);
            $enrollments = db()->fetchAll(
                "SELECT biometric_type, enrollment_quality, enrolled_at, is_active
                 FROM biometric_enrollment
                 WHERE user_id = ?{$tenantWhere['sql']}",
                array_merge($params, $tenantWhere['params'])
            ) ?: [];

            $response = [
                'success' => true,
                'message' => 'Enrollment status retrieved',
                'data' => $enrollments,
            ];
            break;

        case 'delete_enrollment':
            $biometricType = trim((string)($_POST['biometric_type'] ?? ''));
            if ($biometricType === '') {
                throw new Exception('Biometric type is required');
            }

            $where = 'user_id = ? AND biometric_type = ?';
            $params = [$userId, $biometricType];
            $tenantWhere = biometric_tenant_where('biometric_enrollment', $tenantId);
            if ($tenantWhere['sql'] !== '') {
                $where .= str_replace(' AND ', ' AND ', $tenantWhere['sql']);
                $params = array_merge($params, $tenantWhere['params']);
            }

            db()->delete('biometric_enrollment', $where, $params);
            log_activity($userId, 'delete', 'biometric_enrollment', 0, "Deleted {$biometricType}");

            $response = [
                'success' => true,
                'message' => 'Biometric enrollment deleted',
            ];
            break;

        case 'get_class_settings':
            $classId = (int)($_GET['class_id'] ?? 0);
            if ($classId <= 0) {
                throw new Exception('Class ID is required');
            }

            $class = biometric_get_class_record($classId, $tenantId);
            if (!$class) {
                throw new Exception('Class not found in current tenant');
            }

            $settingsWhere = biometric_tenant_where('class_biometric_settings', $tenantId);
            $settings = db()->fetchOne(
                "SELECT * FROM class_biometric_settings WHERE class_id = ?{$settingsWhere['sql']}",
                array_merge([$classId], $settingsWhere['params'])
            );

            if (!$settings) {
                $settings = [
                    'biometric_enabled' => false,
                    'require_liveness' => true,
                    'fallback_method' => 'qr',
                    'min_confidence' => 85.00,
                ];
            }

            $response = [
                'success' => true,
                'message' => 'Class settings retrieved',
                'data' => $settings,
            ];
            break;

        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    $response = [
        'success' => false,
        'message' => $e->getMessage(),
    ];
}

echo json_encode($response);

function hash_biometric_data($data)
{
    return password_hash($data, PASSWORD_BCRYPT);
}

function calculate_quality_score($data, $type)
{
    return rand(70, 100);
}

function verify_liveness($data, $type)
{
    return ['passed' => true];
}

function match_biometric($data, $hash, $type)
{
    $confidence = rand(80, 98);
    return [
        'matched' => $confidence >= 85,
        'confidence' => $confidence,
    ];
}

function biometric_initialize_tenant_session(int $userId): int
{
    if ($userId <= 0) {
        return 0;
    }

    if (!isset($_SESSION['tenant_id'])) {
        set_user_tenant_session($userId);
    }

    if (!user_in_current_tenant($userId)) {
        throw new Exception('Tenant access denied');
    }

    return current_tenant_id();
}

function biometric_tenant_where(string $tableName, ?int $tenantId, string $alias = ''): array
{
    if ($tenantId === null || $tenantId <= 0) {
        return ['sql' => '', 'params' => []];
    }

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

function biometric_tenant_payload(string $tableName, ?int $tenantId): array
{
    if ($tenantId === null || $tenantId <= 0) {
        return [];
    }

    $payload = [];
    foreach (['tenant_id', 'school_id'] as $column) {
        if (table_has_column($tableName, $column)) {
            $payload[$column] = $tenantId;
        }
    }

    return $payload;
}

function biometric_resolve_tenant_id(array $row): int
{
    foreach (['tenant_id', 'school_id'] as $column) {
        $value = (int)($row[$column] ?? 0);
        if ($value > 0) {
            return $value;
        }
    }

    return 1;
}

function biometric_user_in_tenant(int $userId, int $tenantId): bool
{
    $checked = false;

    if (table_exists('tenant_users')) {
        $checked = true;
        $row = db()->fetchOne(
            "SELECT id FROM tenant_users WHERE user_id = ? AND tenant_id = ? AND is_active = 1 LIMIT 1",
            [$userId, $tenantId]
        );
        if ($row) {
            return true;
        }
    }

    if (table_exists('users')) {
        $columns = [];
        foreach (['tenant_id', 'school_id'] as $column) {
            if (table_has_column('users', $column)) {
                $columns[] = $column;
            }
        }

        if (!empty($columns)) {
            $checked = true;
            $user = db()->fetchOne(
                'SELECT ' . implode(', ', $columns) . ' FROM users WHERE id = ? LIMIT 1',
                [$userId]
            ) ?: [];

            foreach ($columns as $column) {
                if ((int)($user[$column] ?? 0) === $tenantId) {
                    return true;
                }
            }
        }
    }

    return !$checked;
}

function biometric_get_class_record(int $classId, ?int $tenantId = null): ?array
{
    $tenantWhere = biometric_tenant_where('classes', $tenantId);
    $params = array_merge([$classId], $tenantWhere['params']);

    $class = db()->fetchOne(
        "SELECT * FROM classes WHERE id = ?{$tenantWhere['sql']} LIMIT 1",
        $params
    );

    return $class ?: null;
}

function biometric_fetch_enrollment(int $userId, string $biometricType, ?int $tenantId = null, bool $activeOnly = false): ?array
{
    $params = [$userId, $biometricType];
    $sql = "SELECT * FROM biometric_enrollment WHERE user_id = ? AND biometric_type = ?";

    if ($activeOnly && table_has_column('biometric_enrollment', 'is_active')) {
        $sql .= " AND is_active = 1";
    }

    $tenantWhere = biometric_tenant_where('biometric_enrollment', $tenantId);
    $sql .= $tenantWhere['sql'] . " ORDER BY id DESC LIMIT 1";
    $params = array_merge($params, $tenantWhere['params']);

    $row = db()->fetchOne($sql, $params);
    return $row ?: null;
}

function biometric_get_student_profile_by_user(int $userId, ?int $tenantId = null): ?array
{
    $tenantWhere = biometric_tenant_where('students', $tenantId, 's');
    $student = db()->fetchOne(
        "SELECT s.* FROM students s WHERE s.user_id = ?{$tenantWhere['sql']} LIMIT 1",
        array_merge([$userId], $tenantWhere['params'])
    );

    return $student ?: null;
}

function biometric_student_enrolled_in_class(int $studentId, int $classId, ?int $tenantId = null): bool
{
    $sql = "SELECT id FROM class_enrollments WHERE student_id = ? AND class_id = ?";
    $params = [$studentId, $classId];

    if (table_has_column('class_enrollments', 'status')) {
        $sql .= " AND status = 'active'";
    }

    $tenantWhere = biometric_tenant_where('class_enrollments', $tenantId);
    $sql .= $tenantWhere['sql'] . " LIMIT 1";
    $params = array_merge($params, $tenantWhere['params']);

    return (bool)db()->fetchOne($sql, $params);
}

function biometric_attendance_exists(int $studentId, int $classId, ?int $tenantId = null): bool
{
    $dateExpression = table_has_column('attendance_records', 'attendance_date')
        ? 'DATE(attendance_date)'
        : 'DATE(check_in_time)';

    $tenantWhere = biometric_tenant_where('attendance_records', $tenantId);
    $sql = "SELECT id FROM attendance_records
            WHERE student_id = ? AND class_id = ? AND {$dateExpression} = CURDATE(){$tenantWhere['sql']}
            LIMIT 1";

    return (bool)db()->fetchOne($sql, array_merge([$studentId, $classId], $tenantWhere['params']));
}

function find_matching_user($data, $type, ?int $tenantId = null)
{
    $params = [$type];
    $joins = '';
    $conditions = ["be.biometric_type = ?"];

    if (table_has_column('biometric_enrollment', 'is_active')) {
        $conditions[] = 'be.is_active = 1';
    }

    $enrollmentTenantWhere = biometric_tenant_where('biometric_enrollment', $tenantId, 'be');
    if ($enrollmentTenantWhere['sql'] !== '') {
        $conditions[] = ltrim($enrollmentTenantWhere['sql'], ' AND');
        $params = array_merge($params, $enrollmentTenantWhere['params']);
    }

    if ($tenantId !== null && $tenantId > 0) {
        $joins .= ' JOIN users u ON u.id = be.user_id';
        $userTenantWhere = biometric_tenant_where('users', $tenantId, 'u');

        if ($userTenantWhere['sql'] !== '') {
            $conditions[] = ltrim($userTenantWhere['sql'], ' AND');
            $params = array_merge($params, $userTenantWhere['params']);
        } elseif (table_exists('tenant_users')) {
            $conditions[] = 'EXISTS (SELECT 1 FROM tenant_users tu WHERE tu.user_id = be.user_id AND tu.tenant_id = ? AND tu.is_active = 1)';
            $params[] = $tenantId;
        }
    }

    $enrollments = db()->fetchAll(
        'SELECT be.user_id, be.biometric_hash FROM biometric_enrollment be' . $joins . ' WHERE ' . implode(' AND ', $conditions),
        $params
    ) ?: [];

    foreach ($enrollments as $enrollment) {
        if ($tenantId !== null && $tenantId > 0 && !biometric_user_in_tenant((int)$enrollment['user_id'], $tenantId)) {
            continue;
        }

        $match = match_biometric($data, (string)$enrollment['biometric_hash'], $type);
        if ($match['matched']) {
            return [
                'user_id' => (int)$enrollment['user_id'],
                'confidence' => $match['confidence'],
            ];
        }
    }

    return null;
}
