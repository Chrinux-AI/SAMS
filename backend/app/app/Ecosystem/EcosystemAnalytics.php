<?php

/**
 * EcosystemAnalytics — Cross-Institutional Analytics Dashboard Engine
 *
 * Displays:
 *   - Institutional health comparison (anonymized)
 *   - Adoption metrics
 *   - Shared improvements
 *   - Intelligence evolution timeline
 */
class EcosystemAnalytics
{
  /**
   * Compile full ecosystem analytics.
   */
  public static function compile(): array
  {
    return [
      'health'      => self::institutionalHealth(),
      'adoption'    => self::adoptionMetrics(),
      'improvements' => self::sharedImprovements(),
      'evolution'   => self::intelligenceEvolution(),
      'compiled_at' => date('Y-m-d H:i:s'),
    ];
  }

  /**
   * Anonymized institutional health comparison.
   */
  private static function institutionalHealth(): array
  {
    TenantOrchestrator::ensureTable();
    $tenants = db()->fetchAll("SELECT id, school_name, status FROM tenants WHERE status = 'active'");

    $health = [];
    foreach ($tenants as $t) {
      $tenantId = (int)$t['id'];

      // Attendance rate for this tenant
      $attendanceRate = 0;
      try {
        if (table_exists('attendance')) {
          $total = db()->fetchOne(
            "SELECT COUNT(*) AS cnt FROM attendance WHERE tenant_id = ? AND date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)",
            [$tenantId]
          );
          $present = db()->fetchOne(
            "SELECT COUNT(*) AS cnt FROM attendance WHERE tenant_id = ? AND status = 'present' AND date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)",
            [$tenantId]
          );
          $totalCnt = (int)($total['cnt'] ?? 0);
          $attendanceRate = $totalCnt > 0 ? round(((int)($present['cnt'] ?? 0)) / $totalCnt * 100) : 0;
        }
      } catch (\Throwable $e) {
        // Skip
      }

      // User count
      $userCount = 0;
      try {
        $row = db()->fetchOne("SELECT COUNT(*) AS cnt FROM users WHERE tenant_id = ?", [$tenantId]);
        $userCount = (int)($row['cnt'] ?? 0);
      } catch (\Throwable $e) {
        // Skip
      }

      $health[] = [
        'institution' => 'School-' . $tenantId, // Anonymized
        'status'      => $t['status'],
        'attendance'  => $attendanceRate,
        'users'       => $userCount,
        'score'       => min(100, $attendanceRate + ($userCount > 10 ? 10 : 0)),
      ];
    }

    return $health;
  }

  /**
   * Adoption metrics across the ecosystem.
   */
  private static function adoptionMetrics(): array
  {
    $metrics = [
      'total_institutions' => TenantOrchestrator::countActive(),
      'features_adopted'   => [],
    ];

    // Check feature adoption
    $features = [
      'attendance' => 'SELECT COUNT(DISTINCT tenant_id) AS cnt FROM attendance',
      'notices'    => 'SELECT COUNT(DISTINCT tenant_id) AS cnt FROM notices',
    ];

    foreach ($features as $feature => $sql) {
      try {
        if (table_exists($feature === 'attendance' ? 'attendance' : 'notices')) {
          $row = db()->fetchOne($sql);
          $metrics['features_adopted'][$feature] = (int)($row['cnt'] ?? 0);
        }
      } catch (\Throwable $e) {
        $metrics['features_adopted'][$feature] = 0;
      }
    }

    return $metrics;
  }

  /**
   * Shared improvements from knowledge exchange.
   */
  private static function sharedImprovements(): array
  {
    $exchange = KnowledgeExchange::getSummary();
    $federation = FederationEngine::getSummary();

    return [
      'knowledge_patterns' => $exchange['total_patterns'] ?? 0,
      'effective_patterns'  => $exchange['effective'] ?? 0,
      'federation_approved' => $federation['approved'] ?? 0,
      'federation_distributed' => $federation['distributed'] ?? 0,
    ];
  }

  /**
   * Intelligence evolution timeline.
   */
  private static function intelligenceEvolution(): array
  {
    $timeline = [];

    // Load last cognitive summary
    $cogFile = BASE_PATH . '/storage/cognitive-summary.json';
    if (is_file($cogFile)) {
      $cog = json_decode(file_get_contents($cogFile), true);
      $timeline[] = [
        'phase'     => 'Cognitive',
        'score'     => $cog['cognitive_score'] ?? 0,
        'timestamp' => $cog['timestamp'] ?? 'unknown',
      ];
    }

    // Load intelligence summary
    $intFile = BASE_PATH . '/storage/intelligence-summary.json';
    if (is_file($intFile)) {
      $intel = json_decode(file_get_contents($intFile), true);
      $timeline[] = [
        'phase'     => 'Intelligence',
        'score'     => $intel['intelligence_score'] ?? 0,
        'timestamp' => $intel['timestamp'] ?? 'unknown',
      ];
    }

    // Ecosystem summary
    $timeline[] = [
      'phase'     => 'Ecosystem',
      'score'     => self::calculateEcosystemScore(),
      'timestamp' => date('Y-m-d H:i:s'),
    ];

    return $timeline;
  }

  /**
   * Calculate overall ecosystem score.
   */
  public static function calculateEcosystemScore(): int
  {
    $score = 100;

    // Deduct for zero tenants
    $tenants = TenantOrchestrator::countActive();
    if ($tenants === 0) $score -= 20;

    // Deduct for no federation activity
    $fed = FederationEngine::getSummary();
    if (($fed['total'] ?? 0) === 0) $score -= 10;

    // Deduct for no knowledge exchange
    $kx = KnowledgeExchange::getSummary();
    if (($kx['total_patterns'] ?? 0) === 0) $score -= 10;

    return max(0, min(100, $score));
  }

  /**
   * Get summary for dashboard.
   */
  public static function getSummary(): array
  {
    return [
      'ecosystem_score' => self::calculateEcosystemScore(),
      'tenants'         => TenantOrchestrator::getSummary(),
      'federation'      => FederationEngine::getSummary(),
      'exchange'        => KnowledgeExchange::getSummary(),
    ];
  }
}
