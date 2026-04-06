<?php

/**
 * SQL Injection Guard — Detects SQL injection patterns in user input.
 * Note: This is a defense-in-depth layer. The primary defense is parameterized queries via PDO.
 */
class SQLInjectionGuard
{
  /** Patterns that indicate SQL injection attempts */
  private static array $patterns = [
    '/\b(UNION\s+SELECT|UNION\s+ALL\s+SELECT)\b/i',
    '/\bSELECT\b.*\bFROM\b.*\bWHERE\b/i',
    '/\bINSERT\s+INTO\b/i',
    '/\bUPDATE\b.*\bSET\b/i',
    '/\bDELETE\s+FROM\b/i',
    '/\bDROP\s+(TABLE|DATABASE|INDEX)\b/i',
    '/\bALTER\s+TABLE\b/i',
    '/\bCREATE\s+(TABLE|DATABASE|INDEX|USER)\b/i',
    '/\bEXEC(UTE)?\s*\(/i',
    '/\bxp_cmdshell\b/i',
    '/\bLOAD_FILE\s*\(/i',
    '/\bINTO\s+(OUT|DUMP)FILE\b/i',
    '/\bINFORMATION_SCHEMA\b/i',
    '/\bSLEEP\s*\(\s*\d+\s*\)/i',
    '/\bBENCHMARK\s*\(/i',
    '/\bWAITFOR\s+DELAY\b/i',
    '/(\'|\");\s*(DROP|DELETE|UPDATE|INSERT)/i',
    '/\bOR\b\s+\d+\s*=\s*\d+/i',  // OR 1=1
    '/\bOR\b\s+[\'"]?\w+[\'"]?\s*=\s*[\'"]?\w+[\'"]?/i',
    '/--\s*$/m',  // SQL comment at end of line
    '/\/\*.*?\*\//s',  // Block comments in unexpected places
  ];

  /**
   * Check a single string for SQL injection patterns.
   */
  public static function detect(string $input): bool
  {
    $decoded = urldecode($input);
    $decoded = str_replace(['/*', '*/'], '', $decoded); // Remove obfuscation

    foreach (self::$patterns as $pattern) {
      if (preg_match($pattern, $decoded)) {
        return true;
      }
    }
    return false;
  }

  /**
   * Scan request data for SQL injection.
   * Returns array of tainted field names.
   */
  public static function scan(array $data, string $prefix = ''): array
  {
    $tainted = [];
    foreach ($data as $key => $value) {
      $path = $prefix ? "{$prefix}.{$key}" : (string) $key;
      if (is_array($value)) {
        $tainted = array_merge($tainted, self::scan($value, $path));
      } elseif (is_string($value) && self::detect($value)) {
        $tainted[] = $path;
      }
    }
    return $tainted;
  }

  /**
   * Guard a request. Returns true if clean, false if injection detected.
   */
  public static function guardRequest(): bool
  {
    $tainted = array_merge(
      self::scan($_GET, 'GET'),
      self::scan($_POST, 'POST')
    );

    if (!empty($tainted)) {
      self::logThreat($tainted);
      return false;
    }
    return true;
  }

  private static function logThreat(array $tainted): void
  {
    $details = implode(', ', array_slice($tainted, 0, 10));
    error_log("SQL injection attempt detected in fields: {$details} from IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));

    try {
      db()->insert('audit_logs', [
        'user_id'    => $_SESSION['user_id'] ?? null,
        'action'     => 'sqli_attempt',
        'details'    => "Tainted fields: {$details}",
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
        'created_at' => date('Y-m-d H:i:s'),
      ]);
    } catch (\Throwable $e) {
      // Fail silently
    }
  }
}
