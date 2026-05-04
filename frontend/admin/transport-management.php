<?php

/**
 * Transport Management System
 * Comprehensive school transport management with routes, vehicles, and driver management
 */

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Only admins can access this
$_user_role = $_SESSION['role'] ?? $_SESSION['user_role'] ?? '';
if (!in_array($_user_role, ['admin', 'super_admin', 'superadmin', 'owner', 'transport'])) {
    header('Location: ../login.php');
    exit;
}

function transport_table_has_tenant_column(string $table): bool
{
    try {
        return (bool)db()->fetchOne("SHOW COLUMNS FROM {$table} LIKE 'tenant_id'");
    } catch (Throwable $e) {
        return false;
    }
}

$is_super_admin = in_array($_user_role, ['super_admin', 'superadmin'], true);
$tenant_filter = (int)($_GET['tenant_id'] ?? ($_SESSION['tenant_id'] ?? 0));
$transport_multi_tenant_ready = transport_table_has_tenant_column('transport_routes')
    && transport_table_has_tenant_column('transport_vehicles')
    && transport_table_has_tenant_column('transport_drivers')
    && transport_table_has_tenant_column('transport_assignments');

if (!$is_super_admin && $tenant_filter <= 0) {
    $tenant_filter = (int)($_SESSION['tenant_id'] ?? 0);
}

$tenants = [];
if ($is_super_admin && table_exists('tenants')) {
    $tenants = db()->fetchAll("SELECT id, institution_name FROM tenants ORDER BY institution_name") ?: [];
}

$tenantWhere = '';
$tenantParams = [];
if ($transport_multi_tenant_ready && $tenant_filter > 0) {
    $tenantWhere = ' WHERE tenant_id = ? ';
    $tenantParams[] = $tenant_filter;
}

// Get transport statistics
$total_routes = db()->fetchOne("SELECT COUNT(*) as count FROM transport_routes" . $tenantWhere, $tenantParams)['count'] ?? 0;
$total_vehicles = db()->fetchOne("SELECT COUNT(*) as count FROM transport_vehicles" . $tenantWhere, $tenantParams)['count'] ?? 0;
$total_drivers = db()->fetchOne("SELECT COUNT(*) as count FROM transport_drivers" . $tenantWhere, $tenantParams)['count'] ?? 0;
$total_students = db()->fetchOne("SELECT COUNT(*) as count FROM transport_assignments" . $tenantWhere, $tenantParams)['count'] ?? 0;

// Get recent transport data
$recent_routes = db()->fetchAll("SELECT * FROM transport_routes" . $tenantWhere . " ORDER BY created_at DESC LIMIT 5", $tenantParams);
$vehicles = db()->fetchAll("SELECT * FROM transport_vehicles" . $tenantWhere . " ORDER BY vehicle_name LIMIT 10", $tenantParams);
$drivers = db()->fetchAll("SELECT * FROM transport_drivers" . $tenantWhere . " ORDER BY driver_name LIMIT 10", $tenantParams);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        header('Location: transport-management.php?error=csrf_failed');
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'add_route') {
        $route_data = [
            'route_name' => $_POST['route_name'],
            'route_number' => $_POST['route_number'],
            'start_point' => $_POST['start_point'],
            'end_point' => $_POST['end_point'],
            'waypoints' => json_encode(explode(',', $_POST['waypoints'] ?? '')),
            'distance_km' => $_POST['distance_km'],
            'estimated_time' => $_POST['estimated_time'],
            'fare_amount' => $_POST['fare_amount'],
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s')
        ];
        if ($transport_multi_tenant_ready && $tenant_filter > 0) {
            $route_data['tenant_id'] = $tenant_filter;
        }

        db()->insert('transport_routes', $route_data);
        header('Location: transport-management.php?success=route_added');
        exit;
    }

    if ($action === 'add_vehicle') {
        $vehicle_data = [
            'vehicle_name' => $_POST['vehicle_name'],
            'vehicle_type' => $_POST['vehicle_type'],
            'registration_number' => $_POST['registration_number'],
            'capacity' => $_POST['capacity'],
            'driver_id' => $_POST['driver_id'] ?? null,
            'insurance_expiry' => $_POST['insurance_expiry'],
            'maintenance_date' => $_POST['maintenance_date'],
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s')
        ];
        if ($transport_multi_tenant_ready && $tenant_filter > 0) {
            $vehicle_data['tenant_id'] = $tenant_filter;
        }

        db()->insert('transport_vehicles', $vehicle_data);
        header('Location: transport-management.php?success=vehicle_added');
        exit;
    }

    if ($action === 'add_driver') {
        $driver_data = [
            'driver_name' => $_POST['driver_name'],
            'license_number' => $_POST['license_number'],
            'phone' => $_POST['phone'],
            'email' => $_POST['email'],
            'address' => $_POST['address'],
            'experience_years' => $_POST['experience_years'],
            'license_expiry' => $_POST['license_expiry'],
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s')
        ];
        if ($transport_multi_tenant_ready && $tenant_filter > 0) {
            $driver_data['tenant_id'] = $tenant_filter;
        }

        db()->insert('transport_drivers', $driver_data);
        header('Location: transport-management.php?success=driver_added');
        exit;
    }
}

