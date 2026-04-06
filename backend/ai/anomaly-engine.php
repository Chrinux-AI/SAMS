<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';

class AnomalyEngine
{
  /**
   * Detect teachers marking suspiciously uniform attendance (90%+ present across all students)
   */
  public function detectAttendanceAnomalies($tenantId)
  {
    try {
      $results = db()->fetchAll(
        "SELECT a.teacher_id, a.class_id, a.date,
                        COUNT(*) as total,
                        SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present_count
                 FROM attendance a
                 JOIN users u ON a.teacher_id = u.id AND u.tenant_id = ?
                 GROUP BY a.teacher_id, a.class_id, a.date
                 HAVING (present_count / total) > 0.9 AND total >= 5",
        [$tenantId]
      );

      $anomalies = [];
      foreach ($results as $row) {
        $anomalies[] = [
          'type' => 'attendance_uniformity',
          'teacher_id' => $row['teacher_id'],
          'class_id' => $row['class_id'],
          'date' => $row['date'],
          'total_students' => $row['total'],
          'present_count' => $row['present_count'],
          'ratio' => round($row['present_count'] / $row['total'], 2),
          'severity' => 'warning'
        ];
      }
      return $anomalies;
    } catch (Exception $e) {
      error_log("AnomalyEngine::detectAttendanceAnomalies error: " . $e->getMessage());
      return [];
    }
  }

  /**
   * Detect multiple grade edits by the same user within a short timeframe
   */
  public function detectGradeManipulation($tenantId)
  {
    try {
      $results = db()->fetchAll(
        "SELECT user_id, COUNT(*) as edit_count, MIN(created_at) as first_edit, MAX(created_at) as last_edit
                 FROM audit_logs
                 WHERE tenant_id = ?
                   AND action LIKE '%grade%'
                   AND created_at >= NOW() - INTERVAL 24 HOUR
                 GROUP BY user_id
                 HAVING edit_count > 3",
        [$tenantId]
      );

      $anomalies = [];
      foreach ($results as $row) {
        $anomalies[] = [
          'type' => 'grade_manipulation',
          'user_id' => $row['user_id'],
          'edit_count' => $row['edit_count'],
          'first_edit' => $row['first_edit'],
          'last_edit' => $row['last_edit'],
          'severity' => $row['edit_count'] > 10 ? 'critical' : 'warning'
        ];
      }
      return $anomalies;
    } catch (Exception $e) {
      error_log("AnomalyEngine::detectGradeManipulation error: " . $e->getMessage());
      return [];
    }
  }

  /**
   * Detect unusual financial entries — expenses > 3x average or deleted/re-added records
   */
  public function detectFinancialAnomalies($tenantId)
  {
    try {
      // Find expenses far above average
      $avgResult = db()->fetchOne(
        "SELECT AVG(amount) as avg_amount FROM expenses WHERE tenant_id = ?",
        [$tenantId]
      );
      $avgAmount = $avgResult ? (float)$avgResult['avg_amount'] : 0;
      $threshold = $avgAmount * 3;

      $highExpenses = [];
      if ($threshold > 0) {
        $highExpenses = db()->fetchAll(
          "SELECT id, description, amount, created_at, created_by
                     FROM expenses
                     WHERE tenant_id = ? AND amount > ?
                     ORDER BY amount DESC
                     LIMIT 20",
          [$tenantId, $threshold]
        );
      }

      // Find delete-then-add patterns in audit logs
      $suspiciousEdits = db()->fetchAll(
        "SELECT user_id, COUNT(*) as action_count,
                        SUM(CASE WHEN action LIKE '%delete%' THEN 1 ELSE 0 END) as deletes,
                        SUM(CASE WHEN action LIKE '%add%' OR action LIKE '%create%' THEN 1 ELSE 0 END) as adds
                 FROM audit_logs
                 WHERE tenant_id = ?
                   AND (action LIKE '%expense%' OR action LIKE '%finance%' OR action LIKE '%payment%')
                   AND created_at >= NOW() - INTERVAL 7 DAY
                 GROUP BY user_id
                 HAVING deletes >= 1 AND adds >= 1",
        [$tenantId]
      );

      $anomalies = [];
      foreach ($highExpenses as $row) {
        $anomalies[] = [
          'type' => 'high_expense',
          'expense_id' => $row['id'],
          'amount' => $row['amount'],
          'average' => round($avgAmount, 2),
          'description' => $row['description'],
          'created_by' => $row['created_by'],
          'severity' => 'warning'
        ];
      }
      foreach ($suspiciousEdits as $row) {
        $anomalies[] = [
          'type' => 'delete_readd_pattern',
          'user_id' => $row['user_id'],
          'deletes' => $row['deletes'],
          'adds' => $row['adds'],
          'severity' => 'critical'
        ];
      }
      return $anomalies;
    } catch (Exception $e) {
      error_log("AnomalyEngine::detectFinancialAnomalies error: " . $e->getMessage());
      return [];
    }
  }

  /**
   * Detect IPs with 5+ failed login attempts
   */
  public function detectLoginAnomalies($tenantId)
  {
    try {
      $results = db()->fetchAll(
        "SELECT ip_address, COUNT(*) as fail_count, MAX(created_at) as last_attempt
                 FROM audit_logs
                 WHERE tenant_id = ?
                   AND action LIKE '%failed%login%'
                   AND created_at >= NOW() - INTERVAL 24 HOUR
                 GROUP BY ip_address
                 HAVING fail_count >= 5
                 ORDER BY fail_count DESC",
        [$tenantId]
      );

      $anomalies = [];
      foreach ($results as $row) {
        $anomalies[] = [
          'type' => 'brute_force_attempt',
          'ip_address' => $row['ip_address'],
          'fail_count' => $row['fail_count'],
          'last_attempt' => $row['last_attempt'],
          'severity' => $row['fail_count'] > 20 ? 'critical' : 'warning'
        ];
      }
      return $anomalies;
    } catch (Exception $e) {
      error_log("AnomalyEngine::detectLoginAnomalies error: " . $e->getMessage());
      return [];
    }
  }

  /**
   * Run all anomaly detections and return combined results
   */
  public function runFullScan($tenantId)
  {
    return [
      'attendance' => $this->detectAttendanceAnomalies($tenantId),
      'grades' => $this->detectGradeManipulation($tenantId),
      'financial' => $this->detectFinancialAnomalies($tenantId),
      'logins' => $this->detectLoginAnomalies($tenantId),
      'scan_time' => date('Y-m-d H:i:s'),
      'tenant_id' => $tenantId
    ];
  }
}
