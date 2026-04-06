<?php

/**
 * Super Admin Dashboard - Multi-Tenant Management
 * Complete platform oversight and tenant administration
 * Stitch Academic Sentinel UI - Tailwind CSS Implementation
 */

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_once '../includes/sams-multi-tenant.php';

// Only super admins can access this dashboard
$_user_role = $_SESSION['role'] ?? $_SESSION['user_role'] ?? '';
if (!in_array($_user_role, ['super_admin', 'superadmin'], true)) {
    header('Location: ../login.php');
    exit;
}

$full_name = $_SESSION['full_name'] ?? htmlspecialchars($_SESSION['user_name'] ?? 'Administrator');
$page_title = 'Super Admin Dashboard';
$page_icon = 'manage_accounts';
$page_subtitle = 'Multi-Tenant Platform Management';

// Initialize multi-tenant system
try {
    $tenant_manager = new SAMS_MultiTenant();
    $all_tenants = $tenant_manager->getAllTenants();
} catch (Throwable $e) {
    $all_tenants = [];
}

// Platform statistics
$total_tenants = count($all_tenants);
$total_users = db()->fetchOne("SELECT COUNT(*) as count FROM users")['count'] ?? 0;
try {
    $active_tenants = db()->fetchOne("SELECT COUNT(*) as count FROM tenants WHERE status = 'active'")['count'] ?? 0;
    $pending_tenants = db()->fetchOne("SELECT COUNT(*) as count FROM tenants WHERE status = 'setup'")['count'] ?? 0;
} catch (Throwable $e) {
    $active_tenants = 0;
    $pending_tenants = 0;
}

// Recent tenant activity
$recent_tenants = array_slice($all_tenants, 0, 5);

