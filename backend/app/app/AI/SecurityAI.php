<?php

/**
 * Security AI — Anomaly Detection Engine.
 *
 * Detects attacks that traditional rules miss by analyzing:
 *  - Abnormal attendance edits
 *  - Bulk database reads
 *  - Unusual login locations/times
 *  - Automated interaction patterns
 *
 * Pipeline: User Activity → Event Collector → Feature Extractor → Threat Classification
 * Outputs: normal | suspicious | attack_likely
 */
class SecurityAI
{
  // Threat classification levels
  public const THREAT_NORMAL        = 'normal';
  public const THREAT_SUSPICIOUS    = 'suspicious';
  public const THREAT_ATTACK_LIKELY = 'attack_likely';

  // Feature weights for scoring
  private static array $featureWeights = [
    'bulk_read'             => 25,
    'bulk_edit'             => 30,
    'time_anomaly'          => 15,
    'velocity_anomaly'      => 20,
    'pattern_anomaly'       => 20,
    'attendance_tampering'  => 35,
    'session_anomaly'       => 25,
    'api_abuse'             => 20,
    'data_exfiltration'     => 35,
  ];

  /**
   * Analyze a user's recent activity and classify the threat level.
   *
   * @return array{threat: string, score: int, features: array, recommendation: string}
   */
  public static function analyze(int $userId): array
  {
    $features = self::extractFeatures($userId);
    $score = self::computeThreatScore($features);
    $threat = self::classifyThreat($score);
    $recommendation = self::getRecommendation($threat, $features);

    // Log if non-normal
    if ($threat !== self::THREAT_NORMAL) {
      self::logThreatDetection($userId, $threat, $score, $features);
    }

    return [
      'threat'         => $threat,
      'score'          => $score,
      'features'       => $features,
      'recommendation' => $recommendation,
      'analyzed_at'    => date('c'),
    ];
  }

  /**
   * Extract behavioral features from recent activity data.
   */
  public static function extractFeatures(int $userId): array
  {
    $features = [];
    $window15 = date('Y-m-d H:i:s', strtotime('-15 minutes'));
    $window1h = date('Y-m-d H:i:s', strtotime('-1 hour'));
    $window24h = date('Y-m-d H:i:s', strtotime('-24 hours'));

    try {
      // 1. Bulk read detection (many SELECT-heavy page loads)
      $readCount = (int) (db()->fetchOne(
        "SELECT COUNT(*) as cnt FROM behavior_log
                 WHERE user_id = :uid AND created_at > :w
                 AND (action LIKE '%list%' OR action LIKE '%view%' OR action LIKE '%export%' OR action LIKE '%report%')",
        ['uid' => $userId, 'w' => $window15]
      )['cnt'] ?? 0);
      if ($readCount > 40) {
        $features['bulk_read'] = [
          'detected' => true,
          'value' => $readCount,
          'detail' => "{$readCount} read-type actions in 15 min",
        ];
      }

      // 2. Bulk edit detection (many writes in short window)
      $editCount = (int) (db()->fetchOne(
        "SELECT COUNT(*) as cnt FROM behavior_log
                 WHERE user_id = :uid AND created_at > :w
                 AND (action LIKE '%create%' OR action LIKE '%update%' OR action LIKE '%delete%' OR action LIKE '%edit%')",
        ['uid' => $userId, 'w' => $window15]
      )['cnt'] ?? 0);
      if ($editCount > 20) {
        $features['bulk_edit'] = [
          'detected' => true,
          'value' => $editCount,
          'detail' => "{$editCount} write actions in 15 min",
        ];
      }

      // 3. Time anomaly (activity outside normal hours for this user)
      $hour = (int) date('H');
      $userPattern = db()->fetchOne(
        "SELECT MIN(HOUR(created_at)) as min_hour, MAX(HOUR(created_at)) as max_hour
                 FROM behavior_log WHERE user_id = :uid AND created_at > :w",
        ['uid' => $userId, 'w' => $window24h]
      );
      $typicalMin = (int) ($userPattern['min_hour'] ?? 6);
      $typicalMax = (int) ($userPattern['max_hour'] ?? 22);
      if ($hour < 5 || ($typicalMax < 20 && $hour > $typicalMax + 3)) {
        $features['time_anomaly'] = [
          'detected' => true,
          'value' => $hour,
          'detail' => "Activity at {$hour}:00, typical range {$typicalMin}:00–{$typicalMax}:00",
        ];
      }

      // 4. Velocity anomaly (action frequency spike vs baseline)
      $recentRate = $readCount + $editCount; // last 15 min
      $baselineRow = db()->fetchOne(
        "SELECT COUNT(*) / GREATEST(TIMESTAMPDIFF(MINUTE, MIN(created_at), MAX(created_at)), 1) * 15 as rate
                 FROM behavior_log WHERE user_id = :uid AND created_at > :w",
        ['uid' => $userId, 'w' => $window24h]
      );
      $baselineRate = (float) ($baselineRow['rate'] ?? 10);
      if ($baselineRate > 0 && $recentRate > $baselineRate * 3) {
        $features['velocity_anomaly'] = [
          'detected' => true,
          'value' => $recentRate,
          'detail' => "Rate {$recentRate}/15min vs baseline " . round($baselineRate, 1) . "/15min",
        ];
      }

      // 5. Attendance tampering detection
      $attEditCount = (int) (db()->fetchOne(
        "SELECT COUNT(*) as cnt FROM audit_logs
                 WHERE user_id = :uid AND created_at > :w
                 AND model = 'attendance' AND action IN ('update','delete')",
        ['uid' => $userId, 'w' => $window1h]
      )['cnt'] ?? 0);
      if ($attEditCount > 10) {
        $features['attendance_tampering'] = [
          'detected' => true,
          'value' => $attEditCount,
          'detail' => "{$attEditCount} attendance modifications in 1 hour",
        ];
      }

      // 6. Session anomaly (multiple concurrent sessions)
      $activeSessions = (int) (db()->fetchOne(
        "SELECT COUNT(*) as cnt FROM session_intelligence
                 WHERE user_id = :uid AND is_active = 1",
        ['uid' => $userId]
      )['cnt'] ?? 0);
      if ($activeSessions > 3) {
        $features['session_anomaly'] = [
          'detected' => true,
          'value' => $activeSessions,
          'detail' => "{$activeSessions} concurrent active sessions",
        ];
      }

      // 7. API abuse detection (too many API calls)
      $apiCalls = (int) (db()->fetchOne(
        "SELECT COUNT(*) as cnt FROM behavior_log
                 WHERE user_id = :uid AND created_at > :w AND action LIKE 'api_%'",
        ['uid' => $userId, 'w' => $window15]
      )['cnt'] ?? 0);
      if ($apiCalls > 60) {
        $features['api_abuse'] = [
          'detected' => true,
          'value' => $apiCalls,
          'detail' => "{$apiCalls} API calls in 15 min",
        ];
      }

      // 8. Data exfiltration pattern (exports + bulk reads)
      $exportCount = (int) (db()->fetchOne(
        "SELECT COUNT(*) as cnt FROM behavior_log
                 WHERE user_id = :uid AND created_at > :w AND action LIKE '%export%'",
        ['uid' => $userId, 'w' => $window1h]
      )['cnt'] ?? 0);
      if ($exportCount > 5) {
        $features['data_exfiltration'] = [
          'detected' => true,
          'value' => $exportCount,
          'detail' => "{$exportCount} export actions in 1 hour",
        ];
      }
    } catch (\Throwable $e) {
      error_log("SecurityAI feature extraction error: " . $e->getMessage());
    }

    return $features;
  }

