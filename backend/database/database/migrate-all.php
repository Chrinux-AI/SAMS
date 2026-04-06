<?php
/**
 * SAMS Comprehensive Database Migration
 * Creates all required tables for the full system
 * Run via: php database/migrate-all.php
 */

require_once __DIR__ . '/../includes/config.php';
require_once INCLUDES_PATH . '/database.php';

$db = db();
$created = [];
$errors = [];

function run_migration($db, $name, $sql, &$created, &$errors) {
    try {
        $db->query($sql);
        $created[] = $name;
    } catch (Exception $e) {
        $errors[] = "$name: " . $e->getMessage();
    }
}

echo "=== SAMS Full Database Migration ===\n\n";

// Core tables
run_migration($db, 'tenants', "CREATE TABLE IF NOT EXISTS tenants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    domain VARCHAR(255) DEFAULT NULL,
    settings JSON DEFAULT NULL,
    status ENUM('active','inactive','suspended') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $created, $errors);

run_migration($db, 'attendance_otp', "CREATE TABLE IF NOT EXISTS attendance_otp (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT DEFAULT 1,
    teacher_id INT NOT NULL,
    class_id INT NOT NULL,
    attendance_date DATE NOT NULL,
    otp VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    attempts INT DEFAULT 0,
    is_used TINYINT(1) DEFAULT 0,
    used_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_teacher_class (teacher_id, class_id, attendance_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $created, $errors);

run_migration($db, 'attendance_snapshots', "CREATE TABLE IF NOT EXISTS attendance_snapshots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT DEFAULT 1,
    class_id INT NOT NULL,
    attendance_date DATE NOT NULL,
    snapshot_type ENUM('before','after') NOT NULL,
    teacher_id INT NOT NULL,
    snapshot_data JSON DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_class_date (class_id, attendance_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $created, $errors);

run_migration($db, 'attendance_review_flags', "CREATE TABLE IF NOT EXISTS attendance_review_flags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT DEFAULT 1,
    class_id INT NOT NULL,
    attendance_date DATE NOT NULL,
    teacher_id INT NOT NULL,
    anomalies JSON DEFAULT NULL,
    status ENUM('pending_review','approved','rejected') DEFAULT 'pending_review',
    reviewed_by INT DEFAULT NULL,
    review_notes TEXT DEFAULT NULL,
    reviewed_at DATETIME DEFAULT NULL,
    flagged_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_teacher (teacher_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $created, $errors);

run_migration($db, 'attendance_versions', "CREATE TABLE IF NOT EXISTS attendance_versions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT DEFAULT 1,
    attendance_record_id INT NOT NULL,
    student_id INT NOT NULL,
    class_id INT NOT NULL,
    attendance_date DATE NOT NULL,
    old_status VARCHAR(50) DEFAULT NULL,
    new_status VARCHAR(50) NOT NULL,
    edited_by INT NOT NULL,
    edit_reason TEXT DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_record (attendance_record_id),
    INDEX idx_student_date (student_id, attendance_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $created, $errors);

run_migration($db, 'json_metadata', "CREATE TABLE IF NOT EXISTS json_metadata (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT DEFAULT 1,
    entity_type VARCHAR(100) NOT NULL,
    entity_id INT NOT NULL,
    metadata JSON DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_entity (tenant_id, entity_type, entity_id),
    INDEX idx_entity_type (entity_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $created, $errors);

run_migration($db, 'ai_knowledge_base', "CREATE TABLE IF NOT EXISTS ai_knowledge_base (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    module VARCHAR(100) DEFAULT 'general',
    category VARCHAR(100) DEFAULT NULL,
    keywords TEXT DEFAULT NULL,
    author_id INT DEFAULT NULL,
    tenant_id INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_module (module),
    INDEX idx_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $created, $errors);

run_migration($db, 'ai_training_logs', "CREATE TABLE IF NOT EXISTS ai_training_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    module VARCHAR(100) NOT NULL,
    description TEXT DEFAULT NULL,
    data_source VARCHAR(100) DEFAULT 'system',
    status ENUM('pending','running','completed','failed') DEFAULT 'pending',
    accuracy DECIMAL(5,2) DEFAULT NULL,
    duration_seconds INT DEFAULT NULL,
    trained_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $created, $errors);

run_migration($db, 'security_logs', "CREATE TABLE IF NOT EXISTS security_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT DEFAULT 1,
    user_id INT DEFAULT NULL,
    event_type VARCHAR(100) NOT NULL,
    details JSON DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_event_type (event_type),
    INDEX idx_user (user_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $created, $errors);

run_migration($db, 'audit_logs', "CREATE TABLE IF NOT EXISTS audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT DEFAULT 1,
    user_id INT DEFAULT NULL,
    action VARCHAR(255) NOT NULL,
    details TEXT DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent TEXT DEFAULT NULL,
    before_state JSON DEFAULT NULL,
    after_state JSON DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_action (action),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $created, $errors);

// Library tables
run_migration($db, 'library_books', "CREATE TABLE IF NOT EXISTS library_books (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT DEFAULT 1,
    title VARCHAR(255) NOT NULL,
    author VARCHAR(255) DEFAULT NULL,
    isbn VARCHAR(50) DEFAULT NULL,
    category VARCHAR(100) DEFAULT NULL,
    publisher VARCHAR(255) DEFAULT NULL,
    publish_year INT DEFAULT NULL,
    total_copies INT DEFAULT 1,
    available_copies INT DEFAULT 1,
    shelf_location VARCHAR(100) DEFAULT NULL,
    status ENUM('available','checked_out','reserved','lost') DEFAULT 'available',
    added_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_isbn (isbn),
    INDEX idx_category (category),
    INDEX idx_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $created, $errors);

run_migration($db, 'library_loans', "CREATE TABLE IF NOT EXISTS library_loans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT DEFAULT 1,
    book_id INT NOT NULL,
    student_id INT NOT NULL,
    loan_date DATE NOT NULL,
    due_date DATE NOT NULL,
    return_date DATE DEFAULT NULL,
    status ENUM('active','returned','overdue','lost') DEFAULT 'active',
    issued_by INT DEFAULT NULL,
    fine_amount DECIMAL(10,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_book (book_id),
    INDEX idx_student (student_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $created, $errors);

run_migration($db, 'library_categories', "CREATE TABLE IF NOT EXISTS library_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT DEFAULT 1,
    name VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $created, $errors);

// Financial tables
run_migration($db, 'fee_payments', "CREATE TABLE IF NOT EXISTS fee_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT DEFAULT 1,
    student_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_type VARCHAR(100) DEFAULT 'tuition',
    payment_method VARCHAR(50) DEFAULT 'cash',
    reference_number VARCHAR(100) DEFAULT NULL,
    status ENUM('paid','pending','partial','refunded') DEFAULT 'paid',
    description TEXT DEFAULT NULL,
    received_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_student (student_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $created, $errors);

run_migration($db, 'fee_structure', "CREATE TABLE IF NOT EXISTS fee_structure (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT DEFAULT 1,
    name VARCHAR(255) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    fee_type VARCHAR(100) DEFAULT 'tuition',
    class_id INT DEFAULT NULL,
    term VARCHAR(50) DEFAULT NULL,
    academic_year VARCHAR(20) DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $created, $errors);

run_migration($db, 'invoices', "CREATE TABLE IF NOT EXISTS invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT DEFAULT 1,
    invoice_number VARCHAR(50) NOT NULL,
    student_id INT NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    paid_amount DECIMAL(10,2) DEFAULT 0.00,
    balance DECIMAL(10,2) DEFAULT 0.00,
    status ENUM('unpaid','partial','paid','cancelled') DEFAULT 'unpaid',
    due_date DATE DEFAULT NULL,
    issued_by INT DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_invoice_number (tenant_id, invoice_number),
    INDEX idx_student (student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $created, $errors);

run_migration($db, 'expenses', "CREATE TABLE IF NOT EXISTS expenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT DEFAULT 1,
    category VARCHAR(100) NOT NULL,
    description TEXT DEFAULT NULL,
    amount DECIMAL(10,2) NOT NULL,
    receipt_number VARCHAR(100) DEFAULT NULL,
    payment_date DATE DEFAULT NULL,
    approved_by INT DEFAULT NULL,
    recorded_by INT DEFAULT NULL,
    status ENUM('pending','approved','rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $created, $errors);

run_migration($db, 'ledger_entries', "CREATE TABLE IF NOT EXISTS ledger_entries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT DEFAULT 1,
    entry_type ENUM('debit','credit') NOT NULL,
    account VARCHAR(100) NOT NULL,
    description TEXT DEFAULT NULL,
    amount DECIMAL(10,2) NOT NULL,
    reference VARCHAR(100) DEFAULT NULL,
    recorded_by INT DEFAULT NULL,
    entry_date DATE DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_account (account),
    INDEX idx_type (entry_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $created, $errors);

run_migration($db, 'payroll', "CREATE TABLE IF NOT EXISTS payroll (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT DEFAULT 1,
    employee_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    period VARCHAR(50) DEFAULT NULL,
    payment_date DATE DEFAULT NULL,
    deductions DECIMAL(10,2) DEFAULT 0.00,
    net_amount DECIMAL(10,2) DEFAULT 0.00,
    status ENUM('pending','processed','paid') DEFAULT 'pending',
    processed_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_employee (employee_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $created, $errors);

// Transport tables
run_migration($db, 'transport_routes', "CREATE TABLE IF NOT EXISTS transport_routes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT DEFAULT 1,
    route_name VARCHAR(255) NOT NULL,
    start_location VARCHAR(255) DEFAULT NULL,
    end_location VARCHAR(255) DEFAULT NULL,
    stops TEXT DEFAULT NULL,
    distance_km DECIMAL(8,2) DEFAULT NULL,
    estimated_time INT DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $created, $errors);

run_migration($db, 'transport_vehicles', "CREATE TABLE IF NOT EXISTS transport_vehicles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT DEFAULT 1,
    vehicle_number VARCHAR(50) NOT NULL,
    vehicle_type VARCHAR(50) DEFAULT 'bus',
    capacity INT DEFAULT 40,
    driver_name VARCHAR(255) DEFAULT NULL,
    driver_phone VARCHAR(20) DEFAULT NULL,
    route_id INT DEFAULT NULL,
    status ENUM('active','maintenance','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_route (route_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $created, $errors);

run_migration($db, 'transport_drivers', "CREATE TABLE IF NOT EXISTS transport_drivers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT DEFAULT 1,
    name VARCHAR(255) NOT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    license_number VARCHAR(50) DEFAULT NULL,
    license_expiry DATE DEFAULT NULL,
    vehicle_id INT DEFAULT NULL,
    status ENUM('active','on_leave','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $created, $errors);

run_migration($db, 'transport_allocations', "CREATE TABLE IF NOT EXISTS transport_allocations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT DEFAULT 1,
    student_id INT NOT NULL,
    route_id INT NOT NULL,
    pickup_point VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_student_route (student_id, route_id),
    INDEX idx_route (route_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $created, $errors);

run_migration($db, 'trip_logs', "CREATE TABLE IF NOT EXISTS trip_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT DEFAULT 1,
    vehicle_id INT NOT NULL,
    route_id INT NOT NULL,
    driver_id INT DEFAULT NULL,
    trip_date DATE NOT NULL,
    departure_time TIME DEFAULT NULL,
    arrival_time TIME DEFAULT NULL,
    passengers INT DEFAULT 0,
    notes TEXT DEFAULT NULL,
    status ENUM('scheduled','in_progress','completed','cancelled') DEFAULT 'scheduled',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_vehicle (vehicle_id),
    INDEX idx_date (trip_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $created, $errors);

run_migration($db, 'vehicle_maintenance', "CREATE TABLE IF NOT EXISTS vehicle_maintenance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT DEFAULT 1,
    vehicle_id INT NOT NULL,
    maintenance_type VARCHAR(100) NOT NULL,
    description TEXT DEFAULT NULL,
    cost DECIMAL(10,2) DEFAULT 0.00,
    maintenance_date DATE DEFAULT NULL,
    next_due_date DATE DEFAULT NULL,
    performed_by VARCHAR(255) DEFAULT NULL,
    status ENUM('scheduled','in_progress','completed') DEFAULT 'scheduled',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_vehicle (vehicle_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $created, $errors);

// Forum tables
run_migration($db, 'forum_categories', "CREATE TABLE IF NOT EXISTS forum_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT DEFAULT 1,
    name VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    sort_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $created, $errors);

run_migration($db, 'forum_threads', "CREATE TABLE IF NOT EXISTS forum_threads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT DEFAULT 1,
    category_id INT DEFAULT NULL,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    author_id INT NOT NULL,
    is_pinned TINYINT(1) DEFAULT 0,
    is_locked TINYINT(1) DEFAULT 0,
    is_reported TINYINT(1) DEFAULT 0,
    view_count INT DEFAULT 0,
    reply_count INT DEFAULT 0,
    status ENUM('active','hidden','deleted') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_category (category_id),
    INDEX idx_author (author_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $created, $errors);

run_migration($db, 'forum_replies', "CREATE TABLE IF NOT EXISTS forum_replies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT DEFAULT 1,
    thread_id INT NOT NULL,
    author_id INT NOT NULL,
    content TEXT NOT NULL,
    is_reported TINYINT(1) DEFAULT 0,
    status ENUM('active','hidden','deleted') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_thread (thread_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $created, $errors);

run_migration($db, 'forum_reports', "CREATE TABLE IF NOT EXISTS forum_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT DEFAULT 1,
    thread_id INT DEFAULT NULL,
    reply_id INT DEFAULT NULL,
    reported_by INT NOT NULL,
    reason TEXT DEFAULT NULL,
    status ENUM('pending','reviewed','dismissed','action_taken') DEFAULT 'pending',
    reviewed_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $created, $errors);

run_migration($db, 'user_warnings', "CREATE TABLE IF NOT EXISTS user_warnings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT DEFAULT 1,
    user_id INT NOT NULL,
    warned_by INT NOT NULL,
    reason TEXT NOT NULL,
    severity ENUM('low','medium','high') DEFAULT 'medium',
    expires_at DATE DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $created, $errors);

run_migration($db, 'banned_users', "CREATE TABLE IF NOT EXISTS banned_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT DEFAULT 1,
    user_id INT NOT NULL,
    banned_by INT NOT NULL,
    reason TEXT NOT NULL,
    banned_until DATE DEFAULT NULL,
    is_permanent TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $created, $errors);

// User theme preferences
run_migration($db, 'user_preferences', "CREATE TABLE IF NOT EXISTS user_preferences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    tenant_id INT DEFAULT 1,
    theme VARCHAR(50) DEFAULT 'light',
    primary_color VARCHAR(20) DEFAULT '#4F46E5',
    sidebar_collapsed TINYINT(1) DEFAULT 0,
    notifications_enabled TINYINT(1) DEFAULT 1,
    language VARCHAR(10) DEFAULT 'en',
    settings JSON DEFAULT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $created, $errors);

// Incident tracking 
run_migration($db, 'system_incidents', "CREATE TABLE IF NOT EXISTS system_incidents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT DEFAULT 1,
    incident_type VARCHAR(100) NOT NULL,
    severity ENUM('low','medium','high','critical') DEFAULT 'medium',
    title VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    affected_module VARCHAR(100) DEFAULT NULL,
    reported_by INT DEFAULT NULL,
    resolved_by INT DEFAULT NULL,
    status ENUM('open','investigating','resolved','closed') DEFAULT 'open',
    resolved_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_severity (severity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $created, $errors);

// PWA sync queue
run_migration($db, 'offline_sync_queue', "CREATE TABLE IF NOT EXISTS offline_sync_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT DEFAULT 1,
    user_id INT NOT NULL,
    action_type VARCHAR(100) NOT NULL,
    payload JSON NOT NULL,
    status ENUM('pending','synced','failed') DEFAULT 'pending',
    synced_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_status (user_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $created, $errors);

// System notifications
run_migration($db, 'system_notifications', "CREATE TABLE IF NOT EXISTS system_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT DEFAULT 1,
    user_id INT DEFAULT NULL,
    role VARCHAR(50) DEFAULT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT DEFAULT NULL,
    type VARCHAR(50) DEFAULT 'info',
    is_read TINYINT(1) DEFAULT 0,
    link VARCHAR(500) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $created, $errors);

echo "Created/verified: " . count($created) . " tables\n";
foreach ($created as $t) echo "  [OK] $t\n";

if (!empty($errors)) {
    echo "\nErrors: " . count($errors) . "\n";
    foreach ($errors as $e) echo "  [ERR] $e\n";
}

echo "\nMigration complete.\n";
