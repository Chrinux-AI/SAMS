<?php
session_start();
require_once '../includes/config.php';
require_once INCLUDES_PATH . '/functions.php';
require_once INCLUDES_PATH . '/database.php';
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['bursar', 'admin'])) {
    header('Location: ../login.php');
    exit;
}
$csrf = generate_csrf_token();
$tenantId = $_SESSION['tenant_id'] ?? 1;
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $student_id = (int)($_POST['student_id'] ?? 0);
    $amount = (float)($_POST['amount'] ?? 0);
    $due_date = $_POST['due_date'] ?? date('Y-m-d', strtotime('+30 days'));
    $notes = htmlspecialchars(strip_tags($_POST['notes'] ?? ''));
    if ($student_id > 0 && $amount > 0) {
        $inv_num = 'INV-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        try {
            db()->insert('invoices', ['tenant_id' => $tenantId, 'invoice_number' => $inv_num, 'student_id' => $student_id, 'total_amount' => $amount, 'balance' => $amount, 'status' => 'unpaid', 'due_date' => $due_date, 'issued_by' => $_SESSION['user_id'], 'notes' => $notes, 'created_at' => date('Y-m-d H:i:s')]);
            $message = "Invoice {$inv_num} created for \${$amount}.";
            $message_type = 'success';
        } catch (Exception $e) {
            $message = 'Error creating invoice.';
            $message_type = 'danger';
        }
    }
}

$invoices = [];
try {
    $invoices = db()->fetchAll("SELECT i.*, u.full_name FROM invoices i LEFT JOIN users u ON i.student_id = u.id WHERE i.tenant_id = ? ORDER BY i.created_at DESC LIMIT 50", [$tenantId]);
} catch (Exception $e) {
}
$students = [];
try {
    $students = db()->fetchAll("SELECT id, full_name FROM users WHERE role = 'student' AND status = 'active' ORDER BY full_name LIMIT 500");
} catch (Exception $e) {
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php include INCLUDES_PATH . '/favicon-loader.php'; ?>
    <script src="../assets/js/theme-loader.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoices - SAMS</title>
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
            <div class="cyber-header">
                <div class="page-icon-orb"><i class="fas fa-file-invoice-dollar"></i></div>
                <div>
                    <h1>Invoices</h1>
                    <p>Create and manage student invoices</p>
                </div>
            </div>
            <div class="cyber-content">
                <?php if ($message): ?><div class="alert alert-<?= $message_type ?>"><?= htmlspecialchars($message) ?></div><?php endif; ?>
                <div class="card" style="margin-bottom:20px;">
                    <div class="card-body">
                        <h3 style="margin-bottom:16px;"><i class="fas fa-plus"></i> Create Invoice</h3>
                        <form method="POST"><input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                            <div style="display:grid; grid-template-columns:repeat(4,1fr); gap:12px; align-items:end;">
                                <div class="form-group"><label>Student</label><select name="student_id" class="form-control" required>
                                        <option value="">Select</option><?php foreach ($students as $s): ?><option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['full_name']) ?></option><?php endforeach; ?>
                                    </select></div>
                                <div class="form-group"><label>Amount</label><input type="number" name="amount" class="form-control" step="0.01" min="0.01" required></div>
                                <div class="form-group"><label>Due Date</label><input type="date" name="due_date" class="form-control" value="<?= date('Y-m-d', strtotime('+30 days')) ?>"></div>
                                <div class="form-group"><button type="submit" class="btn btn-primary"><i class="fas fa-file-invoice"></i> Create</button></div>
                            </div>
                            <div class="form-group"><label>Notes</label><input type="text" name="notes" class="form-control" maxlength="255"></div>
                        </form>
                    </div>
                </div>
                <div class="card">
                    <div class="card-body" style="overflow-x:auto;">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Invoice #</th>
                                    <th>Student</th>
                                    <th>Amount</th>
                                    <th>Paid</th>
                                    <th>Balance</th>
                                    <th>Due</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($invoices)): ?><tr>
                                        <td colspan="7" style="text-align:center;padding:24px;">No invoices yet.</td>
                                    </tr>
                                    <?php else: foreach ($invoices as $inv): ?>
                                        <tr>
                                            <td><code><?= htmlspecialchars($inv['invoice_number']) ?></code></td>
                                            <td><?= htmlspecialchars($inv['full_name'] ?? 'N/A') ?></td>
                                            <td>$<?= number_format($inv['total_amount'], 2) ?></td>
                                            <td>$<?= number_format($inv['paid_amount'], 2) ?></td>
                                            <td>$<?= number_format($inv['balance'], 2) ?></td>
                                            <td><?= $inv['due_date'] ? date('M j, Y', strtotime($inv['due_date'])) : '-' ?></td>
                                            <td><span class="badge badge-<?= match ($inv['status']) {
                                                                                'paid' => 'success',
                                                                                'partial' => 'warning',
                                                                                'cancelled' => 'secondary',
                                                                                default => 'danger'
                                                                            } ?>"><?= ucfirst($inv['status']) ?></span></td>
                                        </tr>
                                <?php endforeach;
                                endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
    <script src="../assets/js/main.js"></script>
</body>

</html>
