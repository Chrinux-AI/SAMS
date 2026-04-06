<?php

/**
 * Biometric Authentication API
 * Handles WebAuthn fingerprint/face authentication
 */

session_start();
require_once '../includes/config.php';
require_once '../includes/database.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'register_start':
        registerStart();
        break;

    case 'register_complete':
        registerComplete();
        break;

    case 'login_start':
        loginStart();
        break;

    case 'login_complete':
        loginComplete();
        break;

    case 'list_credentials':
        listCredentials();
        break;

    case 'delete_credential':
        deleteCredential();
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}

/**
 * Start biometric registration
 */
function registerStart()
{
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'error' => 'Not authenticated']);
        return;
    }

    $user_id = $_SESSION['user_id'];
    $user = db()->fetchOne("SELECT * FROM users WHERE id = ?", [$user_id]);

    if (!$user) {
        echo json_encode(['success' => false, 'error' => 'User not found']);
        return;
    }

    // Verify session role matches user role
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== $user['role']) {
        echo json_encode(['success' => false, 'error' => 'Session role mismatch. Please logout and login again.']);
        return;
    }

    // Generate challenge
    $challenge = bin2hex(random_bytes(32));
    $_SESSION['biometric_challenge'] = $challenge;
    $_SESSION['biometric_register_role'] = $user['role']; // Store role for verification

    echo json_encode([
        'success' => true,
        'challenge' => $challenge,
        'user' => [
            'id' => base64_encode($user['id']),
            'name' => $user['email'],
            'displayName' => $user['first_name'] . ' ' . $user['last_name']
        ],
        'role' => $user['role']
    ]);
}

/**
 * Complete biometric registration
 */
