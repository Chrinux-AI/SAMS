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

function bursar_fee_scope_sql(string $alias = ''): string
{
    $prefix = $alias !== '' ? $alias . '.' : '';
    if (table_has_column('fee_payments', 'tenant_id')) {
        return " AND {$prefix}tenant_id = ?";
    }
    if (table_has_column('fee_payments', 'school_id')) {
        return " AND {$prefix}school_id = ?";
    }
    return '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $student_id = (int)($_POST['student_id'] ?? 0);
    $amount = (float)($_POST['amount'] ?? 0);
    $payment_type = htmlspecialchars(strip_tags($_POST['payment_type'] ?? 'tuition'));
    $method = htmlspecialchars(strip_tags($_POST['payment_method'] ?? 'cash'));
    $ref = htmlspecialchars(strip_tags($_POST['reference'] ?? ''));
    if ($student_id > 0 && $amount > 0) {
        try {
            $invoice = table_exists('invoices')
                ? db()->fetchOne("SELECT id, paid_amount, balance FROM invoices WHERE tenant_id = ? AND student_id = ? AND status IN ('unpaid', 'partial') ORDER BY due_date ASC, created_at ASC LIMIT 1", [$tenantId, $student_id])
                : null;

            $paymentData = [
                'tenant_id' => $tenantId,
                'school_id' => $tenantId,
                'student_id' => $student_id,
                'fee_id' => $invoice['id'] ?? null,
                'amount' => $amount,
                'amount_paid' => $amount,
                'payment_type' => $payment_type,
                'payment_method' => $method,
                'reference_number' => $ref,
                'payment_reference' => $ref,
                'transaction_id' => $ref !== '' ? $ref : ('PAY-' . date('YmdHis')),
                'status' => 'paid',
                'payment_status' => 'completed',
                'received_by' => $_SESSION['user_id'],
                'payment_date' => date('Y-m-d H:i:s'),
                'created_at' => date('Y-m-d H:i:s'),
                'notes' => ucfirst($payment_type) . ' payment recorded from bursar collection page'
            ];
            insert_flexible('fee_payments', $paymentData);

            if ($invoice && table_exists('invoices')) {
                $newPaid = (float)($invoice['paid_amount'] ?? 0) + $amount;
                $newBalance = max(0, (float)($invoice['balance'] ?? 0) - $amount);
                $newStatus = $newBalance <= 0 ? 'paid' : 'partial';
                update_flexible('invoices', ['paid_amount' => $newPaid, 'balance' => $newBalance, 'status' => $newStatus], 'id = ? AND tenant_id = ?', [(int)$invoice['id'], $tenantId]);
            }

            $message = "Payment of $" . number_format($amount, 2) . " recorded successfully.";
            $message_type = 'success';
            Logger::audit('fee_collected', $_SESSION['user_id'], ['student_id' => $student_id, 'amount' => $amount]);
        } catch (Exception $e) {
            $message = 'Error recording payment.';
            $message_type = 'danger';
        }
    } else {
        $message = 'Please fill all required fields.';
        $message_type = 'danger';
    }
}

$recent = [];
try {
    $amountExpr = table_has_column('fee_payments', 'amount_paid') ? 'fp.amount_paid' : 'fp.amount';
    $typeExpr = table_has_column('fee_payments', 'payment_type') ? 'fp.payment_type' : 'fp.payment_status';
    $referenceExpr = table_has_column('fee_payments', 'reference_number') ? 'fp.reference_number' : (table_has_column('fee_payments', 'payment_reference') ? 'fp.payment_reference' : 'fp.transaction_id');
    $statusExpr = table_has_column('fee_payments', 'status') ? 'fp.status' : 'fp.payment_status';
    $dateExpr = table_has_column('fee_payments', 'payment_date') ? 'fp.payment_date' : 'fp.created_at';
    $joins = '';
    if (table_has_column('fee_payments', 'student_id')) {
        $joins = ' LEFT JOIN users u ON fp.student_id = u.id';
    } elseif (table_has_column('fee_payments', 'fee_id') && table_exists('invoices')) {
        $joins = ' LEFT JOIN invoices i ON fp.fee_id = i.id LEFT JOIN users u ON i.student_id = u.id';
    }
    $scope = bursar_fee_scope_sql('fp');
    $recent = db()->fetchAll("SELECT fp.*, u.full_name, {$amountExpr} AS display_amount, {$typeExpr} AS display_type, {$referenceExpr} AS display_reference, {$statusExpr} AS display_status, {$dateExpr} AS display_date FROM fee_payments fp{$joins} WHERE 1=1 {$scope} ORDER BY {$dateExpr} DESC LIMIT 20", [$tenantId]);
} catch (Exception $e) {
}
$students = [];
try {
    $students = db()->fetchAll("SELECT id, full_name FROM users WHERE role = 'student' AND status = 'active' AND (tenant_id = ? OR tenant_id IS NULL) ORDER BY full_name LIMIT 500", [$tenantId]);
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
    <title>Fee Collection - SAMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/professional-ui.css">
    <?php include '../includes/sams-head-bootstrap.php'; ?>

    <link rel="stylesheet" href="../assets/css/sidebar-nav.css">
    <link rel="stylesheet" href="../assets/css/sams-theme-system.css">
    <link rel="stylesheet" href="../assets/css/sams-layout.css">
    <style>
        .finance-table th,
        .finance-table td {
            padding: 0.875rem 1rem;
            vertical-align: middle;
            white-space: nowrap;
        }

        .finance-table th {
            color: #475569;
            font-size: 0.72rem;
            font-weight: 800;
            letter-spacing: 0;
            text-transform: uppercase;
            background: #f8fafc;
        }

        .bursar-form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1rem;
            align-items: end;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.4rem;
            color: #475569;
            font-size: 0.78rem;
            font-weight: 800;
            letter-spacing: 0;
            text-transform: uppercase;
        }

        .form-control {
            width: 100%;
            min-height: 44px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            background: #fff;
            color: #0f172a;
            padding: 0.65rem 0.75rem;
            font: inherit;
        }

        .form-control:focus {
            border-color: #4f46e5;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.12);
            outline: none;
        }
    </style>
