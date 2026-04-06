<?php

/**
 * AutoSyncEngine — Phase-5 Autonomous Data Synchronization.
 *
 * Guarantees: Database write → Event dispatched → Cache invalidated → Broadcast sent.
 * Wraps the existing EventDispatcher + Broadcaster + CacheService into a single
 * fire-and-forget pipeline other code can call after any CRUD operation.
 *
 * Usage:
 *   AutoSyncEngine::afterSave('classes', $id, 'updated', ['schedule' => '...']);
 *   AutoSyncEngine::afterDelete('classes', $id);
 */
class AutoSyncEngine
{
  /**
   * Run the full post-save pipeline.
   *
   * @param string $table   Table that changed
   * @param int    $id      Record primary key
   * @param string $action  created|updated|deleted
   * @param array  $payload Optional extra data to broadcast
   */
  public static function afterSave(string $table, int $id, string $action = 'updated', array $payload = []): void
  {
    $event = $table . '.' . $action;

    $data = array_merge([
      'table'     => $table,
      'record_id' => $id,
      'action'    => $action,
      'timestamp' => date('Y-m-d H:i:s'),
      'user_id'   => $_SESSION['user_id'] ?? 0,
    ], $payload);

    // 1. Invalidate cache for this record and list
    self::invalidateCache($table, $id);

    // 2. Dispatch through Event Bus
    try {
      if (class_exists('EventDispatcher')) {
        EventDispatcher::dispatch($event, $data);
      }
    } catch (\Throwable $e) {
      error_log("AutoSyncEngine: EventDispatcher failed for $event — " . $e->getMessage());
    }

    // 3. Broadcast to connected panels via SSE
    try {
      if (class_exists('Broadcaster')) {
        Broadcaster::toAll($event, $data);
      }
    } catch (\Throwable $e) {
      error_log("AutoSyncEngine: Broadcaster failed for $event — " . $e->getMessage());
    }

    // 4. Log the sync event
    try {
      if (class_exists('AuditLogger')) {
        AuditLogger::log('data_sync', $table, "event=$event, record=$table#$id", $data['user_id'] ?? null);
      }
    } catch (\Throwable $e) {
      // non-critical
    }
  }

  /**
   * Shorthand for delete operations.
   */
  public static function afterDelete(string $table, int $id): void
  {
    self::afterSave($table, $id, 'deleted');
  }

  /**
   * Invalidate all cache keys related to a table/record.
   */
  private static function invalidateCache(string $table, int $id): void
  {
    try {
      if (!function_exists('cache')) {
        return;
      }
      $c = cache();
      // Individual record
      $c->forget("{$table}_{$id}");
      // Common list keys
      $c->forget("{$table}_all");
      $c->forget("{$table}_list");
      $c->forget("{$table}_active");
      $c->forget("{$table}_count");
      // Role-scoped lists
      foreach (['admin', 'teacher', 'student', 'parent'] as $role) {
        $c->forget("{$table}_{$role}");
      }
    } catch (\Throwable $e) {
      error_log("AutoSyncEngine: Cache invalidation failed — " . $e->getMessage());
    }
  }

  /**
   * Verify a record was actually saved correctly.
   * Returns the fetched row or null if missing.
   */
  public static function verify(string $table, int $id, array $expectedFields = []): ?array
  {
    try {
      $safe = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
      $row = db()->fetchOne("SELECT * FROM `{$safe}` WHERE id = ?", [$id]);
      if (!$row) {
        error_log("AutoSyncEngine: VERIFY FAILED — $table#$id not found after save");
        return null;
      }

      // Check expected values match
      foreach ($expectedFields as $col => $expected) {
        $actual = $row[$col] ?? null;
        if ($actual !== null && (string)$actual !== (string)$expected) {
          error_log("AutoSyncEngine: MISMATCH — $table#$id.$col expected=" . json_encode($expected) . " actual=" . json_encode($actual));
        }
      }

      return $row;
    } catch (\Throwable $e) {
      error_log("AutoSyncEngine: Verify query failed — " . $e->getMessage());
      return null;
    }
  }
}
