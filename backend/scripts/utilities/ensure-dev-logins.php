<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/config.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/includes/database.php';

$defaultPassword = 'DevPass@2026';
$roles = [
    'admin' => ['email' => 'dev.admin@attendance.local', 'first_name' => 'Dev', 'last_name' => 'Admin'],
    'owner' => ['email' => 'dev.owner@attendance.local', 'first_name' => 'Dev', 'last_name' => 'Owner'],
    'principal' => ['email' => 'dev.principal@attendance.local', 'first_name' => 'Dev', 'last_name' => 'Principal'],
    'teacher' => ['email' => 'dev.teacher@attendance.local', 'first_name' => 'Dev', 'last_name' => 'Teacher'],
    'student' => ['email' => 'dev.student@attendance.local', 'first_name' => 'Dev', 'last_name' => 'Student'],
    'parent' => ['email' => 'dev.parent@attendance.local', 'first_name' => 'Dev', 'last_name' => 'Parent'],
    'bursar' => ['email' => 'dev.bursar@attendance.local', 'first_name' => 'Dev', 'last_name' => 'Bursar'],
    'accountant' => ['email' => 'dev.accountant@attendance.local', 'first_name' => 'Dev', 'last_name' => 'Accountant'],
    'librarian' => ['email' => 'dev.librarian@attendance.local', 'first_name' => 'Dev', 'last_name' => 'Librarian'],
    'transport' => ['email' => 'dev.transport@attendance.local', 'first_name' => 'Dev', 'last_name' => 'Transport'],
    'staff' => ['email' => 'dev.staff@attendance.local', 'first_name' => 'Dev', 'last_name' => 'Staff'],
    'forum_moderator' => ['email' => 'dev.forum@attendance.local', 'first_name' => 'Dev', 'last_name' => 'Moderator'],
];

function parse_enum_values(string $enumDefinition): array
{
    if (!preg_match("/^enum\\((.*)\\)$/i", trim($enumDefinition), $matches)) {
        return [];
    }

    preg_match_all("/'((?:[^'\\\\]|\\\\.)*)'/", $matches[1], $valueMatches);
    return array_map(
        static fn(string $value): string => str_replace("\\'", "'", $value),
        $valueMatches[1] ?? []
    );
}

function ensure_user_role_enum_support(array $requiredRoles): void
{
    if (!table_exists('users')) {
        return;
    }

    $column = db()->fetchOne("SHOW COLUMNS FROM users LIKE 'role'");
    $definition = (string)($column['Type'] ?? '');
    if ($definition === '' || stripos($definition, 'enum(') !== 0) {
        return;
    }

    $existingRoles = parse_enum_values($definition);
    $missingRoles = array_values(array_diff($requiredRoles, $existingRoles));
    if (empty($missingRoles)) {
        return;
    }

    $allRoles = array_values(array_unique(array_merge($existingRoles, $missingRoles)));
    $quotedRoles = array_map(
        static fn(string $role): string => "'" . str_replace("'", "\\'", $role) . "'",
        $allRoles
    );

    $nullClause = strtoupper((string)($column['Null'] ?? 'NO')) === 'YES' ? 'NULL' : 'NOT NULL';
    $defaultValue = (string)($column['Default'] ?? 'student');
    if (!in_array($defaultValue, $allRoles, true)) {
        $defaultValue = 'student';
    }

    $sql = sprintf(
        "ALTER TABLE users MODIFY role ENUM(%s) %s DEFAULT '%s'",
        implode(',', $quotedRoles),
        $nullClause,
        str_replace("'", "\\'", $defaultValue)
    );

    db()->query($sql);
    echo 'updated users.role enum with missing roles: ' . implode(', ', $missingRoles) . PHP_EOL;
}

ensure_user_role_enum_support(array_keys($roles));

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
