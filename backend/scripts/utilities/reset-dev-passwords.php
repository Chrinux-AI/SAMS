<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/config.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/includes/database.php';

$password = 'DevPass@2026';
$passwordHash = password_hash($password, PASSWORD_DEFAULT);
$targetRoles = ['admin', 'teacher', 'student', 'parent'];

echo "role | email | password | status\n";
echo str_repeat('-', 80) . "\n";

foreach ($targetRoles as $role) {
    $user = db()->fetchOne('SELECT id, email FROM users WHERE role = ? ORDER BY id ASC LIMIT 1', [$role]);
    if (!$user) {
        echo $role . " | (none) | " . $password . " | skipped (no user)\n";
        continue;
    }

    $update = [
        'status' => 'active',
        'approved' => 1,
        'email_verified' => 1,
        'is_active' => 1
    ];
    if (table_has_column('users', 'password')) {
        $update['password'] = $passwordHash;
    }
    if (table_has_column('users', 'password_hash')) {
        $update['password_hash'] = $passwordHash;
    }

    update_flexible('users', $update, 'id = ?', [(int)$user['id']]);
    echo $role . ' | ' . $user['email'] . ' | ' . $password . " | updated\n";
}
