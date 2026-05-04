<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once PROJECT_ROOT . '/backend/includes/advanced-sams.php';
require_once PROJECT_ROOT . '/backend/includes/tenant-context.php';

require_admin('../login.php');

$tenant = active_tenant_context(current_tenant_id());
$requiredTables = [
    'school_invites',
    'school_onboarding_steps',
    'class_point_accounts',
    'class_point_ledger',
    'private_point_accounts',
    'private_point_ledger',
    'monthly_allowance_runs',
    'merit_rules',
    'merit_events',
    'special_exams',
    'special_exam_rules',
    'special_exam_participants',
    'special_exam_outcomes',
    'enforcement_actions'
];

$tableStatus = [];
foreach ($requiredTables as $tableName) {
    $tableStatus[$tableName] = AdvancedSAMS::tableExists($tableName);
}

$readyCount = count(array_filter($tableStatus));

$tenantStats = [
    'students' => table_exists('students') ? (int) (db()->fetchOne('SELECT COUNT(*) AS total FROM students WHERE COALESCE(tenant_id, school_id, 1) = ?', [current_tenant_id()])['total'] ?? 0) : 0,
    'classes' => table_exists('classes') ? (int) (db()->fetchOne('SELECT COUNT(*) AS total FROM classes WHERE COALESCE(tenant_id, school_id, 1) = ?', [current_tenant_id()])['total'] ?? 0) : 0,
    'staff' => table_exists('tenant_users') ? (int) (db()->fetchOne('SELECT COUNT(*) AS total FROM tenant_users WHERE tenant_id = ? AND is_active = 1', [current_tenant_id()])['total'] ?? 0) : 0
];

$page_title = 'Advanced SAMS Setup';
$page_icon = 'settings';
$page_subtitle = 'Tenant merge status, merit-economy tables, and onboarding readiness';
ob_start();
?>

<div class="grid grid-cols-12 gap-6">
  <div class="col-span-12 lg:col-span-4 bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
    <div class="flex items-start justify-between">
      <div>
        <p class="text-[10px] uppercase tracking-widest font-bold text-slate-400">Active School</p>
        <h2 class="text-2xl font-extrabold text-primary mt-2"><?php echo htmlspecialchars($tenant['name'] ?? 'Unknown School'); ?></h2>
      </div>
      <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-bold <?php echo $readyCount === count($requiredTables) ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'; ?>">
        <?php echo $readyCount; ?>/<?php echo count($requiredTables); ?> ready
      </span>
    </div>
    <dl class="mt-6 space-y-3 text-sm text-slate-600">
      <div class="flex justify-between"><dt>Slug</dt><dd class="font-semibold text-primary"><?php echo htmlspecialchars($tenant['slug'] ?? 'n/a'); ?></dd></div>
      <div class="flex justify-between"><dt>Status</dt><dd class="font-semibold text-primary"><?php echo htmlspecialchars($tenant['status'] ?? 'active'); ?></dd></div>
      <div class="flex justify-between"><dt>Plan</dt><dd class="font-semibold text-primary"><?php echo htmlspecialchars($tenant['plan'] ?? 'trial'); ?></dd></div>
      <div class="flex justify-between"><dt>Currency</dt><dd class="font-semibold text-primary"><?php echo htmlspecialchars($tenant['currency'] ?? 'NGN'); ?></dd></div>
    </dl>
  </div>

  <div class="col-span-12 lg:col-span-8 grid grid-cols-1 md:grid-cols-3 gap-6">
    <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
      <p class="text-[10px] uppercase tracking-widest font-bold text-slate-400">Students</p>
      <p class="text-4xl font-extrabold text-primary mt-4"><?php echo number_format($tenantStats['students']); ?></p>
      <p class="text-xs text-slate-500 mt-2">Current tenant-scoped learners</p>
    </div>
    <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
      <p class="text-[10px] uppercase tracking-widest font-bold text-slate-400">Classes</p>
      <p class="text-4xl font-extrabold text-primary mt-4"><?php echo number_format($tenantStats['classes']); ?></p>
      <p class="text-xs text-slate-500 mt-2">Arms available for class points</p>
    </div>
    <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
      <p class="text-[10px] uppercase tracking-widest font-bold text-slate-400">Staff Links</p>
      <p class="text-4xl font-extrabold text-primary mt-4"><?php echo number_format($tenantStats['staff']); ?></p>
      <p class="text-xs text-slate-500 mt-2">Invite-only tenant memberships</p>
    </div>
  </div>

  <div class="col-span-12 bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
    <div class="flex items-center justify-between mb-5">
      <div>
        <h3 class="text-lg font-bold text-primary">Merit-Economy Readiness</h3>
        <p class="text-xs text-slate-500">These tables power class points, private points, White Room tracking, and special exams.</p>
      </div>
      <div class="flex gap-2">
        <a href="<?php echo base_url('admin/merit-overview.php'); ?>" class="px-4 py-2 rounded-lg bg-primary text-white text-xs font-bold">Open Merit Board</a>
        <a href="<?php echo base_url('admin/merit-rules.php'); ?>" class="px-4 py-2 rounded-lg border border-slate-200 text-xs font-bold text-primary">Open Merit Rules</a>
        <a href="<?php echo base_url('accountant/index.php?page=wallets'); ?>" class="px-4 py-2 rounded-lg border border-slate-200 text-xs font-bold text-primary">Open Wallets</a>
      </div>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
      <?php foreach ($tableStatus as $tableName => $isReady): ?>
      <div class="rounded-lg border <?php echo $isReady ? 'border-emerald-200 bg-emerald-50' : 'border-amber-200 bg-amber-50'; ?> p-4">
        <div class="flex items-center justify-between gap-3">
          <span class="font-semibold text-sm text-primary"><?php echo htmlspecialchars($tableName); ?></span>
          <span class="text-[10px] font-bold uppercase tracking-widest <?php echo $isReady ? 'text-emerald-700' : 'text-amber-700'; ?>">
            <?php echo $isReady ? 'Ready' : 'Missing'; ?>
          </span>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<?php
$page_content = ob_get_clean();
require BASE_PATH . '/resources/ui-core/layouts/master-dashboard.php';
