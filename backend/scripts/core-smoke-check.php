<?php
declare(strict_types=1);

/**
 * Core smoke checks for SAMS stabilization.
 * Usage: php scripts/core-smoke-check.php
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';

$checks = [];

$criticalFiles = [
    BASE_PATH . '/login.php',
    BASE_PATH . '/forgot-password.php',
    BASE_PATH . '/confirm-account.php',
    BASE_PATH . '/admin/dashboard.php',
    BASE_PATH . '/admin/teachers.php',
    BASE_PATH . '/admin/students.php',
    BASE_PATH . '/admin/classes.php',
    BASE_PATH . '/admin/students-bulk-import.php',
    BASE_PATH . '/api/messaging.php',
    BASE_PATH . '/api/health.php',
];

foreach ($criticalFiles as $file) {
    $checks[] = [
        'label' => 'FILE ' . str_replace(BASE_PATH . '/', '', $file),
        'ok' => file_exists($file)
    ];
}

$coreTables = ['users', 'students', 'teachers', 'classes', 'messages', 'message_recipients'];
foreach ($coreTables as $table) {
    $checks[] = [
        'label' => 'TABLE ' . $table,
        'ok' => table_exists($table)
    ];
}

$dbOk = false;
try {
    $row = db()->fetchOne('SELECT 1 AS ok');
    $dbOk = (bool)($row['ok'] ?? false);
} catch (Throwable $e) {
    $dbOk = false;
}
$checks[] = ['label' => 'DB CONNECTION', 'ok' => $dbOk];

$failed = array_values(array_filter($checks, static fn($c) => !$c['ok']));

echo "SAMS Core Smoke Check\n";
echo "=====================\n";
foreach ($checks as $check) {
    echo ($check['ok'] ? '[OK]   ' : '[FAIL] ') . $check['label'] . PHP_EOL;
}

echo "\n";
if (!empty($failed)) {
    echo 'Result: FAILED (' . count($failed) . " issues)\n";
    exit(1);
}

echo "Result: PASSED\n";
exit(0);

