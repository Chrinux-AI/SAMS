<?php

/**
 * MCC — Database Control Panel Controller
 * Table stats, slow queries, schema checks, optimization.
 */
class DatabaseController
{
  public static function getStatus(): array
  {
    $data = [
      'tables'       => [],
      'total_tables' => 0,
      'total_rows'   => 0,
      'total_size_mb' => 0,
      'db_name'      => DB_NAME ?? 'attendance_system',
      'db_health'    => 100,
    ];

    try {
      $pdo = db()->getConnection();

      // Table statistics
      $stmt = $pdo->query("SELECT TABLE_NAME, TABLE_ROWS, DATA_LENGTH, INDEX_LENGTH, AUTO_INCREMENT
                FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = DATABASE()
                ORDER BY DATA_LENGTH DESC");
      $tables = $stmt->fetchAll(\PDO::FETCH_ASSOC);

      $totalRows = 0;
      $totalSize = 0;
      foreach ($tables as $t) {
        $rows = (int) $t['TABLE_ROWS'];
        $size = ((int) $t['DATA_LENGTH'] + (int) $t['INDEX_LENGTH']);
        $totalRows += $rows;
        $totalSize += $size;
        $data['tables'][] = [
          'name'           => $t['TABLE_NAME'],
          'rows'           => $rows,
          'size_kb'        => round($size / 1024, 1),
          'auto_increment' => $t['AUTO_INCREMENT'],
        ];
      }
      $data['total_tables'] = count($tables);
      $data['total_rows'] = $totalRows;
      $data['total_size_mb'] = round($totalSize / 1024 / 1024, 2);

      // Missing indexes check (tables without indices beyond PRIMARY)
      $data['schema_warnings'] = [];
      try {
        foreach (array_slice($tables, 0, 30) as $t) {
          $name = $t['TABLE_NAME'];
          $idxStmt = $pdo->query("SHOW INDEX FROM `$name`");
          $indexes = $idxStmt->fetchAll(\PDO::FETCH_ASSOC);
          if (count($indexes) <= 1 && (int) $t['TABLE_ROWS'] > 1000) {
            $data['schema_warnings'][] = "Table `$name` ({$t['TABLE_ROWS']} rows) has no non-primary indexes";
          }
        }
      } catch (\Throwable $e) {
      }
    } catch (\Throwable $e) {
      $data['db_health'] = 0;
      $data['error'] = $e->getMessage();
    }

    return $data;
  }

  public static function optimizeTables(): array
  {
    $optimized = 0;
    try {
      $pdo = db()->getConnection();
      $stmt = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()");
      $tables = $stmt->fetchAll(\PDO::FETCH_COLUMN);
      foreach ($tables as $tbl) {
        $pdo->exec("OPTIMIZE TABLE `$tbl`");
        $optimized++;
      }
      AuditLogger::log('optimize_tables', 'database', "Optimized $optimized tables", $_SESSION['user_id'] ?? null);
    } catch (\Throwable $e) {
      return ['status' => 'error', 'message' => $e->getMessage()];
    }
    return ['status' => 'completed', 'optimized' => $optimized];
  }

  public static function runMigrations(): array
  {
    try {
      $schemaFile = BASE_PATH . '/database/schema.sql';
      if (!is_file($schemaFile)) {
        return ['status' => 'error', 'message' => 'schema.sql not found'];
      }
      $result = SchemaRepairer::repair();
      AuditLogger::log('run_migrations', 'database', 'Schema repair triggered from MCC', $_SESSION['user_id'] ?? null);
      return ['status' => 'completed', 'result' => $result];
    } catch (\Throwable $e) {
      return ['status' => 'error', 'message' => $e->getMessage()];
    }
  }
}
