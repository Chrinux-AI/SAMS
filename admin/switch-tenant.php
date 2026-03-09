<?php

/**
 * Switch Tenant Context - Allow super admin to manage different schools
 */

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_once '../includes/sams-multi-tenant.php';

// Only admins can switch tenants
$_user_role = $_SESSION['role'] ?? $_SESSION['user_role'] ?? '';
if (!in_array($_user_role, ['admin', 'super_admin', 'superadmin', 'owner'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $tenant_id = $data['tenant_id'] ?? '';

    if (empty($tenant_id)) {
        echo json_encode(['success' => false, 'error' => 'Tenant ID required']);
        exit;
    }

    try {
        $tenant_manager = new SAMS_MultiTenant();

        // Switch tenant context
        $success = $tenant_manager->switchTenant($tenant_id);

        if ($success) {
            // Get tenant details for response
            $tenant = db()->fetchOne("SELECT * FROM tenants WHERE id = ?", [$tenant_id]);

            echo json_encode([
                'success' => true,
                'message' => "Switched to {$tenant['institution_name']}",
                'tenant_name' => $tenant['institution_name'],
                'tenant_id' => $tenant_id
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to switch tenant']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
}
