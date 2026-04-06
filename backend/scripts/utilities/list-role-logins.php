<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';

$users = db()->fetchAll("
    SELECT id, email, role, full_name, first_name, last_name, status, is_active, approved, email_verified
    FROM users
    ORDER BY role, email
");

if (!$users) {
    echo "No users found.\n";
    exit(0);
}

echo "id | email | role | name | status | is_active | approved | email_verified\n";
echo str_repeat('-', 110) . "\n";

foreach ($users as $u) {
    $name = trim((string)($u['full_name'] ?? ''));
    if ($name === '') {
        $name = trim(((string)($u['first_name'] ?? '')) . ' ' . ((string)($u['last_name'] ?? '')));
    }

    echo implode(' | ', [
        (string)($u['id'] ?? ''),
        (string)($u['email'] ?? ''),
        (string)($u['role'] ?? ''),
        $name,
        (string)($u['status'] ?? ''),
        isset($u['is_active']) ? (string)$u['is_active'] : '',
        isset($u['approved']) ? (string)$u['approved'] : '',
        isset($u['email_verified']) ? (string)$u['email_verified'] : '',
    ]) . "\n";
}

