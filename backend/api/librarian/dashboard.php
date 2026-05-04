<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../modules/librarian/LibrarianManager.php';

header('Content-Type: application/json');

$manager = new LibrarianManager();
echo json_encode([
    'success' => true,
    'data' => $manager->getDashboardStats()
]);
