#!/bin/bash

# Settings
cat > settings_new.php << 'EOF'
<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_admin('../login.php');
$page_title='Settings';$page_icon='cog';$full_name=$_SESSION['full_name'];
?>
<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?php echo $page_title;?> - <?php echo APP_NAME;?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Orbitron:wght@500;700;900&family=Rajdhani:wght@500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="../assets/css/cyberpunk-ui.css" rel="stylesheet"></head><body>
<div class="cyber-bg"><div class="starfield"></div></div><div class="cyber-grid"></div>
<div class="cyber-layout"><?php include'../includes/cyber-nav.php';?>
<main class="cyber-main"><header class="cyber-header"><div class="page-title-section"><div class="page-icon-orb"><i class="fas fa-<?php echo $page_icon;?>"></i></div>
<h1 class="page-title"><?php echo $page_title;?></h1></div><div class="header-actions"><div class="biometric-orb"title="Quick Scan"><i class="fas fa-fingerprint"></i></div>
<div class="user-card"style="padding:8px 15px;margin:0;"><div class="user-avatar"style="width:35px;height:35px;font-size:0.9rem;">
<?php echo strtoupper(substr($full_name,0,2));?></div><div class="user-info"><div class="user-name"style="font-size:0.85rem;"><?php echo htmlspecialchars($full_name);?></div>
<div class="user-role">Administrator</div></div></div></div></header><div class="cyber-content fade-in">
<div class="holo-card"><div class="card-header"><div class="card-title"><i class="fas fa-cog"></i><span>System Settings</span></div></div>
<div class="card-body"><p style="color:var(--text-muted);text-align:center;padding:40px;">Settings configuration coming soon...</p></div></div>
</div></main></div></body></html>
EOF

# Users
cat > users_new.php << 'EOF'
<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_admin('../login.php');
$page_title='Users Management';$page_icon='users';$full_name=$_SESSION['full_name'];
$users=db()->fetchAll("SELECT * FROM users ORDER BY created_at DESC");
?>
<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?php echo $page_title;?> - <?php echo APP_NAME;?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Orbitron:wght@500;700;900&family=Rajdhani:wght@500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="../assets/css/cyberpunk-ui.css" rel="stylesheet"></head><body>
<div class="cyber-bg"><div class="starfield"></div></div><div class="cyber-grid"></div>
<div class="cyber-layout"><?php include'../includes/cyber-nav.php';?>
<main class="cyber-main"><header class="cyber-header"><div class="page-title-section"><div class="page-icon-orb"><i class="fas fa-<?php echo $page_icon;?>"></i></div>
<h1 class="page-title"><?php echo $page_title;?></h1></div><div class="header-actions"><div class="biometric-orb"title="Quick Scan"><i class="fas fa-fingerprint"></i></div>
<div class="user-card"style="padding:8px 15px;margin:0;"><div class="user-avatar"style="width:35px;height:35px;font-size:0.9rem;">
<?php echo strtoupper(substr($full_name,0,2));?></div><div class="user-info"><div class="user-name"style="font-size:0.85rem;"><?php echo htmlspecialchars($full_name);?></div>
<div class="user-role">Administrator</div></div></div></div></header><div class="cyber-content fade-in">
<div class="holo-card"><div class="card-header"><div class="card-title"><i class="fas fa-users"></i><span>All Users</span></div>
<div class="card-badge cyan"><?php echo count($users);?> Users</div></div><div class="card-body"><div class="holo-table-wrapper">
<table class="holo-table"><thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th></tr></thead><tbody>
<?php foreach($users as $u):?>
<tr><td><?php echo htmlspecialchars($u['first_name'].' '.$u['last_name']);?></td>
<td><?php echo htmlspecialchars($u['email']);?></td>
<td><span class="cyber-badge <?php echo $u['role']==='admin'?'purple':'cyan';?>"><?php echo ucfirst($u['role']);?></span></td>
<td><span class="cyber-badge success">Active</span></td></tr>
<?php endforeach;?></tbody></table></div></div></div></div></main></div></body></html>
EOF

