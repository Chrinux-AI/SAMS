<?php
session_start();
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json');

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);
if (!is_array($payload)) {
    $payload = [];
}

$message = $payload['message'] ?? 'Unknown client error';
$stack = $payload['stack'] ?? null;
$url = $payload['url'] ?? null;

error_log('[Client Error] ' . json_encode([
    'user_id' => $_SESSION['user_id'] ?? null,
    'message' => $message,
    'stack' => $stack,
    'url' => $url,
    'timestamp' => date('c')
]));

echo json_encode([
    'success' => true
]);


