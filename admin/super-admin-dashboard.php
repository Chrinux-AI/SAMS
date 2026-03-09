<?php

/**
 * Super Admin Dashboard - Multi-Tenant Management
 * Complete platform oversight and tenant administration
 */

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_once '../includes/sams-multi-tenant.php';

// Only admins can access this dashboard
$_user_role = $_SESSION['role'] ?? $_SESSION['user_role'] ?? '';
if (!in_array($_user_role, ['admin', 'super_admin', 'superadmin', 'owner'])) {
    header('Location: ../login.php');
    exit;
}

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
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <script src="../assets/js/theme-loader.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Dashboard - SAMS Platform</title>
    <link rel="manifest" href="/attendance/manifest.json">
    <meta name="theme-color" content="#4F46E5">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="../assets/css/professional-ui.css" rel="stylesheet">
    <style>
        .platform-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #4F46E5;
        }

        .stat-number {
            font-size: 32px;
            font-weight: 700;
            color: #4F46E5;
            margin-bottom: 8px;
        }

        .stat-label {
            color: #6b7280;
            font-size: 14px;
            font-weight: 500;
        }

        .tenant-table {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .tenant-table table {
            width: 100%;
            border-collapse: collapse;
        }

        .tenant-table th {
            background: #f9fafb;
            padding: 16px;
            text-align: left;
            font-weight: 600;
            color: #374151;
            border-bottom: 1px solid #e5e7eb;
        }

        .tenant-table td {
            padding: 16px;
            border-bottom: 1px solid #f3f4f6;
        }

        .tenant-table tr:hover {
            background: #f9fafb;
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-active {
            background: #d1fae5;
            color: #065f46;
        }

        .status-setup {
            background: #fef3c7;
            color: #92400e;
        }

        .status-suspended {
            background: #fee2e2;
            color: #991b1b;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .btn-primary {
            background: #4F46E5;
            color: white;
        }

        .btn-secondary {
            background: #6b7280;
            color: white;
        }

        .btn-danger {
            background: #dc2626;
            color: white;
        }

        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .quick-action-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            text-align: center;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .quick-action-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
        }

        .quick-action-icon {
            font-size: 32px;
            color: #4F46E5;
            margin-bottom: 12px;
        }

        .quick-action-title {
            font-weight: 600;
            margin-bottom: 8px;
        }

        .quick-action-desc {
            font-size: 14px;
            color: #6b7280;
        }
    </style>
</head>

<body>
    <div class="app-layout">
        <?php include '../includes/sidebar-nav.php'; ?>

        <main class="main-content">
            <header class="top-header">
                <div class="page-title-area">
                    <button class="mobile-toggle" onclick="document.querySelector('.sidebar').classList.toggle('active'); document.querySelector('.sidebar-overlay').classList.toggle('active');">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div class="page-icon"><i class="fas fa-globe"></i></div>
                    <div>
                        <h1>Super Admin Dashboard</h1>
                        <p class="page-subtitle">Multi-Tenant Platform Management</p>
                    </div>
                </div>
                <div class="header-actions">
                    <div class="datetime-display">
                        <div class="date-text"><?php echo date('l, M j, Y'); ?></div>
                        <div class="time-text" id="live-time"><?php echo date('h:i A'); ?></div>
                    </div>
                </div>
            </header>

            <div class="content-area">
                <!-- Platform Statistics -->
                <div class="platform-stats">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $total_tenants; ?></div>
                        <div class="stat-label">Total Schools</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $active_tenants; ?></div>
                        <div class="stat-label">Active Schools</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $total_users; ?></div>
                        <div class="stat-label">Total Users</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $pending_tenants; ?></div>
                        <div class="stat-label">Pending Setup</div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="quick-actions">
                    <div class="quick-action-card" onclick="window.location.href='create-tenant.php'">
                        <div class="quick-action-icon"><i class="fas fa-plus-circle"></i></div>
                        <div class="quick-action-title">Add New School</div>
                        <div class="quick-action-desc">Register a new institution</div>
                    </div>
                    <div class="quick-action-card" onclick="window.location.href='platform-analytics.php'">
                        <div class="quick-action-icon"><i class="fas fa-chart-line"></i></div>
                        <div class="quick-action-title">Platform Analytics</div>
                        <div class="quick-action-desc">View platform-wide insights</div>
                    </div>
                    <div class="quick-action-card" onclick="window.location.href='system-health.php'">
                        <div class="quick-action-icon"><i class="fas fa-heartbeat"></i></div>
                        <div class="quick-action-title">System Health</div>
                        <div class="quick-action-desc">Monitor platform performance</div>
                    </div>
                    <div class="quick-action-card" onclick="window.location.href='platform-settings.php'">
                        <div class="quick-action-icon"><i class="fas fa-cogs"></i></div>
                        <div class="quick-action-title">Platform Settings</div>
                        <div class="quick-action-desc">Configure global settings</div>
                    </div>
                </div>

                <!-- Recent Schools -->
                <div class="tenant-table">
                    <h3 style="padding: 20px; margin: 0;">Recent Schools</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>School Name</th>
                                <th>Subdomain</th>
                                <th>Status</th>
                                <th>Users</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_tenants as $tenant): ?>
                                <?php
                                $user_count = db()->fetchOne(
                                    "SELECT COUNT(*) as count FROM users WHERE tenant_id = ?",
                                    [$tenant['id']]
                                )['count'] ?? 0;
                                ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($tenant['institution_name']); ?></strong>
                                        <?php if ($tenant['is_default']): ?>
                                            <span style="background: #fef3c7; color: #92400e; padding: 2px 8px; border-radius: 12px; font-size: 11px; margin-left: 8px;">DEFAULT</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo $tenant['subdomain']; ?>.sams.com
                                        <?php if ($tenant['custom_domain']): ?>
                                            <br><small style="color: #6b7280;"><?php echo $tenant['custom_domain']; ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo $tenant['status']; ?>">
                                            <?php echo ucfirst($tenant['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo $user_count; ?></td>
                                    <td><?php echo date('M j, Y', strtotime($tenant['created_at'])); ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn-sm btn-primary" onclick="switchToTenant('<?php echo $tenant['id']; ?>')">
                                                <i class="fas fa-sign-in-alt"></i> Access
                                            </button>
                                            <button class="btn-sm btn-secondary" onclick="viewTenant('<?php echo $tenant['id']; ?>')">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Update live time
        function updateTime() {
            const now = new Date();
            document.getElementById('live-time').textContent = now.toLocaleTimeString('en-US', {
                hour: 'numeric',
                minute: '2-digit',
                hour12: true
            });
        }

        setInterval(updateTime, 1000);

        // Switch to tenant context
        function switchToTenant(tenantId) {
            if (confirm('Switch to this tenant context? You will be able to manage this school as if you were their admin.')) {
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
                            window.location.reload();
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
</body>

</html>
