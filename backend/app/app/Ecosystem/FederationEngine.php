<?php

/**
 * FederationEngine — Safe Intelligence Sharing
 *
 * Shares only abstracted intelligence, never raw data.
 * Pattern: "Morning reminders increase attendance by 11%"
 * Never:   "Student A attendance record"
 *
 * Allowed: attendance improvement trends, timetable efficiency, engagement models
 * Blocked: student names, grades, personal records
 */
class FederationEngine
{
  /**
   * Ensure federation tables exist.
   */
  public static function ensureTable(): void
  {
    $sql = "CREATE TABLE IF NOT EXISTS federation_patterns (
      id INT AUTO_INCREMENT PRIMARY KEY,
      tenant_id INT NOT NULL,
      pattern_hash VARCHAR(64) NOT NULL,
      category VARCHAR(100) NOT NULL,
      pattern_name VARCHAR(255) NOT NULL,
      pattern_data JSON NOT NULL,
      confidence DECIMAL(3,2) DEFAULT 0.50,
      source_count INT DEFAULT 1,
      improvement_percent DECIMAL(5,2) DEFAULT 0.00,
      status ENUM('pending','approved','rejected','distributed') DEFAULT 'pending',
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      distributed_at TIMESTAMP NULL,
      INDEX idx_category (category),
      INDEX idx_status (status),
      UNIQUE KEY uk_hash (pattern_hash)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    db()->query($sql);
  }

  /**
   * Submit a local insight for federation consideration.
   */
  public static function submitPattern(int $tenantId, string $category, string $name, array $data, float $confidence = 0.5): array
  {
    self::ensureTable();

    // Sanitize: strip any PII before federation
    $sanitized = self::sanitizePattern($data);
    if (!$sanitized['safe']) {
      ErrorCollector::log('ecosystem', "Federation blocked: PII detected in pattern '{$name}'", 'HIGH');
      return ['success' => false, 'reason' => 'Pattern contains personally identifiable information'];
    }

    // Trust boundary check
    $trustCheck = TrustBoundary::validateExport($data);
    if (!$trustCheck['safe']) {
      return ['success' => false, 'reason' => 'Trust boundary violation: ' . implode('; ', $trustCheck['violations'])];
    }

    $hash = hash('sha256', json_encode([$category, $name, $sanitized['data']]));

    // Check if similar pattern already exists
    $existing = db()->fetchOne("SELECT id, source_count FROM federation_patterns WHERE pattern_hash = ?", [$hash]);
    if ($existing) {
      db()->query(
        "UPDATE federation_patterns SET source_count = source_count + 1, confidence = LEAST(confidence + 0.05, 1.00) WHERE id = ?",
        [$existing['id']]
      );
      return ['success' => true, 'action' => 'reinforced', 'pattern_id' => (int)$existing['id']];
    }

    $patternId = db()->insert('federation_patterns', [
      'tenant_id'           => $tenantId,
      'pattern_hash'        => $hash,
      'category'            => $category,
      'pattern_name'        => $name,
      'pattern_data'        => json_encode($sanitized['data']),
      'confidence'          => $confidence,
      'source_count'        => 1,
      'improvement_percent' => $data['improvement_percent'] ?? 0,
      'status'              => 'pending',
    ]);

    ErrorCollector::log('ecosystem', "Pattern submitted for federation: {$name}", 'INFO');

    return ['success' => true, 'action' => 'submitted', 'pattern_id' => (int)$patternId];
  }

  /**
   * Sanitize pattern data — remove all PII.
   */
  private static function sanitizePattern(array $data): array
  {
    $piiFields = [
      'name',
      'email',
      'phone',
      'address',
      'student_name',
      'teacher_name',
      'parent_name',
      'guardian',
      'ssn',
      'national_id',
      'dob',
      'date_of_birth',
      'ip_address',
      'password',
      'token'
    ];

    $safe = true;
    $cleaned = [];

    foreach ($data as $key => $value) {
      if (in_array(strtolower($key), $piiFields, true)) {
        $safe = false;
        continue; // Strip PII field
      }
      if (is_string($value) && preg_match('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $value)) {
        $safe = false;
        continue; // Strip email-containing values
      }
      $cleaned[$key] = $value;
    }

    return ['safe' => $safe || empty(array_diff_key($data, $cleaned)), 'data' => $cleaned];
  }

  /**
   * Get approved patterns available for distribution.
   */
  public static function getApprovedPatterns(string $category = ''): array
  {
    self::ensureTable();
    if ($category) {
      return db()->fetchAll(
        "SELECT * FROM federation_patterns WHERE status = 'approved' AND category = ? ORDER BY confidence DESC",
        [$category]
      );
    }
    return db()->fetchAll("SELECT * FROM federation_patterns WHERE status = 'approved' ORDER BY confidence DESC");
  }

  /**
   * Approve a pattern through ConsensusGuard validation.
   */
  public static function approvePattern(int $patternId): array
  {
    $pattern = db()->fetchOne("SELECT * FROM federation_patterns WHERE id = ?", [$patternId]);
    if (!$pattern) {
      return ['success' => false, 'reason' => 'Pattern not found'];
    }

    // Run through ConsensusGuard
    $consensus = ConsensusGuard::validate([
      'name'                => $pattern['pattern_name'],
      'category'            => $pattern['category'],
      'source_count'        => (int)$pattern['source_count'],
      'confidence'          => (float)$pattern['confidence'],
      'improvement_percent' => (float)$pattern['improvement_percent'],
      'age_hours'           => (time() - strtotime($pattern['created_at'])) / 3600,
    ]);

    if (!$consensus['allowed']) {
      db()->query("UPDATE federation_patterns SET status = 'rejected' WHERE id = ?", [$patternId]);
      return ['success' => false, 'reason' => 'ConsensusGuard rejected', 'checks' => $consensus['checks']];
    }

    db()->query("UPDATE federation_patterns SET status = 'approved' WHERE id = ?", [$patternId]);
    ErrorCollector::log('ecosystem', "Pattern approved: {$pattern['pattern_name']}", 'INFO');

    return ['success' => true, 'pattern' => $pattern['pattern_name']];
  }

  /**
   * Distribute approved patterns.
   */
  public static function distribute(): array
  {
    self::ensureTable();
    $patterns = db()->fetchAll("SELECT * FROM federation_patterns WHERE status = 'approved' AND distributed_at IS NULL");
    $distributed = 0;

    foreach ($patterns as $p) {
      db()->query(
        "UPDATE federation_patterns SET status = 'distributed', distributed_at = NOW() WHERE id = ?",
        [$p['id']]
      );
      $distributed++;
    }

    return ['distributed' => $distributed];
  }

  /**
   * Get summary for dashboard.
   */
  public static function getSummary(): array
  {
    self::ensureTable();
    $total = db()->fetchOne("SELECT COUNT(*) AS cnt FROM federation_patterns");
    $approved = db()->fetchOne("SELECT COUNT(*) AS cnt FROM federation_patterns WHERE status = 'approved'");
    $distributed = db()->fetchOne("SELECT COUNT(*) AS cnt FROM federation_patterns WHERE status = 'distributed'");
    $pending = db()->fetchOne("SELECT COUNT(*) AS cnt FROM federation_patterns WHERE status = 'pending'");

    return [
      'total'       => (int)($total['cnt'] ?? 0),
      'approved'    => (int)($approved['cnt'] ?? 0),
      'distributed' => (int)($distributed['cnt'] ?? 0),
      'pending'     => (int)($pending['cnt'] ?? 0),
      'model'       => 'Abstracted Intelligence Only',
    ];
  }
}
