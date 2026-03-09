<?php
/**
 * SAMS Layout Header
 * Global header template for all pages
 */

if (!isset($page_title)) $page_title = 'SAMS';
$current_role = $_SESSION['role'] ?? 'guest';
$user_name = $_SESSION['full_name'] ?? 'Guest';
$theme = $_SESSION['theme'] ?? 'light';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= $theme ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#4F46E5">
    <title><?= htmlspecialchars($page_title) ?> - SAMS</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="/assets/logo/favicon.svg">
    <link rel="alternate icon" href="/assets/logo/favicon.ico">
    <link rel="apple-touch-icon" href="/assets/logo/apple-touch-icon.png">
    
    <!-- PWA -->
    <link rel="manifest" href="/manifest.json">
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Global Styles -->
    <link rel="stylesheet" href="/assets/theme/sams-global.css">
    
    <style>
        /* Critical CSS for immediate render */
        .sams-app {
            display: flex;
            min-height: 100vh;
            font-family: 'Inter', sans-serif;
        }
        .sams-sidebar {
            width: 260px;
            background: #1e293b;
            color: white;
            position: fixed;
            height: 100vh;
            left: 0;
            top: 0;
            z-index: 100;
            transition: transform 0.3s ease;
        }
        .sams-main {
            flex: 1;
            margin-left: 260px;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            background: #f1f5f9;
        }
        .sams-topbar {
            height: 64px;
            background: white;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 1.5rem;
            position: sticky;
            top: 0;
            z-index: 90;
        }
        .sams-content {
            flex: 1;
            padding: 1.5rem;
        }
        @media (max-width: 768px) {
            .sams-sidebar { transform: translateX(-100%); }
            .sams-sidebar.open { transform: translateX(0); }
            .sams-main { margin-left: 0; }
        }
    </style>
</head>
<body>
    <div class="sams-app">
        <!-- Sidebar -->
        <aside class="sams-sidebar" id="sidebar">
            <div class="sidebar-header">
                <a href="/dashboard.php" class="logo">
                    <img src="/assets/logo/logo-icon.svg" alt="SAMS" class="logo-icon">
                    <span class="logo-text">SAMS</span>
                </a>
            </div>
            
            <nav class="sidebar-nav">
                <?php include_once __DIR__ . '/sidebar-nav.php'; ?>
            </nav>
            
            <div class="sidebar-footer">
                <div class="user-info">
                    <div class="user-name"><?= htmlspecialchars($user_name) ?></div>
                    <div class="user-role"><?= ucfirst(str_replace('_', ' ', $current_role)) ?></div>
                </div>
                <a href="/logout.php" class="logout-link">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </aside>
        
        <!-- Main Content -->
        <main class="sams-main">
            <!-- Topbar -->
            <header class="sams-topbar">
                <div class="topbar-left">
                    <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h1 class="page-title"><?= htmlspecialchars($page_title) ?></h1>
                </div>
                
                <div class="topbar-right">
                    <button class="icon-btn" id="chatbotToggle" aria-label="Help">
                        <i class="fas fa-question-circle"></i>
                    </button>
                    <div class="user-menu">
                        <span class="user-name"><?= htmlspecialchars(explode(' ', $user_name)[0]) ?></span>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                </div>
            </header>
            
            <!-- Page Content -->
            <div class="sams-content">
