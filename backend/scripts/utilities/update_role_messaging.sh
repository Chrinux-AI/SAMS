#!/bin/bash

# Update teacher messages (create new functional one)
cat > teacher/messages.php << 'TEACHERMSG'
<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {header('Location: ../login.php');exit;}
$full_name = $_SESSION['full_name'];
$user_id = $_SESSION['user_id'];

// Handle sending message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $receiver_id = intval($_POST['receiver_id']);
    $subject = htmlspecialchars($_POST['subject']);
    $message = htmlspecialchars($_POST['message']);
    db()->query("INSERT INTO messages (sender_id, receiver_id, subject, message) VALUES (?, ?, ?, ?)", 
        [$user_id, $receiver_id, $subject, $message]);
    $success_msg = "Message sent successfully!";
}

// Get students and admin
$students = db()->query("SELECT DISTINCT u.id, u.full_name, u.email, 'student' as role 
    FROM users u 
    JOIN students s ON u.id = s.user_id 
    JOIN class_enrollments ce ON s.id = ce.student_id 
    JOIN classes c ON ce.class_id = c.id 
    WHERE c.teacher_id = ?", [$user_id]);
$admin = db()->query("SELECT id, full_name, email, 'admin' as role FROM users WHERE role = 'admin'");
$all_users = array_merge($admin, $students);

$received = db()->query("SELECT m.*, u.full_name as sender_name, u.role as sender_role 
    FROM messages m JOIN users u ON m.sender_id = u.id 
    WHERE m.receiver_id = ? ORDER BY m.created_at DESC LIMIT 50", [$user_id]);
$sent = db()->query("SELECT m.*, u.full_name as receiver_name, u.role as receiver_role 
    FROM messages m JOIN users u ON m.receiver_id = u.id 
    WHERE m.sender_id = ? ORDER BY m.created_at DESC LIMIT 50", [$user_id]);
$unread_count = db()->count('messages', 'receiver_id = ? AND is_read = 0', ['receiver_id' => $user_id]);
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
<div class="logo-text"><div class="app-name">Attendance AI</div><div class="app-tagline">TEACHER PANEL</div></div></div></div>
<div class="user-card"><div class="user-avatar"><?php echo strtoupper(substr($full_name, 0, 2)); ?></div>
<div class="user-info"><div class="user-name"><?php echo htmlspecialchars($full_name); ?></div><div class="user-role">Teacher</div></div></div>
<nav class="sidebar-nav"><div class="nav-section"><div class="section-title">CORE</div>
<a href="dashboard.php" class="menu-item"><i class="fas fa-brain"></i><span>Dashboard</span></a>
<a href="my-classes.php" class="menu-item"><i class="fas fa-door-open"></i><span>My Classes</span></a>
<a href="attendance.php" class="menu-item"><i class="fas fa-clipboard-check"></i><span>Attendance</span></a>
<a href="students.php" class="menu-item"><i class="fas fa-user-graduate"></i><span>My Students</span></a>
</div><div class="nav-section"><div class="section-title">COMMUNICATION</div>
<a href="messages.php" class="menu-item active"><i class="fas fa-comments"></i><span>Messages</span></a>
</div><div class="nav-section"><div class="section-title">MANAGEMENT</div>
<a href="reports.php" class="menu-item"><i class="fas fa-chart-line"></i><span>Reports</span></a>
<a href="settings.php" class="menu-item"><i class="fas fa-cog"></i><span>Settings</span></a>
</div></nav><div class="sidebar-footer"><a href="../logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a></div>
</aside>
<main class="cyber-main"><header class="cyber-header">
<div class="page-title-section"><div class="page-icon-orb"><i class="fas fa-comments"></i></div>
<h1 class="page-title">Messages</h1></div>
<div class="header-actions">
<div class="stat-badge" style="background:rgba(0,191,255,0.1);border:1px solid var(--cyber-cyan);padding:8px 15px;border-radius:8px;">
<i class="fas fa-envelope"></i> <?php echo $unread_count; ?> Unread
</div></div></header>
<div class="cyber-content fade-in">
<?php if(isset($success_msg)): ?>
<div style="padding:15px 20px;border-radius:10px;margin-bottom:20px;display:flex;align-items:center;gap:12px;background:rgba(0,255,127,0.1);border:1px solid var(--neon-green);color:var(--neon-green);">
<i class="fas fa-check-circle"></i><span><?php echo $success_msg; ?></span>
</div>
<?php endif; ?>
<div style="display:grid;grid-template-columns:1fr 2fr;gap:25px;margin-bottom:30px;">
<div class="holo-card">
<div class="card-header"><div class="card-title"><i class="fas fa-paper-plane"></i> <span>Send Message</span></div></div>
<div class="card-body"><form method="POST">
<div style="margin-bottom:15px;">
<label style="color:var(--cyber-cyan);font-size:0.85rem;font-weight:600;margin-bottom:8px;display:block;">TO</label>
<select name="receiver_id" required style="width:100%;padding:10px;background:rgba(0,191,255,0.05);border:1px solid var(--cyber-cyan);border-radius:8px;color:var(--cyber-cyan);">
<option value="">Select recipient...</option>
<?php foreach($all_users as $user): ?>
<option value="<?php echo $user['id']; ?>">[<?php echo strtoupper($user['role']); ?>] <?php echo htmlspecialchars($user['full_name']); ?></option>
<?php endforeach; ?>
</select></div>
<div style="margin-bottom:15px;">
<label style="color:var(--cyber-cyan);font-size:0.85rem;font-weight:600;margin-bottom:8px;display:block;">SUBJECT</label>
<input type="text" name="subject" required style="width:100%;padding:10px;background:rgba(0,191,255,0.05);border:1px solid var(--cyber-cyan);border-radius:8px;color:var(--cyber-cyan);"></div>
<div style="margin-bottom:15px;">
<label style="color:var(--cyber-cyan);font-size:0.85rem;font-weight:600;margin-bottom:8px;display:block;">MESSAGE</label>
<textarea name="message" required rows="6" style="width:100%;padding:10px;background:rgba(0,191,255,0.05);border:1px solid var(--cyber-cyan);border-radius:8px;color:var(--cyber-cyan);font-family:Rajdhani;"></textarea></div>
<button type="submit" name="send_message" class="cyber-btn primary" style="width:100%;"><i class="fas fa-paper-plane"></i> Send</button>
</form></div></div>
<div class="holo-card">
<div class="card-header"><div class="card-title"><i class="fas fa-inbox"></i> <span>Inbox</span></div></div>
<div class="card-body" style="max-height:400px;overflow-y:auto;">
<?php foreach(array_slice($received, 0, 10) as $msg): ?>
<div style="padding:12px;background:<?php echo $msg['is_read']?'rgba(0,191,255,0.03)':'rgba(0,255,127,0.08)'; ?>;border-left:3px solid <?php echo $msg['is_read']?'var(--cyber-cyan)':'var(--neon-green)'; ?>;margin-bottom:10px;border-radius:6px;">
<div style="display:flex;justify-content:space-between;margin-bottom:5px;">
<span style="color:var(--cyber-cyan);font-weight:600;font-size:0.9rem;"><?php echo htmlspecialchars($msg['sender_name']); ?></span>
<span style="color:var(--text-muted);font-size:0.8rem;"><?php echo date('M j, g:i A', strtotime($msg['created_at'])); ?></span>
</div>
<div style="color:var(--text-primary);font-weight:600;margin-bottom:5px;"><?php echo htmlspecialchars($msg['subject']); ?></div>
<div style="color:var(--text-muted);font-size:0.9rem;"><?php echo substr(htmlspecialchars($msg['message']), 0, 80); ?>...</div>
</div>
<?php endforeach; ?>
</div></div>
</div>
<div class="holo-card">
<div class="card-header"><div class="card-title"><i class="fas fa-paper-plane"></i> <span>Sent Messages</span></div></div>
<div class="card-body"><table class="holo-table">
<thead><tr><th>To</th><th>Subject</th><th>Date</th></tr></thead>
<tbody>
<?php foreach($sent as $msg): ?>
<tr><td><?php echo htmlspecialchars($msg['receiver_name']); ?></td>
<td><?php echo htmlspecialchars($msg['subject']); ?></td>
<td style="font-size:0.85rem;"><?php echo date('M j, g:i A', strtotime($msg['created_at'])); ?></td></tr>
<?php endforeach; ?>
</tbody></table></div></div>
</div></main></div></body></html>
TEACHERMSG

echo "Teacher messages page created!"
