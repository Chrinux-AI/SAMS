<?php
session_start();
require_once '../includes/config.php';
require_once INCLUDES_PATH . '/functions.php';
require_once INCLUDES_PATH . '/database.php';
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['accountant', 'admin'])) { header('Location: ../login.php'); exit; }
$tenantId = $_SESSION['tenant_id'] ?? 1;
$period = $_GET['period'] ?? 'month';
$validPeriods = ['month', 'quarter', 'year'];
if (!in_array($period, $validPeriods)) $period = 'month';
$interval = match($period) { 'month' => 30, 'quarter' => 90, 'year' => 365 };
// Revenue
$feeRevenue = 0;
try { $row = db()->fetchOne("SELECT COALESCE(SUM(amount),0) as total FROM fee_payments WHERE tenant_id = ? AND payment_date >= DATE_SUB(CURDATE(), INTERVAL $interval DAY)", [$tenantId]); $feeRevenue = $row['total'] ?? 0; } catch (Exception $e) {}
$ledgerRevenue = 0;
try { $row = db()->fetchOne("SELECT COALESCE(SUM(credit),0) as total FROM ledger_entries WHERE tenant_id = ? AND account = 'revenue' AND entry_date >= DATE_SUB(CURDATE(), INTERVAL $interval DAY)", [$tenantId]); $ledgerRevenue = $row['total'] ?? 0; } catch (Exception $e) {}
$totalRevenue = $feeRevenue + $ledgerRevenue;
// Expenses
$tableExpenses = 0;
try { $row = db()->fetchOne("SELECT COALESCE(SUM(amount),0) as total FROM expenses WHERE tenant_id = ? AND expense_date >= DATE_SUB(CURDATE(), INTERVAL $interval DAY)", [$tenantId]); $tableExpenses = $row['total'] ?? 0; } catch (Exception $e) {}
$ledgerExpenses = 0;
try { $row = db()->fetchOne("SELECT COALESCE(SUM(debit),0) as total FROM ledger_entries WHERE tenant_id = ? AND account = 'expenses' AND entry_date >= DATE_SUB(CURDATE(), INTERVAL $interval DAY)", [$tenantId]); $ledgerExpenses = $row['total'] ?? 0; } catch (Exception $e) {}
$totalExpenses = $tableExpenses + $ledgerExpenses;
$netIncome = $totalRevenue - $totalExpenses;
// Expense breakdown
$expenseBreakdown = [];
try { $expenseBreakdown = db()->fetchAll("SELECT category, SUM(amount) as total FROM expenses WHERE tenant_id = ? AND expense_date >= DATE_SUB(CURDATE(), INTERVAL $interval DAY) GROUP BY category ORDER BY total DESC", [$tenantId]); } catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include INCLUDES_PATH . '/favicon-loader.php'; ?>
    <script src="../assets/js/theme-loader.js"></script>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profit & Loss - SAMS</title>
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
        <div class="cyber-header"><div class="page-icon-orb"><i class="fas fa-chart-line"></i></div><div><h1>Profit & Loss Statement</h1><p>Income vs Expenses analysis</p></div></div>
        <div class="cyber-content">
            <div style="margin-bottom:20px;display:flex;gap:8px;">
                <?php foreach ($validPeriods as $p): ?><a href="?period=<?= $p ?>" class="btn btn-<?= $period === $p ? 'primary' : 'secondary' ?>"><?= ucfirst($p) ?></a><?php endforeach; ?>
            </div>
            <div class="stats-grid" style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:24px;">
                <div class="card"><div class="card-body" style="text-align:center;"><h3 style="color:#22c55e;font-size:2rem;">$<?= number_format($totalRevenue, 2) ?></h3><p>Total Revenue</p></div></div>
                <div class="card"><div class="card-body" style="text-align:center;"><h3 style="color:#ef4444;font-size:2rem;">$<?= number_format($totalExpenses, 2) ?></h3><p>Total Expenses</p></div></div>
                <div class="card"><div class="card-body" style="text-align:center;"><h3 style="color:<?= $netIncome >= 0 ? '#22c55e' : '#ef4444' ?>;font-size:2rem;">$<?= number_format(abs($netIncome), 2) ?></h3><p><?= $netIncome >= 0 ? 'Net Profit' : 'Net Loss' ?></p></div></div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;">
                <div class="card"><div class="card-header"><h3>Revenue Breakdown</h3></div><div class="card-body">
                    <table class="table"><tbody>
                        <tr><td>Fee Collections</td><td style="text-align:right;">$<?= number_format($feeRevenue, 2) ?></td></tr>
                        <tr><td>Other Revenue (Ledger)</td><td style="text-align:right;">$<?= number_format($ledgerRevenue, 2) ?></td></tr>
                        <tr style="border-top:2px solid #333;font-weight:bold;"><td>Total</td><td style="text-align:right;color:#22c55e;">$<?= number_format($totalRevenue, 2) ?></td></tr>
                    </tbody></table>
                </div></div>
                <div class="card"><div class="card-header"><h3>Expense Breakdown</h3></div><div class="card-body">
                    <table class="table"><tbody>
                    <?php if (empty($expenseBreakdown)): ?><tr><td colspan="2" style="text-align:center;">No expenses recorded</td></tr>
                    <?php else: foreach ($expenseBreakdown as $eb): ?>
                    <tr><td><?= ucfirst(htmlspecialchars($eb['category'])) ?></td><td style="text-align:right;">$<?= number_format($eb['total'], 2) ?></td></tr>
                    <?php endforeach; endif; ?>
                    <tr style="border-top:2px solid #333;font-weight:bold;"><td>Total</td><td style="text-align:right;color:#ef4444;">$<?= number_format($totalExpenses, 2) ?></td></tr>
                    </tbody></table>
                </div></div>
            </div>
        </div>
    </main>
</div>
<script src="../assets/js/main.js"></script>
</body></html>
