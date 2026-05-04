CREATE TABLE IF NOT EXISTS grades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    class_id INT NOT NULL,
    assignment_id INT NULL,
    points_earned DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    max_points DECIMAL(10,2) NOT NULL DEFAULT 100.00,
    percentage DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    letter_grade VARCHAR(5) NOT NULL,
    comments TEXT NULL,
    graded_by INT NOT NULL,
    grade_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_grades_student_class (student_id, class_id),
    INDEX idx_grades_class_date (class_id, grade_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
