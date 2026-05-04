<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_admin('../login.php');

$page_title = 'Reports';
$page_icon = 'chart-line';
$full_name = $_SESSION['full_name'];

// Get filter parameters
$report_type = $_GET['type'] ?? 'attendance';
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$class_id = $_GET['class_id'] ?? '';
$student_id = $_GET['student_id'] ?? '';

$total_students = db()->count('students');
$total_classes = db()->count('classes');
$total_attendance = db()->count('attendance_records');

// Get classes for dropdown
$classes = db()->fetchAll("SELECT id, class_name, class_code FROM classes ORDER BY class_name");

// Get students for dropdown
$students = db()->fetchAll("SELECT s.id, s.admission_number, u.first_name, u.last_name FROM students s LEFT JOIN users u ON s.user_id = u.id ORDER BY u.first_name, u.last_name");

// Generate report data based on type
$report_data = [];
if ($_GET['generate'] ?? false) {
    switch ($report_type) {
        case 'attendance':
            $where = "DATE(ar.attendance_date) BETWEEN ? AND ?";
            $params = [$date_from, $date_to];

            if ($class_id) {
                $where .= " AND ar.class_id = ?";
                $params[] = $class_id;
            }
            if ($student_id) {
                $where .= " AND ar.student_id = ?";
                $params[] = $student_id;
            }

            $report_data = db()->fetchAll(
                "SELECT ar.*, s.admission_number as student_code, u.first_name, u.last_name,
                        c.class_name, c.class_code
                FROM attendance_records ar
                JOIN students s ON ar.student_id = s.id
                LEFT JOIN users u ON s.user_id = u.id
                JOIN classes c ON ar.class_id = c.id
                WHERE {$where}
                ORDER BY ar.attendance_date DESC, u.last_name, u.first_name",
                $params
            );
            break;

        case 'summary':
            $where = "DATE(ar.attendance_date) BETWEEN ? AND ?";
            $params = [$date_from, $date_to];

            if ($class_id) {
                $where .= " AND ar.class_id = ?";
                $params[] = $class_id;
            }

            $report_data = db()->fetchAll(
                "SELECT s.id, s.admission_number, u.first_name, u.last_name,
                        COUNT(CASE WHEN ar.status = 'present' THEN 1 END) as present_count,
                        COUNT(CASE WHEN ar.status = 'absent' THEN 1 END) as absent_count,
                        COUNT(CASE WHEN ar.status = 'late' THEN 1 END) as late_count,
                        COUNT(*) as total_records,
                        ROUND((COUNT(CASE WHEN ar.status = 'present' THEN 1 END) / COUNT(*)) * 100, 2) as attendance_rate
                FROM students s
                LEFT JOIN users u ON s.user_id = u.id
                LEFT JOIN attendance_records ar ON s.id = ar.student_id AND {$where}
                GROUP BY s.id, s.admission_number, u.first_name, u.last_name
                ORDER BY u.last_name, u.first_name",
                $params
            );
            break;
    }
}

// Start output buffering for master layout
ob_start();
?>

