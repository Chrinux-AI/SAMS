<?php

/**
 * Role data API (admin)
 * Returns role details + assigned permission IDs for role edit UI.
 */

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
  http_response_code(405);
  header('Content-Type: application/json');
  echo json_encode(['error' => 'Method not allowed']);
  exit;
}

$_user_role = $_SESSION['role'] ?? $_SESSION['user_role'] ?? '';
if (!in_array($_user_role, ['admin', 'super_admin', 'superadmin', 'owner'], true)) {
  http_response_code(403);
  header('Content-Type: application/json');
  echo json_encode(['error' => 'Forbidden']);
  exit;
}

header('Content-Type: application/json');

if (!table_exists('system_roles') || !table_exists('role_permissions')) {
  http_response_code(503);
  echo json_encode(['error' => 'Role data unavailable']);
  exit;
}

$roleId = (int)($_GET['id'] ?? 0);
if ($roleId <= 0) {
  http_response_code(400);
  echo json_encode(['error' => 'Invalid role id']);
  exit;
}

$role = db()->fetchOne(
  'SELECT id, role_name, display_name, description, hierarchy_level, is_active FROM system_roles WHERE id = ?',
  [$roleId]
);

if (!$role) {
  http_response_code(404);
  echo json_encode(['error' => 'Role not found']);
  exit;
}

$permissions = db()->fetchAll(
  'SELECT permission_id FROM role_permissions WHERE role_id = ? ORDER BY permission_id ASC',
  [$roleId]
) ?: [];

$permissionIds = array_map(static fn($p) => (int)$p['permission_id'], $permissions);

echo json_encode([
  'id' => (int)$role['id'],
  'role_name' => (string)$role['role_name'],
  'display_name' => (string)$role['display_name'],
  'description' => (string)($role['description'] ?? ''),
  'hierarchy_level' => (int)$role['hierarchy_level'],
  'is_active' => (int)$role['is_active'],
  'permissions' => $permissionIds,
]);
