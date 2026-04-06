<?php

/**
 * DataConsistencyGuard — Phase-5 Autonomous Consistency Verification.
 *
 * Verifies that UI-submitted data matches database state after every write.
 * Detects silent save failures, missing columns, and stale reads.
 *
 * Usage:
 *   DataConsistencyGuard::verifySave('classes', $id, $submittedData);
 *   DataConsistencyGuard::verifyColumn('classes', 'schedule');
 *   DataConsistencyGuard::report('classes');
 */
class DataConsistencyGuard
{
  /** @var array Accumulated mismatches for the current request */
  private static array $issues = [];

  /**
   * After saving, re-read the row and compare against what was submitted.
   * Logs any discrepancies and returns the verification result.
   *
   * @return array{ok: bool, mismatches: array, missing_columns: array}
   */
  public static function verifySave(string $table, int $id, array $submitted): array
  {
    $result = ['ok' => true, 'mismatches' => [], 'missing_columns' => []];

    try {
      $safe = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
      $row = db()->fetchOne("SELECT * FROM `{$safe}` WHERE id = ?", [$id]);

      if (!$row) {
        $result['ok'] = false;
        $result['mismatches'][] = 'Record not found after save';
        self::logIssue($table, $id, 'record_missing', 'Row not found after INSERT/UPDATE');
        return $result;
      }

      foreach ($submitted as $col => $expectedValue) {
        // Column doesn't exist in DB result
        if (!array_key_exists($col, $row)) {
          $result['missing_columns'][] = $col;
          $result['ok'] = false;
          self::logIssue($table, $id, 'missing_column', "Column '$col' not in table — data was silently dropped");
          continue;
        }

        // Value mismatch
        $actual = $row[$col];
        if ($actual !== null && $expectedValue !== null && (string)$actual !== (string)$expectedValue) {
          $result['mismatches'][] = [
            'column'   => $col,
            'expected' => $expectedValue,
            'actual'   => $actual,
          ];
          $result['ok'] = false;
          self::logIssue($table, $id, 'value_mismatch', "Column '$col': expected " . json_encode($expectedValue) . ", got " . json_encode($actual));
        }
      }
    } catch (\Throwable $e) {
      $result['ok'] = false;
      $result['mismatches'][] = 'Verification query failed: ' . $e->getMessage();
    }

    return $result;
  }

  /**
   * Check if a column actually exists in a table.
   * Prevents the silent-drop problem.
   */
  public static function verifyColumn(string $table, string $column): bool
  {
    if (function_exists('table_has_column')) {
      return table_has_column($table, $column);
    }

    try {
      $safe_table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
      $safe_col   = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
      $row = db()->fetchOne("SHOW COLUMNS FROM `{$safe_table}` LIKE '{$safe_col}'");
      return (bool)$row;
    } catch (\Throwable $e) {
      return false;
    }
  }

  /**
   * Verify a form's fields all have corresponding DB columns BEFORE saving.
   * Returns array of field names that would be silently dropped.
   *
   * @return string[] Fields with no matching column
   */
  public static function detectOrphanFields(string $table, array $fieldNames): array
  {
    $orphans = [];
    foreach ($fieldNames as $field) {
      if (!self::verifyColumn($table, $field)) {
        $orphans[] = $field;
      }
    }
    if (!empty($orphans)) {
      self::logIssue($table, 0, 'orphan_fields', 'Form fields without DB columns: ' . implode(', ', $orphans));
    }
    return $orphans;
  }

  /**
   * Get all issues logged this request.
   */
  public static function getIssues(): array
  {
    return self::$issues;
  }

  /**
   * Log a consistency issue.
   */
  private static function logIssue(string $table, int $id, string $type, string $message): void
  {
    $issue = [
      'table'     => $table,
      'record_id' => $id,
      'type'      => $type,
      'message'   => $message,
      'url'       => $_SERVER['REQUEST_URI'] ?? 'cli',
      'user_id'   => $_SESSION['user_id'] ?? 0,
      'timestamp' => date('Y-m-d H:i:s'),
    ];

    self::$issues[] = $issue;

    // Log to error log
    error_log("DataConsistencyGuard [{$type}]: {$table}#{$id} — {$message}");

    // Persist to DB if table exists
    try {
      if (function_exists('table_exists') && table_exists('consistency_issues')) {
        db()->insert('consistency_issues', [
          'table_name' => $table,
          'record_id'  => $id,
          'issue_type' => $type,
          'message'    => $message,
          'request_url' => $_SERVER['REQUEST_URI'] ?? 'cli',
          'user_id'    => $_SESSION['user_id'] ?? 0,
          'created_at' => date('Y-m-d H:i:s'),
        ]);
      }
    } catch (\Throwable $e) {
      // DB logging is non-critical
    }
  }
}
