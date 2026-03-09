<?php
session_start();
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Compatibility endpoint for offline sync queue.
echo json_encode([
    'success' => true,
    'message' => 'Assignments sync endpoint is reachable'
]);


