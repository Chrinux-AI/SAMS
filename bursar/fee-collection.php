<?php
session_start();
require_once '../includes/config.php';
require_once INCLUDES_PATH . '/functions.php';
require_once INCLUDES_PATH . '/database.php';
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['bursar', 'admin'])) { header('Location: ../login.php'); exit; }
$csrf = generate_csrf_token();
$tenantId = $_SESSION['tenant_id'] ?? 1;
$message = ''; $message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $student_id = (int)($_POST['student_id'] ?? 0);
    $amount = (float)($_POST['amount'] ?? 0);
    $payment_type = htmlspecialchars(strip_tags($_POST['payment_type'] ?? 'tuition'));
    $method = htmlspecialchars(strip_tags($_POST['payment_method'] ?? 'cash'));
    $ref = htmlspecialchars(strip_tags($_POST['reference'] ?? ''));
    if ($student_id > 0 && $amount > 0) {
        try {
            db()->insert('fee_payments', ['tenant_id' => $tenantId, 'student_id' => $student_id, 'amount' => $amount, 'payment_type' => $payment_type, 'payment_method' => $method, 'reference_number' => $ref, 'status' => 'paid', 'received_by' => $_SESSION['user_id'], 'created_at' => date('Y-m-d H:i:s')]);
            $message = "Payment of $" . number_format($amount, 2) . " recorded successfully."; $message_type = 'success';
            Logger::audit('fee_collected', $_SESSION['user_id'], ['student_id' => $student_id, 'amount' => $amount]);
        } catch (Exception $e) { $message = 'Error recording payment.'; $message_type = 'danger'; }
    } else { $message = 'Please fill all required fields.'; $message_type = 'danger'; }
}

$recent = [];
try { $recent = db()->fetchAll("SELECT fp.*, u.full_name FROM fee_payments fp LEFT JOIN users u ON fp.student_id = u.id WHERE fp.tenant_id = ? ORDER BY fp.created_at DESC LIMIT 20", [$tenantId]); } catch (Exception $e) {}
$students = [];
try { $students = db()->fetchAll("SELECT id, full_name FROM users WHERE role = 'student' AND status = 'active' ORDER BY full_name LIMIT 500"); } catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include INCLUDES_PATH . '/favicon-loader.php'; ?>
    <script src="../assets/js/theme-loader.js"></script>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fee Collection - SAMS</title>
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
        <div class="cyber-header"><div class="page-icon-orb"><i class="fas fa-cash-register"></i></div><div><h1>Fee Collection</h1><p>Record student fee payments</p></div></div>
        <div class="cyber-content">
            <?php if ($message): ?><div class="alert alert-<?= $message_type ?>"><?= htmlspecialchars($message) ?></div><?php endif; ?>
            <div class="card" style="margin-bottom:20px;"><div class="card-body">
                <h3 style="margin-bottom:16px;"><i class="fas fa-plus-circle"></i> Record Payment</h3>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:12px;">
                        <div class="form-group"><label>Student</label><select name="student_id" class="form-control" required><option value="">Select Student</option><?php foreach ($students as $s): ?><option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['full_name']) ?></option><?php endforeach; ?></select></div>
                        <div class="form-group"><label>Amount</label><input type="number" name="amount" class="form-control" step="0.01" min="0.01" required></div>
                        <div class="form-group"><label>Payment Type</label><select name="payment_type" class="form-control"><option value="tuition">Tuition</option><option value="exam">Exam Fee</option><option value="library">Library</option><option value="transport">Transport</option><option value="other">Other</option></select></div>
                        <div class="form-group"><label>Method</label><select name="payment_method" class="form-control"><option value="cash">Cash</option><option value="bank_transfer">Bank Transfer</option><option value="mpesa">M-Pesa</option><option value="card">Card</option></select></div>
                        <div class="form-group"><label>Reference #</label><input type="text" name="reference" class="form-control" maxlength="100"></div>
                        <div class="form-group" style="display:flex; align-items:end;"><button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Record Payment</button></div>
                    </div>
                </form>
            </div></div>
            <div class="section-title"><i class="fas fa-history"></i> Recent Payments</div>
            <div class="card"><div class="card-body" style="overflow-x:auto;">
                <table class="table"><thead><tr><th>Date</th><th>Student</th><th>Amount</th><th>Type</th><th>Method</th><th>Reference</th><th>Status</th></tr></thead><tbody>
                <?php if (empty($recent)): ?><tr><td colspan="7" style="text-align:center;padding:24px;">No payments recorded yet.</td></tr>
                <?php else: foreach ($recent as $r): ?>
                <tr><td><?= date('M j, Y', strtotime($r['created_at'])) ?></td><td><?= htmlspecialchars($r['full_name'] ?? 'N/A') ?></td><td><strong>$<?= number_format($r['amount'], 2) ?></strong></td><td><?= ucfirst($r['payment_type'] ?? '') ?></td><td><?= ucfirst(str_replace('_', ' ', $r['payment_method'] ?? '')) ?></td><td><code><?= htmlspecialchars($r['reference_number'] ?? '-') ?></code></td><td><span class="badge badge-success"><?= ucfirst($r['status']) ?></span></td></tr>
                <?php endforeach; endif; ?></tbody></table>
            </div></div>
        </div>
    </main>
</div>
<script src="../assets/js/main.js"></script>
</body></html>
