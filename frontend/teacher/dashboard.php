<?php

/**
 * SAMS Teacher Dashboard - Modern AI-Enhanced Interface
 * Professional dashboard with AI insights and modern UI
 */
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/database.php';
require_login('../login.php');
if (!has_role('teacher')) {
    redirect('../login.php', 'Teacher access required.', 'error');
}

$teacher_id = $_SESSION['user_id'];
$tenantId = $_SESSION['tenant_id'] ?? 1;
$full_name = $_SESSION['full_name'];

// Get teacher data
$teacher = db()->fetchOne("SELECT * FROM teachers WHERE user_id = ?", [$teacher_id]);

// Get teacher's classes
$my_classes = db()->fetchAll("
    SELECT c.id, c.class_name, c.class_code, c.class_teacher_id, c.description, c.schedule, c.room_number, c.created_at,
           COUNT(DISTINCT ce.student_id) as student_count
    FROM classes c
    LEFT JOIN class_enrollments ce ON c.id = ce.class_id
    WHERE c.class_teacher_id = ?
    GROUP BY c.id, c.class_name, c.class_code, c.class_teacher_id, c.description, c.schedule, c.room_number, c.created_at
", [$teacher_id]);

// Get student statistics
$total_students_result = db()->fetchOne("
    SELECT COUNT(DISTINCT ce.student_id) as total
    FROM class_enrollments ce JOIN classes c ON ce.class_id = c.id
    WHERE c.class_teacher_id = ?
", [$teacher_id]);
$total_students = $total_students_result['total'] ?? 0;

// Today's attendance
$today = date('Y-m-d');
$today_attendance = db()->fetchAll("
    SELECT ar.*, c.class_name
    FROM attendance_records ar JOIN classes c ON ar.class_id = c.id
    WHERE c.class_teacher_id = ? AND DATE(ar.check_in_time) = ?
    ORDER BY ar.check_in_time DESC
", [$teacher_id, $today]);

$today_present = count(array_filter($today_attendance, fn($r) => $r['status'] === 'present'));
$today_late = count(array_filter($today_attendance, fn($r) => $r['status'] === 'late'));
$today_absent = count(array_filter($today_attendance, fn($r) => $r['status'] === 'absent'));
$today_total = count($today_attendance);
$today_rate = $today_total > 0 ? round((($today_present + $today_late) / $today_total) * 100, 1) : 0;

// Recent messages
$unread_count = db()->fetchOne("
    SELECT COUNT(*) as count FROM message_recipients
    WHERE recipient_id = ? AND is_read = 0 AND deleted_at IS NULL
", [$teacher_id])['count'] ?? 0;

// Recent assignments
$recent_assignments = db()->fetchAll("
    SELECT a.*, c.class_name
    FROM assignments a
    JOIN classes c ON a.class_id = c.id
    WHERE c.class_teacher_id = ?
    ORDER BY a.created_at DESC
    LIMIT 5
", [$teacher_id]);

// AI Insights
$ai_insights = [];
try {
    require_once __DIR__ . '/../includes/sams-init.php';
    try {
        if (class_exists('SAMS_TeacherBot')) {
            $teacherBot = new SAMS_TeacherBot();
            $ai_insights = $teacherBot->getTeacherInsights($teacher_id, $tenantId);
        }
    } catch (Throwable $e) {
        $ai_insights = [
            'workload_status' => $total_students > 30 ? 'high' : ($total_students > 15 ? 'moderate' : 'low'),
            'attendance_trend' => $today_rate > 85 ? 'excellent' : ($today_rate > 70 ? 'good' : 'needs_attention'),
            'recommendation' => 'Continue monitoring attendance patterns'
        ];
    }
} catch (Throwable $e) {
    $ai_insights = [
        'workload_status' => 'moderate',
        'attendance_trend' => 'good',
        'recommendation' => 'Focus on student engagement'
    ];
}

$csrf = generate_csrf_token();

// Master layout configuration
$page_title = 'Teacher Dashboard';
$page_icon = 'fas fa-chalkboard-teacher';
$page_subtitle = 'Welcome back, ' . htmlspecialchars($full_name);

ob_start();
?>
<!-- Bento Grid Dashboard -->
<div class="grid grid-cols-12 gap-6">
  
  <!-- Teacher Welcome Banner & AI Insights (Top Full Width) -->
  <div class="col-span-12 grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Welcome Banner (2 cols wide) -->
    <div class="lg:col-span-2 bg-emerald-700 text-white p-8 rounded-xl relative overflow-hidden group shadow-lg" style="background:linear-gradient(135deg, #047857 0%, #065f46 100%);">
      <div class="absolute -right-10 -bottom-10 opacity-10 group-hover:scale-110 transition-transform duration-700 pointer-events-none">
        <span class="material-symbols-outlined" style="font-size:180px">school</span>
      </div>
      <div class="relative z-10 h-full flex flex-col justify-between">
        <div>
          <span class="text-[10px] font-bold uppercase tracking-widest text-emerald-200">Teacher Portal</span>
          <h1 class="text-3xl font-headline font-bold mt-2">Welcome back, <?php echo htmlspecialchars(explode(' ', $full_name)[0]); ?>!</h1>
          <p class="text-emerald-100 mt-2 max-w-lg opactiy-90">Manage your <?php echo count($my_classes); ?> classes, track attendance, and monitor the progress of your <?php echo $total_students; ?> students.</p>
        </div>
        <div class="mt-6 flex gap-4">
            <a href="attendance.php" class="px-5 py-2.5 bg-white text-emerald-700 font-bold rounded-lg text-sm hover:shadow-lg hover:scale-105 transition-all w-fit flex items-center gap-2">
                <span class="material-symbols-outlined text-sm">fact_check</span> Take Attendance
            </a>
            <a href="assignments.php" class="px-5 py-2.5 bg-emerald-800 border border-emerald-600/50 text-white font-bold rounded-lg text-sm hover:bg-emerald-900 transition-all w-fit flex items-center gap-2">
                <span class="material-symbols-outlined text-sm">task</span> Manage Assignments
            </a>
        </div>
      </div>
    </div>
    
    <!-- AI Assistant Widget -->
    <div class="col-span-1 lg:col-span-1 bg-gradient-to-br from-blue-50 to-indigo-50 border border-blue-100 p-6 rounded-xl flex flex-col shadow-sm">
        <div class="flex items-center gap-2 mb-4">
            <span class="material-symbols-outlined text-blue-600">smart_toy</span>
            <h3 class="font-headline font-bold text-blue-800">SAMS AI Assistant</h3>
        </div>
        <p class="text-sm font-medium text-blue-900/80 mb-6 flex-grow"><?php echo htmlspecialchars($ai_insights['recommendation'] ?? 'Continue monitoring student engagement and attendance patterns.'); ?></p>
        
        <div class="space-y-3 pt-4 border-t border-blue-200/50">
            <div class="flex justify-between items-center text-sm">
                <span class="text-blue-700 font-semibold text-xs tracking-wide uppercase">Workload</span>
                <span class="px-2.5 py-1 rounded-md text-[10px] font-bold uppercase tracking-wider
                <?php 
                    $wl = $ai_insights['workload_status'] ?? 'moderate';
                    echo $wl === 'high' ? 'bg-red-100 text-red-700' : ($wl === 'moderate' ? 'bg-amber-100 text-amber-700' : 'bg-green-100 text-green-700');
                ?>">
                    <?php echo htmlspecialchars($wl); ?>
                </span>
            </div>
            <div class="flex justify-between items-center text-sm">
                <span class="text-blue-700 font-semibold text-xs tracking-wide uppercase">Attendance Trend</span>
                <?php $tr = $ai_insights['attendance_trend'] ?? 'good'; ?>
                <span class="flex items-center gap-1 text-xs font-bold <?php echo $tr === 'excellent' ? 'text-emerald-600' : 'text-amber-600'; ?>">
                    <span class="material-symbols-outlined text-[14px]"><?php echo $tr === 'excellent' ? 'trending_up' : 'trending_flat'; ?></span>
                    <?php echo htmlspecialchars(ucfirst($tr)); ?>
                </span>
            </div>
        </div>
    </div>
  </div>

  <!-- Stats Cards Row -->
  <div class="col-span-12 grid grid-cols-2 md:grid-cols-4 gap-6">
    <div class="bg-white p-6 rounded-xl border border-slate-200 flex flex-col shadow-sm hover:border-emerald-500/30 transition-colors group">
      <span class="text-slate-400 font-label text-[10px] uppercase tracking-wider font-bold mb-4">My Classes</span>
      <span class="text-3xl font-extrabold font-headline text-primary group-hover:text-emerald-600 transition-colors"><?php echo count($my_classes); ?></span>
    </div>
    <div class="bg-white p-6 rounded-xl border border-slate-200 flex flex-col shadow-sm hover:border-emerald-500/30 transition-colors group">
      <span class="text-slate-400 font-label text-[10px] uppercase tracking-wider font-bold mb-4">Total Students</span>
      <span class="text-3xl font-extrabold font-headline text-primary group-hover:text-emerald-600 transition-colors"><?php echo $total_students; ?></span>
    </div>
    <div class="bg-white p-6 rounded-xl border border-slate-200 flex flex-col shadow-sm hover:border-emerald-500/30 transition-colors group">
      <span class="text-slate-400 font-label text-[10px] uppercase tracking-wider font-bold mb-4">Today's Attendance</span>
      <span class="text-3xl font-extrabold font-headline <?php echo $today_rate >= 85 ? 'text-emerald-600' : 'text-amber-600'; ?>"><?php echo $today_rate; ?>%</span>
    </div>
    <div class="bg-white p-6 rounded-xl border border-slate-200 flex flex-col shadow-sm hover:border-emerald-500/30 transition-colors group relative">
      <span class="text-slate-400 font-label text-[10px] uppercase tracking-wider font-bold mb-4">Unread Messages</span>
      <span class="text-3xl font-extrabold font-headline text-primary group-hover:text-emerald-600 transition-colors"><?php echo $unread_count; ?></span>
      <?php if($unread_count > 0): ?>
        <span class="absolute top-6 right-6 flex h-3 w-3">
          <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-400 opacity-75"></span>
          <span class="relative inline-flex rounded-full h-3 w-3 bg-rose-500"></span>
        </span>
      <?php endif; ?>
    </div>
  </div>

  <!-- Quick Actions & Recent Activity -->
  <div class="col-span-12 grid grid-cols-1 lg:grid-cols-3 gap-6">
    
    <!-- Quick Actions (Left) -->
    <div class="lg:col-span-2 bg-white p-8 rounded-xl border border-slate-200 shadow-sm">
      <h3 class="font-headline font-bold text-lg text-primary mb-6">Quick Actions</h3>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        
        <a href="attendance.php" class="p-4 rounded-xl border border-slate-100 hover:border-emerald-200 hover:bg-emerald-50/50 flex items-center gap-4 transition-all group">
          <div class="w-12 h-12 rounded-lg bg-emerald-100 flex items-center justify-center text-emerald-600 group-hover:scale-110 transition-transform">
            <span class="material-symbols-outlined">how_to_reg</span>
          </div>
          <div>
            <h4 class="font-bold text-sm text-on-surface">Take Attendance</h4>
            <p class="text-xs text-slate-500 mt-0.5">Mark attendance for classes</p>
          </div>
        </a>

        <a href="grades.php" class="p-4 rounded-xl border border-slate-100 hover:border-blue-200 hover:bg-blue-50/50 flex items-center gap-4 transition-all group">
          <div class="w-12 h-12 rounded-lg bg-blue-100 flex items-center justify-center text-blue-600 group-hover:scale-110 transition-transform">
            <span class="material-symbols-outlined">grading</span>
          </div>
          <div>
            <h4 class="font-bold text-sm text-on-surface">Manage Grades</h4>
            <p class="text-xs text-slate-500 mt-0.5">Enter and view student grades</p>
          </div>
        </a>

        <a href="assignments.php" class="p-4 rounded-xl border border-slate-100 hover:border-violet-200 hover:bg-violet-50/50 flex items-center gap-4 transition-all group">
          <div class="w-12 h-12 rounded-lg bg-violet-100 flex items-center justify-center text-violet-600 group-hover:scale-110 transition-transform">
            <span class="material-symbols-outlined">assignment</span>
          </div>
          <div>
            <h4 class="font-bold text-sm text-on-surface">Assignments</h4>
            <p class="text-xs text-slate-500 mt-0.5">Create and review assignments</p>
          </div>
        </a>

        <a href="students.php" class="p-4 rounded-xl border border-slate-100 hover:border-amber-200 hover:bg-amber-50/50 flex items-center gap-4 transition-all group">
          <div class="w-12 h-12 rounded-lg bg-amber-100 flex items-center justify-center text-amber-600 group-hover:scale-110 transition-transform">
            <span class="material-symbols-outlined">groups</span>
          </div>
          <div>
            <h4 class="font-bold text-sm text-on-surface">My Students</h4>
            <p class="text-xs text-slate-500 mt-0.5">View student information</p>
          </div>
        </a>

        <a href="../communication/conversations.php" class="p-4 rounded-xl border border-slate-100 hover:border-indigo-200 hover:bg-indigo-50/50 flex items-center gap-4 transition-all group">
          <div class="w-12 h-12 rounded-lg bg-indigo-100 flex items-center justify-center text-indigo-600 group-hover:scale-110 transition-transform">
            <span class="material-symbols-outlined">mail</span>
          </div>
          <div>
            <h4 class="font-bold text-sm text-on-surface">Messages</h4>
            <p class="text-xs text-slate-500 mt-0.5"><?php echo $unread_count > 0 ? "$unread_count unread messages" : 'No new messages'; ?></p>
          </div>
        </a>

        <a href="reports.php" class="p-4 rounded-xl border border-slate-100 hover:border-pink-200 hover:bg-pink-50/50 flex items-center gap-4 transition-all group">
          <div class="w-12 h-12 rounded-lg bg-pink-100 flex items-center justify-center text-pink-600 group-hover:scale-110 transition-transform">
            <span class="material-symbols-outlined">analytics</span>
          </div>
          <div>
            <h4 class="font-bold text-sm text-on-surface">Class Reports</h4>
            <p class="text-xs text-slate-500 mt-0.5">Generate analytical reports</p>
          </div>
        </a>

      </div>
    </div>

    <!-- Recent Activity (Right) -->
    <div class="col-span-1 bg-white p-6 rounded-xl border border-slate-200 shadow-sm flex flex-col">
      <h3 class="font-headline font-bold text-sm text-primary mb-6 flex items-center gap-2">
        <span class="material-symbols-outlined text-[18px]">history</span> Recent Activity
      </h3>
      
      <div class="flex-grow space-y-5">
        <?php if (!empty($today_attendance)): ?>
        <div class="flex gap-4">
            <div class="w-10 h-10 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center flex-shrink-0">
                <span class="material-symbols-outlined text-[18px]">rule</span>
            </div>
            <div>
                <p class="text-sm font-bold text-on-surface leading-tight">Today's Attendance</p>
                <p class="text-xs text-slate-500 mt-1"><?php echo $today_present; ?> present, <?php echo $today_late; ?> late, <?php echo $today_absent; ?> absent</p>
                <span class="inline-block mt-2 text-[10px] px-2 py-0.5 rounded-full bg-slate-100 text-slate-600 font-bold"><?php echo $today_rate; ?>% attendance rate</span>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($recent_assignments)): ?>
            <?php foreach (array_slice($recent_assignments, 0, 3) as $assignment): ?>
            <div class="flex gap-4">
                <div class="w-10 h-10 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center flex-shrink-0">
                    <span class="material-symbols-outlined text-[18px]">assignment_turned_in</span>
                </div>
                <div>
                    <p class="text-sm font-bold text-on-surface leading-tight"><?php echo htmlspecialchars($assignment['title'] ?? 'Assignment'); ?></p>
                    <p class="text-xs text-slate-500 mt-1"><?php echo htmlspecialchars($assignment['class_name'] ?? 'Class'); ?></p>
                    <span class="inline-block mt-1 text-[10px] text-slate-400"><?php echo date('M j, Y', strtotime($assignment['created_at'] ?? 'now')); ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if (empty($today_attendance) && empty($recent_assignments)): ?>
        <div class="flex flex-col items-center justify-center text-center h-full text-slate-400 pb-10">
            <span class="material-symbols-outlined text-4xl mb-3 opacity-20">inventory_2</span>
            <p class="text-xs max-w-[200px]">No recent activity logged. Start by taking attendance.</p>
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
