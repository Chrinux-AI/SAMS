<?php

/**
 * Financial Management System
 * Comprehensive bursar and accounting system for educational institutions
 */

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';

// Only admins can access this
$_user_role = $_SESSION['role'] ?? $_SESSION['user_role'] ?? '';
if (!in_array($_user_role, ['admin', 'super_admin', 'superadmin', 'owner', 'bursar', 'accountant'])) {
    header('Location: ../login.php');
    exit;
}

// Get financial statistics
$total_revenue = db()->fetchOne("SELECT COALESCE(SUM(amount), 0) as total FROM financial_transactions WHERE transaction_type = 'revenue' AND status = 'completed'")['total'] ?? 0;
$total_expenses = db()->fetchOne("SELECT COALESCE(SUM(amount), 0) as total FROM financial_transactions WHERE transaction_type = 'expense' AND status = 'completed'")['total'] ?? 0;
$pending_fees = db()->fetchOne("SELECT COALESCE(SUM(amount), 0) as total FROM student_fees WHERE status = 'pending'")['total'] ?? 0;
$total_students = db()->fetchOne("SELECT COUNT(*) as count FROM students")['count'] ?? 0;

// Get recent financial data
$recent_transactions = db()->fetchAll("SELECT * FROM financial_transactions ORDER BY transaction_date DESC LIMIT 10");
$fee_categories = db()->fetchAll("SELECT * FROM fee_categories ORDER BY category_name");
$expense_categories = db()->fetchAll("SELECT * FROM expense_categories ORDER BY category_name");

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_fee_category') {
        $category_data = [
            'category_name' => $_POST['category_name'],
            'description' => $_POST['description'],
            'amount' => $_POST['amount'],
            'frequency' => $_POST['frequency'],
            'is_required' => $_POST['is_required'] ?? 0,
            'created_at' => date('Y-m-d H:i:s')
        ];

        db()->insert('fee_categories', $category_data);
        header('Location: financial-management.php?success=fee_category_added');
        exit;
    }

    if ($action === 'add_expense_category') {
        $category_data = [
            'category_name' => $_POST['category_name'],
            'description' => $_POST['description'],
            'budget_limit' => $_POST['budget_limit'],
            'created_at' => date('Y-m-d H:i:s')
        ];

        db()->insert('expense_categories', $category_data);
        header('Location: financial-management.php?success=expense_category_added');
        exit;
    }

    if ($action === 'record_transaction') {
        $transaction_data = [
            'transaction_type' => $_POST['transaction_type'],
            'category_id' => $_POST['category_id'],
            'description' => $_POST['description'],
            'amount' => $_POST['amount'],
            'payment_method' => $_POST['payment_method'],
            'reference_number' => $_POST['reference_number'],
            'student_id' => $_POST['student_id'] ?? null,
            'transaction_date' => $_POST['transaction_date'],
            'status' => 'completed',
            'created_by' => $_SESSION['user_id'],
            'created_at' => date('Y-m-d H:i:s')
        ];

        db()->insert('financial_transactions', $transaction_data);

        // Update student fee status if applicable
        if ($_POST['transaction_type'] === 'revenue' && !empty($_POST['student_id'])) {
            // Mark relevant fees as paid
            db()->update('student_fees', [
                'status' => 'paid',
                'paid_date' => date('Y-m-d'),
                'payment_method' => $_POST['payment_method']
            ], 'student_id = ? AND status = "pending"', [$_POST['student_id']]);
        }

        header('Location: financial-management.php?success=transaction_recorded');
        exit;
    }

    if ($action === 'generate_invoice') {
        $invoice_data = [
            'student_id' => $_POST['student_id'],
            'invoice_number' => 'INV-' . date('Y') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT),
            'total_amount' => $_POST['total_amount'],
            'due_date' => $_POST['due_date'],
            'status' => 'sent',
            'created_at' => date('Y-m-d H:i:s')
        ];

        db()->insert('fee_invoices', $invoice_data);
        $invoice_id = db()->getConnection()->lastInsertId();

        // Add invoice items
        if (isset($_POST['fee_items'])) {
            foreach ($_POST['fee_items'] as $fee_item) {
                db()->insert('invoice_items', [
                    'invoice_id' => $invoice_id,
                    'fee_category_id' => $fee_item['category_id'],
                    'amount' => $fee_item['amount'],
                    'description' => $fee_item['description']
                ]);
            }
        }

        header('Location: financial-management.php?success=invoice_generated');
        exit;
    }
}

