<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/database.php';

$errors = [];
$message = '';
$message_type = '';

// Check if admin already exists only on form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $admin_exists = db()->fetchOne("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
    if ($admin_exists) {
        $errors[] = 'An admin account already exists. Please delete it first or login.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($errors)) {
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

    if (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters';
    }

    if ($password !== $confirm_password) {
        $errors[] = 'Passwords do not match';
    }

    if (empty($errors)) {
        try {
            // Clear ALL existing data
            db()->query("SET FOREIGN_KEY_CHECKS = 0");

            // Truncate all tables
            db()->query("TRUNCATE TABLE attendance");
            db()->query("TRUNCATE TABLE class_enrollments");
            db()->query("TRUNCATE TABLE classes");
            db()->query("TRUNCATE TABLE messages");
            db()->query("TRUNCATE TABLE notifications");
            db()->query("TRUNCATE TABLE parents");
            db()->query("TRUNCATE TABLE students");
            db()->query("TRUNCATE TABLE teachers");
            db()->query("TRUNCATE TABLE users");

            db()->query("SET FOREIGN_KEY_CHECKS = 1");

            // Generate verification token
            $verification_token = bin2hex(random_bytes(32));

            // Create admin account
            $admin_data = [
                'full_name' => $full_name,
                'email' => $email,
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'role' => 'admin',
                'status' => 'active',
                'email_verified' => 1, // Admin is auto-verified
                'approved' => 1,
                'email_verification_token' => null,
                'created_at' => date('Y-m-d H:i:s')
            ];

            $admin_id = db()->insert('users', $admin_data);

            if ($admin_id) {
                // Send welcome email to admin
                $to = $email;
                $subject = "Admin Account Created - Attendance System";
                $login_url = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/login.php';
                $current_year = date('Y');
                $email_message = <<<HTML
                <html>
                <head>
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
                $errors[] = 'Failed to create admin account. Please try again.';
            }
        } catch (Exception $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Admin Account - Attendance System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .setup-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 500px;
            width: 100%;
            padding: 40px;
            backdrop-filter: blur(10px);
        }

        .setup-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .setup-header h1 {
            color: #333;
            font-size: 28px;
            margin-bottom: 10px;
        }

        .setup-header p {
            color: #666;
            font-size: 14px;
        }

        .warning-box {
            background: #fff3cd;
            border: 2px solid #ffc107;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 25px;
            color: #856404;
        }

        .warning-box strong {
            display: block;
            margin-bottom: 5px;
            color: #d39e00;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            color: #333;
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
        }

        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        /* Password toggle wrapper */
        .pw-wrapper {
            position: relative;
        }

        .pw-toggle {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: transparent;
            border: none;
            cursor: pointer;
            font-size: 16px;
            color: #667eea;
            padding: 4px;
        }

        .pw-input {
            padding-left: 40px;
            /* space for the eye icon */
        }

        .error-list {
            background: #f8d7da;
            border: 2px solid #f5c6cb;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
        }

        .error-list ul {
            list-style: none;
        }

        .error-list li {
            color: #721c24;
            padding: 5px 0;
        }

        .error-list li:before {
            content: "⚠️ ";
            margin-right: 5px;
        }

        .success-message {
            background: #d4edda;
            border: 2px solid #c3e6cb;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            color: #155724;
            text-align: center;
        }

        .success-message:before {
            content: "✅ ";
        }

        .submit-btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.4);
        }

        .submit-btn:active {
            transform: translateY(0);
        }

        .info-text {
            text-align: center;
            margin-top: 20px;
            color: #666;
            font-size: 13px;
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

    <div class="setup-container">
        <div class="setup-header">
            <h1>🔧 Setup Admin Account</h1>
            <p>Create the first administrator account</p>
        </div>

        <div class="warning-box">
            <strong>⚠️ WARNING:</strong>
            This will delete ALL existing accounts and data from the system. This action cannot be undone!
        </div>

        <?php if (!empty($errors)): ?>
            <div class="error-list">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($message_type === 'success'): ?>
            <div class="success-message">
                <?php echo htmlspecialchars($message); ?>
                <p style="margin-top: 10px; font-size: 12px;">Redirecting to login...</p>
            </div>
        <?php else: ?>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="full_name">Full Name</label>
                    <input type="text" id="full_name" name="full_name" required
                        value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>"
                        placeholder="Enter your full name">
                </div>

                <div class="form-group">
                    <label for="email">Email Address (Gmail recommended)</label>
                    <input type="email" id="email" name="email" required
                        value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                        placeholder="your.email@gmail.com">
                </div>

                <div class="form-group pw-wrapper">
                    <label for="password">Password</label>
                    <button type="button" class="pw-toggle" aria-label="Toggle password visibility" onclick="togglePassword('password', this)">
                        <!-- eye SVG -->
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 5C7 5 2.73 8.11 1 12c1.73 3.89 6 7 11 7s9.27-3.11 11-7c-1.73-3.89-6-7-11-7z" stroke="#667eea" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round" />
                            <circle cx="12" cy="12" r="3" stroke="#667eea" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </button>
                    <input type="password" id="password" name="password" class="pw-input" required
                        placeholder="At least 6 characters">
                </div>

                <div class="form-group pw-wrapper">
                    <label for="confirm_password">Confirm Password</label>
                    <button type="button" class="pw-toggle" aria-label="Toggle password visibility" onclick="togglePassword('confirm_password', this)">
                        <!-- eye SVG -->
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 5C7 5 2.73 8.11 1 12c1.73 3.89 6 7 11 7s9.27-3.11 11-7c-1.73-3.89-6-7-11-7z" stroke="#667eea" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round" />
                            <circle cx="12" cy="12" r="3" stroke="#667eea" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </button>
                    <input type="password" id="confirm_password" name="confirm_password" class="pw-input" required
                        placeholder="Re-enter password">
                </div>

                <button type="submit" class="submit-btn">Create Admin & Clear Database</button>
            </form>

            <p class="info-text">
                This page will self-destruct after creating the admin account.
            </p>
        <?php endif; ?>
    </div>

    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/pwa-manager.js"></script>
    <script src="../assets/js/pwa-analytics.js"></script>
</body>

</html>
<script>
    // Toggle password visibility for setup-admin page
    function togglePassword(fieldId, btn) {
        var input = document.getElementById(fieldId);
        if (!input) return;
        if (input.type === 'password') {
            input.type = 'text';
            // change icon to eye-off (simple X overlay)
            btn.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M17.94 17.94L6.06 6.06" stroke="#667eea" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/><path d="M12 5C7 5 2.73 8.11 1 12c1.73 3.89 6 7 11 7 1.7 0 3.31-.33 4.78-.93" stroke="#667eea" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
        } else {
            input.type = 'password';
            btn.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 5C7 5 2.73 8.11 1 12c1.73 3.89 6 7 11 7s9.27-3.11 11-7c-1.73-3.89-6-7-11-7z" stroke="#667eea" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/><circle cx="12" cy="12" r="3" stroke="#667eea" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
        }
    }
</script>
