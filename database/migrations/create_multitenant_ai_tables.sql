-- Multi-tenant + AI foundation for SAMS
-- Safe to run multiple times.

CREATE TABLE IF NOT EXISTS school_tenants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    slug VARCHAR(190) NOT NULL,
    contact_email VARCHAR(190) NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    settings_json JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_school_tenants_slug (slug)
);

CREATE TABLE IF NOT EXISTS tenant_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    user_id INT NOT NULL,
    role_override VARCHAR(50) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_tenant_user (tenant_id, user_id),
    KEY idx_tenant_users_user (user_id),
    CONSTRAINT fk_tenant_users_tenant FOREIGN KEY (tenant_id) REFERENCES school_tenants(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS ai_conversations (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    user_id INT NOT NULL,
    session_id VARCHAR(128) NULL,
    message TEXT NOT NULL,
    response TEXT NOT NULL,
    intent VARCHAR(60) NOT NULL DEFAULT 'general',
    risk_level VARCHAR(20) NOT NULL DEFAULT 'low',
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_ai_conv_tenant_user_time (tenant_id, user_id, created_at),
    KEY idx_ai_conv_intent (intent),
    CONSTRAINT fk_ai_conv_tenant FOREIGN KEY (tenant_id) REFERENCES school_tenants(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS ai_rate_limits (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    user_id INT NOT NULL,
    ip_address VARCHAR(45) NULL,
    requests_minute INT NOT NULL DEFAULT 0,
    requests_hour INT NOT NULL DEFAULT 0,
    minute_window_start DATETIME NOT NULL,
    hour_window_start DATETIME NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_ai_rate_limit (tenant_id, user_id),
    CONSTRAINT fk_ai_rl_tenant FOREIGN KEY (tenant_id) REFERENCES school_tenants(id) ON DELETE CASCADE
);

INSERT INTO school_tenants (id, name, slug, status)
SELECT 1, 'Default School', 'default-school', 'active'
WHERE NOT EXISTS (SELECT 1 FROM school_tenants WHERE id = 1);

INSERT INTO tenant_users (tenant_id, user_id, is_active)
SELECT 1, u.id, 1
FROM users u
LEFT JOIN tenant_users tu ON tu.user_id = u.id
WHERE tu.id IS NULL;
