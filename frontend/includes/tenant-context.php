<?php

declare(strict_types=1);

require_once __DIR__ . '/advanced-sams.php';

function active_tenant_context(?int $requestedTenantId = null): ?array
{
    $tenantId = $requestedTenantId ?? AdvancedSAMS::currentTenantId();
    if (!$tenantId) {
        return null;
    }

    if (AdvancedSAMS::tableExists('school_tenants')) {
        $tenant = db()->fetchOne(
            'SELECT id, name, slug, contact_email, status, onboarding_status, subscription_plan, subscription_status
             FROM school_tenants WHERE id = ? LIMIT 1',
            [$tenantId]
        );

        if ($tenant) {
            return [
                'id' => (int) $tenant['id'],
                'name' => (string) $tenant['name'],
                'slug' => (string) ($tenant['slug'] ?: ('school-' . $tenant['id'])),
                'email' => (string) ($tenant['contact_email'] ?? ''),
                'country' => 'Nigeria',
                'currency' => 'NGN',
                'status' => (string) ($tenant['onboarding_status'] ?? $tenant['status'] ?? 'active'),
                'plan' => (string) ($tenant['subscription_plan'] ?? 'trial'),
                'subscription_status' => (string) ($tenant['subscription_status'] ?? $tenant['status'] ?? 'active')
            ];
        }
    }

    return null;
}

function ensure_tenant_membership(int $userId, int $tenantId): bool
{
    $resolved = AdvancedSAMS::resolveUserTenantId($userId);
    if ($resolved === $tenantId) {
        return true;
    }

    if (AdvancedSAMS::tableExists('tenant_users')) {
        $membership = db()->fetchOne(
            'SELECT id FROM tenant_users WHERE user_id = ? AND tenant_id = ? AND is_active = 1 LIMIT 1',
            [$userId, $tenantId]
        );
        return (bool) $membership;
    }

    return false;
}
