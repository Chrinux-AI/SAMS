<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/api-response.php';

api_require_auth();

// Compatibility endpoint for offline sync queue.
api_success(['message' => 'Attendance sync endpoint is reachable']);

