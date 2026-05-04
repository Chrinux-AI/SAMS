<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once PROJECT_ROOT . '/backend/includes/advanced-sams.php';

require_admin('../login.php');

$tenantId = current_tenant_id();

$ranking = table_exists('class_point_accounts')
    ? db()->fetchAll(
        "SELECT cpa.*, c.class_name
         FROM class_point_accounts cpa
         LEFT JOIN classes c ON c.id = cpa.class_id
         WHERE cpa.tenant_id = ?
         ORDER BY cpa.current_balance DESC, cpa.updated_at DESC",
        [$tenantId]
    )
    : [];

$recentWalletEntries = table_exists('private_point_ledger')
    ? db()->fetchAll(
        "SELECT ppl.*, s.admission_number, u.full_name
         FROM private_point_ledger ppl
         LEFT JOIN students s ON s.id = ppl.student_id
         LEFT JOIN users u ON u.id = s.user_id
         WHERE ppl.tenant_id = ?
         ORDER BY ppl.created_at DESC
         LIMIT 12",
        [$tenantId]
    )
    : [];

$recentEnforcement = table_exists('enforcement_actions')
    ? db()->fetchAll(
        "SELECT *
         FROM enforcement_actions
         WHERE tenant_id = ?
         ORDER BY created_at DESC
         LIMIT 10",
        [$tenantId]
    )
    : [];

$totals = [
    'class_points' => 0,
    'wallet_value' => 0.0,
    'active_rules' => table_exists('merit_rules') ? (int) (db()->fetchOne("SELECT COUNT(*) AS total FROM merit_rules WHERE tenant_id = ? AND rule_status = 'active'", [$tenantId])['total'] ?? 0) : 0,
    'enforcement_open' => table_exists('enforcement_actions') ? (int) (db()->fetchOne("SELECT COUNT(*) AS total FROM enforcement_actions WHERE tenant_id = ? AND action_status = 'active'", [$tenantId])['total'] ?? 0) : 0
];

foreach ($ranking as $row) {
    $totals['class_points'] += (int) ($row['current_balance'] ?? 0);
}
if (table_exists('private_point_accounts')) {
    $totals['wallet_value'] = (float) (db()->fetchOne('SELECT COALESCE(SUM(current_balance), 0) AS total FROM private_point_accounts WHERE tenant_id = ?', [$tenantId])['total'] ?? 0);
}

$page_title = 'Merit Overview';
$page_icon = 'leaderboard';
$page_subtitle = 'Class ranking, wallet activity, and enforcement audit';
ob_start();
?>

