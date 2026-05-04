<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../modules/librarian/LibrarianManager.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit;
}

// Session security
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'librarian') {
    // Also allow super admin or owner to hit these APIs as fail-safe
    if (!in_array($_SESSION['role'] ?? '', ['admin', 'owner'])) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized Role Access']);
        exit;
    }
}

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$manager = new LibrarianManager();

try {
    
    if(empty($input['title'])) throw new Exception('Title is required');
    $res = $manager->addBook($input);
    echo json_encode(['success' => true, 'data' => $res]);

} catch(Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
