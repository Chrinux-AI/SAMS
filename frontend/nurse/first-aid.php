<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_role('nurse', '../login.php');

$db = db();
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

$uid = (int)($_SESSION['user_id'] ?? 0);
$tenantId = (int)($_SESSION['tenant_id'] ?? 0);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
  $db->insert('nurse_first_aid_logs', [
    'tenant_id' => $tenantId > 0 ? $tenantId : null,
    'created_by' => $uid,
    'student_name' => trim((string)($_POST['student_name'] ?? '')),
    'incident' => trim((string)($_POST['incident'] ?? '')),
    'action_taken' => trim((string)($_POST['action_taken'] ?? '')),
    'created_at' => date('Y-m-d H:i:s'),
    'updated_at' => date('Y-m-d H:i:s'),
  ]);
  header('Location: first-aid.php');
  exit;
}

$scopeSql = $tenantId > 0 ? ' AND tenant_id = ?' : '';
$scopeParams = $tenantId > 0 ? [$tenantId] : [];
$rows = $db->fetchAll("SELECT * FROM nurse_first_aid_logs WHERE created_by = ?{$scopeSql} ORDER BY created_at DESC", array_merge([$uid], $scopeParams)) ?: [];

$page_title = 'First Aid Log';
$page_icon = 'healing';
$page_subtitle = 'Track first-aid incidents';
ob_start();
?>
<div class="grid grid-cols-12 gap-6">
  <div class="col-span-12 lg:col-span-4 bg-white border border-gray-100 rounded-xl p-5">
    <h3 class="font-semibold mb-3">Record Incident</h3>
    <form method="POST" class="space-y-3">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
      <input name="student_name" required class="w-full border rounded-lg px-3 py-2" placeholder="Student name">
      <textarea name="incident" required class="w-full border rounded-lg px-3 py-2" placeholder="Incident"></textarea>
      <textarea name="action_taken" class="w-full border rounded-lg px-3 py-2" placeholder="Action taken"></textarea>
      <button class="w-full bg-indigo-600 text-white rounded-lg px-3 py-2">Log Incident</button>
    </form>
  </div>
  <div class="col-span-12 lg:col-span-8 bg-white border border-gray-100 rounded-xl overflow-hidden">
    <table class="w-full text-sm">
      <thead>
        <tr class="bg-gray-50">
          <th class="px-4 py-3 text-left">Student</th>
          <th class="px-4 py-3 text-left">Incident</th>
          <th class="px-4 py-3 text-left">Action</th>
          <th class="px-4 py-3 text-left">Time</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($rows)): ?><tr>
            <td colspan="4" class="px-4 py-6 text-center text-gray-500">No first-aid logs yet.</td>
          </tr><?php else: foreach ($rows as $r): ?>
            <tr class="border-t border-gray-100">
              <td class="px-4 py-3"><?php echo htmlspecialchars($r['student_name']); ?></td>
              <td class="px-4 py-3"><?php echo htmlspecialchars($r['incident']); ?></td>
              <td class="px-4 py-3"><?php echo htmlspecialchars($r['action_taken'] ?? ''); ?></td>
              <td class="px-4 py-3"><?php echo htmlspecialchars($r['created_at']); ?></td>
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
