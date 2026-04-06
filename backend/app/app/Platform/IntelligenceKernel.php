<?php

/**
 * IntelligenceKernel — Platform Intelligence Brain
 *
 * Runs above DevOpsKernel. Central orchestrator for the intelligence layer.
 *
 * Pipeline:
 *   Collect → Understand → Decide → Act → Learn
 *
 * Coordinates: KnowledgeGraph, ContextEngine, BehaviorAnalyzer,
 *   PredictionEngine, DecisionEngine, WorkflowOrchestrator,
 *   SmartAPI, DeviceBridge
 */
class IntelligenceKernel
{
  /**
   * Execute a full intelligence cycle.
   *
   * @return array Complete cycle results
   */
  public static function run(): array
  {
    $startTime = microtime(true);
    $results = [];

    ErrorCollector::log('platform', '═══ Intelligence Kernel cycle started ═══', 'INFO');

    // Ensure all required tables
    self::ensureTables();

    // Phase 1: Run DevOps layer first (foundation)
    ErrorCollector::log('platform', 'Phase 1: Running DevOps foundation layer', 'INFO');
    try {
      $results['devops'] = DevOpsKernel::run();
    } catch (\Throwable $e) {
      ErrorCollector::log('platform', 'DevOps layer error: ' . $e->getMessage(), 'HIGH');
      $results['devops'] = ['system_score' => 0, 'error' => $e->getMessage()];
    }

    // Phase 2: Build/update knowledge graph
    ErrorCollector::log('platform', 'Phase 2: Updating knowledge graph', 'INFO');
    try {
      $results['knowledge'] = KnowledgeGraph::buildFromData();
    } catch (\Throwable $e) {
      ErrorCollector::log('platform', 'Knowledge graph error: ' . $e->getMessage(), 'MEDIUM');
      $results['knowledge'] = ['nodes' => 0, 'edges' => 0];
    }

    // Phase 3: Evaluate operational context
    ErrorCollector::log('platform', 'Phase 3: Evaluating operational context', 'INFO');
    try {
      $results['context'] = ContextEngine::evaluate();
    } catch (\Throwable $e) {
      $results['context'] = ['primary' => 'unknown', 'contexts' => []];
    }

    // Phase 4: Analyze behaviors
    ErrorCollector::log('platform', 'Phase 4: Analyzing behavioral patterns', 'INFO');
    try {
      $results['behavior'] = BehaviorAnalyzer::analyze();
    } catch (\Throwable $e) {
      $results['behavior'] = ['anomalies' => [], 'patterns' => [], 'score' => 0];
    }

    // Phase 5: Generate predictions
    ErrorCollector::log('platform', 'Phase 5: Generating predictions', 'INFO');
    try {
      $results['predictions'] = PredictionEngine::predict();
    } catch (\Throwable $e) {
      $results['predictions'] = ['predictions' => [], 'risk_level' => 'unknown'];
    }

    // Phase 6: Make decisions
    ErrorCollector::log('platform', 'Phase 6: Running decision engine', 'INFO');
    try {
      $results['decisions'] = DecisionEngine::decide();
    } catch (\Throwable $e) {
      $results['decisions'] = ['decisions' => [], 'actions_taken' => 0, 'vetoed' => 0];
    }

    // Phase 7: Process device events
    ErrorCollector::log('platform', 'Phase 7: Processing device bridge', 'INFO');
    try {
      $results['devices'] = DeviceBridge::processQueue();
      $offline = DeviceBridge::checkOfflineDevices();
      $results['devices']['offline_devices'] = count($offline);
    } catch (\Throwable $e) {
      $results['devices'] = ['processed' => 0, 'errors' => 0];
    }

    // Phase 8: Publish platform events
    ErrorCollector::log('platform', 'Phase 8: Publishing platform events', 'INFO');
    $results['events'] = self::publishEvents($results);

    // Phase 9: Record learning
    self::recordLearning($results);

    // Phase 10: Cleanup
    try {
      KnowledgeGraph::pruneOldEdges(90);
      self::pruneOldMemory(60);
    } catch (\Throwable $e) {
      // Non-critical
    }

    $elapsed = round((microtime(true) - $startTime) * 1000);

    $summary = self::compileSummary($results, $elapsed);
    self::persistSummary($summary);

    ErrorCollector::log('platform', "═══ Intelligence Kernel cycle complete ({$elapsed}ms) ═══", 'INFO');

    return $summary;
  }

  /**
   * Get full dashboard data in one call.
   */
  public static function getDashboardData(): array
  {
    $lastRun = self::getLastRun();
    $devopsData = DevOpsKernel::getDashboardData();
    $graphStats = KnowledgeGraph::getStats();
    $context = ContextEngine::evaluate();
    $behavior = BehaviorAnalyzer::getSummary();
    $predictions = PredictionEngine::getSummary();
    $decisions = DecisionEngine::getSummary();
    $devices = DeviceBridge::getSummary();
    $workflows = WorkflowOrchestrator::getHistory(10);
    $recentDecisions = DecisionEngine::getRecentDecisions(10);

    return [
      'last_run'     => $lastRun,
      'devops'       => $devopsData,
      'graph'        => $graphStats,
      'context'      => $context,
      'behavior'     => $behavior,
      'predictions'  => $predictions,
      'decisions'    => $decisions,
      'devices'      => $devices,
      'workflows'    => $workflows,
      'recent_decisions' => $recentDecisions,
    ];
  }

