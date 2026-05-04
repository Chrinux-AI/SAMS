<?php

/**
 * SAMS Parent Dashboard - Modern AI-Enhanced Interface
 * Professional dashboard with AI insights and modern UI
 */
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/database.php';
require_login('../login.php');
if (!has_role('parent')) {
    redirect('../login.php', 'Parent access required.', 'error');
}

$parent_id = $_SESSION['user_id'];
$tenantId = $_SESSION['tenant_id'] ?? 1;
$full_name = $_SESSION['full_name'];

// Get children
$children = [];
$gradeLevelSelect = table_has_column('classes', 'grade_level')
    ? 'c.grade_level'
    : 'NULL AS grade_level';
$userTenantClause = '';
$userTenantParams = [];
if ((int)$tenantId > 0) {
    if (table_has_column('users', 'tenant_id')) {
        $userTenantClause = ' AND u.tenant_id = ?';
        $userTenantParams[] = (int)$tenantId;
    } elseif (table_has_column('users', 'school_id')) {
        $userTenantClause = ' AND u.school_id = ?';
        $userTenantParams[] = (int)$tenantId;
    }
}

if (table_exists('parent_student_links')) {
    $children = db()->fetchAll("
        SELECT u.id, u.first_name, u.last_name, s.admission_number as student_id, {$gradeLevelSelect}, c.class_name
        FROM users u
        JOIN students s ON u.id = s.user_id
        JOIN parent_student_links psl ON s.user_id = psl.student_id
        LEFT JOIN classes c ON s.class_id = c.id
        WHERE psl.parent_id = ? AND u.status = 'active'{$userTenantClause}
    ", array_merge([$parent_id], $userTenantParams)) ?: [];
} elseif (table_has_column('users', 'parent_id')) {
    $children = db()->fetchAll("
        SELECT u.id, u.first_name, u.last_name, s.admission_number as student_id, {$gradeLevelSelect}, c.class_name
        FROM users u
        JOIN students s ON u.id = s.user_id
        LEFT JOIN classes c ON s.class_id = c.id
        WHERE u.parent_id = ? AND u.role = 'student' AND u.status = 'active'{$userTenantClause}
    ", array_merge([$parent_id], $userTenantParams)) ?: [];
}

// Get today's attendance for all children
$today = date('Y-m-d');
$child_ids = array_column($children, 'id');
$today_attendance = [];

if (!empty($child_ids)) {
    $placeholders = implode(',', array_fill(0, count($child_ids), '?'));
    $params = array_merge($child_ids, [$today]);

    if (table_exists('attendance_records')) {
        $today_attendance = db()->fetchAll("
            SELECT ar.*, u.first_name, u.last_name, c.class_name
            FROM attendance_records ar
            JOIN users u ON ar.student_id = u.id
            JOIN classes c ON ar.class_id = c.id
            WHERE ar.student_id IN ($placeholders) AND DATE(ar.check_in_time) = ?
        ", $params) ?: [];
    }
}

// Calculate stats
$total_present = count(array_filter($today_attendance, fn($r) => $r['status'] === 'present'));
$total_late = count(array_filter($today_attendance, fn($r) => $r['status'] === 'late'));
$total_absent = count(array_filter($today_attendance, fn($r) => $r['status'] === 'absent'));
$today_total = count($today_attendance);
$today_rate = $today_total > 0 ? round((($total_present + $total_late) / $today_total) * 100, 1) : 0;

// Unread messages
$unread_count = get_unread_message_count((int)$parent_id, (int)$tenantId);

// Recent communications
$recent_communications = get_recent_received_communications((int)$parent_id, (int)$tenantId, 5);

// Upcoming events/assignments for children
$upcoming_events = [];
if (!empty($child_ids) && table_exists('assignments') && table_exists('class_enrollments')) {
    $placeholders = implode(',', array_fill(0, count($child_ids), '?'));
    $upcoming_events = db()->fetchAll("
        SELECT a.*, c.class_name, u.first_name, u.last_name
        FROM assignments a
        JOIN classes c ON a.class_id = c.id
        JOIN class_enrollments ce ON c.id = ce.class_id
        JOIN users u ON ce.student_id = u.id
        WHERE ce.student_id IN ($placeholders) AND a.due_date >= CURDATE()
        ORDER BY a.due_date ASC
        LIMIT 5
    ", $child_ids) ?: [];
}

// AI Insights
$ai_insights = [];
try {
    require_once __DIR__ . '/../includes/sams-init.php';
    try {
        if (class_exists('SAMS_ParentBot')) {
            $parentBot = new SAMS_ParentBot();
            $ai_insights = $parentBot->getParentInsights($parent_id, $tenantId);
        }
    } catch (Throwable $e) {
        // Fallback insights
        $ai_insights = [
            'engagement_level' => $today_rate > 90 ? 'highly_engaged' : ($today_rate > 75 ? 'moderately_engaged' : 'needs_attention'),
            'recommendation' => 'Regular communication with teachers helps support your child\'s success',
            'children_status' => count($children) > 1 ? 'monitoring_multiple' : 'focused_support'
        ];
    }
} catch (Throwable $e) {
    $ai_insights = [
        'engagement_level' => 'moderately_engaged',
        'recommendation' => 'Stay involved in your child\'s education journey',
        'children_status' => 'active_monitoring'
    ];
}

$csrf = generate_csrf_token();

// Master layout configuration
$page_title = 'Parent Dashboard';
$page_icon = 'fas fa-user-friends';
$page_subtitle = 'Welcome back, ' . htmlspecialchars($full_name);

ob_start();
?>
<!-- Bento Grid Dashboard -->
<div class="grid grid-cols-12 gap-6">

  <!-- Parent Welcome Banner & AI Insights (Top Full Width) -->
  <div class="col-span-12 grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Welcome Banner (2 cols wide) -->
    <div class="lg:col-span-2 bg-violet-700 text-white p-8 rounded-xl relative overflow-hidden group shadow-lg" style="background:linear-gradient(135deg, #6D28D9 0%, #4C1D95 100%);">
      <div class="absolute -right-10 -bottom-10 opacity-10 group-hover:scale-110 transition-transform duration-700 pointer-events-none">
        <span class="material-symbols-outlined" style="font-size:180px">family_history</span>
      </div>
      <div class="relative z-10 h-full flex flex-col justify-between">
        <div>
          <span class="text-[10px] font-bold uppercase tracking-widest text-violet-200">Parent Portal</span>
          <h1 class="text-3xl font-headline font-bold mt-2">Welcome back, <?php echo htmlspecialchars(explode(' ', $full_name)[0]); ?>!</h1>
          <p class="text-violet-100 mt-2 max-w-lg opacity-90">Monitor the progress of your <?php echo count($children); ?> enrolled children, track their real-time attendance, and stay connected with their teachers.</p>
        </div>
        <div class="mt-6 flex gap-4">
            <a href="attendance.php" class="px-5 py-2.5 bg-white text-violet-700 font-bold rounded-lg text-sm hover:shadow-lg hover:scale-105 transition-all w-fit flex items-center gap-2">
                <span class="material-symbols-outlined text-sm">monitor_heart</span> Track Attendance
            </a>
            <a href="communication.php" class="px-5 py-2.5 bg-violet-800 border border-violet-500/50 text-white font-bold rounded-lg text-sm hover:bg-violet-900 transition-all w-fit flex items-center gap-2">
                <span class="material-symbols-outlined text-sm">support_agent</span> Contact Teachers
            </a>
        </div>
      </div>
    </div>
    
    <!-- AI Family Assistant Widget -->
    <div class="col-span-1 lg:col-span-1 bg-gradient-to-br from-amber-50 to-orange-50 border border-amber-100 p-6 rounded-xl flex flex-col shadow-sm">
        <div class="flex items-center gap-2 mb-4">
            <span class="material-symbols-outlined text-amber-600">psychology</span>
            <h3 class="font-headline font-bold text-amber-800">SAMS Family Assistant</h3>
        </div>
        <p class="text-sm font-medium text-amber-900/80 mb-4 flex-grow"><?php echo htmlspecialchars($ai_insights['recommendation'] ?? 'Regular communication with teachers helps support your children\'s success. Stay involved in their educational journey.'); ?></p>
        
        <div class="mb-4 space-y-2">
            <span class="text-[10px] font-bold uppercase tracking-widest text-amber-500">AI Suggestion</span>
            <p class="text-xs text-amber-800 font-medium leading-tight flex gap-2">
                <span class="material-symbols-outlined text-[14px] text-amber-400">arrow_right</span> Check your children's attendance daily and securely sign all digital report cards.
            </p>
        </div>

        <div class="space-y-3 pt-3 border-t border-amber-200/50">
            <div class="flex justify-between items-center text-sm">
                <span class="text-amber-700 font-semibold text-xs tracking-wide uppercase">Engagement Level</span>
                <?php $el = $ai_insights['engagement_level'] ?? 'moderately_engaged'; ?>
                <span class="px-2.5 py-1 rounded-md text-[10px] font-bold uppercase tracking-wider
                <?php 
                    echo $el === 'needs_attention' ? 'bg-red-100 text-red-700' : ($el === 'highly_engaged' ? 'bg-emerald-100 text-emerald-700' : 'bg-blue-100 text-blue-700');
                ?>">
                    <?php echo htmlspecialchars(str_replace('_', ' ', $el)); ?>
                </span>
            </div>
        </div>
    </div>
  </div>

  <!-- Children Overview Grid -->
  <div class="col-span-12">
    <div class="flex items-center gap-2 mb-4">
        <span class="material-symbols-outlined text-violet-600 text-xl">groups</span>
        <h3 class="font-headline font-bold text-primary text-xl">My Children</h3>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
        <?php foreach ($children as $child): ?>
            <?php
            $child_attendance = null;
            foreach ($today_attendance as $att) {
                if ($att['student_id'] == $child['id']) {
                    $child_attendance = $att;
                    break;
                }
            }
            ?>
            <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm hover:border-violet-500/30 transition-all group flex flex-col justify-between h-full">
                <!-- Child Info -->
                <div class="flex items-center gap-4 mb-5 border-b border-slate-100 pb-5">
                    <div class="w-14 h-14 rounded-full bg-violet-50 text-violet-600 flex items-center justify-center font-bold text-xl border border-violet-100 uppercase tracking-widest flex-shrink-0">
                        <?php echo substr($child['first_name'], 0, 1) . substr($child['last_name'], 0, 1); ?>
                    </div>
                    <div>
                        <h4 class="font-bold text-lg text-primary leading-tight"><?php echo htmlspecialchars($child['first_name'] . ' ' . $child['last_name']); ?></h4>
                        <p class="text-xs text-slate-500 mt-1"><?php echo htmlspecialchars($child['class_name'] ?? 'Not assigned'); ?> • ID: <?php echo htmlspecialchars($child['student_id']); ?></p>
                    </div>
                </div>
                
                <!-- Child Status -->
                <div class="mb-5">
                    <span class="text-[10px] uppercase font-bold text-slate-400 tracking-widest block mb-2">Today's Check-in</span>
                    <?php if ($child_attendance): 
                        $bg = $child_attendance['status'] === 'present' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 
                             ($child_attendance['status'] === 'late' ? 'bg-amber-50 text-amber-700 border-amber-200' : 'bg-rose-50 text-rose-700 border-rose-200');
                        $icon = $child_attendance['status'] === 'present' ? 'check_circle' : ($child_attendance['status'] === 'late' ? 'schedule' : 'cancel');
                    ?>
                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold border <?php echo $bg; ?> w-full">
                            <span class="material-symbols-outlined text-[16px]"><?php echo $icon; ?></span>
                            <?php echo htmlspecialchars(ucfirst($child_attendance['status'])); ?>
                        </span>
                    <?php else: ?>
                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold border bg-slate-50 text-slate-600 border-slate-200 w-full">
                            <span class="material-symbols-outlined text-[16px]">more_time</span>
                            Not Marked Yet
                        </span>
                    <?php endif; ?>
                </div>

                <!-- Child Actions -->
                <div class="flex gap-2">
                    <a href="child-details.php?student_id=<?php echo $child['id']; ?>" class="flex-1 py-2 bg-violet-50 text-violet-700 text-xs font-bold rounded-lg text-center hover:bg-violet-600 hover:text-white transition-colors border border-violet-100 flex items-center justify-center gap-1"><span class="material-symbols-outlined text-[14px]">visibility</span> View Full Profile</a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
  </div>

  <!-- Stats Cards Row -->
  <div class="col-span-12 grid grid-cols-2 lg:grid-cols-4 gap-6">
    <div class="bg-white p-6 rounded-xl border border-slate-200 flex flex-col shadow-sm hover:border-violet-500/30 transition-colors group">
      <span class="text-slate-400 font-label text-[10px] uppercase tracking-wider font-bold mb-4">Total Children</span>
      <span class="text-3xl font-extrabold font-headline text-primary group-hover:text-violet-600 transition-colors"><?php echo count($children); ?></span>
    </div>
    <div class="bg-white p-6 rounded-xl border border-slate-200 flex flex-col shadow-sm hover:border-violet-500/30 transition-colors group">
      <span class="text-slate-400 font-label text-[10px] uppercase tracking-wider font-bold mb-4">Today's Attendance Rate</span>
      <span class="text-3xl font-extrabold font-headline <?php echo $today_rate >= 85 ? 'text-emerald-600' : 'text-amber-600'; ?> transition-colors"><?php echo $today_rate; ?>%</span>
    </div>
    <div class="bg-white p-6 rounded-xl border border-slate-200 flex flex-col shadow-sm hover:border-violet-500/30 transition-colors group">
      <span class="text-slate-400 font-label text-[10px] uppercase tracking-wider font-bold mb-4">Present Today</span>
      <span class="text-3xl font-extrabold font-headline text-primary group-hover:text-violet-600 transition-colors"><?php echo $total_present; ?></span>
    </div>
    <div class="bg-white p-6 rounded-xl border border-slate-200 flex flex-col shadow-sm hover:border-violet-500/30 transition-colors group relative">
      <span class="text-slate-400 font-label text-[10px] uppercase tracking-wider font-bold mb-4">Unread Messages</span>
      <span class="text-3xl font-extrabold font-headline text-primary group-hover:text-violet-600 transition-colors"><?php echo $unread_count; ?></span>
      <?php if($unread_count > 0): ?>
        <span class="absolute top-6 right-6 flex h-3 w-3">
          <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-400 opacity-75"></span>
          <span class="relative inline-flex rounded-full h-3 w-3 bg-rose-500"></span>
        </span>
      <?php endif; ?>
    </div>
  </div>

  <!-- Quick Actions & Combined Activity Feed -->
  <div class="col-span-12 grid grid-cols-1 lg:grid-cols-3 gap-6">
    
    <!-- Quick Actions (Left) -->
    <div class="lg:col-span-2 bg-white p-8 rounded-xl border border-slate-200 shadow-sm">
      <h3 class="font-headline font-bold text-lg text-primary mb-6">Quick Actions</h3>
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        
        <a href="attendance.php" class="p-4 rounded-xl border border-slate-100 hover:border-violet-200 hover:bg-violet-50/50 flex flex-col items-center text-center transition-all group">
          <div class="w-12 h-12 rounded-full bg-violet-50 flex items-center justify-center text-violet-600 mb-3 group-hover:bg-violet-600 group-hover:text-white transition-colors">
            <span class="material-symbols-outlined">event_available</span>
          </div>
          <h4 class="font-bold text-sm text-on-surface">Attendance</h4>
        </a>

        <a href="grades.php" class="p-4 rounded-xl border border-slate-100 hover:border-emerald-200 hover:bg-emerald-50/50 flex flex-col items-center text-center transition-all group">
          <div class="w-12 h-12 rounded-full bg-emerald-50 flex items-center justify-center text-emerald-600 mb-3 group-hover:bg-emerald-600 group-hover:text-white transition-colors">
            <span class="material-symbols-outlined">analytics</span>
          </div>
          <h4 class="font-bold text-sm text-on-surface">View Grades</h4>
        </a>

        <a href="reports.php" class="p-4 rounded-xl border border-slate-100 hover:border-blue-200 hover:bg-blue-50/50 flex flex-col items-center text-center transition-all group">
          <div class="w-12 h-12 rounded-full bg-blue-50 flex items-center justify-center text-blue-600 mb-3 group-hover:bg-blue-600 group-hover:text-white transition-colors">
            <span class="material-symbols-outlined">lab_profile</span>
          </div>
          <h4 class="font-bold text-sm text-on-surface">Terminal Reports</h4>
        </a>

        <a href="../communication/conversations.php" class="p-4 rounded-xl border border-slate-100 hover:border-amber-200 hover:bg-amber-50/50 flex flex-col items-center text-center transition-all group relative">
          <?php if($unread_count > 0): ?>
            <span class="absolute top-4 right-4 text-[10px] bg-rose-500 text-white font-bold px-1.5 py-0.5 rounded-full"><?php echo $unread_count; ?></span>
          <?php endif; ?>
          <div class="w-12 h-12 rounded-full bg-amber-50 flex items-center justify-center text-amber-600 mb-3 group-hover:bg-amber-600 group-hover:text-white transition-colors">
            <span class="material-symbols-outlined">mail</span>
          </div>
          <h4 class="font-bold text-sm text-on-surface">Inbox</h4>
        </a>

        <a href="communication.php" class="p-4 rounded-xl border border-slate-100 hover:border-indigo-200 hover:bg-indigo-50/50 flex flex-col items-center text-center transition-all group">
          <div class="w-12 h-12 rounded-full bg-indigo-50 flex items-center justify-center text-indigo-600 mb-3 group-hover:bg-indigo-600 group-hover:text-white transition-colors">
            <span class="material-symbols-outlined">support_agent</span>
          </div>
          <h4 class="font-bold text-sm text-on-surface">Contact Teachers</h4>
        </a>

        <a href="settings.php" class="p-4 rounded-xl border border-slate-100 hover:border-rose-200 hover:bg-rose-50/50 flex flex-col items-center text-center transition-all group">
          <div class="w-12 h-12 rounded-full bg-rose-50 flex items-center justify-center text-rose-600 mb-3 group-hover:bg-rose-600 group-hover:text-white transition-colors">
            <span class="material-symbols-outlined">settings</span>
          </div>
          <h4 class="font-bold text-sm text-on-surface">Settings</h4>
        </a>

      </div>
    </div>

    <!-- Recent Communication (Right) -->
    <div class="col-span-1 bg-white p-6 rounded-xl border border-slate-200 shadow-sm flex flex-col">
      <h3 class="font-headline font-bold text-sm text-primary mb-6 flex items-center gap-2">
        <span class="material-symbols-outlined text-[18px]">forum</span> Recent Comms
      </h3>
      
      <div class="flex-grow space-y-5">
        
        <?php if (!empty($recent_communications)): ?>
            <?php foreach (array_slice($recent_communications, 0, 4) as $comm): ?>
            <div class="flex gap-4 p-3 rounded-lg border <?php echo $comm['is_read'] ? 'border-slate-100 bg-white' : 'border-violet-100 bg-violet-50/30'; ?>">
                <div class="w-8 h-8 rounded-md bg-violet-100 text-violet-600 flex items-center justify-center flex-shrink-0 mt-0.5">
                    <span class="material-symbols-outlined text-[16px]">record_voice_over</span>
                </div>
                <div class="w-full">
                    <div class="flex justify-between items-start mb-0.5">
                        <p class="text-[10px] font-bold text-violet-600 uppercase tracking-wider">
                            From: <?php echo htmlspecialchars($comm['sender_first'] . ' ' . $comm['sender_last']); ?>
                        </p>
                        <?php if (!$comm['is_read']): ?>
                            <span class="w-2 h-2 rounded-full bg-rose-500 mt-1"></span>
                        <?php endif; ?>
                    </div>
                    <p class="text-sm font-bold text-on-surface leading-tight truncate w-[180px]"><?php echo htmlspecialchars($comm['subject'] ?? 'No Subject'); ?></p>
                    <span class="inline-block mt-1 text-[10px] font-bold text-slate-400">
                        <?php echo date('M j, g:i A', strtotime($comm['created_at'] ?? 'now')); ?>
                    </span>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="flex flex-col items-center justify-center text-center h-full text-slate-400 pb-10 pt-4">
                <span class="material-symbols-outlined text-4xl mb-3 opacity-20">mark_email_read</span>
                <p class="text-xs max-w-[200px]">No recent messages. Teachers will reach out if needed!</p>
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
