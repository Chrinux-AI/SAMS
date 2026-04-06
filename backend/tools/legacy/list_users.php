<?php
require_once 'includes/config.php';
try {
    $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $stmt = $pdo->query("SELECT email, role FROM users WHERE role IN ('student', 'teacher', 'admin')");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach($users as $u) {
        echo "Email: " . $u['email'] . " | Role: " . $u['role'] . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
