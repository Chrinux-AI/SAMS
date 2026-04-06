<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_role('staff', '../login.php');

$db = db();
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
  $action = $_POST['action'] ?? '';
  if ($action === 'create') {
    $db->insert('staff_support_cases', [
      'tenant_id' => $tenantId > 0 ? $tenantId : null,
      'created_by' => $uid,
      'student_name' => trim((string)($_POST['student_name'] ?? '')),
      'issue_type' => trim((string)($_POST['issue_type'] ?? 'General')),
      'notes' => trim((string)($_POST['notes'] ?? '')),
      'status' => 'open',
      'created_at' => date('Y-m-d H:i:s'),
      'updated_at' => date('Y-m-d H:i:s'),
    ]);
  } elseif ($action === 'update') {
    $id = (int)($_POST['case_id'] ?? 0);
    if ($id > 0) {
      $db->update('staff_support_cases', [
        'status' => trim((string)($_POST['status'] ?? 'open')),
        'updated_at' => date('Y-m-d H:i:s'),
      ], 'id = ? AND created_by = ?', [$id, $uid]);
    }
  }
  header('Location: student-support.php');
  exit;
}

$scopeSql = $tenantId > 0 ? ' AND tenant_id = ?' : '';
$scopeParams = $tenantId > 0 ? [$tenantId] : [];
$cases = $db->fetchAll("SELECT * FROM staff_support_cases WHERE created_by = ?{$scopeSql} ORDER BY created_at DESC", array_merge([$uid], $scopeParams)) ?: [];

$page_title = 'Student Support Cases';
$page_icon = 'support_agent';
$page_subtitle = 'Track student support and welfare issues';
ob_start();
?>
<div class="grid grid-cols-12 gap-6">
  <div class="col-span-12 lg:col-span-4 bg-white border border-gray-100 rounded-xl p-5">
    <h3 class="font-semibold mb-3">New Case</h3>
    <form method="POST" class="space-y-3">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
      <input type="hidden" name="action" value="create">
      <input name="student_name" required class="w-full border rounded-lg px-3 py-2" placeholder="Student name">
      <input name="issue_type" required class="w-full border rounded-lg px-3 py-2" placeholder="Issue type (e.g. Welfare)">
      <textarea name="notes" class="w-full border rounded-lg px-3 py-2" placeholder="Notes"></textarea>
      <button class="w-full bg-indigo-600 text-white rounded-lg px-3 py-2">Create Case</button>
    </form>
  </div>
  <div class="col-span-12 lg:col-span-8 bg-white border border-gray-100 rounded-xl overflow-hidden">
    <table class="w-full text-sm">
      <thead>
        <tr class="bg-gray-50">
          <th class="px-4 py-3 text-left">Student</th>
          <th class="px-4 py-3 text-left">Issue</th>
          <th class="px-4 py-3 text-left">Status</th>
          <th class="px-4 py-3 text-left">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($cases)): ?><tr>
            <td colspan="4" class="px-4 py-6 text-center text-gray-500">No support cases yet.</td>
          </tr>
          <?php else: foreach ($cases as $c): ?>
            <tr class="border-t border-gray-100">
              <td class="px-4 py-3">
                <div class="font-medium"><?php echo htmlspecialchars($c['student_name']); ?></div>
                <div class="text-xs text-gray-500"><?php echo htmlspecialchars($c['notes'] ?? ''); ?></div>
              </td>
              <td class="px-4 py-3"><?php echo htmlspecialchars($c['issue_type']); ?></td>
              <td class="px-4 py-3"><?php echo htmlspecialchars($c['status']); ?></td>
              <td class="px-4 py-3">
                <form method="POST" class="inline-flex gap-2">
                  <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>"><input type="hidden" name="action" value="update"><input type="hidden" name="case_id" value="<?php echo (int)$c['id']; ?>">
                  <select name="status" class="border rounded px-2 py-1 text-xs">
                    <option value="open" <?php echo $c['status'] === 'open' ? 'selected' : ''; ?>>Open</option>
                    <option value="in_progress" <?php echo $c['status'] === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                    <option value="resolved" <?php echo $c['status'] === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                  </select>
                  <button class="border rounded px-2 py-1 text-xs text-indigo-700">Save</button>
                </form>
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
