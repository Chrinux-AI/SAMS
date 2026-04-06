<?php

/**
 * Activity Log (Super Admin)
 */
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';

$_user_role = $_SESSION['role'] ?? $_SESSION['user_role'] ?? '';
if (!in_array($_user_role, ['super_admin', 'superadmin'], true)) {
  header('Location: ../login.php');
  exit;
}

$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-7 days'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$user_id = $_GET['user_id'] ?? '';
$action = $_GET['action_type'] ?? '';
$search = trim((string)($_GET['search'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 100;

$where = ['DATE(al.created_at) BETWEEN ? AND ?'];
$params = [$date_from, $date_to];
if ($user_id !== '') {
  $where[] = 'al.user_id = ?';
  $params[] = $user_id;
}
if ($action !== '') {
  $where[] = 'al.action_type = ?';
  $params[] = $action;
}
if ($search !== '') {
  $where[] = '(al.description LIKE ? OR al.table_name LIKE ? OR al.ip_address LIKE ? OR CONCAT(COALESCE(u.first_name,\'\'),\' \' ,COALESCE(u.last_name,\'\')) LIKE ? OR u.email LIKE ?)';
  $params[] = "%{$search}%";
  $params[] = "%{$search}%";
  $params[] = "%{$search}%";
  $params[] = "%{$search}%";
  $params[] = "%{$search}%";
}
$whereSql = implode(' AND ', $where);

$total_rows = db()->fetchOne(
  "SELECT COUNT(*) AS c
     FROM activity_logs al
     LEFT JOIN users u ON u.id = al.user_id
     WHERE {$whereSql}",
  $params
)['c'] ?? 0;

$total_pages = max(1, (int)ceil(((int)$total_rows) / $per_page));
if ($page > $total_pages) $page = $total_pages;
$offset = ($page - 1) * $per_page;

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
  $export_logs = db()->fetchAll(
    "SELECT al.created_at, CONCAT(COALESCE(u.first_name,''),' ',COALESCE(u.last_name,'')) AS user_name, u.email,
            al.action_type, al.description, al.table_name, al.record_id, al.ip_address
     FROM activity_logs al
     LEFT JOIN users u ON u.id = al.user_id
     WHERE {$whereSql}
     ORDER BY al.created_at DESC
     LIMIT 5000",
    $params
  ) ?: [];

  header('Content-Type: text/csv');
  header('Content-Disposition: attachment; filename="activity_logs_' . date('Y-m-d') . '.csv"');
  $out = fopen('php://output', 'w');
  fputcsv($out, ['Timestamp', 'User', 'Email', 'Action', 'Description', 'Table', 'Record ID', 'IP Address']);
  foreach ($export_logs as $row) {
    fputcsv($out, [
      $row['created_at'] ?? '',
      trim((string)($row['user_name'] ?? '')),
      $row['email'] ?? '',
      $row['action_type'] ?? '',
      $row['description'] ?? '',
      $row['table_name'] ?? '',
      $row['record_id'] ?? '',
      $row['ip_address'] ?? '',
    ]);
  }
  fclose($out);
  exit;
}

$logs = db()->fetchAll(
  "SELECT al.*, u.first_name, u.last_name, u.role, u.email,
            CONCAT(u.first_name,' ',u.last_name) AS user_name
     FROM activity_logs al
     LEFT JOIN users u ON u.id = al.user_id
     WHERE {$whereSql}
     ORDER BY al.created_at DESC
     LIMIT {$per_page} OFFSET {$offset}",
  $params
) ?: [];

$users = db()->fetchAll("SELECT id, first_name, last_name, role FROM users WHERE status='active' ORDER BY first_name,last_name") ?: [];
$actions = db()->fetchAll("SELECT DISTINCT action_type FROM activity_logs ORDER BY action_type") ?: [];
$stats = db()->fetchOne(
  "SELECT COUNT(*) total, COUNT(DISTINCT user_id) unique_users, COUNT(DISTINCT DATE(created_at)) active_days
     FROM activity_logs WHERE DATE(created_at) BETWEEN ? AND ?",
  [$date_from, $date_to]
) ?: ['total' => 0, 'unique_users' => 0, 'active_days' => 0];

$page_title = 'Activity Log';
$page_icon = 'history';
$page_subtitle = 'Audit events across the platform';
ob_start();
?>
<div class="grid grid-cols-12 gap-6">
  <div class="col-span-12 md:col-span-4 sams-stat-card">
    <p class="text-sm text-gray-500">Total Activities</p>
    <p class="text-3xl font-bold"><?php echo number_format((int)$stats['total']); ?></p>
  </div>
  <div class="col-span-12 md:col-span-4 sams-stat-card">
    <p class="text-sm text-gray-500">Unique Users</p>
    <p class="text-3xl font-bold"><?php echo number_format((int)$stats['unique_users']); ?></p>
  </div>
  <div class="col-span-12 md:col-span-4 sams-stat-card">
    <p class="text-sm text-gray-500">Active Days</p>
    <p class="text-3xl font-bold"><?php echo number_format((int)$stats['active_days']); ?></p>
  </div>

  <div class="col-span-12 bg-white border border-gray-100 rounded-xl p-5">
    <form class="grid grid-cols-1 md:grid-cols-6 gap-3">
      <select name="user_id" class="border rounded-lg px-3 py-2">
        <option value="">All Users</option>
        <?php foreach ($users as $u): ?>
          <option value="<?php echo $u['id']; ?>" <?php echo ((string)$user_id === (string)$u['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars(trim($u['first_name'] . ' ' . $u['last_name'] . ' (' . $u['role'] . ')')); ?></option>
        <?php endforeach; ?>
      </select>
      <select name="action_type" class="border rounded-lg px-3 py-2">
        <option value="">All Actions</option>
        <?php foreach ($actions as $a): ?>
          <option value="<?php echo htmlspecialchars($a['action_type']); ?>" <?php echo $action === $a['action_type'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($a['action_type']); ?></option>
        <?php endforeach; ?>
      </select>
      <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>" class="border rounded-lg px-3 py-2">
      <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>" class="border rounded-lg px-3 py-2">
      <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search description/ip/user" class="border rounded-lg px-3 py-2">
      <button class="px-4 py-2 rounded-lg bg-indigo-600 text-white">Apply</button>
      <a href="?user_id=<?php echo urlencode((string)$user_id); ?>&action_type=<?php echo urlencode((string)$action); ?>&date_from=<?php echo urlencode((string)$date_from); ?>&date_to=<?php echo urlencode((string)$date_to); ?>&search=<?php echo urlencode((string)$search); ?>&export=csv" class="px-4 py-2 rounded-lg border border-gray-200 text-center text-gray-700">Export CSV</a>
    </form>
  </div>

  <div class="col-span-12 bg-white border border-gray-100 rounded-xl overflow-hidden">
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="bg-gray-50">
            <th class="text-left px-4 py-3">Time</th>
            <th class="text-left px-4 py-3">User</th>
            <th class="text-left px-4 py-3">Action</th>
            <th class="text-left px-4 py-3">Description</th>
            <th class="text-left px-4 py-3">Table</th>
            <th class="text-left px-4 py-3">Record</th>
            <th class="text-left px-4 py-3">IP</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($logs)): ?>
            <tr>
              <td colspan="7" class="px-4 py-6 text-center text-gray-500">No activity records found.</td>
            </tr>
            <?php else: foreach ($logs as $l): ?>
              <tr class="border-t border-gray-100">
                <td class="px-4 py-3 whitespace-nowrap"><?php echo htmlspecialchars($l['created_at'] ?? ''); ?></td>
                <td class="px-4 py-3"><?php echo htmlspecialchars(($l['user_name'] ?? '') ?: ($l['email'] ?? 'System')); ?></td>
                <td class="px-4 py-3"><?php echo htmlspecialchars($l['action_type'] ?? ''); ?></td>
                <td class="px-4 py-3"><?php echo htmlspecialchars($l['description'] ?? '-'); ?></td>
                <td class="px-4 py-3"><?php echo htmlspecialchars($l['table_name'] ?? '-'); ?></td>
                <td class="px-4 py-3"><?php echo htmlspecialchars((string)($l['record_id'] ?? '-')); ?></td>
                <td class="px-4 py-3"><?php echo htmlspecialchars($l['ip_address'] ?? '-'); ?></td>
              </tr>
          <?php endforeach;
          endif; ?>
        </tbody>
      </table>
    </div>
    <div class="px-4 py-3 border-t border-gray-100 flex items-center justify-between text-sm">
      <span class="text-gray-500">Page <?php echo $page; ?> of <?php echo $total_pages; ?> • <?php echo (int)$total_rows; ?> result(s)</span>
      <div class="flex gap-2">
        <?php $prev = max(1, $page - 1);
        $next = min($total_pages, $page + 1); ?>
        <a class="px-3 py-1.5 border rounded <?php echo $page <= 1 ? 'text-gray-300 border-gray-100 pointer-events-none' : 'text-gray-700 border-gray-200'; ?>" href="?user_id=<?php echo urlencode((string)$user_id); ?>&action_type=<?php echo urlencode((string)$action); ?>&date_from=<?php echo urlencode((string)$date_from); ?>&date_to=<?php echo urlencode((string)$date_to); ?>&search=<?php echo urlencode((string)$search); ?>&page=<?php echo $prev; ?>">Prev</a>
        <a class="px-3 py-1.5 border rounded <?php echo $page >= $total_pages ? 'text-gray-300 border-gray-100 pointer-events-none' : 'text-gray-700 border-gray-200'; ?>" href="?user_id=<?php echo urlencode((string)$user_id); ?>&action_type=<?php echo urlencode((string)$action); ?>&date_from=<?php echo urlencode((string)$date_from); ?>&date_to=<?php echo urlencode((string)$date_to); ?>&search=<?php echo urlencode((string)$search); ?>&page=<?php echo $next; ?>">Next</a>
      </div>
    </div>
  </div>
</div>
<?php
$page_content = ob_get_clean();
require BASE_PATH . '/resources/ui-core/layouts/master-dashboard.php';
