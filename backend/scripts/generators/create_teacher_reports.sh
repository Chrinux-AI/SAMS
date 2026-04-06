#!/bin/bash

# Create teacher/attendance.php
cat > teacher/attendance.php << 'EOF'
<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {header('Location: ../login.php');exit;}
$full_name = $_SESSION['full_name'];
$teacher_id = $_SESSION['user_id'];
$classes = db()->query("SELECT * FROM classes WHERE teacher_id = ?", [$teacher_id]);
?>
<!DOCTYPE html>
<html><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Attendance - <?php echo APP_NAME; ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Orbitron:wght@500;700;900&family=Rajdhani:wght@500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="../assets/css/cyberpunk-ui.css" rel="stylesheet">
</head><body>
<div class="cyber-bg"><div class="starfield"></div></div><div class="cyber-grid"></div>
<div class="cyber-layout">
<aside class="cyber-sidebar">
<div class="sidebar-header"><div class="logo-wrapper"><div class="logo-icon"><i class="fas fa-graduation-cap"></i></div>
<div class="logo-text"><div class="app-name">Attendance AI</div><div class="app-tagline">TEACHER PANEL</div></div></div></div>
<div class="user-card"><div class="user-avatar"><?php echo strtoupper(substr($full_name, 0, 2)); ?></div>
<div class="user-info"><div class="user-name"><?php echo htmlspecialchars($full_name); ?></div><div class="user-role">Teacher</div></div></div>
<nav class="sidebar-nav"><div class="nav-section"><div class="section-title">CORE</div>
<a href="dashboard.php" class="menu-item"><i class="fas fa-brain"></i><span>Dashboard</span></a>
<a href="my-classes.php" class="menu-item"><i class="fas fa-door-open"></i><span>My Classes</span></a>
<a href="attendance.php" class="menu-item active"><i class="fas fa-clipboard-check"></i><span>Attendance</span></a>
<a href="students.php" class="menu-item"><i class="fas fa-user-graduate"></i><span>My Students</span></a>
</div><div class="nav-section"><div class="section-title">MANAGEMENT</div>
<a href="reports.php" class="menu-item"><i class="fas fa-chart-line"></i><span>Reports</span></a>
<a href="settings.php" class="menu-item"><i class="fas fa-cog"></i><span>Settings</span></a>
</div></nav><div class="sidebar-footer"><a href="../logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a></div>
</aside>
<main class="cyber-main"><header class="cyber-header">
<div class="page-title-section"><div class="page-icon-orb"><i class="fas fa-clipboard-check"></i></div>
<h1 class="page-title">Mark Attendance</h1></div></header>
<div class="cyber-content fade-in">
<div class="holo-card"><div class="card-header"><div class="card-title"><i class="fas fa-clipboard-check"></i> <span>Select Class</span></div></div>
<div class="card-body"><div style="display:grid;gap:15px;">
<?php foreach($classes as $class): ?>
<a href="../admin/attendance.php?class_id=<?php echo $class['id']; ?>" class="cyber-btn primary" style="text-align:left;padding:20px;">
<div style="display:flex;justify-content:space-between;align-items:center;">
<div><div style="font-size:1.2rem;font-weight:700;"><?php echo htmlspecialchars($class['class_name']); ?></div>
<div style="font-size:0.9rem;opacity:0.8;">Grade <?php echo $class['grade_level']; ?> • <?php echo $class['class_code']; ?></div></div>
<i class="fas fa-arrow-right"></i>
</div>
</a>
<?php endforeach; ?>
</div></div></div>
</div></main></div></body></html>
EOF

# Create teacher/reports.php
cat > teacher/reports.php << 'EOF'
<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {header('Location: ../login.php');exit;}
$full_name = $_SESSION['full_name'];
$teacher_id = $_SESSION['user_id'];
?>
<!DOCTYPE html>
<html><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Reports - <?php echo APP_NAME; ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Orbitron:wght@500;700;900&family=Rajdhani:wght@500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="../assets/css/cyberpunk-ui.css" rel="stylesheet">
</head><body>
<div class="cyber-bg"><div class="starfield"></div></div><div class="cyber-grid"></div>
<div class="cyber-layout">
<aside class="cyber-sidebar">
<div class="sidebar-header"><div class="logo-wrapper"><div class="logo-icon"><i class="fas fa-graduation-cap"></i></div>
<div class="logo-text"><div class="app-name">Attendance AI</div><div class="app-tagline">TEACHER PANEL</div></div></div></div>
<div class="user-card"><div class="user-avatar"><?php echo strtoupper(substr($full_name, 0, 2)); ?></div>
<div class="user-info"><div class="user-name"><?php echo htmlspecialchars($full_name); ?></div><div class="user-role">Teacher</div></div></div>
<nav class="sidebar-nav"><div class="nav-section"><div class="section-title">CORE</div>
<a href="dashboard.php" class="menu-item"><i class="fas fa-brain"></i><span>Dashboard</span></a>
<a href="my-classes.php" class="menu-item"><i class="fas fa-door-open"></i><span>My Classes</span></a>
<a href="attendance.php" class="menu-item"><i class="fas fa-clipboard-check"></i><span>Attendance</span></a>
<a href="students.php" class="menu-item"><i class="fas fa-user-graduate"></i><span>My Students</span></a>
</div><div class="nav-section"><div class="section-title">MANAGEMENT</div>
<a href="reports.php" class="menu-item active"><i class="fas fa-chart-line"></i><span>Reports</span></a>
<a href="settings.php" class="menu-item"><i class="fas fa-cog"></i><span>Settings</span></a>
</div></nav><div class="sidebar-footer"><a href="../logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a></div>
</aside>
<main class="cyber-main"><header class="cyber-header">
<div class="page-title-section"><div class="page-icon-orb"><i class="fas fa-chart-line"></i></div>
<h1 class="page-title">Reports</h1></div></header>
<div class="cyber-content fade-in">
<div class="holo-card"><div class="card-header"><div class="card-title"><i class="fas fa-download"></i> <span>Generate Reports</span></div></div>
<div class="card-body" style="text-align:center;padding:60px 20px;">
<i class="fas fa-file-pdf" style="font-size:4rem;color:var(--cyber-cyan);margin-bottom:20px;"></i>
<h3 style="color:var(--text-primary);margin-bottom:10px;">Attendance Reports</h3>
<p style="color:var(--text-muted);margin-bottom:30px;">Download detailed attendance reports for your classes</p>
<button class="cyber-btn primary"><i class="fas fa-download"></i> Generate PDF Report</button>
</div></div>
</div></main></div></body></html>
EOF

echo "Teacher attendance & reports pages created!"
