<?php

/**
 * ACI — Learning Memory
 * Persistent storage for repair patterns, success rates, and operational knowledge.
 * Table: aci_learning
 */
class LearningMemory
{
  private static bool $tableReady = false;

  public static function ensureTable(): void
  {
    if (self::$tableReady) return;
    try {
      db()->getConnection()->exec("CREATE TABLE IF NOT EXISTS aci_learning (
                id INT AUTO_INCREMENT PRIMARY KEY,
                problem_type VARCHAR(100) NOT NULL,
                problem_detail TEXT NOT NULL,
                solution_action VARCHAR(100) NOT NULL,
                solution_detail TEXT DEFAULT NULL,
                success_count INT DEFAULT 1,
                fail_count INT DEFAULT 0,
                confidence DECIMAL(5,4) DEFAULT 1.0000,
                last_seen DATETIME DEFAULT CURRENT_TIMESTAMP,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_problem (problem_type),
                INDEX idx_confidence (confidence)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (\Throwable $e) {
    }
    self::$tableReady = true;
  }

  /**
   * Record a successful repair.
   */
  public static function recordSuccess(string $problemType, string $detail, string $action, string $actionDetail = ''): void
  {
    self::ensureTable();
    try {
      $existing = db()->fetch(
        "SELECT id, success_count, fail_count FROM aci_learning WHERE problem_type = ? AND solution_action = ? LIMIT 1",
        [$problemType, $action]
      );
      if ($existing) {
        $s = (int) $existing['success_count'] + 1;
        $f = (int) $existing['fail_count'];
        $confidence = $s / max(1, $s + $f);
        db()->update('aci_learning', [
          'success_count' => $s,
          'confidence'    => round($confidence, 4),
          'last_seen'     => date('Y-m-d H:i:s'),
          'problem_detail' => $detail,
        ], 'id = ?', [$existing['id']]);
      } else {
        db()->insert('aci_learning', [
          'problem_type'    => $problemType,
          'problem_detail'  => $detail,
          'solution_action' => $action,
          'solution_detail' => $actionDetail,
          'success_count'   => 1,
          'fail_count'      => 0,
          'confidence'      => 1.0,
          'last_seen'       => date('Y-m-d H:i:s'),
          'created_at'      => date('Y-m-d H:i:s'),
        ]);
      }
    } catch (\Throwable $e) {
      ErrorCollector::log('aci_learning', 'Record failed: ' . $e->getMessage(), 'LOW');
    }
  }

  /**
   * Record a failed repair.
   */
  public static function recordFailure(string $problemType, string $action): void
  {
    self::ensureTable();
    try {
      $existing = db()->fetch(
        "SELECT id, success_count, fail_count FROM aci_learning WHERE problem_type = ? AND solution_action = ? LIMIT 1",
        [$problemType, $action]
      );
      if ($existing) {
        $s = (int) $existing['success_count'];
        $f = (int) $existing['fail_count'] + 1;
        $confidence = $s / max(1, $s + $f);
        db()->update('aci_learning', [
          'fail_count' => $f,
          'confidence' => round($confidence, 4),
          'last_seen'  => date('Y-m-d H:i:s'),
        ], 'id = ?', [$existing['id']]);
      }
    } catch (\Throwable $e) {
    }
  }

  /**
   * Get best known fix for a problem type.
   */
  public static function getBestAction(string $problemType): ?array
  {
    self::ensureTable();
    try {
      return db()->fetch(
        "SELECT * FROM aci_learning WHERE problem_type = ? AND confidence >= 0.5 ORDER BY confidence DESC, success_count DESC LIMIT 1",
        [$problemType]
      ) ?: null;
    } catch (\Throwable $e) {
      return null;
    }
  }

  /**
   * Get all learned patterns.
   */
  public static function getAll(): array
  {
    self::ensureTable();
    try {
      return db()->fetchAll("SELECT * FROM aci_learning ORDER BY confidence DESC, last_seen DESC LIMIT 50") ?: [];
    } catch (\Throwable $e) {
      return [];
    }
  }

  /**
   * Get learning stats.
   */
  public static function getStats(): array
  {
    self::ensureTable();
    try {
      return [
        'total_patterns'   => (int) db()->fetchOne("SELECT COUNT(*) FROM aci_learning"),
        'high_confidence'  => (int) db()->fetchOne("SELECT COUNT(*) FROM aci_learning WHERE confidence >= 0.9"),
        'avg_confidence'   => (float) db()->fetchOne("SELECT AVG(confidence) FROM aci_learning") ?: 0,
        'total_successes'  => (int) db()->fetchOne("SELECT COALESCE(SUM(success_count),0) FROM aci_learning"),
        'total_failures'   => (int) db()->fetchOne("SELECT COALESCE(SUM(fail_count),0) FROM aci_learning"),
      ];
    } catch (\Throwable $e) {
      return ['total_patterns' => 0, 'high_confidence' => 0, 'avg_confidence' => 0, 'total_successes' => 0, 'total_failures' => 0];
    }
  }
}
