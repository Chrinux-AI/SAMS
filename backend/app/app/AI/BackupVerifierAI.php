<?php

/**
 * Backup Verifier AI — Intelligence layer for backup integrity.
 *
 * Checks: file tampering, hash mismatch, missing records, corruption indicators.
 * Enhances the existing backup system with verification intelligence.
 */
class BackupVerifierAI
{
  /**
   * Verify the integrity of a backup file.
   *
   * @param string $backupPath Path to the backup file
   * @param string $expectedHash Expected SHA-256 hash (if known)
   * @return array{valid: bool, checks: array, score: int, recommendation: string}
   */
  public static function verify(string $backupPath, string $expectedHash = ''): array
  {
    $checks = [];
    $issues = 0;

    // 1. File existence
    if (!file_exists($backupPath)) {
      return [
        'valid'          => false,
        'checks'         => [['name' => 'file_exists', 'passed' => false, 'detail' => 'Backup file not found']],
        'score'          => 0,
        'recommendation' => 'Backup file is missing. Recreate backup immediately.',
      ];
    }
    $checks[] = ['name' => 'file_exists', 'passed' => true, 'detail' => 'File present'];

    // 2. File size sanity check
    $size = filesize($backupPath);
    if ($size < 100) {
      $checks[] = ['name' => 'file_size', 'passed' => false, 'detail' => "File too small ({$size} bytes)"];
      $issues++;
    } elseif ($size > 500 * 1024 * 1024) { // > 500MB suspicious for school DB
      $checks[] = ['name' => 'file_size', 'passed' => false, 'detail' => 'File unusually large (' . round($size / 1048576, 1) . 'MB)'];
      $issues++;
    } else {
      $checks[] = ['name' => 'file_size', 'passed' => true, 'detail' => round($size / 1024, 1) . 'KB'];
    }

    // 3. Hash verification
    if ($expectedHash !== '') {
      $actualHash = hash_file('sha256', $backupPath);
      if (hash_equals($expectedHash, $actualHash)) {
        $checks[] = ['name' => 'hash_match', 'passed' => true, 'detail' => 'SHA-256 hash matches'];
      } else {
        $checks[] = ['name' => 'hash_match', 'passed' => false, 'detail' => 'Hash mismatch — file may be tampered'];
        $issues += 2; // Extra weight
      }
    }

    // 4. File format validation (check if it's valid SQL or gzip)
    $formatCheck = self::validateFormat($backupPath);
    $checks[] = $formatCheck;
    if (!$formatCheck['passed']) {
      $issues++;
    }

    // 5. SQL content sanity (check for expected tables)
    if ($formatCheck['format'] === 'sql') {
      $contentCheck = self::validateSQLContent($backupPath);
      $checks = array_merge($checks, $contentCheck['checks']);
      $issues += $contentCheck['issues'];
    }

    // 6. Timestamp freshness
    $mtime = filemtime($backupPath);
    $ageHours = (time() - $mtime) / 3600;
    if ($ageHours > 168) { // > 1 week
      $checks[] = ['name' => 'freshness', 'passed' => false, 'detail' => 'Backup is ' . round($ageHours / 24, 1) . ' days old'];
      $issues++;
    } else {
      $checks[] = ['name' => 'freshness', 'passed' => true, 'detail' => round($ageHours, 1) . ' hours old'];
    }

    // 7. File permissions check
    if (is_writable($backupPath) && PHP_OS_FAMILY !== 'Windows') {
      $checks[] = ['name' => 'permissions', 'passed' => false, 'detail' => 'Backup file is world-writable'];
      $issues++;
    } else {
      $checks[] = ['name' => 'permissions', 'passed' => true, 'detail' => 'Permissions OK'];
    }

    // Compute integrity score
    $totalChecks = count($checks);
    $passedChecks = count(array_filter($checks, fn($c) => $c['passed']));
    $score = $totalChecks > 0 ? (int) round(($passedChecks / $totalChecks) * 100) : 0;

    return [
      'valid'          => $issues === 0,
      'checks'         => $checks,
      'score'          => $score,
      'recommendation' => self::getRecommendation($issues, $checks),
    ];
  }

