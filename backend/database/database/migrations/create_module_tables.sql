-- =====================================================
-- Multi-Tenant Module Tables Migration
-- Library, Transport, Accounting, Forum enhancements
-- =====================================================

-- ===== LIBRARY MODULE =====
CREATE TABLE IF NOT EXISTS library_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT DEFAULT 1,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    parent_id INT DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS library_books (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT DEFAULT 1,
    isbn VARCHAR(20),
    title VARCHAR(255) NOT NULL,
    author VARCHAR(255),
    publisher VARCHAR(255),
    edition VARCHAR(50),
    category_id INT,
    total_copies INT DEFAULT 1,
    available_copies INT DEFAULT 1,
    shelf_location VARCHAR(50),
    cover_image VARCHAR(255),
    description TEXT,
    publish_year YEAR,
    is_digital TINYINT(1) DEFAULT 0,
    digital_url VARCHAR(500),
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tenant (tenant_id),
    INDEX idx_category (category_id),
    INDEX idx_isbn (isbn),
    FOREIGN KEY (category_id) REFERENCES library_categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS library_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT DEFAULT 1,
    user_id INT NOT NULL,
    membership_type ENUM('student','teacher','staff') DEFAULT 'student',
    max_books INT DEFAULT 3,
    is_active TINYINT(1) DEFAULT 1,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tenant (tenant_id),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS library_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT DEFAULT 1,
    book_id INT NOT NULL,
    user_id INT NOT NULL,
    type ENUM('issue','return','renew','reserve') DEFAULT 'issue',
    issue_date DATE,
    due_date DATE,
    return_date DATE,
    status ENUM('issued','returned','overdue','lost') DEFAULT 'issued',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tenant (tenant_id),
    INDEX idx_book (book_id),
    INDEX idx_user (user_id),
    INDEX idx_status (status),
    FOREIGN KEY (book_id) REFERENCES library_books(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS library_fines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT DEFAULT 1,
    transaction_id INT NOT NULL,
    user_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    reason VARCHAR(255),
    status ENUM('pending','paid','waived') DEFAULT 'pending',
    paid_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tenant (tenant_id),
    FOREIGN KEY (transaction_id) REFERENCES library_transactions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS library_reservations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT DEFAULT 1,
    book_id INT NOT NULL,
    user_id INT NOT NULL,
    status ENUM('active','fulfilled','cancelled','expired') DEFAULT 'active',
    reserved_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    INDEX idx_tenant (tenant_id),
    FOREIGN KEY (book_id) REFERENCES library_books(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===== TRANSPORT MODULE =====
CREATE TABLE IF NOT EXISTS transport_routes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT DEFAULT 1,
    route_name VARCHAR(100) NOT NULL,
    route_code VARCHAR(20),
    start_point VARCHAR(255),
    end_point VARCHAR(255),
    stops TEXT COMMENT 'JSON array of stop names',
    distance_km DECIMAL(8,2),
    estimated_time_min INT,
    fare DECIMAL(10,2) DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS transport_vehicles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT DEFAULT 1,
    vehicle_number VARCHAR(20) NOT NULL,
    vehicle_type ENUM('bus','van','minibus','car') DEFAULT 'bus',
    make VARCHAR(100),
    model VARCHAR(100),
    year_of_manufacture YEAR,
    capacity INT DEFAULT 40,
    fuel_type ENUM('diesel','petrol','electric','cng') DEFAULT 'diesel',
    insurance_expiry DATE,
    fitness_expiry DATE,
    status ENUM('active','maintenance','retired') DEFAULT 'active',
    gps_tracker_id VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tenant (tenant_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS transport_drivers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT DEFAULT 1,
    user_id INT,
    name VARCHAR(100) NOT NULL,
    license_number VARCHAR(50),
    license_expiry DATE,
    phone VARCHAR(20),
    emergency_contact VARCHAR(20),
    address TEXT,
    status ENUM('active','on_leave','terminated') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS transport_allocations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT DEFAULT 1,
    student_id INT NOT NULL,
    route_id INT NOT NULL,
    stop_name VARCHAR(100),
    pickup_time TIME,
    drop_time TIME,
    academic_year VARCHAR(10),
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tenant (tenant_id),
    INDEX idx_student (student_id),
    FOREIGN KEY (route_id) REFERENCES transport_routes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS transport_trips (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT DEFAULT 1,
    route_id INT NOT NULL,
    vehicle_id INT NOT NULL,
    driver_id INT,
    trip_date DATE NOT NULL,
    trip_type ENUM('morning_pickup','afternoon_drop','special') DEFAULT 'morning_pickup',
    departure_time TIME,
    arrival_time TIME,
    status ENUM('scheduled','in_progress','completed','cancelled') DEFAULT 'scheduled',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tenant (tenant_id),
    INDEX idx_date (trip_date),
    FOREIGN KEY (route_id) REFERENCES transport_routes(id),
    FOREIGN KEY (vehicle_id) REFERENCES transport_vehicles(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS transport_maintenance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT DEFAULT 1,
    vehicle_id INT NOT NULL,
    type ENUM('routine','repair','emergency') DEFAULT 'routine',
    description TEXT,
    cost DECIMAL(10,2) DEFAULT 0,
    scheduled_date DATE,
    completed_date DATE,
    status ENUM('scheduled','in_progress','completed') DEFAULT 'scheduled',
    vendor VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tenant (tenant_id),
    FOREIGN KEY (vehicle_id) REFERENCES transport_vehicles(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS transport_fuel_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT DEFAULT 1,
    vehicle_id INT NOT NULL,
    fill_date DATE NOT NULL,
    quantity_liters DECIMAL(8,2),
    cost_per_liter DECIMAL(8,2),
    total_cost DECIMAL(10,2),
    odometer_reading INT,
    station VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tenant (tenant_id),
    FOREIGN KEY (vehicle_id) REFERENCES transport_vehicles(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===== ACCOUNTING MODULE =====
CREATE TABLE IF NOT EXISTS ledger_entries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT DEFAULT 1,
    entry_date DATE NOT NULL,
    account_name VARCHAR(100) NOT NULL,
    account_code VARCHAR(20),
    type ENUM('debit','credit') NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    description TEXT,
    reference_number VARCHAR(50),
    category ENUM('income','expense','asset','liability','equity') DEFAULT 'income',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tenant (tenant_id),
    INDEX idx_date (entry_date),
    INDEX idx_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS expenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT DEFAULT 1,
    expense_date DATE NOT NULL,
    category VARCHAR(100),
    description TEXT,
    amount DECIMAL(12,2) NOT NULL,
    payment_method ENUM('cash','bank_transfer','cheque','card') DEFAULT 'cash',
    receipt_number VARCHAR(50),
    vendor VARCHAR(255),
    approved_by INT,
    status ENUM('pending','approved','rejected','paid') DEFAULT 'pending',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tenant (tenant_id),
    INDEX idx_date (expense_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS expense_approvals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT DEFAULT 1,
    expense_id INT NOT NULL,
    approver_id INT NOT NULL,
    status ENUM('pending','approved','rejected') DEFAULT 'pending',
    comments TEXT,
    acted_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tenant (tenant_id),
    FOREIGN KEY (expense_id) REFERENCES expenses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS payroll (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT DEFAULT 1,
    user_id INT NOT NULL,
    pay_period VARCHAR(20) NOT NULL COMMENT 'e.g. 2026-03',
    basic_salary DECIMAL(12,2) DEFAULT 0,
    allowances DECIMAL(12,2) DEFAULT 0,
    deductions DECIMAL(12,2) DEFAULT 0,
    tax DECIMAL(12,2) DEFAULT 0,
    net_pay DECIMAL(12,2) DEFAULT 0,
    amount DECIMAL(12,2) DEFAULT 0 COMMENT 'alias for net_pay',
    payment_date DATE,
    status ENUM('draft','processed','paid') DEFAULT 'draft',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tenant (tenant_id),
    INDEX idx_user (user_id),
    INDEX idx_period (pay_period)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS budget_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT DEFAULT 1,
    fiscal_year VARCHAR(10) NOT NULL,
    category VARCHAR(100),
    description TEXT,
    budgeted_amount DECIMAL(12,2) DEFAULT 0,
    actual_amount DECIMAL(12,2) DEFAULT 0,
    variance DECIMAL(12,2) GENERATED ALWAYS AS (budgeted_amount - actual_amount) STORED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tenant (tenant_id),
    INDEX idx_year (fiscal_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS scholarships (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT DEFAULT 1,
    student_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    type ENUM('full','partial','merit','need_based') DEFAULT 'partial',
    percentage DECIMAL(5,2) DEFAULT 0,
    amount DECIMAL(12,2) DEFAULT 0,
    academic_year VARCHAR(10),
    status ENUM('active','expired','revoked') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tenant (tenant_id),
    INDEX idx_student (student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===== FEE MODULE (if not already existing) =====
CREATE TABLE IF NOT EXISTS fee_structures (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT DEFAULT 1,
    name VARCHAR(100) NOT NULL,
    class_id INT,
    academic_year VARCHAR(10),
    tuition DECIMAL(12,2) DEFAULT 0,
    lab_fee DECIMAL(10,2) DEFAULT 0,
    library_fee DECIMAL(10,2) DEFAULT 0,
    transport_fee DECIMAL(10,2) DEFAULT 0,
    misc_fee DECIMAL(10,2) DEFAULT 0,
    total DECIMAL(12,2) DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS fee_invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT DEFAULT 1,
    student_id INT NOT NULL,
    invoice_number VARCHAR(30),
    fee_structure_id INT,
    amount DECIMAL(12,2) NOT NULL,
    due_date DATE,
    status ENUM('pending','partial','paid','overdue','cancelled') DEFAULT 'pending',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tenant (tenant_id),
    INDEX idx_student (student_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS fee_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT DEFAULT 1,
    student_id INT NOT NULL,
    invoice_id INT,
    amount DECIMAL(12,2) NOT NULL,
    payment_method ENUM('cash','bank_transfer','card','cheque','online') DEFAULT 'cash',
    reference_number VARCHAR(50),
    payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    received_by INT,
    notes TEXT,
    INDEX idx_tenant (tenant_id),
    INDEX idx_student (student_id),
    INDEX idx_date (payment_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===== FORUM ENHANCEMENTS =====
-- Add tenant_id to forum_categories if missing
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'forum_categories' AND COLUMN_NAME = 'tenant_id');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE forum_categories ADD COLUMN tenant_id INT DEFAULT 1 AFTER id', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS forum_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT DEFAULT 1,
    post_id INT,
    thread_id INT,
    reporter_id INT NOT NULL,
    reason TEXT,
    status ENUM('pending','reviewed','dismissed','actioned') DEFAULT 'pending',
    moderator_id INT,
    resolution TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tenant (tenant_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS forum_warnings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT DEFAULT 1,
    user_id INT NOT NULL,
    issued_by INT NOT NULL,
    reason TEXT,
    severity ENUM('mild','moderate','severe') DEFAULT 'mild',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tenant (tenant_id),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS forum_bans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT DEFAULT 1,
    user_id INT NOT NULL,
    banned_by INT NOT NULL,
    reason TEXT,
    expires_at TIMESTAMP NULL COMMENT 'NULL = permanent',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tenant (tenant_id),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS forum_user_stats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT DEFAULT 1,
    user_id INT NOT NULL UNIQUE,
    posts_count INT DEFAULT 0,
    threads_count INT DEFAULT 0,
    warnings_count INT DEFAULT 0,
    reputation INT DEFAULT 0,
    last_activity TIMESTAMP NULL,
    INDEX idx_tenant (tenant_id),
    INDEX idx_last_activity (last_activity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default library categories
INSERT IGNORE INTO library_categories (tenant_id, name, description) VALUES
(1, 'Fiction', 'Novels, short stories, and literary fiction'),
(1, 'Non-Fiction', 'Biographies, history, science, and reference'),
(1, 'Textbooks', 'Academic textbooks and study guides'),
(1, 'Periodicals', 'Magazines, journals, and newspapers'),
(1, 'Digital', 'E-books and digital resources');
