<?php

/**
 * ACI — Command Brain
 * Central intelligence loop: Observe → Predict → Decide → Recommend → Execute → Learn
 * Runs every 60 seconds via cron/aci.php. Stores summary at storage/aci-summary.json.
 */
class CommandBrain
{
  private static string $summaryPath = '';

  private static function summaryPath(): string
  {
    if (!self::$summaryPath) {
      self::$summaryPath = (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2)) . '/storage/aci-summary.json';
    }
    return self::$summaryPath;
  }

  /**
   * Run one full ACI cycle.
   * Called from cron/aci.php every minute.
   */
  public static function cycle(): array
  {
    $start = microtime(true);

    // 1. Observe
    $signals = SystemObserver::observe();

    // 2. Predict
    $predictions = CommandPredictor::predict($signals);

    // 3. Decide
    $decisions = ACIDecisionEngine::decide($predictions, $signals);

    // 4. Recommend
    $recommendations = RecommendationEngine::recommend($decisions);

    // 5. Execute (auto-approved only)
    $execution = AutoCommander::execute($decisions);

    // 6. Learn — recorded inside AutoCommander::execute()

    $elapsed = round((microtime(true) - $start) * 1000);

    $summary = [
      'timestamp'        => date('Y-m-d H:i:s'),
      'cycle_ms'         => $elapsed,
      'signal_score'     => $signals['signal_score'] ?? 0,
      'risk_level'       => $predictions['risk_level'] ?? 'LOW',
      'predictions'      => $predictions['count'] ?? 0,
      'decisions'        => count($decisions),
      'recommendations'  => $recommendations,
      'auto_executed'    => $execution['executed_count'] ?? 0,
      'skipped'          => $execution['skipped_count'] ?? 0,
      'execution_detail' => $execution,
      'signals'          => $signals,
    ];

    // Store summary
    self::storeSummary($summary);

    return $summary;
  }

  /**
   * Get current ACI status (for MCC dashboard).
   */
  public static function getStatus(): array
  {
    $summary = self::loadSummary();

    $learning = [];
    try {
      $learning = LearningMemory::getStats();
    } catch (\Throwable $e) {
      // Learning table may not exist yet
    }

    return [
      'online'           => true,
      'last_cycle'       => $summary['timestamp'] ?? null,
      'cycle_ms'         => $summary['cycle_ms'] ?? 0,
      'signal_score'     => $summary['signal_score'] ?? 100,
      'risk_level'       => $summary['risk_level'] ?? 'LOW',
      'predictions'      => $summary['predictions'] ?? 0,
      'auto_executed'    => $summary['auto_executed'] ?? 0,
      'skipped'          => $summary['skipped'] ?? 0,
      'recommendations'  => $summary['recommendations'] ?? [],
      'learning'         => $learning,
    ];
  }

  /**
   * Get pending recommendations (not yet auto-executed).
   */
  public static function getRecommendations(): array
  {
    $summary = self::loadSummary();
    return $summary['recommendations'] ?? [];
  }

  /**
   * Manual execution triggered from MCC.
   */
  public static function executeManual(string $action, array $context = []): array
  {
    $result = AutoCommander::executeSingle($action, $context);

    // Log to learning
    if ($result['success']) {
      LearningMemory::recordSuccess(
        $context['type'] ?? 'manual',
        'Manual execution from MCC',
        $action,
        $result['detail'] ?? ''
      );
    }

    return $result;
  }

  /**
   * Store summary to disk.
   */
  private static function storeSummary(array $summary): void
  {
    $path = self::summaryPath();
    $dir = dirname($path);
    if (!is_dir($dir)) {
      @mkdir($dir, 0755, true);
    }
    @file_put_contents($path, json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
  }

  /**
   * Load last summary from disk.
   */
  private static function loadSummary(): array
  {
    $path = self::summaryPath();
    if (!is_file($path)) return [];
    $data = @file_get_contents($path);
    return $data ? (json_decode($data, true) ?: []) : [];
  }
}
