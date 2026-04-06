<?php

/**
 * DevOpsKernel — Central Orchestrator for Autonomous DevOps Mode
 *
 * Continuously runs the full DevOps pipeline:
 *   Observe → Analyze → Optimize → Secure → Validate → Monitor → Repeat
 *
 * Integrates:
 *   ResourceMonitor, PerformanceOptimizer, DatabaseOptimizer,
 *   SecurityHardener, DeploymentGuard, DriftController,
 *   IncidentResponder, AutonomousFixLoop (from Phase-6)
 */
class DevOpsKernel
{
  /** Target system health score */
  private const TARGET_HEALTH = 98;

  /**
   * Execute a full DevOps cycle.
   *
   * @return array Complete cycle results
   */
  public static function run(): array
  {
    $startTime = microtime(true);
    $results = [];

    ErrorCollector::log('devops', '═══ DevOps Kernel cycle started ═══', 'INFO');

    // Ensure required tables
    self::ensureTables();

    // Phase 1: Observe — collect system metrics
    ErrorCollector::log('devops', 'Phase 1: Observing system metrics', 'INFO');
    $results['metrics'] = ResourceMonitor::snapshot();

    // Phase 2: Analyze — check for incidents
    ErrorCollector::log('devops', 'Phase 2: Analyzing for incidents', 'INFO');
    $results['incidents'] = IncidentResponder::assess();

    // Phase 3: Optimize — performance tuning
    ErrorCollector::log('devops', 'Phase 3: Optimizing performance', 'INFO');
    $results['performance'] = PerformanceOptimizer::optimize();

    // Phase 4: Database optimization
    ErrorCollector::log('devops', 'Phase 4: Optimizing database', 'INFO');
    $results['database'] = DatabaseOptimizer::optimize();

    // Phase 5: Security hardening
    ErrorCollector::log('devops', 'Phase 5: Hardening security', 'INFO');
    $results['security'] = SecurityHardener::harden();

    // Phase 6: Drift detection
    ErrorCollector::log('devops', 'Phase 6: Checking configuration drift', 'INFO');
    $results['drift'] = DriftController::detect();

    // Phase 7: Deployment guard check
    ErrorCollector::log('devops', 'Phase 7: Validating deployment safety', 'INFO');
    $results['deployment'] = DeploymentGuard::validate();

    // Phase 8: Run autonomous fix loop
    ErrorCollector::log('devops', 'Phase 8: Running autonomous fix loop', 'INFO');
    $results['autofix'] = AutonomousFixLoop::run();

    // Phase 9: Learning — record patterns
    self::recordLearning($results);

    // Phase 10: Cleanup
    ResourceMonitor::pruneOldMetrics(30);
    ErrorCollector::pruneLog(5000);

    $elapsed = round((microtime(true) - $startTime) * 1000);

    // Compile summary
    $summary = self::compileSummary($results, $elapsed);
    self::persistSummary($summary);

    ErrorCollector::log('devops', "═══ DevOps Kernel cycle complete ({$elapsed}ms) ═══", 'INFO');

    return $summary;
  }

  /**
   * Get the last run summary.
   */
  public static function getLastRun(): ?array
  {
    $path = self::summaryPath();
    if (!is_file($path)) return null;
    $data = json_decode(file_get_contents($path), true);
    return is_array($data) ? $data : null;
  }

  /**
   * Get full dashboard data in one call.
   */
  public static function getDashboardData(): array
  {
    $lastRun = self::getLastRun();
    $metrics = ResourceMonitor::getLatestAll();
    $threats = SecurityHardener::getThreatSummary();
    $drift = DriftController::getSummary();
    $incidents = IncidentResponder::getSummary();
    $perf = PerformanceOptimizer::getSummary();
    $dbSuggestions = DatabaseOptimizer::detectMissingIndexes();
    $tableStats = DatabaseOptimizer::getTableStats();
    $deploymentSafe = DeploymentGuard::isSafe();
    $learning = self::getRecentLearning();

    return [
      'last_run'        => $lastRun,
      'metrics'         => $metrics,
      'security'        => $threats,
      'drift'           => $drift,
      'incidents'       => $incidents,
      'performance'     => $perf,
      'db_suggestions'  => $dbSuggestions,
      'table_stats'     => $tableStats,
      'deployment_safe' => $deploymentSafe,
      'learning'        => $learning,
    ];
  }

