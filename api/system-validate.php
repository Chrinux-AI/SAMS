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

if (!is_logged_in() || !system_validate_is_admin_role((string)($_SESSION['user_role'] ?? ($_SESSION['role'] ?? '')))) {
  http_response_code(403);
  echo json_encode(['error' => 'Admin access required']);
  exit;
}

$userId = (int)($_SESSION['user_id'] ?? 0);
if (!isset($_SESSION['tenant_id']) && $userId > 0) {
  set_user_tenant_session($userId);
}

$tenantId = current_tenant_id();
if ($userId <= 0 || !$tenantId || !user_in_current_tenant($userId)) {
  http_response_code(403);
  echo json_encode(['error' => 'Tenant access denied']);
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
$required_tables = ['users', 'classes', 'students', 'class_schedules'];
$missing = [];
foreach ($required_tables as $t) {
  if (!table_exists($t)) {
    $missing[] = $t;
  }
}
$attendanceTables = [];
if (table_exists('attendance')) {
  $attendanceTables[] = 'attendance';
}
if (table_exists('attendance_records')) {
  $attendanceTables[] = 'attendance_records';
}
$results['tables'] = [
  'status' => empty($missing) && !empty($attendanceTables) ? 'ok' : 'warning',
  'total' => count($required_tables) + 1,
  'missing' => !empty($attendanceTables) ? $missing : array_merge($missing, ['attendance or attendance_records']),
  'attendance_tables' => $attendanceTables,
];

// 2b. Communication platform coverage
$communicationCoverage = system_validate_communication_coverage();
$results['communication'] = $communicationCoverage;

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

// 7. Tenant safety / scope coverage
$tenantSignals = [];
if (table_exists('tenant_users')) {
  $tenantSignals[] = 'tenant_users';
}
if (table_has_column('users', 'tenant_id')) {
  $tenantSignals[] = 'users.tenant_id';
} elseif (table_has_column('users', 'school_id')) {
  $tenantSignals[] = 'users.school_id';
}
$results['tenant_scope'] = [
  'status' => empty($tenantSignals) ? 'warning' : 'ok',
  'signals' => $tenantSignals,
  'active_tenant_id' => $tenantId,
  'detail' => empty($tenantSignals) ? 'No obvious tenant scope columns found on users' : 'Tenant scope markers detected',
];

// 8. Old messaging system remnants
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

function system_validate_is_admin_role(string $role): bool
{
  $role = str_replace('-', '_', strtolower(trim($role)));
  return in_array($role, ['admin', 'super_admin', 'superadmin', 'owner', 'principal', 'vice_principal', 'admin_officer'], true);
}

function system_validate_communication_coverage(): array
{
  $families = [
    'modern' => ['comm_conversations', 'comm_participants', 'comm_messages', 'comm_reads', 'comm_attachments', 'comm_typing'],
    'legacy' => ['conversation_messages', 'conversation_participants', 'typing_indicators'],
  ];

  $familyStatus = [];
  foreach ($families as $name => $tables) {
    $missing = [];
    foreach ($tables as $table) {
      if (!table_exists($table)) {
        $missing[] = $table;
      }
    }

    $familyStatus[$name] = [
      'status' => empty($missing) ? 'ok' : 'warning',
      'total' => count($tables),
      'missing' => $missing,
    ];
  }

  $selectedFamily = null;
  if (($familyStatus['modern']['status'] ?? 'warning') === 'ok') {
    $selectedFamily = 'modern';
  } elseif (($familyStatus['legacy']['status'] ?? 'warning') === 'ok') {
    $selectedFamily = 'legacy';
  }

  $status = $selectedFamily !== null ? 'ok' : 'warning';

  return [
    'status' => $status,
    'selected_family' => $selectedFamily,
    'families' => $familyStatus,
    'detail' => $selectedFamily !== null
      ? 'Communication tables are available via the ' . $selectedFamily . ' schema family'
      : 'No complete communication schema family was found',
  ];
}
