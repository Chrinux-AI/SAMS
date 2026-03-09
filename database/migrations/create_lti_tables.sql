-- Migration: LTI (Learning Tools Interoperability) Integration Tables
-- Created: November 2025
-- Purpose: Support LMS integration via LTI 1.3 standard

-- Drop existing tables if present (for clean migration)
DROP TABLE IF EXISTS lti_links;
DROP TABLE IF EXISTS lti_sessions;
DROP TABLE IF EXISTS lti_grade_sync_log;
DROP TABLE IF EXISTS lti_configurations;

-- LTI Configurations: Store connection settings for different LMS platforms
CREATE TABLE lti_configurations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lms_platform VARCHAR(50) NOT NULL COMMENT 'moodle, canvas, blackboard, etc.',
    lms_name VARCHAR(100) NOT NULL COMMENT 'Display name for this LMS instance',
    client_id VARCHAR(255) NOT NULL COMMENT 'OAuth client ID from LMS',
    issuer VARCHAR(500) NOT NULL COMMENT 'LMS issuer URL',
    deployment_id VARCHAR(255) NOT NULL COMMENT 'LTI deployment identifier',

    -- RSA key pair for JWT validation
    public_key TEXT NOT NULL COMMENT 'Public RSA key in PEM format',
    private_key TEXT NOT NULL COMMENT 'Private RSA key in PEM format (encrypted)',

    -- LMS-specific endpoints
    auth_login_url VARCHAR(500) COMMENT 'OIDC auth endpoint',
    auth_token_url VARCHAR(500) COMMENT 'OAuth token endpoint',
    keyset_url VARCHAR(500) COMMENT 'JWK set URL',

    -- Integration settings
    is_active BOOLEAN DEFAULT 1 COMMENT 'Enable/disable this integration',
    auto_sync_enabled BOOLEAN DEFAULT 0 COMMENT 'Auto sync attendance to grades',
    sync_frequency INT DEFAULT 3600 COMMENT 'Sync interval in seconds',

    -- Tracking
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_sync_at TIMESTAMP NULL COMMENT 'Last successful sync timestamp',

    UNIQUE KEY unique_lms_client (lms_platform, client_id),
    INDEX idx_active (is_active),
    INDEX idx_platform (lms_platform)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='LMS connection configurations';

-- LTI Sessions: Track individual LTI tool launches
CREATE TABLE lti_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lti_config_id INT NOT NULL COMMENT 'FK to lti_configurations',
    user_id INT NOT NULL COMMENT 'FK to users table',

    -- LMS context information
    lms_user_id VARCHAR(255) COMMENT 'User ID in the LMS',
    lms_context_id VARCHAR(255) COMMENT 'Course/context ID in LMS',
    lms_resource_link_id VARCHAR(255) COMMENT 'Specific resource link',

    -- Launch parameters (stored as JSON)
    launch_params JSON COMMENT 'Full LTI launch parameters',

    -- Session tracking
    session_token VARCHAR(64) UNIQUE COMMENT 'Internal session identifier',
    ip_address VARCHAR(45) COMMENT 'Client IP for security',
    user_agent TEXT COMMENT 'Browser user agent',

    -- Status
    is_valid BOOLEAN DEFAULT 1 COMMENT 'Session validity flag',
    expires_at TIMESTAMP NULL COMMENT 'Session expiration time',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (lti_config_id) REFERENCES lti_configurations(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_context (lms_context_id),
    INDEX idx_session_token (session_token),
    INDEX idx_valid_sessions (is_valid, expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='LTI launch session tracking';

-- LTI Deep Links: Store embedded resource links
CREATE TABLE lti_links (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lti_config_id INT NOT NULL COMMENT 'FK to lti_configurations',

    -- Link details
    resource_type VARCHAR(50) NOT NULL COMMENT 'attendance, grades, assignments, etc.',
    resource_id INT COMMENT 'Local resource ID (class_id, assignment_id, etc.)',
    resource_url VARCHAR(500) NOT NULL COMMENT 'Deep link URL',

    -- LMS mapping
    lms_context_id VARCHAR(255) COMMENT 'LMS course ID',
    lms_resource_link_id VARCHAR(255) UNIQUE COMMENT 'LMS resource link ID',

    -- Metadata
    title VARCHAR(255) NOT NULL COMMENT 'Display title in LMS',
    description TEXT COMMENT 'Link description',
    icon_url VARCHAR(500) COMMENT 'Resource icon URL',

    -- Tracking
    launch_count INT DEFAULT 0 COMMENT 'Number of times launched',
    last_launched_at TIMESTAMP NULL COMMENT 'Last launch timestamp',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (lti_config_id) REFERENCES lti_configurations(id) ON DELETE CASCADE,
    INDEX idx_resource_type (resource_type, resource_id),
    INDEX idx_context (lms_context_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Deep link resources for LMS embedding';

-- LTI Grade Sync Log: Track attendance-to-grade passback
CREATE TABLE lti_grade_sync_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lti_config_id INT NOT NULL COMMENT 'FK to lti_configurations',
    user_id INT NOT NULL COMMENT 'FK to users (student)',

    -- Sync details
    lms_context_id VARCHAR(255) NOT NULL COMMENT 'LMS course ID',
    lms_resource_link_id VARCHAR(255) COMMENT 'Grade column link',

    -- Grade data
    attendance_percentage DECIMAL(5,2) COMMENT 'Calculated attendance %',
    grade_value DECIMAL(5,2) COMMENT 'Grade sent to LMS (0-100)',
    sync_type ENUM('manual', 'auto', 'bulk') DEFAULT 'auto',

    -- Status
    status ENUM('pending', 'success', 'failed') DEFAULT 'pending',
    error_message TEXT COMMENT 'Error details if failed',
    retry_count INT DEFAULT 0 COMMENT 'Number of retry attempts',

    -- Tracking
    synced_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (lti_config_id) REFERENCES lti_configurations(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_context (user_id, lms_context_id),
    INDEX idx_status (status, retry_count),
    INDEX idx_sync_date (synced_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Grade passback sync history';

-- Insert sample LTI configuration (disabled by default)
INSERT INTO lti_configurations (
    lms_platform,
    lms_name,
    client_id,
    issuer,
    deployment_id,
    public_key,
    private_key,
    is_active
) VALUES (
    'moodle',
    'Sample Moodle LMS',
    'sample_client_id_12345',
    'https://moodle.example.edu',
    'deployment_1',
    '-----BEGIN PUBLIC KEY-----\nSAMPLE_KEY_REPLACE_IN_ADMIN_PANEL\n-----END PUBLIC KEY-----',
    '-----BEGIN PRIVATE KEY-----\nSAMPLE_KEY_REPLACE_IN_ADMIN_PANEL\n-----END PRIVATE KEY-----',
    0
) LIMIT 0; -- LIMIT 0 prevents actual insert, run manually after setup

-- Success message
SELECT 'LTI tables created successfully! Configure LMS connections in Admin Panel.' AS message;
