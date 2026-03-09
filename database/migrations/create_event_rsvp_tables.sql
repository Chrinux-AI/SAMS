-- Event RSVP System Tables
-- Created: November 22, 2025
-- Purpose: Support parent event RSVP functionality

-- Event RSVPs Table (if not exists)
CREATE TABLE IF NOT EXISTS event_rsvps (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    student_id INT NOT NULL,
    rsvp_status ENUM('pending', 'attending', 'not_attending', 'maybe') NOT NULL DEFAULT 'pending',
    rsvp_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    UNIQUE KEY unique_event_student (event_id, student_id),
    INDEX idx_event (event_id),
    INDEX idx_student (student_id),
    INDEX idx_status (rsvp_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Event Classes Association (if not exists)
CREATE TABLE IF NOT EXISTS event_classes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    class_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    UNIQUE KEY unique_event_class (event_id, class_id),
    INDEX idx_event (event_id),
    INDEX idx_class (class_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Parent-Student Links (if not exists)
CREATE TABLE IF NOT EXISTS parent_student_links (
    id INT AUTO_INCREMENT PRIMARY KEY,
    parent_id INT NOT NULL,
    student_id INT NOT NULL,
    relationship ENUM('mother', 'father', 'guardian', 'other') NOT NULL DEFAULT 'guardian',
    is_primary BOOLEAN DEFAULT FALSE,
    verification_code VARCHAR(10) NULL,
    verified_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_parent_student (parent_id, student_id),
    INDEX idx_parent (parent_id),
    INDEX idx_student (student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert sample parent-student links for testing (using existing data)
-- This will link parents to students based on any existing relationships
INSERT IGNORE INTO parent_student_links (parent_id, student_id, relationship, is_primary)
SELECT DISTINCT
    u.id as parent_id,
    s.user_id as student_id,
    'guardian' as relationship,
    TRUE as is_primary
FROM users u
JOIN students s ON s.id > 0
WHERE u.role = 'parent' AND u.status = 'active'
LIMIT 0;  -- Set to 0 to prevent auto-insertion, run manually if needed