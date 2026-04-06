<?php

/**
 * PolicyEngine — Self-Adapting Governance Rules
 *
 * Transforms cognitive insights into governance actions.
 * Policies evolve using outcome feedback.
 *
 * Policy types: Academic, Operational, Security, Communication
 *
 * Example:
 *   IF attendance < 70% for 2 weeks → increase parent notification frequency
 */
class PolicyEngine
{
  /**
   * Ensure policy tables exist.
   */
  public static function ensureTable(): void
  {
    db()->query("CREATE TABLE IF NOT EXISTS cognitive_policies (
      id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      policy_type VARCHAR(40) NOT NULL,
      name VARCHAR(120) NOT NULL,
      description TEXT DEFAULT NULL,
      trigger_condition TEXT NOT NULL,
      action_spec TEXT NOT NULL,
      threshold DECIMAL(6,3) DEFAULT 0.500,
      active TINYINT(1) DEFAULT 1,
      auto_execute TINYINT(1) DEFAULT 0,
      success_count INT UNSIGNED DEFAULT 0,
      fail_count INT UNSIGNED DEFAULT 0,
      last_triggered DATETIME DEFAULT NULL,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      INDEX idx_cp_type (policy_type),
      INDEX idx_cp_active (active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  }

  /**
   * Evaluate all active policies against current institutional state.
   *
   * @return array ['evaluated' => int, 'triggered' => [], 'recommendations' => []]
   */
  public static function evaluate(): array
  {
    self::ensureTable();
    self::seedDefaultPolicies();

    $policies = db()->fetchAll(
      "SELECT * FROM cognitive_policies WHERE active = 1 ORDER BY policy_type, name"
    ) ?: [];

    $triggered = [];
    $recommendations = [];

    foreach ($policies as $policy) {
      $result = self::evaluatePolicy($policy);
      if ($result['triggered']) {
        // Run through ethical guard
        $ethicalCheck = EthicalGuard::validate($result['action'], [
          'policy_id'   => $policy['id'],
          'policy_type' => $policy['policy_type'],
        ]);

        $result['ethical_check'] = $ethicalCheck;

        if ($ethicalCheck['allowed'] && $policy['auto_execute']) {
          $result['status'] = 'executed';
          self::executePolicy($policy, $result);
          $triggered[] = $result;
        } else {
          $result['status'] = 'recommended';
          $recommendations[] = $result;
        }

        // Update trigger timestamp
        db()->query(
          "UPDATE cognitive_policies SET last_triggered = NOW() WHERE id = ?",
          [$policy['id']]
        );
      }
    }

    // Record to memory
    if (!empty($triggered) || !empty($recommendations)) {
      InstitutionalMemory::record(
        'policy_evaluation',
        'cycle_complete',
        'PolicyEngine',
        count($triggered) . ' triggered, ' . count($recommendations) . ' recommended',
        'evaluated',
        0.8
      );
    }

    return [
      'evaluated'       => count($policies),
      'triggered'       => $triggered,
      'recommendations' => $recommendations,
      'timestamp'       => date('Y-m-d H:i:s'),
    ];
  }

  /**
   * Evaluate a single policy.
   */
  private static function evaluatePolicy(array $policy): array
  {
    $trigger = $policy['trigger_condition'];
    $threshold = (float) $policy['threshold'];
    $result = [
      'policy_id'   => (int) $policy['id'],
      'policy_name' => $policy['name'],
      'policy_type' => $policy['policy_type'],
      'action'      => $policy['action_spec'],
      'triggered'   => false,
      'confidence'  => 0.0,
      'detail'      => '',
    ];

    try {
      switch ($trigger) {
        case 'attendance_below_threshold':
          $rate = self::getOverallAttendanceRate(14);
          if ($rate < ($threshold * 100)) {
            $result['triggered'] = true;
            $result['confidence'] = min(1.0, (($threshold * 100) - $rate) / 30);
            $result['detail'] = "14-day attendance rate: {$rate}% (threshold: " . ($threshold * 100) . "%)";
          }
          break;

        case 'class_attendance_critical':
          $classes = self::getClassesBelow($threshold * 100, 14);
          if (!empty($classes)) {
            $result['triggered'] = true;
            $result['confidence'] = 0.85;
            $result['detail'] = count($classes) . " class(es) below " . ($threshold * 100) . "% attendance";
          }
          break;

        case 'teacher_inactive':
          $inactive = self::getInactiveTeachers(7);
          if (!empty($inactive)) {
            $result['triggered'] = true;
            $result['confidence'] = 0.75;
            $result['detail'] = count($inactive) . " teacher(s) inactive for 7+ days";
          }
          break;

        case 'system_error_spike':
          $errorCount = self::getRecentErrorCount(1);
          if ($errorCount > ($threshold * 100)) {
            $result['triggered'] = true;
            $result['confidence'] = 0.9;
            $result['detail'] = "{$errorCount} errors in last hour (threshold: " . ($threshold * 100) . ")";
          }
          break;

        case 'high_absence_students':
          $atRisk = self::getAtRiskStudentCount(0.25, 30);
          if ($atRisk > ($threshold * 100)) {
            $result['triggered'] = true;
            $result['confidence'] = 0.8;
            $result['detail'] = "{$atRisk} students with >25% absence rate";
          }
          break;

        default:
          // Custom trigger — skip
          break;
      }
    } catch (\Throwable $e) {
      ErrorCollector::log('cognitive', 'Policy eval error: ' . $e->getMessage(), 'MEDIUM');
    }

    return $result;
  }

  /**
   * Execute a triggered policy action.
   */
  private static function executePolicy(array $policy, array $result): void
  {
    try {
      // Record execution
      InstitutionalMemory::record(
        'policy_execution',
        $policy['policy_type'] . '_policy',
        $policy['name'],
        $result['detail'],
        'executed',
        $result['confidence'],
        0.0,
        ['policy_id' => $policy['id'], 'action' => $policy['action_spec']]
      );

      db()->query(
        "UPDATE cognitive_policies SET success_count = success_count + 1 WHERE id = ?",
        [$policy['id']]
      );

      ErrorCollector::log('cognitive', "Policy executed: {$policy['name']}", 'INFO');
    } catch (\Throwable $e) {
      db()->query(
        "UPDATE cognitive_policies SET fail_count = fail_count + 1 WHERE id = ?",
        [$policy['id']]
      );
    }
  }

  /**
   * Seed default policies if none exist.
   */
  private static function seedDefaultPolicies(): void
  {
    $count = (int) (db()->fetchOne("SELECT COUNT(*) as cnt FROM cognitive_policies")['cnt'] ?? 0);
    if ($count > 0) return;

    $defaults = [
      [
        'academic',
        'Low Overall Attendance Alert',
        'Alert when school-wide attendance drops below threshold',
        'attendance_below_threshold',
        'notify_admin_attendance_drop',
        0.700,
        0,
      ],
      [
        'academic',
        'Critical Class Attendance',
        'Flag classes with attendance below 60%',
        'class_attendance_critical',
        'notify_teacher_class_risk',
        0.600,
        0,
      ],
      [
        'operational',
        'Teacher Inactivity Detection',
        'Detect teachers inactive for 7+ days',
        'teacher_inactive',
        'notify_admin_teacher_inactive',
        0.500,
        0,
      ],
      [
        'security',
        'System Error Spike',
        'Alert when error rate exceeds threshold per hour',
        'system_error_spike',
        'escalate_to_developer',
        0.100,
        0,
      ],
      [
        'communication',
        'At-Risk Student Notification',
        'Increase parent communication for at-risk students',
        'high_absence_students',
        'increase_parent_notifications',
        0.050,
        0,
      ],
    ];

    foreach ($defaults as $d) {
      db()->query(
        "INSERT INTO cognitive_policies (policy_type, name, description, trigger_condition, action_spec, threshold, auto_execute)
         VALUES (?, ?, ?, ?, ?, ?, ?)",
        $d
      );
    }
  }

  /**
   * Record policy outcome for adaptive learning.
   */
  public static function recordOutcome(int $policyId, string $outcome, float $impact = 0.0): void
  {
    if ($outcome === 'success' || $outcome === 'improved') {
      db()->query("UPDATE cognitive_policies SET success_count = success_count + 1 WHERE id = ?", [$policyId]);
    } else {
      db()->query("UPDATE cognitive_policies SET fail_count = fail_count + 1 WHERE id = ?", [$policyId]);
    }

    InstitutionalMemory::record(
      'policy_outcome',
      'policy_feedback',
      "policy_{$policyId}",
      "Outcome: {$outcome}, Impact: {$impact}",
      $outcome,
      0.9,
      $impact
    );
  }

  // --- Data helper methods ---

  private static function getOverallAttendanceRate(int $days): float
  {
    $row = db()->fetchOne(
      "SELECT COUNT(*) as total, SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present
       FROM attendance WHERE date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)",
      [$days]
    );
    $total = (int) ($row['total'] ?? 0);
    return $total > 0 ? round(((int) ($row['present'] ?? 0) / $total) * 100, 1) : 100;
  }

  private static function getClassesBelow(float $threshold, int $days): array
  {
    return db()->fetchAll(
      "SELECT c.id, c.class_name,
              COUNT(*) as total,
              SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present
       FROM classes c
       JOIN class_enrollments ce ON c.id = ce.class_id
       JOIN attendance a ON a.student_id = ce.student_id AND a.date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
       GROUP BY c.id, c.class_name
       HAVING (present / total) * 100 < ?",
      [$days, $threshold]
    ) ?: [];
  }

  private static function getInactiveTeachers(int $days): array
  {
    return db()->fetchAll(
      "SELECT u.id, u.full_name
       FROM users u
       WHERE u.role = 'teacher' AND u.status = 'active'
       AND u.id NOT IN (
         SELECT DISTINCT marked_by FROM attendance WHERE date >= DATE_SUB(CURDATE(), INTERVAL ? DAY) AND marked_by IS NOT NULL
       )",
      [$days]
    ) ?: [];
  }

  private static function getRecentErrorCount(int $hours): int
  {
    try {
      $r = db()->fetchOne(
        "SELECT COUNT(*) as cnt FROM system_failures WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)",
        [$hours]
      );
      return (int) ($r['cnt'] ?? 0);
    } catch (\Throwable $e) {
      return 0;
    }
  }

  private static function getAtRiskStudentCount(float $absenceRate, int $days): int
  {
    try {
      $r = db()->fetchOne(
        "SELECT COUNT(DISTINCT student_id) as cnt FROM (
           SELECT student_id, COUNT(*) as total, SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absences
           FROM attendance WHERE date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
           GROUP BY student_id HAVING (absences / total) > ?
         ) risk_students",
        [$days, $absenceRate]
      );
      return (int) ($r['cnt'] ?? 0);
    } catch (\Throwable $e) {
      return 0;
    }
  }

  /**
   * Get all policies for dashboard.
   */
  public static function getPolicies(): array
  {
    self::ensureTable();
    return db()->fetchAll("SELECT * FROM cognitive_policies ORDER BY policy_type, name") ?: [];
  }

  /**
   * Summary for dashboard.
   */
  public static function getSummary(): array
  {
    self::ensureTable();
    self::seedDefaultPolicies();

    $policies = self::getPolicies();
    $active = array_filter($policies, fn($p) => (int) $p['active'] === 1);

    return [
      'total_policies' => count($policies),
      'active'         => count($active),
      'policies'       => $policies,
    ];
  }
}
