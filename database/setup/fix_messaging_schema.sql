-- Complete Messaging System Schema
-- Run this to fix the messaging system

-- Drop old messages table if exists
DROP TABLE IF EXISTS message_recipients;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS messages;

-- Create messages table with broadcast support
CREATE TABLE messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    receiver_id INT NULL,
    recipient_role VARCHAR(20) NULL COMMENT 'all, student, teacher, parent, admin, or NULL for direct',
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    read_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_receiver (receiver_id),
    INDEX idx_sender (sender_id),
    INDEX idx_role (recipient_role),
    INDEX idx_read (is_read),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create message recipients table for broadcast messages
CREATE TABLE message_recipients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message_id INT NOT NULL,
    recipient_id INT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    read_at DATETIME NULL,
    deleted_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE,
    FOREIGN KEY (recipient_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_recipient (recipient_id),
    INDEX idx_message (message_id),
    INDEX idx_read (is_read),
    UNIQUE KEY unique_message_recipient (message_id, recipient_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create notifications table
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type VARCHAR(50) DEFAULT 'info' COMMENT 'info, message, alert, success, error',
    link VARCHAR(255) NULL,
    is_read TINYINT(1) DEFAULT 0,
    read_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_read (is_read),
    INDEX idx_type (type),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
