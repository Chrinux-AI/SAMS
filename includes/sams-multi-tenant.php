<?php
/**
 * Multi-Tenant Architecture Manager
 * Handles multiple schools/institutions in a single system
 */

class SAMS_MultiTenant {
    private $tenant_id;
    private $tenant_config;
    private $db_connection;

    public function __construct($tenant_identifier = null) {
        $this->tenant_id = $this->resolveTenant($tenant_identifier);
        $this->tenant_config = $this->loadTenantConfig();
        $this->db_connection = $this->establishTenantConnection();
    }

    /**
     * Resolve tenant from domain, subdomain, or user session
     */
    private function resolveTenant($identifier) {
        // Method 1: Subdomain-based (school1.sams.com, school2.sams.com)
        if (isset($_SERVER['HTTP_HOST'])) {
            $host_parts = explode('.', $_SERVER['HTTP_HOST']);
            if (count($host_parts) > 2) {
                $subdomain = $host_parts[0];
                $tenant = $this->getTenantBySubdomain($subdomain);
                if ($tenant) return $tenant['id'];
            }
        }

        // Method 2: Custom domain mapping
        $custom_domain = $this->getTenantByDomain($_SERVER['HTTP_HOST'] ?? '');
        if ($custom_domain) return $custom_domain['id'];

        // Method 3: Session-based (for admin panel)
        if (isset($_SESSION['admin_tenant_id'])) {
            return $_SESSION['admin_tenant_id'];
        }

        // Method 4: URL parameter (for development/testing)
        if (isset($_GET['tenant'])) {
            $tenant = $this->getTenantByIdentifier($_GET['tenant']);
            if ($tenant) return $tenant['id'];
        }

        // Method 5: Default tenant
        return $this->getDefaultTenant();
    }

    /**
     * Load tenant-specific configuration
     */
    private function loadTenantConfig() {
        try {
            $config = db()->fetchOne(
                "SELECT * FROM tenant_configurations WHERE tenant_id = ?",
                [$this->tenant_id]
            );

            if (!$config) {
                throw new Exception("Tenant configuration not found");
            }

            return [
                'tenant_id' => $config['tenant_id'],
                'name' => $config['institution_name'],
                'domain' => $config['custom_domain'],
                'subdomain' => $config['subdomain'],
                'theme' => json_decode($config['theme_config'], true),
                'features' => json_decode($config['enabled_features'], true),
                'limits' => json_decode($config['usage_limits'], true),
                'settings' => json_decode($config['custom_settings'], true),
                'ai_config' => json_decode($config['ai_configuration'], true),
                'security_config' => json_decode($config['security_settings'], true)
            ];
        } catch (Exception $e) {
            // Return default configuration
            return $this->getDefaultTenantConfig();
        }
    }

    /**
     * Establish database connection for tenant
     */
    private function establishTenantConnection() {
        try {
            // Check if tenant uses separate database
            if ($this->tenant_config['settings']['separate_database'] ?? false) {
                return $this->connectToTenantDatabase();
            } else {
                // Use shared database with tenant isolation
                return $this->connectToSharedDatabase();
            }
        } catch (Exception $e) {
            error_log("Tenant connection failed: " . $e->getMessage());
            return $this->connectToSharedDatabase();
        }
    }

    /**
     * Get tenant-specific database connection
     */
    private function connectToTenantDatabase() {
        $db_config = $this->tenant_config['settings']['database'];

        $dsn = "mysql:host={$db_config['host']};dbname={$db_config['name']};charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        return new PDO($dsn, $db_config['username'], $db_config['password'], $options);
    }

    /**
     * Connect to shared database with tenant isolation
     */
    private function connectToSharedDatabase() {
        // Use main database but ensure all queries include tenant_id
        return db()->getConnection();
    }

