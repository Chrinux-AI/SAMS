<?php
session_start();
require_once '../includes/config.php';
require_once INCLUDES_PATH . '/functions.php';
require_once INCLUDES_PATH . '/database.php';
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['bursar', 'admin'])) { header('Location: ../login.php'); exit; }
$tenantId = $_SESSION['tenant_id'] ?? 1;
$plans = [];
// Payment plans are derived from invoices with partial payment status
try { $plans = db()->fetchAll("SELECT i.*, u.full_name FROM invoices i LEFT JOIN users u ON i.student_id = u.id WHERE i.tenant_id = ? AND i.status IN ('unpaid','partial') AND i.balance > 0 ORDER BY i.due_date ASC LIMIT 50", [$tenantId]); } catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include INCLUDES_PATH . '/favicon-loader.php'; ?>
    <script src="../assets/js/theme-loader.js"></script>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Plans - SAMS</title>
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
        <div class="cyber-header"><div class="page-icon-orb"><i class="fas fa-calendar-alt"></i></div><div><h1>Payment Plans</h1><p>Outstanding balances and payment schedules</p></div></div>
        <div class="cyber-content">
            <div class="card"><div class="card-body" style="overflow-x:auto;">
                <table class="table finance-table"><thead><tr><th>Invoice</th><th>Student</th><th>Total</th><th>Paid</th><th>Balance</th><th>Due Date</th><th>Status</th></tr></thead><tbody>
                <?php if (empty($plans)): ?><tr><td colspan="7" style="text-align:center;padding:24px;">No outstanding payment plans.</td></tr>
                <?php else: foreach ($plans as $p): $overdue = $p['due_date'] && strtotime($p['due_date']) < time(); ?>
                <tr><td><code><?= htmlspecialchars($p['invoice_number']) ?></code></td><td><?= htmlspecialchars($p['full_name'] ?? 'N/A') ?></td><td>$<?= number_format($p['total_amount'], 2) ?></td><td>$<?= number_format($p['paid_amount'], 2) ?></td><td><strong style="color:<?= $overdue ? '#ef4444' : 'inherit' ?>">$<?= number_format($p['balance'], 2) ?></strong></td><td><?= $p['due_date'] ? date('M j, Y', strtotime($p['due_date'])) : '-' ?><?php if ($overdue): ?> <span class="badge badge-danger">Overdue</span><?php endif; ?></td><td><span class="badge badge-<?= $p['status'] === 'partial' ? 'warning' : 'danger' ?>"><?= ucfirst($p['status']) ?></span></td></tr>
                <?php endforeach; endif; ?></tbody></table>
            </div></div>
        </div>
    </main>
</div>
<script src="../assets/js/main.js"></script>
</body></html>
