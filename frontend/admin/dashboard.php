<?php

/**
 * SAMS Admin Dashboard — Academic Sentinel
 * Stitch UI: admin_dashboard_sams_overview_1
 */
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_admin('../login.php');
require_once PROJECT_ROOT . '/backend/modules/admin/AdminManager.php';

$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'] ?? ($_SESSION['first_name'] ?? 'Admin') . ' ' . ($_SESSION['last_name'] ?? '');
$dashboardManager = new AdminManager((int)($_SESSION['tenant_id'] ?? 1));
$dashboardPayload = $dashboardManager->getDashboardPayload();

$summary = $dashboardPayload['summary'] ?? [];
$attendanceToday = $dashboardPayload['attendance_today'] ?? [];
$recent_records = $dashboardPayload['recent_records'] ?? [];
$risk_students = $dashboardPayload['risk_students'] ?? [];

$total_students = (int)($summary['students'] ?? 0);
$total_teachers = (int)($summary['teachers'] ?? 0);
$total_classes = (int)($summary['classes'] ?? 0);
$total_parents = (int)($summary['parents'] ?? 0);

$today_total = (int)($attendanceToday['total'] ?? 0);
$today_present = (int)($attendanceToday['present'] ?? 0);
$today_late = (int)($attendanceToday['late'] ?? 0);
$today_absent = (int)($attendanceToday['absent'] ?? 0);
$today_rate = (float)($attendanceToday['rate'] ?? 0);

// Academic year
$academic_year = date('Y') . '/' . (date('Y') + 1);
?>
<?php
$page_title = 'Overview';
$page_icon = 'dashboard';
$page_subtitle = 'SAMS Institutional Dashboard · Academic Year ' . $academic_year;
ob_start();
?>

