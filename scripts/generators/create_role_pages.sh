#!/bin/bash

# Create Teacher Settings
cat > teacher/settings.php << 'EOF'
<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: ../login.php');
    exit;
}
$full_name = $_SESSION['full_name'];
$page_title = 'Settings';
$page_icon = 'cog';
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
<div class="cyber-layout">
<aside class="cyber-sidebar">
<div class="sidebar-header"><div class="logo-wrapper">
<div class="logo-icon"><i class="fas fa-graduation-cap"></i></div>
<div class="logo-text"><div class="app-name">Attendance AI</div><div class="app-tagline">TEACHER PANEL</div></div></div></div>
<div class="user-card"><div class="user-avatar"><?php echo strtoupper(substr($full_name, 0, 2)); ?></div>
<div class="user-info"><div class="user-name"><?php echo htmlspecialchars($full_name); ?></div>
<div class="user-role">Teacher</div></div></div>
<nav class="sidebar-nav">
<div class="nav-section"><div class="section-title">CORE</div>
<a href="dashboard.php" class="menu-item"><i class="fas fa-brain"></i><span>Dashboard</span></a>
<a href="my-classes.php" class="menu-item"><i class="fas fa-door-open"></i><span>My Classes</span></a>
<a href="attendance.php" class="menu-item"><i class="fas fa-clipboard-check"></i><span>Attendance</span></a>
<a href="students.php" class="menu-item"><i class="fas fa-user-graduate"></i><span>My Students</span></a>
</div>
<div class="nav-section"><div class="section-title">MANAGEMENT</div>
<a href="reports.php" class="menu-item"><i class="fas fa-chart-line"></i><span>Reports</span></a>
<a href="settings.php" class="menu-item active"><i class="fas fa-cog"></i><span>Settings</span></a>
</div></nav>
<div class="sidebar-footer"><a href="../logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a></div>
</aside>
<main class="cyber-main"><header class="cyber-header">
<div class="page-title-section"><div class="page-icon-orb"><i class="fas fa-<?php echo $page_icon; ?>"></i></div>
<h1 class="page-title"><?php echo $page_title; ?></h1></div>
<div class="header-actions"><div class="biometric-orb" title="Quick Scan"><i class="fas fa-fingerprint"></i></div>
<div class="user-card" style="padding:8px 15px;margin:0;">
<div class="user-avatar" style="width:35px;height:35px;font-size:0.9rem;"><?php echo strtoupper(substr($full_name, 0, 2)); ?></div>
<div class="user-info"><div class="user-name" style="font-size:0.85rem;"><?php echo htmlspecialchars($full_name); ?></div>
<div class="user-role">Teacher</div></div></div></div></header>
<div class="cyber-content fade-in">
<div class="holo-card"><div class="card-header"><div class="card-title"><i class="fas fa-user-cog"></i> <span>Account Settings</span></div></div>
<div class="card-body"><form style="display:grid;gap:20px;">
<div><label style="color:var(--cyber-cyan);font-size:0.85rem;font-weight:600;margin-bottom:8px;display:block;">EMAIL NOTIFICATIONS</label>
<div style="display:flex;align-items:center;gap:10px;"><input type="checkbox" checked> <span style="color:var(--text-primary);">Receive attendance alerts</span></div></div>
<div><label style="color:var(--cyber-cyan);font-size:0.85rem;font-weight:600;margin-bottom:8px;display:block;">LANGUAGE</label>
<select style="width:100%;padding:12px;background:rgba(0,191,255,0.05);border:1px solid var(--cyber-cyan);border-radius:8px;color:var(--cyber-cyan);">
<option>English</option></select></div>
<button type="submit" class="cyber-btn primary"><i class="fas fa-save"></i> Save Settings</button>
</form></div></div>
</div></main></div></body></html>
EOF

# Create Student Settings
cat > student/settings.php << 'EOF'
<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../login.php');
    exit;
}
$full_name = $_SESSION['full_name'];
$page_title = 'Settings';
$page_icon = 'cog';
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
<div class="cyber-layout">
<aside class="cyber-sidebar">
<div class="sidebar-header"><div class="logo-wrapper">
<div class="logo-icon"><i class="fas fa-graduation-cap"></i></div>
<div class="logo-text"><div class="app-name">Attendance AI</div><div class="app-tagline">STUDENT PANEL</div></div></div></div>
<div class="user-card"><div class="user-avatar"><?php echo strtoupper(substr($full_name, 0, 2)); ?></div>
<div class="user-info"><div class="user-name"><?php echo htmlspecialchars($full_name); ?></div>
<div class="user-role">Student</div></div></div>
<nav class="sidebar-nav">
<div class="nav-section"><div class="section-title">CORE</div>
<a href="dashboard.php" class="menu-item"><i class="fas fa-brain"></i><span>Dashboard</span></a>
<a href="checkin.php" class="menu-item"><i class="fas fa-fingerprint"></i><span>Check In</span></a>
<a href="attendance.php" class="menu-item"><i class="fas fa-clipboard-check"></i><span>My Attendance</span></a>
<a href="schedule.php" class="menu-item"><i class="fas fa-calendar-alt"></i><span>Schedule</span></a>
</div>
<div class="nav-section"><div class="section-title">MANAGEMENT</div>
<a href="profile.php" class="menu-item"><i class="fas fa-user-circle"></i><span>Profile</span></a>
<a href="settings.php" class="menu-item active"><i class="fas fa-cog"></i><span>Settings</span></a>
</div></nav>
<div class="sidebar-footer"><a href="../logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a></div>
</aside>
<main class="cyber-main"><header class="cyber-header">
<div class="page-title-section"><div class="page-icon-orb"><i class="fas fa-<?php echo $page_icon; ?>"></i></div>
<h1 class="page-title"><?php echo $page_title; ?></h1></div>
<div class="header-actions"><div class="biometric-orb" title="Quick Scan"><i class="fas fa-fingerprint"></i></div>
<div class="user-card" style="padding:8px 15px;margin:0;">
<div class="user-avatar" style="width:35px;height:35px;font-size:0.9rem;"><?php echo strtoupper(substr($full_name, 0, 2)); ?></div>
<div class="user-info"><div class="user-name" style="font-size:0.85rem;"><?php echo htmlspecialchars($full_name); ?></div>
<div class="user-role">Student</div></div></div></div></header>
<div class="cyber-content fade-in">
<div class="holo-card"><div class="card-header"><div class="card-title"><i class="fas fa-user-cog"></i> <span>Account Settings</span></div></div>
<div class="card-body"><form style="display:grid;gap:20px;">
<div><label style="color:var(--cyber-cyan);font-size:0.85rem;font-weight:600;margin-bottom:8px;display:block;">NOTIFICATIONS</label>
<div style="display:flex;align-items:center;gap:10px;"><input type="checkbox" checked> <span style="color:var(--text-primary);">Receive attendance reminders</span></div></div>
<div><label style="color:var(--cyber-cyan);font-size:0.85rem;font-weight:600;margin-bottom:8px;display:block;">THEME</label>
<select style="width:100%;padding:12px;background:rgba(0,191,255,0.05);border:1px solid var(--cyber-cyan);border-radius:8px;color:var(--cyber-cyan);">
<option>Cyberpunk (Default)</option></select></div>
<button type="submit" class="cyber-btn primary"><i class="fas fa-save"></i> Save Settings</button>
</form></div></div>
</div></main></div></body></html>
EOF

