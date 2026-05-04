<?php

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../modules/admin/AdminUserManager.php';

header('Content-Type: application/json');

if (!function_exists('admin_api_is_authorized')) {
    function admin_api_is_authorized(): bool
    {
        if (!is_logged_in()) {
            return false;
        }

        $allowedRoles = ['admin', 'super_admin', 'superadmin', 'owner', 'principal', 'vice_principal', 'admin_officer'];
        $sessionRole = strtolower((string)($_SESSION['user_role'] ?? ($_SESSION['role'] ?? '')));
        if (!in_array($sessionRole, $allowedRoles, true)) {
            return false;
        }

        $user = db()->fetchOne("SELECT role, status FROM users WHERE id = ? LIMIT 1", [(int)($_SESSION['user_id'] ?? 0)]) ?: [];
        return in_array(strtolower((string)($user['role'] ?? '')), $allowedRoles, true)
            && strtolower((string)($user['status'] ?? '')) === 'active';
    }
}

if (!admin_api_is_authorized()) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized access.',
    ]);
    exit;
}

$rawBody = file_get_contents('php://input');
$payload = json_decode($rawBody ?: '', true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$action = (string)($_GET['action'] ?? ($payload['action'] ?? ''));
$manager = new AdminUserManager((int)($_SESSION['tenant_id'] ?? 1), (int)($_SESSION['user_id'] ?? 0));

switch ($action) {
    case 'delete':
        $result = $manager->deleteUser((int)($payload['user_id'] ?? 0));
        break;

    case 'bulk_delete':
        $result = $manager->bulkDeleteUsers((array)($payload['user_ids'] ?? []));
        break;

    case 'delete_pending':
        if (($payload['confirm'] ?? '') !== 'DELETE_ALL_PENDING') {
            $result = [
                'success' => false,
                'error' => 'Confirmation required.',
            ];
            break;
        }
        $result = $manager->deletePendingUsers();
        break;

    default:
        http_response_code(400);
        $result = [
            'success' => false,
            'error' => 'Invalid action.',
        ];
        break;
}

if (empty($result['success']) && http_response_code() === 200) {
    http_response_code(422);
}

echo json_encode($result);
