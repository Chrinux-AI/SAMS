<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_admin('../login.php');

$page_title = 'Announcements';
$page_icon = 'bullhorn';
$full_name = $_SESSION['full_name'];

$announcements = db()->fetchAll("SELECT * FROM announcements ORDER BY created_at DESC LIMIT 50") ?? [];
?>
<!DOCTYPE html>
<html><head>
    <script src="../assets/js/theme-loader.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="manifest" href="/attendance/manifest.json">
    <meta name="theme-color" content="#00BFFF">
    <link rel="apple-touch-icon" href="/attendance/assets/images/icons/icon-192x192.png">
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?php echo $page_title; ?> - <?php echo APP_NAME; ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Orbitron:wght@500;700;900&family=Inter:wght@500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="../assets/css/professional-ui.css" rel="stylesheet">
    
</head><body>
    
    

<div class="app-layout"><?php include '../includes/sidebar-nav.php'; ?>
<main class="main-content"><header class="cyber-header">
<div class="page-title-section"><div class="page-icon-orb"><i class="fas fa-<?php echo $page_icon; ?>"></i></div>
<h1 class="page-title"><?php echo $page_title; ?></h1></div>
<div class="header-actions"><div class="biometric-orb" title="Quick Scan"><i class="fas fa-fingerprint"></i></div>
<div class="user-card" style="padding:8px 15px;margin:0;"><div class="user-avatar" style="width:35px;height:35px;font-size:0.9rem;">
<?php echo strtoupper(substr($full_name, 0, 2)); ?></div><div class="user-info">
<div class="user-name" style="font-size:0.85rem;"><?php echo htmlspecialchars($full_name); ?></div>
<div class="user-role">Administrator</div></div></div></div></header>
<div class="cyber-content slide-in">
<div class="holo-card"><div class="card-header"><div class="card-title"><i class="fas fa-bullhorn"></i> <span>All Announcements</span></div>
<div class="card-badge cyan"><?php echo count($announcements); ?> Announcements</div></div>
<div class="card-body"><?php if(empty($announcements)): ?><p style="text-align:center;padding:40px;color:var(--text-muted);">No announcements yet</p>
<?php else: foreach($announcements as $ann): ?>
<div style="padding:20px;background:rgba(0,191,255,0.05);border:1px solid rgba(0,191,255,0.2);border-radius:12px;margin-bottom:15px;">
<h3 style="color:var(--cyber-cyan);margin-bottom:10px;"><?php echo htmlspecialchars($ann['title'] ?? 'Untitled'); ?></h3>
<p style="color:var(--text-muted);margin-bottom:10px;"><?php echo htmlspecialchars($ann['content'] ?? ''); ?></p>
<span class="cyber-badge cyan"><?php echo date('M d, Y', strtotime($ann['created_at'] ?? 'now')); ?></span>
</div><?php endforeach; endif; ?></div></div></div></main></div>
    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/pwa-manager.js"></script>
    <script src="../assets/js/pwa-analytics.js"></script>
</body></html>
