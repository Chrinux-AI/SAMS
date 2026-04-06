<?php

/**
 * Transport Reports
 * Read-only consolidated view for routes, vehicles, drivers, and assignments.
 */

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

$_user_role = $_SESSION['role'] ?? $_SESSION['user_role'] ?? '';
if (!in_array($_user_role, ['admin', 'super_admin', 'superadmin', 'owner', 'transport'], true)) {
  header('Location: ../login.php');
  exit;
}

function tr_has_tenant(string $table): bool
{
  try {
    return (bool)db()->fetchOne("SHOW COLUMNS FROM {$table} LIKE 'tenant_id'");
  } catch (Throwable $e) {
    return false;
  }
}

$is_super_admin = in_array($_user_role, ['super_admin', 'superadmin'], true);
$tenant_filter = (int)($_GET['tenant_id'] ?? ($_SESSION['tenant_id'] ?? 0));
$multi_tenant_ready = tr_has_tenant('transport_routes')
  && tr_has_tenant('transport_vehicles')
  && tr_has_tenant('transport_drivers')
  && tr_has_tenant('transport_assignments');

if (!$is_super_admin && $tenant_filter <= 0) {
  $tenant_filter = (int)($_SESSION['tenant_id'] ?? 0);
}

$tenants = [];
if ($is_super_admin && table_exists('tenants')) {
  $tenants = db()->fetchAll('SELECT id, institution_name FROM tenants ORDER BY institution_name') ?: [];
}

$where = '';
$params = [];
if ($multi_tenant_ready && $tenant_filter > 0) {
  $where = ' WHERE tenant_id = ? ';
  $params[] = $tenant_filter;
}

$routes = db()->fetchAll('SELECT * FROM transport_routes' . $where . ' ORDER BY created_at DESC LIMIT 200', $params) ?: [];
$vehicles = db()->fetchAll('SELECT * FROM transport_vehicles' . $where . ' ORDER BY created_at DESC LIMIT 200', $params) ?: [];
$drivers = db()->fetchAll('SELECT * FROM transport_drivers' . $where . ' ORDER BY created_at DESC LIMIT 200', $params) ?: [];
$assignments = db()->fetchAll('SELECT * FROM transport_assignments' . $where . ' ORDER BY created_at DESC LIMIT 200', $params) ?: [];

$page_title = 'Transport Reports';
$page_icon = 'assessment';
$page_subtitle = 'Consolidated transport operations reports';

