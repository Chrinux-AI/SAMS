<?php

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../modules/admin/AdminManager.php';

header('Content-Type: application/json');

$allowedRoles = ['admin', 'super_admin', 'superadmin', 'owner', 'principal', 'vice_principal', 'admin_officer'];
$sessionRole = strtolower((string)($_SESSION['user_role'] ?? ($_SESSION['role'] ?? '')));

if (!is_logged_in() || !in_array($sessionRole, $allowedRoles, true)) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized access. Admin privileges required.',
    ]);
    exit;
}

try {
    $manager = new AdminManager((int)($_SESSION['tenant_id'] ?? 1));
    echo json_encode([
        'success' => true,
        'data' => $manager->getDashboardPayload(),
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ]);
}
