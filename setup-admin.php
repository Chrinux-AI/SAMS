<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/database.php';

// SECURITY: Block access if an admin already exists
try {
    $adminExists = db()->fetchOne("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
    if ($adminExists) {
        http_response_code(403);
        die('<h1>Access Denied</h1><p>An administrator account already exists. This setup page is disabled.</p><p><a href="login.php">Go to Login</a></p>');
    }
} catch (Exception $e) {
    // Table may not exist yet - allow setup to continue
}

$errors = [];
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $full_name = trim($_POST['full_name'] ?? '');

    // Validation
    if (empty($email) || empty($password) || empty($full_name)) {
        $errors[] = 'All fields are required';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format';
    }

    if (strlen($password) < PASSWORD_MIN_LENGTH) {
        $errors[] = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters';
    }

    if ($password !== $confirm_password) {
        $errors[] = 'Passwords do not match';
    }

    if (empty($errors)) {
        try {
            // Keep errors logged, not displayed to users.
            ini_set('display_errors', 0);
            error_reporting(E_ALL);

            // Clear ALL existing data
            db()->query("SET FOREIGN_KEY_CHECKS = 0");

            // Truncate all tables (use DELETE if TRUNCATE fails)
            $tables = [
                'attendance_records',
                'class_enrollments',
                'classes',
                'messages',
                'notifications',
                'parents',
                'students',
                'teachers',
                'users'
            ];

            foreach ($tables as $table) {
                try {
                    db()->query("TRUNCATE TABLE $table");
                } catch (Exception $e) {
                    // If TRUNCATE fails, try DELETE
                    db()->query("DELETE FROM $table");
                }
            }

            db()->query("SET FOREIGN_KEY_CHECKS = 1");

            // Create admin account
            $password_hash = password_hash($password, PASSWORD_DEFAULT);

            // Split full name into first and last name
            $name_parts = explode(' ', trim($full_name), 2);
            $first_name = $name_parts[0];
            $last_name = isset($name_parts[1]) ? $name_parts[1] : '';

            // Use raw SQL to ensure compatibility with all column variations
            $sql = "INSERT INTO users (username, first_name, last_name, email, password_hash, role, status, email_verified, approved, created_at)
                    VALUES (:username, :first_name, :last_name, :email, :password_hash, :role, :status, :email_verified, :approved, :created_at)";

            $params = [
                'username' => strtolower(str_replace(' ', '', $full_name)), // Generate username from name
                'first_name' => $first_name,
                'last_name' => $last_name,
                'email' => $email,
                'password_hash' => $password_hash,
                'role' => 'admin',
                'status' => 'active',
                'email_verified' => 1,
                'approved' => 1,
                'created_at' => date('Y-m-d H:i:s')
            ];

            $stmt = db()->query($sql, $params);
            $admin_id = $stmt ? db()->getConnection()->lastInsertId() : false;

            if ($admin_id) {
                // Send welcome email to admin
                $to = $email;
                $subject = "Admin Account Created - Attendance System";
                $login_url = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/login.php';
                $current_year = date('Y');

                $email_message = <<<HTML
                <html>
                <head><?php include_once "includes/favicon-loader.php"; ?>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <title>Admin Account Created</title>
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                        .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
                        .button { display: inline-block; background: #00BFFF; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                        .footer { text-align: center; margin-top: 20px; color: #666; font-size: 12px; }
                    </style>
                </head>
                <body>
                    <div class="container">
                        <div class="header">
                            <h1>Admin Account Created</h1>
                        </div>
                        <div class="content">
                            <p>Hello <strong>{$full_name}</strong>,</p>
                            <p>Your administrator account has been successfully created for the Attendance Management System.</p>

                            <h3>Account Details:</h3>
                            <ul>
                                <li><strong>Email:</strong> {$email}</li>
                                <li><strong>Role:</strong> Administrator</li>
                                <li><strong>Status:</strong> Active</li>
                            </ul>

                            <p>You can now login to the system and manage users, classes, and attendance records.</p>

                            <p style="text-align: center;">
                                <a href="{$login_url}" class="button">Login to Dashboard</a>
                            </p>

                            <p><strong>Important:</strong> All previous data has been cleared from the system. You are starting fresh.</p>
                            <div class="footer">
                                <p>This is an automated message from the Attendance Management System.</p>
                                <p>&copy; {$current_year} Attendance System. All rights reserved.</p>
                            </div>
                        </div>
                    </div>
                </body>
                </html>
HTML;

                $headers = "MIME-Version: 1.0" . "\r\n";
                $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
                $headers .= "From: Attendance System <noreply@" . $_SERVER['HTTP_HOST'] . ">" . "\r\n";

                @mail($to, $subject, $email_message, $headers);

                $message = 'Admin account created successfully! All previous data has been cleared. You can now login.';
                $message_type = 'success';

                // Self-destruct: delete this file after successful setup
                @unlink(__FILE__);

                // Redirect to login after 3 seconds
                header("refresh:3;url=login.php");
            } else {
                // Get PDO error info if available
                $errorInfo = db()->getConnection()->errorInfo();
                $errors[] = 'Failed to create admin account. Database error: ' . ($errorInfo[2] ?? 'Unknown error');
            }
        } catch (Exception $e) {
            $errors[] = 'Database error: ' . $e->getMessage() . ' (Code: ' . $e->getCode() . ')';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head><?php include_once "includes/favicon-loader.php"; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Admin Account - Attendance System</title>

    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Orbitron:wght@500;700;900&display=swap" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Cyberpunk UI -->
    <link href="assets/css/cyberpunk-ui.css" rel="stylesheet">

    <style>
        .setup-container {
            max-width: 550px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        .setup-card {
            background: rgba(30, 30, 30, 0.85);
            border: 2px solid var(--cyber-cyan);
            border-radius: 15px;
            padding: 40px;
            box-shadow: 0 0 40px rgba(0, 191, 255, 0.3);
            backdrop-filter: blur(10px);
        }

        .setup-header {
            text-align: center;
            margin-bottom: 35px;
        }

        .setup-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 20px;
            background: linear-gradient(135deg, var(--cyber-cyan), var(--hologram-purple));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            color: var(--text-primary);
            box-shadow: 0 0 30px rgba(0, 191, 255, 0.5);
        }

        .setup-title {
            font-family: 'Orbitron', sans-serif;
            font-size: 2rem;
            color: var(--cyber-cyan);
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 2px;
        }

        .setup-subtitle {
            color: var(--text-secondary);
            font-size: 1rem;
        }

        .warning-box {
            background: rgba(255, 69, 0, 0.1);
            border: 2px solid var(--cyber-red);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
        }

        .warning-box strong {
            display: block;
            color: var(--cyber-red);
            margin-bottom: 8px;
            font-size: 1.1rem;
        }

        .warning-box p {
            color: var(--text-secondary);
            margin: 0;
        }

        .cyber-form-group {
            margin-bottom: 25px;
        }

        .cyber-label {
            display: block;
            color: var(--cyber-cyan);
            font-weight: 600;
            margin-bottom: 10px;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .cyber-label i {
            margin-right: 8px;
        }

        .pw-wrapper {
            position: relative;
        }

        .cyber-input {
            width: 100%;
            padding: 14px 18px;
            background: rgba(0, 191, 255, 0.05);
            border: 2px solid rgba(0, 191, 255, 0.3);
            border-radius: 8px;
            color: var(--text-primary);
            font-size: 1rem;
            font-family: 'Inter', sans-serif;
            transition: all 0.3s;
        }

        .cyber-input:focus {
            outline: none;
            border-color: var(--cyber-cyan);
            background: rgba(0, 191, 255, 0.1);
            box-shadow: 0 0 20px rgba(0, 191, 255, 0.2);
        }

        .cyber-input.pw-input {
            padding-right: 45px;
        }

        .pw-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: transparent;
            border: none;
            cursor: pointer;
            color: var(--cyber-cyan);
            padding: 8px;
            transition: all 0.3s;
        }

        .pw-toggle:hover {
            color: var(--neon-green);
            transform: translateY(-50%) scale(1.1);
        }

        .cyber-btn-submit {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, var(--cyber-cyan), var(--hologram-purple));
            color: var(--text-primary);
            border: none;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: 700;
            font-family: 'Orbitron', sans-serif;
            text-transform: uppercase;
            letter-spacing: 2px;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 0 20px rgba(0, 191, 255, 0.3);
        }

        .cyber-btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 0 40px rgba(0, 191, 255, 0.6);
        }

        .cyber-btn-submit:active {
            transform: translateY(0);
        }

        .info-text {
            text-align: center;
            margin-top: 25px;
            color: var(--text-muted);
            font-size: 0.85rem;
        }

        .cyber-alert {
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
        }

        .cyber-alert-error {
            background: rgba(255, 69, 0, 0.1);
            border: 2px solid var(--cyber-red);
        }

        .cyber-alert-error ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .cyber-alert-error li {
            color: var(--text-primary);
            padding: 5px 0;
        }

        .cyber-alert-error li:before {
            content: "⚠️ ";
            margin-right: 8px;
        }

        .cyber-alert-success {
            background: rgba(0, 255, 127, 0.1);
            border: 2px solid var(--neon-green);
            text-align: center;
        }

        .cyber-alert-success p {
            color: var(--neon-green);
            margin: 0;
            font-weight: 600;
        }
    </style>
</head>

<body class="cyber-bg">
    <div class="starfield"></div>
    <div class="cyber-grid"></div>

    <div class="starfield"></div>
    <div class="cyber-grid"></div>

    <div class="starfield"></div>
    <div class="cyber-grid"></div>

    <!-- Cyberpunk Background -->
    <div class="cyber-bg">
        <div class="starfield"></div>
    </div>
    <div class="cyber-grid"></div>

    <div class="setup-container">
        <div class="setup-card">
            <div class="setup-header">
                <div class="setup-icon">
                    <i class="fas fa-user-shield"></i>
                </div>
                <h1 class="setup-title">Setup Admin Account</h1>
                <p class="setup-subtitle">Initialize the Attendance System</p>
            </div>

            <div class="warning-box">
                <strong><i class="fas fa-exclamation-triangle"></i> WARNING</strong>
                <p>This will delete ALL existing accounts and data from the system. This action cannot be undone!</p>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="cyber-alert cyber-alert-error">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ($message_type === 'success'): ?>
                <div class="cyber-alert cyber-alert-success">
                    <p><?php echo htmlspecialchars($message); ?></p>
                    <p style="margin-top: 10px; font-size: 0.85rem; color: var(--text-secondary);">Redirecting to login...</p>
                </div>
            <?php else: ?>
                <form method="POST" action="">
                    <div class="cyber-form-group">
                        <label class="cyber-label" for="full_name">
                            <i class="fas fa-user"></i> Full Name
                        </label>
                        <input type="text" id="full_name" name="full_name" class="cyber-input" required
                            value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>"
                            placeholder="Enter your full name">
                    </div>

                    <div class="cyber-form-group">
                        <label class="cyber-label" for="email">
                            <i class="fas fa-envelope"></i> Email Address
                        </label>
                        <input type="email" id="email" name="email" class="cyber-input" required
                            value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                            placeholder="admin@example.com">
                    </div>

                    <div class="cyber-form-group pw-wrapper">
                        <label class="cyber-label" for="password">
                            <i class="fas fa-lock"></i> Password
                        </label>
                        <input type="password" id="password" name="password" class="cyber-input pw-input" required
                            placeholder="At least 6 characters">
                        <button type="button" class="pw-toggle" aria-label="Toggle password visibility" onclick="togglePassword('password', this)">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>

                    <div class="cyber-form-group pw-wrapper">
                        <label class="cyber-label" for="confirm_password">
                            <i class="fas fa-lock"></i> Confirm Password
                        </label>
                        <input type="password" id="confirm_password" name="confirm_password" class="cyber-input pw-input" required
                            placeholder="Re-enter password">
                        <button type="button" class="pw-toggle" aria-label="Toggle password visibility" onclick="togglePassword('confirm_password', this)">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>

                    <button type="submit" class="cyber-btn-submit">
                        <i class="fas fa-rocket"></i> Create Admin & Initialize System
                    </button>
                </form>

                <p class="info-text">
                    <i class="fas fa-info-circle"></i> This page will self-destruct after creating the admin account.
                </p>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function togglePassword(fieldId, btn) {
            var input = document.getElementById(fieldId);
            if (!input) return;

            var icon = btn.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.className = 'fas fa-eye-slash';
            } else {
                input.type = 'password';
                icon.className = 'fas fa-eye';
            }
        }
    </script>

    <script src="assets/js/main.js"></script>
    <script src="assets/js/pwa-manager.js"></script>
    <script src="assets/js/pwa-analytics.js"></script>
</body>

</html>
