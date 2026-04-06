<?php

/**
 * Updates Service — Manages the public/role-based updates feed.
 *
 * Visibility levels:
 *  - 'public'        → Visible to everyone (no auth needed)
 *  - 'all_roles'     → Visible to all authenticated users
 *  - 'internal_only' → Visible only to admin/staff roles
 *  - 'role_specific' → Visible only to specified target_role
 */
class UpdatesService
{
  /** Visibility levels */
  public const VIS_PUBLIC        = 'public';
  public const VIS_ALL_ROLES     = 'all_roles';
  public const VIS_INTERNAL      = 'internal_only';
  public const VIS_ROLE_SPECIFIC = 'role_specific';

  /** Staff roles that can see internal_only updates */
  private const STAFF_ROLES = ['admin', 'teacher', 'bursar', 'accountant', 'librarian', 'transport', 'forum_moderator'];

  /**
   * Create a new update/notice.
   *
   * @return int|false  Update ID or false
   */
  public static function create(array $data): int|false
  {
    try {
      $id = db()->insert('notices', [
        'title'        => mb_substr($data['title'] ?? '', 0, 255),
        'content'      => $data['content'] ?? '',
        'category'     => $data['category'] ?? 'general',
        'visibility'   => $data['visibility'] ?? self::VIS_ALL_ROLES,
        'target_role'  => $data['target_role'] ?? null,
        'priority'     => $data['priority'] ?? 'normal',
        'is_pinned'    => $data['is_pinned'] ?? 0,
        'scheduled_at' => $data['scheduled_at'] ?? null,
        'expires_at'   => $data['expires_at'] ?? null,
        'created_by'   => $data['created_by'] ?? ($_SESSION['user_id'] ?? null),
        'created_at'   => date('Y-m-d H:i:s'),
      ]);

      if ($id && class_exists('EventDispatcher')) {
        EventDispatcher::dispatch('NoticePosted', [
          'notice_id'  => $id,
          'title'      => $data['title'] ?? '',
          'visibility' => $data['visibility'] ?? self::VIS_ALL_ROLES,
          'target_role' => $data['target_role'] ?? null,
        ]);
      }

      return $id;
    } catch (\Throwable $e) {
      error_log("UpdatesService::create failed: " . $e->getMessage());
      return false;
    }
  }

  /**
   * Get updates visible to the current user (handles auth & visibility).
   *
   * @param string|null $category  Filter by category (null = all)
   * @param int         $limit     Max results
   * @param int         $offset    Pagination offset
   * @return array
   */
  public static function getVisible(?string $category = null, int $limit = 20, int $offset = 0): array
  {
    $userId = $_SESSION['user_id'] ?? null;
    $role = $_SESSION['role'] ?? $_SESSION['user_role'] ?? null;
    $now = date('Y-m-d H:i:s');

    $conditions = [];
    $params = [];

    // Base: not expired, not scheduled in future
    $conditions[] = "(expires_at IS NULL OR expires_at > :now1)";
    $conditions[] = "(scheduled_at IS NULL OR scheduled_at <= :now2)";
    $params['now1'] = $now;
    $params['now2'] = $now;

    // Visibility logic
    if ($userId === null) {
      // Public users: only public updates
      $conditions[] = "visibility = 'public'";
    } elseif ($role === 'admin') {
      // Admin sees everything
    } elseif (in_array($role, self::STAFF_ROLES, true)) {
      // Staff: public + all_roles + internal_only + their role
      $conditions[] = "(visibility IN ('public', 'all_roles', 'internal_only') OR (visibility = 'role_specific' AND target_role = :role))";
      $params['role'] = $role;
    } else {
      // Students, parents: public + all_roles + their role
      $conditions[] = "(visibility IN ('public', 'all_roles') OR (visibility = 'role_specific' AND target_role = :role))";
      $params['role'] = $role;
    }

    // Category filter
    if ($category !== null && $category !== 'all' && $category !== '') {
      $conditions[] = "category = :cat";
      $params['cat'] = $category;
    }

    $whereClause = implode(' AND ', $conditions);

    try {
      return db()->fetchAll(
        "SELECT n.*, u.full_name as author_name
                 FROM notices n
                 LEFT JOIN users u ON u.id = n.created_by
                 WHERE {$whereClause}
                 ORDER BY n.is_pinned DESC, n.priority DESC, n.created_at DESC
                 LIMIT {$limit} OFFSET {$offset}",
        $params
      );
    } catch (\Throwable $e) {
      error_log("UpdatesService::getVisible failed: " . $e->getMessage());
      return [];
    }
  }

  /**
   * Count visible updates (for pagination).
   */
  public static function countVisible(?string $category = null): int
  {
    $userId = $_SESSION['user_id'] ?? null;
    $role = $_SESSION['role'] ?? $_SESSION['user_role'] ?? null;
    $now = date('Y-m-d H:i:s');

    $conditions = ["(expires_at IS NULL OR expires_at > :now1)", "(scheduled_at IS NULL OR scheduled_at <= :now2)"];
    $params = ['now1' => $now, 'now2' => $now];

    if ($userId === null) {
      $conditions[] = "visibility = 'public'";
    } elseif ($role !== 'admin') {
      $staffRoles = in_array($role, self::STAFF_ROLES, true);
      if ($staffRoles) {
        $conditions[] = "(visibility IN ('public', 'all_roles', 'internal_only') OR (visibility = 'role_specific' AND target_role = :role))";
      } else {
        $conditions[] = "(visibility IN ('public', 'all_roles') OR (visibility = 'role_specific' AND target_role = :role))";
      }
      $params['role'] = $role;
    }

    if ($category !== null && $category !== 'all' && $category !== '') {
      $conditions[] = "category = :cat";
      $params['cat'] = $category;
    }

    $whereClause = implode(' AND ', $conditions);

    try {
      $result = db()->fetchOne("SELECT COUNT(*) as cnt FROM notices WHERE {$whereClause}", $params);
      return (int) ($result['cnt'] ?? 0);
    } catch (\Throwable $e) {
      return 0;
    }
  }

  /**
   * Get a single update by ID (with visibility check).
   */
  public static function getById(int $id): ?array
  {
    try {
      return db()->fetchOne(
        "SELECT n.*, u.full_name as author_name
                 FROM notices n LEFT JOIN users u ON u.id = n.created_by
                 WHERE n.id = :id",
        ['id' => $id]
      ) ?: null;
    } catch (\Throwable $e) {
      return null;
    }
  }

  /**
   * Update an existing notice.
   */
  public static function update(int $id, array $data): bool
  {
    $allowed = ['title', 'content', 'category', 'visibility', 'target_role', 'priority', 'is_pinned', 'scheduled_at', 'expires_at'];
    $clean = [];
    foreach ($allowed as $field) {
      if (array_key_exists($field, $data)) {
        $clean[$field] = $data[$field];
      }
    }

    if (empty($clean)) {
      return false;
    }

    $clean['updated_at'] = date('Y-m-d H:i:s');

    try {
      return db()->update('notices', $clean, 'id = :id', ['id' => $id]);
    } catch (\Throwable $e) {
      return false;
    }
  }

  /**
   * Delete a notice.
   */
  public static function remove(int $id): bool
  {
    try {
      return db()->delete('notices', 'id = :id', ['id' => $id]);
    } catch (\Throwable $e) {
      return false;
    }
  }
}
