<?php

/**
 * Platform Analytics (Super Admin)
 * Cross-tenant KPIs and trends overview
 */
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_once '../includes/sams-multi-tenant.php';

$_user_role = $_SESSION['role'] ?? $_SESSION['user_role'] ?? '';
if (!in_array($_user_role, ['admin', 'super_admin', 'superadmin', 'owner'])) {
  header('Location: ../login.php');
  exit;
}

$page_title = 'Platform Analytics';
$page_icon = 'analytics';
$page_subtitle = 'Cross-tenant insights and performance trends';
$full_name = $_SESSION['full_name'] ?? 'Administrator';

$range = $_GET['range'] ?? '30';
$days = in_array($range, ['7', '30', '90', '365']) ? (int)$range : 30;

try {
  $tenant_manager = new SAMS_MultiTenant();
  $tenants = $tenant_manager->getAllTenants();
} catch (Throwable $e) {
  $tenants = [];
}

$total_tenants = count($tenants);
$active_tenants = 0;
foreach ($tenants as $t) {
  if (($t['status'] ?? '') === 'active') $active_tenants++;
}

$total_users = db()->fetchOne("SELECT COUNT(*) as c FROM users")['c'] ?? 0;
$new_users = db()->fetchOne("SELECT COUNT(*) as c FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)", [$days])['c'] ?? 0;
$active_sessions = db()->fetchOne("SELECT COUNT(*) as c FROM user_sessions WHERE last_activity > DATE_SUB(NOW(), INTERVAL 30 MINUTE)")['c'] ?? 0;
$today_messages = db()->fetchOne("SELECT COUNT(*) as c FROM messages WHERE DATE(created_at)=CURDATE()")['c'] ?? 0;

$roles = db()->fetchAll("SELECT role, COUNT(*) as cnt FROM users GROUP BY role ORDER BY cnt DESC") ?: [];
$top_tenants = db()->fetchAll(
  "SELECT t.id, t.institution_name, t.status, COUNT(u.id) as user_count
     FROM tenants t
     LEFT JOIN users u ON u.tenant_id = t.id
     GROUP BY t.id, t.institution_name, t.status
     ORDER BY user_count DESC
     LIMIT 10"
) ?: [];

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
  header('Content-Type: text/csv');
  header('Content-Disposition: attachment; filename="platform_analytics_' . date('Y-m-d') . '.csv"');
  $out = fopen('php://output', 'w');
  fputcsv($out, ['Metric', 'Value']);
  fputcsv($out, ['Total Tenants', $total_tenants]);
  fputcsv($out, ['Active Tenants', $active_tenants]);
  fputcsv($out, ['Total Users', $total_users]);
  fputcsv($out, ['New Users (' . $days . 'd)', $new_users]);
  fputcsv($out, ['Active Sessions (30m)', $active_sessions]);
  fputcsv($out, ['Messages Today', $today_messages]);
  fputcsv($out, []);
  fputcsv($out, ['Role', 'Count']);
  foreach ($roles as $r) {
    fputcsv($out, [$r['role'], (int)$r['cnt']]);
  }
  fputcsv($out, []);
  fputcsv($out, ['Top Tenant', 'Status', 'User Count']);
  foreach ($top_tenants as $t) {
    fputcsv($out, [$t['institution_name'], $t['status'], (int)$t['user_count']]);
  }
  fclose($out);
  exit;
}

$daily_users = db()->fetchAll(
  "SELECT DATE(created_at) as d, COUNT(*) as c
     FROM users
     WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
     GROUP BY DATE(created_at)
     ORDER BY d ASC",
  [$days]
) ?: [];

$labels = [];
$series = [];
foreach ($daily_users as $row) {
  $labels[] = date('M j', strtotime($row['d']));
  $series[] = (int)$row['c'];
}

