<?php
session_start();
require_once '../includes/config.php';
require_once INCLUDES_PATH . '/functions.php';
require_once INCLUDES_PATH . '/database.php';
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['accountant', 'admin'])) { header('Location: ../login.php'); exit; }
$tenantId = $_SESSION['tenant_id'] ?? 1;
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $data = ['tenant_id' => $tenantId, 'entry_date' => trim($_POST['entry_date'] ?? date('Y-m-d')), 'description' => trim($_POST['description'] ?? ''), 'debit' => floatval($_POST['debit'] ?? 0), 'credit' => floatval($_POST['credit'] ?? 0), 'account' => trim($_POST['account'] ?? ''), 'reference' => trim($_POST['reference'] ?? ''), 'created_by' => $_SESSION['user_id'], 'created_at' => date('Y-m-d H:i:s')];
    try { insert_flexible('ledger_entries', $data); $msg = 'Ledger entry recorded.'; } catch (Exception $e) { $msg = 'Error recording entry.'; }
}
$entries = [];
try { $entries = db()->fetchAll("SELECT * FROM ledger_entries WHERE tenant_id = ? ORDER BY entry_date DESC, id DESC LIMIT 100", [$tenantId]); } catch (Exception $e) {}
$totalDebit = array_sum(array_column($entries, 'debit'));
$totalCredit = array_sum(array_column($entries, 'credit'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include INCLUDES_PATH . '/favicon-loader.php'; ?>
    <script src="../assets/js/theme-loader.js"></script>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>General Ledger - SAMS</title>
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
        <div class="cyber-header"><div class="page-icon-orb"><i class="fas fa-book"></i></div><div><h1>General Ledger</h1><p>Double-entry accounting records</p></div></div>
        <div class="cyber-content">
            <?php if ($msg): ?><div class="alert alert-info"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
            <div class="card" style="margin-bottom:24px;"><div class="card-header"><h3>New Entry</h3></div><div class="card-body">
                <form method="POST" class="form-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;align-items:end;">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    <div><label>Date</label><input type="date" name="entry_date" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
                    <div><label>Account</label><select name="account" class="form-control" required><option value="revenue">Revenue</option><option value="expenses">Expenses</option><option value="assets">Assets</option><option value="liabilities">Liabilities</option><option value="equity">Equity</option></select></div>
                    <div><label>Description</label><input type="text" name="description" class="form-control" required placeholder="Transaction description"></div>
                    <div><label>Debit ($)</label><input type="number" name="debit" class="form-control" step="0.01" min="0" value="0"></div>
                    <div><label>Credit ($)</label><input type="number" name="credit" class="form-control" step="0.01" min="0" value="0"></div>
                    <div><label>Reference</label><input type="text" name="reference" class="form-control" placeholder="Ref #"></div>
                    <div><button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Record</button></div>
                </form>
            </div></div>
            <div class="stats-grid" style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:24px;">
                <div class="card"><div class="card-body" style="text-align:center;"><h3 style="color:#22c55e;">$<?= number_format($totalDebit, 2) ?></h3><p>Total Debits</p></div></div>
                <div class="card"><div class="card-body" style="text-align:center;"><h3 style="color:#ef4444;">$<?= number_format($totalCredit, 2) ?></h3><p>Total Credits</p></div></div>
                <div class="card"><div class="card-body" style="text-align:center;"><h3 style="color:<?= ($totalDebit - $totalCredit) >= 0 ? '#22c55e' : '#ef4444' ?>;">$<?= number_format(abs($totalDebit - $totalCredit), 2) ?></h3><p>Balance</p></div></div>
            </div>
            <div class="card"><div class="card-body" style="overflow-x:auto;">
                <table class="table"><thead><tr><th>Date</th><th>Account</th><th>Description</th><th>Debit</th><th>Credit</th><th>Reference</th></tr></thead><tbody>
                <?php if (empty($entries)): ?><tr><td colspan="6" style="text-align:center;padding:24px;">No ledger entries.</td></tr>
                <?php else: foreach ($entries as $e): ?>
                <tr><td><?= date('M j, Y', strtotime($e['entry_date'])) ?></td><td><?= ucfirst(htmlspecialchars($e['account'] ?? '')) ?></td><td><?= htmlspecialchars($e['description'] ?? '') ?></td><td><?= $e['debit'] > 0 ? '$' . number_format($e['debit'], 2) : '-' ?></td><td><?= $e['credit'] > 0 ? '$' . number_format($e['credit'], 2) : '-' ?></td><td><code><?= htmlspecialchars($e['reference'] ?? '-') ?></code></td></tr>
                <?php endforeach; endif; ?></tbody></table>
            </div></div>
        </div>
    </main>
</div>
<script src="../assets/js/main.js"></script>
</body></html>
