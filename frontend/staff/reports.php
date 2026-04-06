<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_role('staff', '../login.php');

$db = db();
$uid = (int)($_SESSION['user_id'] ?? 0);
$tenantId = (int)($_SESSION['tenant_id'] ?? 0);
$scopeSql = $tenantId > 0 ? ' AND tenant_id = ?' : '';
$scopeParams = $tenantId > 0 ? [$tenantId] : [];

$taskSummary = $db->fetchOne("SELECT status, COUNT(*) c FROM staff_tasks WHERE created_by = ?{$scopeSql} GROUP BY status", array_merge([$uid], $scopeParams)) ?: [];
$caseSummary = $db->fetchAll("SELECT status, COUNT(*) c FROM staff_support_cases WHERE created_by = ?{$scopeSql} GROUP BY status", array_merge([$uid], $scopeParams)) ?: [];
$tasksByStatus = $db->fetchAll("SELECT status, COUNT(*) c FROM staff_tasks WHERE created_by = ?{$scopeSql} GROUP BY status", array_merge([$uid], $scopeParams)) ?: [];

$page_title = 'Staff Reports';
$page_icon = 'bar_chart';
$page_subtitle = 'Operational report summary';
ob_start();
?>
<div class="grid grid-cols-12 gap-6">
  <div class="col-span-12 lg:col-span-6 bg-white border border-gray-100 rounded-xl p-5">
    <h3 class="font-semibold mb-3">Tasks by Status</h3>
    <div class="space-y-2 text-sm">
      <?php if (empty($tasksByStatus)): ?>
        <p class="text-gray-500">No task data yet.</p>
        <?php else: foreach ($tasksByStatus as $row): ?>
          <div class="flex items-center justify-between"><span class="text-gray-600"><?php echo htmlspecialchars($row['status']); ?></span><span class="font-semibold"><?php echo (int)$row['c']; ?></span></div>
      <?php endforeach;
      endif; ?>
    </div>
  </div>
  <div class="col-span-12 lg:col-span-6 bg-white border border-gray-100 rounded-xl p-5">
    <h3 class="font-semibold mb-3">Support Cases by Status</h3>
    <div class="space-y-2 text-sm">
      <?php if (empty($caseSummary)): ?>
        <p class="text-gray-500">No case data yet.</p>
        <?php else: foreach ($caseSummary as $row): ?>
          <div class="flex items-center justify-between"><span class="text-gray-600"><?php echo htmlspecialchars($row['status']); ?></span><span class="font-semibold"><?php echo (int)$row['c']; ?></span></div>
      <?php endforeach;
      endif; ?>
    </div>
  </div>
</div>
<?php
$page_content = ob_get_clean();
require BASE_PATH . '/resources/ui-core/layouts/master-dashboard.php';
