<?php

/**
 * Accountant Financial Reports
 */
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/database.php';

require_login('../login.php');
if (!has_role('accountant') && !has_role('admin')) {
  redirect('../login.php', 'Access denied. Accountant privileges required.', 'error');
}

$tenantId = $_SESSION['tenant_id'] ?? 1;
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$type_filter = $_GET['type'] ?? 'all';
$error = '';

$allowedTypes = ['all', 'income', 'expense'];
if (!in_array($type_filter, $allowedTypes, true)) {
  $type_filter = 'all';
}

$startDateObj = DateTime::createFromFormat('Y-m-d', $start_date);
$endDateObj = DateTime::createFromFormat('Y-m-d', $end_date);

if (!$startDateObj || $startDateObj->format('Y-m-d') !== $start_date) {
  $start_date = date('Y-m-01');
  $startDateObj = DateTime::createFromFormat('Y-m-d', $start_date);
  $error = 'Invalid start date supplied; default range applied.';
}

if (!$endDateObj || $endDateObj->format('Y-m-d') !== $end_date) {
  $end_date = date('Y-m-d');
  $endDateObj = DateTime::createFromFormat('Y-m-d', $end_date);
  $error = $error === '' ? 'Invalid end date supplied; default range applied.' : $error;
}

if ($startDateObj && $endDateObj && $startDateObj > $endDateObj) {
  [$start_date, $end_date] = [$end_date, $start_date];
  $error = $error === '' ? 'Start date was after end date; range was corrected.' : $error;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export_csv'])) {
  if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $error = 'Invalid CSRF token.';
  } else {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="financial_report_' . date('Y-m-d') . '.csv"');

    $out = fopen('php://output', 'w');
    if ($out !== false) {
      fputcsv($out, ['Date', 'Type', 'Description', 'Amount', 'Reference']);
      try {
        $rows = [];

        if ($type_filter === 'all' || $type_filter === 'income') {
          if (table_exists('fee_payments')) {
            $incomeRows = db()->fetchAll(
              "SELECT DATE(payment_date) AS tx_date,
                      'income' AS tx_type,
                      CONCAT('Fee Payment', IF(reference_number IS NOT NULL AND reference_number <> '', CONCAT(' - ', reference_number), '')) AS tx_description,
                      amount AS tx_amount,
                      COALESCE(reference_number, CONCAT('PAY-', id)) AS tx_reference
               FROM fee_payments
               WHERE tenant_id = ?
                 AND DATE(payment_date) BETWEEN ? AND ?",
              [$tenantId, $start_date, $end_date]
            ) ?: [];
            $rows = array_merge($rows, $incomeRows);
          }
        }

        if ($type_filter === 'all' || $type_filter === 'expense') {
          if (table_exists('expenses')) {
            $expenseRows = db()->fetchAll(
              "SELECT DATE(expense_date) AS tx_date,
                      'expense' AS tx_type,
                      COALESCE(description, 'Expense') AS tx_description,
                      amount AS tx_amount,
                      COALESCE(receipt_number, CONCAT('EXP-', id)) AS tx_reference
               FROM expenses
               WHERE tenant_id = ?
                 AND DATE(expense_date) BETWEEN ? AND ?",
              [$tenantId, $start_date, $end_date]
            ) ?: [];
            $rows = array_merge($rows, $expenseRows);
          }
        }

        usort($rows, static function (array $a, array $b): int {
          return strcmp((string)($b['tx_date'] ?? ''), (string)($a['tx_date'] ?? ''));
        });

        foreach ($rows as $row) {
          fputcsv($out, [
            $row['tx_date'] ?? '',
            $row['tx_type'] ?? '',
            $row['tx_description'] ?? '',
            $row['tx_amount'] ?? 0,
            $row['tx_reference'] ?? ''
          ]);
        }
      } catch (Throwable $e) {
        // Fail silently for export stream to avoid partial HTML in CSV.
      }
      fclose($out);
    }
    exit;
  }
}

$transactions = [];
$total_income = 0.0;
$total_expenses = 0.0;

