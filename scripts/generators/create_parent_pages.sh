#!/bin/bash

# Create parent/children.php
cat > parent/children.php << 'CHILDEOF'
<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'parent') {header('Location: ../login.php');exit;}
$full_name = $_SESSION['full_name'];
$parent_id = $_SESSION['user_id'];
$children = db()->query("SELECT s.*, u.full_name, u.email FROM students s JOIN users u ON s.user_id = u.id WHERE s.parent_id = ?", [$parent_id]);
?>
<!DOCTYPE html>
<html><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>My Children - <?php echo APP_NAME; ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Orbitron:wght@500;700;900&family=Rajdhani:wght@500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="../assets/css/cyberpunk-ui.css" rel="stylesheet">
</head><body>
<div class="cyber-bg"><div class="starfield"></div></div><div class="cyber-grid"></div>
<div class="cyber-layout">
<aside class="cyber-sidebar">
<div class="sidebar-header"><div class="logo-wrapper"><div class="logo-icon"><i class="fas fa-graduation-cap"></i></div>
<div class="logo-text"><div class="app-name">Attendance AI</div><div class="app-tagline">PARENT PANEL</div></div></div></div>
<div class="user-card"><div class="user-avatar"><?php echo strtoupper(substr($full_name, 0, 2)); ?></div>
<div class="user-info"><div class="user-name"><?php echo htmlspecialchars($full_name); ?></div><div class="user-role">Parent</div></div></div>
<nav class="sidebar-nav"><div class="nav-section"><div class="section-title">CORE</div>
<a href="dashboard.php" class="menu-item"><i class="fas fa-brain"></i><span>Dashboard</span></a>
<a href="children.php" class="menu-item active"><i class="fas fa-child"></i><span>My Children</span></a>
<a href="attendance.php" class="menu-item"><i class="fas fa-clipboard-check"></i><span>Attendance</span></a>
<a href="communication.php" class="menu-item"><i class="fas fa-comments"></i><span>Messages</span></a>
</div><div class="nav-section"><div class="section-title">MANAGEMENT</div>
<a href="reports.php" class="menu-item"><i class="fas fa-chart-line"></i><span>Reports</span></a>
<a href="settings.php" class="menu-item"><i class="fas fa-cog"></i><span>Settings</span></a>
</div></nav><div class="sidebar-footer"><a href="../logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a></div>
</aside>
<main class="cyber-main"><header class="cyber-header">
<div class="page-title-section"><div class="page-icon-orb"><i class="fas fa-child"></i></div>
<h1 class="page-title">My Children</h1></div></header>
<div class="cyber-content fade-in">
<div class="holo-card"><div class="card-header"><div class="card-title"><i class="fas fa-users"></i> <span>Linked Students</span></div></div>
<div class="card-body"><table class="holo-table">
<thead><tr><th>Student Name</th><th>Email</th><th>Grade Level</th><th>Student ID</th></tr></thead>
<tbody>
<?php foreach($children as $child): ?>
<tr><td><?php echo htmlspecialchars($child['full_name']); ?></td>
<td><?php echo htmlspecialchars($child['email']); ?></td>
<td><span class="status-badge active"><?php echo $child['grade_level']; ?> Level</span></td>
<td><code style="color:var(--cyber-cyan);"><?php echo htmlspecialchars($child['student_id']); ?></code></td>
</tr>
<?php endforeach; ?>
</tbody></table></div></div>
</div></main></div></body></html>
CHILDEOF

