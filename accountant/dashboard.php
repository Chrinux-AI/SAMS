<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_login('../login.php');

if (!has_role('accountant') && !has_role('admin')) {
    redirect('../login.php', 'Access denied. Accountant privileges required.', 'error');
}

$full_name = $_SESSION['full_name'] ?? 'User';

function acc_count($table, $where = '1=1', $params = []) {
    try { if (!table_exists($table)) return 0; return (int)db()->count($table, $where, $params); } catch (Throwable $e) { return 0; }
}
function acc_sum($table, $col, $where = '1=1', $params = []) {
    try {
        if (!table_exists($table)) return 0;
        $r = db()->fetchOne("SELECT COALESCE(SUM($col),0) AS total FROM $table WHERE $where", $params);
        return (float)($r['total'] ?? 0);
    } catch (Throwable $e) { return 0; }
}

$stats = [
    'total_income'     => acc_sum('fee_payments', 'amount'),
    'total_expenses'   => acc_sum('expenses', 'amount'),
    'payroll_total'    => acc_sum('payroll', 'amount'),
    'ledger_entries'   => acc_count('ledger_entries'),
    'pending_approvals'=> acc_count('expense_approvals', "status = 'pending'"),
    'budget_items'     => acc_count('budget_items'),
];

$net = $stats['total_income'] - $stats['total_expenses'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include '../includes/favicon-loader.php'; ?>
    <script src="../assets/js/theme-loader.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accountant Dashboard - <?php echo APP_NAME; ?></title>
    <link rel="manifest" href="/attendance/manifest.json">
    <meta name="theme-color" content="#6366f1">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="../assets/css/professional-ui.css" rel="stylesheet">
</head>
<body>
<div class="app-layout">
    <?php include '../includes/sidebar-nav.php'; ?>
    <main class="main-content">
        <header class="cyber-header">
            <div class="page-title-section">
                <div class="page-icon-orb" style="background:linear-gradient(135deg,#6366f1,#4f46e5)"><i class="fas fa-calculator"></i></div>
                <div>
                    <h1 class="page-title">Accountant Dashboard</h1>
                    <p class="page-subtitle">Financial operations & reporting</p>
                </div>
            </div>
        </header>
        <div class="cyber-content">
            <!-- Financial Summary -->
            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:24px">
                <div class="cyber-card" style="padding:24px;border-left:4px solid #10b981">
                    <div style="font-size:.78rem;color:var(--text-muted);text-transform:uppercase;font-weight:600;margin-bottom:4px">Total Income</div>
                    <div style="font-size:2rem;font-weight:900;color:#10b981">₦<?php echo number_format($stats['total_income'], 2); ?></div>
                </div>
                <div class="cyber-card" style="padding:24px;border-left:4px solid #ef4444">
                    <div style="font-size:.78rem;color:var(--text-muted);text-transform:uppercase;font-weight:600;margin-bottom:4px">Total Expenses</div>
                    <div style="font-size:2rem;font-weight:900;color:#ef4444">₦<?php echo number_format($stats['total_expenses'], 2); ?></div>
                </div>
                <div class="cyber-card" style="padding:24px;border-left:4px solid <?php echo $net >= 0 ? '#10b981' : '#ef4444'; ?>">
                    <div style="font-size:.78rem;color:var(--text-muted);text-transform:uppercase;font-weight:600;margin-bottom:4px">Net Balance</div>
                    <div style="font-size:2rem;font-weight:900;color:<?php echo $net >= 0 ? '#10b981' : '#ef4444'; ?>">₦<?php echo number_format($net, 2); ?></div>
                </div>
            </div>

            <!-- Secondary KPIs -->
            <div class="stats-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:16px;margin-bottom:24px">
                <?php
                $kpis = [
                    ['Payroll','money-check-alt',number_format($stats['payroll_total'],2),'#8b5cf6',true],
                    ['Ledger Entries','book',$stats['ledger_entries'],'#0ea5e9',false],
                    ['Pending Approvals','clock',$stats['pending_approvals'],'#f59e0b',false],
                    ['Budget Items','piggy-bank',$stats['budget_items'],'#14b8a6',false],
                ];
                foreach ($kpis as [$label,$icon,$val,$color,$isCurrency]): ?>
                <div class="cyber-card" style="padding:20px">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
                        <span style="font-size:.78rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;font-weight:600"><?php echo $label; ?></span>
                        <div style="width:36px;height:36px;border-radius:10px;background:<?php echo $color; ?>18;display:flex;align-items:center;justify-content:center"><i class="fas fa-<?php echo $icon; ?>" style="color:<?php echo $color; ?>;font-size:.85rem"></i></div>
                    </div>
                    <div style="font-size:1.5rem;font-weight:800;color:var(--text-primary)"><?php echo $isCurrency ? '₦' : ''; ?><?php echo $val; ?></div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Quick Actions -->
            <div class="cyber-card" style="padding:20px;margin-bottom:24px">
                <h3 style="margin:0 0 16px;font-size:1rem;font-weight:700;color:var(--text-primary)"><i class="fas fa-bolt" style="color:#6366f1;margin-right:8px"></i>Quick Actions</h3>
                <div style="display:flex;flex-wrap:wrap;gap:10px">
                    <a href="ledger.php" class="btn btn-primary" style="display:inline-flex;align-items:center;gap:6px"><i class="fas fa-book"></i> General Ledger</a>
                    <a href="expenses.php" class="btn btn-outline" style="display:inline-flex;align-items:center;gap:6px"><i class="fas fa-receipt"></i> Record Expense</a>
                    <a href="payroll.php" class="btn btn-outline" style="display:inline-flex;align-items:center;gap:6px"><i class="fas fa-money-check-alt"></i> Payroll</a>
                    <a href="balance-sheet.php" class="btn btn-outline" style="display:inline-flex;align-items:center;gap:6px"><i class="fas fa-balance-scale"></i> Balance Sheet</a>
                    <a href="profit-loss.php" class="btn btn-outline" style="display:inline-flex;align-items:center;gap:6px"><i class="fas fa-chart-line"></i> P&L Statement</a>
                    <a href="reports.php" class="btn btn-outline" style="display:inline-flex;align-items:center;gap:6px"><i class="fas fa-chart-bar"></i> Reports</a>
                </div>
            </div>

            <!-- Info Box -->
            <div class="cyber-card" style="padding:20px">
                <h3 style="margin:0 0 16px;font-size:1rem;font-weight:700;color:var(--text-primary)"><i class="fas fa-info-circle" style="color:#6366f1;margin-right:8px"></i>Module Status</h3>
                <div style="padding:16px;border-radius:12px;background:var(--bg-secondary);border:1px dashed var(--border-color)">
                    <p style="font-size:.85rem;color:var(--text-secondary);margin:0">The accounting module tracks income, expenses, payroll, and generates financial statements. Tables like <code>ledger_entries</code>, <code>expenses</code>, <code>payroll</code>, <code>budget_items</code> will be populated as financial operations are recorded.</p>
                </div>
            </div>
        </div>
    </main>
</div>
<script src="../assets/js/main.js"></script>
</body>
</html>
