<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/database.php';

$sqlFile = __DIR__ . '/database/migrations/create_multitenant_ai_tables.sql';
if (!file_exists($sqlFile)) {
    exit("Migration file not found: {$sqlFile}\n");
}

$sql = file_get_contents($sqlFile);
if ($sql === false) {
    exit("Unable to read migration file.\n");
}

$pdo = db()->getConnection();
try {
    $pdo->exec($sql);
    echo "Multi-tenant AI migration applied successfully.\n";
} catch (Throwable $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
?>
