<?php
/**
 * Multi-Tenant Service
 * Handles tenant isolation, management, and configuration
 */

class SAMS_TenantService extends SAMS_BaseService {
    
    private $currentTenantId = null;
    private $currentTenantConfig = null;
    
    public function __construct($container) {
        parent::__construct($container);
        $this->resolveCurrentTenant();
    }
    
    /**
     * Resolve current tenant from request context
     */
    private function resolveCurrentTenant() {
        // Priority 1: Subdomain (school1.sams.com)
        if (isset($_SERVER['HTTP_HOST'])) {
            $hostParts = explode('.', $_SERVER['HTTP_HOST']);
            if (count($hostParts) > 2) {
                $subdomain = $hostParts[0];
                $tenant = $this->getTenantBySubdomain($subdomain);
                if ($tenant) {
                    $this->currentTenantId = $tenant['id'];
                    $this->currentTenantConfig = $tenant;
                    return;
                }
            }
        }
        
        // Priority 2: Custom domain mapping
        if (isset($_SERVER['HTTP_HOST'])) {
            $tenant = $this->getTenantByDomain($_SERVER['HTTP_HOST']);
            if ($tenant) {
                $this->currentTenantId = $tenant['id'];
                $this->currentTenantConfig = $tenant;
                return;
            }
        }
        
        // Priority 3: Session-based (admin context)
        if (isset($_SESSION['tenant_id'])) {
            $this->currentTenantId = (int)$_SESSION['tenant_id'];
            $this->currentTenantConfig = $this->getTenantById($this->currentTenantId);
            return;
        }
        
        // Priority 4: URL parameter (dev/testing)
        if (isset($_GET['tenant_id'])) {
            $this->currentTenantId = (int)$_GET['tenant_id'];
            $this->currentTenantConfig = $this->getTenantById($this->currentTenantId);
            return;
        }
        
        // Default tenant
        $this->currentTenantId = 1;
        $this->currentTenantConfig = $this->getTenantById(1);
    }
    
    /**
     * Get current tenant ID
     */
    public function getCurrentTenantId() {
        return $this->currentTenantId;
    }
    
    /**
     * Get current tenant configuration
     */
    public function getCurrentTenant() {
        return $this->currentTenantConfig;
    }
    
    /**
     * Get tenant by ID
     */
    public function getTenantById($id) {
        $id = (int)$id;
        
        $result = $this->db->query("SELECT * FROM tenants WHERE id = $id AND status = 'active' LIMIT 1");
        
        if ($result && mysqli_num_rows($result) > 0) {
            return mysqli_fetch_assoc($result);
        }
        
        return null;
    }
    
    /**
     * Get tenant by subdomain
     */
    public function getTenantBySubdomain($subdomain) {
        $subdomain = mysqli_real_escape_string($this->db, strtolower(trim($subdomain)));
        
        $result = $this->db->query("SELECT * FROM tenants WHERE subdomain = '$subdomain' AND status = 'active' LIMIT 1");
        
        if ($result && mysqli_num_rows($result) > 0) {
            return mysqli_fetch_assoc($result);
        }
        
        return null;
    }
    
    /**
     * Get tenant by custom domain
     */
    public function getTenantByDomain($domain) {
        $domain = mysqli_real_escape_string($this->db, strtolower(trim($domain)));
        
        $result = $this->db->query("SELECT * FROM tenants WHERE custom_domain = '$domain' AND status = 'active' LIMIT 1");
        
        if ($result && mysqli_num_rows($result) > 0) {
            return mysqli_fetch_assoc($result);
        }
        
        return null;
    }
    
