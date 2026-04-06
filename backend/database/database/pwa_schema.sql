-- PWA Integration Database Schema
-- Tables for push subscriptions, notification preferences, and sync status

-- Push subscriptions table
CREATE TABLE IF NOT EXISTS push_subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    endpoint TEXT NOT NULL,
    p256dh VARCHAR(255) NOT NULL,
    auth VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Notification preferences table
CREATE TABLE IF NOT EXISTS notification_preferences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    preferences JSON NOT NULL DEFAULT '{"attendance":true,"messages":true,"assignments":true,"announcements":true,"grades":true,"events":true}',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User sync status table
CREATE TABLE IF NOT EXISTS user_sync_status (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    last_sync TIMESTAMP NULL,
    device_info JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_last_sync (last_sync)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- PWA installation tracking
CREATE TABLE IF NOT EXISTS pwa_installations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    device_type ENUM('android', 'ios', 'desktop', 'other') NOT NULL,
    browser VARCHAR(100) NULL,
    os VARCHAR(100) NULL,
    screen_resolution VARCHAR(50) NULL,
    installed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_active TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_installed_at (installed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Push notification logs
CREATE TABLE IF NOT EXISTS push_notification_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    subscription_id INT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('attendance', 'message', 'assignment', 'announcement', 'grade', 'event', 'general') DEFAULT 'general',
    status ENUM('sent', 'failed', 'pending') DEFAULT 'pending',
    error_message TEXT NULL,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    clicked BOOLEAN DEFAULT FALSE,
    clicked_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (subscription_id) REFERENCES push_subscriptions(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_type (type),
    INDEX idx_status (status),
    INDEX idx_sent_at (sent_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Offline queue for failed sync attempts
CREATE TABLE IF NOT EXISTS offline_sync_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action_type ENUM('attendance', 'message', 'submission', 'other') NOT NULL,
    payload JSON NOT NULL,
    status ENUM('pending', 'processing', 'synced', 'failed') DEFAULT 'pending',
    attempts INT DEFAULT 0,
    last_attempt TIMESTAMP NULL,
    error_message TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    synced_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- PWA analytics
CREATE TABLE IF NOT EXISTS pwa_analytics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    event_type ENUM('install', 'uninstall', 'page_view', 'offline_access', 'sync', 'notification_click', 'share') NOT NULL,
    event_data JSON NULL,
    page_url VARCHAR(500) NULL,
    is_offline BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_event_type (event_type),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cache manifest for offline resources
CREATE TABLE IF NOT EXISTS pwa_cache_manifest (
    id INT AUTO_INCREMENT PRIMARY KEY,
    resource_type ENUM('page', 'asset', 'api', 'image', 'font') NOT NULL,
    resource_path VARCHAR(500) NOT NULL UNIQUE,
    cache_strategy ENUM('cache-first', 'network-first', 'network-only', 'cache-only') DEFAULT 'cache-first',
    priority INT DEFAULT 5,
    max_age INT DEFAULT 86400 COMMENT 'Cache max age in seconds',
    is_critical BOOLEAN DEFAULT FALSE,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_resource_type (resource_type),
    INDEX idx_priority (priority),
    INDEX idx_is_critical (is_critical)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default critical resources
INSERT INTO pwa_cache_manifest (resource_path, resource_type, cache_strategy, priority, is_critical) VALUES
('/attendance/', 'page', 'network-first', 10, TRUE),
('/attendance/index.php', 'page', 'network-first', 10, TRUE),
('/attendance/login.php', 'page', 'network-first', 10, TRUE),
('/attendance/assets/css/cyberpunk-ui.css', 'asset', 'cache-first', 9, TRUE),
('/attendance/assets/css/pwa-styles.css', 'asset', 'cache-first', 9, TRUE),
('/attendance/assets/js/main.js', 'asset', 'cache-first', 9, TRUE),
('/attendance/assets/js/pwa-manager.js', 'asset', 'cache-first', 9, TRUE),
('/attendance/offline.html', 'page', 'cache-first', 10, TRUE),
('/attendance/manifest.json', 'asset', 'cache-first', 10, TRUE)
ON DUPLICATE KEY UPDATE priority=VALUES(priority);

-- PWA feature flags
CREATE TABLE IF NOT EXISTS pwa_feature_flags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    feature_name VARCHAR(100) NOT NULL UNIQUE,
    is_enabled BOOLEAN DEFAULT TRUE,
    description TEXT NULL,
    config JSON NULL,
    updated_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default feature flags
INSERT INTO pwa_feature_flags (feature_name, is_enabled, description, config) VALUES
('push_notifications', TRUE, 'Enable push notifications', '{"max_per_day":20}'),
('offline_sync', TRUE, 'Enable offline data synchronization', '{"auto_sync":true,"sync_interval":300}'),
('background_sync', TRUE, 'Enable background sync API', '{"retry_attempts":3}'),
('install_prompt', TRUE, 'Show PWA installation prompt', '{"delay_days":1}'),
('periodic_sync', FALSE, 'Enable periodic background sync', '{"interval":86400}'),
('share_target', TRUE, 'Enable Web Share Target API', NULL),
('shortcuts', TRUE, 'Enable app shortcuts', NULL)
ON DUPLICATE KEY UPDATE description=VALUES(description);
