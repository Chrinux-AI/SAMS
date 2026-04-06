<?php
/**
 * Multi-Tenant Integration - Update main system to support multiple schools
 */

// Include multi-tenant system at the top of config.php
require_once __DIR__ . '/sams-multi-tenant.php';
require_once __DIR__ . '/sams-multi-tenant-ai.php';

/**
 * Initialize multi-tenant system
 */
function initializeMultiTenant() {
    try {
        // Detect and initialize current tenant
        $tenant = new SAMS_MultiTenant();

        // Make tenant available globally
        global $current_tenant;
        $current_tenant = $tenant;

        // Set tenant-specific constants
        if (!defined('TENANT_ID')) {
            define('TENANT_ID', $tenant->getTenantId());
        }

        return $tenant;
    } catch (Exception $e) {
        error_log("Multi-tenant initialization failed: " . $e->getMessage());
        return null;
    }
}

/**
 * Get current tenant instance
 */
function getCurrentTenant() {
    global $current_tenant;
    return $current_tenant ?? null;
}

/**
 * Get tenant-aware database connection
 */
function getTenantDB() {
    $tenant = getCurrentTenant();
    return $tenant ? $tenant->getDatabaseConnection() : db()->getConnection();
}

/**
 * Check if current user can access tenant
 */
function canAccessTenant($tenant_id, $user_id = null) {
    if (!$user_id) {
        $user_id = $_SESSION['user_id'] ?? 0;
    }

    // Super admins can access all tenants
    if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'super_admin') {
        return true;
    }

    // Check if user belongs to tenant
    $user = db()->fetchOne(
        "SELECT * FROM users WHERE id = ? AND tenant_id = ?",
        [$user_id, $tenant_id]
    );

    return $user !== false;
}

/**
 * Add tenant context to all database queries
 */
function addTenantContext($query, $params = []) {
    $tenant = getCurrentTenant();
    if (!$tenant) {
        return [$query, $params];
    }

    // Automatically add tenant_id WHERE clause if not present
    if (strpos($query, 'tenant_id') === false &&
        (strpos($query, 'SELECT') !== false || strpos($query, 'UPDATE') !== false || strpos($query, 'DELETE') !== false)) {

        if (strpos($query, 'WHERE') !== false) {
            $query = str_replace('WHERE', 'WHERE tenant_id = ? AND ', $query);
        } else {
            $query .= ' WHERE tenant_id = ?';
        }

        array_unshift($params, $tenant->getTenantId());
    }

    return [$query, $params];
}

// Initialize multi-tenant system
$tenant_system = initializeMultiTenant();
?>
