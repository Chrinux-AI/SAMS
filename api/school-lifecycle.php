<?php
session_start();

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/api-response.php';
require_once __DIR__ . '/../includes/advanced-sams.php';
require_once __DIR__ . '/../includes/tenant-context.php';

$action = trim((string) ($_REQUEST['action'] ?? 'tenant_context'));

try {
    switch ($action) {
        case 'register_school':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                api_error('Method not allowed', 405);
            }

            $result = AdvancedSAMS::createSchoolRegistration([
                'school_name' => trim((string) ($_POST['school_name'] ?? '')),
                'school_slug' => trim((string) ($_POST['school_slug'] ?? '')),
                'admin_email' => trim((string) ($_POST['admin_email'] ?? '')),
                'admin_first_name' => trim((string) ($_POST['admin_first_name'] ?? '')),
                'admin_last_name' => trim((string) ($_POST['admin_last_name'] ?? '')),
                'password' => (string) ($_POST['password'] ?? '')
            ]);

            api_success($result, 201);
            break;

        case 'create_invite':
            api_require_auth();
            [$accessAllowed, $accessMessage] = AdvancedSAMS::userCanAccess();
            if (!$accessAllowed) {
                api_error($accessMessage ?? 'Access denied', 403);
            }

            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                api_error('Method not allowed', 405);
            }

            $tenantId = AdvancedSAMS::currentTenantId();
            if (!$tenantId) {
                api_error('Tenant context could not be resolved', 422);
            }

            $result = AdvancedSAMS::createInvite($tenantId, (int) ($_SESSION['user_id'] ?? 0), [
                'email' => trim((string) ($_POST['email'] ?? '')),
                'role' => trim((string) ($_POST['role'] ?? 'teacher'))
            ]);

            api_success($result, 201);
            break;

        case 'redeem_invite':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                api_error('Method not allowed', 405);
            }

            $userId = AdvancedSAMS::redeemInvite([
                'invite_token' => trim((string) ($_POST['invite_token'] ?? '')),
                'email' => trim((string) ($_POST['email'] ?? '')),
                'first_name' => trim((string) ($_POST['first_name'] ?? '')),
                'last_name' => trim((string) ($_POST['last_name'] ?? '')),
                'password' => (string) ($_POST['password'] ?? '')
            ]);

            api_success(['user_id' => $userId], 201);
            break;

        case 'tenant_context':
            api_require_auth();
            $tenant = active_tenant_context();
            api_success(['tenant' => $tenant]);
            break;

        default:
            api_error('Unknown school lifecycle action', 404);
    }
} catch (Throwable $e) {
    api_error($e->getMessage(), 500);
}
