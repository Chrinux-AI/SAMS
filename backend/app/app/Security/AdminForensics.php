<?php

/**
 * Admin Action Forensics — Every admin action becomes traceable.
 *
 * Tracks: action_type, affected_model, old_value, new_value, device, risk_score.
 * Snapshot feature: auto-saves previous state before critical changes for rollback.
 */
class AdminForensics
{
  // Critical actions that trigger auto-snapshot
  private static array $criticalActions = [
    'delete',
    'bulk_delete',
    'role_change',
    'settings_change',
    'password_reset',
    'account_lock',
    'account_unlock',
    'backup_restore',
    'user_create',
    'privilege_change',
  ];

  /**
   * Record an admin action with full forensic trail.
   */
  public static function record(
    int $adminId,
    string $actionType,
    string $affectedModel,
    ?int $targetId = null,
    $oldValue = null,
    $newValue = null,
    string $description = ''
  ): int {
    $riskScore = $_SESSION['_risk_score'] ?? 0;

    // Auto-snapshot before critical changes
    if (in_array($actionType, self::$criticalActions, true) && $targetId !== null) {
      self::autoSnapshot($affectedModel, $targetId, $adminId);
    }

    try {
      $id = db()->insert('audit_trails', [
        'admin_id'       => $adminId,
        'action_type'    => mb_substr($actionType, 0, 50),
        'affected_model' => mb_substr($affectedModel, 0, 100),
        'target_id'      => $targetId,
        'old_value'      => is_array($oldValue) ? json_encode($oldValue, JSON_UNESCAPED_UNICODE) : ($oldValue !== null ? (string) $oldValue : null),
        'new_value'      => is_array($newValue) ? json_encode($newValue, JSON_UNESCAPED_UNICODE) : ($newValue !== null ? (string) $newValue : null),
        'description'    => mb_substr($description, 0, 1000),
        'ip_address'     => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
        'device'         => mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
        'risk_score'     => $riskScore,
        'created_at'     => date('Y-m-d H:i:s'),
      ]);
      return (int) $id;
    } catch (\Throwable $e) {
      error_log("AdminForensics record failed: " . $e->getMessage());
      return 0;
    }
  }

  /**
   * Auto-save previous state of a record before critical change.
   */
  private static function autoSnapshot(string $model, int $targetId, int $adminId): void
  {
    try {
      // Attempt to read current record from the model table
      $tableName = self::resolveTableName($model);
      if ($tableName === null) {
        return;
      }

      $current = db()->fetchOne(
        "SELECT * FROM `{$tableName}` WHERE id = :id LIMIT 1",
        ['id' => $targetId]
      );

      if ($current) {
        db()->insert('forensic_snapshots', [
          'user_id'    => $targetId,
          'snapshot'   => json_encode([
            'table'     => $tableName,
            'record_id' => $targetId,
            'data'      => $current,
            'admin_id'  => $adminId,
          ], JSON_UNESCAPED_UNICODE),
          'trigger'    => 'pre_critical_change',
          'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
          'created_at' => date('Y-m-d H:i:s'),
        ]);
      }
    } catch (\Throwable $e) {
      error_log("AdminForensics autoSnapshot failed: " . $e->getMessage());
    }
  }

