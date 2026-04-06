<?php

/**
 * DatabaseOptimizer — Autonomous Database Health & Optimization
 *
 * Tasks:
 * - Detect missing indexes on frequently filtered columns
 * - Identify fragmented tables and optimize them
 * - Archive old log entries
 * - Suggest index improvements (non-destructive)
 * - Monitor table sizes and row counts
 */
class DatabaseOptimizer
{
  /**
   * Run all database optimizations.
   *
   * @return array{actions: array, suggestions: array}
   */
  public static function optimize(): array
  {
    $actions = [];
    $suggestions = [];

    $suggestions = array_merge($suggestions, self::detectMissingIndexes());
    $actions = array_merge($actions, self::archiveOldLogs());
    $actions = array_merge($actions, self::optimizeFragmentedTables());

    return ['actions' => $actions, 'suggestions' => $suggestions];
  }

  /**
   * Detect frequently filtered columns that lack indexes.
   * Non-destructive — produces suggestions only.
   *
   * @return array
   */
  public static function detectMissingIndexes(): array
  {
    $suggestions = [];

    $checks = [
      ['table' => 'attendance', 'column' => 'class_id', 'reason' => 'Frequently filtered in reports'],
      ['table' => 'attendance', 'column' => 'date', 'reason' => 'Date-range queries common'],
      ['table' => 'attendance', 'column' => 'student_id', 'reason' => 'Per-student lookups'],
      ['table' => 'class_enrollments', 'column' => 'class_id', 'reason' => 'Join key for class queries'],
      ['table' => 'class_enrollments', 'column' => 'student_id', 'reason' => 'Join key for student queries'],
      ['table' => 'comm_messages', 'column' => 'conversation_id', 'reason' => 'Messages loaded by conversation'],
      ['table' => 'comm_messages', 'column' => 'sender_id', 'reason' => 'Message sender lookups'],
      ['table' => 'system_failures', 'column' => 'module', 'reason' => 'Module-level failure analysis'],
      ['table' => 'system_metrics', 'column' => 'metric', 'reason' => 'Metric history queries'],
    ];

    foreach ($checks as $check) {
      try {
        if (!function_exists('table_exists') || !table_exists($check['table'])) {
          continue;
        }

        if (!self::hasIndex($check['table'], $check['column'])) {
          $suggestions[] = [
            'type'   => 'missing_index',
            'table'  => $check['table'],
            'column' => $check['column'],
            'reason' => $check['reason'],
            'sql'    => "ALTER TABLE {$check['table']} ADD INDEX idx_{$check['table']}_{$check['column']} ({$check['column']})",
          ];
        }
      } catch (\Throwable $e) {
        continue;
      }
    }

    return $suggestions;
  }

  /**
   * Apply a specific index suggestion. Admin-triggered only.
   */
  public static function applyIndex(string $table, string $column): bool
  {
    try {
      if (!function_exists('table_exists') || !table_exists($table)) {
        return false;
      }
      if (self::hasIndex($table, $column)) {
        return true; // Already exists
      }

      $indexName = "idx_{$table}_{$column}";
      db()->query("ALTER TABLE `{$table}` ADD INDEX `{$indexName}` (`{$column}`)");
      ErrorCollector::log('db_optimizer', "Created index {$indexName} on {$table}.{$column}", 'INFO');
      return true;
    } catch (\Throwable $e) {
      ErrorCollector::log('db_optimizer', "Failed to create index on {$table}.{$column}: " . $e->getMessage(), 'ERROR');
      return false;
    }
  }

  /**
   * Archive old log data beyond retention period.
   */
  private static function archiveOldLogs(): array
  {
    $actions = [];

    // Archive old system_failures (keep 90 days)
    try {
      if (function_exists('table_exists') && table_exists('system_failures')) {
        $stmt = db()->query(
          "DELETE FROM system_failures WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)"
        );
        $count = $stmt->rowCount();
        if ($count > 0) {
          $actions[] = ['type' => 'archive', 'action' => "Archived {$count} old system_failures records", 'success' => true];
        }
      }
    } catch (\Throwable $e) {
      // Non-critical
    }

    // Archive old system_metrics (keep 30 days)
    try {
      if (function_exists('table_exists') && table_exists('system_metrics')) {
        $stmt = db()->query(
          "DELETE FROM system_metrics WHERE recorded_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );
        $count = $stmt->rowCount();
        if ($count > 0) {
          $actions[] = ['type' => 'archive', 'action' => "Archived {$count} old system_metrics records", 'success' => true];
        }
      }
    } catch (\Throwable $e) {
      // Non-critical
    }

    return $actions;
  }

  /**
   * Find and optimize fragmented tables.
   */
  private static function optimizeFragmentedTables(): array
  {
    $actions = [];

    try {
      $tables = db()->fetchAll(
        "SELECT TABLE_NAME, DATA_FREE, DATA_LENGTH
                 FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = ? AND DATA_FREE > 0 AND DATA_LENGTH > 0",
        [DB_NAME]
      );

      foreach ($tables as $t) {
        $fragPct = ($t['DATA_FREE'] / ($t['DATA_LENGTH'] + $t['DATA_FREE'])) * 100;
        if ($fragPct > 20) {
          $tableName = $t['TABLE_NAME'];
          db()->query("OPTIMIZE TABLE `{$tableName}`");
          $actions[] = [
            'type'    => 'optimize_table',
            'action'  => "Optimized fragmented table {$tableName} (" . round($fragPct) . "% fragmented)",
            'success' => true,
          ];
        }
      }
    } catch (\Throwable $e) {
      // Non-critical
    }

    return $actions;
  }

  /**
   * Check if a table has an index on a specific column.
   */
  private static function hasIndex(string $table, string $column): bool
  {
    try {
      $indexes = db()->fetchAll("SHOW INDEX FROM `{$table}` WHERE Column_name = ?", [$column]);
      return !empty($indexes);
    } catch (\Throwable $e) {
      return true; // Assume indexed to avoid false suggestions
    }
  }

  /**
   * Get table statistics for dashboard.
   */
  public static function getTableStats(): array
  {
    try {
      return db()->fetchAll(
        "SELECT TABLE_NAME as name,
                        TABLE_ROWS as rows,
                        ROUND((DATA_LENGTH + INDEX_LENGTH) / 1024, 1) as size_kb,
                        ROUND(DATA_FREE / 1024, 1) as free_kb
                 FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = ?
                 ORDER BY (DATA_LENGTH + INDEX_LENGTH) DESC",
        [DB_NAME]
      );
    } catch (\Throwable $e) {
      return [];
    }
  }
}
