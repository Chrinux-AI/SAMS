<?php

/**
 * Session Heartbeat API
 * Called periodically by client JS to check session status and keep alive.
 * Returns session info including time remaining.
 */

header('Content-Type: application/json');
header('Cache-Control: no-store');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
  echo json_encode([
    'authenticated' => false,
    'message' => 'Not authenticated'
  ]);
  exit;
}

$role = $_SESSION['role'] ?? 'student';
$timeout = get_session_timeout();
$lastActivity = $_SESSION['last_activity'] ?? time();
$elapsed = time() - $lastActivity;
$remaining = max(0, $timeout - $elapsed);

// Update last activity (heartbeat keeps session alive)
$_SESSION['last_activity'] = time();

echo json_encode([
  'authenticated' => true,
  'user_id' => (int)$_SESSION['user_id'],
  'role' => $role,
  'timeout' => $timeout,
  'remaining' => $remaining,
  'warning' => $remaining <= 120 && $remaining > 0
]);
