<?php

/**
 * Verify Field API — Returns the current DB value for a single field.
 * Used by auto-sync.js consistency watchdog to detect stale UI.
 *
 * GET /api/verify-field.php?table=classes&id=6&field=schedule
 */
require_once __DIR__ . '/../includes/config.php';
require_once INCLUDES_PATH . '/functions.php';

header('Content-Type: application/json');

// Must be authenticated
if (empty($_SESSION['user_id'])) {
  http_response_code(401);
  echo json_encode(['error' => 'Unauthorized']);
  exit;
}

$table = $_GET['table'] ?? '';
$id    = (int)($_GET['id'] ?? 0);
$field = $_GET['field'] ?? '';

// Whitelist allowed tables to prevent information disclosure
$allowedTables = [
  'classes',
  'users',
  'attendance',
  'notices',
  'events',
  'class_enrollments',
  'grades',
  'assignments',
];
$safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
$safeField = preg_replace('/[^a-zA-Z0-9_]/', '', $field);

if (!in_array($safeTable, $allowedTables, true) || $id <= 0 || $safeField === '') {
  http_response_code(400);
  echo json_encode(['error' => 'Invalid request']);
  exit;
}

// Verify column exists
if (!table_has_column($safeTable, $safeField)) {
  echo json_encode(['value' => null, 'column_exists' => false]);
  exit;
}

try {
  $row = db()->fetchOne("SELECT `{$safeField}` FROM `{$safeTable}` WHERE id = ?", [$id]);
  echo json_encode([
    'value'         => $row[$safeField] ?? null,
    'column_exists' => true,
    'timestamp'     => date('Y-m-d H:i:s'),
  ]);
} catch (\Throwable $e) {
  http_response_code(500);
  echo json_encode(['error' => 'Query failed']);
}
