<?php

/**
 * User Management (Super Admin)
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

$q = trim($_GET['q'] ?? '');
$role = trim($_GET['role'] ?? '');
$status = trim($_GET['status'] ?? '');
$tenant_id = (int)($_GET['tenant_id'] ?? 0);
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 50;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
  $action = $_POST['action'] ?? '';
  $target_user = (int)($_POST['user_id'] ?? 0);
  $currentUserId = (int)($_SESSION['user_id'] ?? 0);

  if ($action === 'bulk_set_role') {
    $bulkRole = trim((string)($_POST['bulk_role'] ?? ''));
    $selectedIds = array_values(array_filter(array_map('intval', $_POST['selected_user_ids'] ?? []), static fn($v) => $v > 0));
    if ($bulkRole !== '' && !empty($selectedIds)) {
      $in = implode(',', array_fill(0, count($selectedIds), '?'));
      db()->query("UPDATE users SET role = ? WHERE id IN ($in)", array_merge([$bulkRole], $selectedIds));
      log_activity($currentUserId, 'bulk_set_user_role', 'users', 0, 'Bulk updated role to ' . $bulkRole . ' for ' . count($selectedIds) . ' user(s)');
    }
  } elseif ($action === 'bulk_set_status') {
    $bulkStatus = trim((string)($_POST['bulk_status'] ?? ''));
    if (!in_array($bulkStatus, ['active', 'pending', 'suspended', 'inactive'], true)) {
      $bulkStatus = '';
    }
    $selectedIds = array_values(array_filter(array_map('intval', $_POST['selected_user_ids'] ?? []), static fn($v) => $v > 0));
    if ($bulkStatus !== '' && !empty($selectedIds)) {
      $in = implode(',', array_fill(0, count($selectedIds), '?'));
      db()->query("UPDATE users SET status = ? WHERE id IN ($in)", array_merge([$bulkStatus], $selectedIds));
      log_activity($currentUserId, 'bulk_set_user_status', 'users', 0, 'Bulk updated status to ' . $bulkStatus . ' for ' . count($selectedIds) . ' user(s)');
    }
  } elseif ($action === 'bulk_delete_users') {
    $selectedIds = array_values(array_filter(array_map('intval', $_POST['selected_user_ids'] ?? []), static fn($v) => $v > 0 && $v !== $currentUserId));
    if (!empty($selectedIds)) {
      $in = implode(',', array_fill(0, count($selectedIds), '?'));
      db()->query("DELETE FROM users WHERE id IN ($in)", $selectedIds);
      log_activity($currentUserId, 'bulk_delete_users', 'users', 0, 'Bulk deleted ' . count($selectedIds) . ' user(s)');
    }
  } elseif ($action === 'edit_user' && $target_user > 0) {
    $first = trim((string)($_POST['edit_first_name'] ?? ''));
    $last = trim((string)($_POST['edit_last_name'] ?? ''));
    $email = trim((string)($_POST['edit_email'] ?? ''));
    $newRole = trim((string)($_POST['edit_role'] ?? ''));
    $newStatus = trim((string)($_POST['edit_status'] ?? ''));
    if ($first !== '' && $last !== '' && $email !== '') {
      $payload = [
        'first_name' => $first,
        'last_name' => $last,
        'full_name' => $first . ' ' . $last,
        'email' => $email,
      ];
      if ($newRole !== '') $payload['role'] = $newRole;
      if (in_array($newStatus, ['active', 'pending', 'suspended', 'inactive'], true)) $payload['status'] = $newStatus;
      db()->update('users', $payload, 'id = ?', [$target_user]);
      log_activity($currentUserId, 'edit_user', 'users', $target_user, 'Edited user profile details');
    }
  }

  if ($target_user > 0) {
    if ($action === 'set_status') {
      $new_status = $_POST['new_status'] ?? 'pending';
      if (!in_array($new_status, ['active', 'pending', 'suspended', 'inactive'], true)) {
        $new_status = 'pending';
      }
      db()->update('users', ['status' => $new_status], 'id = ?', [$target_user]);
      log_activity($_SESSION['user_id'] ?? 0, 'set_user_status', 'users', $target_user, 'Set status to ' . $new_status);
    } elseif ($action === 'set_role') {
      $new_role = trim((string)($_POST['new_role'] ?? ''));
      if ($new_role !== '') {
        db()->update('users', ['role' => $new_role], 'id = ?', [$target_user]);
        log_activity($_SESSION['user_id'] ?? 0, 'set_user_role', 'users', $target_user, 'Changed role to ' . $new_role);
      }
    } elseif ($action === 'delete_user') {
      if ($target_user !== $currentUserId) {
        db()->delete('users', 'id = ?', [$target_user]);
        log_activity($currentUserId, 'delete_user', 'users', $target_user, 'Deleted user from platform user management');
      }
    }
  }

  $redirect = 'user-management.php?q=' . urlencode($q) . '&role=' . urlencode($role) . '&status=' . urlencode($status) . '&tenant_id=' . urlencode((string)$tenant_id) . '&page=' . urlencode((string)$page);
  header('Location: ' . $redirect);
  exit;
}

$where = ['1=1'];
$params = [];
if ($q !== '') {
  $where[] = '(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.assigned_id LIKE ?)';
  $params[] = "%{$q}%";
  $params[] = "%{$q}%";
  $params[] = "%{$q}%";
  $params[] = "%{$q}%";
}
if ($role !== '') {
  $where[] = 'u.role = ?';
  $params[] = $role;
}
if ($status !== '') {
  $where[] = 'u.status = ?';
  $params[] = $status;
}
if ($tenant_id > 0) {
  $where[] = 'u.tenant_id = ?';
  $params[] = $tenant_id;
}
$whereSql = implode(' AND ', $where);

$total_users_filtered = db()->fetchOne("SELECT COUNT(*) AS c FROM users u WHERE {$whereSql}", $params)['c'] ?? 0;
$total_pages = max(1, (int)ceil(((int)$total_users_filtered) / $per_page));
if ($page > $total_pages) $page = $total_pages;
$offset = ($page - 1) * $per_page;

$users = db()->fetchAll(
  "SELECT u.id, u.first_name, u.last_name, u.full_name, u.email, u.role, u.status, u.assigned_id, u.created_at,
            t.institution_name as tenant_name
     FROM users u
     LEFT JOIN tenants t ON t.id = u.tenant_id
     WHERE {$whereSql}
     ORDER BY u.created_at DESC
     LIMIT {$per_page} OFFSET {$offset}",
  $params
) ?: [];

$roles = db()->fetchAll("SELECT role, COUNT(*) c FROM users GROUP BY role ORDER BY c DESC") ?: [];
$role_options = array_values(array_map(static fn($r) => (string)$r['role'], $roles));
$stats = db()->fetchOne("SELECT COUNT(*) total, SUM(CASE WHEN status='active' THEN 1 ELSE 0 END) active FROM users") ?: ['total' => 0, 'active' => 0];
$tenants = table_exists('tenants') ? (db()->fetchAll("SELECT id, institution_name FROM tenants ORDER BY institution_name") ?: []) : [];

$page_title = 'User Management';
$page_icon = 'group';
$page_subtitle = 'Platform-wide user oversight and controls';
ob_start();
?>
<div class="grid grid-cols-12 gap-6">
  <div class="col-span-12 md:col-span-4 sams-stat-card">
    <p class="text-sm text-gray-500">Total Users</p>
    <p class="text-3xl font-bold"><?php echo number_format((int)$stats['total']); ?></p>
  </div>
  <div class="col-span-12 md:col-span-4 sams-stat-card">
    <p class="text-sm text-gray-500">Active Users</p>
    <p class="text-3xl font-bold text-emerald-600"><?php echo number_format((int)$stats['active']); ?></p>
  </div>
  <div class="col-span-12 md:col-span-4 sams-stat-card">
    <p class="text-sm text-gray-500">Role Types</p>
    <p class="text-3xl font-bold"><?php echo count($roles); ?></p>
  </div>

  <div class="col-span-12 bg-white border border-gray-100 rounded-xl p-5">
    <form class="grid grid-cols-1 md:grid-cols-6 gap-3">
      <input name="q" value="<?php echo htmlspecialchars($q); ?>" class="border rounded-lg px-3 py-2" placeholder="Search name/email/ID">
      <input name="role" value="<?php echo htmlspecialchars($role); ?>" class="border rounded-lg px-3 py-2" placeholder="Role (e.g. student)">
      <input name="status" value="<?php echo htmlspecialchars($status); ?>" class="border rounded-lg px-3 py-2" placeholder="Status (e.g. active)">
      <select name="tenant_id" class="border rounded-lg px-3 py-2">
        <option value="0">All Tenants</option>
        <?php foreach ($tenants as $t): ?>
          <option value="<?php echo (int)$t['id']; ?>" <?php echo $tenant_id === (int)$t['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($t['institution_name']); ?></option>
        <?php endforeach; ?>
      </select>
      <button class="px-4 py-2 rounded-lg bg-indigo-600 text-white">Filter</button>
      <div class="flex gap-2">
        <a href="approve-users.php" class="flex-1 px-3 py-2 rounded-lg border border-gray-200 text-center text-gray-700">Approve Users</a>
        <a href="ai-user-management.php" class="flex-1 px-3 py-2 rounded-lg border border-gray-200 text-center text-gray-700">AI Creator</a>
      </div>
    </form>
  </div>

  <div class="col-span-12 bg-white border border-gray-100 rounded-xl p-5">
    <form method="POST" id="bulkActionForm" class="grid grid-cols-1 md:grid-cols-6 gap-3 items-end">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
      <input type="hidden" name="action" id="bulkActionInput" value="">
      <input type="hidden" name="selected_user_ids[]" id="bulkSelectedUsersPlaceholder" value="">
      <div>
        <label class="text-sm text-gray-600">Bulk Role</label>
        <select name="bulk_role" class="w-full border rounded-lg px-3 py-2 text-sm">
          <option value="">Select role</option>
          <?php foreach ($role_options as $ro): ?>
            <option value="<?php echo htmlspecialchars($ro); ?>"><?php echo htmlspecialchars($ro); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="text-sm text-gray-600">Bulk Status</label>
        <select name="bulk_status" class="w-full border rounded-lg px-3 py-2 text-sm">
          <option value="">Select status</option>
          <option value="active">Active</option>
          <option value="pending">Pending</option>
          <option value="suspended">Suspended</option>
          <option value="inactive">Inactive</option>
        </select>
      </div>
      <div class="md:col-span-4 flex flex-wrap gap-2">
        <button type="button" onclick="submitBulkAction('bulk_set_role')" class="px-3 py-2 rounded-lg border border-emerald-300 text-emerald-700 text-sm">Apply Role to Selected</button>
        <button type="button" onclick="submitBulkAction('bulk_set_status')" class="px-3 py-2 rounded-lg border border-indigo-300 text-indigo-700 text-sm">Apply Status to Selected</button>
        <button type="button" onclick="submitBulkAction('bulk_delete_users')" class="px-3 py-2 rounded-lg border border-rose-300 text-rose-700 text-sm">Delete Selected</button>
      </div>
    </form>
  </div>

  <div class="col-span-12 lg:col-span-3 bg-white border border-gray-100 rounded-xl p-5">
    <h3 class="font-semibold mb-3">Users by Role</h3>
    <div class="space-y-2 text-sm">
      <?php foreach ($roles as $r): ?>
        <div class="flex items-center justify-between"><span class="text-gray-600"><?php echo htmlspecialchars($r['role']); ?></span><span class="font-semibold"><?php echo (int)$r['c']; ?></span></div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="col-span-12 lg:col-span-9 bg-white border border-gray-100 rounded-xl overflow-hidden">
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="bg-gray-50">
            <th class="text-left px-4 py-3">Name</th>
            <th class="text-left px-4 py-3">Email</th>
            <th class="text-left px-4 py-3">Role</th>
            <th class="text-left px-4 py-3">Status</th>
            <th class="text-left px-4 py-3">Tenant</th>
            <th class="text-left px-4 py-3">ID</th>
            <th class="text-left px-4 py-3">Created</th>
            <th class="text-left px-4 py-3">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($users)): ?>
            <tr>
              <td colspan="8" class="px-4 py-6 text-center text-gray-500">No users found.</td>
            </tr>
            <?php else: foreach ($users as $u): ?>
              <?php $name = trim(($u['full_name'] ?? '') ?: (($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''))); ?>
              <tr class="border-t border-gray-100">
                <td class="px-4 py-3 font-medium">
                  <div class="flex items-center gap-2">
                    <input type="checkbox" class="bulk-user-checkbox" value="<?php echo (int)$u['id']; ?>">
                    <span><?php echo htmlspecialchars($name); ?></span>
                  </div>
                </td>
                <td class="px-4 py-3"><?php echo htmlspecialchars($u['email'] ?? ''); ?></td>
                <td class="px-4 py-3"><?php echo htmlspecialchars($u['role'] ?? ''); ?></td>
                <td class="px-4 py-3"><?php echo htmlspecialchars($u['status'] ?? ''); ?></td>
                <td class="px-4 py-3"><?php echo htmlspecialchars($u['tenant_name'] ?? '-'); ?></td>
                <td class="px-4 py-3"><?php echo htmlspecialchars($u['assigned_id'] ?? '-'); ?></td>
                <td class="px-4 py-3"><?php echo htmlspecialchars($u['created_at'] ?? ''); ?></td>
                <td class="px-4 py-3">
                  <div class="flex flex-wrap gap-2">
                    <form method="POST" class="inline-flex gap-1 items-center">
                      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
                      <input type="hidden" name="action" value="set_status">
                      <input type="hidden" name="user_id" value="<?php echo (int)$u['id']; ?>">
                      <select name="new_status" class="border rounded px-2 py-1 text-xs">
                        <option value="active" <?php echo ($u['status'] ?? '') === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="pending" <?php echo ($u['status'] ?? '') === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="suspended" <?php echo ($u['status'] ?? '') === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                        <option value="inactive" <?php echo ($u['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                      </select>
                      <button class="border rounded px-2 py-1 text-xs text-indigo-700 border-indigo-200">Set</button>
                    </form>

                    <form method="POST" class="inline-flex gap-1 items-center">
                      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
                      <input type="hidden" name="action" value="set_role">
                      <input type="hidden" name="user_id" value="<?php echo (int)$u['id']; ?>">
                      <select name="new_role" class="border rounded px-2 py-1 text-xs">
                        <?php foreach ($role_options as $ro): ?>
                          <option value="<?php echo htmlspecialchars($ro); ?>" <?php echo ($u['role'] ?? '') === $ro ? 'selected' : ''; ?>><?php echo htmlspecialchars($ro); ?></option>
                        <?php endforeach; ?>
                      </select>
                      <button class="border rounded px-2 py-1 text-xs text-emerald-700 border-emerald-200">Role</button>
                    </form>

                    <form method="POST" onsubmit="return confirm('Delete this user permanently?');" class="inline-flex">
                      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
                      <input type="hidden" name="action" value="delete_user">
                      <input type="hidden" name="user_id" value="<?php echo (int)$u['id']; ?>">
                      <button class="border rounded px-2 py-1 text-xs text-rose-700 border-rose-200">Delete</button>
                    </form>
                    <button type="button"
                      class="border rounded px-2 py-1 text-xs text-sky-700 border-sky-200"
                      onclick="openEditUserModal(<?php echo (int)$u['id']; ?>, '<?php echo htmlspecialchars(addslashes($u['first_name'] ?? '')); ?>', '<?php echo htmlspecialchars(addslashes($u['last_name'] ?? '')); ?>', '<?php echo htmlspecialchars(addslashes($u['email'] ?? '')); ?>', '<?php echo htmlspecialchars(addslashes($u['role'] ?? '')); ?>', '<?php echo htmlspecialchars(addslashes($u['status'] ?? '')); ?>')">Edit</button>
                  </div>
                </td>
              </tr>
          <?php endforeach;
          endif; ?>
        </tbody>
      </table>
    </div>
    <div class="px-4 py-3 border-t border-gray-100 flex items-center justify-between text-sm">
      <span class="text-gray-500">Page <?php echo $page; ?> of <?php echo $total_pages; ?> • <?php echo (int)$total_users_filtered; ?> result(s)</span>
      <div class="flex gap-2">
        <?php $prev = max(1, $page - 1);
        $next = min($total_pages, $page + 1); ?>
        <a class="px-3 py-1.5 border rounded <?php echo $page <= 1 ? 'text-gray-300 border-gray-100 pointer-events-none' : 'text-gray-700 border-gray-200'; ?>" href="?q=<?php echo urlencode($q); ?>&role=<?php echo urlencode($role); ?>&status=<?php echo urlencode($status); ?>&tenant_id=<?php echo (int)$tenant_id; ?>&page=<?php echo $prev; ?>">Prev</a>
        <a class="px-3 py-1.5 border rounded <?php echo $page >= $total_pages ? 'text-gray-300 border-gray-100 pointer-events-none' : 'text-gray-700 border-gray-200'; ?>" href="?q=<?php echo urlencode($q); ?>&role=<?php echo urlencode($role); ?>&status=<?php echo urlencode($status); ?>&tenant_id=<?php echo (int)$tenant_id; ?>&page=<?php echo $next; ?>">Next</a>
      </div>
    </div>
  </div>
</div>

<div id="editUserModal" class="fixed inset-0 bg-black hidden items-center justify-center p-4" style="z-index:9999;">
  <div class="bg-white rounded-xl w-full max-w-xl p-5">
    <h3 class="text-lg font-semibold mb-4">Edit User</h3>
    <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-3">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
      <input type="hidden" name="action" value="edit_user">
      <input type="hidden" name="user_id" id="edit_user_id" value="0">
      <div>
        <label class="text-sm text-gray-600">First Name</label>
        <input id="edit_first_name" name="edit_first_name" class="w-full border rounded-lg px-3 py-2 text-sm" required>
      </div>
      <div>
        <label class="text-sm text-gray-600">Last Name</label>
        <input id="edit_last_name" name="edit_last_name" class="w-full border rounded-lg px-3 py-2 text-sm" required>
      </div>
      <div class="md:col-span-2">
        <label class="text-sm text-gray-600">Email</label>
        <input id="edit_email" type="email" name="edit_email" class="w-full border rounded-lg px-3 py-2 text-sm" required>
      </div>
      <div>
        <label class="text-sm text-gray-600">Role</label>
        <select id="edit_role" name="edit_role" class="w-full border rounded-lg px-3 py-2 text-sm">
          <?php foreach ($role_options as $ro): ?>
            <option value="<?php echo htmlspecialchars($ro); ?>"><?php echo htmlspecialchars($ro); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="text-sm text-gray-600">Status</label>
        <select id="edit_status" name="edit_status" class="w-full border rounded-lg px-3 py-2 text-sm">
          <option value="active">Active</option>
          <option value="pending">Pending</option>
          <option value="suspended">Suspended</option>
          <option value="inactive">Inactive</option>
        </select>
      </div>
      <div class="md:col-span-2 flex justify-end gap-2 pt-2">
        <button type="button" onclick="closeEditUserModal()" class="px-4 py-2 rounded-lg border border-gray-200 text-gray-700">Cancel</button>
        <button class="px-4 py-2 rounded-lg bg-indigo-600 text-white">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<script>
  function submitBulkAction(actionName) {
    const selected = Array.from(document.querySelectorAll('.bulk-user-checkbox:checked')).map(cb => cb.value);
    if (selected.length === 0) {
      alert('Select at least one user.');
      return;
    }
    if (actionName === 'bulk_delete_users' && !confirm('Delete selected users? This cannot be undone.')) {
      return;
    }

    const form = document.getElementById('bulkActionForm');
    document.getElementById('bulkActionInput').value = actionName;
    form.querySelectorAll('input[name="selected_user_ids[]"]').forEach((el, idx) => {
      if (idx > 0 || el.id !== 'bulkSelectedUsersPlaceholder') el.remove();
    });
    const placeholder = document.getElementById('bulkSelectedUsersPlaceholder');
    placeholder.value = selected[0] || '';
    for (let i = 1; i < selected.length; i++) {
      const hidden = document.createElement('input');
      hidden.type = 'hidden';
      hidden.name = 'selected_user_ids[]';
      hidden.value = selected[i];
      form.appendChild(hidden);
    }
    form.submit();
  }

  function openEditUserModal(id, first, last, email, role, status) {
    document.getElementById('edit_user_id').value = id;
    document.getElementById('edit_first_name').value = first;
    document.getElementById('edit_last_name').value = last;
    document.getElementById('edit_email').value = email;
    document.getElementById('edit_role').value = role;
    document.getElementById('edit_status').value = status;
    const modal = document.getElementById('editUserModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
  }

  function closeEditUserModal() {
    const modal = document.getElementById('editUserModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
  }
</script>
<?php
$page_content = ob_get_clean();
require BASE_PATH . '/resources/ui-core/layouts/master-dashboard.php';
