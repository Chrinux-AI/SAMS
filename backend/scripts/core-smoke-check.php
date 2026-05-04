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
$projectRoot = defined('PROJECT_ROOT') ? PROJECT_ROOT : dirname(__DIR__, 2);

$criticalFiles = [
    $projectRoot . '/login.php',
    $projectRoot . '/confirm-account.php',

    // Launch-facing role pages (split frontend layout)
    $projectRoot . '/frontend/admin/dashboard.php',
    $projectRoot . '/frontend/admin/teachers.php',
    $projectRoot . '/frontend/admin/students.php',
    $projectRoot . '/frontend/admin/classes.php',
    $projectRoot . '/frontend/admin/approve-users.php',
    $projectRoot . '/frontend/admin/attendance.php',
    $projectRoot . '/frontend/teacher/attendance.php',
    $projectRoot . '/frontend/teacher/parent-comms.php',
    $projectRoot . '/frontend/student/notifications.php',
    $projectRoot . '/frontend/parent/dashboard.php',

    // Launch-critical backend/API surfaces
    $projectRoot . '/backend/api/health.php',
    $projectRoot . '/backend/communication/api/messages.php',
    $projectRoot . '/backend/api/notifications.php',
];

foreach ($criticalFiles as $file) {
    $checks[] = [
        'label' => 'FILE ' . str_replace($projectRoot . '/', '', $file),
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
