<?php

/**
 * KnowledgeExchange — Anonymized Learning Synchronization
 *
 * Process:
 *   1. Local insights generated
 *   2. Sanitization applied
 *   3. Pattern hashed
 *   4. Uploaded to ecosystem registry
 *   5. Other schools receive improvements
 */
class KnowledgeExchange
{
  /**
   * Ensure the knowledge exchange registry table exists.
   */
  public static function ensureTable(): void
  {
    $sql = "CREATE TABLE IF NOT EXISTS knowledge_exchange (
      id INT AUTO_INCREMENT PRIMARY KEY,
      pattern_hash VARCHAR(64) NOT NULL,
      category VARCHAR(100) NOT NULL,
      insight_type VARCHAR(100) NOT NULL,
      abstracted_insight TEXT NOT NULL,
      adoption_count INT DEFAULT 0,
      effectiveness DECIMAL(3,2) DEFAULT 0.00,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      UNIQUE KEY uk_hash (pattern_hash),
      INDEX idx_category (category)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    db()->query($sql);
  }

  /**
   * Extract and share local insights to the exchange.
   */
  public static function syncLocalInsights(): array
  {
    self::ensureTable();
    $synced = 0;

    try {
      // Extract insights from InsightGenerator
      $insights = InsightGenerator::getSummary();
      $recentInsights = $insights['recent'] ?? [];

      foreach (array_slice($recentInsights, 0, 10) as $insight) {
        $category = $insight['category'] ?? 'general';
        $type = $insight['type'] ?? 'observation';
        $message = $insight['message'] ?? '';

        if (empty($message)) continue;

        // Anonymize the insight
        $anonymized = self::anonymize($message);
        $hash = hash('sha256', $category . ':' . $type . ':' . $anonymized);

        // Check if already registered
        $existing = db()->fetchOne("SELECT id FROM knowledge_exchange WHERE pattern_hash = ?", [$hash]);
        if ($existing) continue;

        db()->insert('knowledge_exchange', [
          'pattern_hash'       => $hash,
          'category'           => $category,
          'insight_type'       => $type,
          'abstracted_insight' => $anonymized,
          'adoption_count'     => 1,
          'effectiveness'      => 0.50,
        ]);
        $synced++;
      }
    } catch (\Throwable $e) {
      ErrorCollector::log('ecosystem', 'Knowledge sync error: ' . $e->getMessage(), 'MEDIUM');
    }

    ErrorCollector::log('ecosystem', "Knowledge exchange synced {$synced} insights", 'INFO');
    return ['synced' => $synced];
  }

  /**
   * Anonymize an insight message — remove any identifying information.
   */
  private static function anonymize(string $message): string
  {
    // Remove potential names (capitalized word pairs)
    $message = preg_replace('/\b[A-Z][a-z]+\s+[A-Z][a-z]+\b/', '[REDACTED]', $message);
    // Remove email addresses
    $message = preg_replace('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', '[REDACTED]', $message);
    // Remove phone numbers
    $message = preg_replace('/\b\d{3}[-.\s]?\d{3}[-.\s]?\d{4}\b/', '[REDACTED]', $message);
    // Remove specific IDs
    $message = preg_replace('/\bID[:#]?\s*\d+\b/i', '[ID]', $message);

    return $message;
  }

  /**
   * Get available improvements from the exchange.
   */
  public static function getAvailableImprovements(string $category = ''): array
  {
    self::ensureTable();
    if ($category) {
      return db()->fetchAll(
        "SELECT * FROM knowledge_exchange WHERE category = ? AND effectiveness >= 0.50 ORDER BY effectiveness DESC LIMIT 20",
        [$category]
      );
    }
    return db()->fetchAll("SELECT * FROM knowledge_exchange WHERE effectiveness >= 0.50 ORDER BY effectiveness DESC LIMIT 20");
  }

  /**
   * Adopt an improvement locally.
   */
  public static function adopt(int $exchangeId): bool
  {
    $result = db()->query(
      "UPDATE knowledge_exchange SET adoption_count = adoption_count + 1 WHERE id = ?",
      [$exchangeId]
    );
    return (bool)$result;
  }

  /**
   * Report effectiveness of an adopted improvement.
   */
  public static function reportEffectiveness(int $exchangeId, float $effectiveness): bool
  {
    $effectiveness = max(0, min(1, $effectiveness));
    $result = db()->query(
      "UPDATE knowledge_exchange SET effectiveness = (effectiveness + ?) / 2 WHERE id = ?",
      [$effectiveness, $exchangeId]
    );
    return (bool)$result;
  }

  /**
   * Get summary for dashboard.
   */
  public static function getSummary(): array
  {
    self::ensureTable();
    $total = db()->fetchOne("SELECT COUNT(*) AS cnt FROM knowledge_exchange");
    $effective = db()->fetchOne("SELECT COUNT(*) AS cnt FROM knowledge_exchange WHERE effectiveness >= 0.70");
    $adopted = db()->fetchOne("SELECT SUM(adoption_count) AS total FROM knowledge_exchange");

    return [
      'total_patterns'   => (int)($total['cnt'] ?? 0),
      'effective'        => (int)($effective['cnt'] ?? 0),
      'total_adoptions'  => (int)($adopted['total'] ?? 0),
      'sync_model'       => 'Anonymized + Hashed',
    ];
  }
}
