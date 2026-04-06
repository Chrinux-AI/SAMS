<?php
session_start();
require_once '../includes/config.php';
require_once INCLUDES_PATH . '/functions.php';
require_once INCLUDES_PATH . '/database.php';
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['bursar', 'admin'])) { header('Location: ../login.php'); exit; }
$tenantId = $_SESSION['tenant_id'] ?? 1;
$exportType = $_POST['export_type'] ?? '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '') && in_array($exportType, ['payments', 'invoices', 'defaulters'])) {
    $filename = "bursar_{$exportType}_" . date('Ymd_His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $output = fopen('php://output', 'w');
    try {
        switch ($exportType) {
            case 'payments':
                fputcsv($output, ['Date', 'Student', 'Amount', 'Type', 'Method', 'Reference']);
                $rows = db()->fetchAll("SELECT fp.payment_date, u.full_name, fp.amount, fp.payment_type, fp.payment_method, fp.reference_number FROM fee_payments fp LEFT JOIN users u ON fp.student_id = u.id WHERE fp.tenant_id = ? ORDER BY fp.payment_date DESC LIMIT 5000", [$tenantId]);
                foreach ($rows as $r) { fputcsv($output, [$r['payment_date'], $r['full_name'] ?? '', $r['amount'], $r['payment_type'] ?? '', $r['payment_method'] ?? '', $r['reference_number'] ?? '']); }
                break;
            case 'invoices':
                fputcsv($output, ['Invoice #', 'Student', 'Total', 'Paid', 'Balance', 'Status', 'Due Date']);
                $rows = db()->fetchAll("SELECT i.invoice_number, u.full_name, i.total_amount, i.paid_amount, i.balance, i.status, i.due_date FROM invoices i LEFT JOIN users u ON i.student_id = u.id WHERE i.tenant_id = ? ORDER BY i.created_at DESC LIMIT 5000", [$tenantId]);
                foreach ($rows as $r) { fputcsv($output, [$r['invoice_number'], $r['full_name'] ?? '', $r['total_amount'], $r['paid_amount'], $r['balance'], $r['status'], $r['due_date'] ?? '']); }
                break;
            case 'defaulters':
                fputcsv($output, ['Student', 'Email', 'Invoice', 'Balance', 'Due Date', 'Days Overdue']);
                $rows = db()->fetchAll("SELECT u.full_name, u.email, i.invoice_number, i.balance, i.due_date FROM invoices i LEFT JOIN users u ON i.student_id = u.id WHERE i.tenant_id = ? AND i.status IN ('unpaid','partial') AND i.due_date < CURDATE() ORDER BY i.balance DESC LIMIT 5000", [$tenantId]);
                foreach ($rows as $r) { $days = max(0, intval((time() - strtotime($r['due_date'])) / 86400)); fputcsv($output, [$r['full_name'] ?? '', $r['email'] ?? '', $r['invoice_number'], $r['balance'], $r['due_date'], $days]); }
                break;
        }
    } catch (Exception $e) { fputcsv($output, ['Error exporting data']); }
    fclose($output);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include INCLUDES_PATH . '/favicon-loader.php'; ?>
    <script src="../assets/js/theme-loader.js"></script>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Export Data - SAMS</title>
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
        <div class="cyber-header"><div class="page-icon-orb"><i class="fas fa-download"></i></div><div><h1>Export Data</h1><p>Download financial data as CSV</p></div></div>
        <div class="cyber-content">
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:24px;">
                <?php $exports = [['payments', 'fa-money-bill', 'Payment Records', 'Export all fee payments with student details, amounts, and payment methods.'], ['invoices', 'fa-file-invoice', 'Invoice Data', 'Export all invoices with totals, balances, and payment status.'], ['defaulters', 'fa-exclamation-circle', 'Defaulter List', 'Export list of students with overdue payments and amounts owed.']]; ?>
                <?php foreach ($exports as [$type, $icon, $title, $desc]): ?>
                <div class="card"><div class="card-body" style="text-align:center;padding:32px;">
                    <i class="fas <?= $icon ?>" style="font-size:3rem;color:#3b82f6;margin-bottom:16px;"></i>
                    <h3><?= $title ?></h3><p style="margin:12px 0;color:#6b7280;"><?= $desc ?></p>
                    <form method="POST"><input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>"><input type="hidden" name="export_type" value="<?= $type ?>"><button type="submit" class="btn btn-primary"><i class="fas fa-download"></i> Download CSV</button></form>
                </div></div>
                <?php endforeach; ?>
            </div>
        </div>
    </main>
</div>
<script src="../assets/js/main.js"></script>
</body></html>
