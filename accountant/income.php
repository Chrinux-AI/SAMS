<?php
session_start();
require_once '../includes/config.php';
require_once INCLUDES_PATH . '/functions.php';
require_once INCLUDES_PATH . '/database.php';
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['accountant', 'admin'])) { header('Location: ../login.php'); exit; }
$tenantId = $_SESSION['tenant_id'] ?? 1;
// Income = fee_payments + other income sources
$feeIncome = 0;
try { $row = db()->fetchOne("SELECT COALESCE(SUM(amount),0) as total FROM fee_payments WHERE tenant_id = ?", [$tenantId]); $feeIncome = $row['total'] ?? 0; } catch (Exception $e) {}
$otherIncome = [];
try { $otherIncome = db()->fetchAll("SELECT * FROM ledger_entries WHERE tenant_id = ? AND account = 'revenue' AND credit > 0 ORDER BY entry_date DESC LIMIT 50", [$tenantId]); } catch (Exception $e) {}
$otherTotal = array_sum(array_column($otherIncome, 'credit'));
$recentPayments = [];
try { $recentPayments = db()->fetchAll("SELECT fp.*, u.full_name FROM fee_payments fp LEFT JOIN users u ON fp.student_id = u.id WHERE fp.tenant_id = ? ORDER BY fp.payment_date DESC LIMIT 20", [$tenantId]); } catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include INCLUDES_PATH . '/favicon-loader.php'; ?>
    <script src="../assets/js/theme-loader.js"></script>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Income - SAMS</title>
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
        <div class="cyber-header"><div class="page-icon-orb"><i class="fas fa-hand-holding-usd"></i></div><div><h1>Income</h1><p>Revenue streams and collection tracking</p></div></div>
        <div class="cyber-content">
            <div class="stats-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:24px;">
                <div class="card"><div class="card-body" style="text-align:center;"><h3 style="color:#22c55e;font-size:2rem;">$<?= number_format($feeIncome, 2) ?></h3><p>Fee Collections</p></div></div>
                <div class="card"><div class="card-body" style="text-align:center;"><h3 style="color:#3b82f6;font-size:2rem;">$<?= number_format($otherTotal, 2) ?></h3><p>Other Revenue</p></div></div>
                <div class="card"><div class="card-body" style="text-align:center;"><h3 style="color:#8b5cf6;font-size:2rem;">$<?= number_format($feeIncome + $otherTotal, 2) ?></h3><p>Total Income</p></div></div>
            </div>
            <div class="card"><div class="card-header"><h3>Recent Fee Payments</h3></div><div class="card-body" style="overflow-x:auto;">
                <table class="table"><thead><tr><th>Date</th><th>Student</th><th>Amount</th><th>Type</th><th>Method</th></tr></thead><tbody>
                <?php if (empty($recentPayments)): ?><tr><td colspan="5" style="text-align:center;padding:24px;">No income recorded.</td></tr>
                <?php else: foreach ($recentPayments as $p): ?>
                <tr><td><?= date('M j, Y', strtotime($p['payment_date'])) ?></td><td><?= htmlspecialchars($p['full_name'] ?? 'N/A') ?></td><td style="color:#22c55e;font-weight:bold;">$<?= number_format($p['amount'], 2) ?></td><td><?= ucfirst($p['payment_type'] ?? '') ?></td><td><?= ucfirst($p['payment_method'] ?? '') ?></td></tr>
                <?php endforeach; endif; ?></tbody></table>
            </div></div>
        </div>
    </main>
</div>
<script src="../assets/js/main.js"></script>
</body></html>
