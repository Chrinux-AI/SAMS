<?php
/**
 * Database Migration - Add missing tables and columns for user management system
 * Run once to set up the database schema.
 * 
 * Access: CLI or authenticated admin only.
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/database.php';

// Allow CLI execution; otherwise require admin login.
$isCli = (php_sapi_name() === 'cli');
if (!$isCli) {
    if (!is_logged_in() || !has_role('admin') && !has_role('super_admin')) {
        http_response_code(403);
        echo 'Access denied. Admin login required.';
        exit;
    }
}

$pdo = db()->getConnection();
$results = [];

// Create migration tracking table if it doesn't exist
$pdo->exec("CREATE TABLE IF NOT EXISTS migration_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    migration_name VARCHAR(255) NOT NULL,
    result ENUM('ok','skip','error') NOT NULL DEFAULT 'ok',
    message TEXT NULL,
    ran_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_name (migration_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

function run_migration($pdo, $sql, $desc, &$results) {
    try {
        $pdo->exec($sql);
        $results[] = "OK: $desc";
        $status = 'ok';
        $msg = null;
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'already exists') !== false || strpos($e->getMessage(), 'Duplicate column') !== false) {
            $results[] = "SKIP: $desc (already exists)";
            $status = 'skip';
            $msg = 'already exists';
        } else {
            $results[] = "ERR: $desc - " . $e->getMessage();
            $status = 'error';
            $msg = $e->getMessage();
        }
    }
    // Record in migration_log
    try {
        $stmt = $pdo->prepare("INSERT INTO migration_log (migration_name, result, message) VALUES (?, ?, ?)");
        $stmt->execute([$desc, $status, $msg]);
    } catch (PDOException $e) {
        // Non-critical — don't block migration
        error_log("Migration log insert failed: " . $e->getMessage());
    }
}

// 1. Add missing columns to users table
run_migration($pdo, "ALTER TABLE users ADD COLUMN token_expiry DATETIME NULL DEFAULT NULL AFTER verification_token", "Add token_expiry to users", $results);
run_migration($pdo, "ALTER TABLE users MODIFY verification_token VARCHAR(255) NULL DEFAULT NULL", "Widen verification_token to 255", $results);
run_migration($pdo, "ALTER TABLE users ADD COLUMN password_set_at DATETIME NULL DEFAULT NULL AFTER verification_token", "Add password_set_at to users", $results);

// 2. Create class_enrollments table
run_migration($pdo, "
    CREATE TABLE IF NOT EXISTS class_enrollments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        class_id INT NOT NULL,
        student_id INT NOT NULL,
        enrolled_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        status ENUM('active','dropped','completed') NOT NULL DEFAULT 'active',
        enrolled_by INT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_enrollment (class_id, student_id),
        INDEX idx_class (class_id),
        INDEX idx_student (student_id),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
", "Create class_enrollments table", $results);

// 3. Create account_activations table
run_migration($pdo, "
    CREATE TABLE IF NOT EXISTS account_activations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        activation_method VARCHAR(50) NOT NULL DEFAULT 'otp',
        activated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        ip_address VARCHAR(45) NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
", "Create account_activations table", $results);

// 4. Create google_form_submissions table for AI extraction
run_migration($pdo, "
    CREATE TABLE IF NOT EXISTS google_form_submissions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        form_response_id VARCHAR(255) NULL,
        raw_data JSON NULL,
        extracted_data JSON NULL,
        processing_status ENUM('pending','processing','completed','failed') NOT NULL DEFAULT 'pending',
        error_message TEXT NULL,
        created_user_id INT NULL,
        processed_at DATETIME NULL,
        processed_by INT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_status (processing_status),
        INDEX idx_user (created_user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
", "Create google_form_submissions table", $results);

// 5. Backfill: Enroll existing students into their classes
try {
    $students_with_classes = $pdo->query("SELECT id AS student_id, class_id FROM students WHERE class_id IS NOT NULL AND class_id > 0")->fetchAll(PDO::FETCH_ASSOC);
    $enrolled = 0;
    foreach ($students_with_classes as $s) {
        try {
            $pdo->prepare("INSERT IGNORE INTO class_enrollments (class_id, student_id, status) VALUES (?, ?, 'active')")
                ->execute([$s['class_id'], $s['student_id']]);
            $enrolled++;
        } catch (PDOException $e) { /* ignore duplicates */ }
    }
    $results[] = "OK: Backfilled $enrolled student enrollments";
} catch (PDOException $e) {
    $results[] = "ERR: Backfill - " . $e->getMessage();
}

// Output results
echo "<h2>Migration Results</h2><pre>";
foreach ($results as $r) {
    $color = strpos($r, 'OK:') === 0 ? 'green' : (strpos($r, 'SKIP:') === 0 ? 'orange' : 'red');
    echo "<span style='color:$color'>$r</span>\n";
}
echo "</pre>";
echo "<p><a href='admin/dashboard.php'>Go to Dashboard</a></p>";
