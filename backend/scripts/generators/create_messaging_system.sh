#!/bin/bash

# Create admin/messages.php
cat > admin/messages.php << 'ADMINMSG'
<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_admin();

$full_name = $_SESSION['full_name'];
$user_id = $_SESSION['user_id'];

// Handle sending message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $receiver_id = intval($_POST['receiver_id']);
    $subject = sanitize($_POST['subject']);
    $message = sanitize($_POST['message']);
    
    db()->query("INSERT INTO messages (sender_id, receiver_id, subject, message) VALUES (?, ?, ?, ?)", 
        [$user_id, $receiver_id, $subject, $message]);
    $success_msg = "Message sent successfully!";
}

// Get all users (teachers, students, parents)
$users = db()->query("SELECT id, full_name, role, email FROM users WHERE role != 'admin' ORDER BY role, full_name");

// Get received messages
$received = db()->query("SELECT m.*, u.full_name as sender_name, u.role as sender_role 
    FROM messages m 
    JOIN users u ON m.sender_id = u.id 
    WHERE m.receiver_id = ? 
    ORDER BY m.created_at DESC LIMIT 50", [$user_id]);

// Get sent messages
$sent = db()->query("SELECT m.*, u.full_name as receiver_name, u.role as receiver_role 
    FROM messages m 
    JOIN users u ON m.receiver_id = u.id 
    WHERE m.sender_id = ? 
    ORDER BY m.created_at DESC LIMIT 50", [$user_id]);

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
<?php include 'cyber-nav.php'; ?>
<main class="cyber-main"><header class="cyber-header">
<div class="page-title-section"><div class="page-icon-orb"><i class="fas fa-comments"></i></div>
<h1 class="page-title">Communication Center</h1></div>
<div class="header-actions">
<div class="stat-badge" style="background:rgba(0,191,255,0.1);border:1px solid var(--cyber-cyan);padding:8px 15px;border-radius:8px;">
<i class="fas fa-envelope"></i> <?php echo $unread_count; ?> Unread
</div>
</div></header>
<div class="cyber-content fade-in">
<?php if(isset($success_msg)): ?>
<div class="cyber-alert success" style="margin-bottom:20px;">
<i class="fas fa-check-circle"></i><span><?php echo $success_msg; ?></span>
</div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 2fr;gap:25px;margin-bottom:30px;">
<div class="holo-card">
<div class="card-header"><div class="card-title"><i class="fas fa-paper-plane"></i> <span>New Message</span></div></div>
<div class="card-body">
<form method="POST">
<div style="margin-bottom:15px;">
<label style="color:var(--cyber-cyan);font-size:0.85rem;font-weight:600;margin-bottom:8px;display:block;">TO</label>
<select name="receiver_id" required style="width:100%;padding:10px;background:rgba(0,191,255,0.05);border:1px solid var(--cyber-cyan);border-radius:8px;color:var(--cyber-cyan);">
<option value="">Select recipient...</option>
<?php foreach($users as $user): ?>
<option value="<?php echo $user['id']; ?>">[<?php echo strtoupper($user['role']); ?>] <?php echo htmlspecialchars($user['full_name']); ?></option>
<?php endforeach; ?>
</select>
</div>
<div style="margin-bottom:15px;">
<label style="color:var(--cyber-cyan);font-size:0.85rem;font-weight:600;margin-bottom:8px;display:block;">SUBJECT</label>
<input type="text" name="subject" required style="width:100%;padding:10px;background:rgba(0,191,255,0.05);border:1px solid var(--cyber-cyan);border-radius:8px;color:var(--cyber-cyan);">
</div>
<div style="margin-bottom:15px;">
<label style="color:var(--cyber-cyan);font-size:0.85rem;font-weight:600;margin-bottom:8px;display:block;">MESSAGE</label>
<textarea name="message" required rows="6" style="width:100%;padding:10px;background:rgba(0,191,255,0.05);border:1px solid var(--cyber-cyan);border-radius:8px;color:var(--cyber-cyan);font-family:Rajdhani;"></textarea>
</div>
<button type="submit" name="send_message" class="cyber-btn primary" style="width:100%;"><i class="fas fa-paper-plane"></i> Send Message</button>
</form>
</div></div>

<div class="holo-card">
<div class="card-header"><div class="card-title"><i class="fas fa-users"></i> <span>All Users</span></div></div>
<div class="card-body" style="max-height:400px;overflow-y:auto;">
<?php 
$grouped = [];
foreach($users as $user) {
    $grouped[$user['role']][] = $user;
}
foreach($grouped as $role => $role_users): ?>
<div style="margin-bottom:15px;">
<h4 style="color:var(--neon-green);font-size:0.9rem;margin-bottom:8px;"><?php echo strtoupper($role).'S'; ?></h4>
<?php foreach($role_users as $u): ?>
<div style="padding:8px;background:rgba(0,191,255,0.05);border-radius:6px;margin-bottom:5px;display:flex;justify-content:space-between;align-items:center;">
<span style="color:var(--text-primary);font-size:0.9rem;"><?php echo htmlspecialchars($u['full_name']); ?></span>
<span class="status-badge active" style="font-size:0.75rem;"><?php echo strtoupper($u['role']); ?></span>
</div>
<?php endforeach; ?>
</div>
<?php endforeach; ?>
</div></div>
</div>

<div style="display:grid;grid-template-columns:repeat(2,1fr);gap:25px;">
<div class="holo-card">
<div class="card-header"><div class="card-title"><i class="fas fa-inbox"></i> <span>Received Messages</span></div></div>
<div class="card-body">
<table class="holo-table">
<thead><tr><th>From</th><th>Subject</th><th>Date</th><th>Status</th></tr></thead>
<tbody>
<?php foreach($received as $msg): ?>
<tr style="<?php echo $msg['is_read'] ? '' : 'background:rgba(0,255,127,0.05);'; ?>">
<td><span class="status-badge <?php echo $msg['sender_role']==='teacher'?'warning':'active'; ?>"><?php echo strtoupper($msg['sender_role']); ?></span> <?php echo htmlspecialchars($msg['sender_name']); ?></td>
<td><?php echo htmlspecialchars($msg['subject']); ?></td>
<td style="font-size:0.85rem;"><?php echo date('M j, g:i A', strtotime($msg['created_at'])); ?></td>
<td><?php echo $msg['is_read'] ? '<span class="status-badge">Read</span>' : '<span class="status-badge active">New</span>'; ?></td>
</tr>
<?php endforeach; ?>
</tbody></table>
</div></div>

<div class="holo-card">
<div class="card-header"><div class="card-title"><i class="fas fa-paper-plane"></i> <span>Sent Messages</span></div></div>
<div class="card-body">
<table class="holo-table">
<thead><tr><th>To</th><th>Subject</th><th>Date</th></tr></thead>
<tbody>
<?php foreach($sent as $msg): ?>
<tr>
<td><span class="status-badge <?php echo $msg['receiver_role']==='teacher'?'warning':'active'; ?>"><?php echo strtoupper($msg['receiver_role']); ?></span> <?php echo htmlspecialchars($msg['receiver_name']); ?></td>
<td><?php echo htmlspecialchars($msg['subject']); ?></td>
<td style="font-size:0.85rem;"><?php echo date('M j, g:i A', strtotime($msg['created_at'])); ?></td>
</tr>
<?php endforeach; ?>
</tbody></table>
</div></div>
</div>

</div></main></div></body></html>
ADMINMSG

echo "Admin messages page created!"
