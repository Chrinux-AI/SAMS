<?php

/**
 * SAMS System Validation API
 * Phase K: Continuous self-validation endpoint
 * Returns JSON health check of all critical system components.
 */
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/database.php';

header('Content-Type: application/json');

if (!is_logged_in() || ($_SESSION['role'] ?? '') !== 'admin') {
  http_response_code(403);
  echo json_encode(['error' => 'Admin access required']);
  exit;
}

$results = [];

// 1. Database connection
try {
  db()->fetchOne("SELECT 1 as ok");
  $results['database'] = ['status' => 'ok', 'detail' => 'Connected'];
} catch (Throwable $e) {
  $results['database'] = ['status' => 'error', 'detail' => $e->getMessage()];
}

// 2. Required tables
$required_tables = [
  'users',
  'classes',
  'attendance',
  'students',
  'comm_conversations',
  'comm_participants',
  'comm_messages',
  'comm_reads',
  'comm_attachments',
  'comm_typing',
  'class_schedules'
];
$missing = [];
foreach ($required_tables as $t) {
  if (!table_exists($t)) {
    $missing[] = $t;
  }
}
$results['tables'] = [
  'status' => empty($missing) ? 'ok' : 'warning',
  'total' => count($required_tables),
  'missing' => $missing
];

// 3. Core classes
$core_classes = ['ErrorHandler', 'AutoSyncEngine', 'DataConsistencyGuard', 'ClassRepository', 'ClassService', 'ClassController', 'AdminEditGuarantee'];
$missing_classes = [];
foreach ($core_classes as $cls) {
  if (!class_exists($cls)) {
    $missing_classes[] = $cls;
  }
}
$results['core_classes'] = [
  'status' => empty($missing_classes) ? 'ok' : 'warning',
  'loaded' => count($core_classes) - count($missing_classes),
  'missing' => $missing_classes
];

// 4. Session guard
$results['session_guard'] = [
  'status' => defined('SESSION_TIMEOUT_ADMIN') ? 'ok' : 'warning',
  'admin_timeout' => defined('SESSION_TIMEOUT_ADMIN') ? SESSION_TIMEOUT_ADMIN : 'not set',
  'staff_timeout' => defined('SESSION_TIMEOUT_STAFF') ? SESSION_TIMEOUT_STAFF : 'not set',
  'default_timeout' => defined('SESSION_TIMEOUT_DEFAULT') ? SESSION_TIMEOUT_DEFAULT : 'not set',
];

// 5. Required directories
$dirs = ['uploads', 'cache', 'logs', 'storage', 'communication/uploads'];
$dir_issues = [];
foreach ($dirs as $d) {
  $path = realpath(__DIR__ . '/../' . $d);
  if (!$path || !is_dir($path)) {
    $dir_issues[] = $d . ' (missing)';
  } elseif (!is_writable($path)) {
    $dir_issues[] = $d . ' (not writable)';
  }
}
$results['directories'] = [
  'status' => empty($dir_issues) ? 'ok' : 'warning',
  'issues' => $dir_issues
];

// 6. PHP version
$results['php'] = [
  'status' => version_compare(PHP_VERSION, '8.0.0', '>=') ? 'ok' : 'warning',
  'version' => PHP_VERSION
];

// 7. Old messaging system remnants
$old_dirs = ['messages', 'chat', 'inbox', 'conversation'];
$remnants = [];
foreach ($old_dirs as $od) {
  if (is_dir(__DIR__ . '/../' . $od)) {
    $remnants[] = $od;
  }
}
$results['old_messaging'] = [
  'status' => empty($remnants) ? 'ok' : 'warning',
  'detail' => empty($remnants) ? 'Clean — no old messaging directories' : 'Remnants found: ' . implode(', ', $remnants)
];

// Overall
$overall = 'ok';
foreach ($results as $r) {
  if ($r['status'] === 'error') {
    $overall = 'error';
    break;
  }
  if ($r['status'] === 'warning') {
    $overall = 'warning';
  }
}

echo json_encode([
  'overall' => $overall,
  'timestamp' => date('Y-m-d H:i:s'),
  'checks' => $results
], JSON_PRETTY_PRINT);
