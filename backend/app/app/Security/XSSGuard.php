<?php

/**
 * XSS Guard — Detects and neutralizes cross-site scripting payloads.
 */
class XSSGuard
{
  /** Patterns that indicate XSS attempts */
  private static array $patterns = [
    '/<script\b[^>]*>.*?<\/script>/is',
    '/\bon\w+\s*=\s*["\']?[^"\']*["\']?/i',
    '/javascript\s*:/i',
    '/vbscript\s*:/i',
    '/data\s*:\s*text\/html/i',
    '/<iframe\b/i',
    '/<object\b/i',
    '/<embed\b/i',
    '/<form\b[^>]*action\s*=/i',
    '/expression\s*\(/i',
    '/url\s*\(\s*["\']?\s*javascript/i',
    '/<svg\b[^>]*\bon\w+/i',
    '/<math\b[^>]*\bon\w+/i',
    '/\beval\s*\(/i',
    '/\bdocument\s*\.\s*(cookie|write|location)/i',
    '/\bwindow\s*\.\s*location/i',
    '/\balert\s*\(/i',
    '/&#x?[0-9a-f]+;?/i',  // Encoded chars that may hide attacks
  ];

  /**
   * Check if input contains XSS patterns. Returns true if XSS detected.
   */
  public static function detect(string $input): bool
  {
    $decoded = html_entity_decode($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $decoded = urldecode($decoded);

    foreach (self::$patterns as $pattern) {
      if (preg_match($pattern, $decoded)) {
        return true;
      }
    }
    return false;
  }

  /**
   * Scan an array of inputs for XSS. Returns list of tainted keys.
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
   * Neutralize XSS in a string by encoding dangerous characters.
   */
  public static function neutralize(string $input): string
  {
    return htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
  }

  /**
   * Guard a request — scan GET + POST and log if threats found.
   * Returns true if request is clean, false if XSS was detected.
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
    error_log("XSS attempt detected in fields: {$details} from IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));

    try {
      db()->insert('audit_logs', [
        'user_id'    => $_SESSION['user_id'] ?? null,
        'action'     => 'xss_attempt',
        'details'    => "Tainted fields: {$details}",
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
        'created_at' => date('Y-m-d H:i:s'),
      ]);
    } catch (\Throwable $e) {
      // Fail silently — logging shouldn't break the request
    }
  }
}
