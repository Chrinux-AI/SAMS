<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../modules/bursar/BursarManager.php';

header('Content-Type: application/json');

$manager = new BursarManager();
echo json_encode([
    'success' => true,
    'data' => $manager->getDashboardStats()
]);
