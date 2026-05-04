<?php

/**
 * SAMS Accountant Dashboard
 * Real-time Financial Insights and Live Data
 */
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/database.php';
require_login('../login.php');

if (!has_role('accountant') && !has_role('admin')) {
    redirect('../login.php', 'Access denied. Accountant privileges required.', 'error');
}

$accountant_id = $_SESSION['user_id'];
$tenantId = $_SESSION['tenant_id'] ?? 1;
$full_name = $_SESSION['full_name'] ?? 'Accountant';

// Safely fetch real-time numbers from our actual database schemas using PDO
try {
    // 1. Total Income from fee_payments
    $incomeSql = "SELECT SUM(amount_paid) as total FROM fee_payments";
    $incomeParams = [];
    if (table_has_column('fee_payments', 'tenant_id')) {
        $incomeSql .= " WHERE tenant_id = ?";
        $incomeParams[] = $tenantId;
    }
    $stmt = db()->query($incomeSql, $incomeParams);
    $total_income = $stmt ? ((float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0)) : 0;

    // 2. Total Expenses from expenses
    $expenseSumSql = "SELECT SUM(amount) as total FROM expenses";
    $expenseSumParams = [];
    if (table_has_column('expenses', 'tenant_id')) {
        $expenseSumSql .= " WHERE tenant_id = ?";
        $expenseSumParams[] = $tenantId;
    }
    $stmt = db()->query($expenseSumSql, $expenseSumParams);
    $total_expenses = $stmt ? ($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0) : 0;

    // 3. Purchase Orders Total from purchase_orders
    $purchaseOrderSql = "SELECT SUM(total_amount) as total FROM purchase_orders";
    $purchaseOrderParams = [];
    if (table_exists('purchase_orders') && table_has_column('purchase_orders', 'tenant_id')) {
        $purchaseOrderSql .= " WHERE tenant_id = ?";
        $purchaseOrderParams[] = $tenantId;
    }
    $stmt = table_exists('purchase_orders') ? db()->query($purchaseOrderSql, $purchaseOrderParams) : false;
    $total_po_amount = $stmt ? ($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0) : 0;

    // 4. Counts
    $stmt = db()->query("SELECT COUNT(*) as cnt FROM ledger_entries WHERE tenant_id = ?", [$tenantId]);
    $ledger_count = $stmt ? ($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0) : 0;
    $suppliersSql = "SELECT COUNT(*) as cnt FROM suppliers";
    $suppliersParams = [];
    if (table_has_column('suppliers', 'tenant_id')) {
        $suppliersSql .= " WHERE tenant_id = ?";
        $suppliersParams[] = $tenantId;
    }
    $stmt = db()->query($suppliersSql, $suppliersParams);
    $supplier_count = $stmt ? ($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0) : 0;
    $invoiceSql = table_exists('invoices') ? "SELECT COUNT(*) as cnt FROM invoices" : (table_exists('fee_invoices') ? "SELECT COUNT(*) as cnt FROM fee_invoices" : '');
    $invoiceParams = [];
    if ($invoiceSql !== '') {
        $invoiceTable = str_contains($invoiceSql, 'fee_invoices') ? 'fee_invoices' : 'invoices';
        if (table_has_column($invoiceTable, 'tenant_id')) {
            $invoiceSql .= " WHERE tenant_id = ?";
            $invoiceParams[] = $tenantId;
        }
        $stmt = db()->query($invoiceSql, $invoiceParams);
        $invoice_count = $stmt ? ($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0) : 0;
    } else {
        $invoice_count = 0;
    }
} catch (Exception $e) {
    // Fallbacks if tables are empty/missing
    $total_income = $total_expenses = $total_po_amount = $ledger_count = $supplier_count = $invoice_count = 0;
}

$net_balance = $total_income - $total_expenses;

