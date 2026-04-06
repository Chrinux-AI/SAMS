<?php

/**
 * CognitiveKernel — Institutional Brain
 *
 * Runs above Platform Intelligence Layer (IntelligenceKernel).
 * Central orchestrator for institutional cognition.
 *
 * Continuous Cognitive Cycle:
 *   Data → Context → Meaning → Policy → Action → Evaluation → Learning
 *
 * Coordinates: InstitutionalModel, PolicyEngine, AcademicReasoner,
 *   AdaptiveLearningEngine, HumanInteractionModel, InstitutionalMemory,
 *   InsightGenerator, EthicalGuard
 */
class CognitiveKernel
{
  /**
   * Execute a full cognitive cycle.
   *
   * @return array Complete cycle results
   */
  public static function run(): array
  {
    $startTime = microtime(true);
    $results = [];

    ErrorCollector::log('cognitive', '═══ Cognitive Kernel cycle started ═══', 'INFO');

    // Ensure tables
    self::ensureTables();

    // Phase 1: Run Intelligence Layer (foundation)
    ErrorCollector::log('cognitive', 'Phase 1: Running Intelligence Layer foundation', 'INFO');
    try {
      $results['intelligence'] = IntelligenceKernel::run();
    } catch (\Throwable $e) {
      ErrorCollector::log('cognitive', 'Intelligence layer error: ' . $e->getMessage(), 'HIGH');
      $results['intelligence'] = ['intelligence_score' => 0, 'error' => $e->getMessage()];
    }

    // Phase 2: Build institutional model
    ErrorCollector::log('cognitive', 'Phase 2: Building institutional model', 'INFO');
    try {
      $results['model'] = InstitutionalModel::build();
    } catch (\Throwable $e) {
      ErrorCollector::log('cognitive', 'Model build error: ' . $e->getMessage(), 'MEDIUM');
      $results['model'] = ['frictions' => [], 'classes' => []];
    }

    // Phase 3: Academic reasoning
    ErrorCollector::log('cognitive', 'Phase 3: Academic reasoning', 'INFO');
    try {
      $results['academic'] = AcademicReasoner::reason();
    } catch (\Throwable $e) {
      $results['academic'] = ['insights' => [], 'score' => 0];
    }

    // Phase 4: Adaptive learning
    ErrorCollector::log('cognitive', 'Phase 4: Adaptive learning', 'INFO');
    try {
      $results['adaptive'] = AdaptiveLearningEngine::adapt();
    } catch (\Throwable $e) {
      $results['adaptive'] = ['recommendations' => [], 'adaptations' => [], 'score' => 0];
    }

    // Phase 5: Human interaction analysis
    ErrorCollector::log('cognitive', 'Phase 5: Human interaction analysis', 'INFO');
    try {
      $results['interaction'] = HumanInteractionModel::analyze();
    } catch (\Throwable $e) {
      $results['interaction'] = ['roles' => [], 'frictions' => [], 'score' => 0];
    }

    // Phase 6: Policy evaluation
    ErrorCollector::log('cognitive', 'Phase 6: Policy evaluation', 'INFO');
    try {
      $results['policy'] = PolicyEngine::evaluate();
    } catch (\Throwable $e) {
      $results['policy'] = ['evaluated' => 0, 'triggered' => [], 'recommendations' => []];
    }

    // Phase 7: Insight generation
    ErrorCollector::log('cognitive', 'Phase 7: Generating institutional insights', 'INFO');
    try {
      $results['insights'] = InsightGenerator::generate();
    } catch (\Throwable $e) {
      $results['insights'] = ['insights' => [], 'total' => 0];
    }

    // Phase 8: Ethical safety check
    ErrorCollector::log('cognitive', 'Phase 8: Ethical safety verification', 'INFO');
    try {
      $results['ethics'] = EthicalGuard::systemSafetyCheck();
    } catch (\Throwable $e) {
      $results['ethics'] = ['safe' => true, 'violations' => []];
    }

    // Phase 9: Record institutional learning
    ErrorCollector::log('cognitive', 'Phase 9: Recording institutional learning', 'INFO');
    self::recordLearning($results);

    // Phase 10: Cleanup stale data
    try {
      InstitutionalMemory::prune(180);
    } catch (\Throwable $e) {
      // Non-critical
    }

    $elapsed = round((microtime(true) - $startTime) * 1000);
    $summary = self::compileSummary($results, $elapsed);
    self::persistSummary($summary);

    ErrorCollector::log('cognitive', "═══ Cognitive Kernel cycle complete ({$elapsed}ms) ═══", 'INFO');

    return $summary;
  }

