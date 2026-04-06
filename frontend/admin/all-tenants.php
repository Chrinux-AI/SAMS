<?php

/**
 * All Tenants (Super Admin)
 */
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';

$_user_role = $_SESSION['role'] ?? $_SESSION['user_role'] ?? '';
if (!in_array($_user_role, ['admin', 'super_admin', 'superadmin', 'owner'])) {
  header('Location: ../login.php');
  exit;
}

$q = trim($_GET['q'] ?? '');
$status = trim($_GET['status'] ?? '');
$sort = $_GET['sort'] ?? 'created_desc';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;

$where = [];
$params = [];
if ($q !== '') {
  $where[] = '(t.institution_name LIKE ? OR t.subdomain LIKE ? OR t.admin_email LIKE ?)';
  $params[] = "%{$q}%";
  $params[] = "%{$q}%";
  $params[] = "%{$q}%";
}
if ($status !== '' && in_array($status, ['active', 'setup', 'suspended'])) {
  $where[] = 't.status = ?';
  $params[] = $status;
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$orderBy = 't.created_at DESC';
if ($sort === 'name_asc') $orderBy = 't.institution_name ASC';
if ($sort === 'name_desc') $orderBy = 't.institution_name DESC';
if ($sort === 'users_desc') $orderBy = 'user_count DESC';
if ($sort === 'users_asc') $orderBy = 'user_count ASC';
if ($sort === 'created_asc') $orderBy = 't.created_at ASC';

$total_rows = db()->fetchOne(
  "SELECT COUNT(*) AS c FROM tenants t {$whereSql}",
  $params
)['c'] ?? 0;

$total_pages = max(1, (int)ceil(((int)$total_rows) / $per_page));
if ($page > $total_pages) $page = $total_pages;
$offset = ($page - 1) * $per_page;

$tenants = db()->fetchAll(
  "SELECT t.*, COUNT(u.id) AS user_count
     FROM tenants t
     LEFT JOIN users u ON u.tenant_id = t.id
     {$whereSql}
     GROUP BY t.id
     ORDER BY {$orderBy}
     LIMIT {$per_page} OFFSET {$offset}",
  $params
) ?: [];

$counts = db()->fetchOne("SELECT COUNT(*) total, SUM(CASE WHEN status='active' THEN 1 ELSE 0 END) active, SUM(CASE WHEN status='setup' THEN 1 ELSE 0 END) setup, SUM(CASE WHEN status='suspended' THEN 1 ELSE 0 END) suspended FROM tenants") ?: ['total' => 0, 'active' => 0, 'setup' => 0, 'suspended' => 0];

$page_title = 'All Tenants';
$page_icon = 'domain';
$page_subtitle = 'Manage all schools and institutions';
ob_start();
?>
<div class="grid grid-cols-12 gap-6">
  <div class="col-span-12 grid grid-cols-1 md:grid-cols-4 gap-4">
    <div class="sams-stat-card">
      <p class="text-sm text-gray-500">Total Tenants</p>
      <p class="text-3xl font-bold"><?php echo (int)$counts['total']; ?></p>
    </div>
    <div class="sams-stat-card">
      <p class="text-sm text-gray-500">Active</p>
      <p class="text-3xl font-bold text-emerald-600"><?php echo (int)$counts['active']; ?></p>
    </div>
    <div class="sams-stat-card">
      <p class="text-sm text-gray-500">Setup</p>
      <p class="text-3xl font-bold text-amber-600"><?php echo (int)$counts['setup']; ?></p>
    </div>
    <div class="sams-stat-card">
      <p class="text-sm text-gray-500">Suspended</p>
      <p class="text-3xl font-bold text-rose-600"><?php echo (int)$counts['suspended']; ?></p>
    </div>
  </div>

  <div class="col-span-12 bg-white border border-gray-100 rounded-xl p-5">
    <form class="grid grid-cols-1 md:grid-cols-4 gap-3">
      <input type="text" name="q" value="<?php echo htmlspecialchars($q); ?>" class="border rounded-lg px-3 py-2" placeholder="Search institution/subdomain/email">
      <select name="status" class="border rounded-lg px-3 py-2">
        <option value="">All Statuses</option>
        <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
        <option value="setup" <?php echo $status === 'setup' ? 'selected' : ''; ?>>Setup</option>
        <option value="suspended" <?php echo $status === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
      </select>
      <select name="sort" class="border rounded-lg px-3 py-2">
        <option value="created_desc" <?php echo $sort === 'created_desc' ? 'selected' : ''; ?>>Newest First</option>
        <option value="created_asc" <?php echo $sort === 'created_asc' ? 'selected' : ''; ?>>Oldest First</option>
        <option value="name_asc" <?php echo $sort === 'name_asc' ? 'selected' : ''; ?>>Name A-Z</option>
        <option value="name_desc" <?php echo $sort === 'name_desc' ? 'selected' : ''; ?>>Name Z-A</option>
        <option value="users_desc" <?php echo $sort === 'users_desc' ? 'selected' : ''; ?>>Most Users</option>
        <option value="users_asc" <?php echo $sort === 'users_asc' ? 'selected' : ''; ?>>Fewest Users</option>
      </select>
      <button class="px-4 py-2 rounded-lg bg-indigo-600 text-white">Filter</button>
      <a href="create-tenant.php" class="px-4 py-2 rounded-lg border border-gray-200 text-gray-700 text-center">+ Create Tenant</a>
    </form>
  </div>

  <div class="col-span-12 bg-white border border-gray-100 rounded-xl overflow-hidden">
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="bg-gray-50">
            <th class="text-left px-5 py-3">Institution</th>
            <th class="text-left px-5 py-3">Subdomain</th>
            <th class="text-left px-5 py-3">Admin Email</th>
            <th class="text-left px-5 py-3">Status</th>
            <th class="text-left px-5 py-3">Users</th>
            <th class="text-left px-5 py-3">Created</th>
            <th class="text-left px-5 py-3">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($tenants)): ?>
            <tr>
              <td colspan="7" class="px-5 py-6 text-center text-gray-500">No tenants found.</td>
            </tr>
            <?php else: foreach ($tenants as $t): ?>
              <tr class="border-t border-gray-100">
                <td class="px-5 py-3 font-medium"><?php echo htmlspecialchars($t['institution_name'] ?? ''); ?></td>
                <td class="px-5 py-3"><?php echo htmlspecialchars($t['subdomain'] ?? '-'); ?></td>
                <td class="px-5 py-3"><?php echo htmlspecialchars($t['admin_email'] ?? '-'); ?></td>
                <td class="px-5 py-3"><?php echo htmlspecialchars($t['status'] ?? 'setup'); ?></td>
                <td class="px-5 py-3"><?php echo (int)($t['user_count'] ?? 0); ?></td>
                <td class="px-5 py-3"><?php echo htmlspecialchars($t['created_at'] ?? ''); ?></td>
                <td class="px-5 py-3">
                  <div class="flex flex-wrap gap-2">
                    <a class="text-indigo-600 hover:underline" href="tenant-details.php?id=<?php echo urlencode($t['id']); ?>">View</a>
                    <button type="button" class="text-blue-600 hover:underline" onclick="accessTenantContext(<?php echo (int)$t['id']; ?>)">Access</button>
                    <a class="text-emerald-600 hover:underline" href="tenant-details.php?id=<?php echo urlencode($t['id']); ?>&tab=settings">Edit</a>
                  </div>
                </td>
              </tr>
          <?php endforeach;
          endif; ?>
        </tbody>
      </table>
    </div>
    <div class="px-5 py-4 border-t border-gray-100 flex items-center justify-between text-sm">
      <span class="text-gray-500">Page <?php echo $page; ?> of <?php echo $total_pages; ?> • <?php echo (int)$total_rows; ?> result(s)</span>
      <div class="flex gap-2">
        <?php $prev = max(1, $page - 1);
        $next = min($total_pages, $page + 1); ?>
        <a class="px-3 py-1.5 border rounded <?php echo $page <= 1 ? 'text-gray-300 border-gray-100 pointer-events-none' : 'text-gray-700 border-gray-200'; ?>" href="?q=<?php echo urlencode($q); ?>&status=<?php echo urlencode($status); ?>&sort=<?php echo urlencode($sort); ?>&page=<?php echo $prev; ?>">Prev</a>
        <a class="px-3 py-1.5 border rounded <?php echo $page >= $total_pages ? 'text-gray-300 border-gray-100 pointer-events-none' : 'text-gray-700 border-gray-200'; ?>" href="?q=<?php echo urlencode($q); ?>&status=<?php echo urlencode($status); ?>&sort=<?php echo urlencode($sort); ?>&page=<?php echo $next; ?>">Next</a>
      </div>
    </div>
  </div>
</div>
<script>
  async function accessTenantContext(tenantId) {
    try {
      const response = await fetch('switch-tenant.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          tenant_id: tenantId
        })
      });
      const data = await response.json();
      if (data.success) {
        window.location.href = data.redirect_url || 'dashboard.php';
      } else {
        alert(data.error || 'Failed to switch tenant context');
      }
    } catch (err) {
      alert('Failed to switch tenant context');
    }
  }
</script>
<?php
$page_content = ob_get_clean();
require BASE_PATH . '/resources/ui-core/layouts/master-dashboard.php';
