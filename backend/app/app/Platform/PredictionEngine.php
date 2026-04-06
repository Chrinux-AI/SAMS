<?php

/**
 * PredictionEngine — Historical Data Forecasting
 *
 * Uses historical data to forecast:
 *   attendance drops, system overload, database growth,
 *   user activity spikes, potential failures
 *
 * Output: actionable predictions with confidence scores.
 */
class PredictionEngine
{
  /**
   * Generate all predictions.
   *
   * @return array ['predictions' => [...], 'risk_level' => string]
   */
  public static function predict(): array
  {
    $predictions = [];

    $predictions = array_merge($predictions, self::predictAttendanceDrops());
    $predictions = array_merge($predictions, self::predictSystemOverload());
    $predictions = array_merge($predictions, self::predictDatabaseGrowth());
    $predictions = array_merge($predictions, self::predictActivitySpikes());
    $predictions = array_merge($predictions, self::predictFailures());

    // Sort by confidence descending
    usort($predictions, fn($a, $b) => ($b['confidence'] ?? 0) <=> ($a['confidence'] ?? 0));

    // Overall risk level
    $highCount = count(array_filter($predictions, fn($p) => ($p['severity'] ?? '') === 'high'));
    $riskLevel = 'low';
    if ($highCount >= 3) $riskLevel = 'critical';
    elseif ($highCount >= 1) $riskLevel = 'elevated';
    elseif (count($predictions) >= 3) $riskLevel = 'moderate';

    if (!empty($predictions)) {
      ErrorCollector::log('platform', count($predictions) . " predictions generated (risk: {$riskLevel})", 'INFO');
    }

    return [
      'predictions' => $predictions,
      'risk_level'  => $riskLevel,
      'generated'   => date('Y-m-d H:i:s'),
    ];
  }

