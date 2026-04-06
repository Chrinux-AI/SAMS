<?php

/**
 * DriftController — Configuration Drift Detection & Correction
 *
 * Stores a baseline snapshot of system configuration.
 * Periodically compares current state to baseline.
 * If deviation detected: restore safe configuration, log change, notify admin.
 */
class DriftController
{
  private static function baselinePath(): string
  {
    $base = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);
    return $base . '/storage/config-baseline.json';
  }

  /**
   * Capture current configuration as the baseline snapshot.
   */
  public static function captureBaseline(): array
  {
    $baseline = self::gatherCurrentConfig();

    $dir = dirname(self::baselinePath());
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    file_put_contents(
      self::baselinePath(),
      json_encode($baseline, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
      LOCK_EX
    );

    ErrorCollector::log('drift', 'Configuration baseline captured', 'INFO');
    return $baseline;
  }

  /**
   * Check for configuration drift against baseline.
   *
   * @return array{drifted: bool, deviations: array}
   */
  public static function detect(): array
  {
    $baseline = self::loadBaseline();
    if (empty($baseline)) {
      // No baseline exists — capture one now
      self::captureBaseline();
      return ['drifted' => false, 'deviations' => []];
    }

    $current = self::gatherCurrentConfig();
    $deviations = [];

    foreach ($baseline as $key => $expected) {
      $actual = $current[$key] ?? null;
      if ($actual !== $expected) {
        $deviations[] = [
          'setting'  => $key,
          'expected' => $expected,
          'actual'   => $actual,
          'severity' => self::deviationSeverity($key),
        ];
      }
    }

    // Check for new config keys not in baseline
    foreach ($current as $key => $val) {
      if (!array_key_exists($key, $baseline)) {
        $deviations[] = [
          'setting'  => $key,
          'expected' => '(not in baseline)',
          'actual'   => $val,
          'severity' => 'low',
        ];
      }
    }

    if (!empty($deviations)) {
      ErrorCollector::log('drift', count($deviations) . ' configuration deviation(s) detected', 'WARNING');
    }

    return [
      'drifted'    => !empty($deviations),
      'deviations' => $deviations,
    ];
  }

  /**
   * Attempt to restore drifted settings to baseline values.
   * Only restores PHP runtime settings, not file constants.
   *
   * @return array of restored settings
   */
  public static function restore(): array
  {
    $result = self::detect();
    $restored = [];

    if (!$result['drifted']) return $restored;

    foreach ($result['deviations'] as $dev) {
      $key = $dev['setting'];

      // Only restore PHP ini settings (safe to change at runtime)
      if (str_starts_with($key, 'ini:')) {
        $iniKey = substr($key, 4);
        $expected = $dev['expected'];
        if ($expected !== null && $expected !== '(not in baseline)') {
          @ini_set($iniKey, (string)$expected);
          $restored[] = ['setting' => $key, 'restored_to' => $expected];
          ErrorCollector::log('drift', "Restored {$iniKey} to baseline value", 'INFO');
        }
      }

      // Log but don't modify defined constants or file-based config
      if (str_starts_with($key, 'const:') && $dev['severity'] !== 'low') {
        ErrorCollector::log('drift', "ALERT: Constant {$key} drifted from baseline (cannot auto-restore)", 'WARNING');
      }
    }

    // Record in learning table
    try {
      if (function_exists('table_exists') && table_exists('devops_learning')) {
        db()->query(
          "INSERT INTO devops_learning (category, pattern, action_taken, occurrences, last_seen)
                     VALUES ('drift', ?, ?, 1, NOW())
                     ON DUPLICATE KEY UPDATE occurrences = occurrences + 1, last_seen = NOW()",
          [count($result['deviations']) . ' deviations', count($restored) . ' restored']
        );
      }
    } catch (\Throwable $e) {
      // Non-critical
    }

    return $restored;
  }

  /**
   * Get drift summary for dashboard.
   */
  public static function getSummary(): array
  {
    $result = self::detect();
    return [
      'drifted'       => $result['drifted'],
      'deviation_count' => count($result['deviations']),
      'critical'      => count(array_filter($result['deviations'], fn($d) => $d['severity'] === 'critical')),
      'baseline_exists' => is_file(self::baselinePath()),
      'baseline_age'  => is_file(self::baselinePath()) ? time() - filemtime(self::baselinePath()) : null,
    ];
  }

  // ── Internal Methods ──────────────────────────────────────────

  /**
   * Gather current system configuration state.
   */
  private static function gatherCurrentConfig(): array
  {
    $config = [];

    // Application constants
    $constants = [
      'APP_NAME',
      'APP_VERSION',
      'SESSION_TIMEOUT',
      'PASSWORD_MIN_LENGTH',
      'MAX_LOGIN_ATTEMPTS',
      'LOCKOUT_DURATION',
      'MAX_FILE_SIZE',
      'DB_CHARSET',
      'TIMEZONE',
    ];

    foreach ($constants as $c) {
      $config["const:{$c}"] = defined($c) ? constant($c) : null;
    }

    // PHP ini settings that matter for security/performance
    $iniKeys = [
      'session.gc_maxlifetime',
      'session.cookie_httponly',
      'session.cookie_secure',
      'session.use_strict_mode',
      'upload_max_filesize',
      'post_max_size',
      'max_execution_time',
      'memory_limit',
      'display_errors',
      'error_reporting',
      'zlib.output_compression',
    ];

    foreach ($iniKeys as $k) {
      $config["ini:{$k}"] = ini_get($k);
    }

    // Critical file checksums (detect tampering)
    $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);
    $checksumFiles = ['includes/config.php', 'includes/database.php', 'app/bootstrap.php'];
    foreach ($checksumFiles as $f) {
      $full = $basePath . '/' . $f;
      $config["checksum:{$f}"] = is_file($full) ? md5_file($full) : null;
    }

    return $config;
  }

  /**
   * Load baseline from file.
   */
  private static function loadBaseline(): array
  {
    $path = self::baselinePath();
    if (!is_file($path)) return [];
    $data = json_decode(file_get_contents($path), true);
    return is_array($data) ? $data : [];
  }

  /**
   * Determine severity of a deviation by setting key.
   */
  private static function deviationSeverity(string $key): string
  {
    $critical = [
      'const:SESSION_TIMEOUT',
      'const:MAX_LOGIN_ATTEMPTS',
      'const:PASSWORD_MIN_LENGTH',
      'ini:display_errors',
      'checksum:includes/config.php',
      'checksum:includes/database.php'
    ];
    $high = ['ini:session.cookie_httponly', 'ini:session.cookie_secure', 'checksum:app/bootstrap.php'];

    if (in_array($key, $critical)) return 'critical';
    if (in_array($key, $high)) return 'high';
    return 'medium';
  }
}
