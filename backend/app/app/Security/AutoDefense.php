<?php

/**
 * AutoDefense — Automated Threat Response System.
 *
 * Reacts to threats with progressive defense levels:
 *   Level 1 (Soft)     — session verification, CAPTCHA challenge
 *   Level 2 (Medium)   — feature restriction, forced token regeneration
 *   Level 3 (Hard)     — logout user, lock account, notify admin
 *   Level 4 (Critical) — IP temporary ban, disable admin privileges, forensic snapshot
 */
class AutoDefense
{
  // Defense levels
  public const LEVEL_SOFT     = 1;
  public const LEVEL_MEDIUM   = 2;
  public const LEVEL_HARD     = 3;
  public const LEVEL_CRITICAL = 4;

  /**
   * Execute automated defense based on risk score and threat classification.
   *
   * @return array{level: int, actions_taken: array, session_active: bool}
   */
  public static function respond(int $userId, int $riskScore, string $threatLevel, array $factors = []): array
  {
    $defenseLevel = self::determineDefenseLevel($riskScore, $threatLevel);
    $actions = [];

    switch ($defenseLevel) {
      case self::LEVEL_SOFT:
        $actions = self::softDefense($userId, $factors);
        break;
      case self::LEVEL_MEDIUM:
        $actions = self::mediumDefense($userId, $factors);
        break;
      case self::LEVEL_HARD:
        $actions = self::hardDefense($userId, $factors);
        break;
      case self::LEVEL_CRITICAL:
        $actions = self::criticalDefense($userId, $factors);
        break;
    }

    // Log the defense action
    self::logDefenseAction($userId, $defenseLevel, $riskScore, $actions);

    return [
      'level'          => $defenseLevel,
      'actions_taken'  => $actions,
      'session_active' => session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['user_id']),
    ];
  }

  /**
   * Determine defense level from risk score and threat classification.
   */
  public static function determineDefenseLevel(int $riskScore, string $threatLevel): int
  {
    if ($riskScore >= 81 || $threatLevel === 'attack_likely') {
      return self::LEVEL_CRITICAL;
    }
    if ($riskScore >= 61 || $threatLevel === 'critical') {
      return self::LEVEL_HARD;
    }
    if ($riskScore >= 31 || $threatLevel === 'suspicious') {
      return self::LEVEL_MEDIUM;
    }
    return self::LEVEL_SOFT;
  }

  /**
   * Level 1 — Soft Defense: session verification, challenge flag.
   */
  private static function softDefense(int $userId, array $factors): array
  {
    $actions = [];

    // Set flag requiring verification on next sensitive action
    $_SESSION['_require_verification'] = true;
    $_SESSION['_verification_reason'] = 'Unusual activity detected';
    $actions[] = 'session_verification_required';

    // Set CAPTCHA flag for next form submission
    $_SESSION['_captcha_required'] = true;
    $actions[] = 'captcha_challenge_set';

    return $actions;
  }

  /**
   * Level 2 — Medium Defense: feature restriction, token regeneration.
   */
  private static function mediumDefense(int $userId, array $factors): array
  {
    $actions = self::softDefense($userId, $factors);

    // Restrict sensitive features temporarily
    $_SESSION['_restricted_mode'] = true;
    $_SESSION['_restriction_until'] = time() + 900; // 15 min
    $_SESSION['_restricted_features'] = ['export', 'bulk_delete', 'role_change', 'settings_change'];
    $actions[] = 'feature_restriction_applied';

    // Force session token regeneration
    if (session_status() === PHP_SESSION_ACTIVE) {
      session_regenerate_id(true);
      $actions[] = 'session_token_regenerated';
    }

    return $actions;
  }

  /**
   * Level 3 — Hard Defense: logout user, lock account, notify admin.
   */
  private static function hardDefense(int $userId, array $factors): array
  {
    $actions = [];

    // Lock the user account temporarily
    self::lockAccount($userId, 30); // 30 min lockout
    $actions[] = 'account_locked_30min';

    // Notify admin via security event
    self::notifyAdmin($userId, 'hard_defense', $factors);
    $actions[] = 'admin_notified';

    // Destroy session
    self::destroyUserSession();
    $actions[] = 'session_destroyed';

    return $actions;
  }

  /**
   * Level 4 — Critical Defense: IP ban, privilege disable, forensic snapshot.
   */
  private static function criticalDefense(int $userId, array $factors): array
  {
    $actions = [];

    // Create forensic snapshot before taking action
    self::createForensicSnapshot($userId, $factors);
    $actions[] = 'forensic_snapshot_created';

    // Temporary IP ban
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    self::banIP($ip, 60); // 60 min ban
    $actions[] = "ip_banned_{$ip}";

    // If admin, disable elevated privileges
    $role = $_SESSION['role'] ?? $_SESSION['user_role'] ?? '';
    if ($role === 'admin') {
      self::disableAdminPrivileges($userId);
      $actions[] = 'admin_privileges_suspended';
    }

    // Lock account indefinitely (admin must unlock)
    self::lockAccount($userId, 0);
    $actions[] = 'account_locked_indefinite';

    // Notify all admins
    self::notifyAdmin($userId, 'critical_defense', $factors);
    $actions[] = 'all_admins_notified';

    // Destroy session
    self::destroyUserSession();
    $actions[] = 'session_destroyed';

    return $actions;
  }

  /**
   * Lock a user account for a specified duration (minutes). 0 = indefinite.
   */
  private static function lockAccount(int $userId, int $minutes): void
  {
    try {
      $lockUntil = $minutes > 0
        ? date('Y-m-d H:i:s', strtotime("+{$minutes} minutes"))
        : '2099-12-31 23:59:59';

      db()->update('users', [
        'account_locked' => 1,
        'locked_until'   => $lockUntil,
      ], 'id = :id', ['id' => $userId]);
    } catch (\Throwable $e) {
      error_log("AutoDefense lockAccount failed: " . $e->getMessage());
    }
  }

  /**
   * Temporarily ban an IP address.
   */
  private static function banIP(string $ip, int $minutes): void
  {
    try {
      // Use IP validation
      if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        return;
      }
      $expiresAt = date('Y-m-d H:i:s', strtotime("+{$minutes} minutes"));
      db()->insert('ip_bans', [
        'ip_address' => $ip,
        'reason'     => 'Auto-defense: critical threat level detected',
        'expires_at' => $expiresAt,
        'created_at' => date('Y-m-d H:i:s'),
      ]);
    } catch (\Throwable $e) {
      error_log("AutoDefense banIP failed: " . $e->getMessage());
    }
  }

  /**
   * Check if an IP is currently banned.
   */
  public static function isIPBanned(string $ip): bool
  {
    try {
      if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        return false;
      }
      $ban = db()->fetchOne(
        "SELECT id FROM ip_bans WHERE ip_address = :ip AND expires_at > NOW()",
        ['ip' => $ip]
      );
      return $ban !== false;
    } catch (\Throwable $e) {
      return false;
    }
  }

  /**
   * Disable admin privileges for a user (downgrade to restricted mode).
   */
  private static function disableAdminPrivileges(int $userId): void
  {
    try {
      db()->insert('security_events', [
        'event_type'  => 'admin_privileges_suspended',
        'severity'    => 'critical',
        'user_id'     => $userId,
        'ip_address'  => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
        'details'     => json_encode(['action' => 'admin_privileges_suspended', 'timestamp' => date('c')]),
        'resolved'    => 0,
        'created_at'  => date('Y-m-d H:i:s'),
      ]);
    } catch (\Throwable $e) {
      error_log("AutoDefense disableAdmin failed: " . $e->getMessage());
    }
  }

  /**
   * Create a forensic snapshot: captures session state, recent actions, and context.
   */
  private static function createForensicSnapshot(int $userId, array $factors): void
  {
    try {
      $snapshot = [
        'user_id'        => $userId,
        'session_data'   => self::sanitizeSessionData(),
        'ip_address'     => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
        'user_agent'     => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'request_uri'    => $_SERVER['REQUEST_URI'] ?? '',
        'factors'        => $factors,
        'timestamp'      => date('c'),
      ];

      // Get recent behavior log for this user
      $recentActions = db()->fetchAll(
        "SELECT action, ip_address, created_at FROM behavior_log
                 WHERE user_id = :uid ORDER BY created_at DESC LIMIT 50",
        ['uid' => $userId]
      );
      $snapshot['recent_actions'] = $recentActions;

      db()->insert('forensic_snapshots', [
        'user_id'    => $userId,
        'snapshot'   => json_encode($snapshot, JSON_UNESCAPED_UNICODE),
        'trigger'    => 'auto_defense_critical',
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
        'created_at' => date('Y-m-d H:i:s'),
      ]);
    } catch (\Throwable $e) {
      error_log("AutoDefense forensic snapshot failed: " . $e->getMessage());
    }
  }

  /**
   * Sanitize session data for forensic snapshot (remove sensitive values).
   */
  private static function sanitizeSessionData(): array
  {
    $safe = $_SESSION ?? [];
    // Remove sensitive keys
    unset($safe['csrf_token'], $safe['password'], $safe['otp_secret']);
    // Truncate large values
    foreach ($safe as $k => $v) {
      if (is_string($v) && strlen($v) > 500) {
        $safe[$k] = substr($v, 0, 500) . '…';
      }
    }
    return $safe;
  }

  /**
   * Notify admin users about a security event.
   */
  private static function notifyAdmin(int $userId, string $defenseType, array $factors): void
  {
    try {
      $message = "AutoDefense triggered ({$defenseType}) for user #{$userId} from IP " .
        ($_SERVER['REMOTE_ADDR'] ?? 'unknown');

      // Insert admin notification
      if (class_exists('NotificationService')) {
        NotificationService::sendToRole('admin', 'Security Alert', $message, 'danger', '/admin/security-center.php');
      }

      // Broadcast to admin sessions
      if (class_exists('Broadcaster')) {
        Broadcaster::toRole('admin', 'security_alert', [
          'type'    => $defenseType,
          'user_id' => $userId,
          'message' => $message,
        ]);
      }
    } catch (\Throwable $e) {
      error_log("AutoDefense admin notification failed: " . $e->getMessage());
    }
  }

  /**
   * Destroy the current user session completely.
   */
  private static function destroyUserSession(): void
  {
    if (session_status() === PHP_SESSION_ACTIVE) {
      $_SESSION = [];
      if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
      }
      session_destroy();
    }
  }

  /**
   * Check if current user is in restricted mode.
   */
  public static function isRestricted(): bool
  {
    if (empty($_SESSION['_restricted_mode'])) {
      return false;
    }
    // Check if restriction has expired
    if (isset($_SESSION['_restriction_until']) && time() > $_SESSION['_restriction_until']) {
      $_SESSION['_restricted_mode'] = false;
      unset($_SESSION['_restricted_features'], $_SESSION['_restriction_until']);
      return false;
    }
    return true;
  }

  /**
   * Check if a specific feature is restricted for the current user.
   */
  public static function isFeatureRestricted(string $feature): bool
  {
    if (!self::isRestricted()) {
      return false;
    }
    $restricted = $_SESSION['_restricted_features'] ?? [];
    return in_array($feature, $restricted, true);
  }

  /**
   * Clean up expired IP bans.
   */
  public static function cleanupBans(): int
  {
    try {
      $stmt = db()->query("DELETE FROM ip_bans WHERE expires_at < NOW()");
      return $stmt ? $stmt->rowCount() : 0;
    } catch (\Throwable $e) {
      return 0;
    }
  }

  /**
   * Get active defense actions for the dashboard.
   */
  public static function getActiveDefenses(): array
  {
    try {
      $bans = db()->fetchAll("SELECT * FROM ip_bans WHERE expires_at > NOW() ORDER BY created_at DESC LIMIT 50");
      $locks = db()->fetchAll(
        "SELECT u.id, u.full_name, u.email, u.locked_until
                 FROM users u WHERE u.account_locked = 1 AND u.locked_until > NOW()
                 ORDER BY u.locked_until DESC LIMIT 50"
      );
      $snapshots = db()->fetchAll(
        "SELECT fs.*, u.full_name FROM forensic_snapshots fs
                 LEFT JOIN users u ON u.id = fs.user_id
                 ORDER BY fs.created_at DESC LIMIT 20"
      );
      return [
        'ip_bans'    => $bans,
        'locked_accounts' => $locks,
        'forensic_snapshots' => $snapshots,
      ];
    } catch (\Throwable $e) {
      return ['ip_bans' => [], 'locked_accounts' => [], 'forensic_snapshots' => []];
    }
  }
}
