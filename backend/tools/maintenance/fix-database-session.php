<?php
/**
 * Database Setup and Configuration Fix
 * Fixes database connection and session issues
 */

echo "<h2>SAMS Database & Session Fix Tool</h2>";

// 1. Check database connection
echo "<h3>1. Checking Database Connection...</h3>";

$database_name = 'attendance_system'; // Changed back to original
$host = 'localhost';
$user = 'root';
$pass = '';

try {
    // Test connection without specifying database first
    $dsn = "mysql:host=$host;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    
    echo "<p style='color: green;'>✓ MySQL connection successful</p>";
    
    // Check if database exists
    $stmt = $pdo->query("SHOW DATABASES LIKE '$database_name'");
    $db_exists = $stmt->rowCount() > 0;
    
    if ($db_exists) {
        echo "<p style='color: green;'>✓ Database '$database_name' exists</p>";
    } else {
        echo "<p style='color: orange;'>⚠ Database '$database_name' does not exist</p>";
        echo "<p>Creating database...</p>";
        
        try {
            $pdo->exec("CREATE DATABASE `$database_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            echo "<p style='color: green;'>✓ Database '$database_name' created successfully</p>";
        } catch (Exception $e) {
            echo "<p style='color: red;'>✗ Failed to create database: " . $e->getMessage() . "</p>";
        }
    }
    
    // Test connection with database
    $dsn_with_db = "mysql:host=$host;dbname=$database_name;charset=utf8mb4";
    $pdo_with_db = new PDO($dsn_with_db, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    
    echo "<p style='color: green;'>✓ Database connection with '$database_name' successful</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Database connection failed: " . $e->getMessage() . "</p>";
    echo "<p>Please check:</p>";
    echo "<ul>";
    echo "<li>MySQL/XAMPP is running</li>";
    echo "<li>Database credentials are correct</li>";
    echo "<li>Database name exists</li>";
    echo "</ul>";
}

// 2. Fix session issues
echo "<h3>2. Fixing Session Issues...</h3>";

// Check session status
$session_status = session_status();
echo "<p>Current session status: " . $session_status . " (1=NONE, 2=ACTIVE)</p>";

if ($session_status === PHP_SESSION_ACTIVE) {
    echo "<p style='color: orange;'>⚠ Session is already active</p>";
    echo "<p>This is causing the ini_set() warnings</p>";
    echo "<p>Solution: Move session_start() calls to bootstrap.php only</p>";
    
    // Show files that might be starting sessions
    echo "<h4>Files that might be starting sessions:</h4>";
    $session_files = [
        'teacher/dashboard.php',
        'student/dashboard.php', 
        'admin/dashboard.php',
        'parent/dashboard.php'
    ];
    
    foreach ($session_files as $file) {
        $file_path = __DIR__ . '/' . $file;
        if (file_exists($file_path)) {
            $content = file_get_contents($file_path);
            if (strpos($content, 'session_start()') !== false) {
                echo "<p style='color: orange;'>⚠ $file contains session_start()</p>";
            }
        }
    }
} else {
    echo "<p style='color: green;'>✓ Session not started yet</p>";
}

// 3. Update configuration files
echo "<h3>3. Updating Configuration Files...</h3>";

$config_file = __DIR__ . '/includes/config.php';
if (file_exists($config_file)) {
    $content = file_get_contents($config_file);
    
    // Ensure database name is correct
    if (strpos($content, "define('DB_NAME', 'attendance_system')") !== false) {
        echo "<p style='color: green;'>✓ Database name already correct in config.php</p>";
    } else {
        $content = str_replace("define('DB_NAME', 'sams_db')", "define('DB_NAME', 'attendance_system')", $content);
        if (file_put_contents($config_file, $content)) {
            echo "<p style='color: green;'>✓ Updated database name in config.php</p>";
        }
    }
}

// 4. Create database if needed
echo "<h3>4. Database Setup Assistance...</h3>";

echo "<p>If you're still getting database errors, run this SQL in phpMyAdmin:</p>";
echo "<pre style='background: #f5f5f5; padding: 10px; border: 1px solid #ccc;'>";
echo "-- Create database if it doesn't exist
CREATE DATABASE IF NOT EXISTS `attendance_system` 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

-- Use the database
USE `attendance_system`;

-- Show tables (should be empty for new setup)
SHOW TABLES;";
echo "</pre>";

// 5. Session fix recommendations
echo "<h3>5. Session Fix Recommendations...</h3>";

echo "<p>To fix session warnings permanently:</p>";
echo "<ol>";
echo "<li>Remove all <code>session_start()</code> calls from individual files</li>";
echo "<li>Use only <code>require_once '../core/bootstrap.php';</code> in all files</li>";
echo "<li>Bootstrap will handle session initialization safely</li>";
echo "<li>Update all role pages to use the new bootstrap system</li>";
echo "</ol>";

echo "<h3>Quick Fix Script</h3>";
echo "<p>Run this to automatically fix common issues:</p>";
echo "<pre style='background: #f5f5f5; padding: 10px; border: 1px solid #ccc;'>";
echo "# Find and replace session_start() calls
find . -name '*.php' -exec sed -i 's/session_start();/\/\/ session_start(); \/\/ Moved to bootstrap/g' {} \;

# Replace old config includes
find . -name '*.php' -exec sed -i 's/require_once.*config\.php/require_once \"..\/..\/core\/bootstrap.php\";/g' {} \;";
echo "</pre>";

echo "<div style='margin-top: 20px; padding: 15px; background: #e7f3ff; border-left: 4px solid #2196f3;'>";
echo "<h4>Next Steps:</h4>";
echo "<ol>";
echo "<li>Test database connection by refreshing any page</li>";
echo "<li>If database errors persist, check XAMPP MySQL service</li>";
echo "<li>Run the database creation SQL above if needed</li>";
echo "<li>Update remaining files to use bootstrap.php</li>";
echo "<li>Clear browser cookies and test again</li>";
echo "</ol>";
echo "</div>";

echo "<p><a href='javascript:history.back()'>Go Back</a> | <a href='../'>Test Main Site</a></p>";

?>
