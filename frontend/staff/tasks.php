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

$uid = (int)($_SESSION['user_id'] ?? 0);
$tenantId = (int)($_SESSION['tenant_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
  $action = $_POST['action'] ?? '';
  if ($action === 'create') {
    $db->insert('staff_tasks', [
      'tenant_id' => $tenantId > 0 ? $tenantId : null,
      'created_by' => $uid,
      'title' => trim((string)($_POST['title'] ?? '')),
      'description' => trim((string)($_POST['description'] ?? '')),
      'status' => 'open',
      'priority' => trim((string)($_POST['priority'] ?? 'normal')),
      'due_date' => ($_POST['due_date'] ?? '') ?: null,
      'created_at' => date('Y-m-d H:i:s'),
      'updated_at' => date('Y-m-d H:i:s'),
    ]);
  } elseif ($action === 'update') {
    $taskId = (int)($_POST['task_id'] ?? 0);
    if ($taskId > 0) {
      $db->update('staff_tasks', [
        'status' => trim((string)($_POST['status'] ?? 'open')),
        'priority' => trim((string)($_POST['priority'] ?? 'normal')),
        'updated_at' => date('Y-m-d H:i:s'),
      ], 'id = ? AND created_by = ?', [$taskId, $uid]);
    }
  } elseif ($action === 'delete') {
    $taskId = (int)($_POST['task_id'] ?? 0);
    if ($taskId > 0) {
      $db->delete('staff_tasks', 'id = ? AND created_by = ?', [$taskId, $uid]);
    }
  }
  header('Location: tasks.php');
  exit;
}

$scopeSql = $tenantId > 0 ? ' AND tenant_id = ?' : '';
$scopeParams = $tenantId > 0 ? [$tenantId] : [];
$tasks = $db->fetchAll("SELECT * FROM staff_tasks WHERE created_by = ?{$scopeSql} ORDER BY created_at DESC", array_merge([$uid], $scopeParams)) ?: [];

$page_title = 'Staff Tasks';
$page_icon = 'task';
$page_subtitle = 'Create and manage operational tasks';
ob_start();
?>
<div class="grid grid-cols-12 gap-6">
  <div class="col-span-12 lg:col-span-4 bg-white border border-gray-100 rounded-xl p-5">
    <h3 class="font-semibold mb-3">New Task</h3>
    <form method="POST" class="space-y-3">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
      <input type="hidden" name="action" value="create">
      <input name="title" required class="w-full border rounded-lg px-3 py-2" placeholder="Task title">
      <textarea name="description" class="w-full border rounded-lg px-3 py-2" placeholder="Description"></textarea>
      <select name="priority" class="w-full border rounded-lg px-3 py-2">
        <option value="low">Low</option>
        <option value="normal" selected>Normal</option>
        <option value="high">High</option>
      </select>
      <input type="date" name="due_date" class="w-full border rounded-lg px-3 py-2">
      <button class="w-full bg-indigo-600 text-white rounded-lg px-3 py-2">Create Task</button>
    </form>
  </div>

  <div class="col-span-12 lg:col-span-8 bg-white border border-gray-100 rounded-xl overflow-hidden">
    <table class="w-full text-sm">
      <thead>
        <tr class="bg-gray-50">
          <th class="px-4 py-3 text-left">Task</th>
          <th class="px-4 py-3 text-left">Status</th>
          <th class="px-4 py-3 text-left">Priority</th>
          <th class="px-4 py-3 text-left">Due</th>
          <th class="px-4 py-3 text-left">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($tasks)): ?>
          <tr>
            <td colspan="5" class="px-4 py-6 text-center text-gray-500">No tasks yet.</td>
          </tr>
          <?php else: foreach ($tasks as $t): ?>
            <tr class="border-t border-gray-100">
              <td class="px-4 py-3">
                <div class="font-medium"><?php echo htmlspecialchars($t['title']); ?></div>
                <div class="text-xs text-gray-500"><?php echo htmlspecialchars($t['description'] ?? ''); ?></div>
              </td>
              <td class="px-4 py-3"><?php echo htmlspecialchars($t['status']); ?></td>
              <td class="px-4 py-3"><?php echo htmlspecialchars($t['priority']); ?></td>
              <td class="px-4 py-3"><?php echo htmlspecialchars((string)($t['due_date'] ?? '-')); ?></td>
              <td class="px-4 py-3">
                <div class="flex flex-wrap gap-2">
                  <form method="POST" class="inline-flex gap-2">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>"><input type="hidden" name="action" value="update"><input type="hidden" name="task_id" value="<?php echo (int)$t['id']; ?>">
                    <select name="status" class="border rounded px-2 py-1 text-xs">
                      <option value="open" <?php echo $t['status'] === 'open' ? 'selected' : ''; ?>>Open</option>
                      <option value="in_progress" <?php echo $t['status'] === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                      <option value="done" <?php echo $t['status'] === 'done' ? 'selected' : ''; ?>>Done</option>
                    </select>
                    <select name="priority" class="border rounded px-2 py-1 text-xs">
                      <option value="low" <?php echo $t['priority'] === 'low' ? 'selected' : ''; ?>>Low</option>
                      <option value="normal" <?php echo $t['priority'] === 'normal' ? 'selected' : ''; ?>>Normal</option>
                      <option value="high" <?php echo $t['priority'] === 'high' ? 'selected' : ''; ?>>High</option>
                    </select>
                    <button class="border rounded px-2 py-1 text-xs text-indigo-700">Save</button>
                  </form>
                  <form method="POST" onsubmit="return confirm('Delete task?');"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="task_id" value="<?php echo (int)$t['id']; ?>"><button class="border rounded px-2 py-1 text-xs text-rose-700">Delete</button></form>
                </div>
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
