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

$uid = (int)($_SESSION['user_id'] ?? 0);
$tenantId = (int)($_SESSION['tenant_id'] ?? 0);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
  $action = $_POST['action'] ?? '';
  if ($action === 'create') {
    $db->insert('nurse_health_records', [
      'tenant_id' => $tenantId > 0 ? $tenantId : null,
      'created_by' => $uid,
      'student_name' => trim((string)($_POST['student_name'] ?? '')),
      'condition_name' => trim((string)($_POST['condition_name'] ?? '')),
      'notes' => trim((string)($_POST['notes'] ?? '')),
      'created_at' => date('Y-m-d H:i:s'),
      'updated_at' => date('Y-m-d H:i:s'),
    ]);
  } elseif ($action === 'delete') {
    $id = (int)($_POST['record_id'] ?? 0);
    if ($id > 0) {
      $db->delete('nurse_health_records', 'id = ? AND created_by = ?', [$id, $uid]);
    }
  }
  header('Location: health-records.php');
  exit;
}

$scopeSql = $tenantId > 0 ? ' AND tenant_id = ?' : '';
$scopeParams = $tenantId > 0 ? [$tenantId] : [];
$rows = $db->fetchAll("SELECT * FROM nurse_health_records WHERE created_by = ?{$scopeSql} ORDER BY created_at DESC", array_merge([$uid], $scopeParams)) ?: [];

$page_title = 'Health Records';
$page_icon = 'folder_shared';
$page_subtitle = 'Create and view student health records';
ob_start();
?>
<div class="grid grid-cols-12 gap-6">
  <div class="col-span-12 lg:col-span-4 bg-white border border-gray-100 rounded-xl p-5">
    <h3 class="font-semibold mb-3">New Record</h3>
    <form method="POST" class="space-y-3">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>"><input type="hidden" name="action" value="create">
      <input name="student_name" required class="w-full border rounded-lg px-3 py-2" placeholder="Student name">
      <input name="condition_name" required class="w-full border rounded-lg px-3 py-2" placeholder="Condition">
      <textarea name="notes" class="w-full border rounded-lg px-3 py-2" placeholder="Notes"></textarea>
      <button class="w-full bg-indigo-600 text-white rounded-lg px-3 py-2">Save Record</button>
    </form>
  </div>
  <div class="col-span-12 lg:col-span-8 bg-white border border-gray-100 rounded-xl overflow-hidden">
    <table class="w-full text-sm">
      <thead>
        <tr class="bg-gray-50">
          <th class="px-4 py-3 text-left">Student</th>
          <th class="px-4 py-3 text-left">Condition</th>
          <th class="px-4 py-3 text-left">Notes</th>
          <th class="px-4 py-3 text-left">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($rows)): ?><tr>
            <td colspan="4" class="px-4 py-6 text-center text-gray-500">No records yet.</td>
          </tr><?php else: foreach ($rows as $r): ?>
            <tr class="border-t border-gray-100">
              <td class="px-4 py-3"><?php echo htmlspecialchars($r['student_name']); ?></td>
              <td class="px-4 py-3"><?php echo htmlspecialchars($r['condition_name']); ?></td>
              <td class="px-4 py-3"><?php echo htmlspecialchars($r['notes'] ?? ''); ?></td>
              <td class="px-4 py-3">
                <form method="POST" onsubmit="return confirm('Delete record?');"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="record_id" value="<?php echo (int)$r['id']; ?>"><button class="border rounded px-2 py-1 text-xs text-rose-700">Delete</button></form>
              </td>
            </tr>
        <?php endforeach;
              endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php
$page_content = ob_get_clean();
require BASE_PATH . '/resources/ui-core/layouts/master-dashboard.php';
