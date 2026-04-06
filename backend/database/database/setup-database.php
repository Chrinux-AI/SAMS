<?php
/**
 * SAMS Database Schema Setup
 * Creates all required tables for the system
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';

function create_tables() {
    $db = db();
    
    $tables = [
        // Users table - core user accounts
        "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT DEFAULT 1,
            email VARCHAR(255) NOT NULL UNIQUE,
            password_hash VARCHAR(255),
            first_name VARCHAR(100),
            last_name VARCHAR(100),
            phone VARCHAR(20),
            role ENUM('admin', 'super_admin', 'teacher', 'student', 'parent', 'accountant', 'bursar', 'librarian', 'transport', 'forum_moderator', 'staff') DEFAULT 'student',
            status ENUM('active', 'pending_activation', 'suspended', 'deleted') DEFAULT 'pending_activation',
            activation_token VARCHAR(255),
            activated_at TIMESTAMP NULL,
            last_login TIMESTAMP NULL,
            email_notifications TINYINT DEFAULT 1,
            sms_notifications TINYINT DEFAULT 0,
            attendance_alerts TINYINT DEFAULT 1,
            announcement_alerts TINYINT DEFAULT 1,
            theme_preference ENUM('light', 'dark', 'system') DEFAULT 'light',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_email (email),
            INDEX idx_tenant (tenant_id),
            INDEX idx_role (role),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        // Profiles table - extended user information
        "CREATE TABLE IF NOT EXISTS profiles (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            bio TEXT,
            avatar_url VARCHAR(255),
            address TEXT,
            city VARCHAR(100),
            state VARCHAR(100),
            country VARCHAR(100),
            postal_code VARCHAR(20),
            date_of_birth DATE,
            gender ENUM('male', 'female', 'other', 'prefer_not_to_say'),
            emergency_contact_name VARCHAR(100),
            emergency_contact_phone VARCHAR(20),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        // Tenants table - multi-tenant support
        "CREATE TABLE IF NOT EXISTS tenants (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            subdomain VARCHAR(100) UNIQUE,
            custom_domain VARCHAR(255),
            logo_url VARCHAR(255),
            primary_color VARCHAR(7) DEFAULT '#4F46E5',
            secondary_color VARCHAR(7) DEFAULT '#10B981',
            settings JSON,
            status ENUM('active', 'suspended', 'deleted') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_subdomain (subdomain),
            INDEX idx_domain (custom_domain)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        // Students table - student-specific data
        "CREATE TABLE IF NOT EXISTS students (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            admission_no VARCHAR(50) UNIQUE,
            roll_number VARCHAR(50),
            grade_level INT,
            section VARCHAR(10),
            parent_id INT,
            guardian_name VARCHAR(100),
            guardian_phone VARCHAR(20),
            guardian_email VARCHAR(255),
            blood_group VARCHAR(10),
            medical_notes TEXT,
            admission_date DATE,
            is_active TINYINT DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_user (user_id),
            INDEX idx_admission (admission_no),
            INDEX idx_grade (grade_level)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        // Teachers table - teacher-specific data
        "CREATE TABLE IF NOT EXISTS teachers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            employee_id VARCHAR(50) UNIQUE,
            department VARCHAR(100),
            qualification VARCHAR(255),
            specialization VARCHAR(100),
            joining_date DATE,
            salary DECIMAL(10,2),
            is_active TINYINT DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_user (user_id),
            INDEX idx_employee (employee_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        // Classes table
        "CREATE TABLE IF NOT EXISTS classes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT DEFAULT 1,
            name VARCHAR(100) NOT NULL,
            grade_level INT,
            section VARCHAR(10),
            teacher_id INT,
            room_number VARCHAR(20),
            capacity INT DEFAULT 30,
            academic_year VARCHAR(20),
            description TEXT,
            is_active TINYINT DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE SET NULL,
            INDEX idx_tenant (tenant_id),
            INDEX idx_teacher (teacher_id),
            INDEX idx_grade (grade_level)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        // Enrollments table - student-class relationships
        "CREATE TABLE IF NOT EXISTS enrollments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            class_id INT NOT NULL,
            student_id INT NOT NULL,
            enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            status ENUM('active', 'transferred', 'withdrawn', 'completed') DEFAULT 'active',
            FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
            FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
            UNIQUE KEY unique_enrollment (class_id, student_id),
            INDEX idx_class (class_id),
            INDEX idx_student (student_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        // OTP Codes table
        "CREATE TABLE IF NOT EXISTS otp_codes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            email VARCHAR(255),
            otp_hash VARCHAR(255) NOT NULL,
            purpose VARCHAR(50) DEFAULT 'account_activation',
            expires_at TIMESTAMP NOT NULL,
            attempts INT DEFAULT 0,
            max_attempts INT DEFAULT 5,
            is_verified TINYINT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_user (user_id),
            INDEX idx_email (email),
            INDEX idx_expires (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        // Invites table
        "CREATE TABLE IF NOT EXISTS invites (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) NOT NULL,
            role VARCHAR(50) DEFAULT 'student',
            tenant_id INT DEFAULT 1,
            invited_by INT,
            activation_token VARCHAR(255) NOT NULL,
            status ENUM('pending', 'accepted', 'expired', 'revoked') DEFAULT 'pending',
            expires_at TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_token (activation_token),
            INDEX idx_email (email),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        // Attendance table
        "CREATE TABLE IF NOT EXISTS attendance (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            class_id INT,
            date DATE NOT NULL,
            status ENUM('present', 'absent', 'late', 'excused', 'half_day') NOT NULL,
            marked_by INT,
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
            FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE SET NULL,
            UNIQUE KEY unique_attendance (student_id, date, class_id),
            INDEX idx_student (student_id),
            INDEX idx_date (date),
            INDEX idx_class (class_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        // Activity Logs table
        "CREATE TABLE IF NOT EXISTS activity_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            action VARCHAR(100) NOT NULL,
            entity_type VARCHAR(50),
            entity_id INT,
            description TEXT,
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user (user_id),
            INDEX idx_action (action),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        // Schema Versions table - for migrations
        "CREATE TABLE IF NOT EXISTS schema_versions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            version VARCHAR(50) NOT NULL UNIQUE,
            description TEXT,
            applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            applied_by VARCHAR(100)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        // Chatbot Logs table
        "CREATE TABLE IF NOT EXISTS chatbot_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            message TEXT NOT NULL,
            detected_intent VARCHAR(50),
            response_sent TEXT,
            session_id VARCHAR(100),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user (user_id),
            INDEX idx_intent (detected_intent)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        // Error Logs table
        "CREATE TABLE IF NOT EXISTS error_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            error_type VARCHAR(50),
            message TEXT,
            file VARCHAR(255),
            line INT,
            user_id INT,
            request_uri VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_type (error_type),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        // Workflow Logs table
        "CREATE TABLE IF NOT EXISTS workflow_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            action VARCHAR(100),
            data JSON,
            user_id INT,
            status ENUM('success', 'failed', 'pending') DEFAULT 'pending',
            error_message TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_action (action),
            INDEX idx_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        // AI Processing Logs table
        "CREATE TABLE IF NOT EXISTS ai_processing_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            data JSON,
            result ENUM('success', 'partial', 'failed'),
            error_count INT DEFAULT 0,
            processed_count INT DEFAULT 0,
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_result (result),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ];
    
    $success = [];
    $errors = [];
    
    foreach ($tables as $sql) {
        try {
            if ($db->query($sql)) {
                $tableName = getTableNameFromSQL($sql);
                $success[] = $tableName;
            }
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
        }
    }
    
    return ['success' => $success, 'errors' => $errors];
}

function getTableNameFromSQL($sql) {
    if (preg_match('/CREATE TABLE IF NOT EXISTS\s+(\w+)/i', $sql, $matches)) {
        return $matches[1];
    }
    return 'unknown';
}

function insert_default_data() {
    $db = db();
    
    // Insert default tenant
    $db->query("INSERT IGNORE INTO tenants (id, name, subdomain, status) VALUES (1, 'Default School', 'default', 'active')");
    
    // Insert schema version
    $db->query("INSERT IGNORE INTO schema_versions (version, description) VALUES ('1.0.0', 'Initial schema creation')");
    
    return true;
}

// Execute if called directly
if (basename($_SERVER['PHP_SELF']) === 'setup-database.php') {
    echo "SAMMS Database Setup\n";
    echo "====================\n\n";
    
    $result = create_tables();
    
    echo "Tables created:\n";
    foreach ($result['success'] as $table) {
        echo "  ✓ $table\n";
    }
    
    if (!empty($result['errors'])) {
        echo "\nErrors:\n";
        foreach ($result['errors'] as $error) {
            echo "  ✗ $error\n";
        }
    }
    
    insert_default_data();
    echo "\n✓ Default data inserted\n";
    echo "✓ Database setup complete\n";
}