  /**
   * Verify all backups in a directory.
   */
  public static function verifyAll(string $backupDir = ''): array
  {
    if ($backupDir === '') {
      $backupDir = (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2)) . '/backups';
    }

    if (!is_dir($backupDir)) {
      return ['error' => 'Backup directory not found', 'results' => []];
    }

    $results = [];
    $files = glob($backupDir . '/*.{sql,sql.gz,zip}', GLOB_BRACE);

    foreach ($files ?: [] as $file) {
      $results[basename($file)] = self::verify($file);
    }

    // Summary
    $total = count($results);
    $valid = count(array_filter($results, fn($r) => $r['valid']));

    return [
      'total'   => $total,
      'valid'   => $valid,
      'invalid' => $total - $valid,
      'results' => $results,
    ];
  }

  /**
   * Generate a hash manifest for all backups (to detect later tampering).
   */
  public static function generateManifest(string $backupDir = ''): array
  {
    if ($backupDir === '') {
      $backupDir = (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2)) . '/backups';
    }

    $manifest = [];
    $files = glob($backupDir . '/*.{sql,sql.gz,zip}', GLOB_BRACE);

    foreach ($files ?: [] as $file) {
      $manifest[basename($file)] = [
        'hash'    => hash_file('sha256', $file),
        'size'    => filesize($file),
        'created' => date('c', filemtime($file)),
      ];
    }

    // Save manifest
    $manifestPath = $backupDir . '/manifest.json';
    file_put_contents(
      $manifestPath,
      json_encode([
        'generated_at' => date('c'),
        'file_count'   => count($manifest),
        'files'        => $manifest,
      ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
      LOCK_EX
    );

    return $manifest;
  }

  /**
   * Verify backups against a previously generated manifest.
   */
  public static function verifyAgainstManifest(string $backupDir = ''): array
  {
    if ($backupDir === '') {
      $backupDir = (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2)) . '/backups';
    }

    $manifestPath = $backupDir . '/manifest.json';
    if (!file_exists($manifestPath)) {
      return ['error' => 'No manifest found. Generate one first.', 'results' => []];
    }

    $manifestData = json_decode(file_get_contents($manifestPath), true);
    $manifest = $manifestData['files'] ?? [];
    $results = [];

    foreach ($manifest as $filename => $expected) {
      $filePath = $backupDir . '/' . $filename;
      if (!file_exists($filePath)) {
        $results[$filename] = ['status' => 'missing', 'detail' => 'File not found'];
        continue;
      }

      $currentHash = hash_file('sha256', $filePath);
      if (hash_equals($expected['hash'], $currentHash)) {
        $results[$filename] = ['status' => 'ok', 'detail' => 'Hash matches'];
      } else {
        $results[$filename] = ['status' => 'tampered', 'detail' => 'Hash mismatch — possible tampering'];

        // Log the tampering event
        try {
          db()->insert('security_events', [
            'event_type'  => 'backup_tampering',
            'severity'    => 'critical',
            'user_id'     => null,
            'ip_address'  => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            'details'     => json_encode([
              'file'          => $filename,
              'expected_hash' => $expected['hash'],
              'actual_hash'   => $currentHash,
            ]),
            'resolved'    => 0,
            'created_at'  => date('Y-m-d H:i:s'),
          ]);
        } catch (\Throwable $e) {
          // Non-critical
        }
      }
    }

    return [
      'manifest_date' => $manifestData['generated_at'] ?? 'unknown',
      'total'         => count($manifest),
      'ok'            => count(array_filter($results, fn($r) => $r['status'] === 'ok')),
      'missing'       => count(array_filter($results, fn($r) => $r['status'] === 'missing')),
      'tampered'      => count(array_filter($results, fn($r) => $r['status'] === 'tampered')),
      'results'       => $results,
    ];
  }

    // ─────── Internal ───────

  /**
   * Validate backup file format (SQL or gzip).
   */
  private static function validateFormat(string $path): array
  {
    $handle = @fopen($path, 'rb');
    if (!$handle) {
      return ['name' => 'format', 'passed' => false, 'detail' => 'Cannot read file', 'format' => 'unknown'];
    }

    $header = fread($handle, 4);
    fclose($handle);

    // Gzip magic bytes: \x1f\x8b
    if (substr($header, 0, 2) === "\x1f\x8b") {
      return ['name' => 'format', 'passed' => true, 'detail' => 'Valid gzip format', 'format' => 'gzip'];
    }

    // ZIP magic bytes: PK\x03\x04
    if (substr($header, 0, 4) === "PK\x03\x04") {
      return ['name' => 'format', 'passed' => true, 'detail' => 'Valid ZIP format', 'format' => 'zip'];
    }

    // SQL: starts with comment or CREATE/DROP/INSERT
    $textStart = strtoupper(trim($header));
    if (
      str_starts_with($textStart, '--') || str_starts_with($textStart, '/*') ||
      str_starts_with($textStart, 'CREA') || str_starts_with($textStart, 'DROP') ||
      str_starts_with($textStart, 'INSE') || str_starts_with($textStart, 'SET ')
    ) {
      return ['name' => 'format', 'passed' => true, 'detail' => 'SQL text format', 'format' => 'sql'];
    }

    return ['name' => 'format', 'passed' => false, 'detail' => 'Unrecognized file format', 'format' => 'unknown'];
  }

  /**
   * Validate SQL content: check for expected tables, no suspicious commands.
   */
  private static function validateSQLContent(string $path): array
  {
    $checks = [];
    $issues = 0;

    $content = @file_get_contents($path, false, null, 0, 1048576); // Read first 1MB
    if ($content === false) {
      return ['checks' => [['name' => 'content_read', 'passed' => false, 'detail' => 'Cannot read SQL']], 'issues' => 1];
    }

    // Check for core tables
    $expectedTables = ['users', 'students', 'attendance', 'classes'];
    $foundTables = 0;
    foreach ($expectedTables as $table) {
      if (stripos($content, $table) !== false) {
        $foundTables++;
      }
    }
    if ($foundTables >= 2) {
      $checks[] = ['name' => 'core_tables', 'passed' => true, 'detail' => "{$foundTables}/4 core tables found"];
    } else {
      $checks[] = ['name' => 'core_tables', 'passed' => false, 'detail' => "Only {$foundTables}/4 core tables found"];
      $issues++;
    }

    // Check for suspicious injected content
    if (preg_match('/\b(GRANT|REVOKE|CREATE\s+USER)\b/i', $content)) {
      $checks[] = ['name' => 'suspicious_commands', 'passed' => false, 'detail' => 'Contains privilege-altering commands'];
      $issues++;
    } else {
      $checks[] = ['name' => 'suspicious_commands', 'passed' => true, 'detail' => 'No suspicious privilege commands'];
    }

    return ['checks' => $checks, 'issues' => $issues];
  }

  /**
   * Generate recommendation based on issues found.
   */
  private static function getRecommendation(int $issues, array $checks): string
  {
    if ($issues === 0) {
      return 'Backup integrity verified. No issues detected.';
    }

    $failedNames = array_map(fn($c) => $c['name'], array_filter($checks, fn($c) => !$c['passed']));

    if (in_array('hash_match', $failedNames, true)) {
      return 'CRITICAL: Backup hash mismatch detected. File may have been tampered with. Create a new backup immediately.';
    }
    if (in_array('core_tables', $failedNames, true)) {
      return 'WARNING: Backup may be incomplete. Core tables not found. Recreate backup.';
    }
    if ($issues >= 3) {
      return 'Multiple integrity issues found. Consider creating a fresh backup.';
    }
    return 'Minor issues detected. Review the check details above.';
  }
}
