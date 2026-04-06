<?php

/**
 * Admin Header Component
 * Include this file in all admin pages for consistent styling and structure
 */

// Ensure admin access is checked before including this
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    redirect('../login.php');
}

$current_user = $_SESSION['full_name'] ?? 'Admin';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'Admin Dashboard'; ?> - <?php echo APP_NAME; ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="../assets/css/advanced-ui.css" rel="stylesheet">
</head>

<body>
    <!-- Header -->
    <div class="header">
        <div class="header-content">
            <div>
                <h1><i class="fas fa-graduation-cap"></i> <?php echo APP_NAME; ?></h1>
                <p>Advanced School Management System - Admin Panel</p>
            </div>
            <div style="display: flex; align-items: center; gap: 20px;">
                <span>Welcome, <strong><?php echo htmlspecialchars($current_user); ?></strong></span>
                <a href="../logout.php" class="btn-logout">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </div>

    <!-- Container -->
    <div class="container">
        <?php include '../includes/admin-nav.php'; ?>