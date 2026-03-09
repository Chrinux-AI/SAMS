<?php

/**
 * Universal Settings Page
 * Accessible by all roles with role-specific sections
 */
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_parent();

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];
$message = '';
$message_type = '';

// Get user data
$user = db()->fetch("SELECT * FROM users WHERE id = ?", [$user_id]);

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $first_name = sanitize($_POST['first_name']);
    $last_name = sanitize($_POST['last_name']);
    $email = sanitize($_POST['email']);
    $phone = sanitize($_POST['phone'] ?? '');

    db()->execute("
        UPDATE users
        SET first_name = ?, last_name = ?, email = ?, phone = ?
        WHERE id = ?
    ", [$first_name, $last_name, $email, $phone, $user_id]);

    $_SESSION['full_name'] = "$first_name $last_name";
    $message = 'Profile updated successfully!';
    $message_type = 'success';

    $user = db()->fetch("SELECT * FROM users WHERE id = ?", [$user_id]);
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if (!password_verify($current_password, $user['password'])) {
        $message = 'Current password is incorrect';
        $message_type = 'error';
    } elseif ($new_password !== $confirm_password) {
        $message = 'New passwords do not match';
        $message_type = 'error';
    } elseif (strlen($new_password) < 8) {
        $message = 'Password must be at least 8 characters';
        $message_type = 'error';
    } else {
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
        db()->execute("UPDATE users SET password = ? WHERE id = ?", [$hashed, $user_id]);
        $message = 'Password changed successfully!';
        $message_type = 'success';
    }
}

// Handle notification preferences
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_notifications'])) {
    $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
    $sms_notifications = isset($_POST['sms_notifications']) ? 1 : 0;
    $push_notifications = isset($_POST['push_notifications']) ? 1 : 0;

    db()->execute("
        UPDATE users
        SET email_notifications = ?, sms_notifications = ?, push_notifications = ?
        WHERE id = ?
    ", [$email_notifications, $sms_notifications, $push_notifications, $user_id]);

    $message = 'Notification preferences updated!';
    $message_type = 'success';
    $user = db()->fetch("SELECT * FROM users WHERE id = ?", [$user_id]);
}

$page_title = "Settings";
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
    <title><?php echo $page_title; ?> - Attendance AI</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="../assets/css/professional-ui.css" rel="stylesheet">
    <link href="../assets/css/pwa-styles.css" rel="stylesheet">
    <style>
        .settings-container {
            padding: 2rem;
            max-width: 1200px;
            margin: 0 auto;
        }

        .settings-grid {
            display: grid;
            gap: 2rem;
        }

        .settings-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .settings-card h2 {
            color: var(--primary-color);
            margin-bottom: 1.5rem;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            font-weight: 500;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 6px;
            color: var(--text-primary);
            font-size: 1rem;
            transition: all 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 123, 255, 0.3);
        }

        .alert {
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid #10b981;
            color: #10b981;
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid #ef4444;
            color: #ef4444;
        }

        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 30px;
        }

        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 30px;
        }

        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 22px;
            width: 22px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked+.toggle-slider {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        }

        input:checked+.toggle-slider:before {
            transform: translateX(30px);
        }

        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: white;
            margin: 0 auto 1.5rem;
        }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .stat-box {
            background: var(--bg-secondary);
            padding: 1rem;
            border-radius: 6px;
            text-align: center;
        }

        .stat-box h3 {
            color: var(--primary-color);
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }

        .stat-box p {
            color: var(--text-secondary);
            font-size: 0.875rem;
        }

        .role-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .role-admin {
            background: #ef4444;
            color: white;
        }

        .role-teacher {
            background: #3b82f6;
            color: white;
        }

        .role-student {
            background: #10b981;
            color: white;
        }

        .role-parent {
            background: #f59e0b;
            color: white;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .settings-card {
            animation: fadeIn 0.6s ease-out;
        }
    </style>
</head>

<body>
    <div class="starfield"></div>
    <div class="app-layout"></div>

<?php include '../includes/sidebar-nav.php'; ?>

    <div class="settings-container">
        <h1><i class="fas fa-cog"></i> Settings</h1>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <div class="settings-grid">
            <!-- Profile Settings -->
            <div class="settings-card">
                <h2><i class="fas fa-user-circle"></i> Profile Information</h2>

                <div class="profile-avatar">
                    <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                </div>

                <div style="text-align: center; margin-bottom: 1.5rem;">
                    <h3 style="color: var(--text-primary);"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h3>
                    <span class="role-badge role-<?php echo $user_role; ?>"><?php echo ucfirst($user_role); ?></span>
                </div>

                <form method="POST">
                    <div class="form-group">
                        <label>First Name</label>
                        <input type="text" name="first_name" class="form-control" value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Last Name</label>
                        <input type="text" name="last_name" class="form-control" value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="tel" name="phone" class="form-control" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                    </div>

                    <button type="submit" name="update_profile" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </form>
            </div>

            <!-- Security Settings -->
            <div class="settings-card">
                <h2><i class="fas fa-shield-alt"></i> Security</h2>

                <form method="POST">
                    <div class="form-group">
                        <label>Current Password</label>
                        <input type="password" name="current_password" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label>New Password</label>
                        <input type="password" name="new_password" class="form-control" required>
                        <small style="color: var(--text-secondary);">Minimum 8 characters</small>
                    </div>

                    <div class="form-group">
                        <label>Confirm New Password</label>
                        <input type="password" name="confirm_password" class="form-control" required>
                    </div>

                    <button type="submit" name="change_password" class="btn btn-primary">
                        <i class="fas fa-key"></i> Change Password
                    </button>
                </form>
            </div>

            <!-- Notification Preferences -->
            <div class="settings-card">
                <h2><i class="fas fa-bell"></i> Notifications</h2>

                <form method="POST">
                    <div class="form-group" style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <label style="margin: 0;">Email Notifications</label>
                            <small style="color: var(--text-secondary); display: block;">Receive updates via email</small>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" name="email_notifications" <?php echo ($user['email_notifications'] ?? 1) ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>

                    <div class="form-group" style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <label style="margin: 0;">SMS Notifications</label>
                            <small style="color: var(--text-secondary); display: block;">Receive text messages</small>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" name="sms_notifications" <?php echo ($user['sms_notifications'] ?? 0) ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>

                    <div class="form-group" style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <label style="margin: 0;">Push Notifications</label>
                            <small style="color: var(--text-secondary); display: block;">Browser notifications</small>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" name="push_notifications" <?php echo ($user['push_notifications'] ?? 1) ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>

                    <button type="submit" name="update_notifications" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Preferences
                    </button>
                </form>
            </div>

            <!-- Account Stats -->
            <div class="settings-card">
                <h2><i class="fas fa-chart-bar"></i> Account Overview</h2>

                <div class="stats-row">
                    <div class="stat-box">
                        <h3><?php echo htmlspecialchars($user['username']); ?></h3>
                        <p>Username</p>
                    </div>
                    <div class="stat-box">
                        <h3><?php echo date('M Y', strtotime($user['created_at'])); ?></h3>
                        <p>Member Since</p>
                    </div>
                    <div class="stat-box">
                        <h3><?php echo ucfirst($user['status']); ?></h3>
                        <p>Account Status</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include '../includes/sams-bot.php'; ?>

    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/pwa-manager.js"></script>
    <script src="../assets/js/pwa-analytics.js"></script>
</body>

</html>