    /**
     * Create new tenant
     */
    public function createTenant($data) {
        return $this->transactional(function() use ($data) {
            // Validate
            if (empty($data['name'])) {
                return ['success' => false, 'error' => 'Tenant name is required'];
            }
            
            // Check subdomain uniqueness
            if (!empty($data['subdomain'])) {
                if ($this->getTenantBySubdomain($data['subdomain'])) {
                    return ['success' => false, 'error' => 'Subdomain already in use'];
                }
            }
            
            // Check domain uniqueness
            if (!empty($data['custom_domain'])) {
                if ($this->getTenantByDomain($data['custom_domain'])) {
                    return ['success' => false, 'error' => 'Custom domain already in use'];
                }
            }
            
            // Build settings JSON
            $settings = json_encode([
                'theme' => $data['theme'] ?? 'default',
                'timezone' => $data['timezone'] ?? 'UTC',
                'date_format' => $data['date_format'] ?? 'Y-m-d',
                'language' => $data['language'] ?? 'en',
                'features' => $data['features'] ?? []
            ]);
            
            $name = mysqli_real_escape_string($this->db, $data['name']);
            $subdomain = !empty($data['subdomain']) ? mysqli_real_escape_string($this->db, $data['subdomain']) : '';
            $domain = !empty($data['custom_domain']) ? mysqli_real_escape_string($this->db, $data['custom_domain']) : '';
            $escapedSettings = mysqli_real_escape_string($this->db, $settings);
            
            $sql = "INSERT INTO tenants (name, subdomain, custom_domain, settings, status, created_at) 
                    VALUES ('$name', '$subdomain', '$domain', '$escapedSettings', 'active', NOW())";
            
            if (!$this->db->query($sql)) {
                return ['success' => false, 'error' => 'Failed to create tenant'];
            }
            
            $tenantId = $this->db->insert_id;
            
            // Create default admin for tenant
            if (!empty($data['admin_email'])) {
                $this->createTenantAdmin($tenantId, $data);
            }
            
            // Log
            $this->log('TENANT_CREATED', [
                'tenant_id' => $tenantId,
                'name' => $data['name']
            ]);
            
            return [
                'success' => true,
                'tenant_id' => $tenantId,
                'message' => 'Tenant created successfully'
            ];
        });
    }
    
    /**
     * Update tenant configuration
     */
    public function updateTenant($tenantId, $data) {
        $tenantId = (int)$tenantId;
        
        $tenant = $this->getTenantById($tenantId);
        if (!$tenant) {
            return ['success' => false, 'error' => 'Tenant not found'];
        }
        
        $updates = [];
        
        if (isset($data['name'])) {
            $updates['name'] = mysqli_real_escape_string($this->db, $data['name']);
        }
        
        if (isset($data['status'])) {
            $updates['status'] = mysqli_real_escape_string($this->db, $data['status']);
        }
        
        if (isset($data['settings']) && is_array($data['settings'])) {
            $currentSettings = json_decode($tenant['settings'], true) ?: [];
            $newSettings = array_merge($currentSettings, $data['settings']);
            $updates['settings'] = mysqli_real_escape_string($this->db, json_encode($newSettings));
        }
        
        if (empty($updates)) {
            return ['success' => true, 'message' => 'No changes made'];
        }
        
        $setParts = [];
        foreach ($updates as $key => $value) {
            $setParts[] = "$key = '$value'";
        }
        
        $sql = "UPDATE tenants SET " . implode(', ', $setParts) . ", updated_at = NOW() WHERE id = $tenantId";
        
        if ($this->db->query($sql)) {
            $this->log('TENANT_UPDATED', [
                'tenant_id' => $tenantId,
                'fields' => array_keys($updates)
            ]);
            
            return ['success' => true, 'message' => 'Tenant updated'];
        }
        
        return ['success' => false, 'error' => 'Update failed'];
    }
    
    /**
     * Get tenant setting
     */
    public function getSetting($key, $default = null) {
        if (!$this->currentTenantConfig) {
            return $default;
        }
        
        $settings = json_decode($this->currentTenantConfig['settings'], true) ?: [];
        
        return $settings[$key] ?? $default;
    }
    
