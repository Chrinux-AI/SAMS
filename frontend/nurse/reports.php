<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_role('nurse', '../login.php');

$db = db();
$uid = (int)($_SESSION['user_id'] ?? 0);
$tenantId = (int)($_SESSION['tenant_id'] ?? 0);
$scopeSql = $tenantId > 0 ? ' AND tenant_id = ?' : '';
$scopeParams = $tenantId > 0 ? [$tenantId] : [];

$health = $db->fetchOne("SELECT COUNT(*) c FROM nurse_health_records WHERE created_by = ?{$scopeSql}", array_merge([$uid], $scopeParams))['c'] ?? 0;
$aid = $db->fetchOne("SELECT COUNT(*) c FROM nurse_first_aid_logs WHERE created_by = ?{$scopeSql}", array_merge([$uid], $scopeParams))['c'] ?? 0;
$med = $db->fetchOne("SELECT COUNT(*) c FROM nurse_medications WHERE created_by = ?{$scopeSql}", array_merge([$uid], $scopeParams))['c'] ?? 0;

$page_title = 'Nurse Reports';
$page_icon = 'summarize';
$page_subtitle = 'Health operations summary';
ob_start();
?>
<div class="grid grid-cols-12 gap-6">
  <div class="col-span-12 md:col-span-4 sams-stat-card">
    <p class="text-sm text-gray-500">Health Records</p>
    <p class="text-3xl font-bold"><?php echo (int)$health; ?></p>
  </div>
  <div class="col-span-12 md:col-span-4 sams-stat-card">
    <p class="text-sm text-gray-500">First Aid Incidents</p>
    <p class="text-3xl font-bold"><?php echo (int)$aid; ?></p>
  </div>
  <div class="col-span-12 md:col-span-4 sams-stat-card">
    <p class="text-sm text-gray-500">Medication Plans</p>
    <p class="text-3xl font-bold"><?php echo (int)$med; ?></p>
  </div>
</div>
<?php
$page_content = ob_get_clean();
require BASE_PATH . '/resources/ui-core/layouts/master-dashboard.php';
