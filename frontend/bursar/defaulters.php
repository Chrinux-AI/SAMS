<?php
session_start();
require_once '../includes/config.php';
require_once INCLUDES_PATH . '/functions.php';
require_once INCLUDES_PATH . '/database.php';
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['bursar', 'admin'])) { header('Location: ../login.php'); exit; }
$tenantId = $_SESSION['tenant_id'] ?? 1;
$defaulters = [];
try {
    $defaulters = db()->fetchAll("SELECT i.*, u.full_name, u.email FROM invoices i LEFT JOIN users u ON i.student_id = u.id WHERE i.tenant_id = ? AND i.status IN ('unpaid','partial') AND i.due_date < CURDATE() ORDER BY i.balance DESC LIMIT 100", [$tenantId]);
} catch (Exception $e) {}
$totalOwed = array_sum(array_column($defaulters, 'balance'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include INCLUDES_PATH . '/favicon-loader.php'; ?>
    <script src="../assets/js/theme-loader.js"></script>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fee Defaulters - SAMS</title>
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
        <div class="cyber-header"><div class="page-icon-orb"><i class="fas fa-exclamation-triangle"></i></div><div><h1>Fee Defaulters</h1><p>Students with overdue payments</p></div></div>
        <div class="cyber-content">
            <div class="stats-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:24px;">
                <div class="card"><div class="card-body" style="text-align:center;"><h3 style="color:#ef4444;font-size:2rem;"><?= count($defaulters) ?></h3><p>Total Defaulters</p></div></div>
                <div class="card"><div class="card-body" style="text-align:center;"><h3 style="color:#f59e0b;font-size:2rem;">$<?= number_format($totalOwed, 2) ?></h3><p>Total Outstanding</p></div></div>
            </div>
            <div class="card"><div class="card-body" style="overflow-x:auto;">
                <table class="table finance-table"><thead><tr><th>Student</th><th>Email</th><th>Invoice</th><th>Total</th><th>Balance</th><th>Due Date</th><th>Days Overdue</th></tr></thead><tbody>
                <?php if (empty($defaulters)): ?><tr><td colspan="7" style="text-align:center;padding:24px;">No defaulters found.</td></tr>
                <?php else: foreach ($defaulters as $d): $daysOverdue = max(0, intval((time() - strtotime($d['due_date'])) / 86400)); ?>
                <tr><td><strong><?= htmlspecialchars($d['full_name'] ?? 'N/A') ?></strong></td><td><?= htmlspecialchars($d['email'] ?? '-') ?></td><td><code><?= htmlspecialchars($d['invoice_number']) ?></code></td><td>$<?= number_format($d['total_amount'], 2) ?></td><td style="color:#ef4444;font-weight:bold;">$<?= number_format($d['balance'], 2) ?></td><td><?= date('M j, Y', strtotime($d['due_date'])) ?></td><td><span class="badge badge-<?= $daysOverdue > 30 ? 'danger' : 'warning' ?>"><?= $daysOverdue ?> days</span></td></tr>
                <?php endforeach; endif; ?></tbody></table>
            </div></div>
        </div>
    </main>
</div>
<script src="../assets/js/main.js"></script>
</body></html>
