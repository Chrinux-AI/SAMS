#!/bin/bash

# Convert announcements.php
cat > announcements_temp.php << 'EOF'
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
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?php echo $page_title; ?> - <?php echo APP_NAME; ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Orbitron:wght@500;700;900&family=Rajdhani:wght@500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="../assets/css/cyberpunk-ui.css" rel="stylesheet">
</head><body>
<div class="cyber-bg"><div class="starfield"></div></div><div class="cyber-grid"></div>
<div class="cyber-layout"><?php include '../includes/cyber-nav.php'; ?>
<main class="cyber-main"><header class="cyber-header">
<div class="page-title-section"><div class="page-icon-orb"><i class="fas fa-<?php echo $page_icon; ?>"></i></div>
<h1 class="page-title"><?php echo $page_title; ?></h1></div>
<div class="header-actions"><div class="biometric-orb" title="Quick Scan"><i class="fas fa-fingerprint"></i></div>
<div class="user-card" style="padding:8px 15px;margin:0;"><div class="user-avatar" style="width:35px;height:35px;font-size:0.9rem;">
<?php echo strtoupper(substr($full_name, 0, 2)); ?></div><div class="user-info">
<div class="user-name" style="font-size:0.85rem;"><?php echo htmlspecialchars($full_name); ?></div>
<div class="user-role">Administrator</div></div></div></div></header>
<div class="cyber-content fade-in">
<div class="holo-card"><div class="card-header"><div class="card-title"><i class="fas fa-bullhorn"></i> <span>All Announcements</span></div>
<div class="card-badge cyan"><?php echo count($announcements); ?> Announcements</div></div>
<div class="card-body"><?php if(empty($announcements)): ?><p style="text-align:center;padding:40px;color:var(--text-muted);">No announcements yet</p>
<?php else: foreach($announcements as $ann): ?>
<div style="padding:20px;background:rgba(0,191,255,0.05);border:1px solid rgba(0,191,255,0.2);border-radius:12px;margin-bottom:15px;">
<h3 style="color:var(--cyber-cyan);margin-bottom:10px;"><?php echo htmlspecialchars($ann['title'] ?? 'Untitled'); ?></h3>
<p style="color:var(--text-muted);margin-bottom:10px;"><?php echo htmlspecialchars($ann['content'] ?? ''); ?></p>
<span class="cyber-badge cyan"><?php echo date('M d, Y', strtotime($ann['created_at'] ?? 'now')); ?></span>
</div><?php endforeach; endif; ?></div></div></div></main></div></body></html>
EOF

[ -f announcements.php ] && mv announcements.php announcements_old_backup.php
mv announcements_temp.php announcements.php

# Convert reports.php
cat > reports_temp.php << 'EOF'
<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_admin('../login.php');

$page_title = 'Reports';
$page_icon = 'chart-line';
$full_name = $_SESSION['full_name'];

$total_students = db()->count('students');
$total_classes = db()->count('classes');
$total_attendance = db()->count('attendance_records');
?>
<!DOCTYPE html>
<html><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?php echo $page_title; ?> - <?php echo APP_NAME; ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Orbitron:wght@500;700;900&family=Rajdhani:wght@500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="../assets/css/cyberpunk-ui.css" rel="stylesheet">
</head><body>
<div class="cyber-bg"><div class="starfield"></div></div><div class="cyber-grid"></div>
<div class="cyber-layout"><?php include '../includes/cyber-nav.php'; ?>
<main class="cyber-main"><header class="cyber-header">
<div class="page-title-section"><div class="page-icon-orb"><i class="fas fa-<?php echo $page_icon; ?>"></i></div>
<h1 class="page-title"><?php echo $page_title; ?></h1></div>
<div class="header-actions"><div class="biometric-orb" title="Quick Scan"><i class="fas fa-fingerprint"></i></div>
<div class="user-card" style="padding:8px 15px;margin:0;"><div class="user-avatar" style="width:35px;height:35px;font-size:0.9rem;">
<?php echo strtoupper(substr($full_name, 0, 2)); ?></div><div class="user-info">
<div class="user-name" style="font-size:0.85rem;"><?php echo htmlspecialchars($full_name); ?></div>
<div class="user-role">Administrator</div></div></div></div></header>
<div class="cyber-content fade-in">
<section class="orb-grid">
<div class="stat-orb"><div class="orb-icon-wrapper cyan"><i class="fas fa-user-graduate"></i></div>
<div class="orb-content"><div class="orb-value"><?php echo number_format($total_students); ?></div>
<div class="orb-label">Total Students</div><div class="orb-trend up"><i class="fas fa-arrow-up"></i><span>Active</span></div></div></div>
<div class="stat-orb"><div class="orb-icon-wrapper green"><i class="fas fa-door-open"></i></div>
<div class="orb-content"><div class="orb-value"><?php echo number_format($total_classes); ?></div>
<div class="orb-label">Total Classes</div><div class="orb-trend up"><i class="fas fa-check"></i><span>All Levels</span></div></div></div>
<div class="stat-orb"><div class="orb-icon-wrapper purple"><i class="fas fa-clipboard-check"></i></div>
<div class="orb-content"><div class="orb-value"><?php echo number_format($total_attendance); ?></div>
<div class="orb-label">Total Records</div><div class="orb-trend up"><i class="fas fa-database"></i><span>Recorded</span></div></div></div>
</section>
<div class="holo-card"><div class="card-header"><div class="card-title"><i class="fas fa-file-alt"></i> <span>Generate Reports</span></div></div>
<div class="card-body"><p style="color:var(--text-muted);text-align:center;padding:40px;">Report generation coming soon...</p></div></div>
</div></main></div></body></html>
EOF

