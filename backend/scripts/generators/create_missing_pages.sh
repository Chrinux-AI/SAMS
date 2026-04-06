#!/bin/bash

# Create teacher/my-classes.php
cat > teacher/my-classes.php << 'PHPEOF'
<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {header('Location: ../login.php');exit;}
$full_name = $_SESSION['full_name'];
$teacher_id = $_SESSION['user_id'];
$classes = db()->query("SELECT c.*, COUNT(ce.id) as student_count FROM classes c LEFT JOIN class_enrollments ce ON c.id = ce.class_id WHERE c.teacher_id = ? GROUP BY c.id", [$teacher_id]);
?>
<!DOCTYPE html>
<html><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>My Classes - <?php echo APP_NAME; ?></title>
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
<a href="my-classes.php" class="menu-item active"><i class="fas fa-door-open"></i><span>My Classes</span></a>
<a href="attendance.php" class="menu-item"><i class="fas fa-clipboard-check"></i><span>Attendance</span></a>
<a href="students.php" class="menu-item"><i class="fas fa-user-graduate"></i><span>My Students</span></a>
</div><div class="nav-section"><div class="section-title">MANAGEMENT</div>
<a href="reports.php" class="menu-item"><i class="fas fa-chart-line"></i><span>Reports</span></a>
<a href="settings.php" class="menu-item"><i class="fas fa-cog"></i><span>Settings</span></a>
</div></nav><div class="sidebar-footer"><a href="../logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a></div>
</aside>
<main class="cyber-main"><header class="cyber-header">
<div class="page-title-section"><div class="page-icon-orb"><i class="fas fa-door-open"></i></div>
<h1 class="page-title">My Classes</h1></div></header>
<div class="cyber-content fade-in">
<div class="stats-grid" style="grid-template-columns:repeat(auto-fit,minmax(250px,1fr));margin-bottom:30px;">
<div class="stat-orb"><div class="stat-icon cyber"><i class="fas fa-door-open"></i></div>
<div class="stat-label">Total Classes</div><div class="stat-value"><?php echo count($classes); ?></div></div>
</div>
<div class="holo-card"><div class="card-header"><div class="card-title"><i class="fas fa-list"></i> <span>All My Classes</span></div></div>
<div class="card-body"><table class="holo-table">
<thead><tr><th>Class Name</th><th>Grade Level</th><th>Students</th><th>Class Code</th><th>Actions</th></tr></thead>
<tbody>
<?php foreach($classes as $class): ?>
<tr><td><?php echo htmlspecialchars($class['class_name']); ?></td>
<td><span class="status-badge active"><?php echo $class['grade_level']; ?> Level</span></td>
<td><?php echo $class['student_count']; ?></td>
<td><code style="color:var(--cyber-cyan);"><?php echo htmlspecialchars($class['class_code']); ?></code></td>
<td><a href="attendance.php?class_id=<?php echo $class['id']; ?>" class="cyber-btn primary sm"><i class="fas fa-clipboard-check"></i> Attendance</a></td>
</tr>
<?php endforeach; ?>
</tbody></table></div></div>
</div></main></div></body></html>
PHPEOF

# Create teacher/students.php
cat > teacher/students.php << 'PHPEOF'
<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {header('Location: ../login.php');exit;}
$full_name = $_SESSION['full_name'];
$teacher_id = $_SESSION['user_id'];
$students = db()->query("SELECT s.*, u.full_name, u.email, c.class_name FROM students s 
JOIN users u ON s.user_id = u.id 
JOIN class_enrollments ce ON s.id = ce.student_id 
JOIN classes c ON ce.class_id = c.id 
WHERE c.teacher_id = ? GROUP BY s.id", [$teacher_id]);
?>
<!DOCTYPE html>
<html><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>My Students - <?php echo APP_NAME; ?></title>
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
<a href="students.php" class="menu-item active"><i class="fas fa-user-graduate"></i><span>My Students</span></a>
</div><div class="nav-section"><div class="section-title">MANAGEMENT</div>
<a href="reports.php" class="menu-item"><i class="fas fa-chart-line"></i><span>Reports</span></a>
<a href="settings.php" class="menu-item"><i class="fas fa-cog"></i><span>Settings</span></a>
</div></nav><div class="sidebar-footer"><a href="../logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a></div>
</aside>
<main class="cyber-main"><header class="cyber-header">
<div class="page-title-section"><div class="page-icon-orb"><i class="fas fa-user-graduate"></i></div>
<h1 class="page-title">My Students</h1></div></header>
<div class="cyber-content fade-in">
<div class="holo-card"><div class="card-header"><div class="card-title"><i class="fas fa-users"></i> <span>All Students</span></div></div>
<div class="card-body"><table class="holo-table">
<thead><tr><th>Student Name</th><th>Email</th><th>Grade Level</th><th>Class</th></tr></thead>
<tbody>
<?php foreach($students as $student): ?>
<tr><td><?php echo htmlspecialchars($student['full_name']); ?></td>
<td><?php echo htmlspecialchars($student['email']); ?></td>
<td><span class="status-badge active"><?php echo $student['grade_level']; ?> Level</span></td>
<td><?php echo htmlspecialchars($student['class_name']); ?></td>
</tr>
<?php endforeach; ?>
</tbody></table></div></div>
</div></main></div></body></html>
PHPEOF

echo "Teacher pages created!"
