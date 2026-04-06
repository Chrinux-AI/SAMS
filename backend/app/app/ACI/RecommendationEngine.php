<?php

/**
 * ACI — Recommendation Engine
 * Generates human-readable recommendations for the MCC dashboard.
 * Converts decisions into actionable UI items.
 */
class RecommendationEngine
{
  /**
   * Generate recommendations from decisions.
   * @param array $decisions from ACIDecisionEngine::decide()
   */
  public static function recommend(array $decisions): array
  {
    $recommendations = [];

    foreach ($decisions as $d) {
      $pred = $d['prediction'];
      $severity = $pred['severity'] ?? 'LOW';
      $icon = self::severityIcon($severity);

      $rec = [
        'id'           => md5($pred['type'] . ($pred['message'] ?? '')),
        'severity'     => $severity,
        'icon'         => $icon,
        'title'        => $pred['message'] ?? 'Unknown issue',
        'action'       => $d['action'],
        'action_label' => self::actionLabel($d['action']),
        'auto_execute' => $d['auto_execute'],
        'confidence'   => round($d['confidence'] * 100),
        'risk_level'   => $d['risk']['risk_level'] ?? 'UNKNOWN',
        'reason'       => $d['reason'],
      ];

      $recommendations[] = $rec;
    }

    // Sort by severity (CRITICAL first)
    usort($recommendations, function ($a, $b) {
      $order = ['CRITICAL' => 0, 'HIGH' => 1, 'MEDIUM' => 2, 'LOW' => 3];
      return ($order[$a['severity']] ?? 4) - ($order[$b['severity']] ?? 4);
    });

    return $recommendations;
  }

  private static function severityIcon(string $severity): string
  {
    return match ($severity) {
      'CRITICAL' => 'fas fa-skull-crossbones',
      'HIGH'     => 'fas fa-exclamation-triangle',
      'MEDIUM'   => 'fas fa-exclamation-circle',
      default    => 'fas fa-info-circle',
    };
  }

  private static function actionLabel(string $action): string
  {
    return match ($action) {
      'route_rebuild'    => 'Rebuild Routes',
      'cache_rebuild'    => 'Clear Cache',
      'create_stub'      => 'Create Page Stub',
      'layout_restore'   => 'Restore Layout',
      'directory_create' => 'Create Directories',
      'summary_refresh'  => 'Refresh Summaries',
      'db_optimize'      => 'Optimize Database',
      'service_restart'  => 'Restart Service',
      'session_clear'    => 'Clear Sessions',
      'schema_alter'     => 'Repair Schema',
      default            => 'Execute Fix',
    };
  }
}
