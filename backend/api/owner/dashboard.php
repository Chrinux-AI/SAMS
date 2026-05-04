<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../modules/owner/OwnerManager.php';

header('Content-Type: application/json');

$manager = new OwnerManager();
echo json_encode([
    'success' => true,
    'data' => $manager->getDashboardStats()
]);
