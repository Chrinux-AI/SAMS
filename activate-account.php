<?php
/**
 * Activation link bridge.
 * Supports:
 * - Email verification tokens -> verify-email.php
 * - OTP confirmation tokens (CONFIRM:...) -> confirm-account.php
 */

require_once 'frontend/includes/config.php';
require_once 'frontend/includes/functions.php';
require_once 'frontend/includes/database.php';

$token = trim((string)($_GET['token'] ?? ''));
if ($token === '') {
    header('Location: login.php');
    exit;
}

try {
    // Legacy/registration verification token flow
    if (table_has_column('users', 'email_verification_token')) {
        $u = db()->fetchOne(
            "SELECT id FROM users WHERE email_verification_token = ? LIMIT 1",
            [$token]
        );
        if ($u) {
            header('Location: verify-email.php?token=' . urlencode($token));
            exit;
        }
    }

    // Activation token flow (if implemented in future)
    if (table_has_column('users', 'activation_token')) {
        $u = db()->fetchOne(
            "SELECT email FROM users WHERE activation_token = ? LIMIT 1",
            [$token]
        );
        if ($u && !empty($u['email'])) {
            header('Location: confirm-account.php?email=' . urlencode((string)$u['email']));
            exit;
        }
    }

    // OTP confirmation token flow stored in verification_token
    if (table_has_column('users', 'verification_token')) {
        $u = db()->fetchOne(
            "SELECT email FROM users WHERE verification_token = ? LIMIT 1",
            [$token]
        );
        if ($u && !empty($u['email'])) {
            header('Location: confirm-account.php?email=' . urlencode((string)$u['email']));
            exit;
        }
    }
} catch (Throwable $e) {
    error_log('activate-account error: ' . $e->getMessage());
}

header('Location: login.php');
exit;