  /**
   * Compile summary from cycle results.
   */
  private static function compileSummary(array $results, int $elapsedMs): array
  {
    $devopsScore = $results['devops']['system_score'] ?? 0;
    $behaviorScore = $results['behavior']['score'] ?? 100;
    $predictionRisk = $results['predictions']['risk_level'] ?? 'low';
    $decisionsMade = count($results['decisions']['decisions'] ?? []);
    $actionsExecuted = $results['decisions']['actions_taken'] ?? 0;
    $graphNodes = $results['knowledge']['nodes'] ?? 0;
    $graphEdges = $results['knowledge']['edges'] ?? 0;
    $contextPrimary = $results['context']['primary'] ?? 'normal';
    $anomalyCount = count($results['behavior']['anomalies'] ?? []);
    $predictionCount = count($results['predictions']['predictions'] ?? []);

    // Intelligence score: weighted combination
    $riskPenalty = ['low' => 0, 'moderate' => 5, 'elevated' => 15, 'critical' => 30];
    $intelligenceScore = intval(
      ($devopsScore * 0.4) +
        ($behaviorScore * 0.3) +
        ((100 - ($riskPenalty[$predictionRisk] ?? 10)) * 0.3)
    );
    $intelligenceScore = max(0, min(100, $intelligenceScore));

    return [
      'intelligence_score' => $intelligenceScore,
      'devops_score'       => $devopsScore,
      'behavior_score'     => $behaviorScore,
      'prediction_risk'    => $predictionRisk,
      'context'            => $contextPrimary,
      'decisions_made'     => $decisionsMade,
      'actions_executed'   => $actionsExecuted,
      'anomalies'          => $anomalyCount,
      'predictions'        => $predictionCount,
      'graph_nodes'        => $graphNodes,
      'graph_edges'        => $graphEdges,
      'elapsed_ms'         => $elapsedMs,
      'timestamp'          => date('Y-m-d H:i:s'),
    ];
  }

  /**
   * Publish platform events from cycle results.
   */
  private static function publishEvents(array $results): array
  {
    $events = [];

    // Publish context changes
    $primary = $results['context']['primary'] ?? 'normal';
    if ($primary !== 'normal') {
      $events[] = ['type' => 'context_change', 'data' => $primary];
    }

    // Publish high-severity predictions
    foreach (($results['predictions']['predictions'] ?? []) as $p) {
      if (($p['severity'] ?? '') === 'high') {
        $events[] = ['type' => 'ai_prediction_generated', 'data' => $p['type']];
      }
    }

    // Publish executed decisions
    foreach (($results['decisions']['decisions'] ?? []) as $d) {
      if (($d['status'] ?? '') === 'executed') {
        $events[] = ['type' => 'decision_executed', 'data' => $d['action']];
      }
    }

    // Store events in intelligence_memory
    try {
      foreach (array_slice($events, 0, 10) as $evt) {
        db()->query(
          "INSERT INTO intelligence_memory (category, signal_type, action_taken, outcome, created_at) VALUES (?, ?, ?, 'published', NOW())",
          ['event', $evt['type'], $evt['data']]
        );
      }
    } catch (\Throwable $e) {
      // Non-critical
    }

    return ['published' => count($events), 'events' => $events];
  }

  /**
   * Record learning from cycle results.
   */
  private static function recordLearning(array $results): void
  {
    try {
      // Record anomaly patterns for future improvement
      foreach (array_slice($results['behavior']['anomalies'] ?? [], 0, 5) as $a) {
        db()->query(
          "INSERT INTO intelligence_memory (category, signal_type, action_taken, reasoning, confidence, outcome, created_at)
           VALUES ('learning', ?, ?, ?, ?, 'recorded', NOW())",
          [
            $a['type'] ?? 'unknown',
            'anomaly_detected',
            $a['detail'] ?? '',
            0.7,
          ]
        );
      }
    } catch (\Throwable $e) {
      // Non-critical
    }
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
   * Persist summary to storage.
   */
  private static function persistSummary(array $summary): void
  {
    $dir = dirname(self::summaryPath());
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    file_put_contents(self::summaryPath(), json_encode($summary, JSON_PRETTY_PRINT));
  }

  private static function summaryPath(): string
  {
    return (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2)) . '/storage/intelligence-summary.json';
  }

  /**
   * Ensure all required tables exist.
   */
  private static function ensureTables(): void
  {
    KnowledgeGraph::ensureTables();
    DecisionEngine::ensureTable();
    DeviceBridge::ensureTables();
  }

  /**
   * Prune old intelligence memory.
   */
  private static function pruneOldMemory(int $days = 60): void
  {
    try {
      db()->query(
        "DELETE FROM intelligence_memory WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)",
        [$days]
      );
    } catch (\Throwable $e) {
      // Non-critical
    }
  }
}
