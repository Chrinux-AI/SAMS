<?php
session_start();
require_once '../includes/config.php';
require_once INCLUDES_PATH . '/functions.php';
require_once INCLUDES_PATH . '/database.php';
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['accountant', 'admin'])) { header('Location: ../login.php'); exit; }
$tenantId = $_SESSION['tenant_id'] ?? 1;
// Assets = Debits - Credits for asset accounts
$assets = 0; $liabilities = 0; $equity = 0;
try {
    $row = db()->fetchOne("SELECT COALESCE(SUM(debit),0) - COALESCE(SUM(credit),0) as bal FROM ledger_entries WHERE tenant_id = ? AND account = 'assets'", [$tenantId]); $assets = $row['bal'] ?? 0;
    $row = db()->fetchOne("SELECT COALESCE(SUM(credit),0) - COALESCE(SUM(debit),0) as bal FROM ledger_entries WHERE tenant_id = ? AND account = 'liabilities'", [$tenantId]); $liabilities = $row['bal'] ?? 0;
    $row = db()->fetchOne("SELECT COALESCE(SUM(credit),0) - COALESCE(SUM(debit),0) as bal FROM ledger_entries WHERE tenant_id = ? AND account = 'equity'", [$tenantId]); $equity = $row['bal'] ?? 0;
} catch (Exception $e) {}
// Revenue and expenses for retained earnings
$revenue = 0; $totalExpenses = 0;
try {
    $row = db()->fetchOne("SELECT COALESCE(SUM(credit),0) - COALESCE(SUM(debit),0) as bal FROM ledger_entries WHERE tenant_id = ? AND account = 'revenue'", [$tenantId]); $revenue = $row['bal'] ?? 0;
    $row = db()->fetchOne("SELECT COALESCE(SUM(debit),0) - COALESCE(SUM(credit),0) as bal FROM ledger_entries WHERE tenant_id = ? AND account = 'expenses'", [$tenantId]); $totalExpenses = $row['bal'] ?? 0;
} catch (Exception $e) {}
// Also include fee_payments as revenue
try { $row = db()->fetchOne("SELECT COALESCE(SUM(amount),0) as total FROM fee_payments WHERE tenant_id = ?", [$tenantId]); $revenue += ($row['total'] ?? 0); } catch (Exception $e) {}
// And expenses table
try { $row = db()->fetchOne("SELECT COALESCE(SUM(amount),0) as total FROM expenses WHERE tenant_id = ?", [$tenantId]); $totalExpenses += ($row['total'] ?? 0); } catch (Exception $e) {}
$retainedEarnings = $revenue - $totalExpenses;
$totalLiabilitiesEquity = $liabilities + $equity + $retainedEarnings;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include INCLUDES_PATH . '/favicon-loader.php'; ?>
    <script src="../assets/js/theme-loader.js"></script>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Balance Sheet - SAMS</title>
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
        <div class="cyber-header"><div class="page-icon-orb"><i class="fas fa-balance-scale"></i></div><div><h1>Balance Sheet</h1><p>Assets, Liabilities & Equity as of <?= date('M j, Y') ?></p></div></div>
        <div class="cyber-content">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;">
                <div class="card"><div class="card-header"><h3>Assets</h3></div><div class="card-body">
                    <table class="table"><tbody>
                        <tr><td>Total Assets (Ledger)</td><td style="text-align:right;font-weight:bold;">$<?= number_format(max(0, $assets), 2) ?></td></tr>
                        <tr style="border-top:2px solid #333;"><td><strong>Total Assets</strong></td><td style="text-align:right;font-weight:bold;font-size:1.2rem;color:#22c55e;">$<?= number_format(max(0, $assets), 2) ?></td></tr>
                    </tbody></table>
                </div></div>
                <div class="card"><div class="card-header"><h3>Liabilities & Equity</h3></div><div class="card-body">
                    <table class="table"><tbody>
                        <tr><td>Liabilities</td><td style="text-align:right;">$<?= number_format($liabilities, 2) ?></td></tr>
                        <tr><td>Owner's Equity</td><td style="text-align:right;">$<?= number_format($equity, 2) ?></td></tr>
                        <tr><td>Retained Earnings</td><td style="text-align:right;">$<?= number_format($retainedEarnings, 2) ?></td></tr>
                        <tr style="border-top:2px solid #333;"><td><strong>Total L & E</strong></td><td style="text-align:right;font-weight:bold;font-size:1.2rem;color:#3b82f6;">$<?= number_format($totalLiabilitiesEquity, 2) ?></td></tr>
                    </tbody></table>
                </div></div>
            </div>
            <div class="card" style="margin-top:24px;"><div class="card-body" style="text-align:center;padding:24px;">
                <?php $balanced = abs($assets - $totalLiabilitiesEquity) < 0.01; ?>
                <h3><?= $balanced ? '<span style="color:#22c55e;"><i class="fas fa-check-circle"></i> Balance Sheet is Balanced</span>' : '<span style="color:#f59e0b;"><i class="fas fa-exclamation-triangle"></i> Variance: $' . number_format(abs($assets - $totalLiabilitiesEquity), 2) . '</span>' ?></h3>
            </div></div>
        </div>
    </main>
</div>
<script src="../assets/js/main.js"></script>
</body></html>
