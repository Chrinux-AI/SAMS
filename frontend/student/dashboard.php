<?php

/**
 * SAMS Student Dashboard - Modern AI-Enhanced Interface
 * Professional dashboard with AI insights and modern UI
 */
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/database.php';
require_login('../login.php');
if (!has_role('student')) {
    redirect('../login.php', 'Student access required.', 'error');
}

$student_id = $_SESSION['user_id'];
$tenantId = $_SESSION['tenant_id'] ?? 1;
$full_name = $_SESSION['full_name'];

// Get student data
$student = db()->fetchOne("
    SELECT s.*, u.email, u.created_at
    FROM students s
    JOIN users u ON s.user_id = u.id
    WHERE s.user_id = ?
", [$student_id]);

// Attendance summary
$records = db()->fetchAll("SELECT * FROM attendance_records WHERE student_id = ?", [$student_id]);
$present = count(array_filter($records, fn($r) => $r['status'] === 'present'));
$late = count(array_filter($records, fn($r) => $r['status'] === 'late'));
$absent = count(array_filter($records, fn($r) => $r['status'] === 'absent'));
$total = count($records);
$attendance_rate = $total > 0 ? round((($present + $late) / $total) * 100, 1) : 0;

// Student's classes
$classes = db()->fetchAll("
    SELECT c.*, ce.enrollment_date
    FROM classes c
    JOIN class_enrollments ce ON c.id = ce.class_id
    WHERE ce.student_id = ?
", [$student_id]);

// Today's attendance
$today = date('Y-m-d');
$today_record = db()->fetchOne("
    SELECT ar.*, c.class_name
    FROM attendance_records ar
    JOIN classes c ON ar.class_id = c.id
    WHERE ar.student_id = ? AND DATE(ar.check_in_time) = ?
    ORDER BY ar.check_in_time DESC
", [$student_id, $today]);

// Recent grades
$recent_grades = db()->fetchAll("
    SELECT g.*, a.title, c.class_name
    FROM grades g
    JOIN assignments a ON g.assignment_id = a.id
    JOIN classes c ON a.class_id = c.id
    JOIN class_enrollments ce ON c.id = ce.class_id
    WHERE ce.student_id = ?
    ORDER BY g.created_at DESC
    LIMIT 5
", [$student_id]);

// Upcoming assignments
$upcoming_assignments = db()->fetchAll("
    SELECT a.*, c.class_name
    FROM assignments a
    JOIN classes c ON a.class_id = c.id
    JOIN class_enrollments ce ON c.id = ce.class_id
    WHERE ce.student_id = ? AND a.due_date >= CURDATE()
    ORDER BY a.due_date ASC
    LIMIT 5
", [$student_id]);

// Unread messages
$unread_count = db()->fetchOne("
    SELECT COUNT(*) as count FROM message_recipients
    WHERE recipient_id = ? AND is_read = 0 AND deleted_at IS NULL
", [$student_id])['count'] ?? 0;

// AI Insights
$ai_insights = [];
try {
    require_once __DIR__ . '/../includes/sams-init.php';
    try {
        if (class_exists('SAMS_StudentBot')) {
            $studentBot = new SAMS_StudentBot();
            $ai_insights = $studentBot->getStudentInsights($student_id, $tenantId);
        }
    } catch (Throwable $e) {
        // Fallback insights
        $ai_insights = [
            'performance_trend' => $attendance_rate > 85 ? 'excellent' : ($attendance_rate > 70 ? 'good' : 'needs_improvement'),
            'recommendation' => 'Focus on maintaining consistent attendance',
            'study_suggestions' => ['Review daily notes', 'Complete assignments on time', 'Ask for help when needed']
        ];
    }
} catch (Throwable $e) {
    $ai_insights = [
        'performance_trend' => 'good',
        'recommendation' => 'Keep up the good work!',
        'study_suggestions' => ['Stay organized', 'Participate in class', 'Study regularly']
    ];
}

$csrf = generate_csrf_token();

// Master layout configuration
$page_title = 'Student Dashboard';
$page_icon = 'fas fa-user-graduate';
$page_subtitle = 'Welcome back, ' . htmlspecialchars($full_name);

ob_start();
?>
<!-- Bento Grid Dashboard -->
<div class="grid grid-cols-12 gap-6">

  <!-- Student Welcome Banner & AI Insights (Top Full Width) -->
  <div class="col-span-12 grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Welcome Banner (2 cols wide) -->
    <div class="lg:col-span-2 bg-indigo-700 text-white p-8 rounded-xl relative overflow-hidden group shadow-lg" style="background:linear-gradient(135deg, #3730A3 0%, #312E81 100%);">
      <div class="absolute -right-10 -bottom-10 opacity-10 group-hover:scale-110 transition-transform duration-700 pointer-events-none">
        <span class="material-symbols-outlined" style="font-size:180px">school</span>
      </div>
      <div class="relative z-10 h-full flex flex-col justify-between">
        <div>
          <span class="text-[10px] font-bold uppercase tracking-widest text-indigo-200">Student Portal</span>
          <h1 class="text-3xl font-headline font-bold mt-2">Welcome back, <?php echo htmlspecialchars(explode(' ', $full_name)[0]); ?>!</h1>
          <p class="text-indigo-100 mt-2 max-w-lg opacity-90">Stay on top of your <?php echo count($classes); ?> enrolled classes, review assignments, and monitor your academic progress today.</p>
        </div>
        <div class="mt-6 flex gap-4">
            <a href="assignments.php" class="px-5 py-2.5 bg-white text-indigo-700 font-bold rounded-lg text-sm hover:shadow-lg hover:scale-105 transition-all w-fit flex items-center gap-2">
                <span class="material-symbols-outlined text-sm">assignment</span> View Assignments
            </a>
            <a href="schedule.php" class="px-5 py-2.5 bg-indigo-800 border border-indigo-500/50 text-white font-bold rounded-lg text-sm hover:bg-indigo-900 transition-all w-fit flex items-center gap-2">
                <span class="material-symbols-outlined text-sm">calendar_month</span> Class Schedule
            </a>
        </div>
      </div>
    </div>
    
    <!-- AI Study Assistant Widget -->
    <div class="col-span-1 lg:col-span-1 bg-gradient-to-br from-violet-50 to-purple-50 border border-violet-100 p-6 rounded-xl flex flex-col shadow-sm">
        <div class="flex items-center gap-2 mb-4">
            <span class="material-symbols-outlined text-violet-600">psychology</span>
            <h3 class="font-headline font-bold text-violet-800">AI Study Assistant</h3>
        </div>
        <p class="text-sm font-medium text-violet-900/80 mb-4 flex-grow"><?php echo htmlspecialchars($ai_insights['recommendation'] ?? 'Keep up the good work! Continue maintaining consistent attendance.'); ?></p>
        
        <?php if (!empty($ai_insights['study_suggestions'])): ?>
            <div class="mb-4 space-y-2">
                <span class="text-[10px] font-bold uppercase tracking-widest text-violet-500">Study Tips</span>
                <ul class="space-y-1.5">
                    <?php foreach (array_slice($ai_insights['study_suggestions'], 0, 2) as $tip): ?>
                        <li class="flex items-start gap-2 text-xs text-violet-800 font-medium leading-tight">
                            <span class="material-symbols-outlined text-[14px] text-violet-400">lightbulb</span> <?php echo htmlspecialchars($tip); ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="space-y-3 pt-3 border-t border-violet-200/50">
            <div class="flex justify-between items-center text-sm">
                <span class="text-violet-700 font-semibold text-xs tracking-wide uppercase">Performance</span>
                <?php $pt = $ai_insights['performance_trend'] ?? 'good'; ?>
                <span class="px-2.5 py-1 rounded-md text-[10px] font-bold uppercase tracking-wider
                <?php 
                    echo $pt === 'needs_improvement' ? 'bg-red-100 text-red-700' : ($pt === 'good' ? 'bg-blue-100 text-blue-700' : 'bg-emerald-100 text-emerald-700');
                ?>">
                    <?php echo htmlspecialchars(str_replace('_', ' ', $pt)); ?>
                </span>
            </div>
        </div>
    </div>
  </div>

  <!-- Today's Attendance Snapshot Bar -->
  <div class="col-span-12 bg-white rounded-xl border border-slate-200 shadow-sm p-4 flex flex-wrap items-center justify-between gap-4">
    <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-full bg-slate-50 border border-slate-100 flex items-center justify-center">
            <span class="material-symbols-outlined text-slate-400">today</span>
        </div>
        <div>
            <h4 class="text-xs font-bold text-slate-500 uppercase tracking-widest">Today's Status</h4>
            <p class="text-sm font-bold text-on-surface">
                <?php echo date('l, M j, Y'); ?>
            </p>
        </div>
    </div>
    
    <div class="flex items-center gap-6">
        <div class="hidden sm:block">
            <span class="text-xs text-slate-500 block mb-0.5">Classes Today</span>
            <span class="text-sm font-bold text-on-surface"><?php echo count($classes); ?> Sessions</span>
        </div>
        <div class="w-px h-8 bg-slate-200 hidden sm:block"></div>
        <div>
            <span class="text-xs text-slate-500 block mb-0.5">Check-in Status</span>
            <?php if ($today_record): 
                $bg = $today_record['status'] === 'present' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 
                     ($today_record['status'] === 'late' ? 'bg-amber-50 text-amber-700 border-amber-200' : 'bg-rose-50 text-rose-700 border-rose-200');
                $icon = $today_record['status'] === 'present' ? 'check_circle' : ($today_record['status'] === 'late' ? 'schedule' : 'cancel');
            ?>
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg text-xs font-bold border <?php echo $bg; ?>">
                    <span class="material-symbols-outlined text-[14px]"><?php echo $icon; ?></span>
                    <?php echo htmlspecialchars(ucfirst($today_record['status'])); ?>
                </span>
            <?php else: ?>
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg text-xs font-bold border bg-slate-50 text-slate-600 border-slate-200">
                    <span class="material-symbols-outlined text-[14px]">more_time</span>
                    Not Marked
                </span>
            <?php endif; ?>
        </div>
    </div>
  </div>

  <!-- Stats Cards Row -->
  <div class="col-span-12 grid grid-cols-2 lg:grid-cols-4 gap-6">
    <div class="bg-white p-6 rounded-xl border border-slate-200 flex flex-col shadow-sm hover:border-indigo-500/30 transition-colors group">
      <span class="text-slate-400 font-label text-[10px] uppercase tracking-wider font-bold mb-4">Overall Attendance</span>
      <span class="text-3xl font-extrabold font-headline <?php echo $attendance_rate >= 85 ? 'text-emerald-600' : 'text-amber-600'; ?> transition-colors"><?php echo $attendance_rate; ?>%</span>
    </div>
    <div class="bg-white p-6 rounded-xl border border-slate-200 flex flex-col shadow-sm hover:border-indigo-500/30 transition-colors group">
      <span class="text-slate-400 font-label text-[10px] uppercase tracking-wider font-bold mb-4">Enrolled Classes</span>
      <span class="text-3xl font-extrabold font-headline text-primary group-hover:text-indigo-600 transition-colors"><?php echo count($classes); ?></span>
    </div>
    <div class="bg-white p-6 rounded-xl border border-slate-200 flex flex-col shadow-sm hover:border-indigo-500/30 transition-colors group">
      <span class="text-slate-400 font-label text-[10px] uppercase tracking-wider font-bold mb-4">Pending Tasks</span>
      <span class="text-3xl font-extrabold font-headline text-primary group-hover:text-indigo-600 transition-colors"><?php echo count($upcoming_assignments); ?></span>
    </div>
    <div class="bg-white p-6 rounded-xl border border-slate-200 flex flex-col shadow-sm hover:border-indigo-500/30 transition-colors group relative">
      <span class="text-slate-400 font-label text-[10px] uppercase tracking-wider font-bold mb-4">Unread Messages</span>
      <span class="text-3xl font-extrabold font-headline text-primary group-hover:text-indigo-600 transition-colors"><?php echo $unread_count; ?></span>
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
        
        <a href="attendance.php" class="p-4 rounded-xl border border-slate-100 hover:border-indigo-200 hover:bg-indigo-50/50 flex flex-col items-center text-center transition-all group">
          <div class="w-12 h-12 rounded-full bg-indigo-50 flex items-center justify-center text-indigo-600 mb-3 group-hover:bg-indigo-600 group-hover:text-white transition-colors">
            <span class="material-symbols-outlined">event_available</span>
          </div>
          <h4 class="font-bold text-sm text-on-surface">My Attendance</h4>
        </a>

        <a href="grades.php" class="p-4 rounded-xl border border-slate-100 hover:border-emerald-200 hover:bg-emerald-50/50 flex flex-col items-center text-center transition-all group">
          <div class="w-12 h-12 rounded-full bg-emerald-50 flex items-center justify-center text-emerald-600 mb-3 group-hover:bg-emerald-600 group-hover:text-white transition-colors">
            <span class="material-symbols-outlined">analytics</span>
          </div>
          <h4 class="font-bold text-sm text-on-surface">View Grades</h4>
        </a>

        <a href="assignments.php" class="p-4 rounded-xl border border-slate-100 hover:border-amber-200 hover:bg-amber-50/50 flex flex-col items-center text-center transition-all group">
          <div class="w-12 h-12 rounded-full bg-amber-50 flex items-center justify-center text-amber-600 mb-3 group-hover:bg-amber-600 group-hover:text-white transition-colors">
            <span class="material-symbols-outlined">assignment</span>
          </div>
          <h4 class="font-bold text-sm text-on-surface">Assignments</h4>
        </a>

        <a href="schedule.php" class="p-4 rounded-xl border border-slate-100 hover:border-blue-200 hover:bg-blue-50/50 flex flex-col items-center text-center transition-all group">
          <div class="w-12 h-12 rounded-full bg-blue-50 flex items-center justify-center text-blue-600 mb-3 group-hover:bg-blue-600 group-hover:text-white transition-colors">
            <span class="material-symbols-outlined">calendar_month</span>
          </div>
          <h4 class="font-bold text-sm text-on-surface">Class Schedule</h4>
        </a>

        <a href="../communication/conversations.php" class="p-4 rounded-xl border border-slate-100 hover:border-violet-200 hover:bg-violet-50/50 flex flex-col items-center text-center transition-all group relative">
          <?php if($unread_count > 0): ?>
            <span class="absolute top-4 right-4 text-[10px] bg-rose-500 text-white font-bold px-1.5 py-0.5 rounded-full"><?php echo $unread_count; ?></span>
          <?php endif; ?>
          <div class="w-12 h-12 rounded-full bg-violet-50 flex items-center justify-center text-violet-600 mb-3 group-hover:bg-violet-600 group-hover:text-white transition-colors">
            <span class="material-symbols-outlined">forum</span>
          </div>
          <h4 class="font-bold text-sm text-on-surface">Inbox</h4>
        </a>

        <a href="profile.php" class="p-4 rounded-xl border border-slate-100 hover:border-rose-200 hover:bg-rose-50/50 flex flex-col items-center text-center transition-all group">
          <div class="w-12 h-12 rounded-full bg-rose-50 flex items-center justify-center text-rose-600 mb-3 group-hover:bg-rose-600 group-hover:text-white transition-colors">
            <span class="material-symbols-outlined">person</span>
          </div>
          <h4 class="font-bold text-sm text-on-surface">My Profile</h4>
        </a>

      </div>
    </div>

    <!-- Recent Grades & Upcoming (Right) -->
    <div class="col-span-1 bg-white p-6 rounded-xl border border-slate-200 shadow-sm flex flex-col">
      <h3 class="font-headline font-bold text-sm text-primary mb-6 flex items-center gap-2">
        <span class="material-symbols-outlined text-[18px]">update</span> Activity Feed
      </h3>
      
      <div class="flex-grow space-y-5">
        
        <?php if (!empty($upcoming_assignments)): ?>
            <?php foreach (array_slice($upcoming_assignments, 0, 2) as $assignment): ?>
            <div class="flex gap-4 p-3 rounded-lg border border-amber-100 bg-amber-50/30">
                <div class="w-8 h-8 rounded-md bg-amber-100 text-amber-600 flex items-center justify-center flex-shrink-0 mt-0.5">
                    <span class="material-symbols-outlined text-[16px]">priority_high</span>
                </div>
                <div>
                    <p class="text-[10px] font-bold text-amber-600 uppercase tracking-wider mb-0.5">Upcoming Deadline</p>
                    <p class="text-sm font-bold text-on-surface leading-tight"><?php echo htmlspecialchars($assignment['title'] ?? 'Assignment'); ?></p>
                    <p class="text-[11px] text-slate-500 mt-1"><?php echo htmlspecialchars($assignment['class_name'] ?? 'Class'); ?></p>
                    <span class="inline-block mt-1 text-[11px] font-bold <?php echo strtotime($assignment['due_date']) < strtotime('+2 days') ? 'text-rose-600' : 'text-slate-600'; ?>">
                        Due: <?php echo date('M j, Y', strtotime($assignment['due_date'] ?? 'now')); ?>
                    </span>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if (!empty($recent_grades)): ?>
            <?php foreach (array_slice($recent_grades, 0, 3) as $grade): 
                $g = $grade['grade'] ?? 'C';
                $cBadge = $g == 'A' || $g == 'B' ? 'bg-emerald-100 text-emerald-700' : ($g == 'C' ? 'bg-blue-100 text-blue-700' : 'bg-rose-100 text-rose-700');
            ?>
            <div class="flex gap-4">
                <div class="w-10 h-10 rounded-full bg-slate-50 border border-slate-100 flex items-center justify-center flex-shrink-0 relative">
                    <span class="material-symbols-outlined text-slate-400 text-[18px]">workspace_premium</span>
                    <span class="absolute -bottom-1 -right-1 w-5 h-5 rounded-full flex items-center justify-center text-[10px] font-bold <?php echo $cBadge; ?> border-2 border-white shadow-sm">
                        <?php echo htmlspecialchars($g); ?>
                    </span>
                </div>
                <div>
                    <p class="text-sm font-bold text-on-surface leading-snug"><?php echo htmlspecialchars($grade['title'] ?? 'Assignment'); ?></p>
                    <p class="text-[11px] text-slate-500 mt-0.5"><?php echo htmlspecialchars($grade['class_name'] ?? 'Class'); ?></p>
                    <span class="inline-block mt-1 text-[10px] text-slate-400"><?php echo date('M j, Y', strtotime($grade['created_at'] ?? 'now')); ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if (empty($recent_grades) && empty($upcoming_assignments)): ?>
        <div class="flex flex-col items-center justify-center text-center h-full text-slate-400 pb-10 pt-4">
            <span class="material-symbols-outlined text-4xl mb-3 opacity-20">celebration</span>
            <p class="text-xs max-w-[200px]">All caught up! No recent grades or upcoming deadlines.</p>
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
