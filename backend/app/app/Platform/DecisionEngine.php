<?php

/**
 * DecisionEngine — Autonomous Governance Logic
 *
 * Receives signals from DevOps, Security, Prediction, Behavior engines
 * and chooses actions using: Risk Score + Context + Confidence = Action
 *
 * Governance rules:
 *   - Never overwrite verified admin data
 *   - Never execute destructive actions automatically
 *   - Always log reasoning
 *   - Maintain audit trail
 */
class DecisionEngine
{
  /**
   * Evaluate all signals and produce decisions.
   *
   * @return array ['decisions' => [...], 'actions_taken' => int, 'vetoed' => int]
   */
  public static function decide(array $signals = []): array
  {
    $decisions = [];
    $actionsTaken = 0;
    $vetoed = 0;

    try {
      // Gather signals if not provided
      if (empty($signals)) {
        $signals = self::gatherSignals();
      }

      $context = ContextEngine::getPrimaryContext();

      // Process each signal into a decision
      foreach ($signals as $signal) {
        $decision = self::evaluateSignal($signal, $context);
        if ($decision) {
          // Apply governance rules before executing
          if (self::isAllowed($decision)) {
            if ($decision['auto_execute']) {
              self::executeAction($decision);
              $decision['status'] = 'executed';
              $actionsTaken++;
            } else {
              $decision['status'] = 'recommended';
            }
          } else {
            $decision['status'] = 'vetoed';
            $vetoed++;
          }
          $decisions[] = $decision;
        }
      }

      // Store decisions in intelligence memory
      self::recordDecisions($decisions);
    } catch (\Throwable $e) {
      ErrorCollector::log('platform', 'DecisionEngine error: ' . $e->getMessage(), 'HIGH');
    }

    return [
      'decisions'     => $decisions,
      'actions_taken' => $actionsTaken,
      'vetoed'        => $vetoed,
      'context'       => $context ?? 'normal',
      'decided_at'    => date('Y-m-d H:i:s'),
    ];
  }

  /**
   * Gather signals from all subsystems.
   */
  private static function gatherSignals(): array
  {
    $signals = [];

    try {
      // Prediction signals
      $predictions = PredictionEngine::predict();
      foreach ($predictions['predictions'] as $p) {
        $signals[] = [
          'source'     => 'prediction',
          'type'       => $p['type'],
          'severity'   => $p['severity'] ?? 'medium',
          'detail'     => $p['detail'],
          'confidence' => $p['confidence'] ?? 0.5,
          'data'       => $p,
        ];
      }

      // Behavior anomalies
      $behavior = BehaviorAnalyzer::analyze();
      foreach ($behavior['anomalies'] as $a) {
        $signals[] = [
          'source'     => 'behavior',
          'type'       => $a['type'],
          'severity'   => $a['severity'] ?? 'medium',
          'detail'     => $a['detail'],
          'confidence' => 0.7,
          'data'       => $a,
        ];
      }

      // Context signals
      $ctx = ContextEngine::evaluate();
      foreach ($ctx['contexts'] as $c) {
        $signals[] = [
          'source'     => 'context',
          'type'       => $c['type'],
          'severity'   => $c['severity'] ?? 'low',
          'detail'     => $c['detail'],
          'confidence' => $c['confidence'] ?? 0.5,
          'data'       => $c,
        ];
      }
    } catch (\Throwable $e) {
      ErrorCollector::log('platform', 'Signal gathering error: ' . $e->getMessage(), 'MEDIUM');
    }

    return $signals;
  }

