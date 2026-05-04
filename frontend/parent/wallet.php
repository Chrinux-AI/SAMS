<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_parent('../login.php');

$tenantId = current_tenant_id();
$children = [];

if (table_exists('private_point_accounts')) {
  $children = db()->fetchAll(
    "SELECT s.id AS student_id, u.full_name, s.admission_number, ppa.current_balance, ppa.account_status, ppa.last_allowance_run_at
     FROM parent_student_links psl
     INNER JOIN students s ON s.user_id = psl.student_id
     LEFT JOIN users u ON u.id = s.user_id
     LEFT JOIN private_point_accounts ppa ON ppa.student_id = s.id AND ppa.tenant_id = ?
     WHERE psl.parent_id = ?
     ORDER BY u.full_name ASC",
    [$tenantId, $_SESSION['user_id']]
  );
}

$page_title = 'Children Wallets';
$page_icon = 'account_balance_wallet';
$page_subtitle = 'Read-only visibility into each linked child private-point wallet';
ob_start();
?>
<div class="grid grid-cols-12 gap-6">
  <div class="col-span-12 bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
    <h3 class="text-lg font-bold text-primary mb-5">Children Private Points</h3>
    <div class="overflow-x-auto">
      <table class="sams-table">
        <thead><tr><th>Student</th><th>Admission No.</th><th>Status</th><th>Balance</th><th>Last Allowance</th></tr></thead>
        <tbody>
        <?php if (!$children): ?>
          <tr><td colspan="5" class="text-center text-slate-500">No linked children or wallet records yet.</td></tr>
        <?php else: foreach ($children as $child): ?>
          <tr>
            <td class="font-semibold"><?php echo htmlspecialchars($child['full_name'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars($child['admission_number'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars($child['account_status'] ?? 'not provisioned'); ?></td>
            <td class="font-bold text-primary">NGN <?php echo number_format((float) ($child['current_balance'] ?? 0), 2); ?></td>
            <td><?php echo htmlspecialchars(format_datetime($child['last_allowance_run_at'] ?? null)); ?></td>
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
