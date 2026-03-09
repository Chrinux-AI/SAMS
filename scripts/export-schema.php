<?php
declare(strict_types=1);

/**
 * Export current MySQL schema to database/schema.sql
 * Usage: php scripts/export-schema.php
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';

$outputPath = __DIR__ . '/../database/schema.sql';
$lines = [];
$lines[] = '-- SAMS schema snapshot';
$lines[] = '-- Generated at ' . date('c');
$lines[] = '-- Database: ' . DB_NAME;
$lines[] = '';
$lines[] = 'SET NAMES utf8mb4;';
$lines[] = 'SET FOREIGN_KEY_CHECKS = 0;';
$lines[] = '';

$tablesResult = db()->fetchAll('SHOW TABLES');
$tables = [];
foreach ($tablesResult as $row) {
    $tableName = array_values($row)[0] ?? null;
    if ($tableName) {
        $tables[] = (string)$tableName;
    }
}
sort($tables);

foreach ($tables as $table) {
    $createRow = db()->fetchOne("SHOW CREATE TABLE `{$table}`");
    if (!$createRow) {
        continue;
    }
    $createSql = $createRow['Create Table'] ?? '';
    if ($createSql === '') {
        continue;
    }

    $lines[] = '-- --------------------------------------------------------';
    $lines[] = '-- Table: ' . $table;
    $lines[] = '-- --------------------------------------------------------';
    $lines[] = "DROP TABLE IF EXISTS `{$table}`;";
    $lines[] = $createSql . ';';
    $lines[] = '';
}

$lines[] = 'SET FOREIGN_KEY_CHECKS = 1;';
$lines[] = '';

file_put_contents($outputPath, implode(PHP_EOL, $lines));

echo "Schema exported to: {$outputPath}\n";
echo 'Tables exported: ' . count($tables) . "\n";