[ -f reports.php ] && mv reports.php reports_old_backup.php
mv reports_temp.php reports.php

# Convert analytics.php
cat > analytics_temp.php << 'EOF'
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
<html><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?php echo $page_title; ?> - <?php echo APP_NAME; ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Orbitron:wght@500;700;900&family=Rajdhani:wght@500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="../assets/css/cyberpunk-ui.css" rel="stylesheet">
</head><body>
<div class="cyber-bg"><div class="starfield"></div></div><div class="cyber-grid"></div>
<div class="cyber-layout"><?php include '../includes/cyber-nav.php'; ?>
<main class="cyber-main"><header class="cyber-header">
<div class="page-title-section"><div class="page-icon-orb"><i class="fas fa-<?php echo $page_icon; ?>"></i></div>
<h1 class="page-title"><?php echo $page_title; ?></h1></div>
<div class="header-actions"><div class="biometric-orb" title="Quick Scan"><i class="fas fa-fingerprint"></i></div>
<div class="user-card" style="padding:8px 15px;margin:0;"><div class="user-avatar" style="width:35px;height:35px;font-size:0.9rem;">
<?php echo strtoupper(substr($full_name, 0, 2)); ?></div><div class="user-info">
<div class="user-name" style="font-size:0.85rem;"><?php echo htmlspecialchars($full_name); ?></div>
<div class="user-role">Administrator</div></div></div></div></header>
<div class="cyber-content fade-in">
<div class="holo-card"><div class="card-header"><div class="card-title"><i class="fas fa-robot"></i> <span>AI Models</span></div>
<span class="cyber-badge primary">Neural Network Active</span></div>
<div class="card-body"><div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:20px;">
<div class="holo-card"><h4 style="color:var(--cyber-cyan);margin-bottom:12px;">Attendance Predictor</h4>
<div style="font-size:2rem;color:var(--text-primary);margin-bottom:8px;">94.2%</div>
<div style="color:var(--text-muted);font-size:0.85rem;">Accuracy Rate</div>
<span class="cyber-badge success" style="margin-top:10px;">Active</span></div>
<div class="holo-card"><h4 style="color:var(--golden-pulse);margin-bottom:12px;">Behavior Analyzer</h4>
<div style="font-size:2rem;color:var(--text-primary);margin-bottom:8px;">89.7%</div>
<div style="color:var(--text-muted);font-size:0.85rem;">Accuracy Rate</div>
<span class="cyber-badge warning" style="margin-top:10px;">Training</span></div>
<div class="holo-card"><h4 style="color:var(--neon-green);margin-bottom:12px;">Grade Predictor</h4>
<div style="font-size:2rem;color:var(--text-primary);margin-bottom:8px;">91.5%</div>
<div style="color:var(--text-muted);font-size:0.85rem;">Accuracy Rate</div>
<span class="cyber-badge success" style="margin-top:10px;">Active</span></div>
</div></div></div>
</div></main></div></body></html>
EOF

[ -f analytics.php ] && mv analytics.php analytics_old_backup.php
mv analytics_temp.php analytics.php

echo "Conversion complete!"
