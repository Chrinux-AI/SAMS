<?php

/**
 * QueryValidator — Phase-5 Autonomous Query Diagnostics.
 *
 * Wraps SELECT queries to detect when UI expects columns the query doesn't return.
 * Logs warnings for missing fields that would show "Not set" / NULL in the UI.
 *
 * Usage:
 *   $rows = QueryValidator::fetchAll('classes', $sql, $params, ['schedule', 'room_number']);
 *   QueryValidator::validateRow('classes', $row, ['schedule', 'teacher_name', 'room_number']);
 */
class QueryValidator
{
  /** @var array Accumulated warnings */
  private static array $warnings = [];

  /**
   * Execute a fetchAll with column-presence validation.
   *
   * @param string   $context       Human label (e.g. 'classes')
   * @param string   $sql           The query
   * @param array    $params        Bind params
   * @param string[] $expectedCols  Columns the UI will try to read
   * @return array
   */
  public static function fetchAll(string $context, string $sql, array $params = [], array $expectedCols = []): array
  {
    $rows = db()->fetchAll($sql, $params);

    if (!empty($rows) && !empty($expectedCols)) {
      $first = $rows[0];
      foreach ($expectedCols as $col) {
        if (!array_key_exists($col, $first)) {
          self::warn($context, $col, $sql);
        }
      }
    }

    return $rows;
  }

  /**
   * Execute a fetchOne with column-presence validation.
   */
  public static function fetchOne(string $context, string $sql, array $params = [], array $expectedCols = []): ?array
  {
    $row = db()->fetchOne($sql, $params);

    if ($row && !empty($expectedCols)) {
      foreach ($expectedCols as $col) {
        if (!array_key_exists($col, $row)) {
          self::warn($context, $col, $sql);
        }
      }
    }

    return $row;
  }

  /**
   * Validate a row against expected columns (post-fetch).
   *
   * @param string   $context      UI context label
   * @param array    $row          The fetched row
   * @param string[] $expectedCols Columns the template reads
   * @return string[] Missing column names
   */
  public static function validateRow(string $context, array $row, array $expectedCols): array
  {
    $missing = [];
    foreach ($expectedCols as $col) {
      if (!array_key_exists($col, $row)) {
        $missing[] = $col;
        self::warn($context, $col, '(post-fetch validation)');
      }
    }
    return $missing;
  }

  /**
   * Record a warning and log it.
   */
  private static function warn(string $context, string $column, string $query): void
  {
    $warning = [
      'context' => $context,
      'column'  => $column,
      'query'   => mb_substr($query, 0, 300),
      'url'     => $_SERVER['REQUEST_URI'] ?? 'cli',
      'time'    => date('Y-m-d H:i:s'),
    ];
    self::$warnings[] = $warning;

    error_log("QueryValidator WARNING: UI requested '$column' in [$context] but query returned NULL column. URL=" . ($warning['url']));
  }

  /**
   * Get all warnings for the current request (useful for debug overlay).
   */
  public static function getWarnings(): array
  {
    return self::$warnings;
  }

  /**
   * Check if any warnings were raised.
   */
  public static function hasWarnings(): bool
  {
    return !empty(self::$warnings);
  }

  /**
   * Reset warnings (for testing).
   */
  public static function reset(): void
  {
    self::$warnings = [];
  }
}