<div class="space-y-6">
    <section class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="rounded-2xl border border-outline-variant/20 bg-surface-container-lowest p-5 shadow-sm">
            <p class="text-[11px] font-bold uppercase tracking-widest text-outline mb-2">Total Students</p>
            <p class="text-3xl font-extrabold text-on-surface"><?php echo number_format($total_students); ?></p>
        </div>
        <div class="rounded-2xl border border-outline-variant/20 bg-surface-container-lowest p-5 shadow-sm">
            <p class="text-[11px] font-bold uppercase tracking-widest text-outline mb-2">Total Classes</p>
            <p class="text-3xl font-extrabold text-on-surface"><?php echo number_format($total_classes); ?></p>
        </div>
        <div class="rounded-2xl border border-outline-variant/20 bg-surface-container-lowest p-5 shadow-sm">
            <p class="text-[11px] font-bold uppercase tracking-widest text-outline mb-2">Attendance Records</p>
            <p class="text-3xl font-extrabold text-on-surface"><?php echo number_format($total_attendance); ?></p>
        </div>
    </section>

    <section class="rounded-2xl border border-outline-variant/20 bg-surface-container-lowest shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-outline-variant/10 bg-surface-container-low">
            <h3 class="text-sm font-extrabold uppercase tracking-wide text-on-surface">Report Filters</h3>
        </div>
        <div class="p-5">
            <form method="GET" action="" class="space-y-4">
                <input type="hidden" name="generate" value="1">
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-5 gap-4">
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-outline mb-1">Report Type</label>
                        <select name="type" required class="w-full rounded-lg border border-outline-variant/30 bg-surface-container-low px-3 py-2.5 text-sm text-on-surface">
                            <option value="attendance" <?php echo $report_type === 'attendance' ? 'selected' : ''; ?>>Detailed Attendance</option>
                            <option value="summary" <?php echo $report_type === 'summary' ? 'selected' : ''; ?>>Attendance Summary</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-outline mb-1">Date From</label>
                        <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>" required class="w-full rounded-lg border border-outline-variant/30 bg-surface-container-low px-3 py-2.5 text-sm text-on-surface">
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-outline mb-1">Date To</label>
                        <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>" required class="w-full rounded-lg border border-outline-variant/30 bg-surface-container-low px-3 py-2.5 text-sm text-on-surface">
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-outline mb-1">Class</label>
                        <select name="class_id" class="w-full rounded-lg border border-outline-variant/30 bg-surface-container-low px-3 py-2.5 text-sm text-on-surface">
                            <option value="">All Classes</option>
                            <?php foreach ($classes as $class): ?>
                                <option value="<?php echo $class['id']; ?>" <?php echo $class_id == $class['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($class['class_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php if ($report_type === 'attendance'): ?>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-outline mb-1">Student</label>
                            <select name="student_id" class="w-full rounded-lg border border-outline-variant/30 bg-surface-container-low px-3 py-2.5 text-sm text-on-surface">
                                <option value="">All Students</option>
                                <?php foreach ($students as $student): ?>
                                    <option value="<?php echo $student['id']; ?>" <?php echo $student_id == $student['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars(trim(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? ''))); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-primary text-on-primary px-4 py-2.5 text-sm font-bold hover:opacity-90">
                        <i class="fas fa-chart-bar"></i>
                        Generate Report
                    </button>
                    <?php if (!empty($_GET['generate'])): ?>
                        <a href="reports.php" class="inline-flex items-center gap-2 rounded-lg border border-outline-variant/30 px-4 py-2.5 text-sm font-semibold text-on-surface hover:bg-surface-container-low">
                            Reset Filters
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </section>

    <?php if (!empty($_GET['generate']) && empty($report_data)): ?>
        <section class="rounded-2xl border border-outline-variant/20 bg-surface-container-lowest p-8 text-center shadow-sm">
            <p class="text-on-surface font-bold mb-1">No records found for selected filters.</p>
            <p class="text-sm text-on-surface-variant">Try a wider date range or remove class/student filters.</p>
        </section>
    <?php endif; ?>

    <?php if (!empty($report_data)): ?>
        <section class="rounded-2xl border border-outline-variant/20 bg-surface-container-lowest shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-outline-variant/10 bg-surface-container-low flex flex-wrap items-center justify-between gap-3">
                <h3 class="text-sm font-extrabold uppercase tracking-wide text-on-surface">
                    <?php echo ucfirst($report_type); ?> Report (<?php echo count($report_data); ?> records)
                </h3>
                <div class="flex flex-wrap gap-2">
                    <button type="button" class="inline-flex items-center gap-2 rounded-lg border border-outline-variant/30 px-3 py-2 text-sm font-semibold text-on-surface hover:bg-surface-container-high" onclick="exportToCSV()">
                        <i class="fas fa-file-csv"></i>
                        Export CSV
                    </button>
                    <button type="button" class="inline-flex items-center gap-2 rounded-lg bg-primary text-on-primary px-3 py-2 text-sm font-bold hover:opacity-90" onclick="printReport()">
                        <i class="fas fa-print"></i>
                        Print
                    </button>
                </div>
            </div>

            <div class="overflow-x-auto">
                <?php if ($report_type === 'attendance'): ?>
                    <table class="min-w-full text-sm" id="reportTable">
                        <thead class="bg-surface-container text-on-surface-variant">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Date</th>
                                <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Student ID</th>
                                <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Student Name</th>
                                <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Class</th>
                                <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Check-in Time</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant/10">
                            <?php foreach ($report_data as $record): ?>
                                <?php
                                $status = strtolower((string)($record['status'] ?? 'absent'));
                                $statusClass = $status === 'present'
                                    ? 'bg-secondary-container text-secondary'
                                    : ($status === 'late' ? 'bg-primary-container text-primary' : 'bg-error-container text-error');
                                ?>
                                <tr class="hover:bg-surface-container-low">
                                    <td class="px-4 py-3"><?php echo date('M d, Y', strtotime($record['attendance_date'])); ?></td>
                                    <td class="px-4 py-3 font-medium"><?php echo htmlspecialchars((string)($record['student_code'] ?? '-')); ?></td>
                                    <td class="px-4 py-3"><?php echo htmlspecialchars(trim(($record['first_name'] ?? '') . ' ' . ($record['last_name'] ?? ''))); ?></td>
                                    <td class="px-4 py-3"><?php echo htmlspecialchars((string)($record['class_name'] ?? '-')); ?></td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-bold uppercase <?php echo $statusClass; ?>">
                                            <?php echo ucfirst($status); ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3"><?php echo !empty($record['check_in_time']) ? date('h:i A', strtotime($record['check_in_time'])) : '-'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <table class="min-w-full text-sm" id="reportTable">
                        <thead class="bg-surface-container text-on-surface-variant">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Student ID</th>
                                <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Student Name</th>
                                <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Present</th>
                                <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Absent</th>
                                <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Late</th>
                                <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Total Records</th>
                                <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Attendance Rate</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant/10">
                            <?php foreach ($report_data as $record): ?>
                                <?php
                                $rate = (float)($record['attendance_rate'] ?? 0);
                                $rateClass = $rate >= 80
                                    ? 'bg-secondary-container text-secondary'
                                    : ($rate >= 60 ? 'bg-primary-container text-primary' : 'bg-error-container text-error');
                                ?>
                                <tr class="hover:bg-surface-container-low">
                                    <td class="px-4 py-3 font-medium"><?php echo htmlspecialchars((string)($record['admission_number'] ?? '-')); ?></td>
                                    <td class="px-4 py-3"><?php echo htmlspecialchars(trim(($record['first_name'] ?? '') . ' ' . ($record['last_name'] ?? ''))); ?></td>
                                    <td class="px-4 py-3"><?php echo (int)($record['present_count'] ?? 0); ?></td>
                                    <td class="px-4 py-3"><?php echo (int)($record['absent_count'] ?? 0); ?></td>
                                    <td class="px-4 py-3"><?php echo (int)($record['late_count'] ?? 0); ?></td>
                                    <td class="px-4 py-3"><?php echo (int)($record['total_records'] ?? 0); ?></td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-bold <?php echo $rateClass; ?>">
                                            <?php echo number_format($rate, 2); ?>%
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>
</div>

<script>
    function exportToCSV() {
        const table = document.getElementById('reportTable');
        if (!table) {
            return;
        }
        let csv = [];

        for (let row of table.rows) {
            let cols = [];
            for (let cell of row.cells) {
                cols.push('"' + cell.textContent.replace(/"/g, '""') + '"');
            }
            csv.push(cols.join(','));
        }

        const csvContent = csv.join('\n');
        const blob = new Blob([csvContent], {
            type: 'text/csv'
        });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'report_' + new Date().toISOString().slice(0, 10) + '.csv';
        a.click();
    }

    function printReport() {
        window.print();
    }
</script>

<?php
// Capture output and use master layout
$page_content = ob_get_clean();
require BASE_PATH . '/resources/ui-core/layouts/master-dashboard.php';
?>
