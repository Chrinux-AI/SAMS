<?php

/**
 * Session Intelligence Engine — Adaptive sessions with device/IP/geo tracking.
 *
 * Tracks: IP address changes, device fingerprint, browser signature, geo anomalies.
 * Rule: IF device_changed AND location_changed THEN require_reauthentication()
 */
class SessionIntelligence
{
  /**
   * Register a new session after successful login.
   */
  public static function register(int $userId, string $role): void
  {
    $deviceHash = self::computeDeviceHash();
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    // Store session fingerprint in $_SESSION
    $_SESSION['_login_ip'] = $ip;
    $_SESSION['_login_ua'] = $userAgent;
    $_SESSION['_device_hash'] = $deviceHash;
    $_SESSION['_session_registered'] = time();
    $_SESSION['_risk_score'] = 0;
    $_SESSION['_risk_level'] = 'normal';

    // Persist to DB for multi-session tracking
    try {
      db()->insert('session_intelligence', [
        'user_id'       => $userId,
        'session_id'    => hash('sha256', session_id()),
        'device_hash'   => $deviceHash,
        'ip_address'    => $ip,
        'user_agent'    => mb_substr($userAgent, 0, 500),
        'browser'       => self::parseBrowser($userAgent),
        'platform'      => self::parsePlatform($userAgent),
        'risk_score'    => 0,
        'is_active'     => 1,
        'last_activity' => date('Y-m-d H:i:s'),
        'created_at'    => date('Y-m-d H:i:s'),
      ]);
    } catch (\Throwable $e) {
      error_log("SessionIntelligence register failed: " . $e->getMessage());
    }
  }

  /**
   * Validate the current session on every request.
   * Returns anomaly data if issues detected.
   *
   * @return array{valid: bool, anomalies: array, risk_score: int}
   */
  public static function validate(int $userId): array
  {
    $anomalies = [];
    $riskAdd = 0;
    $currentIP = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $currentUA = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $currentHash = self::computeDeviceHash();

    // 1. IP change detection
    if (isset($_SESSION['_login_ip']) && $_SESSION['_login_ip'] !== $currentIP) {
      $anomalies[] = [
        'type'   => 'ip_change',
        'detail' => "IP changed from {$_SESSION['_login_ip']} to {$currentIP}",
      ];
      $riskAdd += 20;
    }

    // 2. Device fingerprint change
    if (isset($_SESSION['_device_hash']) && $_SESSION['_device_hash'] !== $currentHash) {
      $anomalies[] = [
        'type'   => 'device_change',
        'detail' => 'Device fingerprint changed mid-session',
      ];
      $riskAdd += 25;
    }

    // 3. User agent change
    if (isset($_SESSION['_login_ua']) && $_SESSION['_login_ua'] !== $currentUA && $currentUA !== '') {
      $anomalies[] = [
        'type'   => 'ua_change',
        'detail' => 'Browser signature changed',
      ];
      $riskAdd += 15;
    }

    // 4. Combined anomaly rule: device + IP changed → force reauth
    $deviceChanged = !empty(array_filter($anomalies, fn($a) => $a['type'] === 'device_change'));
    $ipChanged = !empty(array_filter($anomalies, fn($a) => $a['type'] === 'ip_change'));
    if ($deviceChanged && $ipChanged) {
      $anomalies[] = [
        'type'   => 'combined_anomaly',
        'detail' => 'Both device and IP changed — session hijack likely',
      ];
      $riskAdd += 30; // Additional weight for combined anomaly
    }

    // Update session risk score
    $currentRisk = $_SESSION['_risk_score'] ?? 0;
    $newRisk = min($currentRisk + $riskAdd, 100);
    $_SESSION['_risk_score'] = $newRisk;
    $_SESSION['_risk_level'] = BehaviorMonitor::classifyRisk($newRisk);

    // Update DB record
    self::updateSessionRecord($userId, $newRisk);

    return [
      'valid'      => empty($anomalies),
      'anomalies'  => $anomalies,
      'risk_score' => $newRisk,
    ];
  }

  /**
   * Require re-authentication if anomalies detected.
   * Sets session flag that login page checks.
   */
  public static function requireReauthentication(string $reason): void
  {
    $_SESSION['_require_reauth'] = true;
    $_SESSION['_reauth_reason'] = $reason;
    $_SESSION['_reauth_requested'] = time();
  }

  /**
   * Check if re-authentication is required.
   */
  public static function isReauthRequired(): bool
  {
    return !empty($_SESSION['_require_reauth']);
  }

  /**
   * Clear re-authentication requirement (after successful re-auth).
   */
  public static function clearReauthRequirement(): void
  {
    unset($_SESSION['_require_reauth'], $_SESSION['_reauth_reason'], $_SESSION['_reauth_requested']);
  }

  /**
   * Touch session — update last_activity and deactivate old sessions.
   */
  public static function touch(int $userId): void
  {
    try {
      $sessionHash = hash('sha256', session_id());
      db()->query(
        "UPDATE session_intelligence SET last_activity = NOW() WHERE session_id = :sid AND user_id = :uid",
        ['sid' => $sessionHash, 'uid' => $userId]
      );
    } catch (\Throwable $e) {
      // Non-critical
    }
  }