# Timetable
cat > timetable_new.php << 'EOF'
<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_admin('../login.php');
$page_title='Timetable';$page_icon='calendar-alt';$full_name=$_SESSION['full_name'];
?>
<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?php echo $page_title;?> - <?php echo APP_NAME;?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Orbitron:wght@500;700;900&family=Rajdhani:wght@500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="../assets/css/cyberpunk-ui.css" rel="stylesheet"></head><body>
<div class="cyber-bg"><div class="starfield"></div></div><div class="cyber-grid"></div>
<div class="cyber-layout"><?php include'../includes/cyber-nav.php';?>
<main class="cyber-main"><header class="cyber-header"><div class="page-title-section"><div class="page-icon-orb"><i class="fas fa-<?php echo $page_icon;?>"></i></div>
<h1 class="page-title"><?php echo $page_title;?></h1></div><div class="header-actions"><div class="biometric-orb"title="Quick Scan"><i class="fas fa-fingerprint"></i></div>
<div class="user-card"style="padding:8px 15px;margin:0;"><div class="user-avatar"style="width:35px;height:35px;font-size:0.9rem;">
<?php echo strtoupper(substr($full_name,0,2));?></div><div class="user-info"><div class="user-name"style="font-size:0.85rem;"><?php echo htmlspecialchars($full_name);?></div>
<div class="user-role">Administrator</div></div></div></div></header><div class="cyber-content fade-in">
<div class="holo-card"><div class="card-header"><div class="card-title"><i class="fas fa-calendar-alt"></i><span>Timetable Management</span></div></div>
<div class="card-body"><p style="color:var(--text-muted);text-align:center;padding:40px;">Timetable coming soon...</p></div></div>
</div></main></div></body></html>
EOF

# Communication
cat > communication_new.php << 'EOF'
<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_admin('../login.php');
$page_title='Communication';$page_icon='comments';$full_name=$_SESSION['full_name'];
?>
<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?php echo $page_title;?> - <?php echo APP_NAME;?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Orbitron:wght@500;700;900&family=Rajdhani:wght@500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="../assets/css/cyberpunk-ui.css" rel="stylesheet"></head><body>
<div class="cyber-bg"><div class="starfield"></div></div><div class="cyber-grid"></div>
<div class="cyber-layout"><?php include'../includes/cyber-nav.php';?>
<main class="cyber-main"><header class="cyber-header"><div class="page-title-section"><div class="page-icon-orb"><i class="fas fa-<?php echo $page_icon;?>"></i></div>
<h1 class="page-title"><?php echo $page_title;?></h1></div><div class="header-actions"><div class="biometric-orb"title="Quick Scan"><i class="fas fa-fingerprint"></i></div>
<div class="user-card"style="padding:8px 15px;margin:0;"><div class="user-avatar"style="width:35px;height:35px;font-size:0.9rem;">
<?php echo strtoupper(substr($full_name,0,2));?></div><div class="user-info"><div class="user-name"style="font-size:0.85rem;"><?php echo htmlspecialchars($full_name);?></div>
<div class="user-role">Administrator</div></div></div></div></header><div class="cyber-content fade-in">
<div class="holo-card"><div class="card-header"><div class="card-title"><i class="fas fa-comments"></i><span>Communication Center</span></div></div>
<div class="card-body"><p style="color:var(--text-muted);text-align:center;padding:40px;">Communication tools coming soon...</p></div></div>
</div></main></div></body></html>
EOF

# Facilities
cat > facilities_new.php << 'EOF'
<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_admin('../login.php');
$page_title='Facilities';$page_icon='building';$full_name=$_SESSION['full_name'];
?>
<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?php echo $page_title;?> - <?php echo APP_NAME;?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Orbitron:wght@500;700;900&family=Rajdhani:wght@500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="../assets/css/cyberpunk-ui.css" rel="stylesheet"></head><body>
<div class="cyber-bg"><div class="starfield"></div></div><div class="cyber-grid"></div>
<div class="cyber-layout"><?php include'../includes/cyber-nav.php';?>
<main class="cyber-main"><header class="cyber-header"><div class="page-title-section"><div class="page-icon-orb"><i class="fas fa-<?php echo $page_icon;?>"></i></div>
<h1 class="page-title"><?php echo $page_title;?></h1></div><div class="header-actions"><div class="biometric-orb"title="Quick Scan"><i class="fas fa-fingerprint"></i></div>
<div class="user-card"style="padding:8px 15px;margin:0;"><div class="user-avatar"style="width:35px;height:35px;font-size:0.9rem;">
<?php echo strtoupper(substr($full_name,0,2));?></div><div class="user-info"><div class="user-name"style="font-size:0.85rem;"><?php echo htmlspecialchars($full_name);?></div>
<div class="user-role">Administrator</div></div></div></div></header><div class="cyber-content fade-in">
<div class="holo-card"><div class="card-header"><div class="card-title"><i class="fas fa-building"></i><span>Facilities Management</span></div></div>
<div class="card-body"><p style="color:var(--text-muted);text-align:center;padding:40px;">Facilities management coming soon...</p></div></div>
</div></main></div></body></html>
EOF

# Replace all files
[ -f settings.php ] && mv settings.php settings_old_backup.php
[ -f users.php ] && mv users.php users_old_backup.php
[ -f timetable.php ] && mv timetable.php timetable_old_backup.php
[ -f communication.php ] && mv communication.php communication_old_backup.php
[ -f facilities.php ] && mv facilities.php facilities_old_backup.php

mv settings_new.php settings.php
mv users_new.php users.php
mv timetable_new.php timetable.php
mv communication_new.php communication.php
mv facilities_new.php facilities.php

echo "All pages converted successfully!"
