<?php
require_once 'includes/config.php';
try {
    $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $hash = password_hash('password123', PASSWORD_BCRYPT);
    $pdo->exec("INSERT IGNORE INTO users (email, password, role, first_name, last_name, status) VALUES ('teststudent@example.com', '$hash', 'student', 'Test', 'Student', 'active')");
    $pdo->exec("UPDATE users SET password = '$hash' WHERE email = 'teststudent@example.com'");
    echo "Test student created/updated.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
