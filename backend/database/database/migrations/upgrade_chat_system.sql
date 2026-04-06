-- Enhanced Chat System - WhatsApp/Telegram Style
-- Real-time messaging with threading, replies, reactions, typing indicators

-- Message threads and replies
CREATE TABLE IF NOT EXISTS message_threads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    parent_message_id INT NOT NULL,
    reply_message_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_message_id) REFERENCES messages(id) ON DELETE CASCADE,
    FOREIGN KEY (reply_message_id) REFERENCES messages(id) ON DELETE CASCADE,
    INDEX idx_parent (parent_message_id),
    INDEX idx_reply (reply_message_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Message reactions (like/emoji reactions)
CREATE TABLE IF NOT EXISTS message_reactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message_id INT NOT NULL,
    user_id INT NOT NULL,
    reaction VARCHAR(50) NOT NULL COMMENT 'emoji or reaction type',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_reaction (message_id, user_id, reaction),
    INDEX idx_message (message_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Typing indicators
CREATE TABLE IF NOT EXISTS typing_indicators (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    conversation_id INT NULL,
    chat_room_id INT NULL,
    is_typing BOOLEAN DEFAULT 1,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_conversation (conversation_id),
    INDEX idx_room (chat_room_id),
    INDEX idx_updated (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Message attachments
CREATE TABLE IF NOT EXISTS message_attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message_id INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_type VARCHAR(100) NOT NULL COMMENT 'image/jpeg, application/pdf, etc',
    file_size INT NOT NULL COMMENT 'bytes',
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE,
    INDEX idx_message (message_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Online status tracking
CREATE TABLE IF NOT EXISTS user_online_status (
    user_id INT PRIMARY KEY,
    is_online BOOLEAN DEFAULT 0,
    last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_online (is_online),
    INDEX idx_last_seen (last_seen)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Message delivery status (sent, delivered, read)
CREATE TABLE IF NOT EXISTS message_delivery_status (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message_id INT NOT NULL,
    recipient_id INT NOT NULL,
    status ENUM('sent', 'delivered', 'read') DEFAULT 'sent',
    delivered_at TIMESTAMP NULL,
    read_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE,
    FOREIGN KEY (recipient_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_delivery (message_id, recipient_id),
    INDEX idx_status (status),
    INDEX idx_recipient (recipient_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Conversation participants with settings
CREATE TABLE IF NOT EXISTS conversation_participants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT NOT NULL,
    user_id INT NOT NULL,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_read_at TIMESTAMP NULL,
    is_muted BOOLEAN DEFAULT 0,
    is_archived BOOLEAN DEFAULT 0,
    is_pinned BOOLEAN DEFAULT 0,
    notification_enabled BOOLEAN DEFAULT 1,
    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_participant (conversation_id, user_id),
    INDEX idx_user (user_id),
    INDEX idx_archived (is_archived),
    INDEX idx_pinned (is_pinned)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Message mentions (@username)
CREATE TABLE IF NOT EXISTS message_mentions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message_id INT NOT NULL,
    mentioned_user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE,
    FOREIGN KEY (mentioned_user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_message (message_id),
    INDEX idx_mentioned (mentioned_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Message edit history
CREATE TABLE IF NOT EXISTS message_edit_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message_id INT NOT NULL,
    original_text TEXT NOT NULL,
    edited_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE,
    INDEX idx_message (message_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add new columns to existing messages table
ALTER TABLE messages
ADD COLUMN IF NOT EXISTS is_deleted BOOLEAN DEFAULT 0,
ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMP NULL,
ADD COLUMN IF NOT EXISTS is_edited BOOLEAN DEFAULT 0,
ADD COLUMN IF NOT EXISTS edited_at TIMESTAMP NULL,
ADD COLUMN IF NOT EXISTS reply_to_message_id INT NULL,
ADD COLUMN IF NOT EXISTS forwarded_from_message_id INT NULL,
ADD INDEX IF NOT EXISTS idx_reply (reply_to_message_id),
ADD INDEX IF NOT EXISTS idx_forwarded (forwarded_from_message_id),
ADD INDEX IF NOT EXISTS idx_deleted (is_deleted);

-- Add columns to conversations table
ALTER TABLE conversations
ADD COLUMN IF NOT EXISTS is_archived BOOLEAN DEFAULT 0,
ADD COLUMN IF NOT EXISTS is_muted BOOLEAN DEFAULT 0,
ADD COLUMN IF NOT EXISTS last_message_text TEXT NULL,
ADD COLUMN IF NOT EXISTS last_message_sender_id INT NULL,
ADD COLUMN IF NOT EXISTS unread_count INT DEFAULT 0,
ADD INDEX IF NOT EXISTS idx_archived (is_archived);

-- Add columns to chat_room_messages
ALTER TABLE chat_room_messages
ADD COLUMN IF NOT EXISTS reply_to_message_id INT NULL,
ADD COLUMN IF NOT EXISTS is_deleted BOOLEAN DEFAULT 0,
ADD COLUMN IF NOT EXISTS is_edited BOOLEAN DEFAULT 0,
ADD COLUMN IF NOT EXISTS edited_at TIMESTAMP NULL,
ADD INDEX IF NOT EXISTS idx_reply (reply_to_message_id);

-- Add columns to conversation_messages for replies
ALTER TABLE conversation_messages
ADD COLUMN IF NOT EXISTS reply_to_message_id INT NULL,
ADD COLUMN IF NOT EXISTS is_deleted BOOLEAN DEFAULT 0,
ADD COLUMN IF NOT EXISTS is_edited BOOLEAN DEFAULT 0,
ADD COLUMN IF NOT EXISTS edited_at TIMESTAMP NULL,
ADD INDEX IF NOT EXISTS idx_reply (reply_to_message_id);

-- Voice messages support
CREATE TABLE IF NOT EXISTS voice_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message_id INT NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    duration_seconds INT NOT NULL,
    file_size INT NOT NULL,
    waveform_data TEXT NULL COMMENT 'JSON array of amplitude values',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE,
    INDEX idx_message (message_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Message forwarding tracking
CREATE TABLE IF NOT EXISTS message_forwards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    original_message_id INT NOT NULL,
    forwarded_message_id INT NOT NULL,
    forwarded_by_user_id INT NOT NULL,
    forwarded_to_conversation_id INT NULL,
    forwarded_to_chat_room_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (original_message_id) REFERENCES messages(id) ON DELETE CASCADE,
    FOREIGN KEY (forwarded_message_id) REFERENCES messages(id) ON DELETE CASCADE,
    FOREIGN KEY (forwarded_by_user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_original (original_message_id),
    INDEX idx_forwarded (forwarded_message_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
