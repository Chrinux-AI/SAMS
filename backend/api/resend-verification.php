<?php

/**
 * Resend Verification Email API
 * Resends verification email to users who haven't verified their email
 */

// Start output buffering to prevent any premature output
ob_start();

session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';

// Require admin access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    ob_clean();
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
 * Resend verification email to a single user
 */
function resendVerificationEmail($user_id)
{
    try {
        // Get user details
        $user = db()->fetchOne("SELECT * FROM users WHERE id = ? AND email_verified = 0", [$user_id]);

        if (!$user) {
            return [
                'success' => false,
                'error' => 'User not found or already verified'
            ];
        }

        // Generate new verification token with 10-minute expiration
        $verification_token = bin2hex(random_bytes(32));
        $expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));

        // Update user with new token - try/catch for better error handling
        try {
            $updated = db()->update(
                'users',
                ['email_verification_token' => $verification_token, 'token_expires_at' => $expires_at],
                'id = ?',
                [$user_id]
            );

            // The update method returns false only on database error
            if ($updated === false) {
                throw new Exception('Database update failed');
            }
        } catch (Exception $e) {
            error_log("Token update error for user {$user_id}: " . $e->getMessage());
            throw new Exception('Failed to update verification token: ' . $e->getMessage());
        }

        // Send verification email
        $full_name = $user['first_name'] . ' ' . $user['last_name'];
        $email_sent = send_verification_email(
            $user['email'],
            $full_name,
            $verification_token
        );

        if (!$email_sent) {
            throw new Exception('Failed to send verification email. Please check email configuration.');
        }

        // Log activity
        log_activity(
            $_SESSION['user_id'],
            'resend_verification',
            'user',
            $user_id,
            "Resent verification email to: {$user['email']}"
        );

        return [
            'success' => true,
            'message' => 'Verification email sent successfully',
            'email' => $user['email']
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Bulk resend verification emails
 */
function bulkResendVerificationEmails($user_ids)
{
    $results = [
        'success' => true,
        'sent' => 0,
        'failed' => 0,
        'errors' => []
    ];

    foreach ($user_ids as $user_id) {
        $result = resendVerificationEmail($user_id);
        if ($result['success']) {
            $results['sent']++;
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

/**
 * Resend to all unverified users
 */
function resendToAllUnverified()
{
    // Get all unverified users
    $unverified_users = db()->fetchAll("SELECT id FROM users WHERE email_verified = 0");
    $user_ids = array_column($unverified_users, 'id');

    if (empty($user_ids)) {
        return [
            'success' => false,
            'error' => 'No unverified users found'
        ];
    }

    return bulkResendVerificationEmails($user_ids);
}

// Handle actions
switch ($action) {
    case 'resend_single':
        if (!isset($data['user_id'])) {
            echo json_encode(['success' => false, 'error' => 'User ID required']);
            exit;
        }

        $result = resendVerificationEmail($data['user_id']);
        echo json_encode($result);
        break;

    case 'resend_bulk':
        if (!isset($data['user_ids']) || !is_array($data['user_ids'])) {
            echo json_encode(['success' => false, 'error' => 'User IDs array required']);
            exit;
        }

        if (empty($data['user_ids'])) {
            echo json_encode(['success' => false, 'error' => 'No users selected']);
            exit;
        }

        $result = bulkResendVerificationEmails($data['user_ids']);
        echo json_encode($result);
        break;

    case 'resend_all':
        // Resend to all unverified users
        if (!isset($data['confirm']) || $data['confirm'] !== 'RESEND_ALL') {
            echo json_encode(['success' => false, 'error' => 'Confirmation required']);
            exit;
        }

        $result = resendToAllUnverified();
        echo json_encode($result);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        break;
}
