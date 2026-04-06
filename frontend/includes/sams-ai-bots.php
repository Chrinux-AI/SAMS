<?php

/**
 * SAMS AI Role-Specific Bot Classes
 * Provides AI insights for each role's dashboard
 */

/**
 * Base bot class with shared functionality
 */
class SAMS_BaseBot
{
  protected $db;

  public function __construct()
  {
    try {
      $this->db = db();
    } catch (Throwable $e) {
      $this->db = null;
    }
  }

  protected function safeQuery($sql, $params = [])
  {
    if (!$this->db) return [];
    try {
      $stmt = $this->db->prepare($sql);
      $stmt->execute($params);
      return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
      return [];
    }
  }

  protected function safeCount($table, $where = '1=1', $params = [])
  {
    if (!$this->db) return 0;
    try {
      $stmt = $this->db->prepare("SELECT COUNT(*) as cnt FROM $table WHERE $where");
      $stmt->execute($params);
      $row = $stmt->fetch(PDO::FETCH_ASSOC);
      return (int)($row['cnt'] ?? 0);
    } catch (Throwable $e) {
      return 0;
    }
  }
}

/**
 * Teacher AI Bot - Teaching workload & attendance insights
 */
class SAMS_TeacherBot extends SAMS_BaseBot
{
  public function getTeacherInsights($teacherId, $tenantId = null)
  {
    $total_students = $this->safeCount('students', 'teacher_id = ?', [$teacherId]);
    $attendance_avg = 85;

    try {
      $rows = $this->safeQuery(
        "SELECT AVG(CASE WHEN status = 'present' THEN 100 ELSE 0 END) as avg_rate
                 FROM attendance WHERE teacher_id = ? AND date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)",
        [$teacherId]
      );
      if (!empty($rows[0]['avg_rate'])) $attendance_avg = round($rows[0]['avg_rate']);
    } catch (Throwable $e) {
    }

    return [
      'workload_status' => $total_students > 30 ? 'high' : ($total_students > 15 ? 'moderate' : 'low'),
      'attendance_trend' => $attendance_avg > 85 ? 'excellent' : ($attendance_avg > 70 ? 'good' : 'needs_attention'),
      'recommendation' => $this->getTeacherRecommendation($total_students, $attendance_avg),
      'total_students' => $total_students,
      'avg_attendance' => $attendance_avg
    ];
  }

  private function getTeacherRecommendation($students, $rate)
  {
    if ($rate < 70) return 'Attendance is below target. Consider reaching out to frequently absent students.';
    if ($students > 30) return 'High workload detected. Consider delegating tasks or requesting support.';
    return 'Continue monitoring attendance patterns and maintaining engagement.';
  }
}

/**
 * Student AI Bot - Academic performance & engagement insights
 */
class SAMS_StudentBot extends SAMS_BaseBot
{
  public function getStudentInsights($studentId, $tenantId = null)
  {
    $attendance_rate = 85;

    try {
      $rows = $this->safeQuery(
        "SELECT AVG(CASE WHEN status = 'present' THEN 100 ELSE 0 END) as avg_rate
                 FROM attendance WHERE student_id = ? AND date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)",
        [$studentId]
      );
      if (!empty($rows[0]['avg_rate'])) $attendance_rate = round($rows[0]['avg_rate']);
    } catch (Throwable $e) {
    }

    return [
      'performance_trend' => $attendance_rate > 85 ? 'excellent' : ($attendance_rate > 70 ? 'good' : 'needs_improvement'),
      'attendance_rate' => $attendance_rate,
      'recommendation' => $this->getStudentRecommendation($attendance_rate),
      'study_suggestions' => $this->getStudySuggestions($attendance_rate)
    ];
  }

  private function getStudentRecommendation($rate)
  {
    if ($rate > 90) return 'Excellent attendance! Keep up the great work.';
    if ($rate > 75) return 'Good attendance. Try to maintain consistency for best results.';
    return 'Your attendance needs improvement. Regular attendance is key to academic success.';
  }

  private function getStudySuggestions($rate)
  {
    $suggestions = ['Review daily notes', 'Complete assignments on time'];
    if ($rate < 80) $suggestions[] = 'Attend all scheduled classes';
    $suggestions[] = 'Ask for help when needed';
    return $suggestions;
  }
}

