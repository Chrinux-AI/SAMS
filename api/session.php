<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/api-response.php';

api_success([
    'is_logged_in' => isset($_SESSION['user_id']),
    'user_id' => $_SESSION['user_id'] ?? null,
    'role' => $_SESSION['user_role'] ?? ($_SESSION['role'] ?? null)
]);

