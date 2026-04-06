<?php
/**
 * Standard API response helpers.
 */

if (!function_exists('api_json_response')) {
    function api_json_response(array $payload, int $statusCode = 200): void
    {
        if (!headers_sent()) {
            http_response_code($statusCode);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode($payload);
        exit;
    }
}

if (!function_exists('api_success')) {
    function api_success(array $data = [], int $statusCode = 200): void
    {
        api_json_response([
            'success' => true,
            'data' => $data
        ], $statusCode);
    }
}

if (!function_exists('api_error')) {
    function api_error(string $message, int $statusCode = 400, array $meta = []): void
    {
        api_json_response([
            'success' => false,
            'error' => $message,
            'meta' => $meta
        ], $statusCode);
    }
}

if (!function_exists('api_require_auth')) {
    function api_require_auth(): void
    {
        if (!isset($_SESSION['user_id'])) {
            api_error('Unauthorized', 401);
        }
    }
}

