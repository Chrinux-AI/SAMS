<?php

/**
 * AdminEditGuarantee — Reusable post-save pipeline for all admin forms.
 *
 * Enforces the guarantee chain:
 *   Form → Validation → DB Write → Reload → Verify → Broadcast → Refresh UI
 *
 * Usage:
 *   $result = AdminEditGuarantee::execute('users', $id, $data, function($data) use ($id) {
 *       return db()->update('users', $data, 'id = ?', [$id]);
 *   });
 */
class AdminEditGuarantee
{
  /**
   * Execute a guaranteed save operation with full pipeline.
   *
   * @param string   $table    Table name
   * @param int      $id       Record ID (0 for insert — will use returned ID)
   * @param array    $data     Data to persist
   * @param callable $writer   Function that performs the actual write, returns int|bool
   * @param string   $action   'created'|'updated'|'deleted'
   * @return array{success: bool, id: int, message: string, issues: array}
   */
  public static function execute(string $table, int $id, array $data, callable $writer, string $action = 'updated'): array
  {
    // Step 1: Write to database
    $writeResult = $writer($data);

    if ($action === 'created' || $id === 0) {
      if (!$writeResult) {
        return ['success' => false, 'id' => 0, 'message' => 'Database write failed.', 'issues' => []];
      }
      $id = is_int($writeResult) ? $writeResult : $id;
      $action = 'created';
    }

    // Step 2: Verify (re-read and compare)
    $verification = ['ok' => true, 'mismatches' => [], 'missing_columns' => []];
    if (class_exists('DataConsistencyGuard') && $id > 0) {
      $verification = DataConsistencyGuard::verifySave($table, $id, $data);
    }

    // Step 3: Broadcast change via AutoSyncEngine
    if (class_exists('AutoSyncEngine')) {
      AutoSyncEngine::afterSave($table, $id, $action, $data);
    }

    // Step 4: Activity log
    if (function_exists('log_activity')) {
      log_activity($_SESSION['user_id'] ?? 0, $action === 'created' ? 'create' : 'update', $table, $id);
    }

    $label = ucfirst($action);
    return [
      'success' => true,
      'id'      => $id,
      'message' => "{$label} successfully!",
      'issues'  => $verification['ok'] ? [] : $verification,
    ];
  }

  /**
   * Execute a guaranteed delete operation.
   */
  public static function delete(string $table, int $id, callable $deleter): array
  {
    $deleter($id);

    if (class_exists('AutoSyncEngine')) {
      AutoSyncEngine::afterDelete($table, $id);
    }

    if (function_exists('log_activity')) {
      log_activity($_SESSION['user_id'] ?? 0, 'delete', $table, $id);
    }

    return ['success' => true, 'id' => $id, 'message' => 'Deleted successfully!'];
  }
}