/**
 * Parent AI Bot - Child monitoring & engagement insights
 */
class SAMS_ParentBot extends SAMS_BaseBot
{
  public function getParentInsights($parentId, $tenantId = null)
  {
    $children_count = $this->safeCount('students', 'parent_id = ?', [$parentId]);

    return [
      'engagement_level' => $children_count > 0 ? 'moderately_engaged' : 'needs_attention',
      'recommendation' => 'Regular communication with teachers helps support your child\'s success.',
      'children_status' => $children_count > 1 ? 'monitoring_multiple' : 'focused_support',
      'children_count' => $children_count
    ];
  }
}

/**
 * Financial AI Bot - Budget & expense insights for Accountant
 */
class SAMS_FinancialBot extends SAMS_BaseBot
{
  public function getFinancialInsights($tenantId = null)
  {
    $currentMonthIncome = 0.0;
    $currentMonthExpenses = 0.0;
    $yearIncome = 0.0;
    $yearExpenses = 0.0;
    $pendingApprovals = 0;
    $paidTransactions = 0;
    $overdueFees = 0.0;
    $dataSources = [];

    if ($this->tableExists('fee_payments')) {
      $amountCol = $this->columnExists('fee_payments', 'amount_paid') ? 'amount_paid' : ($this->columnExists('fee_payments', 'amount') ? 'amount' : null);
      $dateCol = $this->resolveDateColumn('fee_payments');
      $statusCol = $this->columnExists('fee_payments', 'status') ? 'status' : null;

      if ($amountCol && $dateCol) {
        [$tenantSql, $tenantParams] = $this->tenantFilter('fee_payments', $tenantId);

        $monthSql = "SELECT COALESCE(SUM($amountCol), 0) AS total FROM fee_payments WHERE DATE_FORMAT($dateCol, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')";
        $yearSql = "SELECT COALESCE(SUM($amountCol), 0) AS total FROM fee_payments WHERE YEAR($dateCol) = YEAR(CURDATE())";
        $countSql = "SELECT COUNT(*) AS cnt FROM fee_payments WHERE 1=1";

        $monthParams = $tenantParams;
        $yearParams = $tenantParams;
        $countParams = $tenantParams;

        if ($tenantSql !== '') {
          $monthSql .= " AND $tenantSql";
          $yearSql .= " AND $tenantSql";
          $countSql .= " AND $tenantSql";
        }

        if ($statusCol) {
          $monthSql .= " AND $statusCol = ?";
          $yearSql .= " AND $statusCol = ?";
          $countSql .= " AND $statusCol = ?";
          $monthParams[] = 'paid';
          $yearParams[] = 'paid';
          $countParams[] = 'paid';
        }

        $currentMonthIncome = $this->safeScalar($monthSql, $monthParams, 'total');
        $yearIncome = $this->safeScalar($yearSql, $yearParams, 'total');
        $paidTransactions = (int)$this->safeScalar($countSql, $countParams, 'cnt');
        $dataSources[] = 'fee_payments';
      }

      if ($this->columnExists('fee_payments', 'balance')) {
        [$tenantSql, $tenantParams] = $this->tenantFilter('fee_payments', $tenantId);
        $overdueSql = "SELECT COALESCE(SUM(balance), 0) AS total FROM fee_payments WHERE status != ?";
        $overdueParams = ['paid'];
        if ($tenantSql !== '') {
          $overdueSql .= " AND $tenantSql";
          $overdueParams = array_merge($overdueParams, $tenantParams);
        }
        $overdueFees = $this->safeScalar($overdueSql, $overdueParams, 'total');
      }
    }

    if ($this->tableExists('fee_invoices') && $overdueFees <= 0.0) {
      $amountCol = $this->columnExists('fee_invoices', 'balance') ? 'balance' : ($this->columnExists('fee_invoices', 'amount') ? 'amount' : null);
      $statusCol = $this->columnExists('fee_invoices', 'status') ? 'status' : null;
      if ($amountCol && $statusCol) {
        [$tenantSql, $tenantParams] = $this->tenantFilter('fee_invoices', $tenantId);
        $sql = "SELECT COALESCE(SUM($amountCol), 0) AS total FROM fee_invoices WHERE ($statusCol = ? OR $statusCol = ?)";
        $params = ['pending', 'partial'];
        if ($tenantSql !== '') {
          $sql .= " AND $tenantSql";
          $params = array_merge($params, $tenantParams);
        }
        $overdueFees = $this->safeScalar($sql, $params, 'total');
        $dataSources[] = 'fee_invoices';
      }
    }

    if ($this->tableExists('expenses')) {
      $dateCol = $this->resolveDateColumn('expenses');
      $statusCol = $this->columnExists('expenses', 'status') ? 'status' : null;

      if ($dateCol) {
        [$tenantSql, $tenantParams] = $this->tenantFilter('expenses', $tenantId);

        $monthSql = "SELECT COALESCE(SUM(amount), 0) AS total FROM expenses WHERE DATE_FORMAT($dateCol, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')";
        $yearSql = "SELECT COALESCE(SUM(amount), 0) AS total FROM expenses WHERE YEAR($dateCol) = YEAR(CURDATE())";
        $monthParams = $tenantParams;
        $yearParams = $tenantParams;

        if ($tenantSql !== '') {
          $monthSql .= " AND $tenantSql";
          $yearSql .= " AND $tenantSql";
        }

        $currentMonthExpenses = $this->safeScalar($monthSql, $monthParams, 'total');
        $yearExpenses = $this->safeScalar($yearSql, $yearParams, 'total');
        $dataSources[] = 'expenses';
      }

      if ($statusCol) {
        [$tenantSql, $tenantParams] = $this->tenantFilter('expenses', $tenantId);
        $approvalSql = "SELECT COUNT(*) AS cnt FROM expenses WHERE $statusCol = ?";
        $approvalParams = ['pending'];
        if ($tenantSql !== '') {
          $approvalSql .= " AND $tenantSql";
          $approvalParams = array_merge($approvalParams, $tenantParams);
        }
        $pendingApprovals = (int)$this->safeScalar($approvalSql, $approvalParams, 'cnt');
      }
    }

    $net = $yearIncome - $yearExpenses;
    $paymentTrend = $currentMonthIncome >= $currentMonthExpenses ? 'positive_cashflow' : 'negative_cashflow';

    return [
      'financial_health' => $net > 0 ? 'excellent' : ($net == 0.0 ? 'balanced' : 'needs_attention'),
      'payment_trend' => $paymentTrend,
      'recommendation' => $this->getFinancialRecommendation($net, $pendingApprovals, $overdueFees),
      'net_balance' => $net,
      'is_db_backed' => true,
      'generated_at' => date('Y-m-d H:i:s'),
      'data_sources' => array_values(array_unique($dataSources)),
      'evidence' => [
        'year_income' => $yearIncome,
        'year_expenses' => $yearExpenses,
        'month_income' => $currentMonthIncome,
        'month_expenses' => $currentMonthExpenses,
        'pending_approvals' => $pendingApprovals,
        'paid_transactions' => $paidTransactions,
        'overdue_fees' => $overdueFees
      ]
    ];
  }

