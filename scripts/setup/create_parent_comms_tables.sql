-- Parent Communication Tables
-- Run this migration to support parent-teacher communication features

-- Parent-Teacher Meetings Table
CREATE TABLE IF NOT EXISTS parent_meetings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    parent_id INT NOT NULL,
    student_id INT NOT NULL,
    meeting_date DATE NOT NULL,
    meeting_time TIME NOT NULL,
    purpose TEXT NOT NULL,
    status ENUM('pending', 'confirmed', 'cancelled', 'completed') DEFAULT 'pending',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES parents(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    INDEX idx_teacher (teacher_id),
    INDEX idx_parent (parent_id),
    INDEX idx_date (meeting_date),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Expand messages table if needed (check if these columns exist)
-- ALTER TABLE messages ADD COLUMN IF NOT EXISTS message_type ENUM('general', 'progress_report', 'meeting_request', 'announcement') DEFAULT 'general';
-- ALTER TABLE messages ADD COLUMN IF NOT EXISTS related_student_id INT;

-- Parent-Student relationship table (if not exists)
CREATE TABLE IF NOT EXISTS parent_student (
    id INT AUTO_INCREMENT PRIMARY KEY,
    parent_id INT NOT NULL,
    student_id INT NOT NULL,
    relationship VARCHAR(50) DEFAULT 'Parent',
    is_primary BOOLEAN DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES parents(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    UNIQUE KEY unique_parent_student (parent_id, student_id),
    INDEX idx_parent (parent_id),
    INDEX idx_student (student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
