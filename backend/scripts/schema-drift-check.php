<?php
declare(strict_types=1);

/**
 * Schema drift checker for core tables.
 * Usage: php scripts/schema-drift-check.php
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';

$spec = [
    'users' => ['id', 'email', 'role', 'status', 'approved', 'email_verified'],
    'students' => ['id', 'user_id'],
    'teachers' => ['id', 'user_id'],
    'classes' => ['id'],
    'messages' => ['id', 'sender_id', 'subject', 'message'],
];

$hasFailures = false;

echo "SAMS Schema Drift Check\n";
echo "=======================\n\n";

foreach ($spec as $table => $requiredCols) {
    if (!table_exists($table)) {
        $hasFailures = true;
        echo "[MISSING TABLE] {$table}\n";
        continue;
    }

    $cols = db()->fetchAll("SHOW COLUMNS FROM {$table}");
    $present = [];
    foreach ($cols as $col) {
        $present[] = (string)$col['Field'];
    }

    $missingCols = array_values(array_diff($requiredCols, $present));
    if (!empty($missingCols)) {
        $hasFailures = true;
        echo "[DRIFT] {$table}\n";
        echo "  Missing required columns: " . implode(', ', $missingCols) . "\n";
    } else {
        echo "[OK] {$table}\n";
    }
}

echo "\n";
if ($hasFailures) {
    echo "Result: DRIFT DETECTED\n";
    exit(1);
}

echo "Result: NO CRITICAL DRIFT IN CORE TABLES\n";
exit(0);
