<?php

/**
 * SAMS - Bursar Settings Page
 * Modern UI with profile avatar, account overview, theme selection
 */

require_once '../includes/config.php';
require_once INCLUDES_PATH . '/functions.php';
require_once INCLUDES_PATH . '/database.php';

require_role('bursar');

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? 'bursar';
$message = '';
$message_type = '';

$user = db()->fetch("SELECT * FROM users WHERE id = ?", [$user_id]);

// Handle Profile Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid CSRF token.';
        $message_type = 'error';
    } else {
        $first_name = sanitize($_POST['first_name'] ?? '');
        $last_name = sanitize($_POST['last_name'] ?? '');
        $email = filter_var(sanitize($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
        $phone = sanitize($_POST['phone'] ?? '');

        if (empty($first_name) || empty($last_name) || empty($email)) {
            $message = 'Please fill in all required fields';
            $message_type = 'error';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = 'Please enter a valid email address';
            $message_type = 'error';
        } else {
            db()->query("UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ? WHERE id = ?",
                [$first_name, $last_name, $email, $phone, $user_id]);
            $_SESSION['full_name'] = "$first_name $last_name";
            $message = 'Profile updated successfully!';
            $message_type = 'success';
            $user = db()->fetch("SELECT * FROM users WHERE id = ?", [$user_id]);
        }
    }
}

// Handle Password Change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid CSRF token.';
        $message_type = 'error';
    } else {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $user_check = db()->fetch("SELECT password FROM users WHERE id = ?", [$user_id]);

        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $message = 'All password fields are required';
            $message_type = 'error';
        } elseif (!password_verify($current_password, $user_check['password'])) {
            $message = 'Current password is incorrect';
            $message_type = 'error';
        } elseif ($new_password !== $confirm_password) {
            $message = 'New passwords do not match';
            $message_type = 'error';
        } elseif (strlen($new_password) < 8) {
            $message = 'Password must be at least 8 characters long';
            $message_type = 'error';
        } else {
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            db()->query("UPDATE users SET password = ? WHERE id = ?", [$hashed, $user_id]);
            $message = 'Password changed successfully!';
            $message_type = 'success';
        }
    }
}

// Handle notification preferences
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_notifications'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid CSRF token.';
        $message_type = 'error';
    } else {
        $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
        $sms_notifications = isset($_POST['sms_notifications']) ? 1 : 0;
        $push_notifications = isset($_POST['push_notifications']) ? 1 : 0;
        db()->query("UPDATE users SET email_notifications = ?, sms_notifications = ?, push_notifications = ? WHERE id = ?",
            [$email_notifications, $sms_notifications, $push_notifications, $user_id]);
        $message = 'Notification preferences updated!';
        $message_type = 'success';
        $user = db()->fetch("SELECT * FROM users WHERE id = ?", [$user_id]);
    }
}

$page_title = "Bursar Settings";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <script src="../assets/js/theme-loader.js"></script>
    <link rel="manifest" href="/attendance/manifest.json">
    <meta name="theme-color" content="#00BFFF">
    <link rel="apple-touch-icon" href="/attendance/assets/images/icons/icon-192x192.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - SAMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="../assets/css/professional-ui.css" rel="stylesheet">
    <?php include '../includes/sams-head-bootstrap.php'; ?>

    <link href="../assets/css/pwa-styles.css" rel="stylesheet">
    <?php include __DIR__ . '/../includes/settings-styles.php'; ?>
</head>
<body>
    <?php include '../includes/sidebar-nav.php'; ?>
    <div class="settings-container">
        <h1><i class="fas fa-cog"></i> Settings</h1>
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        <div class="settings-grid">
            <?php include __DIR__ . '/../includes/settings-profile-card.php'; ?>
            <?php include __DIR__ . '/../includes/settings-security-card.php'; ?>
            <?php include __DIR__ . '/../includes/settings-notifications-card.php'; ?>
            <?php include __DIR__ . '/../includes/settings-overview-card.php'; ?>
            <?php include __DIR__ . '/../includes/settings-theme-card.php'; ?>
        </div>
    </div>
    <?php include '../includes/sams-bot.php'; ?>
    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/pwa-manager.js"></script>
    <script src="../assets/js/pwa-analytics.js"></script>
    <?php include __DIR__ . '/../includes/settings-theme-js.php'; ?>
</body>
</html>