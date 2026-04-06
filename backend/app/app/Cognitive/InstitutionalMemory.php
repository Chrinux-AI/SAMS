<?php

/**
 * InstitutionalMemory — Long-Term Institutional Learning Store
 *
 * Stores decisions, policy changes, outcomes, improvement metrics.
 * Enables the system to learn from past actions and avoid repeating mistakes.
 *
 * Table: institution_memory
 */
class InstitutionalMemory
{
  /**
   * Ensure institution_memory table exists.
   */
  public static function ensureTable(): void
  {
    db()->query("CREATE TABLE IF NOT EXISTS institution_memory (
      id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      category VARCHAR(60) NOT NULL DEFAULT 'general',
      event_type VARCHAR(80) NOT NULL,
      subject VARCHAR(120) DEFAULT NULL,
      detail TEXT DEFAULT NULL,
      outcome VARCHAR(40) DEFAULT NULL,
      confidence DECIMAL(4,3) DEFAULT 0.500,
      impact_score DECIMAL(5,2) DEFAULT 0.00,
      metadata JSON DEFAULT NULL,
      reviewed TINYINT(1) DEFAULT 0,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
      INDEX idx_im_cat (category),
      INDEX idx_im_type (event_type),
      INDEX idx_im_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  }

  /**
   * Record a memory entry.
   */
  public static function record(
    string $category,
    string $eventType,
    string $subject,
    string $detail,
    string $outcome = 'recorded',
    float $confidence = 0.5,
    float $impactScore = 0.0,
    array $metadata = []
  ): int {
    self::ensureTable();

    $metaJson = !empty($metadata) ? json_encode($metadata) : null;

    db()->query(
      "INSERT INTO institution_memory (category, event_type, subject, detail, outcome, confidence, impact_score, metadata, created_at)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())",
      [$category, $eventType, $subject, $detail, $outcome, $confidence, $impactScore, $metaJson]
    );

    return (int) db()->getConnection()->lastInsertId();
  }

  /**
   * Query memories by category and optional type.
   */
  public static function recall(string $category, ?string $eventType = null, int $limit = 20): array
  {
    self::ensureTable();

    if ($eventType) {
      return db()->fetchAll(
        "SELECT * FROM institution_memory WHERE category = ? AND event_type = ? ORDER BY created_at DESC LIMIT ?",
        [$category, $eventType, $limit]
      );
    }

    return db()->fetchAll(
      "SELECT * FROM institution_memory WHERE category = ? ORDER BY created_at DESC LIMIT ?",
      [$category, $limit]
    );
  }

  /**
   * Find memories matching a subject pattern.
   */
  public static function search(string $subjectPattern, int $limit = 20): array
  {
    self::ensureTable();

    return db()->fetchAll(
      "SELECT * FROM institution_memory WHERE subject LIKE ? ORDER BY created_at DESC LIMIT ?",
      ['%' . $subjectPattern . '%', $limit]
    );
  }

  /**
   * Get outcome statistics for a specific policy or event type.
   */
  public static function getOutcomeStats(string $eventType): array
  {
    self::ensureTable();

    $rows = db()->fetchAll(
      "SELECT outcome, COUNT(*) as cnt, AVG(confidence) as avg_confidence, AVG(impact_score) as avg_impact
       FROM institution_memory WHERE event_type = ? GROUP BY outcome",
      [$eventType]
    );

    $stats = ['total' => 0, 'outcomes' => []];
    foreach ($rows as $r) {
      $stats['total'] += (int) $r['cnt'];
      $stats['outcomes'][$r['outcome']] = [
        'count'          => (int) $r['cnt'],
        'avg_confidence' => round((float) $r['avg_confidence'], 3),
        'avg_impact'     => round((float) $r['avg_impact'], 2),
      ];
    }
    return $stats;
  }

  /**
   * Update outcome of an existing memory.
   */
  public static function updateOutcome(int $id, string $outcome, float $impactScore = 0.0): void
  {
    db()->query(
      "UPDATE institution_memory SET outcome = ?, impact_score = ? WHERE id = ?",
      [$outcome, $impactScore, $id]
    );
  }

  /**
   * Get recent institutional memories for dashboard.
   */
  public static function getRecent(int $limit = 30): array
  {
    self::ensureTable();

    return db()->fetchAll(
      "SELECT * FROM institution_memory ORDER BY created_at DESC LIMIT ?",
      [$limit]
    );
  }

  /**
   * Calculate institutional learning score from outcome patterns.
   */
  public static function getLearningScore(): array
  {
    self::ensureTable();

    $total = (int) (db()->fetchOne(
      "SELECT COUNT(*) as cnt FROM institution_memory WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
    )['cnt'] ?? 0);

    $positive = (int) (db()->fetchOne(
      "SELECT COUNT(*) as cnt FROM institution_memory WHERE outcome IN ('improved','success','positive') AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
    )['cnt'] ?? 0);

    $negative = (int) (db()->fetchOne(
      "SELECT COUNT(*) as cnt FROM institution_memory WHERE outcome IN ('failed','degraded','negative') AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
    )['cnt'] ?? 0);

    $score = $total > 0 ? round(($positive / $total) * 100) : 100;
    $avgConfidence = (float) (db()->fetchOne(
      "SELECT AVG(confidence) as avg_c FROM institution_memory WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
    )['avg_c'] ?? 0.5);

    return [
      'score'           => $score,
      'total_memories'  => $total,
      'positive'        => $positive,
      'negative'        => $negative,
      'avg_confidence'  => round($avgConfidence, 3),
      'period'          => '30d',
    ];
  }

  /**
   * Prune old memories.
   */
  public static function prune(int $daysOld = 180): int
  {
    self::ensureTable();

    $result = db()->query(
      "DELETE FROM institution_memory WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY) AND reviewed = 0",
      [$daysOld]
    );
    return $result ? $result->rowCount() : 0;
  }

  /**
   * Summary for dashboard.
   */
  public static function getSummary(): array
  {
    $learning = self::getLearningScore();
    $recent = self::getRecent(10);
    $categories = db()->fetchAll(
      "SELECT category, COUNT(*) as cnt FROM institution_memory GROUP BY category ORDER BY cnt DESC LIMIT 10"
    ) ?: [];

    return [
      'learning_score' => $learning,
      'recent'         => $recent,
      'categories'     => $categories,
    ];
  }
}
