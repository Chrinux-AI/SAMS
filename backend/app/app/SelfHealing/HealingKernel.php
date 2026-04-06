<?php

/**
 * HealingKernel — Self-Healing System Supervisor
 *
 * Central controller: Detect → Diagnose → Contain → Repair → Verify → Learn
 *
 * Runs via cron every minute. Monitors all modules, triggers repair workflows,
 * prevents cascading failures, logs corrective actions.
 *
 * Architecture chain:
 *   HealingKernel → EcosystemKernel → CognitiveKernel → IntelligenceKernel → DevOpsKernel
 */
class HealingKernel
{
  private static string $summaryPath = '';
  private static string $lockPath = '';

  private static function init(): void
  {
    if (!self::$summaryPath) {
      self::$summaryPath = BASE_PATH . '/storage/healing-summary.json';
      self::$lockPath = BASE_PATH . '/storage/healing-kernel.lock';
    }
  }

  /**
   * Execute a full healing cycle.
   */
  public static function run(): array
  {
    self::init();
    $startTime = microtime(true);
    $results = ['phases' => [], 'timestamp' => date('c')];

    ErrorCollector::log('self_healing', '═══ Healing Kernel cycle started ═══', 'INFO');

    // Ensure healing memory table
    HealingMemory::ensureTable();

    // Phase 1: Fault Detection
    ErrorCollector::log('self_healing', 'Phase 1: Fault detection', 'INFO');
    try {
      $faults = FaultDetector::scan();
      $results['phases']['detection'] = [
        'faults_found' => count($faults),
        'faults'       => array_map(fn($f) => $f['type'] . ': ' . $f['message'], $faults),
      ];
    } catch (\Throwable $e) {
      $faults = [];
      $results['phases']['detection'] = ['error' => $e->getMessage()];
      ErrorCollector::log('self_healing', 'Detection failed: ' . $e->getMessage(), 'HIGH');
    }

    // Phase 2: Schema Repair (proactive)
    ErrorCollector::log('self_healing', 'Phase 2: Schema repair', 'INFO');
    try {
      $schemaResult = SchemaRepairer::scanAndRepair();
      $results['phases']['schema'] = $schemaResult;
    } catch (\Throwable $e) {
      $results['phases']['schema'] = ['error' => $e->getMessage()];
    }

    // Phase 3: Route Index Rebuild
    ErrorCollector::log('self_healing', 'Phase 3: Route index', 'INFO');
    try {
      $routes = RouteRepairer::rebuildRouteIndex();
      $results['phases']['routes'] = ['total_routes' => count($routes)];
    } catch (\Throwable $e) {
      $results['phases']['routes'] = ['error' => $e->getMessage()];
    }

    // Phase 4: Auto Repair (if faults found)
    if (!empty($faults)) {
      ErrorCollector::log('self_healing', 'Phase 4: Auto repair — ' . count($faults) . ' faults', 'INFO');
      try {
        $repairOutcomes = SelfHealingRepairEngine::repair($faults);
        $results['phases']['repair'] = [
          'attempted' => count($repairOutcomes),
          'repaired'  => count(array_filter($repairOutcomes, fn($o) => $o['repaired'] ?? false)),
          'outcomes'  => array_map(fn($o) => ($o['fault_type'] ?? '?') . ' → ' . ($o['action'] ?? '?'), $repairOutcomes),
        ];
      } catch (\Throwable $e) {
        $repairOutcomes = [];
        $results['phases']['repair'] = ['error' => $e->getMessage()];
      }
    } else {
      $repairOutcomes = [];
      $results['phases']['repair'] = ['attempted' => 0, 'repaired' => 0, 'note' => 'No faults to repair'];
    }

    // Phase 5: Integrity Verification
    ErrorCollector::log('self_healing', 'Phase 5: Integrity verification', 'INFO');
    try {
      if (!empty($repairOutcomes) && !empty($faults)) {
        $verified = IntegrityVerifier::verify($repairOutcomes, $faults);
        $verifiedCount = count(array_filter($verified, fn($v) => $v['verified'] ?? false));
        $results['phases']['verification'] = [
          'total'    => count($verified),
          'verified' => $verifiedCount,
        ];
      } else {
        $healthCheck = IntegrityVerifier::quickHealthCheck();
        $results['phases']['verification'] = [
          'health_check' => $healthCheck['all_passed'] ? 'passed' : 'issues',
        ];
      }
    } catch (\Throwable $e) {
      $results['phases']['verification'] = ['error' => $e->getMessage()];
    }

    // Phase 6: UI Integrity Check
    ErrorCollector::log('self_healing', 'Phase 6: UI integrity', 'INFO');
    try {
      $results['phases']['ui_integrity'] = UIIntegrityChecker::getSummary();
    } catch (\Throwable $e) {
      $results['phases']['ui_integrity'] = ['error' => $e->getMessage()];
    }

    // Phase 7: Service Health
    ErrorCollector::log('self_healing', 'Phase 7: Service health', 'INFO');
    try {
      $results['phases']['services'] = ServiceRestarter::getSummary();
    } catch (\Throwable $e) {
      $results['phases']['services'] = ['error' => $e->getMessage()];
    }

    // Phase 8: Cache Sync
    ErrorCollector::log('self_healing', 'Phase 8: Cache synchronization', 'INFO');
    try {
      $results['phases']['cache'] = CacheSynchronizer::getSummary();
    } catch (\Throwable $e) {
      $results['phases']['cache'] = ['error' => $e->getMessage()];
    }

    // Phase 9: Healing Memory Stats
    try {
      $results['phases']['memory'] = HealingMemory::getStats();
    } catch (\Throwable $e) {
      $results['phases']['memory'] = ['error' => $e->getMessage()];
    }

    // Calculate stability score
    $results['stability_score'] = self::calculateStabilityScore($results);
    $results['duration'] = round(microtime(true) - $startTime, 3);

    ErrorCollector::log('self_healing', "Healing cycle complete — stability: {$results['stability_score']}/100, duration: {$results['duration']}s", 'INFO');

    // Persist summary
    self::persistSummary($results);

    // Prune old healing memory entries
    HealingMemory::prune(90);

    return $results;
  }