# Create parent/attendance.php
cat > parent/attendance.php << 'ATTENDEOF'
<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'parent') {header('Location: ../login.php');exit;}
$full_name = $_SESSION['full_name'];
$parent_id = $_SESSION['user_id'];
$children = db()->query("SELECT s.*, u.full_name FROM students s JOIN users u ON s.user_id = u.id WHERE s.parent_id = ?", [$parent_id]);
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
<div class="logo-text"><div class="app-name">Attendance AI</div><div class="app-tagline">PARENT PANEL</div></div></div></div>
<div class="user-card"><div class="user-avatar"><?php echo strtoupper(substr($full_name, 0, 2)); ?></div>
<div class="user-info"><div class="user-name"><?php echo htmlspecialchars($full_name); ?></div><div class="user-role">Parent</div></div></div>
<nav class="sidebar-nav"><div class="nav-section"><div class="section-title">CORE</div>
<a href="dashboard.php" class="menu-item"><i class="fas fa-brain"></i><span>Dashboard</span></a>
<a href="children.php" class="menu-item"><i class="fas fa-child"></i><span>My Children</span></a>
<a href="attendance.php" class="menu-item active"><i class="fas fa-clipboard-check"></i><span>Attendance</span></a>
<a href="communication.php" class="menu-item"><i class="fas fa-comments"></i><span>Messages</span></a>
</div><div class="nav-section"><div class="section-title">MANAGEMENT</div>
<a href="reports.php" class="menu-item"><i class="fas fa-chart-line"></i><span>Reports</span></a>
<a href="settings.php" class="menu-item"><i class="fas fa-cog"></i><span>Settings</span></a>
</div></nav><div class="sidebar-footer"><a href="../logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a></div>
</aside>
<main class="cyber-main"><header class="cyber-header">
<div class="page-title-section"><div class="page-icon-orb"><i class="fas fa-clipboard-check"></i></div>
<h1 class="page-title">Children Attendance</h1></div></header>
<div class="cyber-content fade-in">
<?php foreach($children as $child): 
$attendance = db()->query("SELECT status, COUNT(*) as count FROM attendance_records WHERE student_id = ? GROUP BY status", [$child['id']]);
$total = array_sum(array_column($attendance, 'count'));
$present = 0;
foreach($attendance as $rec) { if($rec['status'] === 'present') $present = $rec['count']; }
$rate = $total > 0 ? round(($present / $total) * 100, 1) : 0;
?>
<div class="holo-card" style="margin-bottom:20px;">
<div class="card-header"><div class="card-title"><i class="fas fa-user-graduate"></i> <span><?php echo htmlspecialchars($child['full_name']); ?></span></div></div>
<div class="card-body">
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:15px;">
<div class="stat-orb"><div class="stat-icon cyber"><i class="fas fa-percentage"></i></div>
<div class="stat-label">Attendance Rate</div><div class="stat-value"><?php echo $rate; ?>%</div></div>
<?php foreach($attendance as $rec): ?>
<div class="stat-orb"><div class="stat-icon <?php echo $rec['status']==='present'?'cyber':'warning'; ?>"><i class="fas fa-<?php echo $rec['status']==='present'?'check':'times'; ?>"></i></div>
<div class="stat-label"><?php echo ucfirst($rec['status']); ?></div><div class="stat-value"><?php echo $rec['count']; ?></div></div>
<?php endforeach; ?>
</div>
</div></div>
<?php endforeach; ?>
</div></main></div></body></html>
ATTENDEOF

