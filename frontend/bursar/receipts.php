<?php
session_start();
require_once '../includes/config.php';
require_once INCLUDES_PATH . '/functions.php';
require_once INCLUDES_PATH . '/database.php';
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['bursar', 'admin'])) { header('Location: ../login.php'); exit; }
$tenantId = $_SESSION['tenant_id'] ?? 1;
$receipts = [];
try { $receipts = db()->fetchAll("SELECT fp.*, u.full_name FROM fee_payments fp LEFT JOIN users u ON fp.student_id = u.id WHERE fp.tenant_id = ? AND fp.status = 'paid' ORDER BY fp.created_at DESC LIMIT 50", [$tenantId]); } catch (Exception $e) {}
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
    <link rel="stylesheet" href="../assets/css/sidebar-nav.css">
    <link rel="stylesheet" href="../assets/css/sams-theme-system.css">
    <link rel="stylesheet" href="../assets/css/sams-layout.css">
</head>
<body>
<div class="app-layout">
    <?php include INCLUDES_PATH . '/sidebar-nav.php'; ?>
    <main class="main-content">
        <div class="cyber-header"><div class="page-icon-orb"><i class="fas fa-receipt"></i></div><div><h1>Payment Receipts</h1><p><?= count($receipts) ?> receipts found</p></div></div>
        <div class="cyber-content">
            <div class="card"><div class="card-body" style="overflow-x:auto;">
                <table class="table"><thead><tr><th>Receipt #</th><th>Date</th><th>Student</th><th>Amount</th><th>Type</th><th>Method</th><th>Reference</th></tr></thead><tbody>
                <?php if (empty($receipts)): ?><tr><td colspan="7" style="text-align:center;padding:24px;">No receipts found.</td></tr>
                <?php else: foreach ($receipts as $i => $r): ?>
                <tr><td><code>RCP-<?= str_pad($r['id'], 6, '0', STR_PAD_LEFT) ?></code></td><td><?= date('M j, Y', strtotime($r['created_at'])) ?></td><td><?= htmlspecialchars($r['full_name'] ?? 'N/A') ?></td><td><strong>$<?= number_format($r['amount'], 2) ?></strong></td><td><?= ucfirst($r['payment_type'] ?? '') ?></td><td><?= ucfirst(str_replace('_', ' ', $r['payment_method'] ?? '')) ?></td><td><?= htmlspecialchars($r['reference_number'] ?? '-') ?></td></tr>
                <?php endforeach; endif; ?></tbody></table>
            </div></div>
        </div>
    </main>
</div>
<script src="../assets/js/main.js"></script>
</body></html>