  /**
   * Evaluate a single signal and produce a decision.
   */
  private static function evaluateSignal(array $signal, string $context): ?array
  {
    $type = $signal['type'] ?? '';
    $severity = $signal['severity'] ?? 'medium';
    $confidence = $signal['confidence'] ?? 0.5;

    // Risk score: severity weight * confidence
    $severityWeights = ['critical' => 1.0, 'high' => 0.8, 'medium' => 0.5, 'low' => 0.2];
    $riskScore = ($severityWeights[$severity] ?? 0.5) * $confidence;

    // Context multiplier
    $contextMultiplier = 1.0;
    if ($context === 'academic_peak' && in_array($type, ['attendance_drop', 'class_attendance_risk', 'chronic_absence'])) {
      $contextMultiplier = 1.5; // Attendance issues more critical during exams
    }
    if ($context === 'system_stress' && in_array($type, ['memory_overload', 'db_slowdown'])) {
      $contextMultiplier = 1.3;
    }

    $adjustedRisk = min(1.0, $riskScore * $contextMultiplier);

    // Only act on signals above threshold
    if ($adjustedRisk < 0.3) return null;

    // Determine action based on signal type
    $action = self::mapSignalToAction($signal, $adjustedRisk, $context);
    if (!$action) return null;

    return [
      'signal_type'   => $type,
      'signal_source' => $signal['source'] ?? 'unknown',
      'risk_score'    => round($adjustedRisk, 2),
      'context'       => $context,
      'confidence'    => $confidence,
      'action'        => $action['action'],
      'action_type'   => $action['type'],
      'auto_execute'  => $action['auto_execute'],
      'reasoning'     => $action['reasoning'],
      'detail'        => $signal['detail'] ?? '',
    ];
  }

  /**
   * Map a signal to an actionable response.
   */
  private static function mapSignalToAction(array $signal, float $risk, string $context): ?array
  {
    $type = $signal['type'] ?? '';

    $actions = [
      'attendance_drop' => [
        'action'       => 'Generate attendance alert for at-risk classes',
        'type'         => 'notification',
        'auto_execute' => false,
        'reasoning'    => "Attendance trend declining (risk: {$risk}). Context: {$context}.",
      ],
      'class_attendance_risk' => [
        'action'       => 'Flag class for teacher intervention',
        'type'         => 'notification',
        'auto_execute' => false,
        'reasoning'    => "Class nearing attendance threshold. Teachers and admins should review.",
      ],
      'chronic_absence' => [
        'action'       => 'Flag student for counselor review',
        'type'         => 'notification',
        'auto_execute' => false,
        'reasoning'    => "Chronic absence pattern detected. Intervention recommended.",
      ],
      'memory_overload' => [
        'action'       => 'Clear application caches to free memory',
        'type'         => 'optimization',
        'auto_execute' => $risk > 0.7,
        'reasoning'    => "Memory pressure detected. Clearing non-essential caches.",
      ],
      'db_slowdown' => [
        'action'       => 'Trigger database optimization cycle',
        'type'         => 'optimization',
        'auto_execute' => $risk > 0.6,
        'reasoning'    => "DB latency elevated. Running optimization pass.",
      ],
      'failure_escalation' => [
        'action'       => 'Escalate to incident responder',
        'type'         => 'escalation',
        'auto_execute' => true,
        'reasoning'    => "Error rate doubling. Triggering incident assessment.",
      ],
      'module_instability' => [
        'action'       => 'Log unstable module for repair cycle',
        'type'         => 'repair',
        'auto_execute' => true,
        'reasoning'    => "Recurring module failures. Logging for autonomous repair.",
      ],
      'off_hours_login' => [
        'action'       => 'Log suspicious login pattern for admin review',
        'type'         => 'security',
        'auto_execute' => false,
        'reasoning'    => "Unusual login time pattern. Admin should review.",
      ],
      'admin_edit_burst' => [
        'action'       => 'Log admin activity burst for audit trail',
        'type'         => 'audit',
        'auto_execute' => true,
        'reasoning'    => "High-volume admin edits detected. Recording for audit.",
      ],
      'teacher_inactive' => [
        'action'       => 'Flag inactive teacher for admin notification',
        'type'         => 'notification',
        'auto_execute' => false,
        'reasoning'    => "Teacher not marking attendance. Admin should follow up.",
      ],
      'activity_spike' => [
        'action'       => 'Monitor system resources closely',
        'type'         => 'monitoring',
        'auto_execute' => true,
        'reasoning'    => "Activity spike may lead to resource pressure.",
      ],
    ];

    return $actions[$type] ?? null;
  }

