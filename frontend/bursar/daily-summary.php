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
$payments = [];
$totalCollected = 0;
try {
    $payments = db()->fetchAll("SELECT fp.*, u.full_name FROM fee_payments fp LEFT JOIN users u ON fp.student_id = u.id WHERE fp.tenant_id = ? AND DATE(fp.payment_date) = ? ORDER BY fp.payment_date DESC", [$tenantId, $selectedDate]);
    $totalCollected = array_sum(array_column($payments, 'amount'));
} catch (Exception $e) {}
$invoicesPaidToday = 0;
try { $row = db()->fetchOne("SELECT COUNT(*) as cnt FROM invoices WHERE tenant_id = ? AND DATE(updated_at) = ? AND status = 'paid'", [$tenantId, $selectedDate]); $invoicesPaidToday = $row['cnt'] ?? 0; } catch (Exception $e) {}
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
    <link rel="stylesheet" href="../assets/css/sidebar-nav.css">
    <link rel="stylesheet" href="../assets/css/sams-theme-system.css">
    <link rel="stylesheet" href="../assets/css/sams-layout.css">
</head>
<body>
<div class="app-layout">
    <?php include INCLUDES_PATH . '/sidebar-nav.php'; ?>
    <main class="main-content">
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
                <table class="table"><thead><tr><th>Time</th><th>Student</th><th>Amount</th><th>Type</th><th>Method</th><th>Reference</th></tr></thead><tbody>
                <?php if (empty($payments)): ?><tr><td colspan="6" style="text-align:center;padding:24px;">No transactions for this date.</td></tr>
                <?php else: foreach ($payments as $p): ?>
                <tr><td><?= date('H:i', strtotime($p['payment_date'])) ?></td><td><?= htmlspecialchars($p['full_name'] ?? 'N/A') ?></td><td>$<?= number_format($p['amount'], 2) ?></td><td><?= ucfirst($p['payment_type'] ?? '') ?></td><td><?= ucfirst($p['payment_method'] ?? '') ?></td><td><code><?= htmlspecialchars($p['reference_number'] ?? '-') ?></code></td></tr>
                <?php endforeach; endif; ?></tbody></table>
            </div></div>
        </div>
    </main>
</div>
<script src="../assets/js/main.js"></script>
</body></html>
