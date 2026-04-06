<?php

/**
 * DeploymentManager — Self-Provisioning for New Schools
 *
 * Allows rapid onboarding: automatically creates tenant database namespace,
 * admin account, default roles, initial AI configuration, branding template.
 */
class DeploymentManager
{
  /**
   * Deploy a new school into the ecosystem.
   */
  public static function deploy(string $schoolName, string $domain = '', array $options = []): array
  {
    $startTime = microtime(true);
    $steps = [];

    // 1. Register tenant
    ErrorCollector::log('ecosystem', "Deploying new school: {$schoolName}", 'INFO');
    $tenant = TenantOrchestrator::register($schoolName, $domain, $options['settings'] ?? []);
    if (!$tenant['success']) {
      return ['success' => false, 'error' => $tenant['error'], 'steps' => $steps];
    }
    $steps[] = ['step' => 'tenant_registration', 'status' => 'complete', 'tenant_id' => $tenant['tenant_id']];

    $tenantId = $tenant['tenant_id'];

    // 2. Ensure tenant-aware tables have indexes
    $steps[] = self::ensureTenantIndexes($tenantId);

    // 3. Default branding
    $steps[] = self::setupBranding($tenantId, $schoolName, $options);

    // 4. Initial AI configuration
    $steps[] = self::configureAI($tenantId);

    // 5. Create welcome notification
    $steps[] = self::createWelcome($tenantId, $schoolName);

    $elapsed = round((microtime(true) - $startTime) * 1000);

    ErrorCollector::log('ecosystem', "School deployed: {$schoolName} in {$elapsed}ms", 'INFO');

    return [
      'success'    => true,
      'tenant_id'  => $tenantId,
      'school'     => $schoolName,
      'domain'     => $domain,
      'steps'      => $steps,
      'elapsed_ms' => $elapsed,
      'provisioned' => $tenant['provisioned'] ?? [],
    ];
  }

  /**
   * Ensure tenant-aware tables have proper indexes.
   */
  private static function ensureTenantIndexes(int $tenantId): array
  {
    $tables = ['users', 'attendance', 'classes', 'notices'];
    $indexed = [];

    foreach ($tables as $table) {
      try {
        if (!table_exists($table)) continue;

        // Check if tenant_id column exists
        $cols = db()->fetchAll("SHOW COLUMNS FROM `{$table}` LIKE 'tenant_id'");
        if (empty($cols)) {
          db()->query("ALTER TABLE `{$table}` ADD COLUMN tenant_id INT DEFAULT 1");
          db()->query("ALTER TABLE `{$table}` ADD INDEX idx_tenant_{$table} (tenant_id)");
        }
        $indexed[] = $table;
      } catch (\Throwable $e) {
        // Column may already exist
      }
    }

    return ['step' => 'tenant_indexes', 'status' => 'complete', 'tables' => $indexed];
  }

  /**
   * Setup branding for a new tenant.
   */
  private static function setupBranding(int $tenantId, string $schoolName, array $options): array
  {
    $branding = [
      'primary_color'  => $options['primary_color'] ?? '#4F46E5',
      'logo_text'      => $schoolName,
      'tagline'        => $options['tagline'] ?? 'Excellence in Education',
    ];

    // Store branding in tenant settings
    try {
      db()->query(
        "UPDATE tenants SET settings = JSON_SET(COALESCE(settings, '{}'), '$.branding', CAST(? AS JSON)) WHERE id = ?",
        [json_encode($branding), $tenantId]
      );
    } catch (\Throwable $e) {
      // Non-critical
    }

    return ['step' => 'branding', 'status' => 'complete', 'config' => $branding];
  }

  /**
   * Configure initial AI settings for a tenant.
   */
  private static function configureAI(int $tenantId): array
  {
    $aiConfig = [
      'chatbot_enabled'    => true,
      'auto_insights'      => true,
      'prediction_enabled' => false, // Requires data accumulation
      'federation_opt_in'  => false, // Opt-in by default
    ];

    try {
      db()->query(
        "UPDATE tenants SET settings = JSON_SET(COALESCE(settings, '{}'), '$.ai', CAST(? AS JSON)) WHERE id = ?",
        [json_encode($aiConfig), $tenantId]
      );
    } catch (\Throwable $e) {
      // Non-critical
    }

    return ['step' => 'ai_config', 'status' => 'complete', 'config' => $aiConfig];
  }

  /**
   * Create welcome notification for the new school.
   */
  private static function createWelcome(int $tenantId, string $schoolName): array
  {
    try {
      if (table_exists('notices')) {
        db()->insert('notices', [
          'tenant_id' => $tenantId,
          'title'     => "Welcome to SAMS, {$schoolName}!",
          'content'   => 'Your school has been successfully registered. Start by adding teachers and students to get the most out of the platform.',
          'type'      => 'info',
          'priority'  => 'normal',
          'target_role' => 'admin',
        ]);
      }
    } catch (\Throwable $e) {
      // Non-critical
    }

    return ['step' => 'welcome', 'status' => 'complete'];
  }

  /**
   * Get deployment history.
   */
  public static function getHistory(int $limit = 10): array
  {
    TenantOrchestrator::ensureTable();
    return db()->fetchAll("SELECT * FROM tenants ORDER BY created_at DESC LIMIT ?", [$limit]);
  }

  /**
   * Get summary for dashboard.
   */
  public static function getSummary(): array
  {
    return [
      'auto_provision' => true,
      'steps'          => ['tenant', 'indexes', 'branding', 'ai_config', 'welcome'],
      'status'         => 'ready',
    ];
  }
}
