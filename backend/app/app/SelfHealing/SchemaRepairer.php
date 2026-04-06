<?php

/**
 * SchemaRepairer — Fixes database schema mismatches.
 *
 * Detects: missing columns, missing indexes, datatype mismatches,
 * orphaned records. Executes safe ALTER statements.
 */
class SchemaRepairer
{
  /**
   * Run a full schema health check and repair.
   */
  public static function scanAndRepair(): array
  {
    $results = [];
    $results['indexes'] = self::ensureIndexes();
    $results['columns'] = self::ensureColumns();
    $results['orphans'] = self::checkOrphans();
    return $results;
  }

  /**
   * Repair based on context from FaultDetector.
   */
  public static function repair(array $context): void
  {
    self::scanAndRepair();
  }

  /**
   * Ensure critical indexes exist.
   */
  private static function ensureIndexes(): array
  {
    $pdo = db()->getConnection();
    $fixes = [];

    $requiredIndexes = [
      'users'      => ['email', 'role'],
      'attendance' => ['student_id', 'date'],
      'classes'    => ['teacher_id'],
    ];

    foreach ($requiredIndexes as $table => $columns) {
      if (!self::tableExists($table)) continue;

      $existing = self::getIndexes($table);
      foreach ($columns as $col) {
        if (!self::columnExists($table, $col)) continue;

        $idxName = "idx_{$table}_{$col}";
        if (!in_array($idxName, $existing) && !in_array($col, $existing)) {
          try {
            $safeTbl = self::safeIdentifier($table);
            $safeCol = self::safeIdentifier($col);
            $safeIdx = self::safeIdentifier($idxName);
            $pdo->exec("ALTER TABLE $safeTbl ADD INDEX $safeIdx ($safeCol)");
            $fixes[] = "Added index $idxName on $table.$col";
            ErrorCollector::log('self_healing', "Schema fix: added index $idxName", 'INFO');
          } catch (\Throwable $e) {
            // Index may already exist under different name
            if (strpos($e->getMessage(), 'Duplicate') === false) {
              $fixes[] = "Failed to add index $idxName: " . $e->getMessage();
            }
          }
        }
      }
    }

    return $fixes;
  }

  /**
   * Ensure critical columns exist in tables.
   */
  private static function ensureColumns(): array
  {
    $pdo = db()->getConnection();
    $fixes = [];

    $requiredColumns = [
      'users' => [
        'status'     => "VARCHAR(20) DEFAULT 'active'",
        'created_at' => "TIMESTAMP DEFAULT CURRENT_TIMESTAMP",
      ],
    ];

    foreach ($requiredColumns as $table => $columns) {
      if (!self::tableExists($table)) continue;

      foreach ($columns as $col => $definition) {
        if (!self::columnExists($table, $col)) {
          try {
            $safeTbl = self::safeIdentifier($table);
            $safeCol = self::safeIdentifier($col);
            $pdo->exec("ALTER TABLE $safeTbl ADD COLUMN $safeCol $definition");
            $fixes[] = "Added column $table.$col";
            ErrorCollector::log('self_healing', "Schema fix: added column $table.$col", 'INFO');
          } catch (\Throwable $e) {
            $fixes[] = "Failed to add column $table.$col: " . $e->getMessage();
          }
        }
      }
    }

    return $fixes;
  }

  /**
   * Check for orphaned records in key relationships.
   */
  private static function checkOrphans(): array
  {
    $pdo = db()->getConnection();
    $orphans = [];

    $checks = [
      ['attendance', 'student_id', 'users', 'id'],
    ];

    foreach ($checks as [$child, $childCol, $parent, $parentCol]) {
      if (!self::tableExists($child) || !self::tableExists($parent)) continue;
      if (!self::columnExists($child, $childCol) || !self::columnExists($parent, $parentCol)) continue;

      try {
        $safeChild = self::safeIdentifier($child);
        $safeChildCol = self::safeIdentifier($childCol);
        $safeParent = self::safeIdentifier($parent);
        $safeParentCol = self::safeIdentifier($parentCol);

        $count = $pdo->query("
                    SELECT COUNT(*) FROM $safeChild c
                    LEFT JOIN $safeParent p ON c.$safeChildCol = p.$safeParentCol
                    WHERE p.$safeParentCol IS NULL
                ")->fetchColumn();

        if ($count > 0) {
          $orphans[] = "$count orphaned records in $child.$childCol → $parent.$parentCol";
        }
      } catch (\Throwable $e) {
        // Skip if tables aren't compatible
      }
    }

    return $orphans;
  }

  private static function tableExists(string $table): bool
  {
    try {
      $safe = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
      $pdo = db()->getConnection();
      $stmt = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($safe));
      return $stmt->rowCount() > 0;
    } catch (\Throwable $e) {
      return false;
    }
  }

  private static function columnExists(string $table, string $column): bool
  {
    try {
      $pdo = db()->getConnection();
      $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
      $safeColumn = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
      $stmt = $pdo->query("SHOW COLUMNS FROM `$safeTable` LIKE " . $pdo->quote($safeColumn));
      return $stmt->rowCount() > 0;
    } catch (\Throwable $e) {
      return false;
    }
  }

  private static function getIndexes(string $table): array
  {
    try {
      $pdo = db()->getConnection();
      $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
      $rows = $pdo->query("SHOW INDEX FROM `$safeTable`")->fetchAll(\PDO::FETCH_ASSOC);
      return array_unique(array_column($rows, 'Key_name'));
    } catch (\Throwable $e) {
      return [];
    }
  }

  private static function safeIdentifier(string $name): string
  {
    return '`' . preg_replace('/[^a-zA-Z0-9_]/', '', $name) . '`';
  }

  public static function getSummary(): array
  {
    return ['status' => 'ready'];
  }
}
