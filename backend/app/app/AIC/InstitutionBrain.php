<?php

/**
 * AIC — Institution Brain
 * Central orchestrator of the Institutional Consciousness Layer.
 * Aggregates insights from all AIC modules into a unified institutional awareness state.
 *
 * Cycle: Observe → Analyze → Predict → Advise → Report
 */
class InstitutionBrain
{
  private static string $summaryPath = '';

  private static function summaryPath(): string
  {
    if (!self::$summaryPath) {
      self::$summaryPath = (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2)) . '/storage/aic-summary.json';
    }
    return self::$summaryPath;
  }

  /**
   * Run a full institutional consciousness cycle.
   */
  public static function cycle(): array
  {
    $start = microtime(true);

    $attendance  = AttendanceInsights::analyze();
    $workload    = WorkloadBalancer::analyze();
    $engagement  = StudentEngagementAI::analyze();
    $predictions = AcademicPredictor::predict();
    $behavior    = InstitutionalBehaviorAnalyzer::analyze();
    $policy      = PolicyAdvisor::assess();

    $healthScore = self::calculateInstitutionalHealth($attendance, $workload, $engagement);

    $summary = [
      'timestamp'         => date('Y-m-d H:i:s'),
      'cycle_ms'          => round((microtime(true) - $start) * 1000),
      'health_score'      => $healthScore,
      'attendance'        => $attendance,
      'workload'          => $workload,
      'engagement'        => $engagement,
      'predictions'       => $predictions,
      'behavior'          => $behavior,
      'policy'            => $policy,
      'risk_alerts'       => self::gatherRiskAlerts($attendance, $workload, $engagement, $predictions),
    ];

    self::storeSummary($summary);
    return $summary;
  }

  /**
   * Get current status (for MCC panel).
   */
  public static function getStatus(): array
  {
    $summary = self::loadSummary();
    return [
      'online'           => true,
      'last_cycle'       => $summary['timestamp'] ?? null,
      'cycle_ms'         => $summary['cycle_ms'] ?? 0,
      'health_score'     => $summary['health_score'] ?? 100,
      'attendance'       => $summary['attendance'] ?? [],
      'workload'         => $summary['workload'] ?? [],
      'engagement'       => $summary['engagement'] ?? [],
      'predictions'      => $summary['predictions'] ?? [],
      'risk_alerts'      => $summary['risk_alerts'] ?? [],
      'policy'           => $summary['policy'] ?? [],
    ];
  }

  /**
   * Calculate overall institutional health (0-100).
   */
  private static function calculateInstitutionalHealth(array $att, array $wl, array $eng): int
  {
    $attScore = $att['health_score'] ?? 80;
    $wlScore  = $wl['balance_score'] ?? 80;
    $engScore = $eng['engagement_score'] ?? 80;

    // Weighted average: Attendance 40%, Workload 30%, Engagement 30%
    return (int) round(($attScore * 0.4) + ($wlScore * 0.3) + ($engScore * 0.3));
  }

  /**
   * Gather risk alerts from all modules.
   */
  private static function gatherRiskAlerts(array $att, array $wl, array $eng, array $pred): array
  {
    $alerts = [];

    foreach ($att['alerts'] ?? [] as $a) {
      $alerts[] = array_merge($a, ['source' => 'attendance']);
    }
    foreach ($wl['alerts'] ?? [] as $a) {
      $alerts[] = array_merge($a, ['source' => 'workload']);
    }
    foreach ($eng['alerts'] ?? [] as $a) {
      $alerts[] = array_merge($a, ['source' => 'engagement']);
    }
    foreach ($pred['alerts'] ?? [] as $a) {
      $alerts[] = array_merge($a, ['source' => 'prediction']);
    }

    // Sort by severity
    usort($alerts, function ($a, $b) {
      $order = ['CRITICAL' => 0, 'HIGH' => 1, 'MEDIUM' => 2, 'LOW' => 3];
      return ($order[$a['severity'] ?? 'LOW'] ?? 4) - ($order[$b['severity'] ?? 'LOW'] ?? 4);
    });

    return $alerts;
  }

  private static function storeSummary(array $summary): void
  {
    $dir = dirname(self::summaryPath());
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    @file_put_contents(self::summaryPath(), json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
  }

  private static function loadSummary(): array
  {
    $path = self::summaryPath();
    if (!is_file($path)) return [];
    $data = @file_get_contents($path);
    return $data ? (json_decode($data, true) ?: []) : [];
  }
}
