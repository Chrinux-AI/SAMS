<?php

/**
 * SAMS Transport Dashboard - Modern Transport Management Interface
 * Professional dashboard with transport insights and AI-powered features
 */
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_login('../login.php');
if (!has_role('transport') && !has_role('admin')) {
    redirect('../login.php', 'Access denied. Transport privileges required.', 'error');
}

$transport_id = $_SESSION['user_id'];
$tenantId = $_SESSION['tenant_id'] ?? 1;
$full_name = $_SESSION['full_name'];

// Get transport statistics
$transport_stats = [
    'total_routes' => trn_count('transport_routes', 'tenant_id = ? AND is_active = 1', [$tenantId]),
    'total_vehicles' => trn_count('transport_vehicles', 'tenant_id = ? AND status = "active"', [$tenantId]),
    'assigned_students' => trn_count('transport_assignments', 'tenant_id = ? AND status = "active"', [$tenantId]),
    'unassigned_students' => trn_count('users u', 'u.tenant_id = ? AND u.role = "student" AND u.status = "active" AND NOT EXISTS (SELECT 1 FROM transport_assignments ta WHERE ta.student_id = u.id AND ta.status = "active")', [$tenantId]),
];

// Get recent assignments
$recent_assignments = db()->fetchAll("
    SELECT ta.*, u.first_name, u.last_name, tr.route_name, tv.vehicle_number, tv.driver_name
    FROM transport_assignments ta
    JOIN users u ON ta.student_id = u.id
    JOIN transport_routes tr ON ta.route_id = tr.id
    JOIN transport_vehicles tv ON ta.vehicle_id = tv.id
    WHERE ta.tenant_id = ?
    ORDER BY ta.created_at DESC
    LIMIT 10
", [$tenantId]);

// Get route capacity information
$route_capacity = db()->fetchAll("
    SELECT tr.*, tv.vehicle_number, tv.capacity,
           COUNT(ta.id) as assigned_students,
           (tv.capacity - COUNT(ta.id)) as available_seats,
           ROUND((COUNT(ta.id) / tv.capacity) * 100, 1) as utilization_rate
    FROM transport_routes tr
    JOIN transport_vehicles tv ON tr.vehicle_id = tv.id
    LEFT JOIN transport_assignments ta ON tr.id = ta.route_id AND ta.status = 'active'
    WHERE tr.tenant_id = ? AND tr.is_active = 1
    GROUP BY tr.id, tr.route_name, tv.vehicle_number, tv.capacity
    ORDER BY tr.route_name
", [$tenantId]);

// AI Transport Insights
$ai_insights = [];
try {
    require_once '../includes/sams-init.php';
    try {
        if (class_exists('SAMS_TransportBot')) {
            $transportBot = new SAMS_TransportBot();
            $ai_insights = $transportBot->getTransportInsights($tenantId);
        }
    } catch (Throwable $e) {
        // Fallback insights
        $ai_insights = [
            'transport_health' => $transport_stats['unassigned_students'] > 20 ? 'needs_attention' : 'good',
            'route_optimization' => 'stable',
            'recommendation' => $transport_stats['unassigned_students'] > 0 ? 'Assign unassigned students to appropriate routes to improve transport efficiency' : 'Transport operations are running efficiently'
        ];
    }
} catch (Throwable $e) {
    $ai_insights = [
        'transport_health' => 'good',
        'route_optimization' => 'stable',
        'recommendation' => 'Continue regular transport monitoring and route optimization'
    ];
}

$csrf = generate_csrf_token();

function trn_count($table, $where = '1=1', $params = [])
{
    try {
        if (!table_exists($table)) return 0;
        return (int)db()->count($table, $where, $params);
    } catch (Throwable $e) {
        return 0;
    }
}

$stats = [
    'total_routes'     => trn_count('transport_routes'),
    'total_vehicles'   => trn_count('transport_vehicles'),
    'active_vehicles'  => trn_count('transport_vehicles', "status = 'active'"),
    'total_drivers'    => trn_count('transport_drivers'),
    'students_using'   => trn_count('transport_allocations'),
    'trips_today'      => trn_count('transport_trips', 'DATE(trip_date) = CURDATE()'),
    'maintenance_due'  => trn_count('transport_maintenance', "status = 'scheduled'"),
];

$recent_trips = [];
try {
    if (table_exists('transport_trips')) {
        $recent_trips = db()->fetchAll("
            SELECT tt.*, tr.route_name, tv.vehicle_number
            FROM transport_trips tt
            LEFT JOIN transport_routes tr ON tt.route_id = tr.id
            LEFT JOIN transport_vehicles tv ON tt.vehicle_id = tv.id
            ORDER BY tt.trip_date DESC, tt.departure_time DESC LIMIT 8
        ") ?: [];
    }
} catch (Throwable $e) {
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php include '../includes/favicon-loader.php'; ?>
    <script src="../assets/js/theme-loader.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transport Dashboard - <?php echo APP_NAME; ?></title>
    <link rel="manifest" href="/attendance/manifest.json">
    <meta name="theme-color" content="#f97316">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="../assets/css/professional-ui.css" rel="stylesheet">
</head>

<body>
    <div class="app-layout">
        <?php include '../includes/sidebar-nav.php'; ?>
        <main class="main-content">
            <header class="cyber-header">
                <div class="page-title-section">
                    <div class="page-icon-orb" style="background:linear-gradient(135deg,#f97316,#ea580c)"><i class="fas fa-bus"></i></div>
                    <div>
                        <h1 class="page-title">Transport Dashboard</h1>
                        <p class="page-subtitle">Fleet & route management</p>
                    </div>
                </div>
            </header>
            <div class="cyber-content">
                <!-- KPI Cards -->
                <div class="stats-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:16px;margin-bottom:24px">
                    <?php
                    $kpis = [
                        ['Routes', 'route', $stats['total_routes'], '#f97316'],
                        ['Vehicles', 'bus', $stats['total_vehicles'], '#0ea5e9'],
                        ['Active Vehicles', 'circle-check', $stats['active_vehicles'], '#10b981'],
                        ['Drivers', 'id-card', $stats['total_drivers'], '#8b5cf6'],
                        ['Students', 'user-graduate', $stats['students_using'], '#f59e0b'],
                        ['Trips Today', 'road', $stats['trips_today'], '#14b8a6'],
                        ['Maintenance Due', 'wrench', $stats['maintenance_due'], '#ef4444'],
                    ];
                    foreach ($kpis as [$label, $icon, $val, $color]): ?>
                        <div class="cyber-card" style="padding:18px">
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
                                <span style="font-size:.72rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;font-weight:600"><?php echo $label; ?></span>
                                <div style="width:32px;height:32px;border-radius:8px;background:<?php echo $color; ?>18;display:flex;align-items:center;justify-content:center"><i class="fas fa-<?php echo $icon; ?>" style="color:<?php echo $color; ?>;font-size:.75rem"></i></div>
                            </div>
                            <div style="font-size:1.6rem;font-weight:800;color:var(--text-primary)"><?php echo number_format($val); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Quick Actions -->
                <div class="cyber-card" style="padding:20px;margin-bottom:24px">
                    <h3 style="margin:0 0 16px;font-size:1rem;font-weight:700;color:var(--text-primary)"><i class="fas fa-bolt" style="color:#f97316;margin-right:8px"></i>Quick Actions</h3>
                    <div style="display:flex;flex-wrap:wrap;gap:10px">
                        <a href="routes.php" class="btn btn-primary" style="display:inline-flex;align-items:center;gap:6px;background:#f97316;border-color:#f97316"><i class="fas fa-route"></i> Manage Routes</a>
                        <a href="vehicles.php" class="btn btn-outline" style="display:inline-flex;align-items:center;gap:6px"><i class="fas fa-bus"></i> Vehicles</a>
                        <a href="drivers.php" class="btn btn-outline" style="display:inline-flex;align-items:center;gap:6px"><i class="fas fa-id-card"></i> Drivers</a>
                        <a href="student-allocation.php" class="btn btn-outline" style="display:inline-flex;align-items:center;gap:6px"><i class="fas fa-user-graduate"></i> Student Allocation</a>
                        <a href="trip-logs.php" class="btn btn-outline" style="display:inline-flex;align-items:center;gap:6px"><i class="fas fa-clipboard-list"></i> Trip Logs</a>
                        <a href="maintenance.php" class="btn btn-outline" style="display:inline-flex;align-items:center;gap:6px;border-color:#ef4444;color:#ef4444"><i class="fas fa-wrench"></i> Maintenance (<?php echo $stats['maintenance_due']; ?>)</a>
                    </div>
                </div>

                <!-- Recent Trips / Info -->
                <div class="cyber-card" style="padding:20px">
                    <h3 style="margin:0 0 16px;font-size:1rem;font-weight:700;color:var(--text-primary)"><i class="fas fa-clock-rotate-left" style="color:#0ea5e9;margin-right:8px"></i>Recent Trips</h3>
                    <?php if (empty($recent_trips)): ?>
                        <div style="padding:16px;border-radius:12px;background:var(--bg-secondary);border:1px dashed var(--border-color)">
                            <p style="font-size:.85rem;color:var(--text-secondary);margin:0"><i class="fas fa-info-circle" style="color:#f97316;margin-right:6px"></i>Transport tables (<code>transport_routes</code>, <code>transport_vehicles</code>, <code>transport_trips</code>) will be created when the transport module SQL is executed.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="data-table" style="width:100%">
                                <thead>
                                    <tr>
                                        <th>Route</th>
                                        <th>Vehicle</th>
                                        <th>Date</th>
                                        <th>Departure</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_trips as $t): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($t['route_name'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($t['vehicle_number'] ?? 'N/A'); ?></td>
                                            <td><?php echo date('M j', strtotime($t['trip_date'])); ?></td>
                                            <td><?php echo htmlspecialchars($t['departure_time'] ?? '—'); ?></td>
                                            <td><span class="badge badge-<?php echo ($t['status'] ?? 'completed') === 'completed' ? 'success' : 'warning'; ?>"><?php echo ucfirst($t['status'] ?? 'completed'); ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    <script src="../assets/js/main.js"></script>
</body>

</html>
