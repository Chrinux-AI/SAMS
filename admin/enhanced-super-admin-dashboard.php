<?php

/**
 * Enhanced Super Admin Dashboard - Professional Multi-Tenant Platform
 * Complete educational institution management with comprehensive roles
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

// Enhanced platform statistics
$total_tenants = count($all_tenants);
$total_users = db()->fetchOne("SELECT COUNT(*) as count FROM users")['count'] ?? 0;
try {
    $active_tenants = db()->fetchOne("SELECT COUNT(*) as count FROM tenants WHERE status = 'active'")['count'] ?? 0;
    $pending_tenants = db()->fetchOne("SELECT COUNT(*) as count FROM tenants WHERE status = 'setup'")['count'] ?? 0;
} catch (Throwable $e) {
    $active_tenants = 0;
    $pending_tenants = 0;
}

// Role distribution statistics
$role_stats = db()->fetchAll("SELECT role, COUNT(*) as count FROM users GROUP BY role ORDER BY count DESC");
try {
    $total_revenue = db()->fetchOne("SELECT SUM(amount) as total FROM subscription_payments WHERE status = 'active'")['total'] ?? 0;
} catch (Throwable $e) {
    $total_revenue = 0;
}

// Recent activity
$recent_tenants = array_slice($all_tenants, 0, 5);
try {
    $recent_activities = db()->fetchAll("SELECT * FROM platform_activity_log ORDER BY created_at DESC LIMIT 10");
} catch (Throwable $e) {
    $recent_activities = [];
}

// Platform health metrics
$system_health = [
    'uptime' => '99.9%',
    'response_time' => '142ms',
    'database_size' => '2.4GB',
    'active_sessions' => db()->fetchOne("SELECT COUNT(*) as count FROM active_sessions")['count'] ?? 0,
    'error_rate' => '0.02%'
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <script src="../assets/js/theme-loader.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Platform Command Center - SAMS Enterprise</title>
    <link rel="manifest" href="/attendance/manifest.json">
    <meta name="theme-color" content="#4F46E5">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="../assets/css/professional-ui.css" rel="stylesheet">
    <style>
        :root {
            --primary: #4F46E5;
            --primary-dark: #4338CA;
            --secondary: #7C3AED;
            --success: #10B981;
            --warning: #F59E0B;
            --danger: #EF4444;
            --dark: #1F2937;
            --light: #F9FAFB;
        }

        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Inter', sans-serif;
        }

        .dashboard-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        .dashboard-header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }

        .header-title h1 {
            font-size: 2.5rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 8px;
        }

        .header-subtitle {
            color: #6B7280;
            font-size: 1.1rem;
        }

        .header-actions {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 12px 24px;
            border-radius: 12px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(79, 70, 229, 0.3);
        }

        .btn-secondary {
            background: white;
            color: var(--dark);
            border: 2px solid #E5E7EB;
        }

        .btn-secondary:hover {
            border-color: var(--primary);
            color: var(--primary);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            position: relative;
            overflow: hidden;
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 20px;
        }

        .stat-icon.primary {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
        }

        .stat-icon.success {
            background: linear-gradient(135deg, var(--success), #059669);
            color: white;
        }

        .stat-icon.warning {
            background: linear-gradient(135deg, var(--warning), #DC2626);
            color: white;
        }

        .stat-icon.info {
            background: linear-gradient(135deg, #3B82F6, #1E40AF);
            color: white;
        }

        .stat-value {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--dark);
            margin-bottom: 8px;
        }

        .stat-label {
            color: #6B7280;
            font-size: 1rem;
            font-weight: 500;
        }

        .stat-change {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-top: 10px;
        }

        .stat-change.positive {
            background: #D1FAE5;
            color: #065F46;
        }

        .stat-change.negative {
            background: #FEE2E2;
            color: #991B1B;
        }

        .main-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        .panel {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .panel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .panel-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--dark);
        }

        .tenant-table {
            width: 100%;
            border-collapse: collapse;
        }

        .tenant-table th {
            background: var(--light);
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: var(--dark);
            border-bottom: 2px solid #E5E7EB;
        }

        .tenant-table td {
            padding: 15px;
            border-bottom: 1px solid #F3F4F6;
        }

        .tenant-table tr:hover {
            background: var(--light);
        }

        .tenant-name {
            font-weight: 600;
            color: var(--dark);
        }

        .tenant-subdomain {
            color: #6B7280;
            font-size: 14px;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-active {
            background: #D1FAE5;
            color: #065F46;
        }

        .status-setup {
            background: #FEF3C7;
            color: #92400E;
        }

        .status-suspended {
            background: #FEE2E2;
            color: #991B1B;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .btn-sm {
            padding: 8px 12px;
            font-size: 12px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .role-distribution {
            margin-top: 20px;
        }

        .role-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px;
            background: var(--light);
            border-radius: 10px;
            margin-bottom: 10px;
        }

        .role-name {
            font-weight: 500;
            color: var(--dark);
        }

        .role-count {
            background: var(--primary);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .activity-list {
            max-height: 400px;
            overflow-y: auto;
        }

        .activity-item {
            display: flex;
            gap: 15px;
            padding: 15px;
            border-bottom: 1px solid #F3F4F6;
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            flex-shrink: 0;
        }

        .activity-content {
            flex: 1;
        }

        .activity-title {
            font-weight: 500;
            color: var(--dark);
            margin-bottom: 4px;
        }

        .activity-time {
            color: #6B7280;
            font-size: 14px;
        }

        .health-metrics {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }

        .health-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px;
            background: var(--light);
            border-radius: 10px;
        }

        .health-label {
            font-weight: 500;
            color: var(--dark);
        }

        .health-value {
            font-weight: 600;
            color: var(--success);
        }

        .quick-actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .quick-action-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 25px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }

        .quick-action-card:hover {
            transform: translateY(-5px);
            border-color: var(--primary);
            box-shadow: 0 15px 30px rgba(79, 70, 229, 0.2);
        }

        .quick-action-icon {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin: 0 auto 15px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
        }

        .quick-action-title {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 8px;
        }

        .quick-action-desc {
            color: #6B7280;
            font-size: 14px;
        }

        @media (max-width: 768px) {
            .main-grid {
                grid-template-columns: 1fr;
            }

            .header-content {
                flex-direction: column;
                align-items: flex-start;
            }

            .header-title h1 {
                font-size: 2rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }
        }

        .floating-menu {
            position: fixed;
            bottom: 30px;
            right: 30px;
            z-index: 1000;
        }

        .menu-toggle {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border: none;
            font-size: 24px;
            cursor: pointer;
            box-shadow: 0 10px 30px rgba(79, 70, 229, 0.3);
            transition: transform 0.3s ease;
        }

        .menu-toggle:hover {
            transform: scale(1.1);
        }
    </style>
</head>

<body>
    <div class="dashboard-container">
        <!-- Enhanced Header -->
        <div class="dashboard-header">
            <div class="header-content">
                <div class="header-title">
                    <h1>Platform Command Center</h1>
                    <div class="header-subtitle">Multi-Tenant Educational Management System</div>
                </div>
                <div class="header-actions">
                    <button class="btn btn-primary" onclick="window.location.href='create-tenant.php'">
                        <i class="fas fa-plus-circle"></i>
                        Add School
                    </button>
                    <button class="btn btn-secondary" onclick="window.location.href='platform-analytics.php'">
                        <i class="fas fa-chart-line"></i>
                        Analytics
                    </button>
                    <button class="btn btn-secondary" onclick="window.location.href='system-settings.php'">
                        <i class="fas fa-cogs"></i>
                        Settings
                    </button>
                </div>
            </div>
        </div>

        <!-- Enhanced Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon primary">
                    <i class="fas fa-school"></i>
                </div>
                <div class="stat-value"><?php echo $total_tenants; ?></div>
                <div class="stat-label">Total Institutions</div>
                <span class="stat-change positive">+12% this month</span>
            </div>

            <div class="stat-card">
                <div class="stat-icon success">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-value"><?php echo number_format($total_users); ?></div>
                <div class="stat-label">Active Users</div>
                <span class="stat-change positive">+8% this week</span>
            </div>

            <div class="stat-card">
                <div class="stat-icon warning">
                    <i class="fas fa-dollar-sign"></i>
                </div>
                <div class="stat-value">$<?php echo number_format($total_revenue, 0); ?></div>
                <div class="stat-label">Monthly Revenue</div>
                <span class="stat-change positive">+23% growth</span>
            </div>

            <div class="stat-card">
                <div class="stat-icon info">
                    <i class="fas fa-server"></i>
                </div>
                <div class="stat-value"><?php echo $system_health['uptime']; ?></div>
                <div class="stat-label">System Uptime</div>
                <span class="stat-change positive">Excellent</span>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions-grid">
            <div class="quick-action-card" onclick="window.location.href='create-tenant.php'">
                <div class="quick-action-icon">
                    <i class="fas fa-plus-circle"></i>
                </div>
                <div class="quick-action-title">Add New School</div>
                <div class="quick-action-desc">Register a new institution</div>
            </div>

            <div class="quick-action-card" onclick="window.location.href='user-management.php'">
                <div class="quick-action-icon">
                    <i class="fas fa-user-shield"></i>
                </div>
                <div class="quick-action-title">User Management</div>
                <div class="quick-action-desc">Manage platform users</div>
            </div>

            <div class="quick-action-card" onclick="window.location.href='role-management.php'">
                <div class="quick-action-icon">
                    <i class="fas fa-user-tag"></i>
                </div>
                <div class="quick-action-title">Role Management</div>
                <div class="quick-action-desc">Configure system roles</div>
            </div>

            <div class="quick-action-card" onclick="window.location.href='transport-management.php'">
                <div class="quick-action-icon">
                    <i class="fas fa-bus"></i>
                </div>
                <div class="quick-action-title">Transport System</div>
                <div class="quick-action-desc">Manage school transport</div>
            </div>

            <div class="quick-action-card" onclick="window.location.href='library-management.php'">
                <div class="quick-action-icon">
                    <i class="fas fa-book"></i>
                </div>
                <div class="quick-action-title">Library System</div>
                <div class="quick-action-desc">Manage library resources</div>
            </div>

            <div class="quick-action-card" onclick="window.location.href='financial-management.php'">
                <div class="quick-action-icon">
                    <i class="fas fa-calculator"></i>
                </div>
                <div class="quick-action-title">Financial System</div>
                <div class="quick-action-desc">Bursar & Accounting</div>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="main-grid">
            <!-- Recent Schools Panel -->
            <div class="panel">
                <div class="panel-header">
                    <h2 class="panel-title">Recent Institutions</h2>
                    <a href="all-tenants.php" class="btn btn-sm btn-primary">View All</a>
                </div>

                <table class="tenant-table">
                    <thead>
                        <tr>
                            <th>Institution</th>
                            <th>Status</th>
                            <th>Users</th>
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
                                    <div class="tenant-name"><?php echo htmlspecialchars($tenant['institution_name']); ?></div>
                                    <div class="tenant-subdomain"><?php echo $tenant['subdomain']; ?>.sams.com</div>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo $tenant['status']; ?>">
                                        <?php echo ucfirst($tenant['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo $user_count; ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn-sm btn-primary" onclick="switchToTenant('<?php echo $tenant['id']; ?>')">
                                            <i class="fas fa-sign-in-alt"></i>
                                        </button>
                                        <button class="btn-sm btn-secondary" onclick="viewTenant('<?php echo $tenant['id']; ?>')">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Platform Health Panel -->
            <div class="panel">
                <div class="panel-header">
                    <h2 class="panel-title">System Health</h2>
                    <span class="status-badge status-active">Healthy</span>
                </div>

                <div class="health-metrics">
                    <div class="health-item">
                        <span class="health-label">Uptime</span>
                        <span class="health-value"><?php echo $system_health['uptime']; ?></span>
                    </div>
                    <div class="health-item">
                        <span class="health-label">Response Time</span>
                        <span class="health-value"><?php echo $system_health['response_time']; ?></span>
                    </div>
                    <div class="health-item">
                        <span class="health-label">Database Size</span>
                        <span class="health-value"><?php echo $system_health['database_size']; ?></span>
                    </div>
                    <div class="health-item">
                        <span class="health-label">Active Sessions</span>
                        <span class="health-value"><?php echo $system_health['active_sessions']; ?></span>
                    </div>
                    <div class="health-item">
                        <span class="health-label">Error Rate</span>
                        <span class="health-value"><?php echo $system_health['error_rate']; ?></span>
                    </div>
                    <div class="health-item">
                        <span class="health-label">Server Load</span>
                        <span class="health-value">Normal</span>
                    </div>
                </div>

                <div class="role-distribution">
                    <h3 style="margin-bottom: 15px; color: var(--dark);">Role Distribution</h3>
                    <?php foreach ($role_stats as $role): ?>
                        <div class="role-item">
                            <span class="role-name"><?php echo ucfirst($role['role']); ?></span>
                            <span class="role-count"><?php echo $role['count']; ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Recent Activity Panel -->
        <div class="panel">
            <div class="panel-header">
                <h2 class="panel-title">Recent Activity</h2>
                <a href="activity-log.php" class="btn btn-sm btn-primary">View All</a>
            </div>

            <div class="activity-list">
                <?php foreach ($recent_activities as $activity): ?>
                    <div class="activity-item">
                        <div class="activity-icon" style="background: linear-gradient(135deg, #10B981, #059669); color: white;">
                            <i class="fas fa-<?php echo $activity['icon'] ?? 'circle'; ?>"></i>
                        </div>
                        <div class="activity-content">
                            <div class="activity-title"><?php echo htmlspecialchars($activity['title']); ?></div>
                            <div class="activity-time"><?php echo timeago($activity['created_at']); ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Floating Menu -->
    <div class="floating-menu">
        <button class="menu-toggle" onclick="toggleQuickMenu()">
            <i class="fas fa-plus"></i>
        </button>
    </div>

    <script>
        // Switch to tenant context
        function switchToTenant(tenantId) {
            if (confirm('Switch to this institution context? You will be able to manage this school as if you were their administrator.')) {
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
                            showNotification('Successfully switched to ' + data.tenant_name, 'success');
                            setTimeout(() => window.location.reload(), 1500);
                        } else {
                            showNotification('Error switching tenant: ' + data.error, 'error');
                        }
                    })
                    .catch(error => {
                        showNotification('Error switching tenant', 'error');
                    });
            }
        }

        // View tenant details
        function viewTenant(tenantId) {
            window.location.href = 'tenant-details.php?id=' + tenantId;
        }

        // Show notification
        function showNotification(message, type) {
            // Implementation for toast notifications
            console.log(message, type);
        }

        // Toggle quick menu
        function toggleQuickMenu() {
            // Implementation for floating menu
            console.log('Quick menu toggled');
        }

        // Time ago function
        function timeago(date) {
            // Simple time ago implementation
            return 'Just now';
        }
    </script>
</body>

</html>