// Recent Fee Payments (Real-time)
$recent_transactions = [];
try {
    $paymentCategoryExpr = table_has_column('fee_payments', 'method')
        ? 'method'
        : (table_has_column('fee_payments', 'payment_method') ? 'payment_method' : "'General'");
    $recentIncomeSql = "
        SELECT id, amount_paid as amount, payment_date, {$paymentCategoryExpr} as category, 'Income' as type, created_at
        FROM fee_payments";
    $recentIncomeParams = [];
    if (table_has_column('fee_payments', 'tenant_id')) {
        $recentIncomeSql .= " WHERE tenant_id = ?";
        $recentIncomeParams[] = $tenantId;
    }
    $recentIncomeSql .= " ORDER BY created_at DESC LIMIT 5";
    $recent_transactions = db()->fetchAll("
    " . trim($recentIncomeSql), $recentIncomeParams) ?: [];
} catch (Exception $e) {
    // Ignore if no data
}

// Recent Expenses (Real-time)
$recent_expenses = [];
try {
    $expenseCategoryExpr = table_has_column('expenses', 'expense_category') ? 'expense_category' : (table_has_column('expenses', 'category') ? 'category' : "'General'");
    $recentExpenseSql = "
        SELECT id, amount, expense_date, {$expenseCategoryExpr} as category, description, 'Expense' as type, created_at
        FROM expenses";
    $recentExpenseParams = [];
    if (table_has_column('expenses', 'tenant_id')) {
        $recentExpenseSql .= " WHERE tenant_id = ?";
        $recentExpenseParams[] = $tenantId;
    }
    $recentExpenseSql .= " ORDER BY created_at DESC LIMIT 5";
    $recent_expenses = db()->fetchAll("
    " . trim($recentExpenseSql), $recentExpenseParams) ?: [];
} catch (Exception $e) {
    // Ignore
}
$page_title = 'Accountant Dashboard';
$page_icon = 'account_balance';
$page_subtitle = 'Financial Overview & Live Data';
$activeTab = 'dashboard';
require_once __DIR__ . '/partials/header.php';
?>
<!-- Dashboard Content -->
<div class="mb-6 flex flex-wrap justify-end gap-3">
    <a href="index.php?page=reports" class="inline-flex items-center gap-2 rounded-lg border border-outline-variant/40 bg-surface-container-lowest px-4 py-2 text-sm font-bold hover:bg-surface-container-low transition-colors">
        <span class="material-symbols-outlined text-base">assessment</span>
        View Reports
    </a>
    <a href="index.php?page=income" class="inline-flex items-center gap-2 rounded-lg border border-outline-variant/40 bg-surface-container-lowest px-4 py-2 text-sm font-bold hover:bg-surface-container-low transition-colors">
        <span class="material-symbols-outlined text-base">payments</span>
        Add Income
    </a>
    <a href="index.php?page=expenses#quick-add-expense" class="inline-flex items-center gap-2 rounded-lg bg-primary text-on-primary px-5 py-2 text-sm font-bold shadow-md shadow-primary/20 hover:opacity-90 transition-opacity">
        <span class="material-symbols-outlined text-base">add_circle</span>
        Record Expense
    </a>
</div>
<div class="grid grid-cols-12 gap-6 mb-8">

    <!-- Stats Grid -->
    <div class="col-span-12 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6">

        <div class="bg-white rounded-xl shadow-sm p-6 border-l-4 border-blue-500">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-gray-500 text-sm font-medium uppercase">Net Balance</h3>
                <div class="p-2 bg-blue-50 text-blue-600 rounded-lg"><span class="material-symbols-outlined">account_balance_wallet</span></div>
            </div>
            <div class="text-2xl font-bold <?= $net_balance >= 0 ? 'text-green-600' : 'text-red-600' ?>">
                <span class="naira-symbol">&#8358;</span><?= number_format($net_balance, 2) ?>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm p-6 border-l-4 border-green-500">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-gray-500 text-sm font-medium uppercase">Total Revenue</h3>
                <div class="p-2 bg-green-50 text-green-600 rounded-lg"><span class="material-symbols-outlined">trending_up</span></div>
            </div>
            <div class="text-2xl font-bold text-gray-800">
                <span class="naira-symbol">&#8358;</span><?= number_format($total_income, 2) ?>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm p-6 border-l-4 border-red-500">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-gray-500 text-sm font-medium uppercase">Total Expenses</h3>
                <div class="p-2 bg-red-50 text-red-600 rounded-lg"><span class="material-symbols-outlined">trending_down</span></div>
            </div>
            <div class="text-2xl font-bold text-gray-800">
                <span class="naira-symbol">&#8358;</span><?= number_format($total_expenses, 2) ?>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm p-6 border-l-4 border-purple-500">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-gray-500 text-sm font-medium uppercase">Purchase Orders</h3>
                <div class="p-2 bg-purple-50 text-purple-600 rounded-lg"><span class="material-symbols-outlined">receipt_long</span></div>
            </div>
            <div class="text-2xl font-bold text-gray-800">
                <span class="naira-symbol">&#8358;</span><?= number_format($total_po_amount, 2) ?>
            </div>
        </div>

    </div>

    <!-- Activity Grids -->
    <div class="col-span-12 grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">
        <!-- Recent Incomes -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="p-4 border-b border-gray-100 flex justify-between items-center bg-gray-50">
                <h3 class="font-semibold text-gray-800 flex items-center"><span class="material-symbols-outlined text-green-600 mr-2">arrow_circle_up</span> Recent Fee Payments</h3>
                <a href="index.php?page=income" class="text-sm text-blue-600 hover:text-blue-800">View All</a>
            </div>
            <div class="p-0">
                <?php if (empty($recent_transactions)): ?>
                    <div class="p-6 text-center text-gray-500">No payment data available.</div>
                <?php else: ?>
                    <ul class="divide-y divide-gray-100">
                        <?php foreach ($recent_transactions as $tx): ?>
                            <li class="p-4 hover:bg-gray-50 transition-colors">
                                <div class="flex justify-between items-center">
                                    <div>
                                        <p class="font-medium text-gray-800"><?= htmlspecialchars($tx['category'] ?? 'General') ?></p>
                                        <p class="text-xs text-gray-500"><?= date('M j, Y g:i A', strtotime($tx['created_at'])) ?></p>
                                    </div>
                                    <span class="font-semibold text-green-600">+<span class="naira-symbol">&#8358;</span><?= number_format($tx['amount'], 2) ?></span>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Expenses -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="p-4 border-b border-gray-100 flex justify-between items-center bg-gray-50">
                <h3 class="font-semibold text-gray-800 flex items-center"><span class="material-symbols-outlined text-red-600 mr-2">arrow_circle_down</span> Recent Expenses</h3>
                <a href="index.php?page=expenses" class="text-sm text-blue-600 hover:text-blue-800">View All</a>
            </div>
            <div class="p-0">
                <?php if (empty($recent_expenses)): ?>
                    <div class="p-6 text-center text-gray-500">No expense data available.</div>
                <?php else: ?>
                    <ul class="divide-y divide-gray-100">
                        <?php foreach ($recent_expenses as $ex): ?>
                            <li class="p-4 hover:bg-gray-50 transition-colors">
                                <div class="flex justify-between items-center">
                                    <div>
                                        <p class="font-medium text-gray-800"><?= htmlspecialchars($ex['category'] ?? 'General') ?></p>
                                        <p class="text-xs text-gray-500"><?= htmlspecialchars($ex['description'] ?? '') ?></p>
                                    </div>
                                    <span class="font-semibold text-red-600">-<span class="naira-symbol">&#8358;</span><?= number_format($ex['amount'], 2) ?></span>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-span-12 grid grid-cols-3 gap-6 mt-6">
        <div class="bg-white rounded-xl shadow-sm p-4 border text-center relative overflow-hidden group">
            <h4 class="text-gray-500 uppercase text-xs font-semibold mb-2">Total Invoices</h4>
            <span class="text-3xl font-bold text-gray-800"><?= number_format($invoice_count) ?></span>
            <div class="absolute -right-4 -bottom-4 opacity-[0.03] group-hover:opacity-10 transition-opacity">
                <span class="material-symbols-outlined text-8xl">receipt</span>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm p-4 border text-center relative overflow-hidden group">
            <h4 class="text-gray-500 uppercase text-xs font-semibold mb-2">Ledger Entries</h4>
            <span class="text-3xl font-bold text-gray-800"><?= number_format($ledger_count) ?></span>
            <div class="absolute -right-4 -bottom-4 opacity-[0.03] group-hover:opacity-10 transition-opacity">
                <span class="material-symbols-outlined text-8xl">menu_book</span>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm p-4 border text-center relative overflow-hidden group">
            <h4 class="text-gray-500 uppercase text-xs font-semibold mb-2">Active Suppliers</h4>
            <span class="text-3xl font-bold text-gray-800"><?= number_format($supplier_count) ?></span>
            <div class="absolute -right-4 -bottom-4 opacity-[0.03] group-hover:opacity-10 transition-opacity">
                <span class="material-symbols-outlined text-8xl">local_shipping</span>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/partials/footer.php';
?>
