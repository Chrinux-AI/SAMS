<?php
session_start();
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json');

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);
if (!is_array($payload)) {
    $payload = [];
}

// Keep this lightweight: accept analytics payloads without hard DB dependency.
error_log('[PWA Analytics] ' . json_encode([
    'user_id' => $_SESSION['user_id'] ?? null,
    'action' => $payload['action'] ?? null,
    'event_type' => $payload['event_type'] ?? null,
    'page_url' => $payload['page_url'] ?? null,
    'timestamp' => date('c')
]));

echo json_encode([
    'success' => true
]);


