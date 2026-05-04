<?php
session_start();
require_once '../includes/config.php';
require_once INCLUDES_PATH . '/functions.php';
require_once INCLUDES_PATH . '/database.php';
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['bursar', 'admin'])) { header('Location: ../login.php'); exit; }
$tenantId = $_SESSION['tenant_id'] ?? 1;
$receipts = [];
try {
    $amountExpr = table_has_column('fee_payments', 'amount_paid') ? 'fp.amount_paid' : 'fp.amount';
    $typeExpr = table_has_column('fee_payments', 'payment_type') ? 'fp.payment_type' : 'fp.payment_status';
    $referenceExpr = table_has_column('fee_payments', 'reference_number') ? 'fp.reference_number' : (table_has_column('fee_payments', 'payment_reference') ? 'fp.payment_reference' : 'fp.transaction_id');
    $statusExpr = table_has_column('fee_payments', 'status') ? 'fp.status' : 'fp.payment_status';
    $dateExpr = table_has_column('fee_payments', 'payment_date') ? 'fp.payment_date' : 'fp.created_at';
    $joins = '';
    if (table_has_column('fee_payments', 'student_id')) {
        $joins = ' LEFT JOIN users u ON fp.student_id = u.id';
    } elseif (table_has_column('fee_payments', 'fee_id') && table_exists('invoices')) {
        $joins = ' LEFT JOIN invoices i ON fp.fee_id = i.id LEFT JOIN users u ON i.student_id = u.id';
    }
    $paidClause = table_has_column('fee_payments', 'status') ? "fp.status = 'paid'" : "fp.payment_status = 'completed'";
    $receipts = db()->fetchAll("SELECT fp.*, u.full_name, {$amountExpr} AS display_amount, {$typeExpr} AS display_type, {$referenceExpr} AS display_reference, {$dateExpr} AS display_date FROM fee_payments fp{$joins} WHERE fp.tenant_id = ? AND {$paidClause} ORDER BY {$dateExpr} DESC LIMIT 50", [$tenantId]);
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include INCLUDES_PATH . '/favicon-loader.php'; ?>
    <script src="../assets/js/theme-loader.js"></script>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipts - SAMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/professional-ui.css">
    <?php include '../includes/sams-head-bootstrap.php'; ?>

    <link rel="stylesheet" href="../assets/css/sidebar-nav.css">
    <link rel="stylesheet" href="../assets/css/sams-theme-system.css">
    <link rel="stylesheet" href="../assets/css/sams-layout.css">
    <style>
        .finance-table th,
        .finance-table td {
            padding: 0.875rem 1rem;
            vertical-align: middle;
            white-space: nowrap;
        }

        .finance-table th {
            color: #475569;
            font-size: 0.72rem;
            font-weight: 800;
            letter-spacing: 0;
            text-transform: uppercase;
            background: #f8fafc;
        }
    </style>
</head>
<body>
<div class="app-layout">
    <?php include INCLUDES_PATH . '/sidebar-nav.php'; ?>
    <main class="main-content">
        <div class="cyber-header"><div class="page-icon-orb"><i class="fas fa-receipt"></i></div><div><h1>Payment Receipts</h1><p><?= count($receipts) ?> receipts found</p></div></div>
        <div class="cyber-content">
            <div class="card"><div class="card-body" style="overflow-x:auto;">
                <table class="table finance-table"><thead><tr><th>Receipt #</th><th>Date</th><th>Student</th><th>Amount</th><th>Type</th><th>Method</th><th>Reference</th></tr></thead><tbody>
                <?php if (empty($receipts)): ?><tr><td colspan="7" style="text-align:center;padding:24px;">No receipts found.</td></tr>
                <?php else: foreach ($receipts as $i => $r): ?>
                <tr><td><code>RCP-<?= str_pad($r['id'], 6, '0', STR_PAD_LEFT) ?></code></td><td><?= date('M j, Y', strtotime($r['display_date'] ?? $r['created_at'] ?? 'now')) ?></td><td><?= htmlspecialchars($r['full_name'] ?? 'N/A') ?></td><td><strong>$<?= number_format((float)($r['display_amount'] ?? 0), 2) ?></strong></td><td><?= ucfirst(str_replace('_', ' ', (string)($r['display_type'] ?? ''))) ?></td><td><?= ucfirst(str_replace('_', ' ', $r['payment_method'] ?? '')) ?></td><td><?= htmlspecialchars($r['display_reference'] ?? '-') ?></td></tr>
                <?php endforeach; endif; ?></tbody></table>
            </div></div>
        </div>
    </main>
</div>
<script src="../assets/js/main.js"></script>
</body></html>
