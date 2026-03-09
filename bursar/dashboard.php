<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_login('../login.php');

if (!has_role('bursar') && !has_role('admin')) {
    redirect('../login.php', 'Access denied. Bursar privileges required.', 'error');
}

$full_name = $_SESSION['full_name'] ?? 'User';

function brs_count($table, $where = '1=1', $params = []) {
    try { if (!table_exists($table)) return 0; return (int)db()->count($table, $where, $params); } catch (Throwable $e) { return 0; }
}
function brs_sum($table, $col, $where = '1=1', $params = []) {
    try {
        if (!table_exists($table)) return 0;
        $r = db()->fetchOne("SELECT COALESCE(SUM($col),0) AS total FROM $table WHERE $where", $params);
        return (float)($r['total'] ?? 0);
    } catch (Throwable $e) { return 0; }
}

$stats = [
    'total_students'    => brs_count('students'),
    'invoices'          => brs_count('fee_invoices'),
    'payments_today'    => brs_count('fee_payments', 'DATE(payment_date) = CURDATE()'),
    'total_collected'   => brs_sum('fee_payments', 'amount'),
    'pending_invoices'  => brs_count('fee_invoices', "status = 'pending'"),
    'defaulters'        => brs_count('fee_invoices', "status = 'overdue'"),
    'scholarships'      => brs_count('scholarships'),
];

$recent_payments = [];
try {
    if (table_exists('fee_payments')) {
        $recent_payments = db()->fetchAll("
            SELECT fp.*, u.first_name, u.last_name
            FROM fee_payments fp
            LEFT JOIN students s ON fp.student_id = s.id
            LEFT JOIN users u ON s.user_id = u.id
            ORDER BY fp.payment_date DESC LIMIT 10
        ") ?: [];
    }
} catch (Throwable $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include '../includes/favicon-loader.php'; ?>
    <script src="../assets/js/theme-loader.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bursar Dashboard - <?php echo APP_NAME; ?></title>
    <link rel="manifest" href="/attendance/manifest.json">
    <meta name="theme-color" content="#14b8a6">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="../assets/css/professional-ui.css" rel="stylesheet">
</head>
<body>
<div class="app-layout">
    <?php include '../includes/sidebar-nav.php'; ?>
    <main class="main-content">
        <header class="cyber-header">
            <div class="page-title-section">
                <div class="page-icon-orb" style="background:linear-gradient(135deg,#14b8a6,#0d9488)"><i class="fas fa-money-check-dollar"></i></div>
                <div>
                    <h1 class="page-title">Bursar Dashboard</h1>
                    <p class="page-subtitle">Fee collection & billing management</p>
                </div>
            </div>
        </header>
        <div class="cyber-content">
            <!-- KPI Cards -->
            <div class="stats-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:16px;margin-bottom:24px">
                <?php
                $kpis = [
                    ['Total Collected','coins',number_format($stats['total_collected'],2),'#14b8a6',true],
                    ['Invoices','file-invoice-dollar',$stats['invoices'],'#0ea5e9',false],
                    ['Payments Today','cash-register',$stats['payments_today'],'#10b981',false],
                    ['Pending','clock',$stats['pending_invoices'],'#f59e0b',false],
                    ['Defaulters','user-times',$stats['defaulters'],'#ef4444',false],
                    ['Scholarships','award',$stats['scholarships'],'#8b5cf6',false],
                ];
                foreach ($kpis as [$label,$icon,$val,$color,$isCurrency]): ?>
                <div class="cyber-card" style="padding:20px">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
                        <span style="font-size:.78rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;font-weight:600"><?php echo $label; ?></span>
                        <div style="width:36px;height:36px;border-radius:10px;background:<?php echo $color; ?>18;display:flex;align-items:center;justify-content:center"><i class="fas fa-<?php echo $icon; ?>" style="color:<?php echo $color; ?>;font-size:.85rem"></i></div>
                    </div>
                    <div style="font-size:1.8rem;font-weight:800;color:var(--text-primary)"><?php echo $isCurrency ? '₦' : ''; ?><?php echo is_numeric($val) ? number_format($val) : $val; ?></div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Quick Actions -->
            <div class="cyber-card" style="padding:20px;margin-bottom:24px">
                <h3 style="margin:0 0 16px;font-size:1rem;font-weight:700;color:var(--text-primary)"><i class="fas fa-bolt" style="color:#14b8a6;margin-right:8px"></i>Quick Actions</h3>
                <div style="display:flex;flex-wrap:wrap;gap:10px">
                    <a href="fee-collection.php" class="btn btn-primary" style="display:inline-flex;align-items:center;gap:6px"><i class="fas fa-cash-register"></i> Collect Fee</a>
                    <a href="invoices.php" class="btn btn-outline" style="display:inline-flex;align-items:center;gap:6px"><i class="fas fa-file-invoice-dollar"></i> Invoices</a>
                    <a href="defaulters.php" class="btn btn-outline" style="display:inline-flex;align-items:center;gap:6px;border-color:#ef4444;color:#ef4444"><i class="fas fa-user-times"></i> Defaulters (<?php echo $stats['defaulters']; ?>)</a>
                    <a href="daily-summary.php" class="btn btn-outline" style="display:inline-flex;align-items:center;gap:6px"><i class="fas fa-chart-pie"></i> Daily Summary</a>
                    <a href="receipts.php" class="btn btn-outline" style="display:inline-flex;align-items:center;gap:6px"><i class="fas fa-receipt"></i> Print Receipt</a>
                </div>
            </div>

            <!-- Recent Payments -->
            <div class="cyber-card" style="padding:20px">
                <h3 style="margin:0 0 16px;font-size:1rem;font-weight:700;color:var(--text-primary)"><i class="fas fa-clock-rotate-left" style="color:#0ea5e9;margin-right:8px"></i>Recent Payments</h3>
                <?php if (empty($recent_payments)): ?>
                    <p style="color:var(--text-muted);font-size:.9rem">No fee module tables found yet. Run the fee system setup SQL to enable full functionality.</p>
                    <div style="margin-top:12px;padding:16px;border-radius:12px;background:var(--bg-secondary);border:1px dashed var(--border-color)">
                        <p style="font-size:.85rem;color:var(--text-secondary);margin:0"><i class="fas fa-info-circle" style="color:#14b8a6;margin-right:6px"></i>Fee tables (<code>fee_invoices</code>, <code>fee_payments</code>, etc.) will be created from the migration files.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="data-table" style="width:100%">
                            <thead><tr><th>Student</th><th>Amount</th><th>Method</th><th>Date</th><th>Status</th></tr></thead>
                            <tbody>
                            <?php foreach ($recent_payments as $p): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars(($p['first_name'] ?? '') . ' ' . ($p['last_name'] ?? '')); ?></td>
                                    <td style="font-weight:700">₦<?php echo number_format($p['amount'] ?? 0, 2); ?></td>
                                    <td><?php echo ucfirst($p['payment_method'] ?? 'cash'); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($p['payment_date'])); ?></td>
                                    <td><span class="badge badge-success">Paid</span></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>
<script src="../assets/js/main.js"></script>
</body>
</html>
