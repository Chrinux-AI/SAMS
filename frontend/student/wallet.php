<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_student('../login.php');

$tenantId = current_tenant_id();
$student = db()->fetchOne('SELECT id, admission_number FROM students WHERE user_id = ? LIMIT 1', [$_SESSION['user_id']]);
$account = null;
$ledger = [];

if ($student && table_exists('private_point_accounts')) {
  $account = db()->fetchOne('SELECT * FROM private_point_accounts WHERE tenant_id = ? AND student_id = ? LIMIT 1', [$tenantId, $student['id']]);
  $ledger = db()->fetchAll('SELECT * FROM private_point_ledger WHERE tenant_id = ? AND student_id = ? ORDER BY created_at DESC LIMIT 25', [$tenantId, $student['id']]);
}

$page_title = 'Private Points';
$page_icon = 'account_balance_wallet';
$page_subtitle = 'Your internal NGN wallet powered by merit and class performance';
ob_start();
?>
<div class="grid grid-cols-12 gap-6">
  <div class="col-span-12 md:col-span-5 xl:col-span-4 bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
    <p class="text-[10px] uppercase tracking-widest font-bold text-slate-400">Current Balance</p>
    <p class="text-4xl font-extrabold text-primary mt-4">NGN <?php echo number_format((float) ($account['current_balance'] ?? 0), 2); ?></p>
    <p class="text-xs text-slate-500 mt-2">This wallet is separate from fees and follows the private-point ledger only.</p>
  </div>
  <div class="col-span-12 md:col-span-7 xl:col-span-8 bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
    <h3 class="text-lg font-bold text-primary mb-5">Wallet Ledger</h3>
    <div class="overflow-x-auto">
      <table class="sams-table">
        <thead><tr><th>Type</th><th>Amount</th><th>Reason</th><th>When</th></tr></thead>
        <tbody>
        <?php if (!$ledger): ?>
          <tr><td colspan="4" class="text-center text-slate-500">No wallet activity yet.</td></tr>
        <?php else: foreach ($ledger as $entry): ?>
          <tr>
            <td class="font-semibold"><?php echo htmlspecialchars($entry['entry_type']); ?></td>
            <td class="font-bold <?php echo ((float)$entry['amount']) >= 0 ? 'text-emerald-700' : 'text-rose-700'; ?>">NGN <?php echo number_format((float) $entry['amount'], 2); ?></td>
            <td><?php echo htmlspecialchars($entry['reason'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars(format_datetime($entry['created_at'] ?? null)); ?></td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php
$page_content = ob_get_clean();
require BASE_PATH . '/resources/ui-core/layouts/master-dashboard.php';
