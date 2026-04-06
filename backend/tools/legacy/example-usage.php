<?php
/**
 * Example Updated Include Usage
 * Shows how to use the new global bootstrap system
 */

// OLD WAY (causes session warnings and database errors):
// require_once '../includes/config.php';
// require_once '../includes/functions.php';
// require_once '../includes/database.php';

// NEW WAY (stable and safe):
require_once '../core/bootstrap.php';

// Now you have access to:
// - Safe database connection via db()
// - Proper session handling
// - Global error handling
// - Theme system
// - Layout components

// Example usage:
try {
    $users = db()->fetchAll("SELECT * FROM users WHERE is_active = 1");
    
    // Load layout components
    loadLayout('header');
    loadSidebar('admin');
    
    // Your page content here
    echo '<div class="sams-card">';
    echo '<h2>Users</h2>';
    echo '<table class="table">';
    echo '<tr><th>Name</th><th>Email</th><th>Role</th></tr>';
    
    foreach ($users as $user) {
        echo '<tr>';
        echo '<td>' . htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) . '</td>';
        echo '<td>' . htmlspecialchars($user['email']) . '</td>';
        echo '<td>' . htmlspecialchars($user['role']) . '</td>';
        echo '</tr>';
    }
    
    echo '</table>';
    echo '</div>';
    
    loadLayout('footer');
    
} catch (Exception $e) {
    // Error is already logged by bootstrap
    showNotification('Database error occurred', 'danger');
}

?>
