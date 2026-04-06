<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');

if (!is_logged_in() || (!has_role('accountant') && !has_role('owner') && !has_role('super_admin'))) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized access. Accountant role required.']);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $result = db()->fetchAll("SELECT * FROM suppliers ORDER BY name ASC", []);
        echo json_encode(['success' => true, 'data' => $result]);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $result = db()->query("INSERT INTO suppliers (name, contact_person, email, phone, address, created_at) VALUES (?, ?, ?, ?, ?, NOW())", [$data['name'] ?? '', $data['contact_person'] ?? '', $data['email'] ?? '', $data['phone'] ?? '', $data['address'] ?? '']);
        echo json_encode(['success' => (bool)$result, 'id' => db()->lastInsertId()]);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        $data = json_decode(file_get_contents('php://input'), true);
        $result = db()->query("UPDATE suppliers SET name = ?, contact_person = ?, email = ?, phone = ?, address = ? WHERE id = ?", [$data['name'] ?? '', $data['contact_person'] ?? '', $data['email'] ?? '', $data['phone'] ?? '', $data['address'] ?? '', $data['id'] ?? 0]);
        echo json_encode(['success' => (bool)$result]);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        $data = json_decode(file_get_contents('php://input'), true);
        $result = db()->query("DELETE FROM suppliers WHERE id = ?", [$data['id'] ?? 0]);
        echo json_encode(['success' => (bool)$result]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}