    /**
     * Get all tenants (for superadmin)
     */
    public function getAllTenants($filters = [], $limit = 50, $offset = 0) {
        $where = ['1=1'];
        
        if (!empty($filters['status'])) {
            $status = mysqli_real_escape_string($this->db, $filters['status']);
            $where[] = "status = '$status'";
        }
        
        if (!empty($filters['search'])) {
            $search = mysqli_real_escape_string($this->db, $filters['search']);
            $where[] = "(name LIKE '%$search%' OR subdomain LIKE '%$search%')";
        }
        
        $whereClause = implode(' AND ', $where);
        $limit = (int)$limit;
        $offset = (int)$offset;
        
        $sql = "SELECT id, name, subdomain, custom_domain, status, created_at 
                FROM tenants 
                WHERE $whereClause 
                ORDER BY created_at DESC 
                LIMIT $limit OFFSET $offset";
        
        $result = $this->db->query($sql);
        $tenants = [];
        
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $tenants[] = $row;
            }
        }
        
        // Get counts
        $countResult = $this->db->query("SELECT COUNT(*) as total FROM tenants WHERE $whereClause");
        $total = $countResult ? mysqli_fetch_assoc($countResult)['total'] : 0;
        
        // Get stats for each tenant
        foreach ($tenants as &$tenant) {
            $tenant['stats'] = $this->getTenantStats($tenant['id']);
        }
        
        return [
            'tenants' => $tenants,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset
        ];
    }
    
    /**
     * Get tenant statistics
     */
    public function getTenantStats($tenantId) {
        $tenantId = (int)$tenantId;
        
        $stats = [];
        
        // User counts by role
        $result = $this->db->query("SELECT role, COUNT(*) as count FROM users WHERE tenant_id = $tenantId AND status != 'deleted' GROUP BY role");
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $stats['users_by_role'][$row['role']] = (int)$row['count'];
            }
        }
        
        // Total users
        $result = $this->db->query("SELECT COUNT(*) as total FROM users WHERE tenant_id = $tenantId AND status != 'deleted'");
        if ($result) {
            $stats['total_users'] = (int)mysqli_fetch_assoc($result)['total'];
        }
        
        // Total classes
        $result = $this->db->query("SELECT COUNT(*) as total FROM classes WHERE tenant_id = $tenantId");
        if ($result) {
            $stats['total_classes'] = (int)mysqli_fetch_assoc($result)['total'];
        }
        
        // Active today
        $result = $this->db->query("SELECT COUNT(*) as total FROM users WHERE tenant_id = $tenantId AND last_login > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
        if ($result) {
            $stats['active_today'] = (int)mysqli_fetch_assoc($result)['total'];
        }
        
        return $stats;
    }
    
    /**
     * Switch tenant context
     */
    public function switchTenant($tenantId) {
        $tenant = $this->getTenantById($tenantId);
        
        if (!$tenant) {
            return ['success' => false, 'error' => 'Tenant not found'];
        }
        
        $this->currentTenantId = $tenantId;
        $this->currentTenantConfig = $tenant;
        $_SESSION['tenant_id'] = $tenantId;
        
        $this->log('TENANT_SWITCHED', ['tenant_id' => $tenantId]);
        
        return [
            'success' => true,
            'tenant_id' => $tenantId,
            'tenant_name' => $tenant['name']
        ];
    }
    
    /**
     * Create default admin for tenant
     */
    private function createTenantAdmin($tenantId, $data) {
        // This would create the initial admin user for the tenant
        // Implementation depends on UserService integration
        $userService = $this->container->get('user');
        
        $adminData = [
            'email' => $data['admin_email'],
            'role' => 'admin',
            'status' => 'active',
            'tenant_id' => $tenantId,
            'full_name' => $data['admin_name'] ?? 'Admin',
            'password' => $data['admin_password'] ?? bin2hex(random_bytes(8))
        ];
        
        return $userService->createUser($adminData);
    }
    
    /**
     * Build tenant-aware query condition
     */
    public function tenantCondition($tableAlias = '') {
        $prefix = $tableAlias ? "$tableAlias." : "";
        return "{$prefix}tenant_id = " . $this->currentTenantId;
    }
}
