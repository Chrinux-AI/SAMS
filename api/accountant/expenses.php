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
    $tenantId = (int)($_SESSION['tenant_id'] ?? 1);

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $result = db()->fetchAll("SELECT * FROM expenses WHERE tenant_id = ? ORDER BY created_at DESC", [$tenantId]);
        echo json_encode(['success' => true, 'data' => $result]);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $result = db()->query(
            "INSERT INTO expenses (tenant_id, category, amount, description, expense_date, created_at) VALUES (?, ?, ?, ?, ?, NOW())",
            [$tenantId, $data['category'] ?? null, $data['amount'] ?? 0, $data['description'] ?? '', $data['expense_date'] ?? null]
        );
        echo json_encode(['success' => (bool)$result, 'id' => db()->lastInsertId()]);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        $data = json_decode(file_get_contents('php://input'), true);
        $result = db()->query(
            "UPDATE expenses SET category = ?, amount = ?, description = ?, expense_date = ? WHERE id = ? AND tenant_id = ?",
            [$data['category'] ?? null, $data['amount'] ?? 0, $data['description'] ?? '', $data['expense_date'] ?? null, $data['id'] ?? 0, $tenantId]
        );
        echo json_encode(['success' => (bool)$result]);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        $data = json_decode(file_get_contents('php://input'), true);
        $result = db()->query("DELETE FROM expenses WHERE id = ? AND tenant_id = ?", [$data['id'] ?? 0, $tenantId]);
        echo json_encode(['success' => (bool)$result]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}
