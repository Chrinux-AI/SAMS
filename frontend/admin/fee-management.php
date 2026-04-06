<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_admin('../login.php');

$message = '';
$message_type = '';

// Handle fee creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_fee'])) {
    $data = [
        'student_id' => (int)$_POST['student_id'],
        'fee_type' => sanitize($_POST['fee_type']),
        'amount' => (float)$_POST['amount'],
        'due_date' => sanitize($_POST['due_date']),
        'description' => sanitize($_POST['description']),
        'status' => 'pending',
        'created_by' => $_SESSION['user_id']
    ];
    $id = db()->insert('fees', $data);
    if ($id) {
        log_activity($_SESSION['user_id'], 'create', 'fees', $id);
        $message = 'Fee created successfully!';
        $message_type = 'success';
    }
}

// Handle payment recording
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_payment'])) {
    $fee_id = (int)$_POST['fee_id'];
    $data = [
        'status' => 'paid',
        'paid_amount' => (float)$_POST['paid_amount'],
        'payment_date' => sanitize($_POST['payment_date']),
        'payment_method' => sanitize($_POST['payment_method']),
        'transaction_id' => sanitize($_POST['transaction_id'])
    ];
    db()->update('fees', $data, 'id = ?', [$fee_id]);
    log_activity($_SESSION['user_id'], 'update', 'fees', $fee_id);
    $message = 'Payment recorded successfully!';
    $message_type = 'success';
}