  /**
   * Resolve a model name to a database table name.
   */
  private static function resolveTableName(string $model): ?string
  {
    $map = [
      'user'          => 'users',
      'users'         => 'users',
      'student'       => 'students',
      'students'      => 'students',
      'teacher'       => 'teachers',
      'teachers'      => 'teachers',
      'class'         => 'classes',
      'classes'       => 'classes',
      'attendance'    => 'attendance',
      'notice'        => 'notices',
      'notices'       => 'notices',
      'settings'      => 'settings',
    ];
    $key = strtolower($model);
    if (!isset($map[$key])) {
      return null;
    }
    // Validate the resolved name is safe
    $table = $map[$key];
    if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]{0,63}$/', $table)) {
      return null;
    }
    return $table;
  }

  /**
   * Get audit trail entries with filters.
   */
  public static function getTrail(array $filters = [], int $limit = 50, int $offset = 0): array
  {
    $where = '1=1';
    $params = [];

    if (!empty($filters['admin_id'])) {
      $where .= ' AND at.admin_id = :admin_id';
      $params['admin_id'] = (int) $filters['admin_id'];
    }
    if (!empty($filters['action_type'])) {
      $where .= ' AND at.action_type = :action_type';
      $params['action_type'] = $filters['action_type'];
    }
    if (!empty($filters['affected_model'])) {
      $where .= ' AND at.affected_model = :model';
      $params['model'] = $filters['affected_model'];
    }
    if (!empty($filters['since'])) {
      $where .= ' AND at.created_at >= :since';
      $params['since'] = $filters['since'];
    }
    if (!empty($filters['min_risk'])) {
      $where .= ' AND at.risk_score >= :min_risk';
      $params['min_risk'] = (int) $filters['min_risk'];
    }

    $limit = min($limit, 500);
    try {
      return db()->fetchAll(
        "SELECT at.*, u.full_name as admin_name, u.email as admin_email
                 FROM audit_trails at
                 LEFT JOIN users u ON u.id = at.admin_id
                 WHERE {$where}
                 ORDER BY at.created_at DESC
                 LIMIT {$limit} OFFSET {$offset}",
        $params
      );
    } catch (\Throwable $e) {
      return [];
    }
  }

  /**
   * Get a specific audit trail entry with full details.
   */
  public static function getDetail(int $id): ?array
  {
    try {
      $entry = db()->fetchOne(
        "SELECT at.*, u.full_name as admin_name FROM audit_trails at
                 LEFT JOIN users u ON u.id = at.admin_id WHERE at.id = :id",
        ['id' => $id]
      );
      return $entry ?: null;
    } catch (\Throwable $e) {
      return null;
    }
  }

  /**
   * Get rollback data: the snapshot taken before a critical action.
   */
  public static function getRollbackData(int $targetId, string $model): ?array
  {
    try {
      $snapshot = db()->fetchOne(
        "SELECT * FROM forensic_snapshots
                 WHERE user_id = :tid AND trigger = 'pre_critical_change'
                 AND snapshot LIKE :model_filter
                 ORDER BY created_at DESC LIMIT 1",
        ['tid' => $targetId, 'model_filter' => '%"table":"' . addcslashes($model, '\\"%') . '"%']
      );
      if ($snapshot) {
        $snapshot['snapshot'] = json_decode($snapshot['snapshot'], true);
      }
      return $snapshot ?: null;
    } catch (\Throwable $e) {
      return null;
    }
  }

  /**
   * Get forensics summary for the dashboard.
   */
  public static function getSummary(int $hours = 24): array
  {
    try {
      $since = date('Y-m-d H:i:s', strtotime("-{$hours} hours"));

      $totalActions = db()->count('audit_trails', 'created_at > :since', ['since' => $since]);

      $byType = db()->fetchAll(
        "SELECT action_type, COUNT(*) as cnt FROM audit_trails
                 WHERE created_at > :since GROUP BY action_type ORDER BY cnt DESC",
        ['since' => $since]
      );

      $highRisk = db()->count('audit_trails', 'created_at > :since AND risk_score > 60', ['since' => $since]);

      $topAdmins = db()->fetchAll(
        "SELECT at.admin_id, u.full_name, COUNT(*) as action_count
                 FROM audit_trails at LEFT JOIN users u ON u.id = at.admin_id
                 WHERE at.created_at > :since
                 GROUP BY at.admin_id, u.full_name ORDER BY action_count DESC LIMIT 10",
        ['since' => $since]
      );

      return [
        'total_actions' => $totalActions,
        'by_type'       => $byType,
        'high_risk'     => $highRisk,
        'top_admins'    => $topAdmins,
        'period_hours'  => $hours,
      ];
    } catch (\Throwable $e) {
      return ['total_actions' => 0, 'by_type' => [], 'high_risk' => 0, 'top_admins' => [], 'period_hours' => $hours];
    }
  }

  /**
   * Purge old audit trails (keep critical for longer).
   */
  public static function purge(int $normalDays = 90, int $criticalDays = 365): array
  {
    try {
      $normalCutoff = date('Y-m-d H:i:s', strtotime("-{$normalDays} days"));
      $criticalCutoff = date('Y-m-d H:i:s', strtotime("-{$criticalDays} days"));

      $normalStmt = db()->query(
        "DELETE FROM audit_trails WHERE created_at < :cutoff AND risk_score <= 60",
        ['cutoff' => $normalCutoff]
      );
      $criticalStmt = db()->query(
        "DELETE FROM audit_trails WHERE created_at < :cutoff AND risk_score > 60",
        ['cutoff' => $criticalCutoff]
      );

      return [
        'normal_purged'   => $normalStmt ? $normalStmt->rowCount() : 0,
        'critical_purged' => $criticalStmt ? $criticalStmt->rowCount() : 0,
      ];
    } catch (\Throwable $e) {
      return ['normal_purged' => 0, 'critical_purged' => 0];
    }
  }
}