ob_start();
?>
<div class="grid grid-cols-12 gap-6">
  <div class="col-span-12 bg-white rounded-xl border border-gray-100 p-6">
    <div class="flex items-center justify-between flex-wrap gap-3 mb-4">
      <div>
        <h2 class="text-xl font-semibold">Transport Reports</h2>
        <p class="text-sm text-gray-500">Routes, vehicles, drivers, and assignments in one view.</p>
      </div>
      <a href="transport-management.php" class="px-4 py-2 rounded-lg border border-gray-200 text-gray-700">Back to Transport Management</a>
    </div>

    <?php if ($is_super_admin): ?>
      <form method="GET" class="flex flex-wrap gap-2 items-end mb-4">
        <div>
          <label class="block text-sm text-gray-600 mb-1">Tenant Scope</label>
          <select name="tenant_id" class="border rounded-lg px-3 py-2 text-sm min-w-[260px]">
            <option value="0">All Tenants</option>
            <?php foreach ($tenants as $t): ?>
              <option value="<?php echo (int)$t['id']; ?>" <?php echo $tenant_filter === (int)$t['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($t['institution_name']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button type="submit" class="px-4 py-2 rounded-lg bg-indigo-600 text-white">Apply Scope</button>
      </form>
    <?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
      <div class="rounded-lg border border-gray-100 p-4">
        <p class="text-sm text-gray-500">Routes</p>
        <p class="text-2xl font-bold"><?php echo count($routes); ?></p>
      </div>
      <div class="rounded-lg border border-gray-100 p-4">
        <p class="text-sm text-gray-500">Vehicles</p>
        <p class="text-2xl font-bold"><?php echo count($vehicles); ?></p>
      </div>
      <div class="rounded-lg border border-gray-100 p-4">
        <p class="text-sm text-gray-500">Drivers</p>
        <p class="text-2xl font-bold"><?php echo count($drivers); ?></p>
      </div>
      <div class="rounded-lg border border-gray-100 p-4">
        <p class="text-sm text-gray-500">Assignments</p>
        <p class="text-2xl font-bold"><?php echo count($assignments); ?></p>
      </div>
    </div>

    <div class="space-y-6">
      <section>
        <h3 class="font-semibold mb-2">Recent Routes</h3>
        <div class="overflow-x-auto border rounded-lg">
          <table class="w-full text-sm">
            <thead>
              <tr class="bg-gray-50">
                <th class="px-3 py-2 text-left">Route</th>
                <th class="px-3 py-2 text-left">Number</th>
                <th class="px-3 py-2 text-left">Distance</th>
                <th class="px-3 py-2 text-left">Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($routes as $r): ?>
                <tr class="border-t">
                  <td class="px-3 py-2"><?php echo htmlspecialchars($r['route_name'] ?? '-'); ?></td>
                  <td class="px-3 py-2"><?php echo htmlspecialchars($r['route_number'] ?? '-'); ?></td>
                  <td class="px-3 py-2"><?php echo htmlspecialchars((string)($r['distance_km'] ?? '-')); ?> km</td>
                  <td class="px-3 py-2"><?php echo htmlspecialchars($r['status'] ?? '-'); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>

      <section>
        <h3 class="font-semibold mb-2">Recent Vehicles</h3>
        <div class="overflow-x-auto border rounded-lg">
          <table class="w-full text-sm">
            <thead>
              <tr class="bg-gray-50">
                <th class="px-3 py-2 text-left">Vehicle</th>
                <th class="px-3 py-2 text-left">Reg. No.</th>
                <th class="px-3 py-2 text-left">Type</th>
                <th class="px-3 py-2 text-left">Capacity</th>
                <th class="px-3 py-2 text-left">Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($vehicles as $v): ?>
                <tr class="border-t">
                  <td class="px-3 py-2"><?php echo htmlspecialchars($v['vehicle_name'] ?? '-'); ?></td>
                  <td class="px-3 py-2"><?php echo htmlspecialchars($v['registration_number'] ?? '-'); ?></td>
                  <td class="px-3 py-2"><?php echo htmlspecialchars($v['vehicle_type'] ?? '-'); ?></td>
                  <td class="px-3 py-2"><?php echo (int)($v['capacity'] ?? 0); ?></td>
                  <td class="px-3 py-2"><?php echo htmlspecialchars($v['status'] ?? '-'); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>

      <section>
        <h3 class="font-semibold mb-2">Recent Drivers</h3>
        <div class="overflow-x-auto border rounded-lg">
          <table class="w-full text-sm">
            <thead>
              <tr class="bg-gray-50">
                <th class="px-3 py-2 text-left">Driver</th>
                <th class="px-3 py-2 text-left">License</th>
                <th class="px-3 py-2 text-left">Phone</th>
                <th class="px-3 py-2 text-left">Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($drivers as $d): ?>
                <tr class="border-t">
                  <td class="px-3 py-2"><?php echo htmlspecialchars($d['driver_name'] ?? '-'); ?></td>
                  <td class="px-3 py-2"><?php echo htmlspecialchars($d['license_number'] ?? '-'); ?></td>
                  <td class="px-3 py-2"><?php echo htmlspecialchars($d['phone'] ?? '-'); ?></td>
                  <td class="px-3 py-2"><?php echo htmlspecialchars($d['status'] ?? '-'); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
    </div>
  </div>
</div>
<?php
$page_content = ob_get_clean();
require BASE_PATH . '/resources/ui-core/layouts/master-dashboard.php';