ob_start();
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<div class="grid grid-cols-12 gap-6">
  <div class="col-span-12 lg:col-span-3 sams-stat-card">
    <p class="text-sm text-gray-500">Tenants</p>
    <p class="text-3xl font-bold"><?php echo $total_tenants; ?></p>
    <p class="text-xs text-emerald-600"><?php echo $active_tenants; ?> active</p>
  </div>
  <div class="col-span-12 lg:col-span-3 sams-stat-card">
    <p class="text-sm text-gray-500">Platform Users</p>
    <p class="text-3xl font-bold"><?php echo number_format($total_users); ?></p>
    <p class="text-xs text-blue-600">+<?php echo number_format($new_users); ?> in <?php echo $days; ?>d</p>
  </div>
  <div class="col-span-12 lg:col-span-3 sams-stat-card">
    <p class="text-sm text-gray-500">Active Sessions</p>
    <p class="text-3xl font-bold"><?php echo number_format($active_sessions); ?></p>
    <p class="text-xs text-gray-500">Last 30 mins</p>
  </div>
  <div class="col-span-12 lg:col-span-3 sams-stat-card">
    <p class="text-sm text-gray-500">Messages Today</p>
    <p class="text-3xl font-bold"><?php echo number_format($today_messages); ?></p>
    <p class="text-xs text-gray-500">Communication load</p>
  </div>

  <div class="col-span-12 bg-white rounded-xl border border-gray-100 p-5">
    <div class="flex items-center justify-between mb-4">
      <h3 class="font-semibold">User Growth</h3>
      <form method="GET" class="flex gap-2">
        <select name="range" class="border rounded-lg px-3 py-2 text-sm" onchange="this.form.submit()">
          <option value="7" <?php echo $days === 7 ? 'selected' : ''; ?>>Last 7 days</option>
          <option value="30" <?php echo $days === 30 ? 'selected' : ''; ?>>Last 30 days</option>
          <option value="90" <?php echo $days === 90 ? 'selected' : ''; ?>>Last 90 days</option>
          <option value="365" <?php echo $days === 365 ? 'selected' : ''; ?>>Last 365 days</option>
        </select>
        <a href="?range=<?php echo $days; ?>&export=csv" class="border rounded-lg px-3 py-2 text-sm text-indigo-700 border-indigo-200">Export CSV</a>
      </form>
    </div>
    <canvas id="growthChart" height="90"></canvas>
  </div>

  <div class="col-span-12 lg:col-span-5 bg-white rounded-xl border border-gray-100 p-5">
    <h3 class="font-semibold mb-4">Role Distribution</h3>
    <div class="mb-4"><canvas id="roleChart" height="170"></canvas></div>
    <div class="space-y-3">
      <?php foreach ($roles as $r): ?>
        <div class="flex items-center justify-between"><span class="text-sm text-gray-700"><?php echo htmlspecialchars($r['role']); ?></span><span class="text-sm font-semibold"><?php echo (int)$r['cnt']; ?></span></div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="col-span-12 lg:col-span-7 bg-white rounded-xl border border-gray-100 overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-100">
      <h3 class="font-semibold">Top Tenants by Users</h3>
    </div>
    <div class="px-5 py-4 border-b border-gray-100"><canvas id="tenantBarChart" height="120"></canvas></div>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="bg-gray-50">
            <th class="text-left px-5 py-3">Institution</th>
            <th class="text-left px-5 py-3">Status</th>
            <th class="text-left px-5 py-3">Users</th>
            <th class="text-left px-5 py-3">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($top_tenants as $t): ?>
            <tr class="border-t border-gray-100">
              <td class="px-5 py-3"><?php echo htmlspecialchars($t['institution_name']); ?></td>
              <td class="px-5 py-3"><?php echo htmlspecialchars($t['status']); ?></td>
              <td class="px-5 py-3"><?php echo (int)$t['user_count']; ?></td>
              <td class="px-5 py-3"><a class="text-blue-600 hover:underline" href="tenant-details.php?id=<?php echo urlencode($t['id']); ?>">Open</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<script>
  const labels = <?php echo json_encode($labels); ?>;
  const data = <?php echo json_encode($series); ?>;
  const roleLabels = <?php echo json_encode(array_values(array_map(static fn($r) => (string)$r['role'], $roles))); ?>;
  const roleCounts = <?php echo json_encode(array_values(array_map(static fn($r) => (int)$r['cnt'], $roles))); ?>;
  const tenantLabels = <?php echo json_encode(array_values(array_map(static fn($t) => (string)$t['institution_name'], $top_tenants))); ?>;
  const tenantCounts = <?php echo json_encode(array_values(array_map(static fn($t) => (int)$t['user_count'], $top_tenants))); ?>;

  new Chart(document.getElementById('growthChart'), {
    type: 'line',
    data: {
      labels,
      datasets: [{
        label: 'New Users',
        data,
        borderColor: '#4f46e5',
        tension: 0.25,
        fill: false
      }]
    },
    options: {
      responsive: true,
      plugins: {
        legend: {
          display: true
        }
      }
    }
  });

  if (roleLabels.length > 0) {
    new Chart(document.getElementById('roleChart'), {
      type: 'pie',
      data: {
        labels: roleLabels,
        datasets: [{
          data: roleCounts,
          backgroundColor: ['#4f46e5', '#0ea5e9', '#10b981', '#f59e0b', '#ef4444', '#6b7280', '#8b5cf6']
        }]
      },
      options: {
        responsive: true,
        plugins: {
          legend: {
            position: 'bottom'
          }
        }
      }
    });
  }

  if (tenantLabels.length > 0) {
    new Chart(document.getElementById('tenantBarChart'), {
      type: 'bar',
      data: {
        labels: tenantLabels,
        datasets: [{
          label: 'Users',
          data: tenantCounts,
          backgroundColor: '#4f46e5'
        }]
      },
      options: {
        responsive: true,
        scales: {
          y: {
            beginAtZero: true
          }
        },
        plugins: {
          legend: {
            display: false
          }
        }
      }
    });
  }
</script>
<?php
$page_content = ob_get_clean();
require BASE_PATH . '/resources/ui-core/layouts/master-dashboard.php';
