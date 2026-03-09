<?php

/**
 * Logout Script
 */

session_start();

require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/database.php';

// Log activity before destroying session
if (isset($_SESSION['user_id'])) {
    log_activity($_SESSION['user_id'], 'logout', 'user', $_SESSION['user_id']);
}

// Destroy session
session_destroy();

// Redirect to login
redirect('login.php', 'You have been logged out successfully', 'success');
