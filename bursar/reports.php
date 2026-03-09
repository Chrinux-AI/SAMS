<?php
session_start();
require_once '../includes/config.php';
require_once INCLUDES_PATH . '/functions.php';
require_once INCLUDES_PATH . '/database.php';
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['bursar', 'admin'])) { header('Location: ../login.php'); exit; }
$tenantId = $_SESSION['tenant_id'] ?? 1;
$period = $_GET['period'] ?? 'month';
$validPeriods = ['week', 'month', 'term', 'year'];
if (!in_array($period, $validPeriods)) $period = 'month';
$dateCondition = match($period) {
    'week' => "AND fp.payment_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)",
    'month' => "AND fp.payment_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)",
    'term' => "AND fp.payment_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)",
    'year' => "AND fp.payment_date >= DATE_SUB(CURDATE(), INTERVAL 365 DAY)",
};
$summary = ['total_collected' => 0, 'total_invoiced' => 0, 'total_outstanding' => 0, 'payment_count' => 0];
try {
    $row = db()->fetchOne("SELECT COALESCE(SUM(amount),0) as total, COUNT(*) as cnt FROM fee_payments fp WHERE fp.tenant_id = ? $dateCondition", [$tenantId]);
    $summary['total_collected'] = $row['total'] ?? 0;
    $summary['payment_count'] = $row['cnt'] ?? 0;
} catch (Exception $e) {}
try {
    $row = db()->fetchOne("SELECT COALESCE(SUM(total_amount),0) as invoiced, COALESCE(SUM(balance),0) as outstanding FROM invoices WHERE tenant_id = ?", [$tenantId]);
    $summary['total_invoiced'] = $row['invoiced'] ?? 0;
    $summary['total_outstanding'] = $row['outstanding'] ?? 0;
} catch (Exception $e) {}
$byType = [];
try { $byType = db()->fetchAll("SELECT payment_type, COUNT(*) as cnt, SUM(amount) as total FROM fee_payments fp WHERE fp.tenant_id = ? $dateCondition GROUP BY payment_type ORDER BY total DESC", [$tenantId]); } catch (Exception $e) {}
$byMethod = [];
try { $byMethod = db()->fetchAll("SELECT payment_method, COUNT(*) as cnt, SUM(amount) as total FROM fee_payments fp WHERE fp.tenant_id = ? $dateCondition GROUP BY payment_method ORDER BY total DESC", [$tenantId]); } catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include INCLUDES_PATH . '/favicon-loader.php'; ?>
    <script src="../assets/js/theme-loader.js"></script>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Reports - SAMS</title>
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
        <div class="cyber-header"><div class="page-icon-orb"><i class="fas fa-file-invoice-dollar"></i></div><div><h1>Financial Reports</h1><p>Revenue analysis and reporting</p></div></div>
        <div class="cyber-content">
            <div style="margin-bottom:20px;display:flex;gap:8px;">
                <?php foreach ($validPeriods as $p): ?><a href="?period=<?= $p ?>" class="btn btn-<?= $period === $p ? 'primary' : 'secondary' ?>"><?= ucfirst($p) ?></a><?php endforeach; ?>
            </div>
            <div class="stats-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:24px;">
                <div class="card"><div class="card-body" style="text-align:center;"><h3 style="color:#22c55e;font-size:2rem;">$<?= number_format($summary['total_collected'], 2) ?></h3><p>Collected (<?= ucfirst($period) ?>)</p></div></div>
                <div class="card"><div class="card-body" style="text-align:center;"><h3 style="color:#3b82f6;font-size:2rem;"><?= $summary['payment_count'] ?></h3><p>Payments</p></div></div>
                <div class="card"><div class="card-body" style="text-align:center;"><h3 style="color:#f59e0b;font-size:2rem;">$<?= number_format($summary['total_invoiced'], 2) ?></h3><p>Total Invoiced</p></div></div>
                <div class="card"><div class="card-body" style="text-align:center;"><h3 style="color:#ef4444;font-size:2rem;">$<?= number_format($summary['total_outstanding'], 2) ?></h3><p>Outstanding</p></div></div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;">
                <div class="card"><div class="card-header"><h3>By Fee Type</h3></div><div class="card-body">
                    <table class="table"><thead><tr><th>Type</th><th>Count</th><th>Total</th></tr></thead><tbody>
                    <?php if (empty($byType)): ?><tr><td colspan="3" style="text-align:center;">No data</td></tr>
                    <?php else: foreach ($byType as $t): ?>
                    <tr><td><?= ucfirst(htmlspecialchars($t['payment_type'] ?? 'Unknown')) ?></td><td><?= $t['cnt'] ?></td><td>$<?= number_format($t['total'], 2) ?></td></tr>
                    <?php endforeach; endif; ?></tbody></table>
                </div></div>
                <div class="card"><div class="card-header"><h3>By Payment Method</h3></div><div class="card-body">
                    <table class="table"><thead><tr><th>Method</th><th>Count</th><th>Total</th></tr></thead><tbody>
                    <?php if (empty($byMethod)): ?><tr><td colspan="3" style="text-align:center;">No data</td></tr>
                    <?php else: foreach ($byMethod as $m): ?>
                    <tr><td><?= ucfirst(htmlspecialchars($m['payment_method'] ?? 'Unknown')) ?></td><td><?= $m['cnt'] ?></td><td>$<?= number_format($m['total'], 2) ?></td></tr>
                    <?php endforeach; endif; ?></tbody></table>
                </div></div>
            </div>
        </div>
    </main>
</div>
<script src="../assets/js/main.js"></script>
</body></html>