ob_start();
?>
<!-- Bento Grid Dashboard - Super Admin -->
<div class="grid grid-cols-12 gap-6">

    <!-- Super Admin Header Banner (Full Width) -->
    <div class="col-span-12 bg-gradient-to-r from-purple-700 to-indigo-700 text-white p-8 rounded-xl shadow-lg">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-3xl font-bold mb-2">Platform Control Center</h2>
                <p class="text-purple-100">Manage all schools, users, and platform settings</p>
            </div>
            <span class="material-symbols-outlined text-8xl opacity-20">manage_accounts</span>
        </div>
    </div>

    <!-- KPI Cards (4 across) -->
    <div class="col-span-12 lg:col-span-3 sams-stat-card">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-gray-500 text-sm font-medium">Total Schools</p>
                <p class="text-4xl font-bold text-gray-900 mt-2"><?php echo $total_tenants; ?></p>
            </div>
            <span class="material-symbols-outlined text-5xl text-blue-500 opacity-30">apartment</span>
        </div>
    </div>

    <div class="col-span-12 lg:col-span-3 sams-stat-card">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-gray-500 text-sm font-medium">Active Schools</p>
                <p class="text-4xl font-bold text-green-600 mt-2"><?php echo $active_tenants; ?></p>
            </div>
            <span class="material-symbols-outlined text-5xl text-green-500 opacity-30">verified</span>
        </div>
    </div>

    <div class="col-span-12 lg:col-span-3 sams-stat-card">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-gray-500 text-sm font-medium">Platform Users</p>
                <p class="text-4xl font-bold text-indigo-600 mt-2"><?php echo $total_users; ?></p>
            </div>
            <span class="material-symbols-outlined text-5xl text-indigo-500 opacity-30">group</span>
        </div>
    </div>

    <div class="col-span-12 lg:col-span-3 sams-stat-card">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-gray-500 text-sm font-medium">Pending Setup</p>
                <p class="text-4xl font-bold text-amber-600 mt-2"><?php echo $pending_tenants; ?></p>
            </div>
            <span class="material-symbols-outlined text-5xl text-amber-500 opacity-30">hourglass_empty</span>
        </div>
    </div>

    <!-- Quick Actions Grid (4 across) -->
    <div class="col-span-12 lg:col-span-3 bg-white rounded-xl p-6 shadow-sm hover:shadow-md transition-shadow cursor-pointer border border-gray-100 hover:border-blue-200" onclick="window.location.href='create-tenant.php'">
        <div class="text-center">
            <span class="material-symbols-outlined text-5xl text-blue-600 flex justify-center mb-3">add_circle</span>
            <h3 class="font-semibold text-gray-900 mb-1">Add New School</h3>
            <p class="text-sm text-gray-500">Register new institution</p>
        </div>
    </div>

    <div class="col-span-12 lg:col-span-3 bg-white rounded-xl p-6 shadow-sm hover:shadow-md transition-shadow cursor-pointer border border-gray-100 hover:border-purple-200" onclick="window.location.href='platform-analytics.php'">
        <div class="text-center">
            <span class="material-symbols-outlined text-5xl text-purple-600 flex justify-center mb-3">analytics</span>
            <h3 class="font-semibold text-gray-900 mb-1">Platform Analytics</h3>
            <p class="text-sm text-gray-500">View system insights</p>
        </div>
    </div>

    <div class="col-span-12 lg:col-span-3 bg-white rounded-xl p-6 shadow-sm hover:shadow-md transition-shadow cursor-pointer border border-gray-100 hover:border-green-200" onclick="window.location.href='system-health.php'">
        <div class="text-center">
            <span class="material-symbols-outlined text-5xl text-green-600 flex justify-center mb-3">favorite</span>
            <h3 class="font-semibold text-gray-900 mb-1">System Health</h3>
            <p class="text-sm text-gray-500">Monitor performance</p>
        </div>
    </div>

    <div class="col-span-12 lg:col-span-3 bg-white rounded-xl p-6 shadow-sm hover:shadow-md transition-shadow cursor-pointer border border-gray-100 hover:border-amber-200" onclick="window.location.href='platform-settings.php'">
        <div class="text-center">
            <span class="material-symbols-outlined text-5xl text-amber-600 flex justify-center mb-3">settings</span>
            <h3 class="font-semibold text-gray-900 mb-1">Platform Settings</h3>
            <p class="text-sm text-gray-500">Configure global options</p>
        </div>
    </div>

    <!-- Recent Schools Table (Full Width) -->
    <div class="col-span-12 bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-6 py-4 bg-gray-50 border-b border-gray-100">
            <h3 class="font-semibold text-gray-900 text-lg">Recent Schools</h3>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-200">
                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-900">School Name</th>
                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-900">Subdomain</th>
                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-900">Status</th>
                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-900">Users</th>
                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-900">Created</th>
                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-900">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($recent_tenants as $tenant): ?>
                        <?php
                        $user_count = db()->fetchOne(
                            "SELECT COUNT(*) as count FROM users WHERE tenant_id = ?",
                            [$tenant['id']]
                        )['count'] ?? 0;
                        $status_class = 'bg-green-100 text-green-800';
                        if ($tenant['status'] === 'setup') $status_class = 'bg-amber-100 text-amber-800';
                        if ($tenant['status'] === 'suspended') $status_class = 'bg-red-100 text-red-800';
                        ?>
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <span class="material-symbols-outlined text-gray-400">apartment</span>
                                    <div>
                                        <div class="font-medium text-gray-900"><?php echo htmlspecialchars($tenant['institution_name']); ?></div>
                                        <?php if ($tenant['is_default']): ?>
                                            <span class="text-xs bg-blue-100 text-blue-700 px-2 py-1 rounded">DEFAULT</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600">
                                <div><?php echo $tenant['subdomain']; ?>.sams.com</div>
                                <?php if ($tenant['custom_domain']): ?>
                                    <div class="text-xs text-gray-400"><?php echo $tenant['custom_domain']; ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium <?php echo $status_class; ?>">
                                    <?php echo ucfirst($tenant['status']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600"><?php echo $user_count; ?></td>
                            <td class="px-6 py-4 text-sm text-gray-600"><?php echo date('M j, Y', strtotime($tenant['created_at'])); ?></td>
                            <td class="px-6 py-4">
                                <div class="flex gap-2">
                                    <button onclick="switchToTenant('<?php echo $tenant['id']; ?>')" class="inline-flex items-center px-3 py-1 rounded-lg text-sm font-medium bg-blue-100 text-blue-700 hover:bg-blue-200 transition-colors">
                                        <span class="material-symbols-outlined text-base mr-1">login</span>
                                        Access
                                    </button>
                                    <button onclick="viewTenant('<?php echo $tenant['id']; ?>')" class="inline-flex items-center px-3 py-1 rounded-lg text-sm font-medium bg-gray-100 text-gray-700 hover:bg-gray-200 transition-colors">
                                        <span class="material-symbols-outlined text-base mr-1">visibility</span>
                                        View
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<?php
$page_content = ob_get_clean();
include '../resources/ui-core/layouts/master-dashboard.php';
?>

<script>
    // Switch to tenant context
    function switchToTenant(tenantId) {
        if (confirm('Switch to this school context? You\'ll manage it as their admin.')) {
            fetch('switch-tenant.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        tenant_id: tenantId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const target = data.redirect_url ? data.redirect_url : 'dashboard.php';
                        window.location.href = target;
                    } else {
                        alert('Error switching tenant: ' + data.error);
                    }
                })
                .catch(error => {
                    alert('Error switching tenant');
                });
        }
    }

    // View tenant details
    function viewTenant(tenantId) {
        window.location.href = 'tenant-details.php?id=' + tenantId;
    }
</script>