# Create Parent Settings
cat > parent/settings.php << 'EOF'
<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'parent') {
    header('Location: ../login.php');
    exit;
}
$full_name = $_SESSION['full_name'];
$page_title = 'Settings';
$page_icon = 'cog';
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
<div class="cyber-layout">
<aside class="cyber-sidebar">
<div class="sidebar-header"><div class="logo-wrapper">
<div class="logo-icon"><i class="fas fa-graduation-cap"></i></div>
<div class="logo-text"><div class="app-name">Attendance AI</div><div class="app-tagline">PARENT PANEL</div></div></div></div>
<div class="user-card"><div class="user-avatar"><?php echo strtoupper(substr($full_name, 0, 2)); ?></div>
<div class="user-info"><div class="user-name"><?php echo htmlspecialchars($full_name); ?></div>
<div class="user-role">Parent</div></div></div>
<nav class="sidebar-nav">
<div class="nav-section"><div class="section-title">CORE</div>
<a href="dashboard.php" class="menu-item"><i class="fas fa-brain"></i><span>Dashboard</span></a>
<a href="children.php" class="menu-item"><i class="fas fa-child"></i><span>My Children</span></a>
<a href="attendance.php" class="menu-item"><i class="fas fa-clipboard-check"></i><span>Attendance</span></a>
<a href="communication.php" class="menu-item"><i class="fas fa-comments"></i><span>Messages</span></a>
</div>
<div class="nav-section"><div class="section-title">MANAGEMENT</div>
<a href="reports.php" class="menu-item"><i class="fas fa-chart-line"></i><span>Reports</span></a>
<a href="settings.php" class="menu-item active"><i class="fas fa-cog"></i><span>Settings</span></a>
</div></nav>
<div class="sidebar-footer"><a href="../logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a></div>
</aside>
<main class="cyber-main"><header class="cyber-header">
<div class="page-title-section"><div class="page-icon-orb"><i class="fas fa-<?php echo $page_icon; ?>"></i></div>
<h1 class="page-title"><?php echo $page_title; ?></h1></div>
<div class="header-actions"><div class="biometric-orb" title="Quick Scan"><i class="fas fa-fingerprint"></i></div>
<div class="user-card" style="padding:8px 15px;margin:0;">
<div class="user-avatar" style="width:35px;height:35px;font-size:0.9rem;"><?php echo strtoupper(substr($full_name, 0, 2)); ?></div>
<div class="user-info"><div class="user-name" style="font-size:0.85rem;"><?php echo htmlspecialchars($full_name); ?></div>
<div class="user-role">Parent</div></div></div></div></header>
<div class="cyber-content fade-in">
<div class="holo-card"><div class="card-header"><div class="card-title"><i class="fas fa-user-cog"></i> <span>Account Settings</span></div></div>
<div class="card-body"><form style="display:grid;gap:20px;">
<div><label style="color:var(--cyber-cyan);font-size:0.85rem;font-weight:600;margin-bottom:8px;display:block;">NOTIFICATIONS</label>
<div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;"><input type="checkbox" checked> <span style="color:var(--text-primary);">Daily attendance summary</span></div>
<div style="display:flex;align-items:center;gap:10px;"><input type="checkbox" checked> <span style="color:var(--text-primary);">Absence alerts</span></div></div>
<div><label style="color:var(--cyber-cyan);font-size:0.85rem;font-weight:600;margin-bottom:8px;display:block;">COMMUNICATION</label>
<select style="width:100%;padding:12px;background:rgba(0,191,255,0.05);border:1px solid var(--cyber-cyan);border-radius:8px;color:var(--cyber-cyan);">
<option>Email & SMS</option><option>Email Only</option></select></div>
<button type="submit" class="cyber-btn primary"><i class="fas fa-save"></i> Save Settings</button>
</form></div></div>
</div></main></div></body></html>
EOF

echo "Settings pages created for all roles!"
