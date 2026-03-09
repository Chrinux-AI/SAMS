<?php
require_once 'includes/config.php';
try {
    $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $hash = password_hash('password123', PASSWORD_BCRYPT);

    $roles = ['admin', 'teacher', 'student', 'parent'];
    foreach ($roles as $role) {
        $email = $role . '@example.com';
        // Try to update if exists
        $stmt = $pdo->prepare("UPDATE users SET password = ?, status = 'active', email_verified = 1, approved = 1 WHERE email = ?");
        $stmt->execute([$hash, $email]);

        if ($stmt->rowCount() == 0) {
            // Insert if not exists
            $stmt = $pdo->prepare("INSERT IGNORE INTO users (email, password, role, first_name, last_name, status, email_verified, approved) VALUES (?, ?, ?, 'Test', ?, 'active', 1, 1)");
            $stmt->execute([$email, $hash, $role, ucfirst($role)]);
        }
        echo "Setup $email successfully.\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
