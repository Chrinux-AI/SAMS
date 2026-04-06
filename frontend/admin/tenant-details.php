<?php

/**
 * Tenant Details (Super Admin)
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

$tenant_id = (int)($_GET['id'] ?? 0);
if ($tenant_id <= 0) {
  header('Location: all-tenants.php');
  exit;
}

$tenant = db()->fetchOne("SELECT * FROM tenants WHERE id = ?", [$tenant_id]);
if (!$tenant) {
  header('Location: all-tenants.php');
  exit;
}

$active_tab = $_GET['tab'] ?? 'profile';
if (!in_array($active_tab, ['profile', 'users', 'settings', 'activity'], true)) {
  $active_tab = 'profile';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
    if (isset($_POST['set_status'])) {
      $status = in_array($_POST['status'] ?? '', ['active', 'setup', 'suspended']) ? $_POST['status'] : 'setup';
      db()->update('tenants', ['status' => $status, 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$tenant_id]);
      log_activity($_SESSION['user_id'] ?? 0, 'tenant_status_update', 'tenants', $tenant_id, 'Status changed to ' . $status);
    } elseif (isset($_POST['save_tenant_profile'])) {
      $payload = [
        'institution_name' => trim((string)($_POST['institution_name'] ?? '')),
        'owner_email' => trim((string)($_POST['owner_email'] ?? '')),
        'owner_phone' => trim((string)($_POST['owner_phone'] ?? '')),
        'custom_domain' => trim((string)($_POST['custom_domain'] ?? '')),
        'plan_type' => trim((string)($_POST['plan_type'] ?? 'basic')),
        'updated_at' => date('Y-m-d H:i:s')
      ];

      if ($payload['institution_name'] !== '') {
        db()->update('tenants', $payload, 'id = ?', [$tenant_id]);
        log_activity($_SESSION['user_id'] ?? 0, 'tenant_profile_update', 'tenants', $tenant_id, 'Updated tenant profile details');
      }
    } elseif (isset($_POST['archive_tenant'])) {
      db()->update('tenants', ['status' => 'suspended', 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$tenant_id]);
      log_activity($_SESSION['user_id'] ?? 0, 'tenant_archive', 'tenants', $tenant_id, 'Tenant archived (status set to suspended)');
    } elseif (isset($_POST['delete_tenant'])) {
      $confirmText = trim((string)($_POST['delete_confirm_text'] ?? ''));
      $expected = 'DELETE ' . ($tenant['institution_name'] ?? '');
      if ($confirmText === $expected) {
        $updateData = ['status' => 'deleted', 'updated_at' => date('Y-m-d H:i:s')];
        if (db()->fetchOne("SHOW COLUMNS FROM tenants LIKE 'deleted_at'")) {
          $updateData['deleted_at'] = date('Y-m-d H:i:s');
        }
        db()->update('tenants', $updateData, 'id = ?', [$tenant_id]);
        log_activity($_SESSION['user_id'] ?? 0, 'tenant_delete', 'tenants', $tenant_id, 'Tenant marked as deleted');
        $_SESSION['tenant_details_success'] = 'Tenant deleted successfully.';
        header('Location: all-tenants.php');
        exit;
      }

      $_SESSION['tenant_details_error'] = 'Delete confirmation text mismatch. Tenant was not deleted.';
    }
  }
  header('Location: tenant-details.php?id=' . $tenant_id . '&tab=' . urlencode((string)$active_tab));
  exit;
}

$user_counts = db()->fetchAll(
  "SELECT role, COUNT(*) as c FROM users WHERE tenant_id = ? GROUP BY role ORDER BY c DESC",
  [$tenant_id]
) ?: [];
$total_users = db()->fetchOne("SELECT COUNT(*) as c FROM users WHERE tenant_id = ?", [$tenant_id])['c'] ?? 0;
$recent_users = db()->fetchAll("SELECT id, first_name, last_name, email, role, status, created_at FROM users WHERE tenant_id = ? ORDER BY created_at DESC LIMIT 15", [$tenant_id]) ?: [];
$tenant_activity = db()->fetchAll("SELECT created_at, action, details, user_id FROM activity_logs WHERE entity_type = 'tenants' AND entity_id = ? ORDER BY created_at DESC LIMIT 50", [$tenant_id]) ?: [];
$tenant_error = $_SESSION['tenant_details_error'] ?? '';
unset($_SESSION['tenant_details_error']);

$page_title = 'Tenant Details';
$page_icon = 'apartment';
$page_subtitle = 'Institution profile and controls';
ob_start();
?>
<div class="grid grid-cols-12 gap-6">
  <div class="col-span-12 lg:col-span-8 bg-white rounded-xl border border-gray-100 p-6">
    <?php if ($tenant_error !== ''): ?>
      <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700"><?php echo htmlspecialchars($tenant_error); ?></div>
    <?php endif; ?>

    <h2 class="text-xl font-semibold"><?php echo htmlspecialchars($tenant['institution_name'] ?? 'Institution'); ?></h2>
    <p class="text-sm text-gray-500 mt-1">Subdomain: <?php echo htmlspecialchars($tenant['subdomain'] ?? '-'); ?> • Status: <span class="font-medium"><?php echo htmlspecialchars($tenant['status'] ?? 'setup'); ?></span></p>

    <div class="mt-5 flex flex-wrap gap-2 border-b border-gray-100 pb-4">
      <a href="tenant-details.php?id=<?php echo $tenant_id; ?>&tab=profile" class="px-3 py-2 rounded-lg text-sm <?php echo $active_tab === 'profile' ? 'bg-indigo-600 text-white' : 'border border-gray-200 text-gray-700'; ?>">Profile</a>
      <a href="tenant-details.php?id=<?php echo $tenant_id; ?>&tab=users" class="px-3 py-2 rounded-lg text-sm <?php echo $active_tab === 'users' ? 'bg-indigo-600 text-white' : 'border border-gray-200 text-gray-700'; ?>">Users</a>
      <a href="tenant-details.php?id=<?php echo $tenant_id; ?>&tab=settings" class="px-3 py-2 rounded-lg text-sm <?php echo $active_tab === 'settings' ? 'bg-indigo-600 text-white' : 'border border-gray-200 text-gray-700'; ?>">Settings</a>
      <a href="tenant-details.php?id=<?php echo $tenant_id; ?>&tab=activity" class="px-3 py-2 rounded-lg text-sm <?php echo $active_tab === 'activity' ? 'bg-indigo-600 text-white' : 'border border-gray-200 text-gray-700'; ?>">Activity</a>
    </div>

    <?php if ($active_tab === 'profile'): ?>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-6 text-sm">
        <div><span class="text-gray-500">Custom Domain:</span> <span class="font-medium"><?php echo htmlspecialchars($tenant['custom_domain'] ?? '-'); ?></span></div>
        <div><span class="text-gray-500">Plan:</span> <span class="font-medium"><?php echo htmlspecialchars($tenant['plan_type'] ?? 'basic'); ?></span></div>
        <div><span class="text-gray-500">Owner Email:</span> <span class="font-medium"><?php echo htmlspecialchars($tenant['owner_email'] ?? '-'); ?></span></div>
        <div><span class="text-gray-500">Owner Phone:</span> <span class="font-medium"><?php echo htmlspecialchars($tenant['owner_phone'] ?? '-'); ?></span></div>
        <div><span class="text-gray-500">Created:</span> <span class="font-medium"><?php echo htmlspecialchars($tenant['created_at'] ?? '-'); ?></span></div>
        <div><span class="text-gray-500">Total Users:</span> <span class="font-medium"><?php echo (int)$total_users; ?></span></div>
      </div>
    <?php elseif ($active_tab === 'users'): ?>
      <div class="mt-6">
        <h3 class="font-semibold mb-3">Recent Users</h3>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="bg-gray-50">
                <th class="text-left px-3 py-2">Name</th>
                <th class="text-left px-3 py-2">Email</th>
                <th class="text-left px-3 py-2">Role</th>
                <th class="text-left px-3 py-2">Status</th>
                <th class="text-left px-3 py-2">Created</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recent_users as $u): ?>
                <tr class="border-t border-gray-100">
                  <td class="px-3 py-2"><?php echo htmlspecialchars(trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''))); ?></td>
                  <td class="px-3 py-2"><?php echo htmlspecialchars($u['email'] ?? ''); ?></td>
                  <td class="px-3 py-2"><?php echo htmlspecialchars($u['role'] ?? ''); ?></td>
                  <td class="px-3 py-2"><?php echo htmlspecialchars($u['status'] ?? ''); ?></td>
                  <td class="px-3 py-2"><?php echo htmlspecialchars($u['created_at'] ?? ''); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php elseif ($active_tab === 'settings'): ?>
      <div class="mt-6">
        <h3 class="font-semibold mb-3">Tenant Settings</h3>
        <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
          <div>
            <label class="block text-sm text-gray-600 mb-1">Institution Name</label>
            <input name="institution_name" class="w-full border rounded-lg px-3 py-2 text-sm" value="<?php echo htmlspecialchars($tenant['institution_name'] ?? ''); ?>" required>
          </div>
          <div>
            <label class="block text-sm text-gray-600 mb-1">Plan Type</label>
            <select name="plan_type" class="w-full border rounded-lg px-3 py-2 text-sm">
              <?php $plan = (string)($tenant['plan_type'] ?? 'basic'); ?>
              <option value="basic" <?php echo $plan === 'basic' ? 'selected' : ''; ?>>Basic</option>
              <option value="pro" <?php echo $plan === 'pro' ? 'selected' : ''; ?>>Pro</option>
              <option value="enterprise" <?php echo $plan === 'enterprise' ? 'selected' : ''; ?>>Enterprise</option>
            </select>
          </div>
          <div>
            <label class="block text-sm text-gray-600 mb-1">Owner Email</label>
            <input type="email" name="owner_email" class="w-full border rounded-lg px-3 py-2 text-sm" value="<?php echo htmlspecialchars($tenant['owner_email'] ?? ''); ?>">
          </div>
          <div>
            <label class="block text-sm text-gray-600 mb-1">Owner Phone</label>
            <input name="owner_phone" class="w-full border rounded-lg px-3 py-2 text-sm" value="<?php echo htmlspecialchars($tenant['owner_phone'] ?? ''); ?>">
          </div>
          <div class="md:col-span-2">
            <label class="block text-sm text-gray-600 mb-1">Custom Domain</label>
            <input name="custom_domain" class="w-full border rounded-lg px-3 py-2 text-sm" value="<?php echo htmlspecialchars($tenant['custom_domain'] ?? ''); ?>">
          </div>
          <div class="md:col-span-2 flex justify-end">
            <button name="save_tenant_profile" type="submit" class="px-4 py-2 rounded-lg bg-indigo-600 text-white">Save Settings</button>
          </div>
        </form>
      </div>
    <?php else: ?>
      <div class="mt-6">
        <h3 class="font-semibold mb-3">Tenant Activity</h3>
        <?php if (empty($tenant_activity)): ?>
          <p class="text-sm text-gray-500">No tenant-scoped activity logs found.</p>
        <?php else: ?>
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead>
                <tr class="bg-gray-50">
                  <th class="text-left px-3 py-2">Timestamp</th>
                  <th class="text-left px-3 py-2">Action</th>
                  <th class="text-left px-3 py-2">Details</th>
                  <th class="text-left px-3 py-2">User ID</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($tenant_activity as $log): ?>
                  <tr class="border-t border-gray-100">
                    <td class="px-3 py-2"><?php echo htmlspecialchars((string)$log['created_at']); ?></td>
                    <td class="px-3 py-2"><?php echo htmlspecialchars((string)$log['action']); ?></td>
                    <td class="px-3 py-2"><?php echo htmlspecialchars((string)($log['details'] ?? '')); ?></td>
                    <td class="px-3 py-2"><?php echo (int)($log['user_id'] ?? 0); ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="col-span-12 lg:col-span-4 bg-white rounded-xl border border-gray-100 p-6">
    <h3 class="font-semibold mb-3">Tenant Controls</h3>
    <form method="POST" class="space-y-3">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
      <label class="block text-sm text-gray-600">Status</label>
      <select name="status" class="w-full border rounded-lg px-3 py-2 text-sm">
        <option value="active" <?php echo ($tenant['status'] ?? '') === 'active' ? 'selected' : ''; ?>>Active</option>
        <option value="setup" <?php echo ($tenant['status'] ?? '') === 'setup' ? 'selected' : ''; ?>>Setup</option>
        <option value="suspended" <?php echo ($tenant['status'] ?? '') === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
      </select>
      <button name="set_status" type="submit" class="w-full px-4 py-2 rounded-lg bg-indigo-600 text-white">Update Status</button>
      <button type="button" onclick="accessTenantContext(<?php echo $tenant_id; ?>)" class="w-full px-4 py-2 rounded-lg border border-indigo-200 text-indigo-700">Access Tenant</button>
      <a href="security-logs.php" class="block text-center w-full px-4 py-2 rounded-lg border border-gray-200 text-gray-700">View Security Logs</a>
      <a href="all-tenants.php" class="block text-center w-full px-4 py-2 rounded-lg border border-gray-200 text-gray-700">Back to All Schools</a>
      <a href="super-admin-dashboard.php" class="block text-center w-full px-4 py-2 rounded-lg border border-gray-200 text-gray-700">Back to Dashboard</a>
    </form>

    <form method="POST" class="mt-4">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
      <button name="archive_tenant" type="submit" class="w-full px-4 py-2 rounded-lg bg-red-600 text-white" onclick="return confirm('Archive this tenant? This sets status to suspended.');">Archive Tenant</button>
    </form>

    <form method="POST" class="mt-3 space-y-2">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
      <input type="text" name="delete_confirm_text" class="w-full border border-rose-200 rounded-lg px-3 py-2 text-sm" placeholder="Type: DELETE <?php echo htmlspecialchars($tenant['institution_name'] ?? ''); ?>">
      <button name="delete_tenant" type="submit" class="w-full px-4 py-2 rounded-lg bg-rose-700 text-white" onclick="return confirm('Permanently remove this tenant from active operations?');">Delete Tenant</button>
      <p class="text-xs text-rose-600">Safety check required: type <strong>DELETE <?php echo htmlspecialchars($tenant['institution_name'] ?? ''); ?></strong> exactly.</p>
    </form>

    <div class="mt-6">
      <h4 class="font-semibold mb-2">Role Distribution</h4>
      <div class="space-y-2 text-sm">
        <?php foreach ($user_counts as $row): ?>
          <div class="flex items-center justify-between"><span class="text-gray-600"><?php echo htmlspecialchars($row['role']); ?></span><span class="font-semibold"><?php echo (int)$row['c']; ?></span></div>
        <?php endforeach; ?>
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
