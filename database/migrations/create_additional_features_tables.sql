-- Teacher Resource Repository Tables

CREATE TABLE IF NOT EXISTS teacher_resources (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    file_path VARCHAR(500) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_size INT NOT NULL COMMENT 'in bytes',
    file_type VARCHAR(50),
    category ENUM('lesson_plan', 'worksheet', 'presentation', 'assignment', 'study_guide', 'other') DEFAULT 'other',
    is_public TINYINT(1) DEFAULT 0 COMMENT '1=shared with all teachers, 0=private',
    download_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_teacher (teacher_id),
    INDEX idx_public (is_public),
    INDEX idx_category (category),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Parent-Teacher Meeting Scheduler Tables

CREATE TABLE IF NOT EXISTS meeting_slots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    day_of_week ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday') NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    max_bookings INT DEFAULT 1 COMMENT 'bookings per slot',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_teacher (teacher_id),
    INDEX idx_day (day_of_week),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS meeting_bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slot_id INT NOT NULL,
    parent_id INT NOT NULL,
    student_id INT NOT NULL,
    booking_date DATE NOT NULL,
    status ENUM('pending', 'confirmed', 'cancelled', 'completed') DEFAULT 'pending',
    parent_notes TEXT,
    teacher_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (slot_id) REFERENCES meeting_slots(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    INDEX idx_slot (slot_id),
    INDEX idx_parent (parent_id),
    INDEX idx_date (booking_date),
    INDEX idx_status (status),
    UNIQUE KEY unique_booking (slot_id, booking_date, parent_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Behavior Logging System

CREATE TABLE IF NOT EXISTS behavior_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    teacher_id INT NOT NULL,
    type ENUM('positive', 'negative', 'neutral') NOT NULL,
    severity ENUM('minor', 'moderate', 'major', 'severe') DEFAULT 'minor',
    category VARCHAR(100) COMMENT 'e.g., Participation, Conduct, Academic, etc.',
    incident_description TEXT NOT NULL,
    action_taken TEXT,
    parent_notified TINYINT(1) DEFAULT 0,
    admin_notified TINYINT(1) DEFAULT 0,
    incident_date DATE NOT NULL,
    incident_time TIME,
    location VARCHAR(255),
    witnesses TEXT COMMENT 'Other staff who witnessed',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_student (student_id),
    INDEX idx_teacher (teacher_id),
    INDEX idx_type (type),
    INDEX idx_severity (severity),
    INDEX idx_date (incident_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Study Group Finder

CREATE TABLE IF NOT EXISTS study_groups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    class_id INT NOT NULL,
    creator_id INT NOT NULL,
    max_members INT DEFAULT 5,
    meeting_schedule VARCHAR(255) COMMENT 'e.g., Tuesdays 3-5pm',
    status ENUM('open', 'closed', 'full') DEFAULT 'open',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (creator_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_class (class_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS study_group_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_id INT NOT NULL,
    student_id INT NOT NULL,
    status ENUM('pending', 'accepted', 'rejected') DEFAULT 'pending',
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES study_groups(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_member (group_id, student_id),
    INDEX idx_group (group_id),
    INDEX idx_student (student_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Emergency Alerts System

CREATE TABLE IF NOT EXISTS emergency_alerts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    created_by INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    severity ENUM('info', 'warning', 'critical') DEFAULT 'info',
    target_roles VARCHAR(100) COMMENT 'Comma-separated, NULL = all',
    send_email TINYINT(1) DEFAULT 1,
    send_sms TINYINT(1) DEFAULT 0,
    requires_acknowledgment TINYINT(1) DEFAULT 0,
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_severity (severity),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS alert_acknowledgments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    alert_id INT NOT NULL,
    user_id INT NOT NULL,
    acknowledged_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (alert_id) REFERENCES emergency_alerts(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_ack (alert_id, user_id),
    INDEX idx_alert (alert_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
