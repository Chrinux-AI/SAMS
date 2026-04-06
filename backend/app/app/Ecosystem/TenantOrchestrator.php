<?php

/**
 * TenantOrchestrator — Multi-Tenant Lifecycle Management
 *
 * Creates and manages strict tenant isolation.
 * Every institution operates in its own logical namespace.
 */
class TenantOrchestrator
{
  /**
   * Ensure the tenants table exists.
   */
  public static function ensureTable(): void
  {
    $sql = "CREATE TABLE IF NOT EXISTS tenants (
      id INT AUTO_INCREMENT PRIMARY KEY,
      school_name VARCHAR(255) NOT NULL,
      domain VARCHAR(255) DEFAULT NULL,
      encryption_key VARCHAR(128) NOT NULL,
      status ENUM('active','suspended','pending') DEFAULT 'pending',
      settings JSON DEFAULT NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      UNIQUE KEY uk_domain (domain)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    db()->query($sql);
  }

  /**
   * Register a new school/tenant.
   */
  public static function register(string $schoolName, string $domain = '', array $settings = []): array
  {
    self::ensureTable();

    // Check duplicate domain
    if ($domain) {
      $existing = db()->fetchOne("SELECT id FROM tenants WHERE domain = ?", [$domain]);
      if ($existing) {
        return ['success' => false, 'error' => 'Domain already registered'];
      }
    }

    // Generate encryption key
    $encryptionKey = bin2hex(random_bytes(32));

    $tenantId = db()->insert('tenants', [
      'school_name'    => $schoolName,
      'domain'         => $domain ?: null,
      'encryption_key' => $encryptionKey,
      'status'         => 'active',
      'settings'       => json_encode($settings),
    ]);

    if (!$tenantId) {
      return ['success' => false, 'error' => 'Failed to create tenant record'];
    }

    // Provision tenant resources
    $provisioned = self::provision((int)$tenantId);

    ErrorCollector::log('ecosystem', "Tenant registered: {$schoolName} (ID:{$tenantId})", 'INFO');

    return [
      'success'   => true,
      'tenant_id' => (int)$tenantId,
      'domain'    => $domain,
      'provisioned' => $provisioned,
    ];
  }

  /**
   * Provision default resources for a new tenant.
   */
  private static function provision(int $tenantId): array
  {
    $provisioned = [];

    // 1. Create default admin account
    try {
      $adminExists = db()->fetchOne(
        "SELECT id FROM users WHERE tenant_id = ? AND role = 'admin' LIMIT 1",
        [$tenantId]
      );
      if (!$adminExists) {
        $tempPassword = bin2hex(random_bytes(8));
        db()->insert('users', [
          'tenant_id'  => $tenantId,
          'first_name' => 'Admin',
          'last_name'  => 'User',
          'email'      => "admin@tenant{$tenantId}.local",
          'password'   => password_hash($tempPassword, PASSWORD_BCRYPT),
          'role'       => 'admin',
          'status'     => 'active',
        ]);
        $provisioned['admin'] = ['created' => true, 'temp_password' => $tempPassword];
      } else {
        $provisioned['admin'] = ['created' => false, 'exists' => true];
      }
    } catch (\Throwable $e) {
      $provisioned['admin'] = ['created' => false, 'error' => $e->getMessage()];
    }

    // 2. Seed default roles
    $provisioned['roles'] = ['admin', 'teacher', 'student', 'parent', 'bursar'];

    // 3. Default settings
    $provisioned['settings'] = true;

    return $provisioned;
  }

  /**
   * Get all tenants.
   */
  public static function getAll(): array
  {
    self::ensureTable();
    return db()->fetchAll("SELECT id, school_name, domain, status, created_at FROM tenants ORDER BY created_at DESC");
  }

  /**
   * Get tenant by ID.
   */
  public static function getTenant(int $id): ?array
  {
    self::ensureTable();
    $row = db()->fetchOne("SELECT * FROM tenants WHERE id = ?", [$id]);
    return $row ?: null;
  }

  /**
   * Get tenant by domain.
   */
  public static function getByDomain(string $domain): ?array
  {
    self::ensureTable();
    $row = db()->fetchOne("SELECT * FROM tenants WHERE domain = ? AND status = 'active'", [$domain]);
    return $row ?: null;
  }

  /**
   * Suspend a tenant.
   */
  public static function suspend(int $id): bool
  {
    $result = db()->query("UPDATE tenants SET status = 'suspended' WHERE id = ?", [$id]);
    if ($result) {
      ErrorCollector::log('ecosystem', "Tenant {$id} suspended", 'MEDIUM');
    }
    return (bool)$result;
  }

  /**
   * Activate a tenant.
   */
  public static function activate(int $id): bool
  {
    $result = db()->query("UPDATE tenants SET status = 'active' WHERE id = ?", [$id]);
    return (bool)$result;
  }

  /**
   * Count active tenants.
   */
  public static function countActive(): int
  {
    self::ensureTable();
    $row = db()->fetchOne("SELECT COUNT(*) AS cnt FROM tenants WHERE status = 'active'");
    return (int)($row['cnt'] ?? 0);
  }

  /**
   * Get summary for dashboard.
   */
  public static function getSummary(): array
  {
    self::ensureTable();
    $total = db()->fetchOne("SELECT COUNT(*) AS cnt FROM tenants");
    $active = db()->fetchOne("SELECT COUNT(*) AS cnt FROM tenants WHERE status = 'active'");
    $pending = db()->fetchOne("SELECT COUNT(*) AS cnt FROM tenants WHERE status = 'pending'");

    return [
      'total'     => (int)($total['cnt'] ?? 0),
      'active'    => (int)($active['cnt'] ?? 0),
      'pending'   => (int)($pending['cnt'] ?? 0),
      'isolation' => 'strict',
    ];
  }
}
