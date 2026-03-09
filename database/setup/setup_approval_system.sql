-- Add email verification and approval columns to users table
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS email_verified TINYINT(1) DEFAULT 0,
ADD COLUMN IF NOT EXISTS email_verification_token VARCHAR(64) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS approved TINYINT(1) DEFAULT 0,
ADD COLUMN IF NOT EXISTS approved_by INT DEFAULT NULL,
ADD COLUMN IF NOT EXISTS approved_at TIMESTAMP NULL DEFAULT NULL,
ADD COLUMN IF NOT EXISTS assigned_id VARCHAR(50) DEFAULT NULL;

-- Update students table to have better ID format
ALTER TABLE students
ADD COLUMN IF NOT EXISTS assigned_student_id VARCHAR(50) DEFAULT NULL;

