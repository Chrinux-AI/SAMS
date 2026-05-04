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
        $result = db()->fetchAll("SELECT * FROM ledger_entries ORDER BY created_at DESC", []);
        echo json_encode(['success' => true, 'data' => $result]);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $result = db()->query("INSERT INTO ledger_entries (account, entry_type, amount, description, entry_date, created_at) VALUES (?, ?, ?, ?, ?, NOW())", [$data['account'] ?? '', $data['entry_type'] ?? 'debit', $data['amount'] ?? 0, $data['description'] ?? '', $data['entry_date'] ?? null]);
        echo json_encode(['success' => (bool)$result, 'id' => db()->lastInsertId()]);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        $data = json_decode(file_get_contents('php://input'), true);
        $result = db()->query("UPDATE ledger_entries SET account = ?, entry_type = ?, amount = ?, description = ?, entry_date = ? WHERE id = ?", [$data['account'] ?? '', $data['entry_type'] ?? 'debit', $data['amount'] ?? 0, $data['description'] ?? '', $data['entry_date'] ?? null, $data['id'] ?? 0]);
        echo json_encode(['success' => (bool)$result]);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        $data = json_decode(file_get_contents('php://input'), true);
        $result = db()->query("DELETE FROM ledger_entries WHERE id = ?", [$data['id'] ?? 0]);
        echo json_encode(['success' => (bool)$result]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}
