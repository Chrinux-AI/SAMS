<?php
session_start();
require_once '../includes/config.php';
require_once INCLUDES_PATH . '/functions.php';
require_once INCLUDES_PATH . '/database.php';
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['accountant', 'admin'])) { header('Location: ../login.php'); exit; }
$tenantId = $_SESSION['tenant_id'] ?? 1;
// Tax summary from payroll deductions and expense categories
$payrollTax = 0;
try { $row = db()->fetchOne("SELECT COALESCE(SUM(deductions),0) as total FROM payroll WHERE tenant_id = ?", [$tenantId]); $payrollTax = $row['total'] ?? 0; } catch (Exception $e) {}
$taxableIncome = 0;
try { $row = db()->fetchOne("SELECT COALESCE(SUM(amount),0) as total FROM fee_payments WHERE tenant_id = ?", [$tenantId]); $taxableIncome = $row['total'] ?? 0; } catch (Exception $e) {}
$deductibleExpenses = 0;
try { $row = db()->fetchOne("SELECT COALESCE(SUM(amount),0) as total FROM expenses WHERE tenant_id = ?", [$tenantId]); $deductibleExpenses = $row['total'] ?? 0; } catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include INCLUDES_PATH . '/favicon-loader.php'; ?>
    <script src="../assets/js/theme-loader.js"></script>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tax Reports - SAMS</title>
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
        <div class="cyber-header"><div class="page-icon-orb"><i class="fas fa-file-invoice"></i></div><div><h1>Tax Reports</h1><p>Tax summaries and compliance</p></div></div>
        <div class="cyber-content">
            <div class="stats-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:24px;">
                <div class="card"><div class="card-body" style="text-align:center;"><h3 style="color:#3b82f6;font-size:2rem;">$<?= number_format($taxableIncome, 2) ?></h3><p>Gross Revenue</p></div></div>
                <div class="card"><div class="card-body" style="text-align:center;"><h3 style="color:#f59e0b;font-size:2rem;">$<?= number_format($deductibleExpenses, 2) ?></h3><p>Deductible Expenses</p></div></div>
                <div class="card"><div class="card-body" style="text-align:center;"><h3 style="color:#22c55e;font-size:2rem;">$<?= number_format(max(0, $taxableIncome - $deductibleExpenses), 2) ?></h3><p>Net Taxable</p></div></div>
                <div class="card"><div class="card-body" style="text-align:center;"><h3 style="color:#ef4444;font-size:2rem;">$<?= number_format($payrollTax, 2) ?></h3><p>Payroll Deductions</p></div></div>
            </div>
            <div class="card"><div class="card-header"><h3>Tax Summary</h3></div><div class="card-body">
                <table class="table"><tbody>
                    <tr><td>Gross Revenue (Fee Collections)</td><td style="text-align:right;">$<?= number_format($taxableIncome, 2) ?></td></tr>
                    <tr><td>Less: Deductible Expenses</td><td style="text-align:right;color:#ef4444;">($<?= number_format($deductibleExpenses, 2) ?>)</td></tr>
                    <tr style="border-top:2px solid #333;font-weight:bold;"><td>Net Taxable Income</td><td style="text-align:right;">$<?= number_format(max(0, $taxableIncome - $deductibleExpenses), 2) ?></td></tr>
                    <tr><td colspan="2" style="padding-top:24px;"><strong>Payroll Tax Withholdings</strong></td></tr>
                    <tr><td>Total Employee Deductions</td><td style="text-align:right;">$<?= number_format($payrollTax, 2) ?></td></tr>
                </tbody></table>
                <p style="margin-top:16px;color:#6b7280;font-style:italic;"><i class="fas fa-info-circle"></i> Tax rates and specific obligations vary by jurisdiction. Consult a tax professional for compliance advice.</p>
            </div></div>
        </div>
    </main>
</div>
<script src="../assets/js/main.js"></script>
</body></html>