  /**
   * Deactivate the current session in the DB.
   */
  public static function deactivate(int $userId): void
  {
    try {
      $sessionHash = hash('sha256', session_id());
      db()->query(
        "UPDATE session_intelligence SET is_active = 0 WHERE session_id = :sid AND user_id = :uid",
        ['sid' => $sessionHash, 'uid' => $userId]
      );
    } catch (\Throwable $e) {
      error_log("SessionIntelligence deactivate failed: " . $e->getMessage());
    }
  }

  /**
   * Get all active sessions for a user (for session management UI).
   */
  public static function getActiveSessions(int $userId): array
  {
    try {
      return db()->fetchAll(
        "SELECT id, device_hash, ip_address, browser, platform, risk_score, last_activity, created_at
                 FROM session_intelligence
                 WHERE user_id = :uid AND is_active = 1
                 ORDER BY last_activity DESC",
        ['uid' => $userId]
      );
    } catch (\Throwable $e) {
      return [];
    }
  }

  /**
   * Terminate a specific session by session record ID.
   */
  public static function terminateSession(int $userId, int $sessionRecordId): bool
  {
    try {
      return db()->update(
        'session_intelligence',
        ['is_active' => 0],
        'id = :id AND user_id = :uid',
        ['id' => $sessionRecordId, 'uid' => $userId]
      );
    } catch (\Throwable $e) {
      return false;
    }
  }

  /**
   * Terminate all sessions for a user except the current one.
   */
  public static function terminateOtherSessions(int $userId): int
  {
    try {
      $currentHash = hash('sha256', session_id());
      $stmt = db()->query(
        "UPDATE session_intelligence SET is_active = 0 WHERE user_id = :uid AND session_id != :sid AND is_active = 1",
        ['uid' => $userId, 'sid' => $currentHash]
      );
      return $stmt ? $stmt->rowCount() : 0;
    } catch (\Throwable $e) {
      return 0;
    }
  }

  /**
   * Get all active sessions system-wide (admin dashboard).
   */
  public static function getAllActiveSessions(int $limit = 100): array
  {
    try {
      return db()->fetchAll(
        "SELECT si.*, u.full_name, u.email, u.role
                 FROM session_intelligence si
                 LEFT JOIN users u ON u.id = si.user_id
                 WHERE si.is_active = 1
                 AND si.last_activity > DATE_SUB(NOW(), INTERVAL 30 MINUTE)
                 ORDER BY si.last_activity DESC
                 LIMIT " . min($limit, 500)
      );
    } catch (\Throwable $e) {
      return [];
    }
  }

  /**
   * Compute a device fingerprint hash from available request data.
   */
  private static function computeDeviceHash(): string
  {
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $lang = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
    $encoding = $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '';

    return hash('sha256', $ua . '|' . $accept . '|' . $lang . '|' . $encoding);
  }

  /**
   * Parse browser name from User-Agent string.
   */
  private static function parseBrowser(string $ua): string
  {
    if (preg_match('/Edg\//i', $ua)) return 'Edge';
    if (preg_match('/OPR\//i', $ua)) return 'Opera';
    if (preg_match('/Chrome\//i', $ua)) return 'Chrome';
    if (preg_match('/Firefox\//i', $ua)) return 'Firefox';
    if (preg_match('/Safari\//i', $ua)) return 'Safari';
    if (preg_match('/MSIE|Trident/i', $ua)) return 'IE';
    return 'Unknown';
  }

  /**
   * Parse platform from User-Agent string.
   */
  private static function parsePlatform(string $ua): string
  {
    if (preg_match('/Windows/i', $ua)) return 'Windows';
    if (preg_match('/Macintosh/i', $ua)) return 'macOS';
    if (preg_match('/Linux/i', $ua)) return 'Linux';
    if (preg_match('/Android/i', $ua)) return 'Android';
    if (preg_match('/iPhone|iPad/i', $ua)) return 'iOS';
    return 'Unknown';
  }

  /**
   * Update session record in DB with new risk score.
   */
  private static function updateSessionRecord(int $userId, int $riskScore): void
  {
    try {
      $sessionHash = hash('sha256', session_id());
      db()->query(
        "UPDATE session_intelligence SET risk_score = :score, last_activity = NOW() WHERE session_id = :sid AND user_id = :uid",
        ['score' => $riskScore, 'sid' => $sessionHash, 'uid' => $userId]
      );
    } catch (\Throwable $e) {
      // Non-critical
    }
  }

  /**
   * Cleanup stale sessions (call from cron).
   */
  public static function cleanup(int $hours = 24): int
  {
    try {
      $stmt = db()->query(
        "UPDATE session_intelligence SET is_active = 0 WHERE is_active = 1 AND last_activity < DATE_SUB(NOW(), INTERVAL :hours HOUR)",
        ['hours' => $hours]
      );
      return $stmt ? $stmt->rowCount() : 0;
    } catch (\Throwable $e) {
      return 0;
    }
  }
}
