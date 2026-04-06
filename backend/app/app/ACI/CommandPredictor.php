<?php

/**
 * ACI — Command Predictor
 * Analyzes signals to predict future failures before they occur.
 * Consumes SystemObserver signals + PredictionEngine output + historical patterns.
 */
class CommandPredictor
{
  /**
   * Generate predictions from observed signals.
   * @param array $signals from SystemObserver::observe()
   */
  public static function predict(array $signals): array
  {
    $predictions = [];

    // 1. Route corruption prediction
    $brokenRoutes = $signals['broken_routes']['count'] ?? 0;
    if ($brokenRoutes >= 3) {
      $predictions[] = [
        'type'       => 'navigation_corruption',
        'severity'   => 'HIGH',
        'confidence' => min(0.95, 0.6 + ($brokenRoutes * 0.1)),
        'message'    => "$brokenRoutes broken routes detected — navigation corruption probability HIGH",
        'action'     => 'route_rebuild',
      ];
    } elseif ($brokenRoutes >= 1) {
      $predictions[] = [
        'type'       => 'route_decay',
        'severity'   => 'MEDIUM',
        'confidence' => 0.7,
        'message'    => "$brokenRoutes broken route(s) — early route decay detected",
        'action'     => 'route_rebuild',
      ];
    }

    // 2. Database overload prediction
    $dbLatency = $signals['db_health']['latency_ms'] ?? 0;
    if ($dbLatency > 200) {
      $predictions[] = [
        'type'       => 'db_overload',
        'severity'   => $dbLatency > 500 ? 'CRITICAL' : 'HIGH',
        'confidence' => min(0.95, 0.5 + ($dbLatency / 1000)),
        'message'    => "DB latency {$dbLatency}ms — overload risk " . ($dbLatency > 500 ? 'CRITICAL' : 'HIGH'),
        'action'     => 'db_optimize',
      ];
    }

    // 3. Error storm prediction
    $errorRate = $signals['error_rate']['errors_last_hour'] ?? 0;
    if ($errorRate >= 20) {
      $predictions[] = [
        'type'       => 'error_storm',
        'severity'   => 'CRITICAL',
        'confidence' => 0.9,
        'message'    => "$errorRate errors/hour — error storm imminent",
        'action'     => 'service_restart',
      ];
    } elseif ($errorRate >= 5) {
      $predictions[] = [
        'type'       => 'error_escalation',
        'severity'   => 'MEDIUM',
        'confidence' => 0.65,
        'message'    => "$errorRate errors/hour — escalation risk",
        'action'     => 'cache_rebuild',
      ];
    }

    // 4. Session overload
    $sessionCount = $signals['session_health']['session_count'] ?? 0;
    if ($sessionCount > 200) {
      $predictions[] = [
        'type'       => 'session_overload',
        'severity'   => 'HIGH',
        'confidence' => 0.8,
        'message'    => "$sessionCount active sessions — overload risk",
        'action'     => 'session_clear',
      ];
    }

    // 5. Storage issues
    $storageIssues = $signals['storage_health']['issues'] ?? [];
    if (!empty($storageIssues)) {
      $predictions[] = [
        'type'       => 'storage_failure',
        'severity'   => 'HIGH',
        'confidence' => 0.85,
        'message'    => count($storageIssues) . ' storage issue(s): ' . implode(', ', $storageIssues),
        'action'     => 'directory_create',
      ];
    }

    // 6. Cron staleness
    $staleCrons = $signals['cron_health']['stale'] ?? [];
    if (!empty($staleCrons)) {
      $predictions[] = [
        'type'       => 'cron_decay',
        'severity'   => 'MEDIUM',
        'confidence' => 0.7,
        'message'    => count($staleCrons) . ' stale cron(s): ' . implode(', ', $staleCrons),
        'action'     => 'summary_refresh',
      ];
    }

    // 7. Overall risk level
    $maxSeverity = 'LOW';
    foreach ($predictions as $p) {
      if ($p['severity'] === 'CRITICAL') {
        $maxSeverity = 'CRITICAL';
        break;
      }
      if ($p['severity'] === 'HIGH') $maxSeverity = 'HIGH';
      elseif ($p['severity'] === 'MEDIUM' && $maxSeverity !== 'HIGH') $maxSeverity = 'MEDIUM';
    }

    return [
      'predictions' => $predictions,
      'count'       => count($predictions),
      'risk_level'  => $maxSeverity,
    ];
  }
}
