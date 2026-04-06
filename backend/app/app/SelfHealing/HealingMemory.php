<?php

/**
 * HealingMemory — Persistent learning store for self-healing decisions.
 *
 * Tracks fault→repair outcomes so the system learns best fixes over time.
 * Table: healing_memory (auto-created).
 */
class HealingMemory
{
  private static bool $tableReady = false;

  /**
   * Ensure the healing_memory table exists.
   */
  public static function ensureTable(): void
  {
    if (self::$tableReady) return;

    $pdo = db()->getConnection();
    $pdo->exec("CREATE TABLE IF NOT EXISTS healing_memory (
            id INT AUTO_INCREMENT PRIMARY KEY,
            fault_type VARCHAR(100) NOT NULL,
            fault_signature VARCHAR(255) DEFAULT '',
            repair_action VARCHAR(100) NOT NULL,
            success TINYINT(1) NOT NULL DEFAULT 0,
            duration_ms INT DEFAULT 0,
            context JSON DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_fault_type (fault_type),
            INDEX idx_success (success)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    self::$tableReady = true;
  }

  /**
   * Record a healing attempt and its outcome.
   */
  public static function record(string $faultType, string $repairAction, bool $success, int $durationMs = 0, array $context = []): void
  {
    self::ensureTable();
    $pdo = db()->getConnection();
    $stmt = $pdo->prepare("INSERT INTO healing_memory (fault_type, fault_signature, repair_action, success, duration_ms, context) VALUES (?, ?, ?, ?, ?, ?)");
    $signature = self::generateSignature($faultType, $context);
    $stmt->execute([$faultType, $signature, $repairAction, $success ? 1 : 0, $durationMs, json_encode($context)]);
  }

  /**
   * Get success rate for a given fault type.
   */
  public static function getSuccessRate(string $faultType): float
  {
    self::ensureTable();
    $pdo = db()->getConnection();
    $stmt = $pdo->prepare("SELECT COUNT(*) as total, SUM(success) as wins FROM healing_memory WHERE fault_type = ?");
    $stmt->execute([$faultType]);
    $row = $stmt->fetch(\PDO::FETCH_ASSOC);
    if (!$row || $row['total'] == 0) return 0.0;
    return round(($row['wins'] / $row['total']) * 100, 1);
  }

  /**
   * Get the best repair action for a fault type based on historical success.
   */
  public static function getBestRepair(string $faultType): ?array
  {
    self::ensureTable();
    $pdo = db()->getConnection();
    $stmt = $pdo->prepare("
            SELECT repair_action,
                   COUNT(*) as attempts,
                   SUM(success) as successes,
                   ROUND(SUM(success)/COUNT(*)*100, 1) as rate
            FROM healing_memory
            WHERE fault_type = ?
            GROUP BY repair_action
            ORDER BY rate DESC, successes DESC
            LIMIT 1
        ");
    $stmt->execute([$faultType]);
    return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
  }

  /**
   * Get recent healing events.
   */
  public static function getRecent(int $limit = 50): array
  {
    self::ensureTable();
    $pdo = db()->getConnection();
    $stmt = $pdo->prepare("SELECT * FROM healing_memory ORDER BY created_at DESC LIMIT ?");
    $stmt->bindValue(1, $limit, \PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(\PDO::FETCH_ASSOC);
  }

  /**
   * Get fault statistics.
   */
  public static function getStats(): array
  {
    self::ensureTable();
    $pdo = db()->getConnection();

    $total = $pdo->query("SELECT COUNT(*) FROM healing_memory")->fetchColumn();
    $successes = $pdo->query("SELECT COUNT(*) FROM healing_memory WHERE success = 1")->fetchColumn();
    $today = $pdo->query("SELECT COUNT(*) FROM healing_memory WHERE DATE(created_at) = CURDATE()")->fetchColumn();

    $topFaults = $pdo->query("
            SELECT fault_type, COUNT(*) as cnt, ROUND(SUM(success)/COUNT(*)*100,1) as rate
            FROM healing_memory
            GROUP BY fault_type
            ORDER BY cnt DESC
            LIMIT 10
        ")->fetchAll(\PDO::FETCH_ASSOC);

    return [
      'total_repairs'   => (int)$total,
      'successes'       => (int)$successes,
      'success_rate'    => $total > 0 ? round(($successes / $total) * 100, 1) : 0,
      'today'           => (int)$today,
      'top_faults'      => $topFaults,
    ];
  }

  /**
   * Generate a signature for deduplication.
   */
  private static function generateSignature(string $faultType, array $context): string
  {
    $key = $faultType . ':' . ($context['file'] ?? '') . ':' . ($context['module'] ?? '');
    return substr(md5($key), 0, 16);
  }

  /**
   * Prune old entries (keep last N days).
   */
  public static function prune(int $days = 90): int
  {
    self::ensureTable();
    $pdo = db()->getConnection();
    $stmt = $pdo->prepare("DELETE FROM healing_memory WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
    $stmt->execute([$days]);
    return $stmt->rowCount();
  }

  public static function getSummary(): array
  {
    return self::getStats();
  }
}