// Success message
$success_message = '';
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'route_added':
            $success_message = 'Route added successfully!';
            break;
        case 'vehicle_added':
            $success_message = 'Vehicle added successfully!';
            break;
        case 'driver_added':
            $success_message = 'Driver added successfully!';
            break;
    }
}

$error_message = '';
if (isset($_GET['error']) && $_GET['error'] === 'csrf_failed') {
    $error_message = 'Security validation failed. Please retry your transport operation.';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <script src="../assets/js/theme-loader.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transport Management - SAMS Platform</title>
    <link rel="manifest" href="/attendance/manifest.json">
    <meta name="theme-color" content="#4F46E5">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="../assets/css/professional-ui.css" rel="stylesheet">
    <?php include '../includes/sams-head-bootstrap.php'; ?>

    <style>
        .transport-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        .page-header {
            background: linear-gradient(135deg, #F59E0B, #DC2626);
            color: white;
            padding: 40px;
            border-radius: 20px;
            margin-bottom: 30px;
            box-shadow: 0 20px 40px rgba(245, 158, 11, 0.3);
        }

        .page-title {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 10px;
        }

        .page-subtitle {
            opacity: 0.9;
            font-size: 1.1rem;
        }

        .transport-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #F59E0B;
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin: 0 auto 15px;
            background: linear-gradient(135deg, #F59E0B, #DC2626);
            color: white;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #1F2937;
            margin-bottom: 8px;
        }

        .stat-label {
            color: #6B7280;
            font-weight: 500;
        }

        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        .panel {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
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
            color: #1F2937;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #374151;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #D1D5DB;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.2s;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #F59E0B;
            box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.1);
        }

        .btn {
            padding: 12px 24px;
            border-radius: 8px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: #F59E0B;
            color: white;
        }

        .btn-primary:hover {
            background: #DC2626;
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: #6B7280;
            color: white;
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #D1FAE5;
            color: #065F46;
            border: 1px solid #A7F3D0;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th {
            background: #F9FAFB;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #374151;
            border-bottom: 2px solid #E5E7EB;
        }

        .data-table td {
            padding: 15px;
            border-bottom: 1px solid #F3F4F6;
        }

        .data-table tr:hover {
            background: #F9FAFB;
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

        .status-inactive {
            background: #FEE2E2;
            color: #991B1B;
        }

        .status-maintenance {
            background: #FEF3C7;
            color: #92400E;
        }

        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            border-bottom: 2px solid #E5E7EB;
        }

        .tab {
            padding: 12px 20px;
            background: none;
            border: none;
            font-weight: 600;
            color: #6B7280;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            transition: all 0.3s ease;
        }

        .tab.active {
            color: #F59E0B;
            border-bottom-color: #F59E0B;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        @media (max-width: 768px) {
            .content-grid {
                grid-template-columns: 1fr;
            }

            .transport-stats {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <div class="transport-container">
        <!-- Page Header -->
        <div class="page-header">
            <div class="page-title">Transport Management</div>
            <div class="page-subtitle">Comprehensive school transport system with routes, vehicles, and driver management</div>
            <div style="margin-top:12px;font-size:0.95rem;opacity:0.95;">
                Multi-tenant support: <?php echo $transport_multi_tenant_ready ? 'Enabled' : 'Limited (tenant column missing in one or more transport tables)'; ?>
            </div>
        </div>

        <?php if ($is_super_admin): ?>
            <div class="panel" style="margin-bottom:20px;">
                <form method="GET" style="display:flex;gap:12px;align-items:end;flex-wrap:wrap;">
                    <div>
                        <label style="display:block;margin-bottom:6px;color:#374151;font-weight:600;">Tenant Scope</label>
                        <select name="tenant_id" style="padding:10px 12px;border:1px solid #D1D5DB;border-radius:8px;min-width:280px;">
                            <option value="0">All Tenants</option>
                            <?php foreach ($tenants as $t): ?>
                                <option value="<?php echo (int)$t['id']; ?>" <?php echo $tenant_filter === (int)$t['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($t['institution_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button class="btn btn-primary" type="submit"><i class="fas fa-filter"></i> Apply Scope</button>
                </form>
            </div>
        <?php endif; ?>

        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo $success_message; ?>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert" style="background:#FEE2E2;color:#991B1B;border:1px solid #FCA5A5;">
                <i class="fas fa-exclamation-triangle"></i>
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <!-- Transport Statistics -->
        <div class="transport-stats">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-route"></i>
                </div>
                <div class="stat-value"><?php echo $total_routes; ?></div>
                <div class="stat-label">Active Routes</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-bus"></i>
                </div>
                <div class="stat-value"><?php echo $total_vehicles; ?></div>
                <div class="stat-label">Fleet Vehicles</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-id-card"></i>
                </div>
                <div class="stat-value"><?php echo $total_drivers; ?></div>
                <div class="stat-label">Licensed Drivers</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-value"><?php echo $total_students; ?></div>
                <div class="stat-label">Students Transported</div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="content-grid">
            <!-- Transport Forms -->
            <div class="panel">
                <div class="panel-header">
                    <h2 class="panel-title">Transport Operations</h2>
                </div>

                <!-- Tabs -->
                <div class="tabs">
                    <button class="tab active" onclick="showTab(event, 'routes')">Routes</button>
                    <button class="tab" onclick="showTab(event, 'vehicles')">Vehicles</button>
                    <button class="tab" onclick="showTab(event, 'drivers')">Drivers</button>
                </div>

                <!-- Routes Tab -->
                <div id="routes-tab" class="tab-content active">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
                        <input type="hidden" name="action" value="add_route">

                        <div class="form-group">
                            <label for="route_name">Route Name *</label>
                            <input type="text" id="route_name" name="route_name" required>
                        </div>

                        <div class="form-group">
                            <label for="route_number">Route Number *</label>
                            <input type="text" id="route_number" name="route_number" required>
                        </div>

                        <div class="form-group">
                            <label for="start_point">Start Point *</label>
                            <input type="text" id="start_point" name="start_point" required>
                        </div>

                        <div class="form-group">
                            <label for="end_point">End Point *</label>
                            <input type="text" id="end_point" name="end_point" required>
                        </div>

                        <div class="form-group">
                            <label for="waypoints">Waypoints (comma-separated)</label>
                            <input type="text" id="waypoints" name="waypoints" placeholder="Stop1, Stop2, Stop3">
                        </div>

                        <div class="form-group">
                            <label for="distance_km">Distance (km)</label>
                            <input type="number" id="distance_km" name="distance_km" step="0.1">
                        </div>

                        <div class="form-group">
                            <label for="estimated_time">Estimated Time (minutes)</label>
                            <input type="number" id="estimated_time" name="estimated_time">
                        </div>

                        <div class="form-group">
                            <label for="fare_amount">Fare Amount</label>
                            <input type="number" id="fare_amount" name="fare_amount" step="0.01">
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-plus"></i>
                            Add Route
                        </button>
                    </form>
                </div>

                <!-- Vehicles Tab -->
                <div id="vehicles-tab" class="tab-content">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
                        <input type="hidden" name="action" value="add_vehicle">

                        <div class="form-group">
                            <label for="vehicle_name">Vehicle Name *</label>
                            <input type="text" id="vehicle_name" name="vehicle_name" required>
                        </div>

                        <div class="form-group">
                            <label for="vehicle_type">Vehicle Type *</label>
                            <select id="vehicle_type" name="vehicle_type" required>
                                <option value="">Select Type</option>
                                <option value="bus">Bus</option>
                                <option value="van">Van</option>
                                <option value="minibus">Minibus</option>
                                <option value="car">Car</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="registration_number">Registration Number *</label>
                            <input type="text" id="registration_number" name="registration_number" required>
                        </div>

                        <div class="form-group">
                            <label for="capacity">Capacity *</label>
                            <input type="number" id="capacity" name="capacity" required>
                        </div>

                        <div class="form-group">
                            <label for="driver_id">Assigned Driver</label>
                            <select id="driver_id" name="driver_id">
                                <option value="">Select Driver</option>
                                <?php foreach ($drivers as $driver): ?>
                                    <option value="<?php echo $driver['id']; ?>">
                                        <?php echo htmlspecialchars($driver['driver_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="insurance_expiry">Insurance Expiry</label>
                            <input type="date" id="insurance_expiry" name="insurance_expiry">
                        </div>

                        <div class="form-group">
                            <label for="maintenance_date">Next Maintenance</label>
                            <input type="date" id="maintenance_date" name="maintenance_date">
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-plus"></i>
                            Add Vehicle
                        </button>
                    </form>
                </div>

                <!-- Drivers Tab -->
                <div id="drivers-tab" class="tab-content">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
                        <input type="hidden" name="action" value="add_driver">

                        <div class="form-group">
                            <label for="driver_name">Driver Name *</label>
                            <input type="text" id="driver_name" name="driver_name" required>
                        </div>

                        <div class="form-group">
                            <label for="license_number">License Number *</label>
                            <input type="text" id="license_number" name="license_number" required>
                        </div>

                        <div class="form-group">
                            <label for="phone">Phone Number *</label>
                            <input type="tel" id="phone" name="phone" required>
                        </div>

                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email">
                        </div>

                        <div class="form-group">
                            <label for="address">Address</label>
                            <textarea id="address" name="address" rows="3"></textarea>
                        </div>

                        <div class="form-group">
                            <label for="experience_years">Experience (Years)</label>
                            <input type="number" id="experience_years" name="experience_years" min="0">
                        </div>

                        <div class="form-group">
                            <label for="license_expiry">License Expiry</label>
                            <input type="date" id="license_expiry" name="license_expiry">
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-plus"></i>
                            Add Driver
                        </button>
                    </form>
                </div>
            </div>

            <!-- Recent Data -->
            <div class="panel">
                <div class="panel-header">
                    <h2 class="panel-title">Recent Transport Data</h2>
                    <a href="transport-reports.php" class="btn btn-primary">View All</a>
                </div>

                <!-- Recent Routes -->
                <h3 style="margin-bottom: 15px; color: #374151;">Recent Routes</h3>
                <table class="data-table" style="margin-bottom: 30px;">
                    <thead>
                        <tr>
                            <th>Route</th>
                            <th>Distance</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_routes as $route): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($route['route_name']); ?></strong>
                                    <br><small><?php echo htmlspecialchars($route['start_point']); ?> → <?php echo htmlspecialchars($route['end_point']); ?></small>
                                </td>
                                <td><?php echo $route['distance_km']; ?> km</td>
                                <td>
                                    <span class="status-badge status-<?php echo $route['status']; ?>">
                                        <?php echo ucfirst($route['status']); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Vehicle Status -->
                <h3 style="margin-bottom: 15px; color: #374151;">Vehicle Fleet</h3>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Vehicle</th>
                            <th>Type</th>
                            <th>Capacity</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($vehicles as $vehicle): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($vehicle['vehicle_name']); ?></strong>
                                    <br><small><?php echo htmlspecialchars($vehicle['registration_number']); ?></small>
                                </td>
                                <td><?php echo ucfirst($vehicle['vehicle_type']); ?></td>
                                <td><?php echo $vehicle['capacity']; ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $vehicle['status']; ?>">
                                        <?php echo ucfirst($vehicle['status']); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        function showTab(evt, tabName) {
            if (evt && typeof evt.preventDefault === 'function') {
                evt.preventDefault();
            }

            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });

            // Remove active class from all tab buttons
            document.querySelectorAll('.tab').forEach(btn => {
                btn.classList.remove('active');
            });

            // Show selected tab
            document.getElementById(tabName + '-tab').classList.add('active');

            // Add active class to clicked tab button
            if (evt && evt.currentTarget) {
                evt.currentTarget.classList.add('active');
            }
        }
    </script>
</body>

</html>
