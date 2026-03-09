<?php
session_start();
require_once '../includes/config.php';
require_once INCLUDES_PATH . '/functions.php';
require_once INCLUDES_PATH . '/database.php';
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['accountant', 'admin'])) { header('Location: ../login.php'); exit; }
$tenantId = $_SESSION['tenant_id'] ?? 1;
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $data = ['tenant_id' => $tenantId, 'expense_date' => trim($_POST['expense_date'] ?? date('Y-m-d')), 'category' => trim($_POST['category'] ?? ''), 'description' => trim($_POST['description'] ?? ''), 'amount' => floatval($_POST['amount'] ?? 0), 'vendor' => trim($_POST['vendor'] ?? ''), 'payment_method' => trim($_POST['payment_method'] ?? 'cash'), 'receipt_number' => trim($_POST['receipt_number'] ?? ''), 'approved_by' => $_SESSION['user_id'], 'created_at' => date('Y-m-d H:i:s')];
        try { insert_flexible('expenses', $data); $msg = 'Expense recorded.'; } catch (Exception $e) { $msg = 'Error recording expense.'; }
    }
}
$expenses = [];
try { $expenses = db()->fetchAll("SELECT * FROM expenses WHERE tenant_id = ? ORDER BY expense_date DESC LIMIT 100", [$tenantId]); } catch (Exception $e) {}
$totalExpenses = array_sum(array_column($expenses, 'amount'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include INCLUDES_PATH . '/favicon-loader.php'; ?>
    <script src="../assets/js/theme-loader.js"></script>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expenses - SAMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/professional-ui.css">
    <link rel="stylesheet" href="../assets/css/sidebar-nav.css">
    <link rel="stylesheet" href="../assets/css/sams-theme-system.css">
    <link rel="stylesheet" href="../assets/css/sams-layout.css">
</head>
<body>
<div class="app-layout">
    <?php include INCLUDES_PATH . '/sidebar-nav.php'; ?>
    <main class="main-content">
        <div class="cyber-header"><div class="page-icon-orb"><i class="fas fa-receipt"></i></div><div><h1>Expenses</h1><p>Track and manage expenditures</p></div></div>
        <div class="cyber-content">
            <?php if ($msg): ?><div class="alert alert-info"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
            <div class="card" style="margin-bottom:24px;"><div class="card-header"><h3>Record Expense</h3></div><div class="card-body">
                <form method="POST" class="form-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;align-items:end;">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>"><input type="hidden" name="action" value="add">
                    <div><label>Date</label><input type="date" name="expense_date" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
                    <div><label>Category</label><select name="category" class="form-control"><option value="supplies">Supplies</option><option value="utilities">Utilities</option><option value="maintenance">Maintenance</option><option value="salaries">Salaries</option><option value="transport">Transport</option><option value="events">Events</option><option value="other">Other</option></select></div>
                    <div><label>Description</label><input type="text" name="description" class="form-control" required></div>
                    <div><label>Amount ($)</label><input type="number" name="amount" class="form-control" step="0.01" min="0" required></div>
                    <div><label>Vendor</label><input type="text" name="vendor" class="form-control"></div>
                    <div><label>Method</label><select name="payment_method" class="form-control"><option value="cash">Cash</option><option value="bank_transfer">Bank Transfer</option><option value="cheque">Cheque</option><option value="mobile_money">Mobile Money</option></select></div>
                    <div><label>Receipt #</label><input type="text" name="receipt_number" class="form-control"></div>
                    <div><button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Record</button></div>
                </form>
            </div></div>
            <div class="card" style="margin-bottom:16px;"><div class="card-body" style="text-align:center;"><h3 style="color:#ef4444;">Total Expenses: $<?= number_format($totalExpenses, 2) ?></h3></div></div>
            <div class="card"><div class="card-body" style="overflow-x:auto;">
                <table class="table"><thead><tr><th>Date</th><th>Category</th><th>Description</th><th>Amount</th><th>Vendor</th><th>Method</th><th>Receipt</th></tr></thead><tbody>
                <?php if (empty($expenses)): ?><tr><td colspan="7" style="text-align:center;padding:24px;">No expenses recorded.</td></tr>
                <?php else: foreach ($expenses as $e): ?>
                <tr><td><?= date('M j, Y', strtotime($e['expense_date'])) ?></td><td><span class="badge badge-info"><?= ucfirst($e['category'] ?? '') ?></span></td><td><?= htmlspecialchars($e['description'] ?? '') ?></td><td style="color:#ef4444;font-weight:bold;">$<?= number_format($e['amount'], 2) ?></td><td><?= htmlspecialchars($e['vendor'] ?? '-') ?></td><td><?= ucfirst(str_replace('_', ' ', $e['payment_method'] ?? '')) ?></td><td><code><?= htmlspecialchars($e['receipt_number'] ?? '-') ?></code></td></tr>
                <?php endforeach; endif; ?></tbody></table>
            </div></div>
        </div>
    </main>
</div>
<script src="../assets/js/main.js"></script>
</body></html>