<div class="grid grid-cols-12 gap-6">
  <div class="col-span-12 md:col-span-6 xl:col-span-3 bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
    <p class="text-[10px] uppercase tracking-widest font-bold text-slate-400">Total Class Points</p>
    <p class="text-4xl font-extrabold text-primary mt-4"><?php echo number_format($totals['class_points']); ?></p>
    <p class="text-xs text-slate-500 mt-2">Current CP balance across ranked class-arms</p>
  </div>
  <div class="col-span-12 md:col-span-6 xl:col-span-3 bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
    <p class="text-[10px] uppercase tracking-widest font-bold text-slate-400">Private Point Float</p>
    <p class="text-4xl font-extrabold text-primary mt-4">NGN <?php echo number_format($totals['wallet_value'], 2); ?></p>
    <p class="text-xs text-slate-500 mt-2">Internal wallet value backed by immutable ledger entries</p>
  </div>
  <div class="col-span-12 md:col-span-6 xl:col-span-3 bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
    <p class="text-[10px] uppercase tracking-widest font-bold text-slate-400">Active Rules</p>
    <p class="text-4xl font-extrabold text-primary mt-4"><?php echo number_format($totals['active_rules']); ?></p>
    <p class="text-xs text-slate-500 mt-2">Deterministic scoring rules ready for approval workflows</p>
  </div>
  <div class="col-span-12 md:col-span-6 xl:col-span-3 bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
    <p class="text-[10px] uppercase tracking-widest font-bold text-slate-400">Open Enforcement</p>
    <p class="text-4xl font-extrabold text-primary mt-4"><?php echo number_format($totals['enforcement_open']); ?></p>
    <p class="text-xs text-slate-500 mt-2">Soft restrictions that still preserve the full audit trail</p>
  </div>

  <div class="col-span-12 xl:col-span-7 bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
    <div class="flex items-center justify-between mb-5">
      <div>
        <h3 class="text-lg font-bold text-primary">Class Ranking Board</h3>
        <p class="text-xs text-slate-500">S-System ordering based on current class-point balance.</p>
      </div>
    </div>
    <div class="overflow-x-auto">
      <table class="sams-table">
        <thead>
          <tr>
            <th>Rank</th>
            <th>Class</th>
            <th>Session</th>
            <th>Term</th>
            <th>Class Points</th>
            <th>Updated</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($ranking)): ?>
          <tr><td colspan="6" class="text-center text-slate-500">No class-point accounts yet.</td></tr>
          <?php else: ?>
          <?php foreach ($ranking as $index => $row): ?>
          <tr>
            <td class="font-semibold">#<?php echo $index + 1; ?></td>
            <td><?php echo htmlspecialchars($row['class_name'] ?? ('Class #' . ($row['class_id'] ?? ''))); ?></td>
            <td><?php echo htmlspecialchars($row['academic_session'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars($row['academic_term'] ?? ''); ?></td>
            <td class="font-bold text-primary"><?php echo number_format((int) ($row['current_balance'] ?? 0)); ?></td>
            <td><?php echo htmlspecialchars(format_datetime($row['updated_at'] ?? $row['created_at'] ?? null)); ?></td>
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
        <h3 class="text-lg font-bold text-primary">Recent Wallet Activity</h3>
        <p class="text-xs text-slate-500">Monthly credits, rewards, fines, and adjustments.</p>
      </div>
    </div>
    <div class="space-y-4">
      <?php if (empty($recentWalletEntries)): ?>
      <p class="text-sm text-slate-500">No wallet activity recorded yet.</p>
      <?php else: ?>
      <?php foreach ($recentWalletEntries as $entry): ?>
      <div class="rounded-lg border border-slate-100 p-4">
        <div class="flex items-center justify-between gap-3">
          <div>
            <p class="font-semibold text-primary text-sm"><?php echo htmlspecialchars($entry['full_name'] ?? ('Student #' . ($entry['student_id'] ?? ''))); ?></p>
            <p class="text-xs text-slate-500"><?php echo htmlspecialchars($entry['entry_type'] ?? 'entry'); ?> · <?php echo htmlspecialchars($entry['admission_number'] ?? ''); ?></p>
          </div>
          <span class="font-bold text-sm <?php echo ((float) ($entry['amount'] ?? 0)) >= 0 ? 'text-emerald-700' : 'text-rose-700'; ?>">
            NGN <?php echo number_format((float) ($entry['amount'] ?? 0), 2); ?>
          </span>
        </div>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <div class="col-span-12 bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
    <div class="flex items-center justify-between mb-5">
      <div>
        <h3 class="text-lg font-bold text-primary">Enforcement Audit</h3>
        <p class="text-xs text-slate-500">Special-exam penalties and restorations remain soft-deactivation only.</p>
      </div>
    </div>
    <div class="overflow-x-auto">
      <table class="sams-table">
        <thead>
          <tr>
            <th>Action</th>
            <th>Reason</th>
            <th>Status</th>
            <th>Effective</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($recentEnforcement)): ?>
          <tr><td colspan="4" class="text-center text-slate-500">No enforcement actions recorded yet.</td></tr>
          <?php else: ?>
          <?php foreach ($recentEnforcement as $action): ?>
          <tr>
            <td class="font-semibold"><?php echo htmlspecialchars($action['action_type'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars($action['reason'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars($action['action_status'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars(format_datetime($action['effective_at'] ?? null)); ?></td>
          </tr>
          <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php
$page_content = ob_get_clean();
require BASE_PATH . '/resources/ui-core/layouts/master-dashboard.php';
