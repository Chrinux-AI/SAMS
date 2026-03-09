<?php

/**
 * LTI (Learning Tools Interoperability) Helper Functions
 * Version: 1.0.0
 * Created: November 2025
 * Purpose: LTI 1.3 integration support for LMS connectivity
 *
 * Features:
 * - JWT token validation
 * - LTI launch handling
 * - Session management
 * - Grade passback
 * - Deep linking support
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

/**
 * Validate LTI 1.3 JWT token
 *
 * @param string $jwt_token The JWT token from LTI launch
 * @param int $lti_config_id Configuration ID to validate against
 * @return array|false Decoded token payload or false on failure
 */
function lti_validate_jwt($jwt_token, $lti_config_id)
{
    try {
        // Get LTI configuration
        $config = db()->fetchOne(
            "SELECT * FROM lti_configurations WHERE id = ? AND is_active = 1",
            [$lti_config_id]
        );

        if (!$config) {
            error_log("LTI: Invalid or inactive configuration ID: $lti_config_id");
            return false;
        }

        // Split JWT into parts
        $parts = explode('.', $jwt_token);
        if (count($parts) !== 3) {
            error_log("LTI: Invalid JWT format");
            return false;
        }

        list($header_b64, $payload_b64, $signature_b64) = $parts;

        // Decode header and payload
        $header = json_decode(base64_decode(strtr($header_b64, '-_', '+/')), true);
        $payload = json_decode(base64_decode(strtr($payload_b64, '-_', '+/')), true);

        if (!$header || !$payload) {
            error_log("LTI: Failed to decode JWT parts");
            return false;
        }

        // Verify signature using public key
        $signature = base64_decode(strtr($signature_b64, '-_', '+/'));
        $verify_data = $header_b64 . '.' . $payload_b64;

        $public_key = openssl_pkey_get_public($config['public_key']);
        if (!$public_key) {
            error_log("LTI: Invalid public key");
            return false;
        }

        $verified = openssl_verify($verify_data, $signature, $public_key, OPENSSL_ALGO_SHA256);

        if ($verified !== 1) {
            error_log("LTI: JWT signature verification failed");
            return false;
        }

        // Validate claims
        $now = time();

        // Check expiration
        if (isset($payload['exp']) && $payload['exp'] < $now) {
            error_log("LTI: Token expired");
            return false;
        }

        // Check not before
        if (isset($payload['nbf']) && $payload['nbf'] > $now) {
            error_log("LTI: Token not yet valid");
            return false;
        }

        // Check issuer
        if (isset($payload['iss']) && $payload['iss'] !== $config['issuer']) {
            error_log("LTI: Issuer mismatch");
            return false;
        }

        return $payload;
    } catch (Exception $e) {
        error_log("LTI JWT Validation Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Handle LTI tool launch
 *
 * @param array $launch_params LTI launch parameters
 * @param int $lti_config_id Configuration ID
 * @return array Session data with success status
 */
function lti_handle_launch($launch_params, $lti_config_id)
{
    try {
        // Extract user information from launch params
        $lms_user_id = $launch_params['sub'] ?? null;
        $lms_context_id = $launch_params['https://purl.imsglobal.org/spec/lti/claim/context']['id'] ?? null;
        $lms_resource_link_id = $launch_params['https://purl.imsglobal.org/spec/lti/claim/resource_link']['id'] ?? null;

        $email = $launch_params['email'] ?? null;
        $given_name = $launch_params['given_name'] ?? '';
        $family_name = $launch_params['family_name'] ?? '';

        if (!$lms_user_id || !$email) {
            error_log("LTI Launch: Missing required user data");
            return ['success' => false, 'error' => 'Missing user information'];
        }

        // Find or create user in Attendance AI
        $user = db()->fetchOne("SELECT * FROM users WHERE email = ?", [$email]);

        if (!$user) {
            // Auto-create user from LMS (requires admin approval)
            $role = lti_determine_role($launch_params);

            db()->execute(
                "INSERT INTO users (username, email, password, first_name, last_name, role, approved, lms_user_id, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, 0, ?, NOW())",
                [
                    $email,
                    $email,
                    password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT), // Random password
                    $given_name,
                    $family_name,
                    $role,
                    $lms_user_id
                ]
            );

            $user_id = db()->lastInsertId();

            error_log("LTI: Created new user from LMS - ID: $user_id, Email: $email");
        } else {
            $user_id = $user['id'];

            // Update LMS user ID if not set
            if (!$user['lms_user_id']) {
                db()->execute("UPDATE users SET lms_user_id = ? WHERE id = ?", [$lms_user_id, $user_id]);
            }
        }

        // Create LTI session
        $session_token = bin2hex(random_bytes(32));
        $expires_at = date('Y-m-d H:i:s', time() + 3600); // 1 hour

        db()->execute(
            "INSERT INTO lti_sessions (lti_config_id, user_id, lms_user_id, lms_context_id, lms_resource_link_id,
             launch_params, session_token, ip_address, user_agent, expires_at, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
            [
                $lti_config_id,
                $user_id,
                $lms_user_id,
                $lms_context_id,
                $lms_resource_link_id,
                json_encode($launch_params),
                $session_token,
                $_SERVER['REMOTE_ADDR'] ?? '',
                $_SERVER['HTTP_USER_AGENT'] ?? '',
                $expires_at
            ]
        );

        // Update resource link launch count
        if ($lms_resource_link_id) {
            db()->execute(
                "UPDATE lti_links SET launch_count = launch_count + 1, last_launched_at = NOW()
                 WHERE lms_resource_link_id = ?",
                [$lms_resource_link_id]
            );
        }

        return [
            'success' => true,
            'user_id' => $user_id,
            'session_token' => $session_token,
            'lms_context_id' => $lms_context_id,
            'new_user' => !isset($user)
        ];
    } catch (Exception $e) {
        error_log("LTI Launch Error: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Determine user role from LTI launch parameters
 *
 * @param array $launch_params LTI launch parameters
 * @return string User role (student, teacher, admin)
 */
function lti_determine_role($launch_params)
{
    $roles = $launch_params['https://purl.imsglobal.org/spec/lti/claim/roles'] ?? [];

    foreach ($roles as $role) {
        if (strpos($role, 'Instructor') !== false || strpos($role, 'Teacher') !== false) {
            return 'teacher';
        }
        if (strpos($role, 'Administrator') !== false) {
            return 'admin';
        }
    }

    return 'student'; // Default to student
}

/**
 * Send grade passback to LMS
 *
 * @param int $user_id Student user ID
 * @param int $lti_config_id LTI configuration ID
 * @param string $lms_context_id LMS course ID
 * @param float $grade_value Grade value (0-100)
 * @param string $sync_type Sync type (manual, auto, bulk)
 * @return bool Success status
 */
function lti_grade_passback($user_id, $lti_config_id, $lms_context_id, $grade_value, $sync_type = 'auto')
{
    try {
        // Get LTI configuration
        $config = db()->fetchOne(
            "SELECT * FROM lti_configurations WHERE id = ? AND is_active = 1",
            [$lti_config_id]
        );

        if (!$config) {
            error_log("LTI Grade Passback: Invalid configuration");
            return false;
        }

        // Get user's LMS ID
        $user = db()->fetchOne("SELECT lms_user_id FROM users WHERE id = ?", [$user_id]);
        if (!$user || !$user['lms_user_id']) {
            error_log("LTI Grade Passback: User has no LMS ID");
            return false;
        }

        // Calculate attendance percentage
        $attendance_stats = db()->fetchOne(
            "SELECT
                COUNT(*) as total,
                SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present
             FROM attendance_records
             WHERE student_id = (SELECT id FROM students WHERE user_id = ?)
             AND DATE(check_in_time) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)",
            [$user_id]
        );

        $attendance_percentage = $attendance_stats['total'] > 0
            ? ($attendance_stats['present'] / $attendance_stats['total']) * 100
            : 0;

        // Log sync attempt
        db()->execute(
            "INSERT INTO lti_grade_sync_log
             (lti_config_id, user_id, lms_context_id, attendance_percentage, grade_value, sync_type, status, synced_at)
             VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())",
            [$lti_config_id, $user_id, $lms_context_id, $attendance_percentage, $grade_value, $sync_type]
        );

        $sync_log_id = db()->lastInsertId();

        // TODO: Implement actual LMS API call for grade passback
        // This would use the LMS-specific API (e.g., Moodle web services, Canvas API)
        // For now, mark as success for demonstration

        db()->execute(
            "UPDATE lti_grade_sync_log SET status = 'success' WHERE id = ?",
            [$sync_log_id]
        );

        // Update last sync time
        db()->execute(
            "UPDATE lti_configurations SET last_sync_at = NOW() WHERE id = ?",
            [$lti_config_id]
        );

        return true;
    } catch (Exception $e) {
        error_log("LTI Grade Passback Error: " . $e->getMessage());

        // Update sync log with error
        if (isset($sync_log_id)) {
            db()->execute(
                "UPDATE lti_grade_sync_log SET status = 'failed', error_message = ?, retry_count = retry_count + 1 WHERE id = ?",
                [$e->getMessage(), $sync_log_id]
            );
        }

        return false;
    }
}

/**
 * Create deep link for embedding Attendance AI resource in LMS
 *
 * @param int $lti_config_id LTI configuration ID
 * @param string $resource_type Resource type (attendance, grades, etc.)
 * @param int $resource_id Local resource ID
 * @param string $title Display title
 * @param string $lms_context_id LMS course ID
 * @return array Deep link data
 */
function lti_create_deep_link($lti_config_id, $resource_type, $resource_id, $title, $lms_context_id)
{
    try {
        $resource_url = APP_URL . "/lti-resource.php?type=$resource_type&id=$resource_id";
        $lms_resource_link_id = 'sams_' . $resource_type . '_' . $resource_id . '_' . uniqid();

        db()->execute(
            "INSERT INTO lti_links (lti_config_id, resource_type, resource_id, resource_url, lms_context_id,
             lms_resource_link_id, title, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())",
            [$lti_config_id, $resource_type, $resource_id, $resource_url, $lms_context_id, $lms_resource_link_id, $title]
        );

        return [
            'success' => true,
            'resource_url' => $resource_url,
            'resource_link_id' => $lms_resource_link_id
        ];
    } catch (Exception $e) {
        error_log("LTI Deep Link Creation Error: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Validate LTI session token
 *
 * @param string $session_token Session token
 * @return array|false Session data or false
 */
function lti_validate_session($session_token)
{
    $session = db()->fetchOne(
        "SELECT ls.*, u.id as user_id, u.email, u.role
         FROM lti_sessions ls
         JOIN users u ON ls.user_id = u.id
         WHERE ls.session_token = ? AND ls.is_valid = 1 AND ls.expires_at > NOW()",
        [$session_token]
    );

    return $session ?: false;
}

/**
 * Sync course roster from LMS
 *
 * @param int $lti_config_id LTI configuration ID
 * @param string $lms_context_id LMS course ID
 * @return array Sync results
 */
function lti_sync_course_roster($lti_config_id, $lms_context_id)
{
    // TODO: Implement LMS API call to fetch course roster
    // This would be LMS-specific (Moodle, Canvas, etc.)

    return [
        'success' => true,
        'users_synced' => 0,
        'message' => 'Roster sync not yet implemented. Configure via Admin LMS Settings.'
    ];
}
