-- Add contacts/favorites system for chat
CREATE TABLE IF NOT EXISTS chat_contacts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    contact_user_id INT NOT NULL,
    nickname VARCHAR(100) NULL COMMENT 'Custom name for contact',
    is_favorite BOOLEAN DEFAULT 0,
    is_blocked BOOLEAN DEFAULT 0,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (contact_user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_contact (user_id, contact_user_id),
    INDEX idx_user (user_id),
    INDEX idx_favorite (is_favorite),
    INDEX idx_blocked (is_blocked)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add quick access to recent chats
CREATE TABLE IF NOT EXISTS chat_recent_contacts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    contact_user_id INT NOT NULL,
    last_interaction_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    interaction_count INT DEFAULT 1,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (contact_user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_recent (user_id, contact_user_id),
    INDEX idx_user (user_id),
    INDEX idx_last_interaction (last_interaction_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
