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
        $result = db()->fetchAll("SELECT * FROM purchase_orders ORDER BY created_at DESC", []);
        echo json_encode(['success' => true, 'data' => $result]);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $result = db()->query("INSERT INTO purchase_orders (supplier_id, order_date, total_amount, status, created_at) VALUES (?, ?, ?, ?, NOW())", [$data['supplier_id'] ?? null, $data['order_date'] ?? null, $data['total_amount'] ?? 0, 'pending']);
        echo json_encode(['success' => (bool)$result, 'id' => db()->lastInsertId()]);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        $data = json_decode(file_get_contents('php://input'), true);
        $result = db()->query("UPDATE purchase_orders SET supplier_id = ?, order_date = ?, total_amount = ?, status = ? WHERE id = ?", [$data['supplier_id'] ?? null, $data['order_date'] ?? null, $data['total_amount'] ?? 0, $data['status'] ?? 'pending', $data['id'] ?? 0]);
        echo json_encode(['success' => (bool)$result]);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        $data = json_decode(file_get_contents('php://input'), true);
        $result = db()->query("DELETE FROM purchase_orders WHERE id = ?", [$data['id'] ?? 0]);
        echo json_encode(['success' => (bool)$result]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}
