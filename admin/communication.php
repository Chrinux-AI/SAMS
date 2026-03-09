<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_admin('../login.php');

$page_title = 'Communication Center';
$page_icon = 'comments';
$full_name = $_SESSION['full_name'];
$user_id = $_SESSION['user_id'];

// Handle send broadcast message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_broadcast'])) {
    $recipients = $_POST['recipients'] ?? [];
    $subject = sanitize($_POST['subject']);
    $message = sanitize($_POST['message']);

    if (!empty($recipients) && $subject && $message) {
        $sent_count = 0;
        foreach ($recipients as $recipient_id) {
            db()->insert('messages', [
                'sender_id' => $user_id,
                'receiver_id' => $recipient_id,
                'subject' => $subject,
                'message' => $message,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            $sent_count++;
        }
        $success_msg = "Broadcast sent to {$sent_count} recipient(s)!";
    } else {
        $error_msg = "Please select recipients and fill in all fields.";
    }
}

// Get statistics
$total_messages = db()->count('messages');
$unread_messages = db()->count('messages', 'receiver_id = ? AND is_read = 0', ['receiver_id' => $user_id]);
$sent_messages = db()->count('messages', 'sender_id = ?', ['sender_id' => $user_id]);

// Get all users for broadcast
$all_users = db()->fetchAll("SELECT id, full_name, email, role FROM users WHERE id != ? ORDER BY role, full_name", [$user_id]);

// Get recent messages
$recent_messages = db()->fetchAll(
    "SELECT m.*,
            sender.full_name as sender_name, sender.role as sender_role,
            receiver.full_name as receiver_name, receiver.role as receiver_role
    FROM messages m
    JOIN users sender ON m.sender_id = sender.id
    JOIN users receiver ON m.receiver_id = receiver.id
    WHERE m.sender_id = ? OR m.receiver_id = ?
    ORDER BY m.created_at DESC
    LIMIT 50",
    [$user_id, $user_id]
);

// Get announcements (messages sent to multiple people with same content)
$announcements = db()->fetchAll(
    "SELECT m.subject, m.message, m.created_at, u.full_name as sender_name,
            COUNT(*) as recipient_count
    FROM messages m
    JOIN users u ON m.sender_id = u.id
    WHERE m.sender_id = ?
    GROUP BY m.subject, m.message, m.created_at, u.full_name
    HAVING recipient_count > 1
    ORDER BY m.created_at DESC
    LIMIT 10",
    [$user_id]
);
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
    
    <style>
        .broadcast-section {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 20px;
            margin-bottom: 30px;
        }

        .recipient-list {
            max-height: 400px;
            overflow-y: auto;
            padding: 10px;
        }

        .recipient-item {
            display: flex;
            align-items: center;
            padding: 10px;
            background: rgba(0, 255, 255, 0.05);
            border-radius: 8px;
            margin-bottom: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .recipient-item:hover {
            background: rgba(0, 255, 255, 0.1);
        }

        .recipient-item input[type="checkbox"] {
            margin-right: 10px;
        }

        .message-thread {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .message-card {
            padding: 15px;
            background: linear-gradient(135deg, rgba(0, 255, 255, 0.05), rgba(255, 0, 255, 0.05));
            border: 1px solid var(--glass-border);
            border-radius: 10px;
            border-left: 3px solid var(--cyber-cyan);
        }

        .message-card.sent {
            border-left-color: var(--neon-purple);
            text-align: right;
        }

        .message-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }

        .message-subject {
            color: var(--cyber-cyan);
            font-weight: 600;
            font-size: 1.05rem;
        }

        .message-body {
            color: var(--text-primary);
            line-height: 1.6;
        }

        .message-meta {
            color: var(--text-muted);
            font-size: 0.85rem;
            margin-top: 10px;
        }

        @media (max-width: 768px) {
            .broadcast-section {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    
    

    <div class="app-layout">
        <?php include '../includes/sidebar-nav.php'; ?>
        <main class="main-content">
            <header class="cyber-header">
                <div class="page-title-section">
                    <div class="page-icon-orb"><i class="fas fa-<?php echo $page_icon; ?>"></i></div>
                    <h1 class="page-title"><?php echo $page_title; ?></h1>
                </div>
                <div class="header-actions">
                    <div class="biometric-orb" title="Quick Scan" onclick="window.location.href='biometric-scan.php'">
                        <i class="fas fa-fingerprint"></i>
                    </div>
                    <div class="user-card" style="padding:8px 15px;margin:0;">
                        <div class="user-avatar" style="width:35px;height:35px;font-size:0.9rem;">
                            <?php echo strtoupper(substr($full_name, 0, 2)); ?>
                        </div>
                        <div class="user-info">
                            <div class="user-name" style="font-size:0.85rem;"><?php echo htmlspecialchars($full_name); ?></div>
                            <div class="user-role">Administrator</div>
                        </div>
                    </div>
                </div>
            </header>

            <div class="cyber-content slide-in">
                <?php if (isset($success_msg)): ?>
                    <div class="alert alert-success"><?php echo $success_msg; ?></div>
                <?php endif; ?>
                <?php if (isset($error_msg)): ?>
                    <div class="alert alert-danger"><?php echo $error_msg; ?></div>
                <?php endif; ?>

                <!-- Statistics -->
                <section class="orb-grid">
                    <div class="stat-orb">
                        <div class="stat-icon cyan"><i class="fas fa-envelope"></i></div>
                        <div class="stat-content">
                            <div class="stat-value"><?php echo number_format($total_messages); ?></div>
                            <div class="stat-label">Total Messages</div>
                        </div>
                    </div>
                    <div class="stat-orb">
                        <div class="stat-icon purple"><i class="fas fa-inbox"></i></div>
                        <div class="stat-content">
                            <div class="stat-value"><?php echo number_format($unread_messages); ?></div>
                            <div class="stat-label">Unread Messages</div>
                            <?php if ($unread_messages > 0): ?>
                                <div class="stat-trend up"><i class="fas fa-exclamation"></i><span>New</span></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="stat-orb">
                        <div class="stat-icon green"><i class="fas fa-paper-plane"></i></div>
                        <div class="stat-content">
                            <div class="stat-value"><?php echo number_format($sent_messages); ?></div>
                            <div class="stat-label">Sent Messages</div>
                        </div>
                    </div>
                </section>

                <!-- Broadcast Message -->
                <div class="holo-card">
                    <div class="card-header">
                        <div class="card-title">
                            <i class="fas fa-broadcast-tower"></i>
                            <span>Send Broadcast Message</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="broadcast-section">
                                <div>
                                    <h4 style="color: var(--cyber-cyan); margin-bottom: 15px;">
                                        <i class="fas fa-users"></i> Select Recipients
                                    </h4>
                                    <div style="margin-bottom: 15px;">
                                        <button type="button" class="cyber-btn secondary" onclick="selectAllRecipients()">
                                            <i class="fas fa-check-double"></i> Select All
                                        </button>
                                        <button type="button" class="cyber-btn secondary" onclick="clearAllRecipients()">
                                            <i class="fas fa-times"></i> Clear All
                                        </button>
                                    </div>
                                    <div class="recipient-list">
                                        <?php foreach ($all_users as $user): ?>
                                            <label class="recipient-item">
                                                <input type="checkbox" name="recipients[]" value="<?php echo $user['id']; ?>">
                                                <div style="flex: 1;">
                                                    <div style="color: var(--text-primary); font-weight: 600;">
                                                        <?php echo htmlspecialchars($user['full_name']); ?>
                                                    </div>
                                                    <div style="color: var(--text-muted); font-size: 0.85rem;">
                                                        <span class="cyber-badge <?php echo $user['role']; ?>">
                                                            <?php echo ucfirst($user['role']); ?>
                                                        </span>
                                                        <span style="margin-left: 10px;"><?php echo htmlspecialchars($user['email']); ?></span>
                                                    </div>
                                                </div>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <div>
                                    <h4 style="color: var(--cyber-cyan); margin-bottom: 15px;">
                                        <i class="fas fa-edit"></i> Compose Message
                                    </h4>
                                    <div style="margin-bottom: 15px;">
                                        <label style="display: block; color: var(--text-muted); margin-bottom: 5px;">Subject</label>
                                        <input type="text" name="subject" class="cyber-input" required placeholder="Enter message subject...">
                                    </div>
                                    <div style="margin-bottom: 15px;">
                                        <label style="display: block; color: var(--text-muted); margin-bottom: 5px;">Message</label>
                                        <textarea name="message" class="cyber-input" rows="10" required placeholder="Enter your message..."></textarea>
                                    </div>
                                    <button type="submit" name="send_broadcast" class="cyber-btn primary">
                                        <i class="fas fa-paper-plane"></i> Send Broadcast
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Recent Messages -->
                <div class="holo-card">
                    <div class="card-header">
                        <div class="card-title">
                            <i class="fas fa-history"></i>
                            <span>Recent Messages</span>
                        </div>
                        <a href="messages.php" class="cyber-btn secondary">
                            <i class="fas fa-inbox"></i> View All Messages
                        </a>
                    </div>
                    <div class="card-body">
                        <div class="message-thread">
                            <?php if (empty($recent_messages)): ?>
                                <p style="color: var(--text-muted); text-align: center; padding: 40px;">
                                    No messages yet
                                </p>
                            <?php else: ?>
                                <?php foreach (array_slice($recent_messages, 0, 10) as $msg): ?>
                                    <div class="message-card <?php echo $msg['sender_id'] == $user_id ? 'sent' : ''; ?>">
                                        <div class="message-header">
                                            <div class="message-subject"><?php echo htmlspecialchars($msg['subject']); ?></div>
                                            <div>
                                                <span class="cyber-badge <?php echo $msg['sender_id'] == $user_id ? $msg['receiver_role'] : $msg['sender_role']; ?>">
                                                    <?php echo $msg['sender_id'] == $user_id ? 'To: ' . htmlspecialchars($msg['receiver_name']) : 'From: ' . htmlspecialchars($msg['sender_name']); ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="message-body"><?php echo nl2br(htmlspecialchars($msg['message'])); ?></div>
                                        <div class="message-meta">
                                            <i class="fas fa-clock"></i> <?php echo date('M d, Y h:i A', strtotime($msg['created_at'])); ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        function selectAllRecipients() {
            document.querySelectorAll('.recipient-item input[type="checkbox"]').forEach(cb => cb.checked = true);
        }

        function clearAllRecipients() {
            document.querySelectorAll('.recipient-item input[type="checkbox"]').forEach(cb => cb.checked = false);
        }
    </script>

    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/pwa-manager.js"></script>
    <script src="../assets/js/pwa-analytics.js"></script>
</body>

</html>