<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_login('../login.php');

if (!has_role('bursar') && !has_role('admin')) {
    redirect('../login.php', 'Access denied. Bursar privileges required.', 'error');
}

$full_name = $_SESSION['full_name'] ?? 'User';
$tenantId = (int)($_SESSION['tenant_id'] ?? 1);

function brs_scope_sql(string $table, string $alias = ''): array {
    global $tenantId;
    $qualified = $alias !== '' ? $alias . '.' : '';
    if (table_has_column($table, 'tenant_id')) {
        return ['sql' => " AND {$qualified}tenant_id = ?", 'params' => [$tenantId]];
    }
    if (table_has_column($table, 'school_id')) {
        return ['sql' => " AND {$qualified}school_id = ?", 'params' => [$tenantId]];
    }
    return ['sql' => '', 'params' => []];
}

function brs_count($table, $where = '1=1', $params = []) {
    try {
        if (!table_exists($table)) return 0;
        $scope = brs_scope_sql($table);
        return (int)db()->count($table, $where . $scope['sql'], array_merge($params, $scope['params']));
    } catch (Throwable $e) { return 0; }
}
function brs_sum($table, $col, $where = '1=1', $params = []) {
    try {
        if (!table_exists($table)) return 0;
        $scope = brs_scope_sql($table);
        $r = db()->fetchOne("SELECT COALESCE(SUM($col),0) AS total FROM $table WHERE $where{$scope['sql']}", array_merge($params, $scope['params']));
        return (float)($r['total'] ?? 0);
    } catch (Throwable $e) { return 0; }
}

$invoiceTable = table_exists('fee_invoices') ? 'fee_invoices' : (table_exists('invoices') ? 'invoices' : null);
$invoicePendingClause = $invoiceTable === 'invoices' ? "status IN ('unpaid','partial')" : "status = 'pending'";
$invoiceDefaulterClause = $invoiceTable === 'invoices'
    ? "status IN ('unpaid','partial') AND due_date < CURDATE()"
    : "status = 'overdue'";
$paymentAmountColumn = table_has_column('fee_payments', 'amount_paid') ? 'amount_paid' : 'amount';

$stats = [
    'total_students'    => brs_count('students'),
    'invoices'          => $invoiceTable ? brs_count($invoiceTable) : 0,
    'payments_today'    => brs_count('fee_payments', 'DATE(payment_date) = CURDATE()'),
    'total_collected'   => brs_sum('fee_payments', $paymentAmountColumn),
    'pending_invoices'  => $invoiceTable ? brs_count($invoiceTable, $invoicePendingClause) : 0,
    'defaulters'        => $invoiceTable ? brs_count($invoiceTable, $invoiceDefaulterClause) : 0,
    'scholarships'      => brs_count('scholarships'),
];

