<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_teacher();


$user_id = $_SESSION['user_id'];

// Get active emergency alerts
$alerts = db()->fetchAll("
    SELECT ea.*, u.username as created_by_name,
           (SELECT COUNT(*) FROM alert_acknowledgments WHERE alert_id = ea.id AND user_id = ?) as acknowledged
    FROM emergency_alerts ea
    LEFT JOIN users u ON ea.created_by = u.id
    WHERE (ea.target_roles IS NULL OR ea.target_roles LIKE '%teacher%')
    AND (ea.expires_at IS NULL OR ea.expires_at > NOW())
    AND ea.is_active = 1
    ORDER BY ea.created_at DESC
", [$user_id, $user_id]);

// Handle acknowledgment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acknowledge'])) {
    $alert_id = (int)$_POST['alert_id'];

    $exists = db()->fetch("
        SELECT id
        FROM alert_acknowledgments
        WHERE alert_id = ? AND user_id = ?
    ", [$alert_id, $user_id]);

    if (!$exists) {
        db()->query("
            INSERT INTO alert_acknowledgments (alert_id, user_id)
            VALUES (?, ?)
        ", [$alert_id, $user_id]);
    }

    header('Location: emergency-alerts.php');
    exit();
}
$page_title = "Emergency Alerts";
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <script src="../assets/js/theme-loader.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="manifest" href="/attendance/manifest.json">
    <meta name="theme-color" content="#00BFFF">
    <link rel="apple-touch-icon" href="/attendance/assets/images/icons/icon-192x192.png"><title><?php echo $page_title; ?> - SAMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="../assets/css/professional-ui.css" rel="stylesheet">
    <link href="../assets/css/pwa-styles.css" rel="stylesheet">
    <style>
        .alerts-container {
            padding: 2rem;
            max-width: 1200px;
            margin: 0 auto;
        }

        .alert-card {
            background: var(--card-bg);
            border-left: 4px solid;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .alert-card.critical {
            border-left-color: #dc2626;
            background: linear-gradient(to right, rgba(220, 38, 38, 0.1), var(--card-bg));
        }

        .alert-card.warning {
            border-left-color: #f59e0b;
            background: linear-gradient(to right, rgba(245, 158, 11, 0.1), var(--card-bg));
        }

        .alert-card.info {
            border-left-color: #3b82f6;
            background: linear-gradient(to right, rgba(59, 130, 246, 0.1), var(--card-bg));
        }

        .alert-card.success {
            border-left-color: #10b981;
            background: linear-gradient(to right, rgba(16, 185, 129, 0.1), var(--card-bg));
        }

        .alert-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .alert-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .severity-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .severity-badge.critical {
            background: #dc2626;
            color: white;
        }

        .severity-badge.warning {
            background: #f59e0b;
            color: white;
        }

        .severity-badge.info {
            background: #3b82f6;
            color: white;
        }

        .severity-badge.success {
            background: #10b981;
            color: white;
        }

        .alert-message {
            color: var(--text-secondary);
            line-height: 1.6;
            margin-bottom: 1rem;
        }

        .alert-meta {
            display: flex;
            gap: 1.5rem;
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-bottom: 1rem;
        }

        .alert-meta i {
            margin-right: 0.25rem;
        }

        .ack-button {
            padding: 0.5rem 1.5rem;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
        }

        .ack-button:hover {
            background: var(--secondary-color);
            transform: translateY(-2px);
        }

        .ack-button:disabled {
            background: #6b7280;
            cursor: not-allowed;
            transform: none;
        }

        .acknowledged-badge {
            background: #10b981;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-size: 0.875rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .no-alerts {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--text-secondary);
        }

        .no-alerts i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
    </style>
</head>

<body>
    <div class="starfield"></div>
    <div class="app-layout"></div>

        <?php include '../includes/sidebar-nav.php'; ?>

    <div class="alerts-container">
        <h1><i class="fas fa-exclamation-triangle"></i> Emergency Alerts</h1>

        <?php if (count($alerts) > 0): ?>
            <?php foreach ($alerts as $alert): ?>
                <div class="alert-card <?php echo $alert['severity']; ?>">
                    <div class="alert-header">
                        <div>
                            <div class="alert-title">
                                <?php
                                $icons = [
                                    'critical' => 'fa-exclamation-circle',
                                    'warning' => 'fa-exclamation-triangle',
                                    'info' => 'fa-info-circle',
                                    'success' => 'fa-check-circle'
                                ];
                                $icon = $icons[$alert['severity']] ?? 'fa-bell';
                                ?>
                                <i class="fas <?php echo $icon; ?>"></i>
                                <?php echo htmlspecialchars($alert['title']); ?>
                            </div>
                        </div>
                        <span class="severity-badge <?php echo $alert['severity']; ?>">
                            <?php echo strtoupper($alert['severity']); ?>
                        </span>
                    </div>

                    <div class="alert-message">
                        <?php echo nl2br(htmlspecialchars($alert['message'])); ?>
                    </div>

                    <div class="alert-meta">
                        <span>
                            <i class="fas fa-user"></i>
                            By: <?php echo htmlspecialchars($alert['created_by_name'] ?? 'System'); ?>
                        </span>
                        <span>
                            <i class="fas fa-clock"></i>
                            <?php echo date('M d, Y h:i A', strtotime($alert['created_at'])); ?>
                        </span>
                        <?php if ($alert['expires_at']): ?>
                            <span>
                                <i class="fas fa-hourglass-end"></i>
                                Expires: <?php echo date('M d, Y', strtotime($alert['expires_at'])); ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <?php if ($alert['requires_acknowledgment']): ?>
                        <?php if ($alert['acknowledged'] > 0): ?>
                            <span class="acknowledged-badge">
                                <i class="fas fa-check"></i> Acknowledged
                            </span>
                        <?php else: ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="alert_id" value="<?php echo $alert['id']; ?>">
                                <button type="submit" name="acknowledge" class="ack-button">
                                    <i class="fas fa-check"></i> Acknowledge Alert
                                </button>
                            </form>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="no-alerts">
                <i class="fas fa-bell-slash"></i>
                <h2>No Active Alerts</h2>
                <p>There are currently no emergency alerts. Check back later.</p>
            </div>
        <?php endif; ?>
    </div>

    <?php include '../includes/sams-bot.php'; ?>

    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/pwa-manager.js"></script>
    <script src="../assets/js/pwa-analytics.js"></script>
</body>

</html>