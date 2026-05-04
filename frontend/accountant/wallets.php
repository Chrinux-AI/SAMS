<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('accountant', '../login.php');

$tenantId = current_tenant_id();

$walletTotals = [
    'accounts' => table_exists('private_point_accounts') ? (int) (db()->fetchOne('SELECT COUNT(*) AS total FROM private_point_accounts WHERE tenant_id = ?', [$tenantId])['total'] ?? 0) : 0,
    'balance' => table_exists('private_point_accounts') ? (float) (db()->fetchOne('SELECT COALESCE(SUM(current_balance), 0) AS total FROM private_point_accounts WHERE tenant_id = ?', [$tenantId])['total'] ?? 0) : 0,
    'monthly_runs' => table_exists('monthly_allowance_runs') ? (int) (db()->fetchOne('SELECT COUNT(*) AS total FROM monthly_allowance_runs WHERE tenant_id = ?', [$tenantId])['total'] ?? 0) : 0,
    'class_points' => table_exists('class_point_accounts') ? (int) (db()->fetchOne('SELECT COALESCE(SUM(current_balance), 0) AS total FROM class_point_accounts WHERE tenant_id = ?', [$tenantId])['total'] ?? 0) : 0
];

$accounts = table_exists('private_point_accounts')
    ? db()->fetchAll(
        "SELECT ppa.*, s.admission_number, u.full_name
         FROM private_point_accounts ppa
         LEFT JOIN students s ON s.id = ppa.student_id
         LEFT JOIN users u ON u.id = s.user_id
         WHERE ppa.tenant_id = ?
         ORDER BY ppa.current_balance DESC, ppa.updated_at DESC
         LIMIT 20",
        [$tenantId]
    )
    : [];

$runs = table_exists('monthly_allowance_runs')
    ? db()->fetchAll(
        "SELECT mar.*, c.class_name
         FROM monthly_allowance_runs mar
         LEFT JOIN classes c ON c.id = mar.class_id
         WHERE mar.tenant_id = ?
         ORDER BY mar.processed_at DESC, mar.created_at DESC
         LIMIT 12",
        [$tenantId]
    )
    : [];

$page_title = 'Private Point Wallets';
$page_icon = 'account_balance_wallet';
$page_subtitle = 'Internal NGN wallet reconciliation for monthly class-point allowances';
ob_start();
?>

<div class="grid grid-cols-12 gap-6">
  <div class="col-span-12 md:col-span-6 xl:col-span-3 bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
    <p class="text-[10px] uppercase tracking-widest font-bold text-slate-400">Wallet Accounts</p>
    <p class="text-4xl font-extrabold text-primary mt-4"><?php echo number_format($walletTotals['accounts']); ?></p>
    <p class="text-xs text-slate-500 mt-2">Student wallets currently provisioned</p>
  </div>
  <div class="col-span-12 md:col-span-6 xl:col-span-3 bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
    <p class="text-[10px] uppercase tracking-widest font-bold text-slate-400">Total Wallet Balance</p>
    <p class="text-4xl font-extrabold text-primary mt-4">NGN <?php echo number_format($walletTotals['balance'], 2); ?></p>
    <p class="text-xs text-slate-500 mt-2">Internal liability held for students</p>
  </div>
  <div class="col-span-12 md:col-span-6 xl:col-span-3 bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
    <p class="text-[10px] uppercase tracking-widest font-bold text-slate-400">Allowance Runs</p>
    <p class="text-4xl font-extrabold text-primary mt-4"><?php echo number_format($walletTotals['monthly_runs']); ?></p>
    <p class="text-xs text-slate-500 mt-2">Idempotent monthly credit batches processed</p>
  </div>
  <div class="col-span-12 md:col-span-6 xl:col-span-3 bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
    <p class="text-[10px] uppercase tracking-widest font-bold text-slate-400">Current Class Points</p>
    <p class="text-4xl font-extrabold text-primary mt-4"><?php echo number_format($walletTotals['class_points']); ?></p>
    <p class="text-xs text-slate-500 mt-2">Base figure driving the next allowance cycle</p>
  </div>

  <div class="col-span-12 xl:col-span-7 bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
    <div class="flex items-center justify-between mb-5">
      <div>
        <h3 class="text-lg font-bold text-primary">Wallet Accounts</h3>
        <p class="text-xs text-slate-500">Top balances across students in this tenant.</p>
      </div>
    </div>
    <div class="overflow-x-auto">
      <table class="sams-table">
        <thead>
          <tr>
            <th>Student</th>
            <th>Admission No.</th>
            <th>Status</th>
            <th>Balance</th>
            <th>Last Allowance Run</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($accounts)): ?>
          <tr><td colspan="5" class="text-center text-slate-500">No private point accounts found yet.</td></tr>
          <?php else: ?>
          <?php foreach ($accounts as $account): ?>
          <tr>
            <td class="font-semibold"><?php echo htmlspecialchars($account['full_name'] ?? ('Student #' . ($account['student_id'] ?? ''))); ?></td>
            <td><?php echo htmlspecialchars($account['admission_number'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars($account['account_status'] ?? 'active'); ?></td>
            <td class="font-bold text-primary">NGN <?php echo number_format((float) ($account['current_balance'] ?? 0), 2); ?></td>
            <td><?php echo htmlspecialchars(format_datetime($account['last_allowance_run_at'] ?? null)); ?></td>
          </tr>
          <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="col-span-12 xl:col-span-5 bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
    <div class="flex items-center justify-between mb-5">
      <div>
        <h3 class="text-lg font-bold text-primary">Recent Allowance Runs</h3>
        <p class="text-xs text-slate-500">Snapshot-driven monthly credits with rerun protection.</p>
      </div>
    </div>
    <div class="space-y-4">
      <?php if (empty($runs)): ?>
      <p class="text-sm text-slate-500">No allowance runs have been posted yet.</p>
      <?php else: ?>
      <?php foreach ($runs as $run): ?>
      <div class="rounded-lg border border-slate-100 p-4">
        <div class="flex items-center justify-between gap-3">
          <div>
            <p class="font-semibold text-primary text-sm"><?php echo htmlspecialchars($run['class_name'] ?? ('Class #' . ($run['class_id'] ?? ''))); ?></p>
            <p class="text-xs text-slate-500"><?php echo htmlspecialchars($run['run_month'] ?? ''); ?> · <?php echo number_format((int) ($run['student_count'] ?? 0)); ?> students</p>
          </div>
          <span class="font-bold text-primary text-sm">NGN <?php echo number_format((float) ($run['allowance_per_student'] ?? 0), 2); ?></span>
        </div>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php
$page_content = ob_get_clean();
require BASE_PATH . '/resources/ui-core/layouts/master-dashboard.php';
