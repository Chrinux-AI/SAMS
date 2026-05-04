<?php

session_start();

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/api-response.php';
require_once __DIR__ . '/../../modules/admin/AdminUserManager.php';

if (!function_exists('admin_api_normalize_role')) {
    function admin_api_normalize_role(string $role): string
    {
        return str_replace('-', '_', strtolower(trim($role)));
    }
}

if (!function_exists('admin_api_allowed_roles')) {
    function admin_api_allowed_roles(): array
    {
        return ['admin', 'super_admin', 'superadmin', 'owner', 'principal', 'vice_principal', 'admin_officer'];
    }
}

if (!function_exists('admin_api_authorize')) {
    function admin_api_authorize(): array
    {
        api_require_auth();

        $userId = (int)($_SESSION['user_id'] ?? 0);
        if ($userId <= 0) {
            api_error('Unauthorized access.', 401);
        }

        if (!isset($_SESSION['tenant_id'])) {
            set_user_tenant_session($userId);
        }

        if (!user_in_current_tenant($userId)) {
            api_error('Tenant access denied.', 403);
        }

        $allowedRoles = admin_api_allowed_roles();
        $sessionRole = admin_api_normalize_role((string)($_SESSION['user_role'] ?? ($_SESSION['role'] ?? '')));
        if (!in_array($sessionRole, $allowedRoles, true)) {
            api_error('Unauthorized access.', 403);
        }

        $user = db()->fetchOne(
            "SELECT role, status FROM users WHERE id = ? LIMIT 1",
            [$userId]
        ) ?: [];

        $dbRole = admin_api_normalize_role((string)($user['role'] ?? ''));
        $dbStatus = strtolower((string)($user['status'] ?? ''));

        if (!in_array($dbRole, $allowedRoles, true) || $dbStatus !== 'active') {
            api_error('Session invalid. Please login again.', 403);
        }

        return [
            'user_id' => $userId,
            'tenant_id' => current_tenant_id(),
            'role' => $dbRole,
        ];
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_error('Method not allowed', 405);
}

$context = admin_api_authorize();

$rawBody = file_get_contents('php://input');
$payload = json_decode($rawBody ?: '', true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$action = (string)($_GET['action'] ?? ($payload['action'] ?? ''));
if ($action === '') {
    api_error('Action is required.', 422);
}

$manager = new AdminUserManager($context['tenant_id'], $context['user_id']);
$statusCode = 200;

switch ($action) {
    case 'approve_user':
        $result = $manager->approveUser((int)($payload['user_id'] ?? 0), (string)($payload['assigned_id'] ?? ''));
        break;

    case 'approve_unverified':
        $result = $manager->approveUser((int)($payload['user_id'] ?? 0), (string)($payload['assigned_id'] ?? ''), true);
        break;

    case 'bulk_approve':
        $result = $manager->bulkApproveUsers((array)($payload['user_ids'] ?? []), (array)($payload['assigned_ids'] ?? []));
        break;

    case 'disapprove_user':
        $result = $manager->disapproveUser((int)($payload['user_id'] ?? 0));
        break;

    case 'reject_user':
        $result = $manager->rejectUser((int)($payload['user_id'] ?? 0));
        break;

    case 'bulk_reject':
        $result = $manager->bulkRejectUsers((array)($payload['user_ids'] ?? []));
        break;

    case 'resend_single':
        $result = $manager->resendVerification((int)($payload['user_id'] ?? 0));
        break;

    case 'resend_bulk':
        $result = $manager->bulkResendVerification((array)($payload['user_ids'] ?? []));
        break;

    case 'resend_all':
        if (($payload['confirm'] ?? '') !== 'RESEND_ALL') {
            $result = [
                'success' => false,
                'error' => 'Confirmation required.',
            ];
            $statusCode = 422;
            break;
        }
        $result = $manager->resendVerificationToAll();
        break;

    default:
        api_error('Invalid action.', 400);
}

if (empty($result['success'])) {
    $statusCode = $statusCode === 200 ? 422 : $statusCode;
}

api_json_response($result, $statusCode);
