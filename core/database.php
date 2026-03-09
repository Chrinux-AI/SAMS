<?php
/**
 * SAMS Core Database
 * Central database connection and utilities
 */

require_once __DIR__ . '/config.php';

/**
 * Enhanced Database Class
 */
class Database {
    private $pdo;
    private $tenantId;
    
    public function __construct() {
        $this->pdo = db();
        $this->tenantId = getTenantId();
    }
    
    /**
     * Execute query with tenant isolation
     */
    public function query($sql, $params = []) {
        // Add tenant_id filter if table has it
        $sql = $this->addTenantFilter($sql);
        
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            logEvent('ERROR', 'Database query failed', [
                'sql' => $sql,
                'params' => $params,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Fetch single record
     */
    public function fetchOne($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }
    
    /**
     * Fetch multiple records
     */
    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Count records
     */
    public function count($table, $where = '', $params = []) {
        $sql = "SELECT COUNT(*) as count FROM `$table`";
        if ($where) {
            $sql .= " WHERE $where";
        }
        $result = $this->fetchOne($sql, $params);
        return $result['count'] ?? 0;
    }
    
    /**
     * Insert record
     */
    public function insert($table, $data) {
        // Add tenant_id
        $data['tenant_id'] = $this->tenantId;
        
        $columns = array_keys($data);
        $placeholders = array_fill(0, count($columns), '?');
        
        $sql = "INSERT INTO `$table` (`" . implode('`, `', $columns) . "`) 
                VALUES (" . implode(', ', $placeholders) . ")";
        
        $this->query($sql, array_values($data));
        return $this->pdo->lastInsertId();
    }
    
    /**
     * Update record
     */
    public function update($table, $data, $where, $whereParams = []) {
        // Add tenant_id to where clause if not present
        if (strpos($where, 'tenant_id') === false) {
            $where .= " AND tenant_id = ?";
            $whereParams[] = $this->tenantId;
        }
        
        $setClause = [];
        $params = [];
        
        foreach ($data as $column => $value) {
            $setClause[] = "`$column` = ?";
            $params[] = $value;
        }
        
        $sql = "UPDATE `$table` SET " . implode(', ', $setClause) . " WHERE $where";
        
        $params = array_merge($params, $whereParams);
        $this->query($sql, $params);
        return $this->pdo->rowCount();
    }
    
    /**
     * Delete record
     */
    public function delete($table, $where, $params = []) {
        // Add tenant_id to where clause if not present
        if (strpos($where, 'tenant_id') === false) {
            $where .= " AND tenant_id = ?";
            $params[] = $this->tenantId;
        }
        
        $sql = "DELETE FROM `$table` WHERE $where";
        $this->query($sql, $params);
        return $this->pdo->rowCount();
    }
    
    /**
     * Begin transaction
     */
    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }
    
    /**
     * Commit transaction
     */
    public function commit() {
        return $this->pdo->commit();
    }
    
    /**
     * Rollback transaction
     */
    public function rollback() {
        return $this->pdo->rollback();
    }
    
    /**
     * Add tenant filter to queries
     */
    private function addTenantFilter($sql) {
        // List of tables that have tenant_id column
        $tenantTables = [
            'users', 'students', 'teachers', 'parents', 'classes', 'attendance',
            'grades', 'assignments', 'payments', 'invoices', 'books', 'routes',
            'forum_posts', 'audit_logs', 'login_logs', 'teams', 'team_members',
            'anomalies', 'ai_predictions', 'security_events', 'attendance_versions'
        ];
        
        // Add tenant_id filter for SELECT queries on tenant tables
        if (preg_match('/^\s*SELECT\s+/i', $sql)) {
            foreach ($tenantTables as $table) {
                if (preg_match("/\b$table\b/i", $sql) && 
                    !preg_match("/\btenant_id\s*=\s*?/i", $sql)) {
                    // Add tenant_id condition
                    if (preg_match('/\bWHERE\b/i', $sql)) {
                        $sql = preg_replace('/\bWHERE\b/i', "WHERE $table.tenant_id = {$this->tenantId} AND", $sql, 1);
                    } else {
                        $sql = preg_replace('/\bFROM\s+' . $table . '\b/i', "FROM $table WHERE $table.tenant_id = {$this->tenantId}", $sql);
                    }
                    break;
                }
            }
        }
        
        return $sql;
    }
    
    /**
     * Get last error
     */
    public function getError() {
        return $this->pdo->errorInfo();
    }
    
    /**
     * Check if table exists
     */
    public function tableExists($table) {
        $sql = "SHOW TABLES LIKE ?";
        $result = $this->fetchOne($sql, [$table]);
        return !empty($result);
    }
    
    /**
     * Create table if not exists
     */
    public function createTable($sql) {
        try {
            $this->query($sql);
            return true;
        } catch (PDOException $e) {
            logEvent('ERROR', 'Table creation failed', ['sql' => $sql, 'error' => $e->getMessage()]);
            return false;
        }
    }
}

// Global database instance
function db() {
    static $instance = null;
    if ($instance === null) {
        $instance = new Database();
    }
    return $instance;
}

// Initialize database tables
function initializeDatabase() {
    $db = db();
    
    // Core tables SQL
    $tables = [
        // Users table
        "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT NOT NULL DEFAULT 1,
            username VARCHAR(50) UNIQUE NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            first_name VARCHAR(50) NOT NULL,
            last_name VARCHAR(50) NOT NULL,
            role ENUM('admin', 'teacher', 'student', 'parent', 'accountant', 'librarian', 'transport', 'moderator') NOT NULL,
            is_active BOOLEAN DEFAULT TRUE,
            last_login DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_tenant (tenant_id),
            INDEX idx_role (role),
            INDEX idx_email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        // Students table
        "CREATE TABLE IF NOT EXISTS students (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT NOT NULL DEFAULT 1,
            user_id INT NOT NULL,
            admission_number VARCHAR(20) UNIQUE NOT NULL,
            grade_level VARCHAR(20) NOT NULL,
            section VARCHAR(10),
            date_of_birth DATE,
            gender ENUM('male', 'female', 'other'),
            address TEXT,
            phone VARCHAR(20),
            parent_id INT,
            is_active BOOLEAN DEFAULT TRUE,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (parent_id) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_tenant (tenant_id),
            INDEX idx_admission (admission_number),
            INDEX idx_grade (grade_level)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        // Teachers table
        "CREATE TABLE IF NOT EXISTS teachers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT NOT NULL DEFAULT 1,
            user_id INT NOT NULL,
            employee_number VARCHAR(20) UNIQUE NOT NULL,
            department VARCHAR(50),
            qualification VARCHAR(100),
            experience_years INT DEFAULT 0,
            specialization VARCHAR(100),
            is_active BOOLEAN DEFAULT TRUE,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_tenant (tenant_id),
            INDEX idx_employee (employee_number)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        // Classes table
        "CREATE TABLE IF NOT EXISTS classes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT NOT NULL DEFAULT 1,
            name VARCHAR(50) NOT NULL,
            grade_level VARCHAR(20) NOT NULL,
            section VARCHAR(10),
            teacher_id INT,
            max_students INT DEFAULT 40,
            room_number VARCHAR(20),
            schedule JSON,
            is_active BOOLEAN DEFAULT TRUE,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_tenant (tenant_id),
            INDEX idx_grade (grade_level),
            INDEX idx_teacher (teacher_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        // Attendance table
        "CREATE TABLE IF NOT EXISTS attendance (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT NOT NULL DEFAULT 1,
            student_id INT NOT NULL,
            class_id INT NOT NULL,
            teacher_id INT NOT NULL,
            date DATE NOT NULL,
            check_in_time DATETIME,
            check_out_time DATETIME,
            status ENUM('present', 'absent', 'late', 'excused') DEFAULT 'present',
            notes TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            updated_by INT,
            FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
            FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
            UNIQUE KEY unique_attendance (student_id, class_id, date),
            INDEX idx_tenant (tenant_id),
            INDEX idx_student (student_id),
            INDEX idx_class (class_id),
            INDEX idx_date (date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        // Attendance Versions table
        "CREATE TABLE IF NOT EXISTS attendance_versions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            attendance_id INT NOT NULL,
            tenant_id INT NOT NULL DEFAULT 1,
            before_state JSON NOT NULL,
            after_state JSON NOT NULL,
            editor_id INT NOT NULL,
            edit_reason TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (attendance_id) REFERENCES attendance(id) ON DELETE CASCADE,
            FOREIGN KEY (editor_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_tenant (tenant_id),
            INDEX idx_attendance (attendance_id),
            INDEX idx_editor (editor_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        // Audit Logs table
        "CREATE TABLE IF NOT EXISTS audit_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT NOT NULL DEFAULT 1,
            actor_id INT NOT NULL,
            action VARCHAR(100) NOT NULL,
            entity_type VARCHAR(50),
            entity_id INT,
            before_state JSON,
            after_state JSON,
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_tenant (tenant_id),
            INDEX idx_actor (actor_id),
            INDEX idx_action (action),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        // Teams table
        "CREATE TABLE IF NOT EXISTS teams (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT NOT NULL DEFAULT 1,
            name VARCHAR(100) NOT NULL,
            description TEXT,
            type ENUM('academic', 'administrative', 'extracurricular', 'sports') DEFAULT 'academic',
            leader_id INT,
            is_active BOOLEAN DEFAULT TRUE,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (leader_id) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_tenant (tenant_id),
            INDEX idx_type (type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        // Team Members table
        "CREATE TABLE IF NOT EXISTS team_members (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT NOT NULL DEFAULT 1,
            team_id INT NOT NULL,
            user_id INT NOT NULL,
            role ENUM('leader', 'member', 'assistant') DEFAULT 'member',
            joined_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY unique_membership (team_id, user_id),
            INDEX idx_tenant (tenant_id),
            INDEX idx_team (team_id),
            INDEX idx_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ];
    
    foreach ($tables as $sql) {
        $db->createTable($sql);
    }
    
    logEvent('INFO', 'Database tables initialized');
}

// Initialize database on first load
if (!isset($_SESSION['db_initialized'])) {
    initializeDatabase();
    $_SESSION['db_initialized'] = true;
}
?>