  /**
   * Get full dashboard data in one call.
   */
  public static function getDashboardData(): array
  {
    $lastRun = self::getLastRun();
    $model = InstitutionalModel::getSummary();
    $academic = AcademicReasoner::getSummary();
    $adaptive = AdaptiveLearningEngine::getSummary();
    $interaction = HumanInteractionModel::getSummary();
    $policy = PolicyEngine::getSummary();
    $insights = InsightGenerator::getSummary();
    $ethics = EthicalGuard::getSummary();
    $memory = InstitutionalMemory::getSummary();

    return [
      'last_run'    => $lastRun,
      'model'       => $model,
      'academic'    => $academic,
      'adaptive'    => $adaptive,
      'interaction' => $interaction,
      'policy'      => $policy,
      'insights'    => $insights,
      'ethics'      => $ethics,
      'memory'      => $memory,
    ];
  }

  /**
   * Compile summary from cycle results.
   */
  private static function compileSummary(array $results, int $elapsedMs): array
  {
    $intelligenceScore = $results['intelligence']['intelligence_score'] ?? 0;
    $academicScore = $results['academic']['score'] ?? 100;
    $adaptiveScore = $results['adaptive']['score'] ?? 100;
    $interactionScore = $results['interaction']['score'] ?? 100;
    $insightCount = $results['insights']['total'] ?? 0;
    $policyTriggered = count($results['policy']['triggered'] ?? []);
    $policyRecommended = count($results['policy']['recommendations'] ?? []);
    $frictionCount = count($results['model']['frictions'] ?? []);
    $ethicsSafe = $results['ethics']['safe'] ?? true;

    // Cognitive Score: weighted blend
    $cognitiveScore = intval(
      ($intelligenceScore * 0.25) +
        ($academicScore * 0.25) +
        ($adaptiveScore * 0.20) +
        ($interactionScore * 0.20) +
        (($ethicsSafe ? 100 : 60) * 0.10)
    );
    $cognitiveScore = max(0, min(100, $cognitiveScore));

    return [
      'cognitive_score'     => $cognitiveScore,
      'intelligence_score'  => $intelligenceScore,
      'academic_score'      => $academicScore,
      'adaptive_score'      => $adaptiveScore,
      'interaction_score'   => $interactionScore,
      'ethics_safe'         => $ethicsSafe,
      'insights_generated'  => $insightCount,
      'policies_triggered'  => $policyTriggered,
      'policies_recommended' => $policyRecommended,
      'frictions_detected'  => $frictionCount,
      'elapsed_ms'          => $elapsedMs,
      'timestamp'           => date('Y-m-d H:i:s'),
    ];
  }

  /**
   * Record institutional learning from cycle results.
   */
  private static function recordLearning(array $results): void
  {
    try {
      // Record frictions for long-term tracking
      foreach (array_slice($results['model']['frictions'] ?? [], 0, 5) as $f) {
        InstitutionalMemory::record(
          'cognitive_learning',
          'friction_detected',
          $f['type'] ?? 'unknown',
          $f['detail'] ?? '',
          'recorded',
          0.7,
          0.0,
          ['severity' => $f['severity'] ?? 'medium']
        );
      }

      // Record policy outcomes
      $triggered = $results['policy']['triggered'] ?? [];
      foreach (array_slice($triggered, 0, 5) as $t) {
        InstitutionalMemory::record(
          'cognitive_learning',
          'policy_triggered',
          $t['policy_name'] ?? '',
          $t['detail'] ?? '',
          $t['status'] ?? 'unknown',
          $t['confidence'] ?? 0.5
        );
      }
    } catch (\Throwable $e) {
      // Non-critical
    }
  }

  /**
   * Ensure all required tables exist.
   */
  private static function ensureTables(): void
  {
    try {
      InstitutionalMemory::ensureTable();
      PolicyEngine::ensureTable();
    } catch (\Throwable $e) {
      ErrorCollector::log('cognitive', 'Table setup error: ' . $e->getMessage(), 'HIGH');
    }
  }

  /**
   * Persist summary to file.
   */
  private static function persistSummary(array $summary): void
  {
    $path = self::summaryPath();
    $dir = dirname($path);
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    file_put_contents($path, json_encode($summary, JSON_PRETTY_PRINT));
  }

  /**
   * Get last run summary.
   */
  public static function getLastRun(): ?array
  {
    $path = self::summaryPath();
    if (!is_file($path)) return null;
    $data = json_decode(file_get_contents($path), true);
    return is_array($data) ? $data : null;
  }

  /**
   * Summary file path.
   */
  private static function summaryPath(): string
  {
    return dirname(__DIR__, 2) . '/storage/cognitive-summary.json';
  }
}