  /**
   * Governance check — is this action safe to execute?
   */
  private static function isAllowed(array $decision): bool
  {
    // Never allow destructive actions automatically
    $destructive = ['delete', 'drop', 'truncate', 'overwrite'];
    $actionLower = strtolower($decision['action']);
    foreach ($destructive as $d) {
      if (str_contains($actionLower, $d)) return false;
    }

    // Never auto-execute admin data modifications
    if (($decision['action_type'] ?? '') === 'data_modify') return false;

    return true;
  }

  /**
   * Execute an approved action.
   */
  private static function executeAction(array $decision): void
  {
    try {
      switch ($decision['action_type']) {
        case 'optimization':
          if (str_contains($decision['action'], 'cache')) {
            PerformanceOptimizer::optimize();
          }
          if (str_contains($decision['action'], 'database')) {
            DatabaseOptimizer::optimize();
          }
          break;

        case 'escalation':
          IncidentResponder::assess();
          break;

        case 'repair':
          ErrorCollector::log('platform', 'Decision: ' . $decision['action'], 'MEDIUM');
          break;

        case 'audit':
        case 'monitoring':
          ErrorCollector::log('platform', 'Decision executed: ' . $decision['action'], 'INFO');
          break;
      }
    } catch (\Throwable $e) {
      ErrorCollector::log('platform', 'Action execution failed: ' . $e->getMessage(), 'HIGH');
    }
  }

  /**
   * Record decisions to intelligence_memory for learning.
   */
  private static function recordDecisions(array $decisions): void
  {
    try {
      self::ensureTable();
      foreach (array_slice($decisions, 0, 20) as $d) {
        db()->query(
          "INSERT INTO intelligence_memory (category, signal_type, action_taken, reasoning, risk_score, confidence, outcome, created_at)
           VALUES (?, ?, ?, ?, ?, ?, ?, NOW())",
          [
            'decision',
            $d['signal_type'] ?? '',
            $d['action'] ?? '',
            $d['reasoning'] ?? '',
            $d['risk_score'] ?? 0,
            $d['confidence'] ?? 0,
            $d['status'] ?? 'pending',
          ]
        );
      }
    } catch (\Throwable $e) {
      // Non-critical
    }
  }

  /**
   * Ensure intelligence_memory table exists.
   */
  public static function ensureTable(): void
  {
    try {
      $pdo = db()->getConnection();
      $pdo->exec("CREATE TABLE IF NOT EXISTS intelligence_memory (
        id INT AUTO_INCREMENT PRIMARY KEY,
        category VARCHAR(50) NOT NULL,
        signal_type VARCHAR(100) NOT NULL,
        action_taken TEXT DEFAULT NULL,
        reasoning TEXT DEFAULT NULL,
        risk_score DECIMAL(4,2) DEFAULT 0,
        confidence DECIMAL(4,2) DEFAULT 0,
        outcome VARCHAR(50) DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_category (category),
        INDEX idx_signal (signal_type),
        INDEX idx_created (created_at)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (\Throwable $e) {
      // Non-critical
    }
  }

  /**
   * Get recent decisions for dashboard.
   */
  public static function getRecentDecisions(int $limit = 20): array
  {
    try {
      self::ensureTable();
      return db()->fetchAll(
        "SELECT * FROM intelligence_memory WHERE category = 'decision' ORDER BY created_at DESC LIMIT ?",
        [$limit]
      );
    } catch (\Throwable $e) {
      return [];
    }
  }

  /**
   * Get summary for dashboard.
   */
  public static function getSummary(): array
  {
    try {
      self::ensureTable();
      $total = db()->fetchOne("SELECT COUNT(*) AS cnt FROM intelligence_memory WHERE category = 'decision'");
      $executed = db()->fetchOne("SELECT COUNT(*) AS cnt FROM intelligence_memory WHERE category = 'decision' AND outcome = 'executed'");
      $recent = db()->fetchOne("SELECT COUNT(*) AS cnt FROM intelligence_memory WHERE category = 'decision' AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
      return [
        'total_decisions'   => (int) ($total['cnt'] ?? 0),
        'executed'          => (int) ($executed['cnt'] ?? 0),
        'decisions_24h'     => (int) ($recent['cnt'] ?? 0),
      ];
    } catch (\Throwable $e) {
      return ['total_decisions' => 0, 'executed' => 0, 'decisions_24h' => 0];
    }
  }
}
