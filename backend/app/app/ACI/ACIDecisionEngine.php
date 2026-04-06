<?php

/**
 * ACI — Decision Engine
 * Maps predictions and observations to concrete actions.
 * Decides the safest correction for each detected issue.
 */
class ACIDecisionEngine
{
  /**
   * Generate decisions from predictions.
   * @param array $predictions from CommandPredictor::predict()
   * @param array $signals from SystemObserver::observe()
   */
  public static function decide(array $predictions, array $signals): array
  {
    $decisions = [];

    foreach ($predictions['predictions'] ?? [] as $pred) {
      $action = $pred['action'] ?? '';
      $risk = RiskAnalyzer::analyze($action, $pred);
      $learned = LearningMemory::getBestAction($pred['type']);

      // If we've learned a better action, use it
      if ($learned && $learned['confidence'] >= 0.8) {
        $action = $learned['solution_action'];
      }

      $autoExecute = $risk['safe'] && $pred['confidence'] >= RiskAnalyzer::getAutoThreshold();

      $decisions[] = [
        'prediction'   => $pred,
        'action'       => $action,
        'risk'         => $risk,
        'auto_execute' => $autoExecute,
        'confidence'   => $pred['confidence'],
        'reason'       => self::describeAction($action),
      ];
    }

    // Also check broken navigation specifically
    $brokenPages = $signals['broken_routes']['broken'] ?? [];
    foreach ($brokenPages as $bp) {
      $decisions[] = [
        'prediction'   => ['type' => 'missing_page', 'severity' => 'MEDIUM', 'confidence' => 1.0, 'message' => "Missing: {$bp['route']}"],
        'action'       => 'create_stub',
        'risk'         => RiskAnalyzer::analyze('create_stub'),
        'auto_execute' => true,
        'confidence'   => 1.0,
        'reason'       => "Create safe stub for missing page: {$bp['route']}",
      ];
    }

    return $decisions;
  }

  private static function describeAction(string $action): string
  {
    $map = [
      'route_rebuild'    => 'Rebuild route index to fix navigation',
      'cache_rebuild'    => 'Clear and rebuild system caches',
      'create_stub'      => 'Create safe page stub for missing page',
      'layout_restore'   => 'Restore layout includes',
      'directory_create' => 'Create missing directories',
      'summary_refresh'  => 'Refresh system summary files',
      'db_optimize'      => 'Optimize database tables',
      'service_restart'  => 'Restart degraded services',
      'session_clear'    => 'Clear stale sessions',
      'schema_alter'     => 'Repair database schema',
    ];
    return $map[$action] ?? "Execute: $action";
  }
}
