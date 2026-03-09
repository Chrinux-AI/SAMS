<?php

/**
 * Test Admin Login
 */

require_once 'includes/config.php';
require_once 'includes/database.php';

$email = 'christolabiyi35@gmail.com';
$password = 'Finekit@1410';

echo "Testing login for: $email\n";
echo "Password: $password\n\n";

// Fetch user
$user = db()->fetchOne(
    "SELECT * FROM users WHERE email = :email",
    ['email' => $email]
);

if (!$user) {
    echo "❌ User not found in database\n";
    exit(1);
}

echo "✓ User found\n";
echo "  ID: {$user['id']}\n";
echo "  Email: {$user['email']}\n";
echo "  Role: {$user['role']}\n";
echo "  Status: {$user['status']}\n";
echo "  Email Verified: {$user['email_verified']}\n";
echo "  Approved: {$user['approved']}\n\n";

// Verify password
if (!password_verify($password, $user['password_hash'])) {
    echo "❌ Password verification failed\n";
    exit(1);
}

echo "✓ Password verified\n\n";

// Check conditions
if ($user['email_verified'] == 0) {
    echo "❌ Email not verified\n";
    exit(1);
}

echo "✓ Email verified\n";

if ($user['approved'] == 0) {
    echo "❌ Account not approved\n";
    exit(1);
}

echo "✓ Account approved\n";

if ($user['status'] !== 'active') {
    echo "❌ Account status is: {$user['status']}\n";
    exit(1);
}

echo "✓ Account status is active\n\n";

// Determine redirect
$redirect = 'student/dashboard.php';
if ($user['role'] === 'admin') {
    $redirect = 'admin/dashboard.php';
} elseif ($user['role'] === 'teacher') {
    $redirect = 'teacher/dashboard.php';
} elseif ($user['role'] === 'parent') {
    $redirect = 'parent/dashboard.php';
}

echo "✅ Login would succeed!\n";
echo "Redirect to: $redirect\n";
