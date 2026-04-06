<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_role('nurse', '../login.php');

$db = db();
$db->query("CREATE TABLE IF NOT EXISTS nurse_health_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NULL,
    created_by INT NOT NULL,
    student_name VARCHAR(255) NOT NULL,
    condition_name VARCHAR(255) NOT NULL,
    notes TEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL
)");
$db->query("CREATE TABLE IF NOT EXISTS nurse_first_aid_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NULL,
    created_by INT NOT NULL,
    student_name VARCHAR(255) NOT NULL,
    incident TEXT NOT NULL,
    action_taken TEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL
)");
$db->query("CREATE TABLE IF NOT EXISTS nurse_medications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NULL,
    created_by INT NOT NULL,
    student_name VARCHAR(255) NOT NULL,
    medication_name VARCHAR(255) NOT NULL,
    dosage VARCHAR(100) NULL,
    schedule_time VARCHAR(100) NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL
)");

$uid = (int)($_SESSION['user_id'] ?? 0);
$tenantId = (int)($_SESSION['tenant_id'] ?? 0);
$scopeSql = $tenantId > 0 ? ' AND tenant_id = ?' : '';
$scopeParams = $tenantId > 0 ? [$tenantId] : [];

$healthCount = $db->fetchOne("SELECT COUNT(*) c FROM nurse_health_records WHERE created_by = ?{$scopeSql}", array_merge([$uid], $scopeParams))['c'] ?? 0;
$aidCount = $db->fetchOne("SELECT COUNT(*) c FROM nurse_first_aid_logs WHERE created_by = ?{$scopeSql}", array_merge([$uid], $scopeParams))['c'] ?? 0;
$medCount = $db->fetchOne("SELECT COUNT(*) c FROM nurse_medications WHERE created_by = ?{$scopeSql}", array_merge([$uid], $scopeParams))['c'] ?? 0;

$page_title = 'Nurse Dashboard';
$page_icon = 'medical_services';
$page_subtitle = 'School health operations center';
ob_start();
?>
<div class="grid grid-cols-12 gap-6">
  <div class="col-span-12 md:col-span-4 sams-stat-card">
    <p class="text-sm text-gray-500">Health Records</p>
    <p class="text-3xl font-bold"><?php echo (int)$healthCount; ?></p>
  </div>
  <div class="col-span-12 md:col-span-4 sams-stat-card">
    <p class="text-sm text-gray-500">First Aid Logs</p>
    <p class="text-3xl font-bold"><?php echo (int)$aidCount; ?></p>
  </div>
  <div class="col-span-12 md:col-span-4 sams-stat-card">
    <p class="text-sm text-gray-500">Medication Plans</p>
    <p class="text-3xl font-bold"><?php echo (int)$medCount; ?></p>
  </div>

  <a class="col-span-12 md:col-span-4 bg-white border border-gray-100 rounded-xl p-6 hover:border-indigo-200" href="health-records.php">
    <h3 class="font-semibold mb-1">Health Records</h3>
    <p class="text-sm text-gray-600">Maintain student health records</p>
  </a>
  <a class="col-span-12 md:col-span-4 bg-white border border-gray-100 rounded-xl p-6 hover:border-indigo-200" href="first-aid.php">
    <h3 class="font-semibold mb-1">First Aid</h3>
    <p class="text-sm text-gray-600">Record incidents and interventions</p>
  </a>
  <a class="col-span-12 md:col-span-4 bg-white border border-gray-100 rounded-xl p-6 hover:border-indigo-200" href="medications.php">
    <h3 class="font-semibold mb-1">Medications</h3>
    <p class="text-sm text-gray-600">Manage medication schedules</p>
  </a>
</div>
<?php
$page_content = ob_get_clean();
require BASE_PATH . '/resources/ui-core/layouts/master-dashboard.php';