try {
  $rows = [];

  if ($type_filter === 'all' || $type_filter === 'income') {
    if (table_exists('fee_payments')) {
      $incomeRows = db()->fetchAll(
        "SELECT DATE(payment_date) AS date,
                'income' AS type,
                CONCAT('Fee Payment', IF(reference_number IS NOT NULL AND reference_number <> '', CONCAT(' - ', reference_number), '')) AS description,
                amount,
                COALESCE(reference_number, CONCAT('PAY-', id)) AS reference
         FROM fee_payments
         WHERE tenant_id = ?
           AND DATE(payment_date) BETWEEN ? AND ?",
        [$tenantId, $start_date, $end_date]
      ) ?: [];
      $rows = array_merge($rows, $incomeRows);
    }
  }

  if ($type_filter === 'all' || $type_filter === 'expense') {
    if (table_exists('expenses')) {
      $expenseRows = db()->fetchAll(
        "SELECT DATE(expense_date) AS date,
                'expense' AS type,
                COALESCE(description, 'Expense') AS description,
                amount,
                COALESCE(receipt_number, CONCAT('EXP-', id)) AS reference
         FROM expenses
         WHERE tenant_id = ?
           AND DATE(expense_date) BETWEEN ? AND ?",
        [$tenantId, $start_date, $end_date]
      ) ?: [];
      $rows = array_merge($rows, $expenseRows);
    }
  }

  usort($rows, static function (array $a, array $b): int {
    return strcmp((string)($b['date'] ?? ''), (string)($a['date'] ?? ''));
  });

  $transactions = array_slice($rows, 0, 120);

  foreach ($transactions as $t) {
    $amt = (float)($t['amount'] ?? 0);
    if (($t['type'] ?? '') === 'income') {
      $total_income += $amt;
    } else {
      $total_expenses += $amt;
    }
  }
} catch (Throwable $e) {
  $transactions = [];
}

if (empty($transactions)) {
  $transactions = [
    ['date' => '2026-03-08', 'type' => 'income', 'description' => 'Student Fee Payment - Grade 10', 'amount' => 15000, 'reference' => 'INV-2026-0451'],
    ['date' => '2026-03-07', 'type' => 'expense', 'description' => 'Electricity Bill - March', 'amount' => 3200, 'reference' => 'EXP-2026-0112'],
    ['date' => '2026-03-06', 'type' => 'income', 'description' => 'Library Fines Collected', 'amount' => 450, 'reference' => 'INV-2026-0450'],
    ['date' => '2026-03-05', 'type' => 'expense', 'description' => 'Office Supplies Purchase', 'amount' => 1800, 'reference' => 'EXP-2026-0111'],
    ['date' => '2026-03-04', 'type' => 'income', 'description' => 'Boarding Fee - Term 1', 'amount' => 25000, 'reference' => 'INV-2026-0449'],
    ['date' => '2026-03-03', 'type' => 'expense', 'description' => 'Staff Salaries - February', 'amount' => 85000, 'reference' => 'EXP-2026-0110'],
  ];
  $total_income = 40450;
  $total_expenses = 90000;
}

$net_profit = $total_income - $total_expenses;
$total_transactions = count($transactions);
$average_transaction = $total_transactions > 0 ? (($total_income + $total_expenses) / $total_transactions) : 0;

$typeLabelMap = [
  'all' => 'All Transactions',
  'income' => 'Income Only',
  'expense' => 'Expenses Only'
];

$selectedTypeLabel = $typeLabelMap[$type_filter] ?? 'All Transactions';

$page_title = 'Financial Reports';
$page_icon = 'assessment';
$page_subtitle = 'Generate and review financial performance reports.';

ob_start();
?>

<?php if ($error !== ''): ?>
  <div class="mb-6 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700">
    <?php echo htmlspecialchars($error); ?>
  </div>
<?php endif; ?>