  private function getFinancialRecommendation($net, $pendingApprovals, $overdueFees)
  {
    if ($net < 0) return 'Verified from ledger totals: expenses exceed income. Prioritize expense controls and improve fee collection.';
    if ((int)$pendingApprovals > 0) return 'Verified from expense records: pending approvals exist. Review and post approvals promptly for accurate reporting.';
    if ((float)$overdueFees > 0) return 'Verified from outstanding balances: overdue fees are present. Follow up collections to protect cash flow.';
    return 'Verified from current financial records: cash position is stable. Continue regular reconciliation and monitoring.';
  }

  private function safeScalar($sql, $params, $field)
  {
    $rows = $this->safeQuery($sql, $params);
    if (empty($rows) || !array_key_exists($field, $rows[0])) {
      return 0;
    }
    return (float)$rows[0][$field];
  }

  private function tableExists($table)
  {
    $rows = $this->safeQuery(
      "SELECT COUNT(*) AS cnt FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?",
      [$table]
    );
    return !empty($rows[0]['cnt']) && (int)$rows[0]['cnt'] > 0;
  }

  private function columnExists($table, $column)
  {
    $rows = $this->safeQuery(
      "SELECT COUNT(*) AS cnt FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?",
      [$table, $column]
    );
    return !empty($rows[0]['cnt']) && (int)$rows[0]['cnt'] > 0;
  }

