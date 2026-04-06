<?php

/**
 * Biometric API
 * Handles biometric enrollment, verification, and management
 */

session_start();
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$response = ['success' => false, 'message' => '', 'data' => null];

try {
    $user_id = $_SESSION['user_id'] ?? null;

    if (!$user_id && $action !== 'verify_attendance') {
        throw new Exception('Authentication required');
    }

    switch ($action) {
        case 'enroll':
            $biometric_type = $_POST['biometric_type'] ?? '';
            $biometric_data = $_POST['biometric_data'] ?? '';

            if (!in_array($biometric_type, ['facial', 'fingerprint', 'voice'])) {
                throw new Exception('Invalid biometric type');
            }

            // Hash the biometric data (in production, use specialized encryption)
            $biometric_hash = hash_biometric_data($biometric_data);

            // Calculate quality score
            $quality_score = calculate_quality_score($biometric_data, $biometric_type);

            if ($quality_score < 70) {
                throw new Exception('Biometric quality too low. Please try again.');
            }

            // Check if already enrolled
            $existing = db()->fetchOne(
                "SELECT id FROM biometric_enrollment WHERE user_id = ? AND biometric_type = ?",
                [$user_id, $biometric_type]
            );

            if ($existing) {
                // Update existing enrollment
                db()->update('biometric_enrollment', [
                    'biometric_hash' => $biometric_hash,
                    'enrollment_quality' => $quality_score
                ], 'id = ?', [$existing['id']]);
            } else {
                // New enrollment
                db()->insert('biometric_enrollment', [
                    'user_id' => $user_id,
                    'biometric_type' => $biometric_type,
                    'biometric_hash' => $biometric_hash,
                    'enrollment_quality' => $quality_score
                ]);
            }

            log_activity($user_id, 'enroll', 'biometric_enrollment', 0, "Enrolled $biometric_type");

            $response = [
                'success' => true,
                'message' => 'Biometric enrollment successful',
                'data' => ['quality_score' => $quality_score]
            ];
            break;

        case 'verify':
            $biometric_type = $_POST['biometric_type'] ?? '';
            $biometric_data = $_POST['biometric_data'] ?? '';
            $require_liveness = $_POST['require_liveness'] ?? true;

            // Get enrolled biometric
            $enrolled = db()->fetchOne(
                "SELECT * FROM biometric_enrollment
                 WHERE user_id = ? AND biometric_type = ? AND is_active = 1",
                [$user_id, $biometric_type]
            );

            if (!$enrolled) {
                throw new Exception('No biometric enrollment found. Please enroll first.');
            }

            // Verify liveness if required
            if ($require_liveness) {
                $liveness_check = verify_liveness($biometric_data, $biometric_type);
                if (!$liveness_check['passed']) {
                    // Log failed verification
                    db()->insert('biometric_verification_logs', [
                        'user_id' => $user_id,
                        'biometric_type' => $biometric_type,
                        'verification_result' => 'liveness_failed',
                        'confidence_score' => 0,
                        'ip_address' => $_SERVER['REMOTE_ADDR']
                    ]);

                    throw new Exception('Liveness check failed. Please try again.');
                }
            }

            // Match biometric data
            $match_result = match_biometric(
                $biometric_data,
                $enrolled['biometric_hash'],
                $biometric_type
            );

            $verification_result = $match_result['confidence'] >= 85 ? 'success' : 'failed';

            // Log verification attempt
            db()->insert('biometric_verification_logs', [
                'user_id' => $user_id,
                'biometric_type' => $biometric_type,
                'verification_result' => $verification_result,
                'confidence_score' => $match_result['confidence'],
                'ip_address' => $_SERVER['REMOTE_ADDR'],
                'device_info' => $_SERVER['HTTP_USER_AGENT']
            ]);

            if ($verification_result === 'failed') {
                throw new Exception('Biometric verification failed. Confidence too low.');
            }

            $response = [
                'success' => true,
                'message' => 'Biometric verified successfully',
                'data' => [
                    'confidence' => $match_result['confidence'],
                    'user_id' => $user_id
                ]
            ];
            break;

        case 'verify_attendance':
            // Public endpoint for attendance check-in with biometric
            $biometric_type = $_POST['biometric_type'] ?? '';
            $biometric_data = $_POST['biometric_data'] ?? '';
            $class_id = $_POST['class_id'] ?? null;

            // Find matching enrolled user
            $match = find_matching_user($biometric_data, $biometric_type);

            if (!$match || $match['confidence'] < 85) {
                throw new Exception('No matching biometric found');
            }

            // Record attendance
            $attendance_id = db()->insert('attendance_records', [
                'student_id' => $match['user_id'],
                'class_id' => $class_id,
                'check_in_time' => date('Y-m-d H:i:s'),
                'status' => 'present',
                'verification_method' => 'biometric_' . $biometric_type,
                'remarks' => 'Biometric verification'
            ]);

            log_activity($match['user_id'], 'checkin', 'attendance_records', $attendance_id);

            $response = [
                'success' => true,
                'message' => 'Attendance recorded successfully',
                'data' => [
                    'attendance_id' => $attendance_id,
                    'user_id' => $match['user_id'],
                    'confidence' => $match['confidence']
                ]
            ];
            break;

        case 'get_enrollment_status':
            $enrollments = db()->fetchAll(
                "SELECT biometric_type, enrollment_quality, enrolled_at, is_active
                 FROM biometric_enrollment WHERE user_id = ?",
                [$user_id]
            );

            $response = [
                'success' => true,
                'message' => 'Enrollment status retrieved',
                'data' => $enrollments
            ];
            break;

        case 'delete_enrollment':
            $biometric_type = $_POST['biometric_type'] ?? '';

            db()->delete(
                'biometric_enrollment',
                'user_id = ? AND biometric_type = ?',
                [$user_id, $biometric_type]
            );

            log_activity($user_id, 'delete', 'biometric_enrollment', 0, "Deleted $biometric_type");

            $response = [
                'success' => true,
                'message' => 'Biometric enrollment deleted'
            ];
            break;

        case 'get_class_settings':
            $class_id = $_GET['class_id'] ?? null;

            $settings = db()->fetchOne(
                "SELECT * FROM class_biometric_settings WHERE class_id = ?",
                [$class_id]
            );

            if (!$settings) {
                $settings = [
                    'biometric_enabled' => false,
                    'require_liveness' => true,
                    'fallback_method' => 'qr',
                    'min_confidence' => 85.00
                ];
            }

            $response = [
                'success' => true,
                'message' => 'Class settings retrieved',
                'data' => $settings
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
function hash_biometric_data($data)
{
    // In production, use specialized biometric hashing
    // This is a simplified version
    return password_hash($data, PASSWORD_BCRYPT);
}

function calculate_quality_score($data, $type)
{
    // Placeholder - implement actual quality assessment
    // Would check image resolution, clarity, etc.
    return rand(70, 100);
}

function verify_liveness($data, $type)
{
    // Placeholder - implement actual liveness detection
    // Would check for blinks, head movement, etc.
    return ['passed' => true];
}

function match_biometric($data, $hash, $type)
{
    // Placeholder - implement actual biometric matching
    // Would use specialized algorithms for each type
    $confidence = rand(80, 98);
    return [
        'matched' => $confidence >= 85,
        'confidence' => $confidence
    ];
}

function find_matching_user($data, $type)
{
    // Placeholder - search all enrolled biometrics for match
    // In production, use specialized matching algorithms
    $enrollments = db()->fetchAll(
        "SELECT user_id, biometric_hash FROM biometric_enrollment
         WHERE biometric_type = ? AND is_active = 1",
        [$type]
    );

    foreach ($enrollments as $enrollment) {
        $match = match_biometric($data, $enrollment['biometric_hash'], $type);
        if ($match['matched']) {
            return [
                'user_id' => $enrollment['user_id'],
                'confidence' => $match['confidence']
            ];
        }
    }

    return null;
}
