-- Add last_login tracking to users table
-- Run this migration to enable real-time login tracking

ALTER TABLE users
ADD COLUMN last_login DATETIME NULL AFTER updated_at,
ADD COLUMN login_count INT DEFAULT 0 AFTER last_login,
ADD INDEX idx_last_login (last_login);

-- Update existing users with a default value
UPDATE users SET last_login = created_at WHERE last_login IS NULL;

-- Show the updated structure
DESCRIBE users;
