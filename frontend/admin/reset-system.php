<?php

/**
 * Database Reset & Clean Setup Script
 * This will delete all existing data and create fresh accounts
 */

session_start();
require_once '../includes/functions.php';
require_once '../includes/config.php';
require_once '../includes/database.php';
require_admin();

echo <<<HTML
<!DOCTYPE html>
<html>
<head>
    <script src="../assets/js/theme-loader.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="manifest" href="/attendance/manifest.json">
    <meta name="theme-color" content="#00BFFF">
    <link rel="apple-touch-icon" href="/attendance/assets/images/icons/icon-192x192.png">
    <title>System Reset</title>
    <link href="../assets/css/professional-ui.css" rel="stylesheet">
    <style>
        .log { margin: 10px 0; }
        .success { color: #00FF7F; }
        .error { color: #FF4500; }
        .info { color: #FFD700; }
    </style>
</head>
<body>
HTML;

include '../includes/sidebar-nav.php';

echo <<<HTML
<div class="app-layout"><div class="cyber-main">
<h1>System Reset & Database Clean Up</h1>
<div id='log'>
HTML;

try {
    $db = db();

    // Truncate all tables
    $tables = ['attendance_records', 'students', 'teachers', 'classes', 'users'];

    echo "<div class='log info'>Clearing all existing data...</div>";

    foreach ($tables as $table) {
        try {
            $db->query("TRUNCATE TABLE `$table`");
            echo "<div class='log success'>âœ“ Cleared table: $table</div>";
        } catch (Exception $e) {
            echo "<div class='log info'>â†’ Table $table already empty or doesn't exist</div>";
        }
    }

    echo "<br><div class='log info'>Creating fresh admin account...</div>";

    // Create admin account
    $admin_password = password_hash('admin123', PASSWORD_DEFAULT);
    $db->query(
        "INSERT INTO users (email, password, first_name, last_name, role, created_at)
         VALUES (:email, :password, :first_name, :last_name, :role, NOW())",
        [
            'email' => 'admin@attendance.com',
            'password' => $admin_password,
            'first_name' => 'System',
            'last_name' => 'Administrator',
            'role' => 'admin'
        ]
    );

    echo "<div class='log success'>âœ“ Admin account created</div>";
    echo "<div class='log success'>  â†’ Email: admin@attendance.com</div>";
    echo "<div class='log success'>  â†’ Password: admin123</div>";

    echo "<br><div class='log info'>Creating sample teacher account...</div>";

    // Create teacher account
    $teacher_password = password_hash('teacher123', PASSWORD_DEFAULT);
    $db->query(
        "INSERT INTO users (email, password, first_name, last_name, role, created_at)
         VALUES (:email, :password, :first_name, :last_name, :role, NOW())",
        [
            'email' => 'teacher@attendance.com',
            'password' => $teacher_password,
            'first_name' => 'John',
            'last_name' => 'Teacher',
            'role' => 'teacher'
        ]
    );

    echo "<div class='log success'>âœ“ Teacher account created</div>";
    echo "<div class='log success'>  â†’ Email: teacher@attendance.com</div>";
    echo "<div class='log success'>  â†’ Password: teacher123</div>";

    echo "<br><div class='log success'>âœ… DATABASE RESET COMPLETE!</div>";
    echo "<br><div class='log info'>You can now login with:</div>";
    echo "<div class='log info'>Admin: admin@attendance.com / admin123</div>";
    echo "<div class='log info'>Teacher: teacher@attendance.com / teacher123</div>";
    echo "<br><div class='log'><a href='../login.php' style='color: #00BFFF; text-decoration: underline;'>â†’ Go to Login Page</a></div>";
} catch (Exception $e) {
    echo "<div class='log error'>âœ— ERROR: " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo <<<HTML
</div>
</div></div>
    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/pwa-manager.js"></script>
    <script src="../assets/js/pwa-analytics.js"></script>
</body></html>
HTML;

