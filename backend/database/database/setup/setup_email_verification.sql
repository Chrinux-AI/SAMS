-- Add email verification and approval fields to users table
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS email_verified TINYINT(1) DEFAULT 0,
ADD COLUMN IF NOT EXISTS verification_token VARCHAR(64) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS approved TINYINT(1) DEFAULT 0,
ADD COLUMN IF NOT EXISTS approved_at TIMESTAMP NULL,
ADD COLUMN IF NOT EXISTS approved_by INT NULL;

-- Update students table to ensure student_id is unique and auto-generated
ALTER TABLE students 
MODIFY COLUMN student_id VARCHAR(20) DEFAULT NULL,
ADD UNIQUE KEY IF NOT EXISTS unique_student_id (student_id);

-- Create function to generate student ID (YEAR+0001 format)
DELIMITER $$
DROP FUNCTION IF EXISTS generate_student_id$$
CREATE FUNCTION generate_student_id() RETURNS VARCHAR(20)
DETERMINISTIC
BEGIN
    DECLARE new_id VARCHAR(20);
    DECLARE year_prefix VARCHAR(4);
    DECLARE max_number INT;
    
    SET year_prefix = YEAR(CURDATE());
    
    SELECT COALESCE(MAX(CAST(SUBSTRING(student_id, 5) AS UNSIGNED)), 0) INTO max_number
    FROM students 
    WHERE student_id LIKE CONCAT(year_prefix, '%');
    
    SET new_id = CONCAT(year_prefix, LPAD(max_number + 1, 4, '0'));
    RETURN new_id;
END$$
DELIMITER ;

