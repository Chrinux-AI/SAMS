<?php

/**
 * API Security Middleware — Advanced API gateway protection.
 *
 * Controls: request rate anomaly detection, token misuse detection,
 *           replay attack prevention, payload validation.
 *
 * Protection: timestamp validation, request signature hash, nonce verification.
 */
class ApiSecurityMiddleware
{
  // Maximum allowed clock skew for timestamp validation (seconds)
  private const MAX_TIMESTAMP_SKEW = 300; // 5 minutes

  // Nonce expiry time (seconds)
  private const NONCE_EXPIRY = 600; // 10 minutes

  /**
   * Full API security check. Call at the top of API endpoints.
   *
   * @param array $options Configuration:
   *   'require_auth'   => bool (default: true)
   *   'require_role'   => string|array|null
   *   'rate_action'    => string (default: 'api')
   *   'validate_nonce' => bool (default: false, for critical endpoints)
   *   'validate_timestamp' => bool (default: true)
   *   'max_payload_size' => int (default: 1MB)
   */
  public static function protect(array $options = []): void
  {
    $requireAuth = $options['require_auth'] ?? true;
    $requireRole = $options['require_role'] ?? null;
    $rateAction = $options['rate_action'] ?? 'api';
    $validateNonce = $options['validate_nonce'] ?? false;
    $validateTimestamp = $options['validate_timestamp'] ?? true;
    $maxPayload = $options['max_payload_size'] ?? 1048576; // 1MB

    // 1. Check IP ban
    if (class_exists('AutoDefense') && AutoDefense::isIPBanned($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0')) {
      self::deny(403, 'Access denied');
    }

    // 2. Payload size validation
    $contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
    if ($contentLength > $maxPayload) {
      self::deny(413, 'Payload too large');
    }

    // 3. Timestamp validation (replay attack prevention)
    if ($validateTimestamp) {
      self::validateTimestamp();
    }

    // 4. Nonce verification (for critical endpoints)
    if ($validateNonce) {
      self::validateNonce();
    }

    // 5. Run base security gateway checks
    SecurityGateway::guard([
      'require_auth' => $requireAuth,
      'require_role' => $requireRole,
      'rate_action'  => $rateAction,
      'csrf'         => false, // APIs use other auth methods
      'xss_check'    => true,
      'sqli_check'   => true,
    ]);

    // 6. Behavior tracking for authenticated users
    if (isset($_SESSION['user_id'])) {
      $endpoint = $_SERVER['REQUEST_URI'] ?? 'unknown';
      $action = 'api_' . basename(parse_url($endpoint, PHP_URL_PATH) ?? '', '.php');
      if (class_exists('BehaviorMonitor')) {
        BehaviorMonitor::recordAction((int) $_SESSION['user_id'], $action, [
          'method'   => $_SERVER['REQUEST_METHOD'] ?? 'GET',
          'endpoint' => $endpoint,
        ]);
      }
    }

    // 7. Rate anomaly detection
    self::detectRateAnomaly();
  }

  /**
   * Validate X-Request-Timestamp header to prevent replay attacks.
   */
  private static function validateTimestamp(): void
  {
    $timestamp = $_SERVER['HTTP_X_REQUEST_TIMESTAMP'] ?? null;
    if ($timestamp === null) {
      return; // Optional — only validate if header is present
    }

    $requestTime = (int) $timestamp;
    $currentTime = time();

    if (abs($currentTime - $requestTime) > self::MAX_TIMESTAMP_SKEW) {
      AuditLogger::logSecurity('Replay attack: timestamp outside allowed window');
      self::deny(400, 'Request timestamp expired');
    }
  }

  /**
   * Validate X-Request-Nonce header for one-time-use requests.
   */
  private static function validateNonce(): void
  {
    $nonce = $_SERVER['HTTP_X_REQUEST_NONCE'] ?? null;
    if ($nonce === null) {
      self::deny(400, 'Missing request nonce');
    }

    // Validate nonce format (hex string)
    if (!preg_match('/^[a-f0-9]{32,64}$/i', $nonce)) {
      self::deny(400, 'Invalid nonce format');
    }

    try {
      // Check if nonce was already used
      $existing = db()->fetchOne(
        "SELECT id FROM api_nonces WHERE nonce = :nonce",
        ['nonce' => $nonce]
      );
      if ($existing) {
        AuditLogger::logSecurity("Nonce replay detected: {$nonce}");
        self::deny(400, 'Nonce already used');
      }

      // Store nonce to prevent reuse
      db()->insert('api_nonces', [
        'nonce'      => $nonce,
        'user_id'    => $_SESSION['user_id'] ?? null,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
        'expires_at' => date('Y-m-d H:i:s', time() + self::NONCE_EXPIRY),
        'created_at' => date('Y-m-d H:i:s'),
      ]);
    } catch (\Throwable $e) {
      error_log("ApiSecurityMiddleware nonce check failed: " . $e->getMessage());
    }
  }

  /**
   * Detect rate anomalies — sudden spikes in API usage for a user.
   */
  private static function detectRateAnomaly(): void
  {
    if (!isset($_SESSION['user_id'])) {
      return;
    }

    $userId = (int) $_SESSION['user_id'];
    $window = date('Y-m-d H:i:s', strtotime('-5 minutes'));

    try {
      $recentCalls = (int) (db()->fetchOne(
        "SELECT COUNT(*) as cnt FROM behavior_log
                 WHERE user_id = :uid AND created_at > :window AND action LIKE 'api_%'",
        ['uid' => $userId, 'window' => $window]
      )['cnt'] ?? 0);

      // Flag if more than 100 API calls in 5 minutes
      if ($recentCalls > 100) {
        if (class_exists('SecurityAI')) {
          $analysis = SecurityAI::analyze($userId);
          if ($analysis['threat'] !== 'normal' && class_exists('AutoDefense')) {
            AutoDefense::respond($userId, $analysis['score'], $analysis['threat'], $analysis['features']);
          }
        }
      }
    } catch (\Throwable $e) {
      // Non-critical — don't block the request
    }
  }

  /**
   * Generate a nonce for client-side requests.
   */
  public static function generateNonce(): string
  {
    return bin2hex(random_bytes(32));
  }

  /**
   * Clean up expired nonces (call from cron).
   */
  public static function cleanupNonces(): int
  {
    try {
      $stmt = db()->query("DELETE FROM api_nonces WHERE expires_at < NOW()");
      return $stmt ? $stmt->rowCount() : 0;
    } catch (\Throwable $e) {
      return 0;
    }
  }

  /**
   * Deny an API request.
   */
  private static function deny(int $code, string $message): void
  {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
  }
}
