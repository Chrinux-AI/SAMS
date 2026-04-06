-- Notice Board System Tables
-- Universal feature for announcements visible to all roles

CREATE TABLE IF NOT EXISTS notices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    category ENUM('academic', 'sports', 'emergency', 'event', 'maintenance', 'general') DEFAULT 'general',
    priority ENUM('normal', 'high', 'urgent') DEFAULT 'normal',
    target_roles VARCHAR(100) NULL COMMENT 'Comma-separated roles (NULL = all roles)',
    status ENUM('active', 'archived') DEFAULT 'active',
    is_pinned TINYINT(1) DEFAULT 0,
    created_by INT NOT NULL,
    expires_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_status_expires (status, expires_at),
    INDEX idx_category (category),
    INDEX idx_pinned (is_pinned),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sample notices (admin must create real ones)
INSERT INTO notices (title, content, category, priority, target_roles, created_by, is_pinned) VALUES
('Welcome to SAMS', 'The Student Attendance Management System is now live! All users can now check in, view schedules, and track attendance in real-time.', 'general', 'high', NULL, 1, 1),
('System Maintenance Notice', 'Scheduled maintenance will occur on November 25, 2025 from 2:00 AM to 4:00 AM. The system will be temporarily unavailable during this time.', 'maintenance', 'normal', NULL, 1, 0)
LIMIT 0; -- Remove LIMIT 0 to insert sample data