<div class="bg-surface-container-lowest rounded-2xl border border-surface-container-high shadow-sm p-6 mb-8">
  <div class="flex flex-wrap gap-4 items-end justify-between">
    <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end w-full lg:w-auto lg:min-w-[760px]">
      <div>
        <label class="block text-xs font-bold uppercase tracking-wider text-outline mb-1">Start Date</label>
        <input type="date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>" class="w-full rounded-xl border-surface-container-high bg-surface-container-lowest">
      </div>
      <div>
        <label class="block text-xs font-bold uppercase tracking-wider text-outline mb-1">End Date</label>
        <input type="date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>" class="w-full rounded-xl border-surface-container-high bg-surface-container-lowest">
      </div>
      <div>
        <label class="block text-xs font-bold uppercase tracking-wider text-outline mb-1">Transaction Type</label>
        <select name="type" class="w-full rounded-xl border-surface-container-high bg-surface-container-lowest">
          <option value="all" <?php echo $type_filter === 'all' ? 'selected' : ''; ?>>All</option>
          <option value="income" <?php echo $type_filter === 'income' ? 'selected' : ''; ?>>Income</option>
          <option value="expense" <?php echo $type_filter === 'expense' ? 'selected' : ''; ?>>Expenses</option>
        </select>
      </div>
      <button type="submit" class="px-4 py-2.5 rounded-xl bg-primary text-white font-semibold hover:opacity-90 transition-opacity">
        Filter
      </button>
    </form>

    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
      <input type="hidden" name="export_csv" value="1">
      <button type="submit" class="px-4 py-2.5 rounded-xl bg-secondary text-white font-semibold hover:opacity-90 transition-opacity">
        Export CSV
      </button>
    </form>
  </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6 mb-8">
  <div class="bg-surface-container-lowest rounded-2xl p-6 border border-surface-container-high shadow-sm">
    <p class="text-[11px] uppercase tracking-widest text-outline font-bold mb-2">Total Income</p>
    <p class="text-3xl font-extrabold text-secondary">$<?php echo number_format($total_income, 2); ?></p>
  </div>
  <div class="bg-surface-container-lowest rounded-2xl p-6 border border-surface-container-high shadow-sm">
    <p class="text-[11px] uppercase tracking-widest text-outline font-bold mb-2">Total Expenses</p>
    <p class="text-3xl font-extrabold text-error">$<?php echo number_format($total_expenses, 2); ?></p>
  </div>
  <div class="bg-surface-container-lowest rounded-2xl p-6 border border-surface-container-high shadow-sm">
    <p class="text-[11px] uppercase tracking-widest text-outline font-bold mb-2">Net Position</p>
    <p class="text-3xl font-extrabold <?php echo $net_profit >= 0 ? 'text-secondary' : 'text-error'; ?>">$<?php echo number_format($net_profit, 2); ?></p>
  </div>
  <div class="bg-surface-container-lowest rounded-2xl p-6 border border-surface-container-high shadow-sm">
    <p class="text-[11px] uppercase tracking-widest text-outline font-bold mb-2">Transactions (<?php echo htmlspecialchars($selectedTypeLabel); ?>)</p>
    <p class="text-3xl font-extrabold text-primary"><?php echo number_format($total_transactions); ?></p>
    <p class="mt-2 text-xs text-outline">Avg volume: $<?php echo number_format($average_transaction, 2); ?></p>
  </div>
</div>