  /**
   * Compile a summary from cycle results.
   */
  private static function compileSummary(array $results, int $elapsedMs): array
  {
    $healthScore = $results['autofix']['final_score'] ?? 0;
    $securityScore = $results['security']['score'] ?? 100;
    $incidentCount = count($results['incidents']['incidents'] ?? []);
    $threatCount = count($results['security']['threats'] ?? []);
    $drifted = $results['drift']['drifted'] ?? false;
    $deploymentSafe = $results['deployment']['safe'] ?? true;
    $perfActions = count($results['performance']['actions'] ?? []);
    $dbActions = count($results['database']['actions'] ?? []);
    $dbSuggestions = count($results['database']['suggestions'] ?? []);
    $repairsMade = $results['autofix']['repairs_made'] ?? 0;

    // Combined system score
    $systemScore = intval(($healthScore * 0.4) + ($securityScore * 0.3) + (($deploymentSafe ? 100 : 50) * 0.15) + ((!$drifted ? 100 : 60) * 0.15));
    $systemScore = max(0, min(100, $systemScore));

    return [
      'system_score'    => $systemScore,
      'health_score'    => $healthScore,
      'security_score'  => $securityScore,
      'deployment_safe' => $deploymentSafe,
      'drifted'         => $drifted,
      'incidents'       => $incidentCount,
      'threats'         => $threatCount,
      'perf_actions'    => $perfActions,
      'db_actions'      => $dbActions,
      'db_suggestions'  => $dbSuggestions,
      'repairs'         => $repairsMade,
      'elapsed_ms'      => $elapsedMs,
      'timestamp'       => date('Y-m-d H:i:s'),
    ];
  }

  /**
   * Record learning from this cycle.
   */
  private static function recordLearning(array $results): void
  {
    try {
      if (!function_exists('table_exists') || !table_exists('devops_learning')) {
        return;
      }

      // Record security patterns
      foreach ($results['security']['threats'] ?? [] as $threat) {
        db()->query(
          "INSERT INTO devops_learning (category, pattern, action_taken, occurrences, last_seen)
                     VALUES ('security', ?, 'detected', 1, NOW())
                     ON DUPLICATE KEY UPDATE occurrences = occurrences + 1, last_seen = NOW()",
          [$threat['type'] . ':' . ($threat['detail'] ?? '')]
        );
      }

      // Record incident patterns
      foreach ($results['incidents']['incidents'] ?? [] as $inc) {
        db()->query(
          "INSERT INTO devops_learning (category, pattern, action_taken, occurrences, last_seen)
                     VALUES ('incident', ?, ?, 1, NOW())
                     ON DUPLICATE KEY UPDATE occurrences = occurrences + 1, last_seen = NOW()",
          [$inc['module'] . ':' . $inc['type'], $inc['healable'] ?? false ? 'self-healed' : 'flagged']
        );
      }
    } catch (\Throwable $e) {
      // Non-critical
    }
  }

  /**
   * Get recent learning entries.
   */
  private static function getRecentLearning(): array
  {
    try {
      if (!function_exists('table_exists') || !table_exists('devops_learning')) {
        return [];
      }
      return db()->fetchAll(
        "SELECT * FROM devops_learning ORDER BY occurrences DESC, last_seen DESC LIMIT 20"
      );
    } catch (\Throwable $e) {
      return [];
    }
  }

  /**
   * Ensure required DevOps tables exist.
   */
  private static function ensureTables(): void
  {
    try {
      if (function_exists('table_exists') && !table_exists('system_metrics')) {
        db()->query("CREATE TABLE IF NOT EXISTS system_metrics (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    metric VARCHAR(100) NOT NULL,
                    value DECIMAL(15,2) NOT NULL DEFAULT 0,
                    module VARCHAR(100) DEFAULT 'system',
                    severity VARCHAR(20) DEFAULT 'normal',
                    recorded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_sm_metric (metric),
                    INDEX idx_sm_recorded (recorded_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        ErrorCollector::log('devops', 'Created system_metrics table', 'INFO');
      }

      if (function_exists('table_exists') && !table_exists('devops_learning')) {
        db()->query("CREATE TABLE IF NOT EXISTS devops_learning (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    category VARCHAR(50) NOT NULL,
                    pattern VARCHAR(500) NOT NULL,
                    action_taken VARCHAR(255) DEFAULT '',
                    occurrences INT DEFAULT 1,
                    last_seen DATETIME DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY uk_category_pattern (category, pattern(191)),
                    INDEX idx_dl_category (category),
                    INDEX idx_dl_occurrences (occurrences)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        ErrorCollector::log('devops', 'Created devops_learning table', 'INFO');
      }
    } catch (\Throwable $e) {
      ErrorCollector::log('devops', 'Table setup failed: ' . $e->getMessage(), 'ERROR');
    }
  }

  private static function persistSummary(array $summary): void
  {
    $dir = dirname(self::summaryPath());
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    file_put_contents(self::summaryPath(), json_encode($summary, JSON_PRETTY_PRINT), LOCK_EX);
  }

  private static function summaryPath(): string
  {
    $base = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);
    return $base . '/storage/devops-summary.json';
  }
}