</head>

<body>
    <div class="app-layout">
        <?php include INCLUDES_PATH . '/sidebar-nav.php'; ?>
        <main class="main-content">
            <div class="cyber-header">
                <div class="page-icon-orb"><i class="fas fa-cash-register"></i></div>
                <div>
                    <h1>Fee Collection</h1>
                    <p>Record student fee payments</p>
                </div>
            </div>
            <div class="cyber-content">
                <?php if ($message): ?><div class="alert alert-<?= $message_type ?>"><?= htmlspecialchars($message) ?></div><?php endif; ?>
                <div class="card" style="margin-bottom:20px;">
                    <div class="card-body">
                        <h3 style="margin-bottom:16px;"><i class="fas fa-plus-circle"></i> Record Payment</h3>
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                            <div class="bursar-form-grid">
                                <div class="form-group"><label>Student</label><select name="student_id" class="form-control" required>
                                        <option value="">Select Student</option><?php foreach ($students as $s): ?><option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['full_name']) ?></option><?php endforeach; ?>
                                    </select></div>
                                <div class="form-group"><label>Amount</label><input type="number" name="amount" class="form-control" step="0.01" min="0.01" required></div>
                                <div class="form-group"><label>Payment Type</label><select name="payment_type" class="form-control">
                                        <option value="tuition">Tuition</option>
                                        <option value="exam">Exam Fee</option>
                                        <option value="library">Library</option>
                                        <option value="transport">Transport</option>
                                        <option value="other">Other</option>
                                    </select></div>
                                <div class="form-group"><label>Method</label><select name="payment_method" class="form-control">
                                        <option value="cash">Cash</option>
                                        <option value="bank_transfer">Bank Transfer</option>
                                        <option value="mpesa">M-Pesa</option>
                                        <option value="card">Card</option>
                                    </select></div>
                                <div class="form-group"><label>Reference #</label><input type="text" name="reference" class="form-control" maxlength="100"></div>
                                <div class="form-group" style="display:flex; align-items:end;"><button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Record Payment</button></div>
                            </div>
                        </form>
                    </div>
                </div>
                <div class="section-title"><i class="fas fa-history"></i> Recent Payments</div>
                <div class="card">
                    <div class="card-body" style="overflow-x:auto;">
                        <table class="table finance-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Student</th>
                                    <th>Amount</th>
                                    <th>Type</th>
                                    <th>Method</th>
                                    <th>Reference</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recent)): ?><tr>
                                        <td colspan="7" style="text-align:center;padding:24px;">No payments recorded yet.</td>
                                    </tr>
                                    <?php else: foreach ($recent as $r): ?>
                                        <tr>
                                            <td><?= date('M j, Y', strtotime($r['display_date'] ?? $r['created_at'] ?? 'now')) ?></td>
                                            <td><?= htmlspecialchars($r['full_name'] ?? 'N/A') ?></td>
                                            <td><strong>$<?= number_format((float)($r['display_amount'] ?? 0), 2) ?></strong></td>
                                            <td><?= ucfirst(str_replace('_', ' ', (string)($r['display_type'] ?? ''))) ?></td>
                                            <td><?= ucfirst(str_replace('_', ' ', $r['payment_method'] ?? '')) ?></td>
                                            <td><code><?= htmlspecialchars($r['display_reference'] ?? '-') ?></code></td>
                                            <td><span class="badge badge-success"><?= ucfirst(str_replace('_', ' ', (string)($r['display_status'] ?? 'paid'))) ?></span></td>
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
