<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');

if (!is_logged_in() || (!has_role('accountant') && !has_role('dev'))) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized access. Accountant role required.']);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $result = db()->fetchAll("SELECT * FROM fee_invoices ORDER BY created_at DESC", []);
        echo json_encode(['success' => true, 'data' => $result]);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $result = db()->query("INSERT INTO fee_invoices (student_id, amount, due_date, status, created_at) VALUES (?, ?, ?, ?, NOW())", [$data['student_id'] ?? null, $data['amount'] ?? 0, $data['due_date'] ?? null, 'pending']);
        echo json_encode(['success' => (bool)$result, 'id' => db()->lastInsertId()]);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        $data = json_decode(file_get_contents('php://input'), true);
        $result = db()->query("UPDATE fee_invoices SET student_id = ?, amount = ?, due_date = ?, status = ? WHERE id = ?", [$data['student_id'] ?? null, $data['amount'] ?? 0, $data['due_date'] ?? null, $data['status'] ?? 'pending', $data['id'] ?? 0]);
        echo json_encode(['success' => (bool)$result]);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        $data = json_decode(file_get_contents('php://input'), true);
        $result = db()->query("DELETE FROM fee_invoices WHERE id = ?", [$data['id'] ?? 0]);
        echo json_encode(['success' => (bool)$result]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}