function registerComplete()
{
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'error' => 'Not authenticated']);
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['credential_id']) || !isset($input['public_key'])) {
        echo json_encode(['success' => false, 'error' => 'Missing credential data']);
        return;
    }

    // Verify user and role
    $user = db()->fetchOne("SELECT * FROM users WHERE id = ?", [$_SESSION['user_id']]);
    if (!$user) {
        echo json_encode(['success' => false, 'error' => 'User not found']);
        return;
    }

    // Verify role consistency
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== $user['role']) {
        echo json_encode(['success' => false, 'error' => 'Role verification failed']);
        return;
    }

    try {
        $data = [
            'user_id' => $_SESSION['user_id'],
            'credential_id' => $input['credential_id'],
            'public_key' => $input['public_key'],
            'counter' => $input['counter'] ?? 0,
            'device_name' => $input['device_name'] ?? 'Biometric Device',
            'user_role' => $user['role'], // Store role with credential
            'created_at' => date('Y-m-d H:i:s')
        ];

        db()->insert('biometric_credentials', $data);

        // Log the registration
        logBiometricAuth($_SESSION['user_id'], $input['credential_id'], 'fingerprint', 'success');

        unset($_SESSION['biometric_challenge']);

        echo json_encode(['success' => true, 'message' => 'Biometric credential registered successfully']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * Start biometric login
 */
function loginStart()
{
    // Determine expected role from current context (URL path)
    $expected_role = null;
    $request_uri = $_SERVER['REQUEST_URI'] ?? '';

    if (strpos($request_uri, '/admin/') !== false) {
        $expected_role = 'admin';
    } elseif (strpos($request_uri, '/teacher/') !== false) {
        $expected_role = 'teacher';
    } elseif (strpos($request_uri, '/student/') !== false) {
        $expected_role = 'student';
    } elseif (strpos($request_uri, '/parent/') !== false) {
        $expected_role = 'parent';
    }

    // Generate challenge for login
    $challenge = bin2hex(random_bytes(32));
    $_SESSION['biometric_challenge'] = $challenge;
    $_SESSION['biometric_expected_role'] = $expected_role; // Store expected role

    // Get credentials filtered by role if context is known
    if ($expected_role) {
        $credentials = db()->fetchAll(
            "SELECT DISTINCT bc.credential_id FROM biometric_credentials bc
             JOIN users u ON bc.user_id = u.id
             WHERE u.role = ?",
            [$expected_role]
        );
    } else {
        // Fallback: get all credentials
        $credentials = db()->fetchAll("SELECT DISTINCT credential_id FROM biometric_credentials");
    }

    $allowedCredentials = array_map(function ($c) {
        return ['id' => $c['credential_id'], 'type' => 'public-key'];
    }, $credentials);

    echo json_encode([
        'success' => true,
        'challenge' => $challenge,
        'allowedCredentials' => $allowedCredentials,
        'expectedRole' => $expected_role
    ]);
}

/**
 * Complete biometric login
 */
function loginComplete()
{
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['credential_id'])) {
        echo json_encode(['success' => false, 'error' => 'Missing credential ID']);
        return;
    }

    try {
        // Find user by credential
        $credential = db()->fetchOne(
            "SELECT bc.*, u.* FROM biometric_credentials bc
             JOIN users u ON bc.user_id = u.id
             WHERE bc.credential_id = ?",
            [$input['credential_id']]
        );

        if (!$credential) {
            logBiometricAuth(null, $input['credential_id'], 'fingerprint', 'failed');
            echo json_encode(['success' => false, 'error' => 'Invalid credential']);
            return;
        }

        // Verify role matches expected context
        if (isset($_SESSION['biometric_expected_role']) && $_SESSION['biometric_expected_role']) {
            if ($credential['role'] !== $_SESSION['biometric_expected_role']) {
                logBiometricAuth($credential['user_id'], $input['credential_id'], 'fingerprint', 'role_mismatch');
                echo json_encode([
                    'success' => false,
                    'error' => 'Role mismatch: This credential is registered for ' . $credential['role'] . ' but you are accessing ' . $_SESSION['biometric_expected_role'] . ' panel'
                ]);
                return;
            }
        }

        // Update counter and last used
        db()->query(
            "UPDATE biometric_credentials SET counter = counter + 1, last_used = NOW() WHERE credential_id = ?",
            [$input['credential_id']]
        );

        // Create session
        $_SESSION['user_id'] = $credential['user_id'];
        $_SESSION['email'] = $credential['email'];
        $_SESSION['full_name'] = $credential['first_name'] . ' ' . $credential['last_name'];
        $_SESSION['role'] = $credential['role'];
        $_SESSION['user_role'] = $credential['role'];  // For compatibility with has_role() function
        $_SESSION['biometric_auth'] = true;

        // Log successful auth
        logBiometricAuth($credential['user_id'], $input['credential_id'], 'fingerprint', 'success');

        // Determine redirect URL
        $redirect = 'student/dashboard.php';
        if ($credential['role'] === 'admin') {
            $redirect = 'admin/dashboard.php';
        } elseif ($credential['role'] === 'teacher') {
            $redirect = 'teacher/dashboard.php';
        } elseif ($credential['role'] === 'parent') {
            $redirect = 'parent/dashboard.php';
        }

        echo json_encode([
            'success' => true,
            'message' => 'Login successful',
            'redirect' => $redirect,
            'user' => [
                'name' => $_SESSION['full_name'],
                'role' => $credential['role']
            ]
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * List user's biometric credentials
 */
function listCredentials()
{
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'error' => 'Not authenticated']);
        return;
    }

    $credentials = db()->fetchAll(
        "SELECT id, credential_id, device_name, created_at, last_used FROM biometric_credentials WHERE user_id = ?",
        [$_SESSION['user_id']]
    );

    echo json_encode(['success' => true, 'credentials' => $credentials]);
}

/**
 * Delete biometric credential
 */
function deleteCredential()
{
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'error' => 'Not authenticated']);
        return;
    }

    $credential_id = $_POST['credential_id'] ?? '';

    if (empty($credential_id)) {
        echo json_encode(['success' => false, 'error' => 'Missing credential ID']);
        return;
    }

    db()->query(
        "DELETE FROM biometric_credentials WHERE user_id = ? AND credential_id = ?",
        [$_SESSION['user_id'], $credential_id]
    );

    echo json_encode(['success' => true, 'message' => 'Credential deleted']);
}

/**
 * Log biometric authentication attempt
 */
function logBiometricAuth($user_id, $credential_id, $type, $status)
{
    $data = [
        'user_id' => $user_id,
        'credential_id' => $credential_id,
        'auth_type' => $type,
        'status' => $status,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        'created_at' => date('Y-m-d H:i:s')
    ];

    db()->insert('biometric_auth_logs', $data);
}
