<?php

/**
 * SAMS Transport Dashboard - Modern Transport Management Interface
 * Professional dashboard with transport insights and AI-powered features
 */
session_start();
require_once '../core/bootstrap.php';
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
// Master layout configuration
$page_title = 'Transport Dashboard';
$page_icon = 'fas fa-bus';
$page_subtitle = 'Welcome back, ' . htmlspecialchars($full_name);

ob_start();
?>

<!-- Bento Grid Dashboard -->
<div class="grid grid-cols-12 gap-6">

  <!-- Welcome Banner & AI Insights (Top Full Width) -->
  <div class="col-span-12 grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Welcome Banner (2 cols wide) -->
    <div class="lg:col-span-2 bg-orange-600 text-white p-8 rounded-xl relative overflow-hidden group shadow-lg" style="background:linear-gradient(135deg, #EA580C 0%, #C2410C 100%);">
      <div class="absolute -right-10 -bottom-10 opacity-10 group-hover:scale-110 transition-transform duration-700 pointer-events-none">
        <span class="material-symbols-outlined" style="font-size:180px">directions_bus</span>
      </div>
      <div class="relative z-10 h-full flex flex-col justify-between">
        <div>
          <span class="text-[10px] font-bold uppercase tracking-widest text-orange-200">Transport & Fleet Operations</span>
          <h1 class="text-3xl font-headline font-bold mt-2">Welcome back, <?php echo htmlspecialchars(explode(' ', $full_name)[0]); ?>!</h1>
          <p class="text-orange-100 mt-2 max-w-lg opacity-90">Manage school vehicles, optimize transport routes, track student allocations, and oversee driver schedules.</p>
        </div>
        <div class="mt-6 flex gap-4">
            <a href="routes.php" class="px-5 py-2.5 bg-white text-orange-700 font-bold rounded-lg text-sm hover:shadow-lg hover:scale-105 transition-all w-fit flex items-center gap-2">
                <span class="material-symbols-outlined text-sm">route</span> Manage Routes
            </a>
            <a href="allocation.php" class="px-5 py-2.5 bg-orange-700 border border-orange-400/50 text-white font-bold rounded-lg text-sm hover:bg-orange-800 transition-all w-fit flex items-center gap-2">
                <span class="material-symbols-outlined text-sm">hail</span> Student Allocation
            </a>
        </div>
      </div>
    </div>
    
    <!-- AI Transport Advisor Widget -->
    <div class="col-span-1 lg:col-span-1 bg-gradient-to-br from-orange-50 to-amber-50 border border-orange-100 p-6 rounded-xl flex flex-col shadow-sm">
        <div class="flex items-center gap-2 mb-4">
            <span class="material-symbols-outlined text-orange-600">smart_toy</span>
            <h3 class="font-headline font-bold text-orange-800">AI Transport Advisor</h3>
        </div>
        <p class="text-sm font-medium text-orange-900/80 mb-4 flex-grow"><?php echo htmlspecialchars($ai_insights['recommendation'] ?? 'Continue regular transport monitoring and route optimization.'); ?></p>
        
        <div class="mb-4 space-y-2">
            <span class="text-[10px] font-bold uppercase tracking-widest text-orange-500">Route Efficiency</span>
            <p class="text-xs text-orange-800 font-medium leading-tight flex items-center gap-2">
                <span class="material-symbols-outlined text-[14px] text-orange-400">add_road</span> Network optimization is <?php echo htmlspecialchars(strtoupper($ai_insights['route_optimization'] ?? 'stable')); ?>
            </p>
        </div>

        <div class="space-y-3 pt-3 border-t border-orange-200/50">
            <div class="flex justify-between items-center text-sm">
                <span class="text-orange-700 font-semibold text-xs tracking-wide uppercase">Transport Health</span>
                <?php $th = $ai_insights['transport_health'] ?? 'good'; ?>
                <span class="px-2.5 py-1 rounded-md text-[10px] font-bold uppercase tracking-wider
                <?php 
                    echo $th === 'needs_attention' ? 'bg-rose-100 text-rose-700' : ($th === 'excellent' ? 'bg-emerald-100 text-emerald-700' : 'bg-orange-100 text-orange-700');
                ?>">
                    <?php echo htmlspecialchars(str_replace('_', ' ', $th)); ?>
                </span>
            </div>
        </div>
    </div>
  </div>

  <!-- Stats Cards Row -->
  <div class="col-span-12 grid grid-cols-2 lg:grid-cols-6 gap-4">
    <div class="bg-white p-5 rounded-xl border border-slate-200 flex flex-col shadow-sm hover:border-orange-500/30 transition-colors group">
      <div class="flex justify-between items-center mb-3">
          <span class="text-slate-400 font-label text-[10px] uppercase tracking-wider font-bold">Total Routes</span>
          <div class="w-8 h-8 rounded-lg bg-orange-50 text-orange-600 flex items-center justify-center"><span class="material-symbols-outlined text-[16px]">alt_route</span></div>
      </div>
      <span class="text-2xl font-extrabold font-headline text-primary group-hover:text-orange-600 transition-colors"><?php echo number_format($stats['total_routes']); ?></span>
    </div>
    
    <div class="bg-white p-5 rounded-xl border border-slate-200 flex flex-col shadow-sm hover:border-sky-500/30 transition-colors group">
      <div class="flex justify-between items-center mb-3">
          <span class="text-slate-400 font-label text-[10px] uppercase tracking-wider font-bold">Vehicles</span>
          <div class="w-8 h-8 rounded-lg bg-sky-50 text-sky-600 flex items-center justify-center"><span class="material-symbols-outlined text-[16px]">directions_bus</span></div>
      </div>
      <span class="text-2xl font-extrabold font-headline text-primary group-hover:text-sky-600 transition-colors"><?php echo number_format($stats['total_vehicles']); ?></span>
    </div>

    <div class="bg-white p-5 rounded-xl border border-slate-200 flex flex-col shadow-sm hover:border-emerald-500/30 transition-colors group">
      <div class="flex justify-between items-center mb-3">
          <span class="text-slate-400 font-label text-[10px] uppercase tracking-wider font-bold">Active Vehicles</span>
          <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center"><span class="material-symbols-outlined text-[16px]">check_circle</span></div>
      </div>
      <span class="text-2xl font-extrabold font-headline text-primary group-hover:text-emerald-600 transition-colors"><?php echo number_format($stats['active_vehicles']); ?></span>
    </div>
    
    <div class="bg-white p-5 rounded-xl border border-slate-200 flex flex-col shadow-sm hover:border-violet-500/30 transition-colors group">
      <div class="flex justify-between items-center mb-3">
          <span class="text-slate-400 font-label text-[10px] uppercase tracking-wider font-bold">Drivers</span>
          <div class="w-8 h-8 rounded-lg bg-violet-50 text-violet-600 flex items-center justify-center"><span class="material-symbols-outlined text-[16px]">badge</span></div>
      </div>
      <span class="text-2xl font-extrabold font-headline text-primary group-hover:text-violet-600 transition-colors"><?php echo number_format($stats['total_drivers']); ?></span>
    </div>

    <div class="bg-white p-5 rounded-xl border border-slate-200 flex flex-col shadow-sm hover:border-amber-500/30 transition-colors group">
      <div class="flex justify-between items-center mb-3">
          <span class="text-slate-400 font-label text-[10px] uppercase tracking-wider font-bold">Students</span>
          <div class="w-8 h-8 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center"><span class="material-symbols-outlined text-[16px]">wc</span></div>
      </div>
      <span class="text-2xl font-extrabold font-headline text-primary group-hover:text-amber-600 transition-colors"><?php echo number_format($stats['students_using']); ?></span>
    </div>

    <div class="bg-white p-5 rounded-xl border border-slate-200 flex flex-col shadow-sm hover:border-rose-500/30 transition-colors group">
      <div class="flex justify-between items-center mb-3">
          <span class="text-slate-400 font-label text-[10px] uppercase tracking-wider font-bold">Maintenance</span>
          <div class="w-8 h-8 rounded-lg bg-rose-50 text-rose-600 flex items-center justify-center"><span class="material-symbols-outlined text-[16px]">build</span></div>
      </div>
      <span class="text-2xl font-extrabold font-headline text-rose-600"><?php echo number_format($stats['maintenance_due']); ?></span>
    </div>
  </div>

  <!-- Main Content Grid -->
  <div class="col-span-12 grid grid-cols-1 lg:grid-cols-3 gap-6">
    
    <!-- Quick Actions & Route Capacity (Left) -->
    <div class="lg:col-span-1 space-y-6">
        
        <!-- Quick Actions -->
        <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
            <h3 class="font-headline font-bold text-sm text-primary mb-4 flex items-center gap-2">
                <span class="material-symbols-outlined text-[18px] text-orange-600">bolt</span> Quick Actions
            </h3>
            <div class="flex flex-col gap-3">
                <a href="maintenance.php" class="px-4 py-3 rounded-lg border border-rose-200 bg-rose-50 text-rose-700 font-bold hover:bg-rose-100 transition-colors flex items-center justify-between group">
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-outlined">car_repair</span>
                        <span class="text-sm">Maintenance Logs</span>
                    </div>
                    <?php if($stats['maintenance_due'] > 0): ?>
                        <span class="px-2 py-0.5 bg-white rounded-md text-xs"><?php echo $stats['maintenance_due']; ?></span>
                    <?php endif; ?>
                </a>

                <a href="vehicles.php" class="px-4 py-3 rounded-lg border border-slate-200 text-slate-700 font-bold hover:bg-slate-50 transition-colors flex items-center gap-3">
                    <span class="material-symbols-outlined text-orange-600">directions_bus</span>
                    <span class="text-sm">Manage Vehicles</span>
                </a>
                
                <a href="drivers.php" class="px-4 py-3 rounded-lg border border-slate-200 text-slate-700 font-bold hover:bg-slate-50 transition-colors flex items-center gap-3">
                    <span class="material-symbols-outlined text-orange-600">badge</span>
                    <span class="text-sm">Manage Drivers</span>
                </a>
                
                <a href="trip-logs.php" class="px-4 py-3 rounded-lg border border-slate-200 text-slate-700 font-bold hover:bg-slate-50 transition-colors flex items-center gap-3">
                    <span class="material-symbols-outlined text-orange-600">list_alt</span>
                    <span class="text-sm">View Trip Logs</span>
                </a>
            </div>
        </div>

        <!-- Route Capacity -->
        <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
            <h3 class="font-headline font-bold text-sm text-primary mb-4 flex items-center gap-2">
                <span class="material-symbols-outlined text-[18px] text-sky-500">pie_chart</span> Route Capacity
            </h3>
            
            <div class="space-y-4">
            <?php if (!empty($route_capacity)): ?>
                <?php foreach (array_slice($route_capacity, 0, 4) as $route): 
                    $utilization = min(100, $route['utilization_rate']);
                    $barColor = $utilization > 90 ? 'bg-rose-500' : ($utilization > 75 ? 'bg-amber-500' : 'bg-emerald-500');
                ?>
                <div>
                    <div class="flex justify-between items-center mb-1">
                        <span class="text-sm font-bold text-on-surface line-clamp-1 flex-grow pr-2"><?php echo htmlspecialchars($route['route_name']); ?></span>
                        <span class="text-[10px] font-bold uppercase text-slate-500 whitespace-nowrap"><?php echo $route['utilization_rate']; ?>%</span>
                    </div>
                    <div class="text-[10px] text-slate-500 mb-2">Bus <?php echo htmlspecialchars($route['vehicle_number']); ?> • <?php echo $route['available_seats']; ?> seats left</div>
                    <div class="w-full bg-slate-100 rounded-full h-2 shadow-inner">
                        <div class="<?php echo $barColor; ?> h-2 rounded-full transition-all duration-500" style="width: <?php echo $utilization; ?>%"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="text-center py-6 text-slate-400">
                    <span class="material-symbols-outlined text-3xl mb-2 opacity-50">alt_route</span>
                    <p class="text-xs">No active routes found.</p>
                </div>
            <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Recent Trips (Right) -->
    <div class="lg:col-span-2 bg-white p-6 rounded-xl border border-slate-200 shadow-sm flex flex-col">
      <div class="flex justify-between items-center mb-6">
          <h3 class="font-headline font-bold text-sm text-primary flex items-center gap-2">
            <span class="material-symbols-outlined text-[18px]">history</span> Recent Trips
          </h3>
          <a href="trip-logs.php" class="text-xs font-bold text-orange-600 hover:text-orange-800 uppercase tracking-wider">View All</a>
      </div>
      
      <div class="flex-grow">
        <?php if (!empty($recent_trips)): ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="text-[10px] uppercase tracking-wider text-slate-400 border-b border-slate-100">
                            <th class="pb-3 font-bold">Route</th>
                            <th class="pb-3 font-bold">Vehicle</th>
                            <th class="pb-3 font-bold">Date</th>
                            <th class="pb-3 font-bold">Departure</th>
                            <th class="pb-3 font-bold text-right">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach (array_slice($recent_trips, 0, 8) as $t): 
                            $status = strtolower($t['status'] ?? 'completed');
                            
                            $badgeClass = 'bg-slate-100 text-slate-600';
                            $icon = 'circle';
                            
                            if ($status === 'completed') {
                                $badgeClass = 'bg-emerald-100 text-emerald-700';
                                $icon = 'check_circle';
                            } elseif ($status === 'in-progress' || $status === 'active') {
                                $badgeClass = 'bg-sky-100 text-sky-700';
                                $icon = 'directions_bus';
                            } elseif ($status === 'delayed') {
                                $badgeClass = 'bg-rose-100 text-rose-700';
                                $icon = 'warning';
                            } else {
                                $badgeClass = 'bg-amber-100 text-amber-700';
                                $icon = 'schedule';
                            }
                        ?>
                        <tr class="hover:bg-slate-50 transition-colors group">
                            <td class="py-3 pr-4">
                                <div class="font-bold text-primary max-w-[200px] truncate"><?php echo htmlspecialchars($t['route_name'] ?? 'N/A'); ?></div>
                            </td>
                            <td class="py-3 text-slate-600 font-medium whitespace-nowrap"><span class="px-2 py-1 bg-slate-50 border border-slate-200 inline-block rounded text-xs font-bold font-mono"><?php echo htmlspecialchars($t['vehicle_number'] ?? 'N/A'); ?></span></td>
                            <td class="py-3 text-slate-500 text-xs whitespace-nowrap"><?php echo date('M j, Y', strtotime($t['trip_date'])); ?></td>
                            <td class="py-3 text-slate-500 text-xs font-mono font-bold"><?php echo htmlspecialchars($t['departure_time'] ?? '—'); ?></td>
                            <td class="py-3 text-right">
                                <span class="inline-flex items-center gap-1 px-2 py-1 rounded text-[10px] font-bold uppercase tracking-wider <?php echo $badgeClass; ?>">
                                    <span class="material-symbols-outlined text-[12px]"><?php echo $icon; ?></span> <?php echo htmlspecialchars($status); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="flex flex-col items-center justify-center text-center h-full text-slate-400 pb-10 pt-10">
                <span class="material-symbols-outlined text-5xl mb-4 opacity-20">commute</span>
                <p class="text-sm max-w-[300px] mb-2 font-medium text-slate-500">No transport trips logged yet.</p>
                <p class="text-xs max-w-[300px]">Run the transport setup SQL to enable full functionality and see trips here.</p>
            </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

</div>
<?php
$page_content = ob_get_clean();
require BASE_PATH . '/resources/ui-core/layouts/master-dashboard.php';
?>
