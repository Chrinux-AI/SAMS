<?php

/**
 * Admin - Emergency Alert System
 * Broadcast urgent alerts to staff/students/parents
 */
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_admin();

$user_id = $_SESSION['user_id'];
$page_title = "Emergency Alerts";

// Handle alert creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    $title = trim($_POST['title'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $severity = $_POST['severity'] ?? 'info';
    $target_roles = isset($_POST['target_roles']) ? implode(',', $_POST['target_roles']) : null;
    $send_email = isset($_POST['send_email']) ? 1 : 0;
    $requires_ack = isset($_POST['requires_acknowledgment']) ? 1 : 0;
    $expires_at = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;

    if ($title && $message) {
        db()->execute("
            INSERT INTO emergency_alerts (created_by, title, message, severity, target_roles, send_email, requires_acknowledgment, expires_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ", [$user_id, $title, $message, $severity, $target_roles, $send_email, $requires_ack, $expires_at]);

        $_SESSION['success_message'] = "Emergency alert broadcast successfully!";
        header("Location: emergency-alerts.php");
        exit;
    }
}

// Get all alerts
$alerts = db()->fetchAll("
    SELECT ea.*, u.first_name, u.last_name,
           (SELECT COUNT(*) FROM alert_acknowledgments WHERE alert_id = ea.id) as ack_count
    FROM emergency_alerts ea
    JOIN users u ON ea.created_by = u.id
    ORDER BY ea.created_at DESC
    LIMIT 50
");

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <script src="../assets/js/theme-loader.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'Emergency Alerts'; ?> - <?php echo APP_NAME; ?></title>
    <link rel="manifest" href="/attendance/manifest.json">
    <meta name="theme-color" content="#00BFFF">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Orbitron:wght@500;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="../assets/css/professional-ui.css" rel="stylesheet">
</head>

<body>
    <?php include '../includes/sidebar-nav.php'; ?>

    <div class="app-layout">
        <div class="cyber-main">
            <div class="cyber-content">
                <div class="content-header">
                    <h1><i class="fas fa-exclamation-triangle"></i> Emergency Alerts</h1>
                    <p class="subtitle">Broadcast urgent messages system-wide</p>
                    <button onclick="document.getElementById('alertModal').style.display='flex'" class="cyber-btn">
                        <i class="fas fa-bell"></i> Send Alert
                    </button>
                </div>

                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success_message'];
                                                            unset($_SESSION['success_message']); ?>
                    </div>
                <?php endif; ?>

                <!-- Alert History -->
                <div class="holo-card">
                    <div class="card-header">
                        <div class="card-title"><i class="fas fa-history"></i> Alert History (<?php echo count($alerts); ?>)</div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($alerts)): ?>
                            <div class="empty-state">
                                <i class="fas fa-bell-slash" style="font-size: 4rem; opacity: 0.3;"></i>
                                <h3>No alerts sent</h3>
                                <p>Emergency alerts will appear here</p>
                            </div>
                        <?php else: ?>
                            <div class="alerts-list">
                                <?php foreach ($alerts as $alert): ?>
                                    <div class="alert-item severity-<?php echo $alert['severity']; ?>">
                                        <div class="alert-icon">
                                            <?php if ($alert['severity'] === 'critical'): ?>
                                                <i class="fas fa-exclamation-triangle"></i>
                                            <?php elseif ($alert['severity'] === 'warning'): ?>
                                                <i class="fas fa-exclamation-circle"></i>
                                            <?php else: ?>
                                                <i class="fas fa-info-circle"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="alert-content">
                                            <div class="alert-header">
                                                <h3><?php echo htmlspecialchars($alert['title']); ?></h3>
                                                <span class="severity-badge <?php echo $alert['severity']; ?>">
                                                    <?php echo strtoupper($alert['severity']); ?>
                                                </span>
                                            </div>
                                            <p class="alert-message"><?php echo nl2br(htmlspecialchars($alert['message'])); ?></p>
                                            <div class="alert-meta">
                                                <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($alert['first_name'] . ' ' . $alert['last_name']); ?></span>
                                                <span><i class="fas fa-clock"></i> <?php echo date('M d, Y g:i A', strtotime($alert['created_at'])); ?></span>
                                                <?php if ($alert['target_roles']): ?>
                                                    <span><i class="fas fa-users"></i> <?php echo ucfirst(str_replace(',', ', ', $alert['target_roles'])); ?></span>
                                                <?php else: ?>
                                                    <span><i class="fas fa-globe"></i> All Users</span>
                                                <?php endif; ?>
                                                <?php if ($alert['send_email']): ?>
                                                    <span class="sent-badge"><i class="fas fa-envelope"></i> Email Sent</span>
                                                <?php endif; ?>
                                                <?php if ($alert['requires_acknowledgment']): ?>
                                                    <span class="ack-badge"><i class="fas fa-check"></i> <?php echo $alert['ack_count']; ?> Acknowledged</span>
                                                <?php endif; ?>
                                                <?php if ($alert['expires_at']): ?>
                                                    <span><i class="fas fa-calendar-times"></i> Expires: <?php echo date('M d, Y', strtotime($alert['expires_at'])); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Create Alert Modal -->
            <div id="alertModal" class="modal">
                <div class="modal-content" style="max-width: 700px;">
                    <div class="modal-header">
                        <h2><i class="fas fa-bell"></i> Send Emergency Alert</h2>
                        <button onclick="document.getElementById('alertModal').style.display='none'" class="close-btn">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="modal-body">
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="create">

                            <div class="form-group">
                                <label>Alert Title <span class="required">*</span></label>
                                <input type="text" name="title" class="cyber-input" placeholder="e.g., Fire Drill, School Closure" required>
                            </div>

                            <div class="form-group">
                                <label>Severity Level <span class="required">*</span></label>
                                <select name="severity" class="cyber-input" required>
                                    <option value="info">Info - General information</option>
                                    <option value="warning">Warning - Important notice</option>
                                    <option value="critical">Critical - Urgent action required</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Message <span class="required">*</span></label>
                                <textarea name="message" class="cyber-input" rows="5" placeholder="Detailed alert message..." required></textarea>
                            </div>

                            <div class="form-group">
                                <label>Target Recipients</label>
                                <div class="checkbox-group">
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="target_roles[]" value="admin">
                                        <span>Administrators</span>
                                    </label>
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="target_roles[]" value="teacher">
                                        <span>Teachers</span>
                                    </label>
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="target_roles[]" value="student">
                                        <span>Students</span>
                                    </label>
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="target_roles[]" value="parent">
                                        <span>Parents</span>
                                    </label>
                                </div>
                                <small class="form-help">Leave unchecked to send to all users</small>
                            </div>

                            <div class="form-group">
                                <label>Expires At</label>
                                <input type="datetime-local" name="expires_at" class="cyber-input">
                                <small class="form-help">Optional - Alert will hide after this date/time</small>
                            </div>

                            <div class="form-group">
                                <label class="checkbox-label">
                                    <input type="checkbox" name="send_email" checked>
                                    <span>Send email notification</span>
                                </label>
                            </div>

                            <div class="form-group">
                                <label class="checkbox-label">
                                    <input type="checkbox" name="requires_acknowledgment">
                                    <span>Require acknowledgment from recipients</span>
                                </label>
                            </div>

                            <div class="form-actions">
                                <button type="submit" class="cyber-btn" style="background: var(--danger-color);">
                                    <i class="fas fa-bell"></i> Broadcast Alert
                                </button>
                                <button type="button" onclick="document.getElementById('alertModal').style.display='none'" class="cyber-btn-outline">
                                    Cancel
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <style>
                .alerts-list {
                    display: flex;
                    flex-direction: column;
                    gap: 15px;
                }

                .alert-item {
                    display: flex;
                    gap: 20px;
                    padding: 20px;
                    border-radius: 12px;
                    border-left: 5px solid;
                    animation: slideIn 0.3s ease;
                }

                @keyframes slideIn {
                    from {
                        opacity: 0;
                        transform: translateX(-20px);
                    }

                    to {
                        opacity: 1;
                        transform: translateX(0);
                    }
                }

                .alert-item.severity-info {
                    background: rgba(59, 130, 246, 0.05);
                    border-left-color: #3b82f6;
                }

                .alert-item.severity-warning {
                    background: rgba(245, 158, 11, 0.05);
                    border-left-color: var(--warning-color);
                }

                .alert-item.severity-critical {
                    background: rgba(239, 68, 68, 0.05);
                    border-left-color: var(--danger-color);
                    animation: pulse 2s ease-in-out infinite;
                }

                @keyframes pulse {

                    0%,
                    100% {
                        box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.4);
                    }

                    50% {
                        box-shadow: 0 0 0 15px rgba(239, 68, 68, 0);
                    }
                }

                .alert-icon {
                    width: 60px;
                    height: 60px;
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-size: 2rem;
                    flex-shrink: 0;
                }

                .severity-info .alert-icon {
                    background: rgba(59, 130, 246, 0.2);
                    color: #3b82f6;
                }

                .severity-warning .alert-icon {
                    background: rgba(245, 158, 11, 0.2);
                    color: var(--warning-color);
                }

                .severity-critical .alert-icon {
                    background: rgba(239, 68, 68, 0.2);
                    color: var(--danger-color);
                }

                .alert-content {
                    flex: 1;
                }

                .alert-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    margin-bottom: 12px;
                }

                .alert-header h3 {
                    margin: 0;
                    color: var(--cyber-cyan);
                    font-size: 1.3rem;
                }

                .severity-badge {
                    padding: 6px 14px;
                    border-radius: 15px;
                    font-size: 0.75rem;
                    font-weight: 700;
                }

                .severity-badge.info {
                    background: rgba(59, 130, 246, 0.2);
                    color: #3b82f6;
                }

                .severity-badge.warning {
                    background: rgba(245, 158, 11, 0.2);
                    color: var(--warning-color);
                }

                .severity-badge.critical {
                    background: rgba(239, 68, 68, 0.2);
                    color: var(--danger-color);
                }

                .alert-message {
                    margin: 12px 0;
                    line-height: 1.7;
                    color: var(--text-color);
                    font-size: 1.05rem;
                }

                .alert-meta {
                    display: flex;
                    gap: 15px;
                    flex-wrap: wrap;
                    font-size: 0.85rem;
                    color: var(--text-muted);
                    margin-top: 15px;
                    padding-top: 15px;
                    border-top: 1px solid rgba(0, 243, 255, 0.1);
                }

                .sent-badge,
                .ack-badge {
                    color: var(--success-color);
                    font-weight: 600;
                }

                .checkbox-group {
                    display: grid;
                    grid-template-columns: repeat(2, 1fr);
                    gap: 10px;
                    margin: 10px 0;
                }
            </style>

        </div>
    </div>
    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/pwa-manager.js"></script>
    <script src="../assets/js/pwa-analytics.js"></script>
</body>

</html>
