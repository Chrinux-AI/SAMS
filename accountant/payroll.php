<?php
session_start();
require_once '../includes/config.php';
require_once INCLUDES_PATH . '/functions.php';
require_once INCLUDES_PATH . '/database.php';
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['accountant', 'admin'])) { header('Location: ../login.php'); exit; }
$tenantId = $_SESSION['tenant_id'] ?? 1;
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $data = ['tenant_id' => $tenantId, 'employee_id' => intval($_POST['employee_id'] ?? 0), 'pay_period' => trim($_POST['pay_period'] ?? ''), 'basic_salary' => floatval($_POST['basic_salary'] ?? 0), 'allowances' => floatval($_POST['allowances'] ?? 0), 'deductions' => floatval($_POST['deductions'] ?? 0), 'net_pay' => floatval($_POST['basic_salary'] ?? 0) + floatval($_POST['allowances'] ?? 0) - floatval($_POST['deductions'] ?? 0), 'status' => 'pending', 'created_at' => date('Y-m-d H:i:s')];
    try { insert_flexible('payroll', $data); $msg = 'Payroll entry added.'; } catch (Exception $e) { $msg = 'Error adding payroll entry.'; }
}
$payroll = [];
try { $payroll = db()->fetchAll("SELECT p.*, u.full_name FROM payroll p LEFT JOIN users u ON p.employee_id = u.id WHERE p.tenant_id = ? ORDER BY p.created_at DESC LIMIT 100", [$tenantId]); } catch (Exception $e) {}
$staff = [];
try { $staff = db()->fetchAll("SELECT id, full_name, role FROM users WHERE tenant_id = ? AND role IN ('teacher','admin','bursar','accountant','librarian') ORDER BY full_name", [$tenantId]); } catch (Exception $e) {}
$totalNet = array_sum(array_column($payroll, 'net_pay'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include INCLUDES_PATH . '/favicon-loader.php'; ?>
    <script src="../assets/js/theme-loader.js"></script>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payroll - SAMS</title>
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
        <div class="cyber-header"><div class="page-icon-orb"><i class="fas fa-money-check-alt"></i></div><div><h1>Payroll</h1><p>Staff salary management</p></div></div>
        <div class="cyber-content">
            <?php if ($msg): ?><div class="alert alert-info"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
            <div class="card" style="margin-bottom:24px;"><div class="card-header"><h3>Add Payroll Entry</h3></div><div class="card-body">
                <form method="POST" class="form-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;align-items:end;">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    <div><label>Employee</label><select name="employee_id" class="form-control" required><option value="">Select Staff</option><?php foreach ($staff as $s): ?><option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['full_name']) ?> (<?= ucfirst($s['role']) ?>)</option><?php endforeach; ?></select></div>
                    <div><label>Pay Period</label><input type="text" name="pay_period" class="form-control" required placeholder="e.g. Jan 2025"></div>
                    <div><label>Basic Salary ($)</label><input type="number" name="basic_salary" class="form-control" step="0.01" min="0" required></div>
                    <div><label>Allowances ($)</label><input type="number" name="allowances" class="form-control" step="0.01" min="0" value="0"></div>
                    <div><label>Deductions ($)</label><input type="number" name="deductions" class="form-control" step="0.01" min="0" value="0"></div>
                    <div><button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Add</button></div>
                </form>
            </div></div>
            <div class="card" style="margin-bottom:16px;"><div class="card-body" style="text-align:center;"><h3>Total Net Pay: <span style="color:#22c55e;">$<?= number_format($totalNet, 2) ?></span></h3></div></div>
            <div class="card"><div class="card-body" style="overflow-x:auto;">
                <table class="table"><thead><tr><th>Employee</th><th>Period</th><th>Basic</th><th>Allowances</th><th>Deductions</th><th>Net Pay</th><th>Status</th></tr></thead><tbody>
                <?php if (empty($payroll)): ?><tr><td colspan="7" style="text-align:center;padding:24px;">No payroll entries.</td></tr>
                <?php else: foreach ($payroll as $p): ?>
                <tr><td><?= htmlspecialchars($p['full_name'] ?? 'N/A') ?></td><td><?= htmlspecialchars($p['pay_period'] ?? '') ?></td><td>$<?= number_format($p['basic_salary'], 2) ?></td><td>$<?= number_format($p['allowances'], 2) ?></td><td>$<?= number_format($p['deductions'], 2) ?></td><td style="font-weight:bold;">$<?= number_format($p['net_pay'], 2) ?></td><td><span class="badge badge-<?= ($p['status'] ?? '') === 'paid' ? 'success' : 'warning' ?>"><?= ucfirst($p['status'] ?? 'pending') ?></span></td></tr>
                <?php endforeach; endif; ?></tbody></table>
            </div></div>
        </div>
    </main>
</div>
<script src="../assets/js/main.js"></script>
</body></html>
