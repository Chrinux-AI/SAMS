<?php

/**
 * TenantResolver Middleware
 *
 * Detects tenant via domain/session, injects tenant scope into queries,
 * and prevents cross-institution access.
 *
 * Include early in the request lifecycle (after config.php, before DB queries).
 */

class TenantResolver
{
  private static ?int $currentTenantId = null;
  private static bool $resolved = false;

  /**
   * Resolve the current tenant from domain or session.
   */
  public static function resolve(): int
  {
    if (self::$resolved && self::$currentTenantId !== null) {
      return self::$currentTenantId;
    }

    // 1. Check session first
    if (isset($_SESSION['tenant_id'])) {
      self::$currentTenantId = (int)$_SESSION['tenant_id'];
      self::$resolved = true;
      return self::$currentTenantId;
    }

    // 2. Resolve from domain
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $host = strtolower(preg_replace('/:\d+$/', '', $host)); // Strip port

    try {
      TenantOrchestrator::ensureTable();
      $tenant = TenantOrchestrator::getByDomain($host);
      if ($tenant) {
        self::$currentTenantId = (int)$tenant['id'];
        $_SESSION['tenant_id'] = self::$currentTenantId;
        self::$resolved = true;
        return self::$currentTenantId;
      }
    } catch (\Throwable $e) {
      // Fall through to default
    }

    // 3. Default tenant (single-school mode)
    self::$currentTenantId = 1;
    $_SESSION['tenant_id'] = 1;
    self::$resolved = true;
    return self::$currentTenantId;
  }

  /**
   * Get the current tenant ID (resolve if needed).
   */
  public static function getTenantId(): int
  {
    return self::$currentTenantId ?? self::resolve();
  }

  /**
   * Set tenant explicitly (for CLI/cron operations).
   */
  public static function setTenant(int $tenantId): void
  {
    self::$currentTenantId = $tenantId;
    self::$resolved = true;
  }

  /**
   * Inject tenant scope into a WHERE clause.
   */
  public static function scopeWhere(string $tableAlias = ''): string
  {
    $prefix = $tableAlias ? "{$tableAlias}." : '';
    return "{$prefix}tenant_id = " . self::getTenantId();
  }

  /**
   * Validate that a record belongs to the current tenant.
   */
  public static function validateAccess(string $table, int $recordId): bool
  {
    try {
      $row = db()->fetchOne(
        "SELECT tenant_id FROM `{$table}` WHERE id = ?",
        [$recordId]
      );
      if (!$row) return false;
      return (int)$row['tenant_id'] === self::getTenantId();
    } catch (\Throwable $e) {
      return false;
    }
  }

  /**
   * Prevent cross-tenant access — throws exception on violation.
   */
  public static function enforce(string $table, int $recordId): void
  {
    if (!self::validateAccess($table, $recordId)) {
      ErrorCollector::log('ecosystem', "Cross-tenant access blocked: {$table}#{$recordId}", 'HIGH');
      throw new RuntimeException('Access denied: cross-institution access is not permitted.');
    }
  }

  /**
   * Get tenant data for the current tenant.
   */
  public static function getCurrentTenant(): ?array
  {
    return TenantOrchestrator::getTenant(self::getTenantId());
  }

  /**
   * Reset resolver (for testing).
   */
  public static function reset(): void
  {
    self::$currentTenantId = null;
    self::$resolved = false;
  }
}
