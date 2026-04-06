<?php

/**
 * EcosystemKernel — Global Ecosystem Coordinator
 *
 * Sits above CognitiveKernel. Coordinates institutions, manages
 * distributed intelligence, synchronizes non-sensitive patterns,
 * and enforces separation policies.
 *
 * Execution loop:
 *   Collect → Abstract → Validate → Share Pattern → Improve Locally
 *
 * No raw data leaves an institution.
 */
class EcosystemKernel
{
  /**
   * Execute a full ecosystem cycle.
   */
  public static function run(): array
  {
    $startTime = microtime(true);
    $results = [];

    ErrorCollector::log('ecosystem', '═══ Ecosystem Kernel cycle started ═══', 'INFO');

    // Ensure all tables exist
    self::ensureTables();

    // Phase 1: Run Cognitive Layer (foundation)
    ErrorCollector::log('ecosystem', 'Phase 1: Running Cognitive Kernel foundation', 'INFO');
    try {
      $results['cognitive'] = CognitiveKernel::run();
    } catch (\Throwable $e) {
      ErrorCollector::log('ecosystem', 'Cognitive layer error: ' . $e->getMessage(), 'HIGH');
      $results['cognitive'] = ['cognitive_score' => 0, 'error' => $e->getMessage()];
    }

    // Phase 2: Tenant orchestration check
    ErrorCollector::log('ecosystem', 'Phase 2: Tenant orchestration', 'INFO');
    try {
      $results['tenants'] = TenantOrchestrator::getSummary();
    } catch (\Throwable $e) {
      $results['tenants'] = ['total' => 0, 'active' => 0];
    }

    // Phase 3: Knowledge exchange sync
    ErrorCollector::log('ecosystem', 'Phase 3: Knowledge exchange sync', 'INFO');
    try {
      $results['exchange'] = KnowledgeExchange::syncLocalInsights();
    } catch (\Throwable $e) {
      $results['exchange'] = ['synced' => 0];
    }

    // Phase 4: Federation pattern processing
    ErrorCollector::log('ecosystem', 'Phase 4: Federation pattern processing', 'INFO');
    try {
      $results['federation'] = self::processFederation();
    } catch (\Throwable $e) {
      $results['federation'] = ['processed' => 0, 'approved' => 0];
    }

    // Phase 5: Trust boundary verification
    ErrorCollector::log('ecosystem', 'Phase 5: Trust boundary verification', 'INFO');
    try {
      $results['trust'] = TrustBoundary::getSummary();
    } catch (\Throwable $e) {
      $results['trust'] = ['status' => 'unknown'];
    }

    // Phase 6: Ecosystem analytics
    ErrorCollector::log('ecosystem', 'Phase 6: Ecosystem analytics compilation', 'INFO');
    try {
      $results['analytics'] = EcosystemAnalytics::compile();
    } catch (\Throwable $e) {
      $results['analytics'] = ['health' => [], 'adoption' => []];
    }

    // Phase 7: Consensus validation
    ErrorCollector::log('ecosystem', 'Phase 7: Consensus guard validation', 'INFO');
    try {
      $results['consensus'] = ConsensusGuard::getSummary();
    } catch (\Throwable $e) {
      $results['consensus'] = ['status' => 'unknown'];
    }

    // Phase 8: Deployment readiness check
    ErrorCollector::log('ecosystem', 'Phase 8: Deployment readiness', 'INFO');
    try {
      $results['deployment'] = DeploymentManager::getSummary();
    } catch (\Throwable $e) {
      $results['deployment'] = ['status' => 'unknown'];
    }

    $elapsed = round((microtime(true) - $startTime) * 1000);
    $summary = self::compileSummary($results, $elapsed);
    self::persistSummary($summary);

    ErrorCollector::log('ecosystem', "═══ Ecosystem Kernel cycle complete ({$elapsed}ms) ═══", 'INFO');

    return $summary;
  }

  /**
   * Ensure all ecosystem tables exist.
   */
  private static function ensureTables(): void
  {
    try {
      TenantOrchestrator::ensureTable();
    } catch (\Throwable $e) {
    }
    try {
      FederationEngine::ensureTable();
    } catch (\Throwable $e) {
    }
    try {
      KnowledgeExchange::ensureTable();
    } catch (\Throwable $e) {
    }
  }

  /**
   * Process pending federation patterns.
   */
  private static function processFederation(): array
  {
    FederationEngine::ensureTable();
    $pending = db()->fetchAll("SELECT id FROM federation_patterns WHERE status = 'pending' LIMIT 10");
    $processed = 0;
    $approved = 0;

    foreach ($pending as $p) {
      $result = FederationEngine::approvePattern((int)$p['id']);
      $processed++;
      if ($result['success'] ?? false) $approved++;
    }

    // Distribute approved patterns
    $distributed = FederationEngine::distribute();

    return [
      'processed'   => $processed,
      'approved'    => $approved,
      'distributed' => $distributed['distributed'] ?? 0,
    ];
  }

  /**
   * Compile ecosystem summary.
   */
  private static function compileSummary(array $results, int $elapsedMs): array
  {
    $cognitiveScore = $results['cognitive']['cognitive_score'] ?? 0;
    $ecosystemScore = EcosystemAnalytics::calculateEcosystemScore();
    $tenantCount = $results['tenants']['active'] ?? 0;
    $exchangeSynced = $results['exchange']['synced'] ?? 0;
    $federationProcessed = $results['federation']['processed'] ?? 0;

    // Combined score: cognitive + ecosystem weighted
    $combinedScore = intval(($cognitiveScore * 0.60) + ($ecosystemScore * 0.40));
    $combinedScore = max(0, min(100, $combinedScore));

    return [
      'ecosystem_score'     => $combinedScore,
      'cognitive_score'     => $cognitiveScore,
      'infrastructure_score' => $ecosystemScore,
      'active_tenants'      => $tenantCount,
      'patterns_synced'     => $exchangeSynced,
      'federation_processed' => $federationProcessed,
      'trust_status'        => $results['trust']['status'] ?? 'active',
      'consensus_status'    => $results['consensus']['status'] ?? 'active',
      'deployment_ready'    => ($results['deployment']['status'] ?? '') === 'ready',
      'elapsed_ms'          => $elapsedMs,
      'timestamp'           => date('Y-m-d H:i:s'),
    ];
  }

  /**
   * Get full dashboard data.
   */
  public static function getDashboardData(): array
  {
    $lastRun = self::getLastRun();

    return [
      'last_run'    => $lastRun,
      'tenants'     => TenantOrchestrator::getSummary(),
      'federation'  => FederationEngine::getSummary(),
      'exchange'    => KnowledgeExchange::getSummary(),
      'trust'       => TrustBoundary::getSummary(),
      'consensus'   => ConsensusGuard::getSummary(),
      'deployment'  => DeploymentManager::getSummary(),
      'analytics'   => EcosystemAnalytics::getSummary(),
    ];
  }

  /**
   * Get last run summary from persisted file.
   */
  public static function getLastRun(): array
  {
    $file = BASE_PATH . '/storage/ecosystem-summary.json';
    if (is_file($file)) {
      $data = json_decode(file_get_contents($file), true);
      return is_array($data) ? $data : [];
    }
    return [];
  }

  /**
   * Persist summary to storage.
   */
  private static function persistSummary(array $summary): void
  {
    $dir = BASE_PATH . '/storage';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    file_put_contents($dir . '/ecosystem-summary.json', json_encode($summary, JSON_PRETTY_PRINT));
  }
}
