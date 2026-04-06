-- Fee Management System Tables
-- Created: 2025
-- Purpose: Student fees, payments, and tracking

-- Student Fees Table
CREATE TABLE IF NOT EXISTS student_fees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    class_id INT NULL,
    fee_type ENUM('tuition', 'books', 'uniform', 'transport', 'activities', 'examination', 'library', 'laboratory', 'other') NOT NULL DEFAULT 'tuition',
    amount DECIMAL(10,2) NOT NULL,
    amount_paid DECIMAL(10,2) DEFAULT 0.00,
    due_date DATE NOT NULL,
    status ENUM('pending', 'partial', 'paid', 'overdue') NOT NULL DEFAULT 'pending',
    description TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE SET NULL,
    INDEX idx_student_fees (student_id),
    INDEX idx_fee_status (status),
    INDEX idx_due_date (due_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Fee Payments Table
CREATE TABLE IF NOT EXISTS fee_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fee_id INT NOT NULL,
    amount_paid DECIMAL(10,2) NOT NULL,
    payment_method ENUM('credit_card', 'debit_card', 'bank_transfer', 'paypal', 'cash', 'check') NOT NULL,
    payment_date DATETIME NOT NULL,
    payment_status ENUM('pending', 'completed', 'failed', 'refunded') NOT NULL DEFAULT 'completed',
    transaction_id VARCHAR(100) NULL,
    payment_reference VARCHAR(100) NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (fee_id) REFERENCES student_fees(id) ON DELETE CASCADE,
    INDEX idx_fee_payments (fee_id),
    INDEX idx_transaction (transaction_id),
    INDEX idx_payment_date (payment_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Parent-Student Links (if not exists)
CREATE TABLE IF NOT EXISTS parent_student (
    id INT AUTO_INCREMENT PRIMARY KEY,
    parent_id INT NOT NULL,
    student_id INT NOT NULL,
    relationship ENUM('mother', 'father', 'guardian', 'other') NOT NULL DEFAULT 'guardian',
    is_primary BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES parents(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    UNIQUE KEY unique_parent_student (parent_id, student_id),
    INDEX idx_parent (parent_id),
    INDEX idx_student (student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert sample fees for testing
INSERT INTO student_fees (student_id, class_id, fee_type, amount, due_date, description)
SELECT
    s.id,
    ce.class_id,
    'tuition',
    1500.00,
    DATE_ADD(CURDATE(), INTERVAL 30 DAY),
    'Semester Tuition Fee'
FROM students s
LEFT JOIN class_enrollments ce ON s.id = ce.student_id
WHERE s.id IN (SELECT student_id FROM parent_student LIMIT 5)
ON DUPLICATE KEY UPDATE id=id;

INSERT INTO student_fees (student_id, class_id, fee_type, amount, due_date, description)
SELECT
    s.id,
    ce.class_id,
    'books',
    250.00,
    DATE_ADD(CURDATE(), INTERVAL 15 DAY),
    'Textbooks and Materials'
FROM students s
LEFT JOIN class_enrollments ce ON s.id = ce.student_id
WHERE s.id IN (SELECT student_id FROM parent_student LIMIT 5)
ON DUPLICATE KEY UPDATE id=id;
