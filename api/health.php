<?php
/**
 * Lightweight health endpoint for runtime and DB checks.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/api-response.php';
require_once __DIR__ . '/../includes/system-log.php';

$startedAt = microtime(true);

$checks = [
    'php' => [
        'ok' => true,
        'version' => PHP_VERSION
    ],
    'db' => [
        'ok' => false,
        'error' => null
    ],
    'tables' => [
        'users' => false,
        'classes' => false,
        'students' => false,
        'teachers' => false,
        'class_enrollments' => false,
        'account_activations' => false
    ],
    'disk' => [
        'ok' => true,
        'logs_writable' => is_writable(BASE_PATH . '/logs'),
        'cache_writable' => is_writable(BASE_PATH . '/cache')
    ],
    'session' => [
        'ok' => session_status() === PHP_SESSION_ACTIVE
    ]
];

try {
    $ping = db()->fetchOne('SELECT 1 AS ok');
    $checks['db']['ok'] = (bool)($ping['ok'] ?? false);
} catch (Throwable $e) {
    $checks['db']['ok'] = false;
    $checks['db']['error'] = $e->getMessage();
}

foreach (array_keys($checks['tables']) as $table) {
    $checks['tables'][$table] = table_exists($table);
}

$healthy = $checks['db']['ok'] && !in_array(false, $checks['tables'], true)
    && $checks['disk']['logs_writable'] && $checks['disk']['cache_writable'];
$durationMs = (int)round((microtime(true) - $startedAt) * 1000);

if (!$healthy) {
    system_log('WARNING', 'Health check degraded', ['checks' => $checks]);
}

api_json_response([
    'success' => true,
    'status' => $healthy ? 'healthy' : 'degraded',
    'timestamp' => date('c'),
    'duration_ms' => $durationMs,
    'checks' => $checks
], $healthy ? 200 : 503);