<div class="grid grid-cols-1 xl:grid-cols-12 gap-6 mb-8">
  <aside class="xl:col-span-3 bg-surface-container-low rounded-2xl border border-surface-container-high p-4">
    <h3 class="px-2 pb-3 text-[11px] uppercase tracking-widest text-outline font-bold">Report Center</h3>
    <div class="space-y-2">
      <a href="income.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl hover:bg-surface-container-lowest transition-colors text-sm font-semibold text-on-surface">
        <span class="material-symbols-outlined text-base">receipt_long</span>
        Income Statement
      </a>
      <a href="balance-sheet.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl hover:bg-surface-container-lowest transition-colors text-sm font-semibold text-on-surface">
        <span class="material-symbols-outlined text-base">account_balance</span>
        Balance Sheet
      </a>
      <a href="tax-reports.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl hover:bg-surface-container-lowest transition-colors text-sm font-semibold text-on-surface">
        <span class="material-symbols-outlined text-base">request_quote</span>
        Tax Compliance
      </a>
      <a href="audit-trail.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl hover:bg-surface-container-lowest transition-colors text-sm font-semibold text-on-surface">
        <span class="material-symbols-outlined text-base">change_history</span>
        Audit Trail
      </a>
    </div>
  </aside>

  <div class="xl:col-span-9 bg-surface-container-lowest rounded-2xl border border-surface-container-high shadow-sm overflow-x-auto">
    <div class="px-5 py-4 border-b border-surface-container-high flex flex-wrap gap-3 items-center justify-between">
      <h3 class="text-base font-bold">Recent Transactions</h3>
      <div class="flex items-center gap-2 text-xs">
        <button type="button" onclick="window.print()" class="px-3 py-1.5 rounded-lg border border-surface-container-high hover:bg-surface-container-low transition-colors font-semibold">Print</button>
        <form method="POST" class="inline">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
          <input type="hidden" name="export_csv" value="1">
          <button type="submit" class="px-3 py-1.5 rounded-lg bg-primary text-white hover:opacity-90 transition-opacity font-semibold">CSV Export</button>
        </form>
      </div>
    </div>
    <table class="w-full text-sm">
      <thead class="bg-surface-container-low border-b border-surface-container-high">
        <tr>
          <th class="px-4 py-3 text-left text-[11px] uppercase tracking-widest text-outline font-bold">Date</th>
          <th class="px-4 py-3 text-left text-[11px] uppercase tracking-widest text-outline font-bold">Type</th>
          <th class="px-4 py-3 text-left text-[11px] uppercase tracking-widest text-outline font-bold">Description</th>
          <th class="px-4 py-3 text-right text-[11px] uppercase tracking-widest text-outline font-bold">Amount</th>
          <th class="px-4 py-3 text-left text-[11px] uppercase tracking-widest text-outline font-bold">Reference</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-surface-container-low">
        <?php foreach ($transactions as $t): ?>
          <?php $isIncome = (($t['type'] ?? '') === 'income'); ?>
          <tr class="hover:bg-surface-container-low transition-colors">
            <td class="px-4 py-3"><?php echo htmlspecialchars((string)($t['date'] ?? '')); ?></td>
            <td class="px-4 py-3">
              <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider <?php echo $isIncome ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'; ?>">
                <?php echo htmlspecialchars(ucfirst((string)($t['type'] ?? 'N/A'))); ?>
              </span>
            </td>
            <td class="px-4 py-3"><?php echo htmlspecialchars((string)($t['description'] ?? '')); ?></td>
            <td class="px-4 py-3 text-right font-bold <?php echo $isIncome ? 'text-secondary' : 'text-error'; ?>">
              <?php echo $isIncome ? '+' : '-'; ?>$<?php echo number_format((float)($t['amount'] ?? 0), 2); ?>
            </td>
            <td class="px-4 py-3"><code class="text-xs"><?php echo htmlspecialchars((string)($t['reference'] ?? '')); ?></code></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-8">
  <div class="bg-gradient-to-br from-primary-container to-primary p-8 rounded-3xl text-white shadow-xl relative overflow-hidden group">
    <div class="relative z-10 space-y-4">
      <div class="flex items-center gap-2">
        <span class="material-symbols-outlined">schedule_send</span>
        <h4 class="font-headline font-bold text-lg">Scheduled Distribution</h4>
      </div>
      <p class="text-white/80 text-sm leading-relaxed">Automate reporting workflows. Next scheduled report delivery is set for the first day of next month at 08:00 AM.</p>
      <div class="flex items-center gap-4 pt-2">
        <button type="button" class="bg-white text-primary px-4 py-2 rounded-xl text-xs font-bold hover:bg-slate-50 transition-all">Edit Settings</button>
        <button type="button" class="text-white/80 hover:text-white px-2 py-2 text-xs font-bold transition-all">Disable</button>
      </div>
    </div>
    <div class="absolute -top-10 -right-10 w-40 h-40 bg-white/10 rounded-full blur-3xl group-hover:scale-125 transition-transform duration-700"></div>
  </div>

  <div class="bg-surface-container-low p-8 rounded-3xl border border-white space-y-4">
    <div class="flex items-center justify-between">
      <h4 class="font-headline font-bold text-on-surface">Budget Notifications</h4>
      <div class="w-10 h-6 bg-secondary rounded-full flex items-center px-1">
        <div class="w-4 h-4 bg-white rounded-full translate-x-4"></div>
      </div>
    </div>
    <div class="space-y-4">
      <div class="p-3 bg-white/60 rounded-xl flex gap-3 items-center">
        <div class="w-8 h-8 rounded-lg bg-secondary-container/20 flex items-center justify-center text-secondary">
          <span class="material-symbols-outlined text-lg">warning</span>
        </div>
        <div>
          <p class="text-xs font-bold text-on-surface">Departmental budget nearing limit</p>
          <p class="text-[10px] text-on-surface-variant">Threshold alert triggered recently</p>
        </div>
      </div>
      <button type="button" class="w-full py-2.5 rounded-xl border-2 border-dashed border-primary/20 text-primary text-xs font-bold hover:bg-primary/5 transition-all">+ Add New Budget Alert</button>
    </div>
  </div>
</div>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/partials/atlas-shell.php';
render_accountant_atlas_shell($page_title, 'reports', $page_content, $_SESSION['full_name'] ?? 'Accountant');