    /**
     * Create new tenant/school
     */
    public function createTenant($tenant_data) {
        try {
            // Validate tenant data
            $this->validateTenantData($tenant_data);

            // Generate unique tenant identifier
            $tenant_id = $this->generateTenantId();
            $subdomain = $this->generateSubdomain($tenant_data['institution_name']);

            // Start transaction
            db()->query("START TRANSACTION");

            // Create tenant record
            $tenant_record = [
                'id' => $tenant_id,
                'institution_name' => $tenant_data['institution_name'],
                'subdomain' => $subdomain,
                'custom_domain' => $tenant_data['custom_domain'] ?? null,
                'admin_email' => $tenant_data['admin_email'],
                'status' => 'setup',
                'created_at' => date('Y-m-d H:i:s'),
                'plan_type' => $tenant_data['plan_type'] ?? 'basic'
            ];

            db()->insert('tenants', $tenant_record);

            // Create tenant configuration
            $config = $this->createDefaultTenantConfig($tenant_id, $tenant_data);
            db()->insert('tenant_configurations', $config);

            // Create tenant database (if required)
            if ($tenant_data['separate_database'] ?? false) {
                $this->createTenantDatabase($tenant_id, $subdomain);
            }

            // Initialize tenant data
            $this->initializeTenantData($tenant_id);

            // Send setup email
            $this->sendTenantSetupEmail($tenant_data['admin_email'], $subdomain);

            db()->query("COMMIT");

            return [
                'success' => true,
                'tenant_id' => $tenant_id,
                'subdomain' => $subdomain,
                'setup_url' => "https://{$subdomain}.sams.com/setup"
            ];

        } catch (Exception $e) {
            db()->query("ROLLBACK");
            error_log("Tenant creation failed: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Initialize tenant with default data
     */
    private function initializeTenantData($tenant_id) {
        // Create default admin user
        $admin_data = [
            'tenant_id' => $tenant_id,
            'email' => $this->tenant_config['admin_email'],
            'role' => 'tenant_admin',
            'status' => 'pending_setup',
            'created_at' => date('Y-m-d H:i:s')
        ];

        $this->db_connection->insert('users', $admin_data);

        // Create default academic structure
        $this->createDefaultAcademicStructure($tenant_id);

        // Setup default AI configuration
        $this->setupTenantAI($tenant_id);
    }

    /**
     * Create default academic structure for tenant
     */
    private function createDefaultAcademicStructure($tenant_id) {
        // Default grade levels
        $grade_levels = [
            ['name' => 'Grade 1', 'level' => 1, 'tenant_id' => $tenant_id],
            ['name' => 'Grade 2', 'level' => 2, 'tenant_id' => $tenant_id],
            ['name' => 'Grade 3', 'level' => 3, 'tenant_id' => $tenant_id],
            // Add more as needed
        ];

        foreach ($grade_levels as $grade) {
            $this->db_connection->insert('grade_levels', $grade);
        }

        // Default subjects
        $subjects = [
            ['name' => 'Mathematics', 'code' => 'MATH', 'tenant_id' => $tenant_id],
            ['name' => 'English', 'code' => 'ENG', 'tenant_id' => $tenant_id],
            ['name' => 'Science', 'code' => 'SCI', 'tenant_id' => $tenant_id],
            // Add more as needed
        ];

        foreach ($subjects as $subject) {
            $this->db_connection->insert('subjects', $subject);
        }
    }

    /**
     * Setup tenant-specific AI configuration
     */
    private function setupTenantAI($tenant_id) {
        $ai_config = [
            'tenant_id' => $tenant_id,
            'learning_style' => 'adaptive',
            'security_level' => 'balanced',
            'features_enabled' => ['chatbot', 'navigation', 'learning'],
            'custom_training_data' => null,
            'privacy_settings' => ['data_anonymization' => true, 'behavioral_analysis' => true],
            'created_at' => date('Y-m-d H:i:s')
        ];

        $this->db_connection->insert('tenant_ai_config', $ai_config);
    }

    /**
     * Get tenant statistics and analytics
     */
    public function getTenantAnalytics() {
        try {
            $stats = [
                'users' => $this->getUserStatistics(),
                'attendance' => $this->getAttendanceStatistics(),
                'academic' => $this->getAcademicStatistics(),
                'system_usage' => $this->getSystemUsageStatistics(),
                'ai_usage' => $this->getAIUsageStatistics()
            ];

            return $stats;
        } catch (Exception $e) {
            error_log("Analytics fetch failed: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Switch tenant context (for super-admin)
     */
    public function switchTenant($tenant_id) {
        if (!$this->isSuperAdmin()) {
            throw new Exception("Unauthorized to switch tenants");
        }

        $tenant = $this->getTenantById($tenant_id);
        if (!$tenant) {
            throw new Exception("Tenant not found");
        }

        $_SESSION['admin_tenant_id'] = $tenant_id;
        $_SESSION['admin_tenant_name'] = $tenant['institution_name'];

        return true;
    }

    /**
     * Get all tenants (for super-admin)
     */
    public function getAllTenants() {
        if (!$this->isSuperAdmin()) {
            throw new Exception("Unauthorized");
        }

        return db()->fetchAll(
            "SELECT * FROM tenants ORDER BY created_at DESC"
        );
    }

    // Helper methods
    public function getTenantId() {
        return $this->tenant_id;
    }

    public function getTenantConfig() {
        return $this->tenant_config;
    }

    public function getDatabaseConnection() {
        return $this->db_connection;
    }

    private function getTenantBySubdomain($subdomain) {
        return db()->fetchOne(
            "SELECT * FROM tenants WHERE subdomain = ? AND status = 'active'",
            [$subdomain]
        );
    }

    private function getTenantByDomain($domain) {
        return db()->fetchOne(
            "SELECT * FROM tenants WHERE custom_domain = ? AND status = 'active'",
            [$domain]
        );
    }

    private function getTenantByIdentifier($identifier) {
        return db()->fetchOne(
            "SELECT * FROM tenants WHERE id = ? OR subdomain = ?",
            [$identifier, $identifier]
        );
    }

    private function getDefaultTenant() {
        $tenant = db()->fetchOne(
            "SELECT * FROM tenants WHERE is_default = 1 AND status = 'active'"
        );
        return $tenant ? $tenant['id'] : 1;
    }

    private function getDefaultTenantConfig() {
        return [
            'tenant_id' => 1,
            'name' => 'Default School',
            'domain' => null,
            'subdomain' => 'default',
            'theme' => $this->getDefaultTheme(),
            'features' => $this->getDefaultFeatures(),
            'limits' => $this->getDefaultLimits(),
            'settings' => $this->getDefaultSettings(),
            'ai_config' => $this->getDefaultAIConfig(),
            'security_config' => $this->getDefaultSecurityConfig()
        ];
    }

    private function generateTenantId() {
        return 'tenant_' . uniqid() . '_' . time();
    }

    private function generateSubdomain($institution_name) {
        $base = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $institution_name));
        $subdomain = $base;
        $counter = 1;

        while ($this->getTenantBySubdomain($subdomain)) {
            $subdomain = $base . $counter;
            $counter++;
        }

        return $subdomain;
    }

    private function validateTenantData($data) {
        $required = ['institution_name', 'admin_email'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("Field {$field} is required");
            }
        }

        if (!filter_var($data['admin_email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid email address");
        }
    }

    private function createDefaultTenantConfig($tenant_id, $tenant_data) {
        return [
            'tenant_id' => $tenant_id,
            'theme_config' => json_encode($this->getDefaultTheme()),
            'enabled_features' => json_encode($this->getDefaultFeatures()),
            'usage_limits' => json_encode($this->getDefaultLimits()),
            'custom_settings' => json_encode($this->getDefaultSettings()),
            'ai_configuration' => json_encode($this->getDefaultAIConfig()),
            'security_settings' => json_encode($this->getDefaultSecurityConfig()),
            'created_at' => date('Y-m-d H:i:s')
        ];
    }

    private function isSuperAdmin() {
        $role = $_SESSION['role'] ?? $_SESSION['user_role'] ?? '';
        return in_array($role, ['admin', 'super_admin', 'superadmin', 'owner']);
    }

    // Default configuration methods
    private function getDefaultTheme() { return ['primary_color' => '#4F46E5', 'logo' => null]; }
    private function getDefaultFeatures() { return ['attendance', 'grades', 'messaging', 'ai_assistant']; }
    private function getDefaultLimits() { return ['users' => 100, 'storage' => '1GB']; }
    private function getDefaultSettings() { return ['separate_database' => false, 'timezone' => 'UTC']; }
    private function getDefaultAIConfig() { return ['enabled' => true, 'level' => 'standard']; }
    private function getDefaultSecurityConfig() { return ['level' => 'balanced', '2fa_required' => false]; }

    // Statistics methods
    private function getUserStatistics() { return []; }
    private function getAttendanceStatistics() { return []; }
    private function getAcademicStatistics() { return []; }
    private function getSystemUsageStatistics() { return []; }
    private function getAIUsageStatistics() { return []; }

    private function createTenantDatabase($tenant_id, $subdomain) {
        // Implementation for creating separate tenant database
        // This would create a new database and run migrations
        return true;
    }

    private function sendTenantSetupEmail($admin_email, $subdomain) {
        // Implementation for sending setup email
        return true;
    }

    private function getTenantById($tenant_id) {
        return db()->fetchOne("SELECT * FROM tenants WHERE id = ?", [$tenant_id]);
    }
}
?>
