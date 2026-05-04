<?php
session_start();
require_once '../includes/config.php';
require_once INCLUDES_PATH . '/functions.php';
require_once INCLUDES_PATH . '/database.php';
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['bursar', 'admin'])) { header('Location: ../login.php'); exit; }
$tenantId = $_SESSION['tenant_id'] ?? 1;
$today = date('Y-m-d');
$selectedDate = $_GET['date'] ?? $today;
// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)) { $selectedDate = $today; }

function bursar_daily_scope_sql(string $table, string $alias = ''): array {
    $qualified = $alias !== '' ? $alias . '.' : '';
    if (table_has_column($table, 'tenant_id')) {
        return ['sql' => " AND {$qualified}tenant_id = ?", 'params' => [(int)($_SESSION['tenant_id'] ?? 1)]];
    }
    if (table_has_column($table, 'school_id')) {
        return ['sql' => " AND {$qualified}school_id = ?", 'params' => [(int)($_SESSION['tenant_id'] ?? 1)]];
    }
    return ['sql' => '', 'params' => []];
}

$paymentAmountField = table_has_column('fee_payments', 'amount_paid')
    ? 'amount_paid'
    : (table_has_column('fee_payments', 'amount') ? 'amount' : '0');
$paymentReferenceField = table_has_column('fee_payments', 'payment_reference')
    ? 'payment_reference'
    : (table_has_column('fee_payments', 'reference_number') ? 'reference_number' : (table_has_column('fee_payments', 'transaction_id') ? 'transaction_id' : "''"));
$paymentTypeField = table_has_column('fee_payments', 'payment_status')
    ? 'payment_status'
    : (table_has_column('fee_payments', 'payment_type') ? 'payment_type' : "''");

$payments = [];
$totalCollected = 0;
try {
    $scope = bursar_daily_scope_sql('fee_payments', 'fp');
    $studentJoin = '';
    $studentNameExpr = "'N/A' AS full_name";
    if (table_has_column('fee_payments', 'student_id')) {
        $studentJoin = " LEFT JOIN students s ON fp.student_id = s.id LEFT JOIN users u ON s.user_id = u.id";
        $studentNameExpr = "COALESCE(u.full_name, CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')), 'N/A') AS full_name";
    } elseif (table_has_column('fee_payments', 'fee_id') && table_exists('invoices') && table_has_column('invoices', 'student_id')) {
        $studentJoin = " LEFT JOIN invoices inv ON fp.fee_id = inv.id LEFT JOIN students s ON inv.student_id = s.id LEFT JOIN users u ON s.user_id = u.id";
        $studentNameExpr = "COALESCE(u.full_name, CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')), 'N/A') AS full_name";
    }

    $payments = db()->fetchAll(
        "SELECT fp.*, {$studentNameExpr},
                {$paymentAmountField} AS payment_amount,
                {$paymentReferenceField} AS payment_reference_display,
                {$paymentTypeField} AS payment_type_display
         FROM fee_payments fp{$studentJoin}
         WHERE DATE(fp.payment_date) = ?{$scope['sql']}
         ORDER BY fp.payment_date DESC",
        array_merge([$selectedDate], $scope['params'])
    ) ?: [];
    $totalCollected = array_sum(array_map(static fn($row) => (float)($row['payment_amount'] ?? 0), $payments));
} catch (Exception $e) {}
$invoicesPaidToday = 0;
try {
    if (table_exists('invoices')) {
        $invoiceScope = bursar_daily_scope_sql('invoices');
        $invoiceDateField = table_has_column('invoices', 'updated_at') ? 'updated_at' : 'created_at';
        $row = db()->fetchOne(
            "SELECT COUNT(*) as cnt FROM invoices WHERE status = 'paid' AND DATE({$invoiceDateField}) = ?{$invoiceScope['sql']}",
            array_merge([$selectedDate], $invoiceScope['params'])
        );
        $invoicesPaidToday = (int)($row['cnt'] ?? 0);
    }
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include INCLUDES_PATH . '/favicon-loader.php'; ?>
    <script src="../assets/js/theme-loader.js"></script>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daily Summary - SAMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/professional-ui.css">
    <?php include '../includes/sams-head-bootstrap.php'; ?>

    <link rel="stylesheet" href="../assets/css/sidebar-nav.css">
    <link rel="stylesheet" href="../assets/css/sams-theme-system.css">
    <link rel="stylesheet" href="../assets/css/sams-layout.css">
</head>
<body>
<div class="app-layout">
    <?php include INCLUDES_PATH . '/sidebar-nav.php'; ?>
    <main class="main-content">
        <style>
            .daily-summary-table th,
            .daily-summary-table td {
                padding: 12px 14px;
                text-align: left;
                white-space: nowrap;
                vertical-align: middle;
            }

            .daily-summary-table thead th {
                font-size: 0.72rem;
                letter-spacing: 0.06em;
                text-transform: uppercase;
                color: rgba(15, 23, 42, 0.62);
                border-bottom: 1px solid rgba(148, 163, 184, 0.18);
            }

            .daily-summary-table tbody td {
                border-bottom: 1px solid rgba(148, 163, 184, 0.12);
            }
        </style>
        <div class="cyber-header"><div class="page-icon-orb"><i class="fas fa-chart-bar"></i></div><div><h1>Daily Summary</h1><p>Financial overview for <?= htmlspecialchars($selectedDate) ?></p></div></div>
        <div class="cyber-content">
            <form method="GET" style="margin-bottom:20px;display:flex;gap:12px;align-items:center;">
                <label>Date:</label><input type="date" name="date" class="form-control" value="<?= htmlspecialchars($selectedDate) ?>" style="width:auto;">
                <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> View</button>
            </form>
            <div class="stats-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:24px;">
                <div class="card"><div class="card-body" style="text-align:center;"><h3 style="color:#22c55e;font-size:2rem;">$<?= number_format($totalCollected, 2) ?></h3><p>Total Collected</p></div></div>
                <div class="card"><div class="card-body" style="text-align:center;"><h3 style="color:#3b82f6;font-size:2rem;"><?= count($payments) ?></h3><p>Transactions</p></div></div>
                <div class="card"><div class="card-body" style="text-align:center;"><h3 style="color:#8b5cf6;font-size:2rem;"><?= $invoicesPaidToday ?></h3><p>Invoices Settled</p></div></div>
            </div>
            <div class="card"><div class="card-header"><h3>Transactions on <?= htmlspecialchars($selectedDate) ?></h3></div><div class="card-body" style="overflow-x:auto;">
                <table class="table daily-summary-table"><thead><tr><th>Time</th><th>Student</th><th>Amount</th><th>Type</th><th>Method</th><th>Reference</th></tr></thead><tbody>
                <?php if (empty($payments)): ?><tr><td colspan="6" style="text-align:center;padding:24px;">No transactions for this date.</td></tr>
                <?php else: foreach ($payments as $p): ?>
                <tr><td><?= date('H:i', strtotime($p['payment_date'])) ?></td><td><?= htmlspecialchars(trim((string)($p['full_name'] ?? '')) ?: 'N/A') ?></td><td>$<?= number_format((float)($p['payment_amount'] ?? 0), 2) ?></td><td><?= ucfirst((string)($p['payment_type_display'] ?? '')) ?></td><td><?= ucfirst((string)($p['payment_method'] ?? '')) ?></td><td><code><?= htmlspecialchars((string)($p['payment_reference_display'] ?? '-')) ?></code></td></tr>
                <?php endforeach; endif; ?></tbody></table>
            </div></div>
        </div>
    </main>
</div>
<script src="../assets/js/main.js"></script>
</body></html>
