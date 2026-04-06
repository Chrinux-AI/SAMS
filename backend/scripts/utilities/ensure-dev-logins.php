<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/database.php';

$defaultPassword = 'DevPass@2026';
$roles = [
    'admin' => ['email' => 'dev.admin@attendance.local', 'first_name' => 'Dev', 'last_name' => 'Admin'],
    'teacher' => ['email' => 'dev.teacher@attendance.local', 'first_name' => 'Dev', 'last_name' => 'Teacher'],
    'student' => ['email' => 'dev.student@attendance.local', 'first_name' => 'Dev', 'last_name' => 'Student'],
    'parent' => ['email' => 'dev.parent@attendance.local', 'first_name' => 'Dev', 'last_name' => 'Parent'],
    'bursar' => ['email' => 'dev.bursar@attendance.local', 'first_name' => 'Dev', 'last_name' => 'Bursar'],
    'accountant' => ['email' => 'dev.accountant@attendance.local', 'first_name' => 'Dev', 'last_name' => 'Accountant'],
    'librarian' => ['email' => 'dev.librarian@attendance.local', 'first_name' => 'Dev', 'last_name' => 'Librarian'],
    'transport' => ['email' => 'dev.transport@attendance.local', 'first_name' => 'Dev', 'last_name' => 'Transport'],
    'forum_moderator' => ['email' => 'dev.forum@attendance.local', 'first_name' => 'Dev', 'last_name' => 'Moderator'],
];

echo "role | email | password | status\n";
echo str_repeat('-', 80) . "\n";

foreach ($roles as $role => $meta) {
    $email = $meta['email'];
    $first = $meta['first_name'];
    $last = $meta['last_name'];
    $fullName = trim($first . ' ' . $last);

    $existing = db()->fetchOne('SELECT id FROM users WHERE email = ?', [$email]);
    $payload = build_user_payload([
        'email' => $email,
        'password' => $defaultPassword,
        'role' => $role,
        'first_name' => $first,
        'last_name' => $last,
        'full_name' => $fullName,
        'status' => 'active',
        'approved' => 1,
        'email_verified' => 1,
        'is_active' => 1
    ]);

    if ($existing) {
        update_flexible('users', $payload, 'id = ?', [(int)$existing['id']]);
        $userId = (int)$existing['id'];
        $status = 'updated';
    } else {
        $userId = (int)insert_flexible('users', $payload);
        $status = 'created';
    }

    if ($userId > 0) {
        attach_user_to_tenant($userId, current_tenant_id());
    }

    echo $role . ' | ' . $email . ' | ' . $defaultPassword . ' | ' . $status . PHP_EOL;
}