// Success message
$success_message = '';
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'fee_category_added':
            $success_message = 'Fee category added successfully!';
            break;
        case 'expense_category_added':
            $success_message = 'Expense category added successfully!';
            break;
        case 'transaction_recorded':
            $success_message = 'Transaction recorded successfully!';
            break;
        case 'invoice_generated':
            $success_message = 'Invoice generated successfully!';
            break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <script src="../assets/js/theme-loader.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Management - SAMS Platform</title>
    <link rel="manifest" href="/attendance/manifest.json">
    <meta name="theme-color" content="#4F46E5">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="../assets/css/professional-ui.css" rel="stylesheet">
    <?php include '../includes/sams-head-bootstrap.php'; ?>

    <style>
        .financial-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        .page-header {
            background: linear-gradient(135deg, #8B5CF6, #7C3AED);
            color: white;
            padding: 40px;
            border-radius: 20px;
            margin-bottom: 30px;
            box-shadow: 0 20px 40px rgba(139, 92, 246, 0.3);
        }

        .page-title {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 10px;
        }

        .page-subtitle {
            opacity: 0.9;
            font-size: 1.1rem;
        }

        .financial-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #8B5CF6;
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin: 0 auto 15px;
            background: linear-gradient(135deg, #8B5CF6, #7C3AED);
            color: white;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #1F2937;
            margin-bottom: 8px;
        }

        .stat-label {
            color: #6B7280;
            font-weight: 500;
        }

        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        .panel {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .panel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .panel-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1F2937;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #374151;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #D1D5DB;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.2s;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #8B5CF6;
            box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
        }

        .btn {
            padding: 12px 24px;
            border-radius: 8px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: #8B5CF6;
            color: white;
        }

        .btn-primary:hover {
            background: #7C3AED;
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: #6B7280;
            color: white;
        }

        .btn-success {
            background: #10B981;
            color: white;
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #D1FAE5;
            color: #065F46;
            border: 1px solid #A7F3D0;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th {
            background: #F9FAFB;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #374151;
            border-bottom: 2px solid #E5E7EB;
        }

        .data-table td {
            padding: 15px;
            border-bottom: 1px solid #F3F4F6;
        }

        .data-table tr:hover {
            background: #F9FAFB;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-completed {
            background: #D1FAE5;
            color: #065F46;
        }

        .status-pending {
            background: #FEF3C7;
            color: #92400E;
        }

        .status-overdue {
            background: #FEE2E2;
            color: #991B1B;
        }

        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            border-bottom: 2px solid #E5E7EB;
        }

        .tab {
            padding: 12px 20px;
            background: none;
            border: none;
            font-weight: 600;
            color: #6B7280;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            transition: all 0.3s ease;
        }

        .tab.active {
            color: #8B5CF6;
            border-bottom-color: #8B5CF6;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .financial-summary {
            background: linear-gradient(135deg, #F9FAFB, #F3F4F6);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #E5E7EB;
        }

        .summary-row:last-child {
            border-bottom: none;
            padding-top: 20px;
            font-weight: 700;
            font-size: 1.2rem;
            color: #1F2937;
        }

        .summary-label {
            font-weight: 500;
            color: #6B7280;
        }

        .summary-value {
            font-weight: 600;
            color: #1F2937;
        }

        .summary-value.positive {
            color: #10B981;
        }

        .summary-value.negative {
            color: #EF4444;
        }

        @media (max-width: 768px) {
            .content-grid {
                grid-template-columns: 1fr;
            }

            .financial-stats {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <div class="financial-container">
        <!-- Page Header -->
        <div class="page-header">
            <div class="page-title">Financial Management</div>
            <div class="page-subtitle">Comprehensive bursar and accounting system for fee management, expenses, and financial reporting</div>
        </div>

        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo $success_message; ?>
            </div>
        <?php endif; ?>

        <!-- Financial Statistics -->
        <div class="financial-stats">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-dollar-sign"></i>
                </div>
                <div class="stat-value">$<?php echo number_format($total_revenue, 2); ?></div>
                <div class="stat-label">Total Revenue</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-receipt"></i>
                </div>
                <div class="stat-value">$<?php echo number_format($total_expenses, 2); ?></div>
                <div class="stat-label">Total Expenses</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-value">$<?php echo number_format($pending_fees, 2); ?></div>
                <div class="stat-label">Pending Fees</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-value"><?php echo $total_students; ?></div>
                <div class="stat-label">Active Students</div>
            </div>
        </div>

        <!-- Financial Summary -->
        <div class="financial-summary">
            <div class="summary-row">
                <span class="summary-label">Total Revenue</span>
                <span class="summary-value positive">$<?php echo number_format($total_revenue, 2); ?></span>
            </div>
            <div class="summary-row">
                <span class="summary-label">Total Expenses</span>
                <span class="summary-value negative">$<?php echo number_format($total_expenses, 2); ?></span>
            </div>
            <div class="summary-row">
                <span class="summary-label">Net Profit/Loss</span>
                <span class="summary-value <?php echo ($total_revenue - $total_expenses) >= 0 ? 'positive' : 'negative'; ?>">
                    $<?php echo number_format($total_revenue - $total_expenses, 2); ?>
                </span>
            </div>
        </div>

        <!-- Main Content -->
        <div class="content-grid">
            <!-- Financial Forms -->
            <div class="panel">
                <div class="panel-header">
                    <h2 class="panel-title">Financial Operations</h2>
                </div>

                <!-- Tabs -->
                <div class="tabs">
                    <button class="tab active" onclick="showTab(event, 'transactions')">Transactions</button>
                    <button class="tab" onclick="showTab(event, 'fees')">Fee Categories</button>
                    <button class="tab" onclick="showTab(event, 'expenses')">Expenses</button>
                    <button class="tab" onclick="showTab(event, 'invoices')">Invoices</button>
                </div>

                <!-- Transactions Tab -->
                <div id="transactions-tab" class="tab-content active">
                    <form method="POST">
                        <input type="hidden" name="action" value="record_transaction">

                        <div class="form-group">
                            <label for="transaction_type">Transaction Type *</label>
                            <select id="transaction_type" name="transaction_type" required>
                                <option value="">Select Type</option>
                                <option value="revenue">Revenue</option>
                                <option value="expense">Expense</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="category_id">Category *</label>
                            <select id="category_id" name="category_id" required>
                                <option value="">Select Category</option>
                                <optgroup label="Revenue Categories">
                                    <?php foreach ($fee_categories as $category): ?>
                                        <option value="fee_<?php echo $category['id']; ?>">
                                            <?php echo htmlspecialchars($category['category_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                                <optgroup label="Expense Categories">
                                    <?php foreach ($expense_categories as $category): ?>
                                        <option value="expense_<?php echo $category['id']; ?>">
                                            <?php echo htmlspecialchars($category['category_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="description">Description *</label>
                            <input type="text" id="description" name="description" required>
                        </div>

                        <div class="form-group">
                            <label for="amount">Amount *</label>
                            <input type="number" id="amount" name="amount" step="0.01" required>
                        </div>

                        <div class="form-group">
                            <label for="payment_method">Payment Method *</label>
                            <select id="payment_method" name="payment_method" required>
                                <option value="">Select Method</option>
                                <option value="cash">Cash</option>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="credit_card">Credit Card</option>
                                <option value="check">Check</option>
                                <option value="online">Online Payment</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="reference_number">Reference Number</label>
                            <input type="text" id="reference_number" name="reference_number">
                        </div>

                        <div class="form-group">
                            <label for="student_id">Student (if applicable)</label>
                            <select id="student_id" name="student_id">
                                <option value="">Select Student</option>
                                <!-- Students would be loaded dynamically -->
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="transaction_date">Transaction Date *</label>
                            <input type="date" id="transaction_date" name="transaction_date" required>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i>
                            Record Transaction
                        </button>
                    </form>
                </div>

                <!-- Fee Categories Tab -->
                <div id="fees-tab" class="tab-content">
                    <form method="POST">
                        <input type="hidden" name="action" value="add_fee_category">

                        <div class="form-group">
                            <label for="category_name">Category Name *</label>
                            <input type="text" id="category_name" name="category_name" required>
                        </div>

                        <div class="form-group">
                            <label for="description">Description</label>
                            <textarea id="description" name="description" rows="3"></textarea>
                        </div>

                        <div class="form-group">
                            <label for="amount">Default Amount *</label>
                            <input type="number" id="amount" name="amount" step="0.01" required>
                        </div>

                        <div class="form-group">
                            <label for="frequency">Frequency *</label>
                            <select id="frequency" name="frequency" required>
                                <option value="">Select Frequency</option>
                                <option value="once">One Time</option>
                                <option value="monthly">Monthly</option>
                                <option value="quarterly">Quarterly</option>
                                <option value="semester">Semester</option>
                                <option value="yearly">Yearly</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="is_required" value="1">
                                Required Fee
                            </label>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-plus"></i>
                            Add Fee Category
                        </button>
                    </form>
                </div>

                <!-- Expenses Tab -->
                <div id="expenses-tab" class="tab-content">
                    <form method="POST">
                        <input type="hidden" name="action" value="add_expense_category">

                        <div class="form-group">
                            <label for="category_name">Category Name *</label>
                            <input type="text" id="category_name" name="category_name" required>
                        </div>

                        <div class="form-group">
                            <label for="description">Description</label>
                            <textarea id="description" name="description" rows="3"></textarea>
                        </div>

                        <div class="form-group">
                            <label for="budget_limit">Budget Limit</label>
                            <input type="number" id="budget_limit" name="budget_limit" step="0.01">
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-plus"></i>
                            Add Expense Category
                        </button>
                    </form>
                </div>

                <!-- Invoices Tab -->
                <div id="invoices-tab" class="tab-content">
                    <form method="POST">
                        <input type="hidden" name="action" value="generate_invoice">

                        <div class="form-group">
                            <label for="student_id">Student *</label>
                            <select id="student_id" name="student_id" required>
                                <option value="">Select Student</option>
                                <!-- Students would be loaded dynamically -->
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="total_amount">Total Amount *</label>
                            <input type="number" id="total_amount" name="total_amount" step="0.01" required>
                        </div>

                        <div class="form-group">
                            <label for="due_date">Due Date *</label>
                            <input type="date" id="due_date" name="due_date" required>
                        </div>

                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-file-invoice"></i>
                            Generate Invoice
                        </button>
                    </form>
                </div>
            </div>

            <!-- Recent Financial Activity -->
            <div class="panel">
                <div class="panel-header">
                    <h2 class="panel-title">Recent Transactions</h2>
                    <a href="financial-reports.php" class="btn btn-primary">View All</a>
                </div>

                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Description</th>
                            <th>Amount</th>
                            <th>Type</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_transactions as $transaction): ?>
                            <tr>
                                <td>
                                    <?php echo date('M j, Y', strtotime($transaction['transaction_date'])); ?>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($transaction['description']); ?></strong>
                                    <?php if (!empty($transaction['reference_number'])): ?>
                                        <br><small>Ref: <?php echo htmlspecialchars($transaction['reference_number']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span style="color: <?php echo $transaction['transaction_type'] === 'revenue' ? '#10B981' : '#EF4444'; ?>; font-weight: 600;">
                                        <?php echo $transaction['transaction_type'] === 'revenue' ? '+' : '-'; ?>
                                        $<?php echo number_format($transaction['amount'], 2); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo $transaction['transaction_type']; ?>">
                                        <?php echo ucfirst($transaction['transaction_type']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo $transaction['status']; ?>">
                                        <?php echo ucfirst($transaction['status']); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        function showTab(evt, tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });

            // Remove active class from all tab buttons
            document.querySelectorAll('.tab').forEach(btn => {
                btn.classList.remove('active');
            });

            // Show selected tab
            document.getElementById(tabName + '-tab').classList.add('active');

            // Add active class to clicked tab button
            if (evt && evt.target) {
                evt.target.classList.add('active');
            }
        }

        // Set today's date as default
        document.addEventListener('DOMContentLoaded', function() {
            const transactionDate = document.getElementById('transaction_date');
            if (transactionDate) {
                transactionDate.valueAsDate = new Date();
            }
        });
    </script>
</body>

</html>