  /**
   * Calculate a 0-100 stability score.
   */
  private static function calculateStabilityScore(array $results): int
  {
    $score = 100;
    $phases = $results['phases'] ?? [];

    // Deduct for faults found
    $faultsFound = $phases['detection']['faults_found'] ?? 0;
    $score -= min(30, $faultsFound * 5);

    // Deduct for unrepaired faults
    $attempted = $phases['repair']['attempted'] ?? 0;
    $repaired = $phases['repair']['repaired'] ?? 0;
    if ($attempted > 0) {
      $unrepaired = $attempted - $repaired;
      $score -= min(20, $unrepaired * 10);
    }

    // Deduct for UI issues
    $uiIssues = $phases['ui_integrity']['total_issues'] ?? 0;
    $score -= min(15, $uiIssues * 3);

    // Deduct for degraded services
    $svcStatus = $phases['services']['status'] ?? 'all_healthy';
    if ($svcStatus !== 'all_healthy') {
      $score -= 10;
    }

    // Deduct for verification failures
    $healthPassed = $phases['verification']['health_check'] ?? 'passed';
    if ($healthPassed === 'issues') {
      $score -= 15;
    }

    // Deduct for schema issues
    $schemaIndexes = $phases['schema']['indexes'] ?? [];
    $schemaOrphans = $phases['schema']['orphans'] ?? [];
    if (!empty($schemaOrphans)) {
      $score -= min(10, count($schemaOrphans) * 5);
    }

    return max(0, min(100, $score));
  }

  /**
   * Persist summary to storage.
   */
  private static function persistSummary(array $results): void
  {
    self::init();
    $dir = dirname(self::$summaryPath);
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    file_put_contents(self::$summaryPath, json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
  }

  /**
   * Get last run data.
   */
  public static function getLastRun(): array
  {
    self::init();
    if (!file_exists(self::$summaryPath)) {
      return ['stability_score' => 0, 'timestamp' => null, 'phases' => []];
    }
    return json_decode(file_get_contents(self::$summaryPath), true) ?: [];
  }

  /**
   * Get dashboard-ready data.
   */
  public static function getDashboardData(): array
  {
    $lastRun = self::getLastRun();
    $memory = [];
    $routes = [];

    try {
      $memory = HealingMemory::getStats();
    } catch (\Throwable $e) {
    }
    try {
      $routes = RouteRepairer::getSummary();
    } catch (\Throwable $e) {
    }

    return [
      'last_run'  => $lastRun,
      'memory'    => $memory,
      'routes'    => $routes,
    ];
  }

  public static function getSummary(): array
  {
    $last = self::getLastRun();
    return [
      'stability_score' => $last['stability_score'] ?? 0,
      'last_cycle'      => $last['timestamp'] ?? 'never',
      'duration'        => $last['duration'] ?? 0,
    ];
  }
}
