<?php

session_start();

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/api-response.php';

api_require_auth();

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    api_error('Unauthorized', 401);
}

if (!isset($_SESSION['tenant_id'])) {
    set_user_tenant_session($userId);
}

if (!user_in_current_tenant($userId)) {
    api_error('Tenant access denied', 403);
}

api_success([
    'message' => 'Attendance endpoint is reachable',
    'tenant_id' => current_tenant_id(),
    'user_id' => $userId,
]);