// Get all fees with student info
$fees = db()->fetchAll("
    SELECT f.*, s.admission_number AS student_id, CONCAT(u.first_name, ' ', u.last_name) as student_name,
           c.grade_level AS grade, c.class_name AS class
    FROM fees f
    LEFT JOIN students s ON f.student_id = s.id
    LEFT JOIN users u ON s.user_id = u.id
    LEFT JOIN classes c ON s.class_id = c.id
    ORDER BY f.created_at DESC
");

// Get all students for dropdown
$students = db()->fetchAll("
    SELECT s.id, s.admission_number AS student_id, CONCAT(u.first_name, ' ', u.last_name) as name, c.grade_level AS grade
    FROM students s
    LEFT JOIN users u ON s.user_id = u.id
    LEFT JOIN classes c ON s.class_id = c.id
    ORDER BY s.admission_number
");

// Calculate statistics
$total_fees = array_sum(array_column($fees, 'amount'));
$paid_fees = array_sum(array_map(function ($f) {
    return $f['status'] === 'paid' ? $f['paid_amount'] : 0;
}, $fees));
$pending_fees = $total_fees - $paid_fees;

$page_title = 'Fee Management';
$page_icon = 'money-bill-wave';
$full_name = $_SESSION['full_name'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <script src="../assets/js/theme-loader.js"></script>
        <link rel="manifest" href="/attendance/manifest.json">
    <meta name="theme-color" content="#00BFFF">
    <link rel="apple-touch-icon" href="/attendance/assets/images/icons/icon-192x192.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - <?php echo APP_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Orbitron:wght@500;700;900&family=Inter:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="../assets/css/professional-ui.css" rel="stylesheet">

</head>
<body>
    
    

    <div class="app-layout">
        <?php include '../includes/sidebar-nav.php'; ?>
        <main class="main-content">
            <header class="cyber-header">
                <div class="page-title-section">
                    <div class="page-icon-orb"><i class="fas fa-<?php echo $page_icon; ?>"></i></div>
                    <h1 class="page-title"><?php echo $page_title; ?></h1>
                </div>
                <div class="header-actions">
                    <button onclick="document.getElementById('addFeeModal').style.display='flex'" class="cyber-btn primary">
                        <i class="fas fa-plus-circle"></i> Create Fee
                    </button>
                    <div class="user-card" style="padding:8px 15px;margin:0;margin-left:15px;">
                        <div class="user-avatar" style="width:35px;height:35px;font-size:0.9rem;">
                            <?php echo strtoupper(substr($full_name, 0, 2)); ?>
                        </div>
                        <div class="user-info">
                            <div class="user-name" style="font-size:0.85rem;"><?php echo htmlspecialchars($full_name); ?></div>
                            <div class="user-role">Administrator</div>
                        </div>
                    </div>
                </div>
            </header>
            <div class="cyber-content slide-in">
                <?php if ($message): ?>
                    <div class="cyber-alert <?php echo $message_type === 'success' ? 'success' : 'error'; ?>">
                        <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                        <span><?php echo $message; ?></span>
                    </div>
                <?php endif; ?>

                <!-- Statistics Cards -->
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:20px;margin-bottom:25px;">
                    <div class="holo-card" style="background:linear-gradient(135deg,rgba(0,191,255,0.1),rgba(138,43,226,0.1));">
                        <div class="card-body" style="text-align:center;padding:25px;">
                            <i class="fas fa-coins" style="font-size:2.5rem;color:var(--cyber-cyan);margin-bottom:15px;"></i>
                            <div style="font-size:2rem;font-weight:700;color:var(--cyber-cyan);margin-bottom:5px;">$<?php echo number_format($total_fees, 2); ?></div>
                            <div style="color:rgba(255,255,255,0.6);">Total Fees</div>
                        </div>
                    </div>
                    <div class="holo-card" style="background:linear-gradient(135deg,rgba(0,255,127,0.1),rgba(0,191,255,0.1));">
                        <div class="card-body" style="text-align:center;padding:25px;">
                            <i class="fas fa-check-circle" style="font-size:2.5rem;color:#00ff7f;margin-bottom:15px;"></i>
                            <div style="font-size:2rem;font-weight:700;color:#00ff7f;margin-bottom:5px;">$<?php echo number_format($paid_fees, 2); ?></div>
                            <div style="color:rgba(255,255,255,0.6);">Collected</div>
                        </div>
                    </div>
                    <div class="holo-card" style="background:linear-gradient(135deg,rgba(255,20,147,0.1),rgba(138,43,226,0.1));">
                        <div class="card-body" style="text-align:center;padding:25px;">
                            <i class="fas fa-exclamation-circle" style="font-size:2.5rem;color:var(--cyber-pink);margin-bottom:15px;"></i>
                            <div style="font-size:2rem;font-weight:700;color:var(--cyber-pink);margin-bottom:5px;">$<?php echo number_format($pending_fees, 2); ?></div>
                            <div style="color:rgba(255,255,255,0.6);">Pending</div>
                        </div>
                    </div>
                </div>

                <div class="holo-card">
                    <div class="card-header">
                        <div class="card-title"><i class="fas fa-list"></i> <span>All Fees</span></div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($fees)): ?>
                            <div style="text-align:center;padding:40px;color:rgba(255,255,255,0.4);">
                                <i class="fas fa-money-bill-wave" style="font-size:3rem;margin-bottom:15px;"></i>
                                <div>No fees created yet</div>
                            </div>
                        <?php else: ?>
                            <div style="overflow-x:auto;">
                                <table style="width:100%;border-collapse:collapse;">
                                    <thead>
                                        <tr style="border-bottom:2px solid var(--cyber-cyan);">
                                            <th style="padding:12px;text-align:left;color:var(--cyber-cyan);font-weight:700;">STUDENT</th>
                                            <th style="padding:12px;text-align:left;color:var(--cyber-cyan);font-weight:700;">FEE TYPE</th>
                                            <th style="padding:12px;text-align:right;color:var(--cyber-cyan);font-weight:700;">AMOUNT</th>
                                            <th style="padding:12px;text-align:center;color:var(--cyber-cyan);font-weight:700;">DUE DATE</th>
                                            <th style="padding:12px;text-align:center;color:var(--cyber-cyan);font-weight:700;">STATUS</th>
                                            <th style="padding:12px;text-align:center;color:var(--cyber-cyan);font-weight:700;">ACTIONS</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($fees as $fee): ?>
                                            <tr style="border-bottom:1px solid rgba(0,191,255,0.1);">
                                                <td style="padding:12px;">
                                                    <div style="font-weight:600;color:var(--cyber-cyan);"><?php echo htmlspecialchars($fee['student_name']); ?></div>
                                                    <div style="font-size:0.75rem;color:rgba(255,255,255,0.5);"><?php echo $fee['student_id']; ?> • Grade <?php echo $fee['grade']; ?></div>
                                                </td>
                                                <td style="padding:12px;">
                                                    <span style="padding:5px 10px;background:rgba(0,191,255,0.1);border:1px solid var(--cyber-cyan);border-radius:12px;font-size:0.85rem;">
                                                        <?php echo ucfirst($fee['fee_type']); ?>
                                                    </span>
                                                </td>
                                                <td style="padding:12px;text-align:right;font-weight:700;color:var(--cyber-cyan);">$<?php echo number_format($fee['amount'], 2); ?></td>
                                                <td style="padding:12px;text-align:center;color:rgba(255,255,255,0.7);"><?php echo date('M d, Y', strtotime($fee['due_date'])); ?></td>
                                                <td style="padding:12px;text-align:center;">
                                                    <?php if ($fee['status'] === 'paid'): ?>
                                                        <span style="padding:5px 12px;background:rgba(0,255,127,0.2);border:1px solid #00ff7f;border-radius:15px;font-weight:700;color:#00ff7f;">
                                                            <i class="fas fa-check"></i> PAID
                                                        </span>
                                                    <?php elseif ($fee['status'] === 'overdue'): ?>
                                                        <span style="padding:5px 12px;background:rgba(255,20,147,0.2);border:1px solid var(--cyber-pink);border-radius:15px;font-weight:700;color:var(--cyber-pink);">
                                                            <i class="fas fa-exclamation-triangle"></i> OVERDUE
                                                        </span>
                                                    <?php else: ?>
                                                        <span style="padding:5px 12px;background:rgba(0,191,255,0.2);border:1px solid var(--cyber-cyan);border-radius:15px;font-weight:700;">
                                                            PENDING
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td style="padding:12px;text-align:center;">
                                                    <?php if ($fee['status'] !== 'paid'): ?>
                                                        <button onclick="showPaymentForm(<?php echo $fee['id']; ?>, '<?php echo addslashes($fee['student_name']); ?>', <?php echo $fee['amount']; ?>)" class="cyber-btn primary" style="padding:6px 12px;">
                                                            <i class="fas fa-dollar-sign"></i> Record Payment
                                                        </button>
                                                    <?php else: ?>
                                                        <span style="color:rgba(255,255,255,0.4);font-size:0.85rem;">
                                                            <i class="fas fa-check-circle"></i> Paid on <?php echo date('M d', strtotime($fee['payment_date'])); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Create Fee Modal -->
    <div id="addFeeModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.8);z-index:9999;align-items:center;justify-content:center;">
        <div class="holo-card" style="max-width:600px;width:90%;max-height:90vh;overflow-y:auto;">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-plus-circle"></i> <span>Create New Fee</span></div>
                <button onclick="document.getElementById('addFeeModal').style.display='none'" class="cyber-btn danger" style="padding:8px 12px;"><i class="fas fa-times"></i></button>
            </div>
            <div class="card-body">
                <form method="POST" style="display:grid;gap:15px;">
                    <div>
                        <label style="color:var(--cyber-cyan);font-size:0.85rem;font-weight:600;margin-bottom:8px;display:block;">STUDENT *</label>
                        <select name="student_id" required style="width:100%;padding:12px;background:rgba(0,191,255,0.05);border:1px solid var(--cyber-cyan);border-radius:8px;color:var(--cyber-cyan);font-family:Roboto;">
                            <?php foreach ($students as $stu): ?>
                                <option value="<?php echo $stu['id']; ?>"><?php echo htmlspecialchars($stu['name']); ?> (<?php echo $stu['student_id']; ?> - Grade <?php echo $stu['grade']; ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
                        <div>
                            <label style="color:var(--cyber-cyan);font-size:0.85rem;font-weight:600;margin-bottom:8px;display:block;">FEE TYPE *</label>
                            <select name="fee_type" required style="width:100%;padding:12px;background:rgba(0,191,255,0.05);border:1px solid var(--cyber-cyan);border-radius:8px;color:var(--cyber-cyan);font-family:Roboto;">
                                <option value="tuition">Tuition</option>
                                <option value="library">Library</option>
                                <option value="lab">Lab</option>
                                <option value="transport">Transport</option>
                                <option value="sports">Sports</option>
                                <option value="exam">Exam</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div>
                            <label style="color:var(--cyber-cyan);font-size:0.85rem;font-weight:600;margin-bottom:8px;display:block;">AMOUNT ($) *</label>
                            <input type="number" name="amount" step="0.01" required style="width:100%;padding:12px;background:rgba(0,191,255,0.05);border:1px solid var(--cyber-cyan);border-radius:8px;color:var(--cyber-cyan);font-family:Roboto;">
                        </div>
                    </div>
                    <div>
                        <label style="color:var(--cyber-cyan);font-size:0.85rem;font-weight:600;margin-bottom:8px;display:block;">DUE DATE *</label>
                        <input type="date" name="due_date" required style="width:100%;padding:12px;background:rgba(0,191,255,0.05);border:1px solid var(--cyber-cyan);border-radius:8px;color:var(--cyber-cyan);font-family:Roboto;">
                    </div>
                    <div>
                        <label style="color:var(--cyber-cyan);font-size:0.85rem;font-weight:600;margin-bottom:8px;display:block;">DESCRIPTION</label>
                        <textarea name="description" rows="3" style="width:100%;padding:12px;background:rgba(0,191,255,0.05);border:1px solid var(--cyber-cyan);border-radius:8px;color:var(--cyber-cyan);font-family:Roboto;"></textarea>
                    </div>
                    <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:10px;">
                        <button type="button" onclick="document.getElementById('addFeeModal').style.display='none'" class="cyber-btn">Cancel</button>
                        <button type="submit" name="create_fee" class="cyber-btn primary"><i class="fas fa-save"></i> Create Fee</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Record Payment Modal -->
    <div id="paymentModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.8);z-index:9999;align-items:center;justify-content:center;">
        <div class="holo-card" style="max-width:500px;width:90%;">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-dollar-sign"></i> <span id="paymentModalTitle">Record Payment</span></div>
                <button onclick="document.getElementById('paymentModal').style.display='none'" class="cyber-btn danger" style="padding:8px 12px;"><i class="fas fa-times"></i></button>
            </div>
            <div class="card-body">
                <form method="POST" style="display:grid;gap:15px;">
                    <input type="hidden" name="fee_id" id="paymentFeeId">
                    <div>
                        <label style="color:var(--cyber-cyan);font-size:0.85rem;font-weight:600;margin-bottom:8px;display:block;">AMOUNT PAID ($) *</label>
                        <input type="number" name="paid_amount" id="paymentAmount" step="0.01" required style="width:100%;padding:12px;background:rgba(0,191,255,0.05);border:1px solid var(--cyber-cyan);border-radius:8px;color:var(--cyber-cyan);font-family:Roboto;font-size:1.2rem;">
                    </div>
                    <div>
                        <label style="color:var(--cyber-cyan);font-size:0.85rem;font-weight:600;margin-bottom:8px;display:block;">PAYMENT DATE *</label>
                        <input type="date" name="payment_date" required value="<?php echo date('Y-m-d'); ?>" style="width:100%;padding:12px;background:rgba(0,191,255,0.05);border:1px solid var(--cyber-cyan);border-radius:8px;color:var(--cyber-cyan);font-family:Roboto;">
                    </div>
                    <div>
                        <label style="color:var(--cyber-cyan);font-size:0.85rem;font-weight:600;margin-bottom:8px;display:block;">PAYMENT METHOD *</label>
                        <select name="payment_method" required style="width:100%;padding:12px;background:rgba(0,191,255,0.05);border:1px solid var(--cyber-cyan);border-radius:8px;color:var(--cyber-cyan);font-family:Roboto;">
                            <option value="cash">Cash</option>
                            <option value="check">Check</option>
                            <option value="card">Card</option>
                            <option value="online">Online Transfer</option>
                        </select>
                    </div>
                    <div>
                        <label style="color:var(--cyber-cyan);font-size:0.85rem;font-weight:600;margin-bottom:8px;display:block;">TRANSACTION ID</label>
                        <input type="text" name="transaction_id" placeholder="Optional" style="width:100%;padding:12px;background:rgba(0,191,255,0.05);border:1px solid var(--cyber-cyan);border-radius:8px;color:var(--cyber-cyan);font-family:Roboto;">
                    </div>
                    <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:10px;">
                        <button type="button" onclick="document.getElementById('paymentModal').style.display='none'" class="cyber-btn">Cancel</button>
                        <button type="submit" name="record_payment" class="cyber-btn primary"><i class="fas fa-save"></i> Record Payment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function showPaymentForm(feeId, studentName, amount) {
            document.getElementById('paymentFeeId').value = feeId;
            document.getElementById('paymentModalTitle').textContent = 'Record Payment - ' + studentName;
            document.getElementById('paymentAmount').value = amount.toFixed(2);
            document.getElementById('paymentModal').style.display = 'flex';
        }
    </script>

    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/pwa-manager.js"></script>
    <script src="../assets/js/pwa-analytics.js"></script>
</body>

</html>