# Create parent/communication.php
cat > parent/communication.php << 'COMMEOF'
<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'parent') {header('Location: ../login.php');exit;}
$full_name = $_SESSION['full_name'];
?>
<!DOCTYPE html>
<html><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Messages - <?php echo APP_NAME; ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Orbitron:wght@500;700;900&family=Rajdhani:wght@500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="../assets/css/cyberpunk-ui.css" rel="stylesheet">
</head><body>
<div class="cyber-bg"><div class="starfield"></div></div><div class="cyber-grid"></div>
<div class="cyber-layout">
<aside class="cyber-sidebar">
<div class="sidebar-header"><div class="logo-wrapper"><div class="logo-icon"><i class="fas fa-graduation-cap"></i></div>
<div class="logo-text"><div class="app-name">Attendance AI</div><div class="app-tagline">PARENT PANEL</div></div></div></div>
<div class="user-card"><div class="user-avatar"><?php echo strtoupper(substr($full_name, 0, 2)); ?></div>
<div class="user-info"><div class="user-name"><?php echo htmlspecialchars($full_name); ?></div><div class="user-role">Parent</div></div></div>
<nav class="sidebar-nav"><div class="nav-section"><div class="section-title">CORE</div>
<a href="dashboard.php" class="menu-item"><i class="fas fa-brain"></i><span>Dashboard</span></a>
<a href="children.php" class="menu-item"><i class="fas fa-child"></i><span>My Children</span></a>
<a href="attendance.php" class="menu-item"><i class="fas fa-clipboard-check"></i><span>Attendance</span></a>
<a href="communication.php" class="menu-item active"><i class="fas fa-comments"></i><span>Messages</span></a>
</div><div class="nav-section"><div class="section-title">MANAGEMENT</div>
<a href="reports.php" class="menu-item"><i class="fas fa-chart-line"></i><span>Reports</span></a>
<a href="settings.php" class="menu-item"><i class="fas fa-cog"></i><span>Settings</span></a>
</div></nav><div class="sidebar-footer"><a href="../logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a></div>
</aside>
<main class="cyber-main"><header class="cyber-header">
<div class="page-title-section"><div class="page-icon-orb"><i class="fas fa-comments"></i></div>
<h1 class="page-title">Messages</h1></div></header>
<div class="cyber-content fade-in">
<div class="holo-card"><div class="card-header"><div class="card-title"><i class="fas fa-inbox"></i> <span>Communication Center</span></div></div>
<div class="card-body" style="text-align:center;padding:60px 20px;">
<i class="fas fa-comments" style="font-size:4rem;color:var(--cyber-cyan);margin-bottom:20px;"></i>
<h3 style="color:var(--text-primary);margin-bottom:10px;">Message Teachers</h3>
<p style="color:var(--text-muted);margin-bottom:30px;">Stay connected with your children's teachers</p>
<button class="cyber-btn primary"><i class="fas fa-paper-plane"></i> Send Message</button>
</div></div>
</div></main></div></body></html>
COMMEOF

# Create parent/reports.php
cat > parent/reports.php << 'REPEOF'
<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'parent') {header('Location: ../login.php');exit;}
$full_name = $_SESSION['full_name'];
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
<div class="logo-text"><div class="app-name">Attendance AI</div><div class="app-tagline">PARENT PANEL</div></div></div></div>
<div class="user-card"><div class="user-avatar"><?php echo strtoupper(substr($full_name, 0, 2)); ?></div>
<div class="user-info"><div class="user-name"><?php echo htmlspecialchars($full_name); ?></div><div class="user-role">Parent</div></div></div>
<nav class="sidebar-nav"><div class="nav-section"><div class="section-title">CORE</div>
<a href="dashboard.php" class="menu-item"><i class="fas fa-brain"></i><span>Dashboard</span></a>
<a href="children.php" class="menu-item"><i class="fas fa-child"></i><span>My Children</span></a>
<a href="attendance.php" class="menu-item"><i class="fas fa-clipboard-check"></i><span>Attendance</span></a>
<a href="communication.php" class="menu-item"><i class="fas fa-comments"></i><span>Messages</span></a>
</div><div class="nav-section"><div class="section-title">MANAGEMENT</div>
<a href="reports.php" class="menu-item active"><i class="fas fa-chart-line"></i><span>Reports</span></a>
<a href="settings.php" class="menu-item"><i class="fas fa-cog"></i><span>Settings</span></a>
</div></nav><div class="sidebar-footer"><a href="../logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a></div>
</aside>
<main class="cyber-main"><header class="cyber-header">
<div class="page-title-section"><div class="page-icon-orb"><i class="fas fa-chart-line"></i></div>
<h1 class="page-title">Reports</h1></div></header>
<div class="cyber-content fade-in">
<div class="holo-card"><div class="card-header"><div class="card-title"><i class="fas fa-download"></i> <span>Attendance Reports</span></div></div>
<div class="card-body" style="text-align:center;padding:60px 20px;">
<i class="fas fa-file-pdf" style="font-size:4rem;color:var(--cyber-cyan);margin-bottom:20px;"></i>
<h3 style="color:var(--text-primary);margin-bottom:10px;">Children's Reports</h3>
<p style="color:var(--text-muted);margin-bottom:30px;">Download detailed attendance reports for your children</p>
<button class="cyber-btn primary"><i class="fas fa-download"></i> Generate PDF Report</button>
</div></div>
</div></main></div></body></html>
REPEOF

echo "All parent pages created successfully!"