<!-- Bento Grid Dashboard -->
<div class="grid grid-cols-12 gap-6">
  <!-- Stats Cards Row -->
  <div class="col-span-12 lg:col-span-8 grid grid-cols-1 md:grid-cols-3 gap-6">
    <!-- Total Students -->
    <div class="bg-white p-6 rounded-xl border border-slate-200 flex flex-col justify-between shadow-sm">
      <div class="flex justify-between items-start">
        <span class="text-slate-400 font-label text-[10px] uppercase tracking-wider font-bold">Total Students</span>
        <span class="text-emerald-600 bg-emerald-50 text-[10px] px-2 py-0.5 rounded-full font-bold">Active</span>
      </div>
      <div class="mt-4">
        <span class="text-4xl font-extrabold font-headline text-primary"><?php echo number_format($total_students); ?></span>
        <p class="text-slate-500 text-xs mt-1">Enrolled across all classes</p>
      </div>
    </div>
    <!-- Faculty & Staff -->
    <div class="bg-white p-6 rounded-xl border border-slate-200 flex flex-col justify-between shadow-sm">
      <div class="flex justify-between items-start">
        <span class="text-slate-400 font-label text-[10px] uppercase tracking-wider font-bold">Faculty & Staff</span>
        <span class="text-blue-600 bg-blue-50 text-[10px] px-2 py-0.5 rounded-full font-bold">Stable</span>
      </div>
      <div class="mt-4">
        <span class="text-4xl font-extrabold font-headline text-primary"><?php echo number_format($total_teachers); ?></span>
        <p class="text-slate-500 text-xs mt-1">Active academic personnel</p>
      </div>
    </div>
    <!-- Attendance Rate -->
    <div class="bg-white p-6 rounded-xl border border-slate-200 flex flex-col justify-between shadow-sm">
      <div class="flex justify-between items-start">
        <span class="text-slate-400 font-label text-[10px] uppercase tracking-wider font-bold">Today's Attendance</span>
        <?php if ($today_rate >= 90): ?>
          <span class="text-emerald-600 bg-emerald-50 text-[10px] px-2 py-0.5 rounded-full font-bold">On Track</span>
        <?php else: ?>
          <span class="text-amber-600 bg-amber-50 text-[10px] px-2 py-0.5 rounded-full font-bold"><?php echo $today_present; ?>/<?php echo $today_total; ?></span>
        <?php endif; ?>
      </div>
      <div class="mt-4">
        <span class="text-4xl font-extrabold font-headline text-primary"><?php echo $today_rate; ?>%</span>
        <p class="text-slate-500 text-xs mt-1"><?php echo $today_present; ?> present · <?php echo $today_late; ?> late · <?php echo $today_absent; ?> absent</p>
      </div>
    </div>
  </div>

  <!-- System Status Widget -->
  <div class="col-span-12 lg:col-span-4 bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
    <div class="flex justify-between items-center mb-6">
      <h3 class="font-headline font-bold text-sm text-primary">System Status</h3>
      <span class="flex h-2 w-2 rounded-full bg-emerald-500"></span>
    </div>
    <div class="space-y-4">
      <div class="flex items-center justify-between">
        <span class="text-xs text-slate-600">Active Classes</span>
        <span class="text-sm font-bold text-primary"><?php echo number_format($total_classes); ?></span>
      </div>
      <div class="flex items-center justify-between">
        <span class="text-xs text-slate-600">Parents Linked</span>
        <span class="text-sm font-bold text-primary"><?php echo number_format($total_parents); ?></span>
      </div>
      <div class="flex items-center justify-between">
        <span class="text-xs text-slate-600">At-Risk Students</span>
        <span class="text-sm font-bold <?php echo count($risk_students) > 0 ? 'text-amber-600' : 'text-emerald-600'; ?>"><?php echo count($risk_students); ?></span>
      </div>
      <div class="pt-4 border-t border-slate-100 mt-2">
        <div class="flex justify-between items-center">
          <span class="text-[10px] text-slate-400 font-bold uppercase tracking-widest">Server Status</span>
          <span class="flex items-center gap-1 text-xs font-bold text-emerald-600">
            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 inline-block"></span>
            Operational
          </span>
        </div>
      </div>
    </div>
  </div>

  <!-- Attendance Weekly Chart -->
  <div class="col-span-12 lg:col-span-8 bg-white p-8 rounded-xl border border-slate-200 shadow-sm">
    <div class="flex justify-between items-center mb-10">
      <div>
        <h3 class="font-headline font-bold text-lg text-primary">Attendance Analytics</h3>
        <p class="text-xs text-slate-500">7-day rolling engagement window</p>
      </div>
      <div class="flex space-x-2">
        <button class="px-3 py-1 bg-slate-100 rounded text-[10px] font-bold text-primary">MTD</button>
        <button class="px-3 py-1 hover:bg-slate-50 rounded text-[10px] font-bold text-slate-400 transition-colors">YTD</button>
      </div>
    </div>
    <!-- Chart placeholder bars -->
    <div class="h-48 relative flex items-end space-x-4" id="attendance-chart">
      <?php
      $days = ['MON','TUE','WED','THU','FRI','SAT','SUN'];
      $heights = [80, 90, 85, 95, 75, 40, 40];
      $fills = [85, 92, 78, 95, 65, 0, 0];
      foreach ($days as $i => $day):
        $isWeekend = $i >= 5;
      ?>
      <div class="flex-1 bg-slate-50 rounded-t-lg relative <?php echo $isWeekend ? 'opacity-50' : 'group'; ?>" style="height:<?php echo $heights[$i]; ?>%">
        <?php if (!$isWeekend): ?>
        <div class="absolute inset-x-0 bottom-0 bg-primary rounded-t-lg group-hover:bg-primary transition-all" style="height:<?php echo $fills[$i]; ?>%"></div>
        <?php endif; ?>
        <span class="absolute -bottom-6 inset-x-0 text-[10px] text-center text-slate-400"><?php echo $day; ?></span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Recent Activity Logs -->
  <div class="col-span-12 lg:col-span-4 bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
    <h3 class="font-headline font-bold text-sm text-primary mb-6">Recent Activity</h3>
    <?php if (!empty($recent_records)): ?>
      <div class="space-y-5">
        <?php
        $status_config = [
          'present' => ['icon' => 'check_circle', 'bg' => 'bg-emerald-50', 'text' => 'text-emerald-600'],
          'late'    => ['icon' => 'schedule',     'bg' => 'bg-amber-50',   'text' => 'text-amber-600'],
          'absent'  => ['icon' => 'cancel',       'bg' => 'bg-rose-50',    'text' => 'text-rose-600'],
        ];
        foreach (array_slice($recent_records, 0, 5) as $record):
          $s = $status_config[$record['status'] ?? 'absent'] ?? $status_config['absent'];
        ?>
        <div class="flex space-x-3">
          <div class="w-8 h-8 rounded-lg <?php echo $s['bg']; ?> flex items-center justify-center flex-shrink-0">
            <span class="material-symbols-outlined <?php echo $s['text']; ?> text-sm"><?php echo $s['icon']; ?></span>
          </div>
          <div>
            <p class="text-xs font-bold text-on-surface leading-tight"><?php echo htmlspecialchars($record['student_name'] ?? 'Unknown'); ?></p>
            <p class="text-[10px] text-slate-500 mt-0.5"><?php echo htmlspecialchars($record['class_name'] ?? 'N/A'); ?> · <?php echo ucfirst($record['status'] ?? 'N/A'); ?></p>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="sams-empty-state">
        <span class="material-symbols-outlined empty-icon">inbox</span>
        <p class="empty-text">No recent activity</p>
      </div>
    <?php endif; ?>
    <a href="audit-logs.php" class="block w-full mt-6 py-3 text-[10px] font-bold text-slate-400 uppercase tracking-widest hover:text-primary transition-colors border-t border-slate-100 text-center">
      View Full Activity Log
    </a>
  </div>

  <!-- Bottom Row: Announcement + Quick Actions -->
  <div class="col-span-12 grid grid-cols-1 md:grid-cols-2 gap-6">
    <!-- Campus Bulletin -->
    <div class="bg-primary text-white p-8 rounded-xl relative overflow-hidden group shadow-lg" style="background:linear-gradient(135deg, #000666 0%, #1a237e 100%);">
      <div class="absolute -right-10 -bottom-10 opacity-10 group-hover:scale-110 transition-transform duration-700">
        <span class="material-symbols-outlined" style="font-size:180px">campaign</span>
      </div>
      <div class="relative z-10">
        <span class="text-[10px] font-bold uppercase tracking-widest text-blue-200">Campus Bulletin</span>
        <h4 class="text-xl font-headline font-bold mt-2">Welcome, <?php echo htmlspecialchars(explode(' ', $full_name)[0]); ?>!</h4>
        <p class="text-sm text-blue-100 mt-3 opacity-90">
          <?php if (count($risk_students) > 0): ?>
            <?php echo count($risk_students); ?> student(s) are at risk with high absence rates. Review and take action.
          <?php else: ?>
            All students are in good attendance standing. Keep up the great work!
          <?php endif; ?>
        </p>
        <a href="reports.php" class="mt-6 flex items-center text-xs font-bold border-b-2 border-blue-400 pb-1 hover:text-white hover:border-white transition-all w-fit">
          View Reports <span class="material-symbols-outlined ml-2 text-sm">arrow_forward</span>
        </a>
      </div>
    </div>

    <!-- Quick Actions Grid -->
    <div class="bg-white p-8 rounded-xl border border-slate-200 shadow-sm">
      <h4 class="font-headline font-bold text-primary mb-6">Quick Actions</h4>
      <div class="grid grid-cols-2 gap-3">
        <a href="students.php" class="flex items-center gap-3 p-3 rounded-xl hover:bg-slate-50 transition-colors">
          <div class="w-10 h-10 rounded-lg bg-blue-50 flex items-center justify-center">
            <span class="material-symbols-outlined text-blue-600">school</span>
          </div>
          <span class="text-xs font-bold text-on-surface">Manage Students</span>
        </a>
        <a href="attendance.php" class="flex items-center gap-3 p-3 rounded-xl hover:bg-slate-50 transition-colors">
          <div class="w-10 h-10 rounded-lg bg-emerald-50 flex items-center justify-center">
            <span class="material-symbols-outlined text-emerald-600">fact_check</span>
          </div>
          <span class="text-xs font-bold text-on-surface">Mark Attendance</span>
        </a>
        <a href="reports.php" class="flex items-center gap-3 p-3 rounded-xl hover:bg-slate-50 transition-colors">
          <div class="w-10 h-10 rounded-lg bg-violet-50 flex items-center justify-center">
            <span class="material-symbols-outlined text-violet-600">assessment</span>
          </div>
          <span class="text-xs font-bold text-on-surface">View Reports</span>
        </a>
        <a href="classes.php" class="flex items-center gap-3 p-3 rounded-xl hover:bg-slate-50 transition-colors">
          <div class="w-10 h-10 rounded-lg bg-amber-50 flex items-center justify-center">
            <span class="material-symbols-outlined text-amber-600">meeting_room</span>
          </div>
          <span class="text-xs font-bold text-on-surface">Manage Classes</span>
        </a>
      </div>
    </div>
  </div>

  <!-- At-Risk Students -->
  <?php if (!empty($risk_students)): ?>
  <div class="col-span-12 bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
    <div class="flex justify-between items-center mb-6">
      <div>
        <h3 class="font-headline font-bold text-sm text-primary flex items-center gap-2">
          <span class="material-symbols-outlined text-amber-600 text-lg">warning</span>
          Students at Risk
        </h3>
        <p class="text-xs text-slate-500 mt-1">Students with >20% absence in the last 30 days</p>
      </div>
    </div>
    <div class="overflow-x-auto">
      <table class="sams-table">
        <thead>
          <tr>
            <th>Student</th>
            <th>ID</th>
            <th>Total Days</th>
            <th>Absent Days</th>
            <th>Absence Rate</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($risk_students as $student):
            $absence_rate = round(($student['absent_days'] / $student['total_days']) * 100, 1);
          ?>
          <tr>
            <td class="font-semibold"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></td>
            <td class="text-slate-500"><?php echo htmlspecialchars($student['admission_number'] ?? $student['id']); ?></td>
            <td><?php echo $student['total_days']; ?></td>
            <td class="text-rose-600 font-bold"><?php echo $student['absent_days']; ?></td>
            <td>
              <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold <?php echo $absence_rate > 40 ? 'bg-rose-50 text-rose-600' : 'bg-amber-50 text-amber-600'; ?>">
                <?php echo $absence_rate; ?>%
              </span>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>
</div>

<?php
$page_content = ob_get_clean();
require BASE_PATH . '/resources/ui-core/layouts/master-dashboard.php';
?>
