<?php

/**
 * SAMS Global Layout System
 * Shared components for all role panels
 */

// Prevent direct access
if (!defined('SAMS_BOOTSTRAP_LOADED')) {
    require_once __DIR__ . '/../core/bootstrap.php';
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' : ''; ?>SAMS</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- SAMS Theme CSS -->
    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --info-color: #17a2b8;
            --light-color: #f8f9fa;
            --dark-color: #343a40;
            --sidebar-width: 250px;
            --header-height: 60px;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background-color: #f5f5f5;
            margin: 0;
            padding: 0;
        }

        .sams-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            height: var(--header-height);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .sams-header .navbar-brand {
            font-weight: 600;
            font-size: 1.25rem;
        }

        .sams-sidebar {
            position: fixed;
            top: var(--header-height);
            left: 0;
            width: var(--sidebar-width);
            height: calc(100vh - var(--header-height));
            background: white;
            border-right: 1px solid #e3e6f0;
            overflow-y: auto;
            z-index: 999;
        }

        .sams-sidebar .nav-link {
            color: var(--dark-color);
            padding: 12px 20px;
            border-radius: 0;
            transition: all 0.3s ease;
        }

        .sams-sidebar .nav-link:hover,
        .sams-sidebar .nav-link.active {
            background: var(--primary-color);
            color: white;
        }

        .sams-sidebar .nav-link i {
            width: 20px;
            margin-right: 10px;
        }

        .sams-content {
            margin-left: var(--sidebar-width);
            margin-top: var(--header-height);
            padding: 20px;
            min-height: calc(100vh - var(--header-height));
        }

        .sams-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid #e3e6f0;
        }

        .sams-card-header {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e3e6f0;
        }

        .sams-btn-primary {
            background: var(--primary-color);
            border-color: var(--primary-color);
        }

        .sams-btn-primary:hover {
            background: var(--secondary-color);
            border-color: var(--secondary-color);
        }

        .sams-table {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .sams-table thead {
            background: var(--light-color);
        }

        .sams-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .sams-badge-success {
            background: #d4edda;
            color: #155724;
        }

        .sams-badge-warning {
            background: #fff3cd;
            color: #856404;
        }

        .sams-badge-danger {
            background: #f8d7da;
            color: #721c24;
        }

        .sams-badge-info {
            background: #d1ecf1;
            color: #0c5460;
        }

        .sams-alert {
            border-radius: 8px;
            border: none;
            padding: 15px;
        }

        .sams-alert-success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }

        .sams-alert-warning {
            background: #fff3cd;
            color: #856404;
            border-left: 4px solid #ffc107;
        }

        .sams-alert-danger {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }

        .sams-alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border-left: 4px solid #17a2b8;
        }

        .sams-form-group {
            margin-bottom: 20px;
        }

        .sams-form-label {
            font-weight: 500;
            color: var(--dark-color);
            margin-bottom: 8px;
        }

        .sams-form-control {
            border: 1px solid #ced4da;
            border-radius: 6px;
            padding: 10px 15px;
            transition: border-color 0.3s ease;
        }

        .sams-form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }

        .sams-footer {
            background: var(--dark-color);
            color: white;
            text-align: center;
            padding: 20px;
            margin-left: var(--sidebar-width);
        }

        @media (max-width: 768px) {
            .sams-sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }

            .sams-sidebar.show {
                transform: translateX(0);
            }

            .sams-content,
            .sams-footer {
                margin-left: 0;
            }
        }

        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        .theme-dark {
            --light-color: #2d3748;
            --dark-color: #f7fafc;
            background: #1a202c;
            color: #f7fafc;
        }

        .theme-dark .sams-card {
            background: #2d3748;
            color: #f7fafc;
            border-color: #4a5568;
        }

        .theme-dark .sams-sidebar {
            background: #2d3748;
            border-color: #4a5568;
        }

        .theme-dark .sams-sidebar .nav-link {
            color: #f7fafc;
        }

        .theme-dark .sams-sidebar .nav-link:hover,
        .theme-dark .sams-sidebar .nav-link.active {
            background: var(--primary-color);
        }
    </style>
</head>

<body>
    <!-- Header -->
    <header class="sams-header">
        <nav class="navbar navbar-expand-lg navbar-dark">
            <div class="container-fluid">
                <a class="navbar-brand" href="<?php echo function_exists('base_url') ? base_url('') : APP_URL; ?>">
                    <i class="fas fa-graduation-cap me-2"></i>
                    SAMS
                </a>

                <button class="navbar-toggler d-lg-none" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav ms-auto">
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-user me-2"></i>
                                <?php echo isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name']) : 'User'; ?>
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="#"><i class="fas fa-user me-2"></i>Profile</a></li>
                                <li><a class="dropdown-item" href="#"><i class="fas fa-cog me-2"></i>Settings</a></li>
                                <li>
                                    <hr class="dropdown-divider">
                                </li>
                                <li><a class="dropdown-item" href="<?php echo function_exists('base_url') ? base_url('logout.php') : APP_URL . '/logout.php'; ?>"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                            </ul>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
    </header>

    <!-- Sidebar -->
    <aside class="sams-sidebar">
        <?php
        $currentRole = getCurrentRole();
        loadSidebar($currentRole);
        ?>
    </aside>

    <!-- Main Content -->
    <main class="sams-content">
