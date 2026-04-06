<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_role('nurse', '../login.php');

$db = db();
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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
  $action = $_POST['action'] ?? '';
  if ($action === 'create') {
    $db->insert('nurse_medications', [
      'tenant_id' => $tenantId > 0 ? $tenantId : null,
      'created_by' => $uid,
      'student_name' => trim((string)($_POST['student_name'] ?? '')),
      'medication_name' => trim((string)($_POST['medication_name'] ?? '')),
      'dosage' => trim((string)($_POST['dosage'] ?? '')),
      'schedule_time' => trim((string)($_POST['schedule_time'] ?? '')),
      'status' => 'active',
      'created_at' => date('Y-m-d H:i:s'),
      'updated_at' => date('Y-m-d H:i:s'),
    ]);
  } elseif ($action === 'update_status') {
    $id = (int)($_POST['med_id'] ?? 0);
    if ($id > 0) {
      $db->update('nurse_medications', ['status' => trim((string)($_POST['status'] ?? 'active')), 'updated_at' => date('Y-m-d H:i:s')], 'id = ? AND created_by = ?', [$id, $uid]);
    }
  }
  header('Location: medications.php');
  exit;
}

$scopeSql = $tenantId > 0 ? ' AND tenant_id = ?' : '';
$scopeParams = $tenantId > 0 ? [$tenantId] : [];
$rows = $db->fetchAll("SELECT * FROM nurse_medications WHERE created_by = ?{$scopeSql} ORDER BY created_at DESC", array_merge([$uid], $scopeParams)) ?: [];

$page_title = 'Medications';
$page_icon = 'medication';
$page_subtitle = 'Manage medication schedules';
ob_start();
?>
<div class="grid grid-cols-12 gap-6">
  <div class="col-span-12 lg:col-span-4 bg-white border border-gray-100 rounded-xl p-5">
    <h3 class="font-semibold mb-3">Add Medication Plan</h3>
    <form method="POST" class="space-y-3">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>"><input type="hidden" name="action" value="create">
      <input name="student_name" required class="w-full border rounded-lg px-3 py-2" placeholder="Student name">
      <input name="medication_name" required class="w-full border rounded-lg px-3 py-2" placeholder="Medication">
      <input name="dosage" class="w-full border rounded-lg px-3 py-2" placeholder="Dosage">
      <input name="schedule_time" class="w-full border rounded-lg px-3 py-2" placeholder="Schedule (e.g. 8AM / 2PM)">
      <button class="w-full bg-indigo-600 text-white rounded-lg px-3 py-2">Save Plan</button>
    </form>
  </div>
  <div class="col-span-12 lg:col-span-8 bg-white border border-gray-100 rounded-xl overflow-hidden">
    <table class="w-full text-sm">
      <thead>
        <tr class="bg-gray-50">
          <th class="px-4 py-3 text-left">Student</th>
          <th class="px-4 py-3 text-left">Medication</th>
          <th class="px-4 py-3 text-left">Schedule</th>
          <th class="px-4 py-3 text-left">Status</th>
          <th class="px-4 py-3 text-left">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($rows)): ?><tr>
            <td colspan="5" class="px-4 py-6 text-center text-gray-500">No medication plans yet.</td>
          </tr><?php else: foreach ($rows as $r): ?>
            <tr class="border-t border-gray-100">
              <td class="px-4 py-3"><?php echo htmlspecialchars($r['student_name']); ?></td>
              <td class="px-4 py-3"><?php echo htmlspecialchars($r['medication_name']); ?> <span class="text-xs text-gray-500"><?php echo htmlspecialchars($r['dosage'] ?? ''); ?></span></td>
              <td class="px-4 py-3"><?php echo htmlspecialchars($r['schedule_time'] ?? ''); ?></td>
              <td class="px-4 py-3"><?php echo htmlspecialchars($r['status']); ?></td>
              <td class="px-4 py-3">
                <form method="POST" class="inline-flex gap-2"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>"><input type="hidden" name="action" value="update_status"><input type="hidden" name="med_id" value="<?php echo (int)$r['id']; ?>"><select name="status" class="border rounded px-2 py-1 text-xs">
                    <option value="active" <?php echo $r['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="paused" <?php echo $r['status'] === 'paused' ? 'selected' : ''; ?>>Paused</option>
                    <option value="completed" <?php echo $r['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                  </select><button class="border rounded px-2 py-1 text-xs text-indigo-700">Save</button></form>
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
