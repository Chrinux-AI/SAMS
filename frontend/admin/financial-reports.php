<?php

/**
 * Financial Reports
 */

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

$_user_role = $_SESSION['role'] ?? $_SESSION['user_role'] ?? '';
if (!in_array($_user_role, ['admin', 'super_admin', 'superadmin', 'owner', 'bursar', 'accountant'], true)) {
  header('Location: ../login.php');
  exit;
}

$total_revenue = table_exists('financial_transactions')
  ? (float)(db()->fetchOne("SELECT COALESCE(SUM(amount), 0) AS total FROM financial_transactions WHERE transaction_type = 'revenue' AND status = 'completed'")['total'] ?? 0)
  : 0.0;

$total_expenses = table_exists('financial_transactions')
  ? (float)(db()->fetchOne("SELECT COALESCE(SUM(amount), 0) AS total FROM financial_transactions WHERE transaction_type = 'expense' AND status = 'completed'")['total'] ?? 0)
  : 0.0;

$pending_fees = table_exists('student_fees')
  ? (float)(db()->fetchOne("SELECT COALESCE(SUM(amount), 0) AS total FROM student_fees WHERE status = 'pending'")['total'] ?? 0)
  : 0.0;

$recent_transactions = table_exists('financial_transactions')
  ? (db()->fetchAll('SELECT transaction_date, description, amount, transaction_type, status, payment_method, reference_number FROM financial_transactions ORDER BY transaction_date DESC LIMIT 100') ?: [])
  : [];

$page_title = 'Financial Reports';
$page_icon = 'chart-line';
$page_subtitle = 'Consolidated financial reporting and transaction history';

ob_start();
?>
<div class="grid grid-cols-12 gap-6">
  <div class="col-span-12 bg-white rounded-xl border border-gray-100 p-6">
    <div class="flex items-center justify-between mb-4">
      <div>
        <h2 class="text-xl font-semibold">Financial Reports</h2>
        <p class="text-sm text-gray-500">Revenue, expense and cash-flow snapshot.</p>
      </div>
      <a href="financial-management.php" class="px-4 py-2 rounded-lg border border-gray-200 text-gray-700">Back to Financial Management</a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
      <div class="rounded-lg border border-gray-100 p-4">
        <p class="text-sm text-gray-500">Revenue</p>
        <p class="text-2xl font-bold">$<?php echo number_format($total_revenue, 2); ?></p>
      </div>
      <div class="rounded-lg border border-gray-100 p-4">
        <p class="text-sm text-gray-500">Expenses</p>
        <p class="text-2xl font-bold">$<?php echo number_format($total_expenses, 2); ?></p>
      </div>
      <div class="rounded-lg border border-gray-100 p-4">
        <p class="text-sm text-gray-500">Pending Fees</p>
        <p class="text-2xl font-bold">$<?php echo number_format($pending_fees, 2); ?></p>
      </div>
      <div class="rounded-lg border border-gray-100 p-4">
        <p class="text-sm text-gray-500">Net</p>
        <p class="text-2xl font-bold">$<?php echo number_format($total_revenue - $total_expenses, 2); ?></p>
      </div>
    </div>

    <section>
      <h3 class="font-semibold mb-2">Recent Transactions</h3>
      <div class="overflow-x-auto border rounded-lg">
        <table class="w-full text-sm">
          <thead>
            <tr class="bg-gray-50">
              <th class="px-3 py-2 text-left">Date</th>
              <th class="px-3 py-2 text-left">Description</th>
              <th class="px-3 py-2 text-left">Type</th>
              <th class="px-3 py-2 text-left">Amount</th>
              <th class="px-3 py-2 text-left">Payment</th>
              <th class="px-3 py-2 text-left">Reference</th>
              <th class="px-3 py-2 text-left">Status</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recent_transactions as $txn): ?>
              <tr class="border-t">
                <td class="px-3 py-2"><?php echo htmlspecialchars((string)($txn['transaction_date'] ?? '-')); ?></td>
                <td class="px-3 py-2"><?php echo htmlspecialchars((string)($txn['description'] ?? '-')); ?></td>
                <td class="px-3 py-2"><?php echo htmlspecialchars((string)($txn['transaction_type'] ?? '-')); ?></td>
                <td class="px-3 py-2"><?php echo number_format((float)($txn['amount'] ?? 0), 2); ?></td>
                <td class="px-3 py-2"><?php echo htmlspecialchars((string)($txn['payment_method'] ?? '-')); ?></td>
                <td class="px-3 py-2"><?php echo htmlspecialchars((string)($txn['reference_number'] ?? '-')); ?></td>
                <td class="px-3 py-2"><?php echo htmlspecialchars((string)($txn['status'] ?? '-')); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</div>
<?php
$page_content = ob_get_clean();
require BASE_PATH . '/resources/ui-core/layouts/master-dashboard.php';
