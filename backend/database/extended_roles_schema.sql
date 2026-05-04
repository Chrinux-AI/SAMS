
-- Librarian
CREATE TABLE IF NOT EXISTS lib_books (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    isbn VARCHAR(50),
    author VARCHAR(255),
    copies_available INT DEFAULT 1,
    status ENUM('available', 'low', 'out') DEFAULT 'available',
    tenant_id INT NOT NULL
);

CREATE TABLE IF NOT EXISTS lib_loans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    book_id INT NOT NULL,
    student_id INT NOT NULL,
    issued_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    due_date DATETIME NOT NULL,
    returned_date DATETIME NULL,
    status ENUM('active', 'returned', 'overdue') DEFAULT 'active'
);

-- Nurse
CREATE TABLE IF NOT EXISTS health_incidents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    nurse_id INT NOT NULL,
    incident_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    symptoms TEXT,
    treatment_provided TEXT,
    requires_parental_notice BOOLEAN DEFAULT FALSE,
    tenant_id INT NOT NULL
);

CREATE TABLE IF NOT EXISTS health_records_extended (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL UNIQUE,
    allergies TEXT,
    blood_group VARCHAR(10),
    emergency_contact VARCHAR(255),
    tenant_id INT NOT NULL
);

-- Bursar
CREATE TABLE IF NOT EXISTS finance_invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    description VARCHAR(255) NOT NULL,
    due_date DATETIME NOT NULL,
    status ENUM('unpaid', 'partial', 'paid') DEFAULT 'unpaid',
    tenant_id INT NOT NULL
);

CREATE TABLE IF NOT EXISTS finance_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT NOT NULL,
    amount_paid DECIMAL(12,2) NOT NULL,
    payment_method VARCHAR(50),
    transaction_id VARCHAR(100),
    payment_date DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Transport
CREATE TABLE IF NOT EXISTS transport_fleet (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vehicle_plate VARCHAR(50) NOT NULL,
    capacity INT NOT NULL,
    driver_name VARCHAR(255),
    tenant_id INT NOT NULL
);

CREATE TABLE IF NOT EXISTS transport_routes_extended (
    id INT AUTO_INCREMENT PRIMARY KEY,
    route_name VARCHAR(255) NOT NULL,
    vehicle_id INT NOT NULL,
    morning_pickup_time TIME,
    evening_dropoff_time TIME,
    tenant_id INT NOT NULL
);

-- Forum Moderator
CREATE TABLE IF NOT EXISTS forum_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    reported_by INT NOT NULL,
    reason TEXT,
    status ENUM('pending', 'reviewed', 'dismissed', 'deleted') DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
