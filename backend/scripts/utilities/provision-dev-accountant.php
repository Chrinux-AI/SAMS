<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/database.php';

$email = 'dev.accountant@attendance.local';
$password = 'DevPass@2026';
$role = 'accountant';
$first = 'Dev';
$last = 'Accountant';
$fullName = trim($first . ' ' . $last);

$existing = db()->fetchOne('SELECT id FROM users WHERE email = ?', [$email]);
$payload = build_user_payload([
  'email' => $email,
  'password' => $password,
  'role' => $role,
  'first_name' => $first,
  'last_name' => $last,
  'full_name' => $fullName,
  'status' => 'active',
  'approved' => 1,
  'email_verified' => 1,
  'is_active' => 1,
]);

if ($existing) {
  update_flexible('users', $payload, 'id = ?', [(int)$existing['id']]);
  $userId = (int)$existing['id'];
  $state = 'updated';
} else {
  $userId = (int)insert_flexible('users', $payload);
  $state = 'created';
}

if ($userId > 0 && function_exists('attach_user_to_tenant')) {
  attach_user_to_tenant($userId, current_tenant_id());
}

$user = db()->fetchOne('SELECT id, email, role, status, approved, email_verified FROM users WHERE id = ?', [$userId]);

echo 'STATE=' . $state . PHP_EOL;
echo 'ID=' . ($user['id'] ?? 'n/a') . PHP_EOL;
echo 'EMAIL=' . ($user['email'] ?? 'n/a') . PHP_EOL;
echo 'ROLE=' . ($user['role'] ?? 'n/a') . PHP_EOL;
echo 'STATUS=' . ($user['status'] ?? 'n/a') . PHP_EOL;
echo 'APPROVED=' . ($user['approved'] ?? 'n/a') . PHP_EOL;
echo 'EMAIL_VERIFIED=' . ($user['email_verified'] ?? 'n/a') . PHP_EOL;