$recent_payments = [];
try {
    if (table_exists('fee_payments')) {
        $scope = brs_scope_sql('fee_payments', 'fp');
        $studentJoin = '';
        $nameExpr = "'' AS first_name, '' AS last_name";
        if (table_has_column('fee_payments', 'student_id')) {
            $studentJoin = " LEFT JOIN students s ON fp.student_id = s.id LEFT JOIN users u ON s.user_id = u.id";
            $nameExpr = "COALESCE(u.first_name, '') AS first_name, COALESCE(u.last_name, '') AS last_name";
        } elseif (table_has_column('fee_payments', 'fee_id') && table_exists('invoices') && table_has_column('invoices', 'student_id')) {
            $studentJoin = " LEFT JOIN invoices inv ON fp.fee_id = inv.id LEFT JOIN students s ON inv.student_id = s.id LEFT JOIN users u ON s.user_id = u.id";
            $nameExpr = "COALESCE(u.first_name, '') AS first_name, COALESCE(u.last_name, '') AS last_name";
        }
        $recent_payments = db()->fetchAll("
            SELECT fp.*, {$nameExpr}, {$paymentAmountColumn} AS payment_amount
            FROM fee_payments fp{$studentJoin}
            WHERE 1=1{$scope['sql']}
            ORDER BY fp.payment_date DESC LIMIT 10
        ", $scope['params']) ?: [];
    }
} catch (Throwable $e) {}

// Master layout configuration
$page_title = 'Bursar Dashboard';
$page_icon = 'fas fa-money-check-dollar';
$page_subtitle = 'Welcome back, ' . htmlspecialchars($full_name);

ob_start();
?>

<!-- Bento Grid Dashboard -->
<div class="grid grid-cols-12 gap-6">

  <!-- Welcome Banner (Top Full Width) -->
  <div class="col-span-12">
    <div class="bg-teal-700 text-white p-8 rounded-xl relative overflow-hidden group shadow-lg" style="background:linear-gradient(135deg, #0F766E 0%, #115E59 100%);">
      <div class="absolute -right-10 -bottom-10 opacity-10 group-hover:scale-110 transition-transform duration-700 pointer-events-none">
        <span class="material-symbols-outlined" style="font-size:180px">account_balance_wallet</span>
      </div>
      <div class="relative z-10 h-full flex flex-col justify-between">
        <div>
          <span class="text-[10px] font-bold uppercase tracking-widest text-teal-200">Bursary Department</span>
          <h1 class="text-3xl font-headline font-bold mt-2">Welcome back, <?php echo htmlspecialchars(explode(' ', $full_name)[0]); ?>!</h1>
          <p class="text-teal-100 mt-2 max-w-lg opacity-90">Manage student fee collections, process invoices, and track outstanding payments across the institution.</p>
        </div>
        <div class="mt-6 flex gap-4">
            <a href="fee-collection.php" class="px-5 py-2.5 bg-white text-teal-700 font-bold rounded-lg text-sm hover:shadow-lg hover:scale-105 transition-all w-fit flex items-center gap-2">
                <span class="material-symbols-outlined text-sm">point_of_sale</span> Collect Fee
            </a>
            <a href="invoices.php" class="px-5 py-2.5 bg-teal-800 border border-teal-500/50 text-white font-bold rounded-lg text-sm hover:bg-teal-900 transition-all w-fit flex items-center gap-2">
                <span class="material-symbols-outlined text-sm">receipt_long</span> View Invoices
            </a>
        </div>
      </div>
    </div>
  </div>

  <!-- Stats Cards Row 1 -->
  <div class="col-span-12 grid grid-cols-2 lg:grid-cols-4 gap-6">
    <div class="bg-white p-6 rounded-xl border border-slate-200 flex flex-col shadow-sm hover:border-teal-500/30 transition-colors group">
      <div class="flex justify-between items-center mb-4">
          <span class="text-slate-400 font-label text-[10px] uppercase tracking-wider font-bold">Total Collected</span>
          <div class="w-8 h-8 rounded-lg bg-teal-50 text-teal-600 flex items-center justify-center"><span class="material-symbols-outlined text-[16px]">account_balance</span></div>
      </div>
      <span class="text-3xl font-extrabold font-headline text-primary group-hover:text-teal-600 transition-colors">₦<?php echo number_format($stats['total_collected'], 0); ?></span>
    </div>
    
    <div class="bg-white p-6 rounded-xl border border-slate-200 flex flex-col shadow-sm hover:border-sky-500/30 transition-colors group">
      <div class="flex justify-between items-center mb-4">
          <span class="text-slate-400 font-label text-[10px] uppercase tracking-wider font-bold">Total Invoices</span>
          <div class="w-8 h-8 rounded-lg bg-sky-50 text-sky-600 flex items-center justify-center"><span class="material-symbols-outlined text-[16px]">receipt</span></div>
      </div>
      <span class="text-3xl font-extrabold font-headline text-primary group-hover:text-sky-600 transition-colors"><?php echo number_format($stats['invoices']); ?></span>
    </div>
    
    <div class="bg-white p-6 rounded-xl border border-slate-200 flex flex-col shadow-sm hover:border-emerald-500/30 transition-colors group">
      <div class="flex justify-between items-center mb-4">
          <span class="text-slate-400 font-label text-[10px] uppercase tracking-wider font-bold">Payments Today</span>
          <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center"><span class="material-symbols-outlined text-[16px]">today</span></div>
      </div>
      <span class="text-3xl font-extrabold font-headline text-primary group-hover:text-emerald-600 transition-colors"><?php echo number_format($stats['payments_today']); ?></span>
    </div>
    
    <div class="bg-white p-6 rounded-xl border border-slate-200 flex flex-col shadow-sm hover:border-amber-500/30 transition-colors group">
      <div class="flex justify-between items-center mb-4">
          <span class="text-slate-400 font-label text-[10px] uppercase tracking-wider font-bold">Pending Invoices</span>
          <div class="w-8 h-8 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center"><span class="material-symbols-outlined text-[16px]">pending_actions</span></div>
      </div>
      <span class="text-3xl font-extrabold font-headline text-primary group-hover:text-amber-600 transition-colors"><?php echo number_format($stats['pending_invoices']); ?></span>
    </div>
  </div>

  <!-- Stats Cards Row 2 -->
  <div class="col-span-12 grid grid-cols-2 lg:grid-cols-3 gap-6">
    <div class="bg-white p-6 rounded-xl border border-slate-200 flex flex-col shadow-sm hover:border-rose-500/30 transition-colors group">
      <div class="flex justify-between items-center mb-4">
          <span class="text-slate-400 font-label text-[10px] uppercase tracking-wider font-bold">Defaulters</span>
          <div class="w-8 h-8 rounded-lg bg-rose-50 text-rose-600 flex items-center justify-center"><span class="material-symbols-outlined text-[16px]">warning</span></div>
      </div>
      <span class="text-3xl font-extrabold font-headline text-rose-600"><?php echo number_format($stats['defaulters']); ?></span>
    </div>

    <div class="bg-white p-6 rounded-xl border border-slate-200 flex flex-col shadow-sm hover:border-violet-500/30 transition-colors group">
      <div class="flex justify-between items-center mb-4">
          <span class="text-slate-400 font-label text-[10px] uppercase tracking-wider font-bold">Scholarships</span>
          <div class="w-8 h-8 rounded-lg bg-violet-50 text-violet-600 flex items-center justify-center"><span class="material-symbols-outlined text-[16px]">workspace_premium</span></div>
      </div>
      <span class="text-3xl font-extrabold font-headline text-primary group-hover:text-violet-600 transition-colors"><?php echo number_format($stats['scholarships']); ?></span>
    </div>

    <div class="bg-white p-6 rounded-xl border border-slate-200 flex flex-col shadow-sm hover:border-teal-500/30 transition-colors group">
      <div class="flex justify-between items-center mb-4">
          <span class="text-slate-400 font-label text-[10px] uppercase tracking-wider font-bold">Total Students</span>
          <div class="w-8 h-8 rounded-lg bg-teal-50 text-teal-600 flex items-center justify-center"><span class="material-symbols-outlined text-[16px]">groups</span></div>
      </div>
      <span class="text-3xl font-extrabold font-headline text-primary group-hover:text-teal-600 transition-colors"><?php echo number_format($stats['total_students']); ?></span>
    </div>
  </div>

  <!-- Quick Actions & Recent Transactions -->
  <div class="col-span-12 grid grid-cols-1 lg:grid-cols-3 gap-6">
    
    <!-- Quick Actions (Left) -->
    <div class="lg:col-span-1 bg-white p-8 rounded-xl border border-slate-200 shadow-sm">
      <h3 class="font-headline font-bold text-lg text-primary mb-6">Quick Actions</h3>
      <div class="grid grid-cols-1 gap-4">
        
        <a href="defaulters.php" class="px-4 py-3 rounded-lg border border-rose-200 bg-rose-50 text-rose-700 font-bold hover:bg-rose-100 transition-colors flex items-center justify-between group">
            <div class="flex items-center gap-3">
                <span class="material-symbols-outlined">warning</span>
                <span>View Defaulters</span>
            </div>
            <span class="px-2 py-0.5 bg-white rounded-md text-xs"><?php echo $stats['defaulters']; ?></span>
        </a>

        <a href="daily-summary.php" class="px-4 py-3 rounded-lg border border-slate-200 text-slate-700 font-bold hover:bg-slate-50 transition-colors flex items-center gap-3">
            <span class="material-symbols-outlined text-teal-600">pie_chart</span>
            <span>Daily Summary</span>
        </a>

        <a href="receipts.php" class="px-4 py-3 rounded-lg border border-slate-200 text-slate-700 font-bold hover:bg-slate-50 transition-colors flex items-center gap-3">
            <span class="material-symbols-outlined text-teal-600">receipt</span>
            <span>Print Receipt</span>
        </a>

      </div>
    </div>

    <!-- Recent Payments (Right) -->
    <div class="lg:col-span-2 bg-white p-6 rounded-xl border border-slate-200 shadow-sm flex flex-col">
      <h3 class="font-headline font-bold text-sm text-primary mb-6 flex items-center gap-2">
        <span class="material-symbols-outlined text-[18px]">history</span> Recent Payments
      </h3>
      
      <div class="flex-grow">
        <?php if (!empty($recent_payments)): ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="text-[10px] uppercase tracking-wider text-slate-400 border-b border-slate-100">
                            <th class="pb-3 font-bold">Student</th>
                            <th class="pb-3 font-bold">Amount</th>
                            <th class="pb-3 font-bold">Method</th>
                            <th class="pb-3 font-bold">Date</th>
                            <th class="pb-3 font-bold text-right">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($recent_payments as $p): ?>
                        <tr class="hover:bg-slate-50 transition-colors group">
                            <td class="py-3 font-bold text-primary"><?php echo htmlspecialchars(($p['first_name'] ?? '') . ' ' . ($p['last_name'] ?? '')); ?></td>
                            <td class="py-3 font-bold text-teal-600">₦<?php echo number_format((float)($p['payment_amount'] ?? 0), 2); ?></td>
                            <td class="py-3 text-slate-600"><span class="px-2 py-1 bg-slate-100 rounded text-xs"><?php echo ucfirst($p['payment_method'] ?? 'cash'); ?></span></td>
                            <td class="py-3 text-slate-500 font-medium"><?php echo date('M j, Y', strtotime($p['payment_date'])); ?></td>
                            <td class="py-3 text-right">
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-[10px] font-bold uppercase tracking-wider bg-emerald-100 text-emerald-700">
                                    <span class="material-symbols-outlined text-[12px]">check_circle</span> Paid
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="flex flex-col items-center justify-center text-center h-full text-slate-400 pb-10 pt-4">
                <span class="material-symbols-outlined text-4xl mb-3 opacity-20">inventory_2</span>
                <p class="text-xs max-w-[250px]">No fee module tables found yet. Run the fee system setup SQL to enable full functionality.</p>
                <div class="mt-4 px-4 py-2 bg-teal-50 border border-teal-100 rounded-lg text-teal-800 text-[11px] font-medium inline-flex items-center gap-2">
                    <span class="material-symbols-outlined text-[14px]">info</span>
                    Fee tables will be created from the migration files.
                </div>
            </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

</div>
<?php
$page_content = ob_get_clean();
require BASE_PATH . '/resources/ui-core/layouts/master-dashboard.php';
?>