  /**
   * Predict attendance drops using linear trend analysis.
   */
  private static function predictAttendanceDrops(): array
  {
    $predictions = [];

    try {
      // Get weekly attendance rates for the last 6 weeks
      $weeks = db()->fetchAll(
        "SELECT YEARWEEK(date, 1) AS yw,
                COUNT(*) AS total,
                SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) AS absences
         FROM attendance
         WHERE date >= DATE_SUB(CURDATE(), INTERVAL 42 DAY)
         GROUP BY yw
         ORDER BY yw ASC"
      );

      if (count($weeks) >= 3) {
        $rates = [];
        foreach ($weeks as $w) {
          $total = (int) $w['total'];
          if ($total > 0) {
            $rates[] = ((int) $w['absences'] / $total) * 100;
          }
        }

        if (count($rates) >= 3) {
          // Simple linear regression to predict next week
          $n = count($rates);
          $xSum = $ySum = $xySum = $xxSum = 0;
          for ($i = 0; $i < $n; $i++) {
            $xSum += $i;
            $ySum += $rates[$i];
            $xySum += $i * $rates[$i];
            $xxSum += $i * $i;
          }
          $denominator = ($n * $xxSum - $xSum * $xSum);
          if ($denominator != 0) {
            $slope = ($n * $xySum - $xSum * $ySum) / $denominator;
            $intercept = ($ySum - $slope * $xSum) / $n;
            $predicted = $intercept + $slope * $n;

            if ($slope > 0.5 && $predicted > 15) {
              $predictions[] = [
                'type'       => 'attendance_drop',
                'severity'   => $predicted > 30 ? 'high' : 'medium',
                'detail'     => sprintf(
                  'Absence rate trending upward (%.1f%% slope). Predicted next week: %.1f%%',
                  $slope,
                  $predicted
                ),
                'predicted_value' => round($predicted, 1),
                'confidence' => min(0.85, 0.5 + ($n * 0.05)),
                'timeframe'  => '7 days',
              ];
            }
          }
        }
      }

      // Per-class predictions — classes likely to fall below 75% attendance
      if (function_exists('table_exists') && table_exists('classes')) {
        $classRates = db()->fetchAll(
          "SELECT a.class_id, c.class_name AS class_name,
                  COUNT(*) AS total,
                  SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) AS present
           FROM attendance a
           JOIN classes c ON c.id = a.class_id
           WHERE a.date >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
           GROUP BY a.class_id, c.class_name
           HAVING (present / total) < 0.80 AND total >= 10
           ORDER BY (present / total) ASC
           LIMIT 10"
        );

        foreach ($classRates as $cr) {
          $attendanceRate = round(((int) $cr['present'] / (int) $cr['total']) * 100, 1);
          $predictions[] = [
            'type'       => 'class_attendance_risk',
            'severity'   => $attendanceRate < 70 ? 'high' : 'medium',
            'detail'     => "{$cr['class_name']} at {$attendanceRate}% attendance — risk of falling below 75%",
            'predicted_value' => $attendanceRate,
            'confidence' => min(0.8, (80 - $attendanceRate) / 30 + 0.5),
            'timeframe'  => '5 days',
            'class_id'   => (int) $cr['class_id'],
          ];
        }
      }
    } catch (\Throwable $e) {
      // Non-critical
    }

    return $predictions;
  }

  /**
   * Predict system overload from resource trends.
   */
  private static function predictSystemOverload(): array
  {
    $predictions = [];

    try {
      if (!function_exists('table_exists') || !table_exists('system_metrics')) {
        return [];
      }

      // Memory trend over last 24 hours
      $memTrend = db()->fetchAll(
        "SELECT value, recorded_at FROM system_metrics
         WHERE metric = 'memory_usage'
         ORDER BY recorded_at DESC LIMIT 24"
      );

      if (count($memTrend) >= 4) {
        $values = array_reverse(array_column($memTrend, 'value'));
        $recent = array_slice($values, -3);
        $avgRecent = array_sum($recent) / count($recent);
        $older = array_slice($values, 0, 3);
        $avgOlder = array_sum($older) / count($older);

        if ($avgRecent > $avgOlder * 1.3 && $avgRecent > 50000000) {
          $predictions[] = [
            'type'       => 'memory_overload',
            'severity'   => 'high',
            'detail'     => sprintf('Memory usage trending up 30%%+ (%.0fMB avg recent)', $avgRecent / 1048576),
            'confidence' => 0.7,
            'timeframe'  => '24 hours',
          ];
        }
      }

      // DB latency trend
      $latencyTrend = db()->fetchAll(
        "SELECT value FROM system_metrics
         WHERE metric = 'db_latency'
         ORDER BY recorded_at DESC LIMIT 10"
      );

      if (count($latencyTrend) >= 4) {
        $latValues = array_column($latencyTrend, 'value');
        $avgLat = array_sum($latValues) / count($latValues);
        if ($avgLat > 200) {
          $predictions[] = [
            'type'       => 'db_slowdown',
            'severity'   => $avgLat > 1000 ? 'high' : 'medium',
            'detail'     => sprintf('Average DB latency %.0fms — possible degradation', $avgLat),
            'confidence' => min(0.8, $avgLat / 2000),
            'timeframe'  => '12 hours',
          ];
        }
      }
    } catch (\Throwable $e) {
      // Non-critical
    }

    return $predictions;
  }

  /**
   * Predict database growth trajectory.
   */
  private static function predictDatabaseGrowth(): array
  {
    $predictions = [];

    try {
      if (!function_exists('table_exists') || !table_exists('system_metrics')) {
        return [];
      }

      // DB size trend
      $sizeTrend = db()->fetchAll(
        "SELECT value, recorded_at FROM system_metrics
         WHERE metric = 'db_size_mb'
         ORDER BY recorded_at DESC LIMIT 30"
      );

      if (count($sizeTrend) >= 5) {
        $sizes = array_reverse(array_map(fn($r) => (float) $r['value'], $sizeTrend));
        $firstSize = $sizes[0];
        $lastSize = end($sizes);

        if ($firstSize > 0) {
          $growthPct = (($lastSize - $firstSize) / $firstSize) * 100;
          if ($growthPct > 10) {
            $dailyGrowth = $growthPct / count($sizes);
            $projected30 = $lastSize * (1 + ($dailyGrowth * 30 / 100));
            $predictions[] = [
              'type'       => 'db_growth',
              'severity'   => $projected30 > 500 ? 'high' : 'medium',
              'detail'     => sprintf(
                'DB growing %.1f%% per cycle. Current: %.1fMB, projected 30 days: %.1fMB',
                $dailyGrowth,
                $lastSize,
                $projected30
              ),
              'predicted_value' => round($projected30, 1),
              'confidence' => min(0.75, 0.4 + (count($sizes) * 0.02)),
              'timeframe'  => '30 days',
            ];
          }
        }
      }
    } catch (\Throwable $e) {
      // Non-critical
    }

    return $predictions;
  }

  /**
   * Predict user activity spikes.
   */
  private static function predictActivitySpikes(): array
  {
    $predictions = [];

    try {
      if (!function_exists('table_exists') || !table_exists('activity_log')) {
        return [];
      }

      // Compare today's activity to weekly average at same hour
      $hour = (int) date('G');
      $todayCount = db()->fetchOne(
        "SELECT COUNT(*) AS cnt FROM activity_log
         WHERE DATE(created_at) = CURDATE() AND HOUR(created_at) <= ?",
        [$hour]
      );
      $weekAvg = db()->fetchOne(
        "SELECT ROUND(AVG(cnt)) AS avg_cnt FROM (
           SELECT DATE(created_at) AS d, COUNT(*) AS cnt
           FROM activity_log
           WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
             AND created_at < CURDATE()
             AND HOUR(created_at) <= ?
           GROUP BY d
         ) sub",
        [$hour]
      );

      $today = (int) ($todayCount['cnt'] ?? 0);
      $avg = (int) ($weekAvg['avg_cnt'] ?? 0);

      if ($avg > 0 && $today > $avg * 1.5) {
        $spike = round(($today / $avg - 1) * 100);
        $predictions[] = [
          'type'       => 'activity_spike',
          'severity'   => $spike > 100 ? 'high' : 'medium',
          'detail'     => "Today's activity {$spike}% above weekly average ({$today} vs avg {$avg})",
          'confidence' => min(0.85, 0.5 + ($spike / 200)),
          'timeframe'  => 'today',
        ];
      }
    } catch (\Throwable $e) {
      // Non-critical
    }

    return $predictions;
  }

  /**
   * Predict potential failures from error patterns.
   */
  private static function predictFailures(): array
  {
    $predictions = [];

    try {
      if (!function_exists('table_exists') || !table_exists('system_failures')) {
        return [];
      }

      // Check error frequency trend
      $recent = db()->fetchOne(
        "SELECT COUNT(*) AS cnt FROM system_failures
         WHERE detected_at >= DATE_SUB(NOW(), INTERVAL 6 HOUR)"
      );
      $earlier = db()->fetchOne(
        "SELECT COUNT(*) AS cnt FROM system_failures
         WHERE detected_at BETWEEN DATE_SUB(NOW(), INTERVAL 12 HOUR) AND DATE_SUB(NOW(), INTERVAL 6 HOUR)"
      );

      $recentCnt = (int) ($recent['cnt'] ?? 0);
      $earlierCnt = (int) ($earlier['cnt'] ?? 0);

      if ($earlierCnt > 0 && $recentCnt > $earlierCnt * 2 && $recentCnt > 5) {
        $predictions[] = [
          'type'       => 'failure_escalation',
          'severity'   => 'high',
          'detail'     => "Error rate doubled: {$earlierCnt} → {$recentCnt} in 6-hour windows",
          'confidence' => min(0.85, 0.6 + ($recentCnt / 50)),
          'timeframe'  => '6 hours',
        ];
      }

      // Recurring module failures
      $recurring = db()->fetchAll(
        "SELECT module, COUNT(*) AS cnt
         FROM system_failures
         WHERE detected_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
         GROUP BY module
         HAVING cnt >= 5
         ORDER BY cnt DESC
         LIMIT 5"
      );

      foreach ($recurring as $r) {
        $predictions[] = [
          'type'       => 'module_instability',
          'severity'   => $r['cnt'] >= 15 ? 'high' : 'medium',
          'detail'     => "Module '{$r['module']}' had {$r['cnt']} failures in 24 hours",
          'confidence' => min(0.8, 0.5 + ($r['cnt'] / 30)),
          'timeframe'  => '24 hours',
        ];
      }
    } catch (\Throwable $e) {
      // Non-critical
    }

    return $predictions;
  }

  /**
   * Get summary for dashboard.
   */
  public static function getSummary(): array
  {
    $result = self::predict();
    return [
      'prediction_count' => count($result['predictions']),
      'risk_level'       => $result['risk_level'],
      'top_predictions'  => array_slice($result['predictions'], 0, 5),
    ];
  }
}