  /**
   * Compute threat score from extracted features.
   */
  public static function computeThreatScore(array $features): int
  {
    $score = 0;
    foreach ($features as $name => $feature) {
      if (!empty($feature['detected'])) {
        $score += self::$featureWeights[$name] ?? 10;
      }
    }
    return min($score, 100);
  }

  /**
   * Classify threat level from score.
   */
  public static function classifyThreat(int $score): string
  {
    if ($score <= 30) {
      return self::THREAT_NORMAL;
    }
    if ($score <= 60) {
      return self::THREAT_SUSPICIOUS;
    }
    return self::THREAT_ATTACK_LIKELY;
  }

  /**
   * Generate a human-readable recommendation based on threat classification.
   */
  private static function getRecommendation(string $threat, array $features): string
  {
    if ($threat === self::THREAT_NORMAL) {
      return 'No action required.';
    }

    $reasons = [];
    foreach ($features as $name => $f) {
      if (!empty($f['detected'])) {
        $reasons[] = $f['detail'];
      }
    }
    $reasonStr = implode('; ', $reasons);

    if ($threat === self::THREAT_SUSPICIOUS) {
      return "Elevated monitoring recommended. Indicators: {$reasonStr}";
    }
    return "Immediate review required. Attack indicators: {$reasonStr}";
  }

  /**
   * Log a threat detection to security_events.
   */
  private static function logThreatDetection(int $userId, string $threat, int $score, array $features): void
  {
    try {
      db()->insert('security_events', [
        'event_type'  => 'threat_detection',
        'severity'    => $threat === self::THREAT_ATTACK_LIKELY ? 'critical' : 'suspicious',
        'user_id'     => $userId,
        'ip_address'  => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
        'details'     => json_encode([
          'threat'   => $threat,
          'score'    => $score,
          'features' => $features,
        ], JSON_UNESCAPED_UNICODE),
        'resolved'    => 0,
        'created_at'  => date('Y-m-d H:i:s'),
      ]);
    } catch (\Throwable $e) {
      error_log("SecurityAI log failed: " . $e->getMessage());
    }
  }

  /**
   * Get recent threat detections for the security dashboard.
   */
  public static function getRecentThreats(int $limit = 50): array
  {
    try {
      return db()->fetchAll(
        "SELECT se.*, u.full_name, u.email
                 FROM security_events se
                 LEFT JOIN users u ON u.id = se.user_id
                 WHERE se.event_type = 'threat_detection'
                 ORDER BY se.created_at DESC
                 LIMIT " . min($limit, 200)
      );
    } catch (\Throwable $e) {
      return [];
    }
  }

  /**
   * Get threat summary statistics for the dashboard.
   */
  public static function getThreatSummary(int $hours = 24): array
  {
    try {
      $since = date('Y-m-d H:i:s', strtotime("-{$hours} hours"));
      $rows = db()->fetchAll(
        "SELECT severity, COUNT(*) as cnt FROM security_events
                 WHERE event_type = 'threat_detection' AND created_at > :since
                 GROUP BY severity",
        ['since' => $since]
      );
      $summary = ['critical' => 0, 'suspicious' => 0, 'total' => 0];
      foreach ($rows as $r) {
        $summary[$r['severity']] = (int) $r['cnt'];
        $summary['total'] += (int) $r['cnt'];
      }
      return $summary;
    } catch (\Throwable $e) {
      return ['critical' => 0, 'suspicious' => 0, 'total' => 0];
    }
  }
}
