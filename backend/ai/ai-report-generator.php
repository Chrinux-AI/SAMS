<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';

class AIReportGenerator
{
  /**
   * Summary stats from attendance table for a date range
   */
  public function generateAttendanceReport($tenantId, $dateFrom, $dateTo)
  {
    try {
      $summary = db()->fetchOne(
        "SELECT COUNT(*) as total_records,
                        SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present,
                        SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent,
                        SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) as late,
                        COUNT(DISTINCT a.user_id) as unique_students,
                        COUNT(DISTINCT a.date) as total_days
                 FROM attendance a
                 JOIN users u ON a.user_id = u.id AND u.tenant_id = ?
                 WHERE a.date BETWEEN ? AND ?",
        [$tenantId, $dateFrom, $dateTo]
      );

      $dailyBreakdown = db()->fetchAll(
        "SELECT a.date,
                        COUNT(*) as total,
                        SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present,
                        SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent
                 FROM attendance a
                 JOIN users u ON a.user_id = u.id AND u.tenant_id = ?
                 WHERE a.date BETWEEN ? AND ?
                 GROUP BY a.date
                 ORDER BY a.date ASC",
        [$tenantId, $dateFrom, $dateTo]
      );

      return [
        'report_type' => 'attendance',
        'period' => ['from' => $dateFrom, 'to' => $dateTo],
        'summary' => $summary,
        'daily_breakdown' => $dailyBreakdown,
        'generated_at' => date('Y-m-d H:i:s')
      ];
    } catch (Exception $e) {
      error_log("AIReportGenerator::generateAttendanceReport error: " . $e->getMessage());
      return ['error' => $e->getMessage()];
    }
  }

  /**
   * Income vs expenses summary for a date range
   */
  public function generateFinancialReport($tenantId, $dateFrom, $dateTo)
  {
    try {
      $income = db()->fetchOne(
        "SELECT COALESCE(SUM(amount), 0) as total_income, COUNT(*) as transaction_count
                 FROM payments
                 WHERE tenant_id = ? AND payment_date BETWEEN ? AND ?",
        [$tenantId, $dateFrom, $dateTo]
      );

      $expenses = db()->fetchOne(
        "SELECT COALESCE(SUM(amount), 0) as total_expenses, COUNT(*) as transaction_count
                 FROM expenses
                 WHERE tenant_id = ? AND created_at BETWEEN ? AND ?",
        [$tenantId, $dateFrom, $dateTo]
      );

      $totalIncome = $income ? (float)$income['total_income'] : 0;
      $totalExpenses = $expenses ? (float)$expenses['total_expenses'] : 0;

      return [
        'report_type' => 'financial',
        'period' => ['from' => $dateFrom, 'to' => $dateTo],
        'income' => [
          'total' => $totalIncome,
          'transactions' => $income ? $income['transaction_count'] : 0
        ],
        'expenses' => [
          'total' => $totalExpenses,
          'transactions' => $expenses ? $expenses['transaction_count'] : 0
        ],
        'net' => $totalIncome - $totalExpenses,
        'generated_at' => date('Y-m-d H:i:s')
      ];
    } catch (Exception $e) {
      error_log("AIReportGenerator::generateFinancialReport error: " . $e->getMessage());
      return ['error' => $e->getMessage()];
    }
  }

  /**
   * Individual student attendance + grades summary
   */
  public function generateStudentReport($studentId)
  {
    try {
      $student = db()->fetchOne(
        "SELECT id, full_name, email, role, class_id FROM users WHERE id = ?",
        [$studentId]
      );

      $attendance = db()->fetchOne(
        "SELECT COUNT(*) as total,
                        SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
                        SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent,
                        SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late
                 FROM attendance
                 WHERE user_id = ?",
        [$studentId]
      );

      $grades = db()->fetchAll(
        "SELECT subject, score, grade, created_at
                 FROM grades
                 WHERE student_id = ?
                 ORDER BY created_at DESC
                 LIMIT 20",
        [$studentId]
      );

      $attendanceRate = 0;
      if ($attendance && $attendance['total'] > 0) {
        $attendanceRate = round(($attendance['present'] / $attendance['total']) * 100, 1);
      }

      return [
        'report_type' => 'student',
        'student' => $student,
        'attendance' => [
          'summary' => $attendance,
          'rate' => $attendanceRate
        ],
        'grades' => $grades,
        'generated_at' => date('Y-m-d H:i:s')
      ];
    } catch (Exception $e) {
      error_log("AIReportGenerator::generateStudentReport error: " . $e->getMessage());
      return ['error' => $e->getMessage()];
    }
  }

  /**
   * Server stats, table row counts, recent audit activity
   */
  public function generateSystemReport()
  {
    try {
      // Table row counts
      $tables = ['users', 'attendance', 'classes', 'audit_logs'];
      $tableCounts = [];
      foreach ($tables as $table) {
        $result = db()->fetchOne("SELECT COUNT(*) as cnt FROM {$table}");
        $tableCounts[$table] = $result ? (int)$result['cnt'] : 0;
      }

      // Recent audit activity
      $recentActivity = db()->fetchAll(
        "SELECT action, COUNT(*) as cnt
                 FROM audit_logs
                 WHERE created_at >= NOW() - INTERVAL 24 HOUR
                 GROUP BY action
                 ORDER BY cnt DESC
                 LIMIT 10"
      );

      return [
        'report_type' => 'system',
        'server' => [
          'php_version' => PHP_VERSION,
          'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
          'disk_free' => disk_free_space('/') !== false ? round(disk_free_space('/') / 1073741824, 2) . ' GB' : 'N/A',
          'disk_total' => disk_total_space('/') !== false ? round(disk_total_space('/') / 1073741824, 2) . ' GB' : 'N/A',
          'memory_usage' => round(memory_get_usage(true) / 1048576, 2) . ' MB'
        ],
        'table_counts' => $tableCounts,
        'recent_activity' => $recentActivity,
        'generated_at' => date('Y-m-d H:i:s')
      ];
    } catch (Exception $e) {
      error_log("AIReportGenerator::generateSystemReport error: " . $e->getMessage());
      return ['error' => $e->getMessage()];
    }
  }

  /**
   * List available report types with descriptions
   */
  public function getAvailableReports()
  {
    return [
      [
        'id' => 'attendance',
        'name' => 'Attendance Report',
        'description' => 'Summary of attendance records including daily breakdown, present/absent/late counts',
        'parameters' => ['tenantId', 'dateFrom', 'dateTo']
      ],
      [
        'id' => 'financial',
        'name' => 'Financial Report',
        'description' => 'Income vs expenses summary with transaction counts and net balance',
        'parameters' => ['tenantId', 'dateFrom', 'dateTo']
      ],
      [
        'id' => 'student',
        'name' => 'Student Report',
        'description' => 'Individual student attendance rate, grades, and overall performance',
        'parameters' => ['studentId']
      ],
      [
        'id' => 'system',
        'name' => 'System Report',
        'description' => 'Server health, database table sizes, and recent audit activity',
        'parameters' => []
      ]
    ];
  }
}
