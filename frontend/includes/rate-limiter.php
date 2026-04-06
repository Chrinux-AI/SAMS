<?php

/**
 * SAMS Rate Limiter
 * Database-backed, IP-based rate limiting for login and API endpoints.
 * File-based fallback when DB is unavailable.
 */

class RateLimiter
{
  private string $storageDir;

  public function __construct()
  {
    $this->storageDir = (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__)) . '/storage/rate_limits';
    if (!is_dir($this->storageDir)) {
      @mkdir($this->storageDir, 0755, true);
    }
  }

  /**
   * Check if an action is allowed under the rate limit.
   *
   * @param string $action   Action identifier (e.g., 'login', 'ai_chat')
   * @param string $identity IP address or user identifier
   * @param int    $maxAttempts Maximum allowed attempts
   * @param int    $windowSeconds Time window in seconds
   * @return array ['allowed' => bool, 'remaining' => int, 'retry_after' => int]
   */
  public function check(string $action, string $identity, int $maxAttempts, int $windowSeconds): array
  {
    $key = $this->buildKey($action, $identity);

    // Try DB first, fall back to file
    try {
      return $this->checkDb($key, $maxAttempts, $windowSeconds);
    } catch (\Throwable $e) {
      return $this->checkFile($key, $maxAttempts, $windowSeconds);
    }
  }

  /**
   * Record an attempt for the given action.
   */
  public function record(string $action, string $identity): void
  {
    $key = $this->buildKey($action, $identity);
    try {
      $this->recordDb($key);
    } catch (\Throwable $e) {
      $this->recordFile($key);
    }
  }

  /**
   * Clear all attempts for an action/identity pair (e.g., on successful login).
   */
  public function clear(string $action, string $identity): void
  {
    $key = $this->buildKey($action, $identity);
    try {
      $this->clearDb($key);
    } catch (\Throwable $e) {
      $this->clearFile($key);
    }
  }

  // ── DB-backed methods ──────────────────────────────

  private function ensureTable(): void
  {
    db()->query("CREATE TABLE IF NOT EXISTS rate_limits (
            id INT AUTO_INCREMENT PRIMARY KEY,
            rate_key VARCHAR(255) NOT NULL,
            attempts INT DEFAULT 1,
            first_attempt DATETIME NOT NULL,
            last_attempt DATETIME NOT NULL,
            UNIQUE KEY idx_rate_key (rate_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  }

  private function checkDb(string $key, int $max, int $window): array
  {
    $this->ensureTable();
    $cutoff = date('Y-m-d H:i:s', time() - $window);

    // Clean old entries
    db()->query("DELETE FROM rate_limits WHERE last_attempt < ?", [$cutoff]);

    $row = db()->fetchOne("SELECT attempts, first_attempt FROM rate_limits WHERE rate_key = ? AND first_attempt >= ?", [$key, $cutoff]);

    if (!$row) {
      return ['allowed' => true, 'remaining' => $max, 'retry_after' => 0];
    }

    $attempts = (int)$row['attempts'];
    if ($attempts >= $max) {
      $first = strtotime($row['first_attempt']);
      $retryAfter = max(0, ($first + $window) - time());
      return ['allowed' => false, 'remaining' => 0, 'retry_after' => $retryAfter];
    }

    return ['allowed' => true, 'remaining' => $max - $attempts, 'retry_after' => 0];
  }

  private function recordDb(string $key): void
  {
    $this->ensureTable();
    $now = date('Y-m-d H:i:s');
    $existing = db()->fetchOne("SELECT id FROM rate_limits WHERE rate_key = ?", [$key]);
    if ($existing) {
      db()->query("UPDATE rate_limits SET attempts = attempts + 1, last_attempt = ? WHERE rate_key = ?", [$now, $key]);
    } else {
      db()->query("INSERT INTO rate_limits (rate_key, attempts, first_attempt, last_attempt) VALUES (?, 1, ?, ?)", [$key, $now, $now]);
    }
  }

  private function clearDb(string $key): void
  {
    $this->ensureTable();
    db()->query("DELETE FROM rate_limits WHERE rate_key = ?", [$key]);
  }

  // ── File-based fallback ────────────────────────────

  private function checkFile(string $key, int $max, int $window): array
  {
    $file = $this->storageDir . '/' . $key . '.json';
    if (!file_exists($file)) {
      return ['allowed' => true, 'remaining' => $max, 'retry_after' => 0];
    }

    $data = json_decode(file_get_contents($file), true);
    if (!$data || (time() - ($data['first'] ?? 0)) > $window) {
      @unlink($file);
      return ['allowed' => true, 'remaining' => $max, 'retry_after' => 0];
    }

    $attempts = (int)($data['count'] ?? 0);
    if ($attempts >= $max) {
      $retryAfter = max(0, ($data['first'] + $window) - time());
      return ['allowed' => false, 'remaining' => 0, 'retry_after' => $retryAfter];
    }

    return ['allowed' => true, 'remaining' => $max - $attempts, 'retry_after' => 0];
  }

  private function recordFile(string $key): void
  {
    $file = $this->storageDir . '/' . $key . '.json';
    $data = ['count' => 0, 'first' => time()];
    if (file_exists($file)) {
      $existing = json_decode(file_get_contents($file), true);
      if ($existing) {
        $data = $existing;
      }
    }
    $data['count'] = ($data['count'] ?? 0) + 1;
    $data['last'] = time();
    file_put_contents($file, json_encode($data), LOCK_EX);
  }

  private function clearFile(string $key): void
  {
    $file = $this->storageDir . '/' . $key . '.json';
    @unlink($file);
  }

  private function buildKey(string $action, string $identity): string
  {
    // Sanitize to filesystem-safe name
    return preg_replace('/[^a-zA-Z0-9_-]/', '_', $action . '_' . $identity);
  }
}

/**
 * Global rate limiter instance.
 */
function rate_limiter(): RateLimiter
{
  static $instance = null;
  if ($instance === null) {
    $instance = new RateLimiter();
  }
  return $instance;
}