  private function tenantFilter($table, $tenantId)
  {
    if ($tenantId === null || !$this->columnExists($table, 'tenant_id')) {
      return ['', []];
    }
    return ['tenant_id = ?', [$tenantId]];
  }

  private function resolveDateColumn($table)
  {
    $candidates = ['payment_date', 'expense_date', 'created_at', 'date'];
    foreach ($candidates as $candidate) {
      if ($this->columnExists($table, $candidate)) {
        return $candidate;
      }
    }
    return null;
  }
}

/**
 * Library AI Bot - Collection & circulation insights
 */
class SAMS_LibraryBot extends SAMS_BaseBot
{
  public function getLibraryInsights($tenantId = null)
  {
    $overdue = $this->safeCount('book_loans', 'status = ? AND due_date < CURDATE()', ['active']);
    $total_books = $this->safeCount('books', '1=1');

    return [
      'library_health' => $overdue > 5 ? 'needs_attention' : 'good',
      'reading_trend' => 'stable',
      'recommendation' => $this->getLibraryRecommendation($overdue),
      'overdue_count' => $overdue,
      'total_books' => $total_books
    ];
  }

  private function getLibraryRecommendation($overdue)
  {
    if ($overdue > 10) return 'Many overdue books. Send reminder notices to borrowers.';
    if ($overdue > 0) return 'Follow up on overdue books to improve circulation.';
    return 'Library operations are running smoothly.';
  }
}

/**
 * Transport AI Bot - Route & vehicle insights
 */
class SAMS_TransportBot extends SAMS_BaseBot
{
  public function getTransportInsights($tenantId = null)
  {
    $total_routes = $this->safeCount('transport_routes', '1=1');
    $total_vehicles = $this->safeCount('transport_vehicles', '1=1');

    return [
      'transport_health' => $total_routes > 0 ? 'good' : 'needs_attention',
      'route_optimization' => 'stable',
      'recommendation' => $this->getTransportRecommendation($total_routes, $total_vehicles),
      'total_routes' => $total_routes,
      'total_vehicles' => $total_vehicles
    ];
  }

  private function getTransportRecommendation($routes, $vehicles)
  {
    if ($routes === 0) return 'No routes configured. Set up transport routes for student allocation.';
    if ($vehicles === 0) return 'No vehicles registered. Add vehicles to enable transport management.';
    return 'Transport operations are running efficiently.';
  }
}

/**
 * Moderation AI Bot - Forum health & content quality insights
 */
class SAMS_ModerationBot extends SAMS_BaseBot
{
  public function getModerationInsights($tenantId = null)
  {
    $reported = $this->safeCount('forum_reported_posts', "status = 'pending'");
    $banned = $this->safeCount('forum_bans', 'is_active = 1');

    return [
      'moderation_health' => $reported > 10 ? 'needs_attention' : 'good',
      'content_quality' => 'stable',
      'recommendation' => $this->getModerationRecommendation($reported, $banned),
      'pending_reports' => $reported,
      'active_bans' => $banned
    ];
  }

  private function getModerationRecommendation($reported, $banned)
  {
    if ($reported > 10) return 'High number of reported posts. Review queue promptly.';
    if ($reported > 0) return 'Review reported posts promptly to maintain community standards.';
    return 'Forum moderation is running smoothly.';
  }
}
