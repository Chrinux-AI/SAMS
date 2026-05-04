<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_admin('../login.php');

$page_title = 'AI Analytics';
$page_icon = 'brain';
$full_name = $_SESSION['full_name'];
?>
<!DOCTYPE html>
<html>

<head>
    <script src="../assets/js/theme-loader.js"></script>
        <link rel="manifest" href="/attendance/manifest.json">
    <meta name="theme-color" content="#00BFFF">
    <link rel="apple-touch-icon" href="/attendance/assets/images/icons/icon-192x192.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - <?php echo APP_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Orbitron:wght@500;700;900&family=Inter:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="../assets/css/professional-ui.css" rel="stylesheet">
    <?php include '../includes/sams-head-bootstrap.php'; ?>

    
</head>
<body>
    
    

    <div class="app-layout"><?php include '../includes/sidebar-nav.php'; ?>
        <main class="main-content">
            <header class="cyber-header">
                <div class="page-title-section">
                    <div class="page-icon-orb"><i class="fas fa-<?php echo $page_icon; ?>"></i></div>
                    <h1 class="page-title"><?php echo $page_title; ?></h1>
                </div>
                <div class="header-actions">
                    <div class="biometric-orb" title="Quick Scan"><i class="fas fa-fingerprint"></i></div>
                    <div class="user-card" style="padding:8px 15px;margin:0;">
                        <div class="user-avatar" style="width:35px;height:35px;font-size:0.9rem;">
                            <?php echo strtoupper(substr($full_name, 0, 2)); ?></div>
                        <div class="user-info">
                            <div class="user-name" style="font-size:0.85rem;"><?php echo htmlspecialchars($full_name); ?></div>
                            <div class="user-role">Administrator</div>
                        </div>
                    </div>
                </div>
            </header>
            <div class="cyber-content slide-in">
                <div class="holo-card">
                    <div class="card-header">
                        <div class="card-title"><i class="fas fa-chart-line"></i> <span>System Analytics</span></div>
                        <span class="cyber-badge primary">System Active</span>
                    </div>
                    <div class="card-body">
                        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:20px;">
                            <div class="holo-card">
                                <h4 style="color:var(--cyber-cyan);margin-bottom:12px;">Attendance Predictor</h4>
                                <div style="font-size:2rem;color:var(--text-primary);margin-bottom:8px;">94.2%</div>
                                <div style="color:var(--text-muted);font-size:0.85rem;">Accuracy Rate</div>
                                <span class="cyber-badge success" style="margin-top:10px;">Active</span>
                            </div>
                            <div class="holo-card">
                                <h4 style="color:var(--golden-pulse);margin-bottom:12px;">Behavior Analyzer</h4>
                                <div style="font-size:2rem;color:var(--text-primary);margin-bottom:8px;">89.7%</div>
                                <div style="color:var(--text-muted);font-size:0.85rem;">Accuracy Rate</div>
                                <span class="cyber-badge warning" style="margin-top:10px;">Training</span>
                            </div>
                            <div class="holo-card">
                                <h4 style="color:var(--neon-green);margin-bottom:12px;">Grade Predictor</h4>
                                <div style="font-size:2rem;color:var(--text-primary);margin-bottom:8px;">91.5%</div>
                                <div style="color:var(--text-muted);font-size:0.85rem;">Accuracy Rate</div>
                                <span class="cyber-badge success" style="margin-top:10px;">Active</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/pwa-manager.js"></script>
    <script src="../assets/js/pwa-analytics.js"></script>
</body>

</html>