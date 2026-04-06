<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_role('staff', '../login.php');

$db = db();
$db->query("CREATE TABLE IF NOT EXISTS staff_tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NULL,
    created_by INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'open',
    priority VARCHAR(32) NOT NULL DEFAULT 'normal',
    due_date DATE NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL
)");
$db->query("CREATE TABLE IF NOT EXISTS staff_support_cases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NULL,
    created_by INT NOT NULL,
    student_name VARCHAR(255) NOT NULL,
    issue_type VARCHAR(100) NOT NULL,
    notes TEXT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'open',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL
)");

$uid = (int)($_SESSION['user_id'] ?? 0);
$tenantId = (int)($_SESSION['tenant_id'] ?? 0);
$scopeSql = $tenantId > 0 ? ' AND tenant_id = ?' : '';
$scopeParams = $tenantId > 0 ? [$tenantId] : [];

$taskCounts = $db->fetchOne("SELECT
    COUNT(*) total,
    SUM(CASE WHEN status='open' THEN 1 ELSE 0 END) open_count,
    SUM(CASE WHEN status='in_progress' THEN 1 ELSE 0 END) in_progress_count,
    SUM(CASE WHEN status='done' THEN 1 ELSE 0 END) done_count
    FROM staff_tasks WHERE created_by = ?{$scopeSql}", array_merge([$uid], $scopeParams)) ?: [];
$caseCounts = $db->fetchOne("SELECT
    COUNT(*) total,
    SUM(CASE WHEN status='open' THEN 1 ELSE 0 END) open_count,
    SUM(CASE WHEN status='resolved' THEN 1 ELSE 0 END) resolved_count
    FROM staff_support_cases WHERE created_by = ?{$scopeSql}", array_merge([$uid], $scopeParams)) ?: [];

$page_title = 'Staff Dashboard';
$page_icon = 'dashboard';
$page_subtitle = 'Operational support center';
ob_start();
?>
<div class="grid grid-cols-12 gap-6">
  <div class="col-span-12 md:col-span-3 sams-stat-card">
    <p class="text-sm text-gray-500">Tasks</p>
    <p class="text-3xl font-bold"><?php echo (int)($taskCounts['total'] ?? 0); ?></p>
  </div>
  <div class="col-span-12 md:col-span-3 sams-stat-card">
    <p class="text-sm text-gray-500">Open Tasks</p>
    <p class="text-3xl font-bold text-amber-600"><?php echo (int)($taskCounts['open_count'] ?? 0); ?></p>
  </div>
  <div class="col-span-12 md:col-span-3 sams-stat-card">
    <p class="text-sm text-gray-500">Support Cases</p>
    <p class="text-3xl font-bold"><?php echo (int)($caseCounts['total'] ?? 0); ?></p>
  </div>
  <div class="col-span-12 md:col-span-3 sams-stat-card">
    <p class="text-sm text-gray-500">Resolved Cases</p>
    <p class="text-3xl font-bold text-emerald-600"><?php echo (int)($caseCounts['resolved_count'] ?? 0); ?></p>
  </div>

  <a class="col-span-12 md:col-span-4 bg-white border border-gray-100 rounded-xl p-6 hover:border-indigo-200" href="tasks.php">
    <h3 class="font-semibold mb-1">Task Board</h3>
    <p class="text-sm text-gray-600">Create, update and close operational tasks</p>
  </a>
  <a class="col-span-12 md:col-span-4 bg-white border border-gray-100 rounded-xl p-6 hover:border-indigo-200" href="student-support.php">
    <h3 class="font-semibold mb-1">Student Support</h3>
    <p class="text-sm text-gray-600">Track support and welfare cases</p>
  </a>
  <a class="col-span-12 md:col-span-4 bg-white border border-gray-100 rounded-xl p-6 hover:border-indigo-200" href="reports.php">
    <h3 class="font-semibold mb-1">Operational Reports</h3>
    <p class="text-sm text-gray-600">Monitor workload and outcomes</p>
  </a>
</div>
<?php
$page_content = ob_get_clean();
require BASE_PATH . '/resources/ui-core/layouts/master-dashboard.php';
