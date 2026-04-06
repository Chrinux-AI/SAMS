<?php

/**
 * Cache Service — File-based caching layer for SAMS.
 * Provides Redis-like API with file-system backend.
 * Ready to swap to Redis/Memcached when scaling.
 */
class CacheService
{
  private static ?CacheService $instance = null;
  private string $cacheDir;
  private const DEFAULT_TTL = 300; // 5 minutes

  private function __construct()
  {
    $this->cacheDir = (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2)) . '/cache/data';
    if (!is_dir($this->cacheDir)) {
      mkdir($this->cacheDir, 0755, true);
    }
  }

  public static function getInstance(): self
  {
    if (self::$instance === null) {
      self::$instance = new self();
    }
    return self::$instance;
  }

  /**
   * Get a cached value.
   *
   * @param string $key     Cache key
   * @param mixed  $default Default if key not found or expired
   * @return mixed
   */
  public function get(string $key, $default = null)
  {
    $path = $this->keyToPath($key);
    if (!is_file($path)) {
      return $default;
    }

    $raw = file_get_contents($path);
    if ($raw === false) {
      return $default;
    }

    $data = json_decode($raw, true);
    if (!$data || !isset($data['expires_at'])) {
      return $default;
    }

    // Check expiry
    if ($data['expires_at'] > 0 && $data['expires_at'] < time()) {
      @unlink($path);
      return $default;
    }

    return $data['value'];
  }

  /**
   * Set a cached value.
   *
   * @param string $key   Cache key
   * @param mixed  $value Value to cache (must be JSON-serializable)
   * @param int    $ttl   Time-to-live in seconds (0 = forever)
   */
  public function set(string $key, $value, int $ttl = self::DEFAULT_TTL): bool
  {
    $path = $this->keyToPath($key);
    $dir = dirname($path);
    if (!is_dir($dir)) {
      mkdir($dir, 0755, true);
    }

    $data = [
      'key'        => $key,
      'value'      => $value,
      'created_at' => time(),
      'expires_at' => $ttl > 0 ? time() + $ttl : 0,
    ];

    return file_put_contents($path, json_encode($data), LOCK_EX) !== false;
  }

  /**
   * Check if a key exists and is not expired.
   */
  public function has(string $key): bool
  {
    return $this->get($key, '__MISS__') !== '__MISS__';
  }

  /**
   * Delete a cached key.
   */
  public function forget(string $key): bool
  {
    $path = $this->keyToPath($key);
    return is_file($path) ? @unlink($path) : true;
  }

  /**
   * Get or compute: returns cached value, or calls $callback and caches the result.
   *
   * @param string   $key      Cache key
   * @param int      $ttl      TTL in seconds
   * @param callable $callback Function to compute value if cache miss
   * @return mixed
   */
  public function remember(string $key, int $ttl, callable $callback)
  {
    $value = $this->get($key);
    if ($value !== null) {
      return $value;
    }

    $value = $callback();
    $this->set($key, $value, $ttl);
    return $value;
  }

  /**
   * Flush all cached data.
   */
  public function flush(): int
  {
    $count = 0;
    $files = glob($this->cacheDir . '/**/*.json') ?: [];
    $files = array_merge($files, glob($this->cacheDir . '/*.json') ?: []);
    foreach ($files as $file) {
      if (@unlink($file)) {
        $count++;
      }
    }
    return $count;
  }

  /**
   * Flush expired entries only.
   */
  public function flushExpired(): int
  {
    $count = 0;
    $files = glob($this->cacheDir . '/**/*.json') ?: [];
    $files = array_merge($files, glob($this->cacheDir . '/*.json') ?: []);
    $now = time();

    foreach ($files as $file) {
      $raw = @file_get_contents($file);
      if ($raw === false) {
        continue;
      }
      $data = json_decode($raw, true);
      if ($data && isset($data['expires_at']) && $data['expires_at'] > 0 && $data['expires_at'] < $now) {
        @unlink($file);
        $count++;
      }
    }
    return $count;
  }

  /**
   * Increment a numeric cache value.
   */
  public function increment(string $key, int $amount = 1): int
  {
    $value = (int) $this->get($key, 0);
    $value += $amount;
    $this->set($key, $value, self::DEFAULT_TTL);
    return $value;
  }

  /**
   * Convert a cache key to a file path.
   */
  private function keyToPath(string $key): string
  {
    // Use first 2 chars of hash as subdirectory for distribution
    $hash = md5($key);
    $subdir = substr($hash, 0, 2);
    $filename = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $key) . '.json';
    return $this->cacheDir . '/' . $subdir . '/' . $filename;
  }
}

/**
 * Global cache helper function.
 */
function cache(): CacheService
{
  return CacheService::getInstance();
}
